<?php
/**
 * Area 51 Reunion — Admin Column Registration
 * Layer 03: Adds custom action columns to missing_person and clearance_request list screens.
 *
 * missing_person: "Action" column with DECLASSIFY button (or DECLASSIFIED checkmark)
 * clearance_request: "Approve" column with level dropdown + Approve button (or approved state display)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ==========================================================================
   Missing Person — Action column (DECLASSIFY)
   ========================================================================== */

function area51_missing_person_columns( array $columns ): array {
    $columns['area51_action'] = 'Action';
    return $columns;
}

function area51_missing_person_column_content( string $column, int $post_id ): void {
    if ( 'area51_action' !== $column ) {
        return;
    }
    $status = get_post_meta( $post_id, 'status', true );
    if ( 'declassified' === $status ) {
        echo '<span style="color:#0a0">&#x2713; DECLASSIFIED</span>';
    } else {
        echo '<button class="area51-declassify-btn button button-primary" data-post-id="' . esc_attr( $post_id ) . '">DECLASSIFY</button>';
    }
}

/* ==========================================================================
   Clearance Request — Approve column
   ========================================================================== */

function area51_clearance_request_columns( array $columns ): array {
    $columns['area51_approve'] = 'Approve';
    return $columns;
}

function area51_clearance_request_column_content( string $column, int $post_id ): void {
    if ( 'area51_approve' !== $column ) {
        return;
    }
    $level    = (int) get_post_meta( $post_id, 'clearance_level', true );
    $codename = get_post_meta( $post_id, 'clearance_codename', true );

    if ( $level > 0 && $codename ) {
        echo '<span>Level ' . esc_html( $level ) . ' &mdash; ' . esc_html( $codename ) . '</span>';
    } else {
        echo '<select class="area51-clearance-level-select" data-post-id="' . esc_attr( $post_id ) . '">';
        echo '<option value="1">Level 1</option>';
        echo '<option value="2">Level 2</option>';
        echo '<option value="3">Level 3</option>';
        echo '</select>';
        echo ' <button class="area51-clearance-approve-btn button button-primary" data-post-id="' . esc_attr( $post_id ) . '">Approve Clearance</button>';
    }
}

/* ==========================================================================
   Hook registrations
   ========================================================================== */

add_filter( 'manage_missing_person_posts_columns',          'area51_missing_person_columns' );
add_action( 'manage_missing_person_posts_custom_column',    'area51_missing_person_column_content', 10, 2 );
add_filter( 'manage_clearance_request_posts_columns',       'area51_clearance_request_columns' );
add_action( 'manage_clearance_request_posts_custom_column', 'area51_clearance_request_column_content', 10, 2 );
