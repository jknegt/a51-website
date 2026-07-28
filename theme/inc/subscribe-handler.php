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

    $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

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
    $subject = '[CLASSIFIED] Subscription Confirmed — Area 51 Reunion';
    $body    = "Your request for subscription has been processed.\n\nYou will be notified when subjects are declassified.\n\nThis message is classified. Do not share.\n\n— Area 51 Reunion Command";

    $mail_sent = wp_mail( $email, $subject, $body, $headers );

    if ( ! $mail_sent ) {
        error_log( 'Area51 Subscribe: wp_mail() returned false for ' . $email );
        wp_send_json_error( [ 'message' => 'Subscription failed — please try again.' ] );
        return;
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
        'message' => 'Your clearance request has been filed. Confirmation incoming.',
    ] );
}
