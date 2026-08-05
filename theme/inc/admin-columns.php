<?php
/**
 * Area 51 Reunion — Admin Column Registration
 * Layer 03: Adds custom action columns to missing_person and clearance_request list screens.
 * Layer 15: Adds read-only display columns to the area51_contact list screen.
 *
 * missing_person: "Action" column with DECLASSIFY button (or DECLASSIFIED checkmark)
 * clearance_request: "Approve" column with level dropdown + Approve button (or approved state display)
 * area51_contact: Source / Clearance / First Submitted / Last Updated — read-only display columns
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ==========================================================================
   Layer 16 — Kit broadcast-composer link (area51_contact list screen only)
   ========================================================================== */

const AREA51_KIT_BROADCAST_URL = 'https://app.kit.com/broadcasts/new';

function area51_contact_kit_link_notice(): void {
    $screen = get_current_screen();
    if ( ! $screen || 'edit-area51_contact' !== $screen->id ) {
        return;
    }
    echo '<div class="notice notice-info"><p><a href="' . esc_url( AREA51_KIT_BROADCAST_URL ) . '" target="_blank" rel="noopener">Compose Broadcast in Kit</a></p></div>';
}
add_action( 'admin_notices', 'area51_contact_kit_link_notice' );

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
   Contact — Source / Clearance / First Submitted / Last Updated columns
   ========================================================================== */

function area51_contact_columns( array $columns ): array {
    $columns['area51_source']          = 'Source';
    $columns['area51_clearance']       = 'Clearance';
    $columns['area51_first_submitted'] = 'First Submitted';
    $columns['area51_last_updated']    = 'Last Updated';
    $columns['area51_send']            = 'Send';   // Layer 16
    return $columns;
}

function area51_contact_column_content( string $column, int $post_id ): void {
    switch ( $column ) {
        case 'area51_source':
            $source = get_post_meta( $post_id, 'source', true );
            echo esc_html( ucfirst( $source ) );
            break;

        case 'area51_clearance':
            $level    = (int) get_post_meta( $post_id, 'clearance_level', true );
            $codename = get_post_meta( $post_id, 'clearance_codename', true );
            if ( $level > 0 && $codename ) {
                echo 'Level ' . esc_html( $level ) . ' &mdash; ' . esc_html( $codename );
            } else {
                echo '&mdash;';
            }
            break;

        case 'area51_first_submitted':
            echo esc_html( get_post_meta( $post_id, 'first_submitted_date', true ) );
            break;

        case 'area51_last_updated':
            echo esc_html( get_post_meta( $post_id, 'last_updated_date', true ) );
            break;

        case 'area51_send':
            echo '<button type="button" class="area51-send-toggle-btn button" data-post-id="' . esc_attr( $post_id ) . '">Send Email</button>';
            echo '<div class="area51-send-form" data-post-id="' . esc_attr( $post_id ) . '" style="display:none;">';
            echo '<input type="text" class="area51-send-subject" placeholder="Subject" style="display:block;width:100%;margin:6px 0;">';
            echo '<textarea class="area51-send-message" placeholder="Message" rows="4" style="display:block;width:100%;margin:6px 0;"></textarea>';
            echo '<button type="button" class="area51-send-submit-btn button button-primary" data-post-id="' . esc_attr( $post_id ) . '">Send</button> ';
            echo '<button type="button" class="area51-send-cancel-btn button">Cancel</button>';
            echo '</div>';
            break;
    }
}

/* ==========================================================================
   Hook registrations
   ========================================================================== */

add_filter( 'manage_missing_person_posts_columns',          'area51_missing_person_columns' );
add_action( 'manage_missing_person_posts_custom_column',    'area51_missing_person_column_content', 10, 2 );
add_filter( 'manage_clearance_request_posts_columns',       'area51_clearance_request_columns' );
add_action( 'manage_clearance_request_posts_custom_column', 'area51_clearance_request_column_content', 10, 2 );
add_filter( 'manage_area51_contact_posts_columns',          'area51_contact_columns' );
add_action( 'manage_area51_contact_posts_custom_column',    'area51_contact_column_content', 10, 2 );
