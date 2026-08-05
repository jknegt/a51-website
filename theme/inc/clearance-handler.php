<?php
/**
 * Area 51 Reunion — Clearance Request AJAX Handler
 *
 * Handles wp_ajax_nopriv_area51_clearance_request and wp_ajax_area51_clearance_request.
 * On valid submission:
 *   1. Creates a clearance_request post (status: pending)
 *   2. Stores all submitted fields as post meta
 *   3. Sends a "Clearance Pending" confirmation email to the submitter
 *   4. Sends a notification email to John (area51_admin_email option)
 *
 * Email is routed through wp_mail() → Resend plugin (same pattern as subscribe-handler.php).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function area51_handle_clearance_request() {
    check_ajax_referer( 'area51_clearance_nonce', 'nonce' );

    // Sanitize and validate inputs
    $name           = sanitize_text_field( $_POST['name'] ?? '' );
    $submitter_email = sanitize_email( $_POST['email'] ?? '' );
    $alias          = sanitize_text_field( $_POST['alias'] ?? '' );
    $were_you_there = isset( $_POST['were_you_there'] ) ? absint( $_POST['were_you_there'] ) : null;
    $proof_notes    = sanitize_textarea_field( $_POST['proof_notes'] ?? '' );

    // Validate required fields
    if ( empty( $name ) ) {
        wp_send_json_error( [ 'message' => 'Name is required.' ] );
    }
    if ( empty( $submitter_email ) || ! is_email( $submitter_email ) ) {
        wp_send_json_error( [ 'message' => 'A valid email address is required.' ] );
    }
    if ( null === $were_you_there ) {
        wp_send_json_error( [ 'message' => 'Please indicate whether you were there.' ] );
    }

    $display_name = $alias ?: $name;

    // Create clearance_request post
    $post_id = wp_insert_post( [
        'post_type'   => 'clearance_request',
        'post_title'  => sanitize_text_field( $display_name ) . ' — ' . current_time( 'Y-m-d H:i' ),
        'post_status' => 'pending',
    ] );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Server error. Please try again.' ] );
    }

    // Layer 14: Upsert unified contact record. Gated on wp_insert_post() success —
    // this handler's own primary write and point of no return, since (unlike
    // subscribe-handler.php) its confirmation email below is not itself gated on
    // wp_mail()'s return value. clearance_level/clearance_codename are NOT passed
    // here: at initial submission clearance_request's own clearance_level meta is
    // always 0 (see line below) — nothing meaningful to snapshot yet. See
    // SCOUT Section 5.4 for the known, accepted staleness gap this leaves for
    // Layer 15/16 to close, not fixed here.
    area51_upsert_contact( $submitter_email, 'clearance', [ 'display_name' => $display_name ] );

    // Store all submission fields as post meta
    update_post_meta( $post_id, 'submitter_name',  $name );
    update_post_meta( $post_id, 'submitter_alias', $alias );
    update_post_meta( $post_id, 'submitter_email', $submitter_email );
    update_post_meta( $post_id, 'were_you_there',  $were_you_there );
    update_post_meta( $post_id, 'proof_notes',     $proof_notes );
    update_post_meta( $post_id, 'clearance_level', 0 );

    // Build email headers using Resend plugin settings (same pattern as subscribe-handler.php)
    $resend_settings = get_option( 'resend_settings', [] );
    $from_email      = $resend_settings['from_email'] ?? get_option( 'admin_email' );
    $from_name       = $resend_settings['from_name']  ?? get_bloginfo( 'name' );
    $headers         = [
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
    ];

    // 1. Submitter confirmation — "Clearance Pending"
    $subj_confirm = '[CLASSIFIED] Clearance Request Received — Area 51 Reunion';
    $body_confirm = "Your clearance request has been received and is under review.\n\n"
        . "Stand by for further instructions.\n\n"
        . "— Area 51 Clearance Division";
    wp_mail( $submitter_email, $subj_confirm, $body_confirm, $headers );

    // 2. John notification email
    $admin_email = get_option( 'area51_admin_email' );
    if ( $admin_email ) {
        $admin_subject = '[AREA 51] New Clearance Request — ' . $display_name;
        $admin_body    = "New clearance request received.\n\n"
            . "Name: $name\n"
            . "Email: $submitter_email\n"
            . "Alias: $alias\n"
            . "Were You There: " . ( $were_you_there ? 'Yes' : 'No' ) . "\n"
            . "Proof Notes:\n$proof_notes\n\n"
            . "Admin link: " . admin_url( 'post.php?post=' . $post_id . '&action=edit' );
        wp_mail( $admin_email, $admin_subject, $admin_body, $headers );
    }

    // 3. Add to Kit (secondary — failure does not block success response).
    // Clearance requesters previously only reached Kit if they *also* used the
    // separate subscribe form — same pattern as subscribe-handler.php so every
    // funnel feeds the one audience the dashboard/broadcast tooling reads from.
    $kit_settings = get_option( '_wp_convertkit_settings', [] );
    $kit_api_key  = $kit_settings['api_key'] ?? '';
    $kit_form_id  = (int) get_option( 'area51_kit_form_id', 0 );

    if ( ! empty( $kit_api_key ) ) {
        area51_kit_add_subscriber( $submitter_email, $kit_api_key, $kit_form_id );
    } else {
        error_log( 'Area51 Clearance: Kit API key not configured — submitter not added to Kit.' );
    }

    wp_send_json_success( [ 'message' => 'Request received.' ] );
}

add_action( 'wp_ajax_nopriv_area51_clearance_request', 'area51_handle_clearance_request' );
add_action( 'wp_ajax_area51_clearance_request',        'area51_handle_clearance_request' );
