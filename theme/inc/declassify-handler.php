<?php
/**
 * Area 51 Reunion — DECLASSIFY AJAX Handler
 * Layer 03: Flips a missing_person card from classified to declassified.
 *
 * Execution order (email-first atomicity):
 *   1. Validate nonce + permissions
 *   2. Validate post_id + post type + current status
 *   3. Retrieve Kit subscriber list (with graceful degradation)
 *   4. Send email blast to all recipients
 *   5. On email success ONLY: update status meta + increment located_count
 *   6. Return JSON success/error
 *
 * If wp_mail() fails for all recipients, the card does NOT flip and the counter
 * does NOT increment. This is the non-negotiable email-first atomicity requirement.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ==========================================================================
   Kit subscriber retrieval (with graceful degradation)
   ========================================================================== */

/**
 * Retrieve all active Kit subscribers via cursor-paginated API calls.
 * Falls back to admin-only email if Kit API key is absent or API returns non-200.
 *
 * @return string[] Array of email addresses.
 */
function area51_get_kit_subscribers(): array {
    $settings = get_option( '_wp_convertkit_settings', [] );
    $api_key  = $settings['api_key'] ?? '';

    if ( empty( $api_key ) ) {
        error_log( '[Area51] Kit API key not configured — degrading to admin-only email blast.' );
        return [ get_option( 'area51_admin_email', '' ) ];
    }

    $emails  = [];
    $cursor  = null;
    $page    = 0;

    do {
        $page++;
        $url = 'https://api.kit.com/v4/subscribers?status=active&per_page=500';
        if ( $cursor ) {
            $url .= '&after=' . rawurlencode( $cursor );
        }

        $response = wp_remote_get( $url, [
            'timeout' => 15,
            'headers' => [
                'X-Kit-Api-Key' => $api_key,
                'Accept'        => 'application/json',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( '[Area51] Kit API wp_error on page ' . $page . ': ' . $response->get_error_message() );
            // Degrade gracefully — return what we have so far (or admin if nothing)
            if ( empty( $emails ) ) {
                return [ get_option( 'area51_admin_email', '' ) ];
            }
            break;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== (int) $status_code ) {
            error_log( '[Area51] Kit API returned HTTP ' . $status_code . ' on page ' . $page . ' — degrading to admin-only.' );
            if ( empty( $emails ) ) {
                return [ get_option( 'area51_admin_email', '' ) ];
            }
            break;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $subscribers = $body['subscribers'] ?? [];

        foreach ( $subscribers as $subscriber ) {
            $email = $subscriber['email_address'] ?? '';
            if ( ! empty( $email ) ) {
                $emails[] = $email;
            }
        }

        // Cursor pagination
        $pagination    = $body['pagination'] ?? [];
        $has_next_page = $pagination['has_next_page'] ?? false;
        $cursor        = $pagination['end_cursor'] ?? null;

    } while ( $has_next_page && $cursor );

    if ( empty( $emails ) ) {
        error_log( '[Area51] Kit API returned no subscribers — degrading to admin-only email blast.' );
        return [ get_option( 'area51_admin_email', '' ) ];
    }

    return $emails;
}

/* ==========================================================================
   DECLASSIFY AJAX handler
   ========================================================================== */

function area51_handle_declassify_card(): void {
    // 1. Validate nonce
    check_ajax_referer( 'area51_declassify_nonce', 'nonce' );

    // 2. Permission check
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
    }

    // 3. Validate post_id
    $post_id = absint( $_POST['post_id'] ?? 0 );
    if ( $post_id <= 0 ) {
        wp_send_json_error( [ 'message' => 'Invalid post ID.' ] );
    }

    // 4. Verify post type
    if ( get_post_type( $post_id ) !== 'missing_person' ) {
        wp_send_json_error( [ 'message' => 'Invalid post type.' ] );
    }

    // 5. Check current status
    $current_status = get_post_meta( $post_id, 'status', true );
    if ( 'classified' !== $current_status ) {
        wp_send_json_error( [ 'message' => 'Already declassified.' ] );
    }

    // 6. Retrieve first_name
    $first_name = get_post_meta( $post_id, 'first_name', true );
    if ( empty( $first_name ) ) {
        $first_name = 'UNKNOWN';
    }

    // 7. Retrieve Kit subscriber list (with graceful degradation)
    $recipients = area51_get_kit_subscribers();

    // Filter out empty emails
    $recipients = array_filter( $recipients, function( $email ) {
        return ! empty( $email ) && is_email( $email );
    } );

    if ( empty( $recipients ) ) {
        // Absolute fallback: use admin email directly
        $admin_email = get_option( 'area51_admin_email', '' );
        if ( ! empty( $admin_email ) && is_email( $admin_email ) ) {
            $recipients = [ $admin_email ];
        } else {
            wp_send_json_error( [ 'message' => 'No valid recipient emails configured. Card not flipped.' ] );
        }
    }

    // 8. Build email content
    $subject = 'DECLASSIFIED: ' . $first_name . ' has been located.';
    $body    = "One of our own. The files are now open.\n\nFull details now available: " . home_url( '/board' ) . "\n\n— Area 51 Command";

    // Determine From header
    $resend_settings = get_option( 'resend_settings', [] );
    $from_email      = $resend_settings['from_email'] ?? ( defined( 'RESEND_FROM_EMAIL' ) ? RESEND_FROM_EMAIL : '' );
    $headers         = [];
    if ( ! empty( $from_email ) ) {
        $headers[] = 'From: Area 51 Command <' . $from_email . '>';
    }

    // 9. Send email blast — email-first atomicity
    $mail_success    = false;
    $send_count      = 0;
    $recipients_list = array_values( $recipients );
    $total           = count( $recipients_list );

    for ( $i = 0; $i < $total; $i++ ) {
        $email = $recipients_list[ $i ];
        try {
            $result = wp_mail( $email, $subject, $body, $headers );
        } catch ( \Throwable $e ) {
            // Catch plugin-level exceptions (e.g., Resend logger directory permission errors)
            error_log( '[Area51] wp_mail threw exception for ' . $email . ': ' . $e->getMessage() );
            $result = false;
        }
        if ( $result ) {
            $mail_success = true;
        }
        // Rate limiting: pause every 50 sends
        if ( ( $i + 1 ) % 50 === 0 && ( $i + 1 ) < $total ) {
            usleep( 500000 ); // 0.5 seconds
        }
    }

    // If no successful sends, abort — do NOT flip the card
    if ( ! $mail_success ) {
        wp_send_json_error( [ 'message' => 'Email blast failed. Card not flipped. Try again.' ] );
    }

    // 10. Email succeeded — now update state
    update_post_meta( $post_id, 'status', 'declassified' );
    update_option( 'area51_located_count', (int) get_option( 'area51_located_count', 0 ) + 1 );

    // 11. Return success
    wp_send_json_success();
}

add_action( 'wp_ajax_area51_declassify_card', 'area51_handle_declassify_card' );
