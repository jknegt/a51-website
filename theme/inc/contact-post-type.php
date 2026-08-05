<?php
/**
 * Area 51 Reunion — Unified Contact Data Model (Layer 14)
 *
 * Registers the area51_contact CPT — the single unified index of every email
 * address collected via the homepage Subscribe form or the Clearance Request
 * form. Layer 15 builds the wp-admin dashboard list screen on top of this;
 * Layer 16 adds individual send + Kit link-out. This file owns only the CPT,
 * its post meta, and the area51_upsert_contact() upsert function.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function area51_register_contact_post_type() {
    register_post_type( 'area51_contact', [
        'label'              => 'Contacts',
        'labels'             => [
            'name'          => 'Contacts',
            'singular_name' => 'Contact',
            'add_new_item'  => 'Add New Contact',
            'edit_item'     => 'Edit Contact',
        ],
        'public'             => false,
        'publicly_queryable' => false,
        'has_archive'        => false,
        'rewrite'            => false,
        'query_var'          => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => false,
        'supports'           => [ 'title', 'custom-fields' ],
        'menu_icon'          => 'dashicons-email-alt',
    ] );
}
add_action( 'init', 'area51_register_contact_post_type' );

function area51_register_contact_post_meta() {
    $contact_meta = [
        'email'                => 'sanitize_email',
        'source'               => 'sanitize_text_field',
        'display_name'         => 'sanitize_text_field',
        'clearance_level'      => 'absint',
        'clearance_codename'   => 'sanitize_text_field',
        'first_submitted_date' => 'sanitize_text_field',
        'last_updated_date'    => 'sanitize_text_field',
    ];
    foreach ( $contact_meta as $key => $sanitize_cb ) {
        register_post_meta( 'area51_contact', $key, [
            'type'              => 'string',
            'description'       => $key,
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => $sanitize_cb,
        ] );
    }
}
add_action( 'init', 'area51_register_contact_post_meta' );

/**
 * Create or update an area51_contact record, keyed by email.
 *
 * @param string $email  Email address (will be sanitized).
 * @param string $source 'subscribe' | 'clearance'.
 * @param array  $fields Optional meta overrides. Only keys present are written —
 *                        omitted keys are left untouched on an existing record
 *                        (prevents e.g. a Subscribe-path upsert from blanking a
 *                        display_name a prior Clearance-path upsert already set).
 *                        Recognized keys: display_name, clearance_level, clearance_codename.
 * @return int Post ID (0 on failure).
 */
function area51_upsert_contact( string $email, string $source, array $fields = [] ): int {
    $email = sanitize_email( $email );
    if ( ! is_email( $email ) ) {
        return 0;
    }

    $existing = get_posts( [
        'post_type'      => 'area51_contact',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'no_found_rows'  => true,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'email',
                'value'   => $email,
                'compare' => '=',
            ],
        ],
    ] );
    $post_id = $existing ? (int) $existing[0] : 0;

    $now = current_time( 'mysql' );

    // display_name: only overwrite if the caller explicitly passed one.
    $display_name = null;
    if ( array_key_exists( 'display_name', $fields ) ) {
        $display_name = $fields['display_name'];
    } elseif ( $post_id ) {
        $display_name = get_post_meta( $post_id, 'display_name', true );
    }

    $title = $display_name ? $display_name : $email;

    if ( $post_id ) {
        // Update path — first_submitted_date is untouched.
        wp_update_post( [
            'ID'         => $post_id,
            'post_title' => sanitize_text_field( $title ),
        ] );
    } else {
        // Create path.
        $post_id = wp_insert_post( [
            'post_type'   => 'area51_contact',
            'post_title'  => sanitize_text_field( $title ),
            'post_status' => 'publish',
        ] );
        if ( is_wp_error( $post_id ) ) {
            error_log( 'Area51 Contact: wp_insert_post() failed for ' . $email . ' — ' . $post_id->get_error_message() );
            return 0;
        }
        update_post_meta( $post_id, 'first_submitted_date', $now );
    }

    update_post_meta( $post_id, 'email',             $email );
    update_post_meta( $post_id, 'source',             $source );
    update_post_meta( $post_id, 'last_updated_date',  $now );

    // Only write keys the caller actually passed — overwrite-avoidance rule.
    if ( array_key_exists( 'display_name', $fields ) ) {
        update_post_meta( $post_id, 'display_name', $fields['display_name'] );
    }
    if ( array_key_exists( 'clearance_level', $fields ) ) {
        update_post_meta( $post_id, 'clearance_level', $fields['clearance_level'] );
    }
    if ( array_key_exists( 'clearance_codename', $fields ) ) {
        update_post_meta( $post_id, 'clearance_codename', $fields['clearance_codename'] );
    }

    return (int) $post_id;
}
