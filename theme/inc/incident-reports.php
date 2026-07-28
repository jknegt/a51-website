<?php
/**
 * Area 51 Reunion — Memory Wall: Incident Reports
 * Layer 06
 *
 * Contains:
 *   2a. CPT registration (incident_report)
 *   2b. Meta registration (5 ir_* keys)
 *   2c. Admin meta box
 *   2d. Meta save hook
 *   2e. Admin column customization
 *   2f. Display shortcode [area51_incident_reports]
 *   2g. Form shortcode [area51_incident_report_form]
 *   2h. AJAX handler (wp_ajax_nopriv_area51_submit_incident_report)
 *
 * [Prevention: PATTERN-042] — Nonce object name: area51IncidentReport (distinct from area51Subscribe)
 * [Prevention: ISSUE-006]   — Both shortcodes use scoped wpautop remove/restore
 */

// ===========================================================================
// 2a. CPT Registration
// ===========================================================================

add_action( 'init', 'area51_register_incident_report_cpt' );
function area51_register_incident_report_cpt(): void {
    register_post_type( 'incident_report', [
        'label'              => 'Incident Reports',
        'labels'             => [
            'name'               => 'Incident Reports',
            'singular_name'      => 'Incident Report',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Incident Report',
            'edit_item'          => 'Edit Incident Report',
            'new_item'           => 'New Incident Report',
            'view_item'          => 'View Incident Report',
            'search_items'       => 'Search Incident Reports',
            'not_found'          => 'No incident reports found.',
            'not_found_in_trash' => 'No incident reports found in Trash.',
        ],
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => false,
        'rewrite'            => false,
        'has_archive'        => false,
        'supports'           => [ 'title', 'editor', 'custom-fields' ],
        'menu_icon'          => 'dashicons-clipboard',
    ] );

    // ===========================================================================
    // 2b. Meta Registration (all five ir_* keys)
    // ===========================================================================

    $meta_args_text = [
        'object_subtype'    => 'incident_report',
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => false,
    ];
    $meta_args_area = [
        'object_subtype'    => 'incident_report',
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_textarea_field',
        'show_in_rest'      => false,
    ];

    register_post_meta( 'incident_report', 'ir_date_of_incident',   $meta_args_text );
    register_post_meta( 'incident_report', 'ir_location',           $meta_args_text );
    register_post_meta( 'incident_report', 'ir_subjects_present',   $meta_args_area );
    register_post_meta( 'incident_report', 'ir_what_occurred',      $meta_args_area );
    register_post_meta( 'incident_report', 'ir_classification_level', $meta_args_text );
}

// ===========================================================================
// 2c. Admin Meta Box
// ===========================================================================

add_action( 'add_meta_boxes', 'area51_incident_report_meta_box' );
function area51_incident_report_meta_box(): void {
    add_meta_box(
        'incident_report_fields',
        'Incident Report Details',
        'area51_incident_report_meta_box_callback',
        'incident_report',
        'normal',
        'high'
    );
}

function area51_incident_report_meta_box_callback( WP_Post $post ): void {
    wp_nonce_field( 'area51_incident_report_meta', 'area51_incident_report_meta_nonce' );

    $date     = get_post_meta( $post->ID, 'ir_date_of_incident',    true );
    $location = get_post_meta( $post->ID, 'ir_location',            true );
    $subjects = get_post_meta( $post->ID, 'ir_subjects_present',    true );
    $occurred = get_post_meta( $post->ID, 'ir_what_occurred',       true );
    $level    = get_post_meta( $post->ID, 'ir_classification_level', true );
    ?>
    <table class="form-table">
        <tr>
            <th><label for="ir_date_of_incident">Date of Incident</label></th>
            <td>
                <input type="date" id="ir_date_of_incident" name="ir_date_of_incident"
                       value="<?php echo esc_attr( $date ); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="ir_location">Location Within Facility</label></th>
            <td>
                <input type="text" id="ir_location" name="ir_location"
                       value="<?php echo esc_attr( $location ); ?>"
                       list="ir-admin-location-list" class="regular-text">
                <datalist id="ir-admin-location-list">
                    <option value="Main Floor">
                    <option value="Back Room">
                    <option value="Entry / Checkpoint">
                    <option value="Outdoor Area">
                    <option value="The Booth">
                    <option value="Unspecified">
                </datalist>
            </td>
        </tr>
        <tr>
            <th><label for="ir_subjects_present">Subjects Present</label></th>
            <td>
                <textarea id="ir_subjects_present" name="ir_subjects_present"
                          rows="3" class="large-text"><?php echo esc_textarea( $subjects ); ?></textarea>
                <p class="description">Leave blank to hide on public Memory Wall.</p>
            </td>
        </tr>
        <tr>
            <th><label for="ir_what_occurred">What Occurred</label></th>
            <td>
                <textarea id="ir_what_occurred" name="ir_what_occurred"
                          rows="5" class="large-text"><?php echo esc_textarea( $occurred ); ?></textarea>
            </td>
        </tr>
        <tr>
            <th><label for="ir_classification_level">Classification Level</label></th>
            <td>
                <select id="ir_classification_level" name="ir_classification_level">
                    <?php
                    $levels = [ 'UNCLASSIFIED', 'LEVEL 1', 'LEVEL 2', 'LEVEL 3' ];
                    foreach ( $levels as $opt ) {
                        printf(
                            '<option value="%s"%s>%s</option>',
                            esc_attr( $opt ),
                            selected( $level, $opt, false ),
                            esc_html( $opt )
                        );
                    }
                    ?>
                </select>
            </td>
        </tr>
    </table>
    <?php
}

