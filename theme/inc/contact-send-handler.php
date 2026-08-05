<?php
/**
 * Area 51 Reunion — Individual Contact Email Send AJAX Handler
 * Layer 16: Sends a one-off email to a single area51_contact from the admin
 * list screen, reusing the email-first atomicity discipline established by
 * clearance-approval-handler.php (simplified here — no meta write follows the
 * email on either branch, so the discipline reduces to "don't claim success
 * when wp_mail() returned false").
 *
 * Recipient is always server-resolved from the area51_contact post's `email`
 * meta — never trusts a client-POSTed `to`/similar field.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function area51_handle_send_contact_email(): void {
    // 1. Validate nonce
    check_ajax_referer( 'area51_contact_send_nonce', 'nonce' );

    // 2. Permission check
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
    }

    // 3. Validate post_id + post type
    $post_id = absint( $_POST['post_id'] ?? 0 );
    if ( $post_id <= 0 || get_post_type( $post_id ) !== 'area51_contact' ) {
        wp_send_json_error( [ 'message' => 'Invalid contact.' ] );
    }

    // 4. Validate subject + message (non-empty after sanitizing)
    $subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
    $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
    if ( '' === $subject || '' === $message ) {
        wp_send_json_error( [ 'message' => 'Subject and message are both required.' ] );
    }

    // 5. Recipient — always server-resolved from post meta, never client input
    $email = get_post_meta( $post_id, 'email', true );
    if ( empty( $email ) || ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'No valid email address on file for this contact.' ] );
    }

    // 6. Build headers — verbatim shape of incident-reports.php:431-439 (SCOUT Technology Decision 1)
    $resend_settings = get_option( 'resend_settings', [] );
    $from_email = is_array( $resend_settings ) ? ( $resend_settings['from_email'] ?? 'noreply@area51reunion.com' ) : 'noreply@area51reunion.com';
    $from_name  = is_array( $resend_settings ) ? ( $resend_settings['from_name']  ?? 'Area 51 Reunion' )           : 'Area 51 Reunion';
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        "From: {$from_name} <{$from_email}>",
    ];

    // 7. Body — plain text sanitized on input, re-escaped + line-broken on send (SCOUT Technology Decision 5)
    $body = nl2br( esc_html( $message ) );

    // 8. Send — email-first, no meta write on either branch
    $mail_result = wp_mail( $email, $subject, $body, $headers );

    if ( ! $mail_result ) {
        wp_send_json_error( [ 'message' => 'Email failed to send. Please try again.' ] );
    }

    wp_send_json_success( [ 'message' => 'Email sent successfully.' ] );
}
add_action( 'wp_ajax_area51_send_contact_email', 'area51_handle_send_contact_email' );
