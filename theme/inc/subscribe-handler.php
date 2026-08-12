<?php
/**
 * Area 51 Reunion — Subscribe AJAX Handler
 * Handles wp_ajax_nopriv_area51_subscribe and wp_ajax_area51_subscribe.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add a subscriber to Kit via REST API v4.
 * Returns true if subscriber was created/updated; false on failure.
 * If $form_id is 0, subscriber is created in Kit but not assigned to a form.
 */
function area51_kit_add_subscriber( string $email, string $api_key, int $form_id = 0 ): bool {
    // Step 1: Create or update subscriber
    $response = wp_remote_post( 'https://api.kit.com/v4/subscribers', [
        'headers' => [
            'Content-Type'  => 'application/json',
            'X-Kit-Api-Key' => $api_key,
        ],
        'body'    => wp_json_encode( [ 'email_address' => $email ] ),
        'timeout' => 10,
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( 'Area51 Subscribe: Kit API Step 1 error — ' . $response->get_error_message() );
        return false;
    }

    $status = wp_remote_retrieve_response_code( $response );
    if ( $status !== 200 && $status !== 201 ) {
        error_log( 'Area51 Subscribe: Kit API Step 1 unexpected status ' . $status );
        return false;
    }

    // Step 2: Add to form (if form ID is configured)
    if ( $form_id > 0 ) {
        $form_response = wp_remote_post(
            'https://api.kit.com/v4/forms/' . (int) $form_id . '/subscribers',
            [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'X-Kit-Api-Key' => $api_key,
                ],
                'body'    => wp_json_encode( [ 'email_address' => $email ] ),
                'timeout' => 10,
            ]
        );

        if ( is_wp_error( $form_response ) ) {
            error_log( 'Area51 Subscribe: Kit API Step 2 (form) error — ' . $form_response->get_error_message() );
            // Subscriber was created in Step 1; Step 2 failure is non-fatal
        }
    }

    return true;
}

/**
 * AJAX handler: area51_subscribe
 * Validates nonce, validates email, sends Resend confirmation, calls Kit.
 */
function area51_handle_subscribe(): void {
    // Nonce verification (calls wp_die() on failure automatically)
    check_ajax_referer( 'area51_subscribe_nonce', 'nonce' );

    $email  = isset( $_POST['email'] )  ? sanitize_email( wp_unslash( $_POST['email'] ) )            : '';
    $name   = isset( $_POST['name'] )   ? sanitize_text_field( wp_unslash( $_POST['name'] ) )        : '';
    $memory = isset( $_POST['memory'] ) ? sanitize_textarea_field( wp_unslash( $_POST['memory'] ) )  : '';

    if ( ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Invalid email address. Please try again.' ] );
        return;
    }

    // Send Resend confirmation email via wp_mail()
    $settings   = get_option( 'resend_settings', [] );
    $from_email = $settings['from_email'] ?? '';
    $from_name  = $settings['from_name']  ?? '';
    $headers    = [
        'Content-Type: text/plain; charset=UTF-8',
        "From: {$from_name} <{$from_email}>",
    ];
    $subject = 'Area 51 — 30 Year Reunion. One Night Only. Halloween.';
    $body    = "It's been a long time — before smartphones, before the internet took over. This Halloween, for one night only, we're back from the dead — and we would love to see you again (or meet you for the first time) to fill your ears, move your feet and feed your head!\n\nWe won't flood your inbox. You'll hear from us only when the site has something new or when the party date and venue are declassified.\n\n— Area 51\n  (Sandro, John, Dan)";

    // Layer 19: personalize the greeting when a name was given. $subject stays
    // unchanged in both branches; $body stays byte-identical to the pre-Layer-19
    // value when no name is given (the branch below is simply skipped).
    if ( '' !== $name ) {
        $body = "Hey {$name} — thanks for signing up.\n\n" . $body;
    }

    $mail_sent = wp_mail( $email, $subject, $body, $headers );

    if ( ! $mail_sent ) {
        error_log( 'Area51 Subscribe: wp_mail() returned false for ' . $email );
        wp_send_json_error( [ 'message' => 'Subscription failed — please try again.' ] );
        return;
    }

    // Layer 14: Upsert unified contact record. Runs only after Resend confirmation
    // succeeds — preserves this handler's existing email-first atomicity discipline.
    // Layer 19: display_name is only included in $fields when a name was given —
    // array_key_exists-gated pattern (contact-post-type.php) — so an email-only
    // resubmission never blanks a display_name a prior Clearance-path upsert set.
    $contact_fields = [];
    if ( '' !== $name ) {
        $contact_fields['display_name'] = $name;
    }
    area51_upsert_contact( $email, 'subscribe', $contact_fields );

    // Layer 19: create a pending incident_report only when a memory was given —
    // same email-first-atomicity position as the area51_upsert_contact() call
    // above (i.e. only after wp_mail() has already succeeded).
    if ( '' !== $memory ) {
        area51_create_incident_report_post( [
            'ir_what_occurred'        => $memory,
            'ir_source_contact_email' => $email,
        ] );
    }

    // Add to Kit (secondary — failure does not block success response)
    $kit_settings = get_option( '_wp_convertkit_settings', [] );
    $kit_api_key  = $kit_settings['api_key'] ?? '';
    $kit_form_id  = (int) get_option( 'area51_kit_form_id', 0 );

    if ( ! empty( $kit_api_key ) ) {
        area51_kit_add_subscriber( $email, $kit_api_key, $kit_form_id );
    } else {
        error_log( 'Area51 Subscribe: Kit API key not configured — subscriber not added to Kit.' );
    }

    wp_send_json_success( [
        'message' => 'Your subscription has been filed. Confirmation incoming.',
    ] );
}
