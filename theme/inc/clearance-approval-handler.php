<?php
/**
 * Area 51 Reunion — Clearance Approval AJAX Handler
 * Layer 03: Assigns a clearance level and codename to a clearance_request.
 *
 * Execution order (email-first atomicity):
 *   1. Validate nonce + permissions
 *   2. Validate post_id + level (1-3) + post type
 *   3. Generate deterministic codename from post_id
 *   4. Retrieve submitter_email from post meta
 *   5. Send approval email via wp_mail()
 *   6. On email success ONLY: update clearance_level + clearance_codename meta
 *   7. Return JSON success with codename and level
 *
 * If wp_mail() fails, meta is NOT updated. Email-first atomicity enforced.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ==========================================================================
   Codename constant array — 20 classified-sounding codenames
   ========================================================================== */

const AREA51_CODENAMES = [
    'RAVEN', 'VORTEX', 'PHANTOM-7', 'SIGNAL-9', 'ECLIPSE',
    'NOMAD', 'SPECTER', 'CIPHER', 'NEXUS', 'WRAITH',
    'PRISM', 'VECTOR', 'OBSIDIAN', 'MERIDIAN', 'SHADOW-4',
    'QUANTUM', 'LYNX', 'DELTA-ZERO', 'COBALT', 'FALCON-3',
];

/* ==========================================================================
   Codename generator — deterministic, same post always gets same codename
   ========================================================================== */

function area51_generate_codename( int $post_id ): string {
    return AREA51_CODENAMES[ $post_id % count( AREA51_CODENAMES ) ];
}

/* ==========================================================================
   Clearance Approval AJAX handler
   ========================================================================== */

function area51_handle_approve_clearance(): void {
    // 1. Validate nonce
    check_ajax_referer( 'area51_clearance_approve_nonce', 'nonce' );

    // 2a. Permission check
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
    }

    // 2b. Validate post_id
    $post_id = absint( $_POST['post_id'] ?? 0 );
    if ( $post_id <= 0 ) {
        wp_send_json_error( [ 'message' => 'Invalid post ID.' ] );
    }

    // 2c. Validate level (1–3 inclusive)
    $level = absint( $_POST['level'] ?? 0 );
    if ( $level < 1 || $level > 3 ) {
        wp_send_json_error( [ 'message' => 'Invalid clearance level. Must be 1, 2, or 3.' ] );
    }

    // 2d. Verify post type
    if ( get_post_type( $post_id ) !== 'clearance_request' ) {
        wp_send_json_error( [ 'message' => 'Invalid post type.' ] );
    }

    // 3. Generate deterministic codename
    $codename = area51_generate_codename( $post_id );

    // 4. Retrieve submitter email
    $submitter_email = get_post_meta( $post_id, 'submitter_email', true );
    if ( empty( $submitter_email ) || ! is_email( $submitter_email ) ) {
        wp_send_json_error( [ 'message' => 'No valid submitter email on file. Cannot send approval.' ] );
    }

    // 5. Build and send approval email
    $subject = '[AREA 51] Clearance Level ' . $level . ' Granted — Codename: ' . $codename;
    $body    = "CLEARANCE APPROVED.\n\nYour application has been processed.\n\nClearance Level: " . $level . "\nCodename: " . $codename . "\n\nEvent: October 31, 2026 — Vatican Gift Shop, Toronto\n\nWelcome to the inner circle.\n\n— Area 51 Command";

    $resend_settings = get_option( 'resend_settings', [] );
    $from_email      = $resend_settings['from_email'] ?? ( defined( 'RESEND_FROM_EMAIL' ) ? RESEND_FROM_EMAIL : '' );
    $headers = [];
    if ( ! empty( $from_email ) ) {
        $headers[] = 'From: Area 51 Command <' . $from_email . '>';
    }

    $mail_result = wp_mail( $submitter_email, $subject, $body, $headers );

    // If email fails, abort — do NOT update meta
    if ( ! $mail_result ) {
        wp_send_json_error( [ 'message' => 'Approval email failed. Level not assigned. Try again.' ] );
    }

    // 6. Email succeeded — update meta
    update_post_meta( $post_id, 'clearance_level',   $level );
    update_post_meta( $post_id, 'clearance_codename', $codename );

    // 7. Return success with codename and level
    wp_send_json_success( [ 'codename' => $codename, 'level' => $level ] );
}

add_action( 'wp_ajax_area51_approve_clearance', 'area51_handle_approve_clearance' );