// ===========================================================================
// 2d. Meta Save Hook
// ===========================================================================

add_action( 'save_post_incident_report', 'area51_save_incident_report_meta' );
function area51_save_incident_report_meta( int $post_id ): void {
    // Nonce verification
    if ( ! isset( $_POST['area51_incident_report_meta_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['area51_incident_report_meta_nonce'], 'area51_incident_report_meta' ) ) return;

    // Autosave check
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    // Capability check
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    // Save all five ir_* keys
    if ( isset( $_POST['ir_date_of_incident'] ) ) {
        update_post_meta( $post_id, 'ir_date_of_incident', sanitize_text_field( $_POST['ir_date_of_incident'] ) );
    }
    if ( isset( $_POST['ir_location'] ) ) {
        update_post_meta( $post_id, 'ir_location', sanitize_text_field( $_POST['ir_location'] ) );
    }
    if ( isset( $_POST['ir_subjects_present'] ) ) {
        update_post_meta( $post_id, 'ir_subjects_present', sanitize_textarea_field( $_POST['ir_subjects_present'] ) );
    }
    if ( isset( $_POST['ir_what_occurred'] ) ) {
        update_post_meta( $post_id, 'ir_what_occurred', sanitize_textarea_field( $_POST['ir_what_occurred'] ) );
    }
    if ( isset( $_POST['ir_classification_level'] ) ) {
        update_post_meta( $post_id, 'ir_classification_level', sanitize_text_field( $_POST['ir_classification_level'] ) );
    }
}

// ===========================================================================
// 2e. Admin Column Customization
// ===========================================================================

add_filter( 'manage_incident_report_posts_columns', 'area51_ir_columns' );
function area51_ir_columns( array $columns ): array {
    unset( $columns['date'] );
    $columns['ir_classification_level'] = 'Classification';
    $columns['ir_location']             = 'Location';
    $columns['ir_date_of_incident']     = 'Date of Incident';
    $columns['date']                    = 'Submission Date';
    return $columns;
}

add_action( 'manage_incident_report_posts_custom_column', 'area51_ir_column_content', 10, 2 );
function area51_ir_column_content( string $column, int $post_id ): void {
    switch ( $column ) {
        case 'ir_classification_level':
            echo esc_html( get_post_meta( $post_id, 'ir_classification_level', true ) ?: '—' );
            break;
        case 'ir_location':
            echo esc_html( get_post_meta( $post_id, 'ir_location', true ) ?: '—' );
            break;
        case 'ir_date_of_incident':
            echo esc_html( get_post_meta( $post_id, 'ir_date_of_incident', true ) ?: '—' );
            break;
    }
}

// ===========================================================================
// 2f. Display Shortcode [area51_incident_reports]
// ===========================================================================

add_shortcode( 'area51_incident_reports', 'area51_incident_reports_shortcode' );
function area51_incident_reports_shortcode(): string {
    // [Prevention: ISSUE-006] Scoped wpautop fix — remove before building output, restore before return
    remove_filter( 'the_content', 'wpautop', 12 );

    $query = new WP_Query( [
        'post_type'      => 'incident_report',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ] );

    $output = '<div class="incident-reports-grid">';

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id  = get_the_ID();
            $date     = esc_html( get_post_meta( $post_id, 'ir_date_of_incident',    true ) );
            $location = esc_html( get_post_meta( $post_id, 'ir_location',            true ) );
            $subjects = esc_html( get_post_meta( $post_id, 'ir_subjects_present',    true ) );
            $occurred = nl2br( esc_html( get_post_meta( $post_id, 'ir_what_occurred', true ) ) );
            $level    = esc_html( get_post_meta( $post_id, 'ir_classification_level', true ) ?: 'UNCLASSIFIED' );

            $output .= '<div class="incident-report-card">';

            // Card header
            $output .= '<div class="ir-card-header">';
            $output .= '<div class="ir-card-label">DECLASSIFIED INCIDENT REPORT</div>';
            $output .= '<div class="ir-classification-stamp">' . $level . '</div>';
            $output .= '</div>';

            // Date field
            if ( $date ) {
                $output .= '<div class="ir-field ir-date">';
                $output .= '<div class="ir-field-label">DATE OF INCIDENT</div>';
                $output .= '<div class="ir-field-value">' . $date . '</div>';
                $output .= '</div>';
            }

            // Location field
            if ( $location ) {
                $output .= '<div class="ir-field ir-location">';
                $output .= '<div class="ir-field-label">LOCATION WITHIN FACILITY</div>';
                $output .= '<div class="ir-field-value">' . $location . '</div>';
                $output .= '</div>';
            }

            // Subjects Present — only if non-empty (privacy control)
            if ( ! empty( $subjects ) ) {
                $output .= '<div class="ir-field ir-subjects">';
                $output .= '<div class="ir-field-label">SUBJECTS PRESENT</div>';
                $output .= '<div class="ir-field-value">' . $subjects . '</div>';
                $output .= '</div>';
            }

            // What Occurred
            $output .= '<div class="ir-field ir-occurred">';
            $output .= '<div class="ir-field-label">WHAT OCCURRED</div>';
            $output .= '<div class="ir-field-value">' . $occurred . '</div>';
            $output .= '</div>';

            $output .= '</div>'; // .incident-report-card
        }
        wp_reset_postdata();
    } else {
        $output .= '<div class="incident-report-empty"><div>NO DECLASSIFIED INCIDENTS ON RECORD.</div></div>';
    }

    $output .= '</div>'; // .incident-reports-grid

    // [Prevention: ISSUE-006] Restore wpautop after shortcode output is complete
    add_filter( 'the_content', 'wpautop', 12 );

    return $output;
}

// ===========================================================================
// 2g. Form Shortcode [area51_incident_report_form]
// ===========================================================================

add_shortcode( 'area51_incident_report_form', 'area51_incident_report_form_shortcode' );
function area51_incident_report_form_shortcode(): string {
    // [Prevention: ISSUE-006] Scoped wpautop fix
    remove_filter( 'the_content', 'wpautop', 12 );

    $output  = '<div id="incident-report-form-wrap">';
    $output .= '<form id="incident-report-form" novalidate>';

    // Date of Incident
    $output .= '<div class="ir-form-field">';
    $output .= '<label for="ir-date-of-incident">DATE OF INCIDENT</label>';
    $output .= '<input type="date" id="ir-date-of-incident" name="ir_date_of_incident">';
    $output .= '</div>';

    // Location Within Facility
    $output .= '<div class="ir-form-field">';
    $output .= '<label for="ir-location">LOCATION WITHIN FACILITY</label>';
    $output .= '<input type="text" id="ir-location" name="ir_location" list="ir-location-list" placeholder="Main Floor, Back Room…">';
    $output .= '<datalist id="ir-location-list">';
    $output .= '<option value="Main Floor">';
    $output .= '<option value="Back Room">';
    $output .= '<option value="Entry / Checkpoint">';
    $output .= '<option value="Outdoor Area">';
    $output .= '<option value="Unspecified">';
    $output .= '</datalist>';
    $output .= '</div>';

    // Subjects Present
    $output .= '<div class="ir-form-field">';
    $output .= '<label for="ir-subjects-present">SUBJECTS PRESENT</label>';
    $output .= '<textarea id="ir-subjects-present" name="ir_subjects_present" placeholder="Names or aliases of those present (optional)"></textarea>';
    $output .= '</div>';

    // What Occurred (required)
    $output .= '<div class="ir-form-field">';
    $output .= '<label for="ir-what-occurred">WHAT OCCURRED <span class="ir-required">(REQUIRED)</span></label>';
    $output .= '<textarea id="ir-what-occurred" name="ir_what_occurred" required placeholder="Describe the incident…"></textarea>';
    $output .= '</div>';

    // Classification Level
    $output .= '<div class="ir-form-field">';
    $output .= '<label for="ir-classification-level">CLASSIFICATION LEVEL</label>';
    $output .= '<select id="ir-classification-level" name="ir_classification_level">';
    $output .= '<option value="UNCLASSIFIED">UNCLASSIFIED</option>';
    $output .= '<option value="LEVEL 1">LEVEL 1</option>';
    $output .= '<option value="LEVEL 2">LEVEL 2</option>';
    $output .= '<option value="LEVEL 3">LEVEL 3</option>';
    $output .= '</select>';
    $output .= '</div>';

    // Submit button
    $output .= '<div class="ir-form-field">';
    $output .= '<button type="submit" id="ir-submit-btn" class="area51-btn">FILE INCIDENT REPORT</button>';
    $output .= '</div>';

    $output .= '</form>';

    // Confirmation message (hidden until AJAX success)
    $output .= '<div id="incident-report-confirmation" style="display:none;">';
    $output .= '<div class="ir-confirmation-message">INCIDENT REPORT FILED — AWAITING CLASSIFICATION</div>';
    $output .= '</div>';

    // Error div (hidden until AJAX error)
    $output .= '<div id="incident-report-error" style="display:none;"></div>';

    $output .= '</div>'; // #incident-report-form-wrap

    // [Prevention: ISSUE-006] Restore wpautop
    add_filter( 'the_content', 'wpautop', 12 );

    return $output;
}

// ===========================================================================
// 2h. AJAX Handler
// ===========================================================================

add_action( 'wp_ajax_nopriv_area51_submit_incident_report', 'area51_submit_incident_report' );
add_action( 'wp_ajax_area51_submit_incident_report',        'area51_submit_incident_report' );
function area51_submit_incident_report(): void {
    // Nonce verification — [Prevention: PATTERN-042] action name matches wp_create_nonce() in functions.php
    check_ajax_referer( 'area51_incident_report_nonce', 'nonce' );

    // Sanitize all five ir_* inputs
    $ir_date     = sanitize_text_field( $_POST['ir_date_of_incident']    ?? '' );
    $ir_location = sanitize_text_field( $_POST['ir_location']            ?? '' );
    $ir_subjects = sanitize_textarea_field( $_POST['ir_subjects_present'] ?? '' );
    $ir_occurred = sanitize_textarea_field( $_POST['ir_what_occurred']   ?? '' );
    $ir_level    = sanitize_text_field( $_POST['ir_classification_level'] ?? 'UNCLASSIFIED' );

    // Validate required field
    if ( empty( $ir_occurred ) ) {
        wp_send_json_error( [ 'message' => 'WHAT OCCURRED field is required.' ] );
    }

    // Build post title from location + date
    $title = trim( $ir_location . ' — ' . $ir_date );
    if ( empty( trim( $title, ' —' ) ) ) {
        $title = 'Memory Submission — ' . current_time( 'Y-m-d' );
    }

    // Insert post — CRITICAL: post_status must be 'pending', never 'draft' or 'publish'
    $post_id = wp_insert_post( [
        'post_type'   => 'incident_report',
        'post_title'  => $title,
        'post_status' => 'pending',
    ] );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'TRANSMISSION ERROR. PLEASE RETRY.' ] );
    }

    // Save all five meta keys
    update_post_meta( $post_id, 'ir_date_of_incident',    $ir_date );
    update_post_meta( $post_id, 'ir_location',            $ir_location );
    update_post_meta( $post_id, 'ir_subjects_present',    $ir_subjects );
    update_post_meta( $post_id, 'ir_what_occurred',       $ir_occurred );
    update_post_meta( $post_id, 'ir_classification_level', $ir_level );

    // Notification email to John
    $admin_email = get_option( 'area51_admin_email', get_option( 'admin_email' ) );
    $resend      = get_option( 'resend_settings' );
    $from_email  = is_array( $resend ) ? ( $resend['from_email'] ?? 'noreply@area51reunion.com' ) : 'noreply@area51reunion.com';
    $from_name   = is_array( $resend ) ? ( $resend['from_name']  ?? 'Area 51 Reunion' )           : 'Area 51 Reunion';
    $headers     = [
        'Content-Type: text/html; charset=UTF-8',
        "From: {$from_name} <{$from_email}>",
    ];
    $subject = 'NEW INCIDENT REPORT FILED — AWAITING YOUR CLASSIFICATION';
    $body    = '<p><strong>Date of Incident:</strong> ' . esc_html( $ir_date )     . '</p>';
    $body   .= '<p><strong>Location:</strong> '          . esc_html( $ir_location ) . '</p>';
    $body   .= '<p><strong>Subjects Present:</strong> '  . esc_html( $ir_subjects ) . '</p>';
    $body   .= '<p><strong>What Occurred:</strong></p><p>' . nl2br( esc_html( $ir_occurred ) ) . '</p>';
    $body   .= '<p><strong>Classification Level (submitted):</strong> ' . esc_html( $ir_level ) . '</p>';
    $body   .= '<p><a href="' . esc_url( admin_url( "post.php?post={$post_id}&action=edit" ) ) . '">Review in WP Admin</a></p>';

    try {
        wp_mail( $admin_email, $subject, $body, $headers );
    } catch ( \Throwable $e ) {
        // Notification failure must NOT block the success response
        // [Prevention: ISSUE-008 pattern] — Resend plugin exceptions caught here
        error_log( 'area51_submit_incident_report: wp_mail exception: ' . $e->getMessage() );
    }

    wp_send_json_success( [ 'message' => 'INCIDENT REPORT FILED — AWAITING CLASSIFICATION' ] );
}
