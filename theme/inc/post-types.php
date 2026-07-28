<?php
/**
 * Area 51 Reunion — Custom Post Types and Post Meta
 *
 * Registers:
 *   - missing_person: admin-only, non-public, used for the Board page card grid
 *   - clearance_request: admin-only, non-public, stores clearance form submissions
 *
 * [Privacy invariant] — first_name meta on missing_person is stored here and in the
 * admin meta box, but MUST NEVER be output in any front-end template in Layer 02.
 * Layer 03 owns the DECLASSIFIED flip that reveals first_name. Any code that echoes
 * first_name on the front end before Layer 03 is implemented is a privacy violation.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ==========================================================================
   3a. Register post types
   ========================================================================== */

function area51_register_post_types() {
    register_post_type( 'missing_person', [
        'label'              => 'Missing Persons',
        'labels'             => [
            'name'               => 'Missing Persons',
            'singular_name'      => 'Missing Person',
            'add_new_item'       => 'Add New Missing Person',
            'edit_item'          => 'Edit Missing Person',
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
        'menu_icon'          => 'dashicons-id',
    ] );

    register_post_type( 'clearance_request', [
        'label'              => 'Clearance Requests',
        'labels'             => [
            'name'               => 'Clearance Requests',
            'singular_name'      => 'Clearance Request',
            'add_new_item'       => 'Add New Clearance Request',
            'edit_item'          => 'Edit Clearance Request',
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
        'menu_icon'          => 'dashicons-clipboard',
    ] );
}
add_action( 'init', 'area51_register_post_types' );

/* ==========================================================================
   3b. Register post meta
   ========================================================================== */

function area51_register_post_meta() {
    // missing_person meta keys
    $string_meta = [ 'alias', 'role', 'era', 'status', 'first_name' ];
    foreach ( $string_meta as $key ) {
        register_post_meta( 'missing_person', $key, [
            'type'              => 'string',
            'description'       => $key,
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => 'sanitize_text_field',
        ] );
    }

    // clearance_request meta keys
    $cr_meta = [
        'submitter_email'  => 'sanitize_email',
        'submitter_name'   => 'sanitize_text_field',
        'submitter_alias'  => 'sanitize_text_field',
        'were_you_there'   => 'absint',
        'proof_notes'      => 'sanitize_textarea_field',
        'clearance_level'    => 'absint',
        'clearance_codename' => 'sanitize_text_field',
    ];
    foreach ( $cr_meta as $key => $sanitize_cb ) {
        register_post_meta( 'clearance_request', $key, [
            'type'              => 'string',
            'description'       => $key,
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => $sanitize_cb,
        ] );
    }
}
add_action( 'init', 'area51_register_post_meta' );

/* ==========================================================================
   3c. Admin meta box for missing_person
   ========================================================================== */

function area51_missing_person_meta_box() {
    add_meta_box(
        'area51_missing_person_meta',
        'Subject File',
        'area51_missing_person_meta_box_callback',
        'missing_person',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'area51_missing_person_meta_box' );

function area51_missing_person_meta_box_callback( $post ) {
    wp_nonce_field( 'area51_missing_person_meta', 'area51_missing_person_nonce' );
    $alias      = get_post_meta( $post->ID, 'alias', true );
    $role       = get_post_meta( $post->ID, 'role', true );
    $era        = get_post_meta( $post->ID, 'era', true );
    $status     = get_post_meta( $post->ID, 'status', true );
    $first_name = get_post_meta( $post->ID, 'first_name', true );
    ?>
    <p><label>Alias: <input type="text" name="area51_alias" value="<?php echo esc_attr( $alias ); ?>" style="width:100%"></label></p>
    <p><label>Role: <input type="text" name="area51_role" value="<?php echo esc_attr( $role ); ?>" style="width:100%"></label></p>
    <p><label>Era: <input type="text" name="area51_era" value="<?php echo esc_attr( $era ); ?>" style="width:100%" placeholder="e.g. 1994–1997"></label></p>
    <p><label>Status:
        <select name="area51_status">
            <option value="classified" <?php selected( $status, 'classified' ); ?>>CLASSIFIED</option>
            <option value="declassified" <?php selected( $status, 'declassified' ); ?>>DECLASSIFIED</option>
        </select>
    </label></p>
    <p><label>First Name (revealed on DECLASSIFY — Layer 03): <input type="text" name="area51_first_name" value="<?php echo esc_attr( $first_name ); ?>" style="width:100%"></label></p>
    <?php
}

function area51_save_missing_person_meta( $post_id ) {
    if ( ! isset( $_POST['area51_missing_person_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['area51_missing_person_nonce'], 'area51_missing_person_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    update_post_meta( $post_id, 'alias',      sanitize_text_field( $_POST['area51_alias'] ?? '' ) );
    update_post_meta( $post_id, 'role',       sanitize_text_field( $_POST['area51_role'] ?? '' ) );
    update_post_meta( $post_id, 'era',        sanitize_text_field( $_POST['area51_era'] ?? '' ) );
    update_post_meta( $post_id, 'status',     sanitize_text_field( $_POST['area51_status'] ?? 'classified' ) );
    update_post_meta( $post_id, 'first_name', sanitize_text_field( $_POST['area51_first_name'] ?? '' ) );
}
add_action( 'save_post_missing_person', 'area51_save_missing_person_meta' );

/* ==========================================================================
   3d. Admin meta box for clearance_request (read-only display)
   ========================================================================== */

function area51_clearance_request_meta_box() {
    add_meta_box(
        'area51_clearance_request_meta',
        'Submission Details',
        'area51_clearance_request_meta_box_callback',
        'clearance_request',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'area51_clearance_request_meta_box' );

function area51_clearance_request_meta_box_callback( $post ) {
    $fields = [
        'submitter_name'  => 'Name',
        'submitter_alias' => 'Alias',
        'submitter_email' => 'Email',
        'were_you_there'  => 'Were You There (1=yes, 0=no)',
        'proof_notes'     => 'Proof Notes',
        'clearance_level' => 'Clearance Level (0–3)',
    ];
    foreach ( $fields as $key => $label ) {
        $value = get_post_meta( $post->ID, $key, true );
        echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
    }
    echo '<p><em>Meta values are written by the AJAX handler on submission. Edit via Custom Fields panel or WP-CLI.</em></p>';
}
