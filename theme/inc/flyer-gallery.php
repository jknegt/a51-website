<?php
/**
 * Area 51 Reunion — Flyer Gallery
 * [Layer 08] CPT flyer_submission, post meta, meta box, admin columns,
 * display shortcode [area51_flyer_gallery], submission form shortcode
 * [area51_flyer_submit], and upload handler (admin-post.php).
 *
 * NONCE action: area51_flyer_submit_nonce
 * NONCE field:  _wpnonce_fg
 * HANDLER hook: admin_post_nopriv_area51_flyer_submit
 *               admin_post_area51_flyer_submit
 */

// =============================================================================
// Section 1: CPT Registration
// =============================================================================

add_action( 'init', 'area51_register_flyer_submission_cpt' );
function area51_register_flyer_submission_cpt(): void {
    register_post_type( 'flyer_submission', [
        'label'              => 'Flyer Submissions',
        'labels'             => [
            'name'               => 'Flyer Submissions',
            'singular_name'      => 'Flyer Submission',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Flyer Submission',
            'edit_item'          => 'Edit Flyer Submission',
            'new_item'           => 'New Flyer Submission',
            'view_item'          => 'View Flyer Submission',
            'search_items'       => 'Search Flyer Submissions',
            'not_found'          => 'No flyer submissions found.',
            'not_found_in_trash' => 'No flyer submissions found in Trash.',
        ],
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => false,
        'rewrite'            => false,
        'has_archive'        => false,
        'supports'           => [ 'title', 'editor', 'custom-fields' ],
        'menu_icon'          => 'dashicons-images-alt2',
    ] );

    // ==========================================================================
    // Section 2: Post Meta Registration (four fg_* keys)
    // ==========================================================================

    register_post_meta( 'flyer_submission', 'fg_image_url', [
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'esc_url_raw',
        'show_in_rest'      => false,
    ] );

    register_post_meta( 'flyer_submission', 'fg_date', [
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => false,
    ] );

    register_post_meta( 'flyer_submission', 'fg_notes', [
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_textarea_field',
        'show_in_rest'      => false,
    ] );

    register_post_meta( 'flyer_submission', 'fg_submitter_name', [
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => false,
    ] );
}

// =============================================================================
// Section 3: Admin Meta Box
// =============================================================================

add_action( 'add_meta_boxes', 'area51_add_flyer_submission_meta_box' );
function area51_add_flyer_submission_meta_box(): void {
    add_meta_box(
        'flyer_submission_fields',
        'FLYER SUBMISSION DETAILS',
        'area51_flyer_submission_meta_box_callback',
        'flyer_submission',
        'normal',
        'high'
    );
}

function area51_flyer_submission_meta_box_callback( WP_Post $post ): void {
    wp_nonce_field( 'area51_flyer_submission_meta', 'area51_flyer_submission_meta_nonce' );

    $fg_image_url     = esc_url( get_post_meta( $post->ID, 'fg_image_url', true ) );
    $fg_date          = esc_attr( get_post_meta( $post->ID, 'fg_date', true ) );
    $fg_notes         = esc_textarea( get_post_meta( $post->ID, 'fg_notes', true ) );
    $fg_submitter_name = esc_attr( get_post_meta( $post->ID, 'fg_submitter_name', true ) );
    ?>
    <table class="form-table">
        <tr>
            <th><label for="fg_image_url">Image URL</label></th>
            <td>
                <input type="url" id="fg_image_url" name="fg_image_url"
                       value="<?php echo $fg_image_url; ?>"
                       class="widefat" />
                <p class="description">URL of the uploaded flyer image. Auto-populated on form submission. John can paste a URL manually for admin-added flyers.</p>
            </td>
        </tr>
        <tr>
            <th><label for="fg_date">Date / Era</label></th>
            <td>
                <input type="text" id="fg_date" name="fg_date"
                       value="<?php echo $fg_date; ?>"
                       class="widefat" placeholder="e.g. Halloween 1998, March 1997" />
            </td>
        </tr>
        <tr>
            <th><label for="fg_notes">Notes</label></th>
            <td>
                <textarea id="fg_notes" name="fg_notes" rows="4"
                          class="widefat"><?php echo $fg_notes; ?></textarea>
                <p class="description">Optional submitter notes about the flyer.</p>
            </td>
        </tr>
        <tr>
            <th><label for="fg_submitter_name">Submitter Name</label></th>
            <td>
                <input type="text" id="fg_submitter_name" name="fg_submitter_name"
                       value="<?php echo $fg_submitter_name; ?>"
                       class="widefat" placeholder="Name or alias" />
            </td>
        </tr>
    </table>
    <?php
}

// =============================================================================
// Section 4: Meta Save Hook
// =============================================================================

add_action( 'save_post_flyer_submission', 'area51_save_flyer_submission_meta' );
function area51_save_flyer_submission_meta( int $post_id ): void {
    // Nonce check
    if ( ! isset( $_POST['area51_flyer_submission_meta_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['area51_flyer_submission_meta_nonce'], 'area51_flyer_submission_meta' ) ) {
        return;
    }

    // Autosave check
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Capability check
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Save all four fg_* keys
    if ( isset( $_POST['fg_image_url'] ) ) {
        update_post_meta( $post_id, 'fg_image_url', esc_url_raw( wp_unslash( $_POST['fg_image_url'] ) ) );
    }
    if ( isset( $_POST['fg_date'] ) ) {
        update_post_meta( $post_id, 'fg_date', sanitize_text_field( wp_unslash( $_POST['fg_date'] ) ) );
    }
    if ( isset( $_POST['fg_notes'] ) ) {
        update_post_meta( $post_id, 'fg_notes', sanitize_textarea_field( wp_unslash( $_POST['fg_notes'] ) ) );
    }
    if ( isset( $_POST['fg_submitter_name'] ) ) {
        update_post_meta( $post_id, 'fg_submitter_name', sanitize_text_field( wp_unslash( $_POST['fg_submitter_name'] ) ) );
    }
}

// =============================================================================
// Section 5: Admin Column Customization
// =============================================================================

add_filter( 'manage_flyer_submission_posts_columns', 'area51_flyer_submission_columns' );
function area51_flyer_submission_columns( array $columns ): array {
    $new_columns = [];
    foreach ( $columns as $key => $label ) {
        $new_columns[ $key ] = $label;
        if ( 'title' === $key ) {
            $new_columns['fg_date']           = 'Date / Era';
            $new_columns['fg_submitter_name'] = 'Submitter';
            $new_columns['fg_image_url']      = 'Image URL';
        }
    }
    return $new_columns;
}

add_action( 'manage_flyer_submission_posts_custom_column', 'area51_flyer_submission_column_content', 10, 2 );
function area51_flyer_submission_column_content( string $column, int $post_id ): void {
    switch ( $column ) {
        case 'fg_date':
            echo esc_html( get_post_meta( $post_id, 'fg_date', true ) ?: '—' );
            break;
        case 'fg_submitter_name':
            echo esc_html( get_post_meta( $post_id, 'fg_submitter_name', true ) ?: '—' );
            break;
        case 'fg_image_url':
            $url = get_post_meta( $post_id, 'fg_image_url', true );
            if ( $url ) {
                $truncated = strlen( $url ) > 60 ? substr( $url, 0, 60 ) . '…' : $url;
                echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $truncated ) . '</a>';
            } else {
                echo '—';
            }
            break;
    }
}

// =============================================================================
// Section 6: Display Shortcode [area51_flyer_gallery]
// =============================================================================

add_shortcode( 'area51_flyer_gallery', 'area51_flyer_gallery_shortcode' );
function area51_flyer_gallery_shortcode(): string {
    // [Prevention: PATTERN-001/ISSUE-006] — Remove wpautop while building shortcode output.
    // Block-level <div> containers prevent wpautop injection, but scoped removal is defense-in-depth.
    remove_filter( 'the_content', 'wpautop', 12 );

    $output = '';

    // Check submission query arg for confirmation/error banners
    $submission = isset( $_GET['submission'] ) ? sanitize_key( $_GET['submission'] ) : '';
    if ( 'received' === $submission ) {
        $output .= '<div class="fg-confirmation-banner">SUBMISSION RECEIVED — PENDING CLEARANCE REVIEW</div>';
    } elseif ( 'error' === $submission ) {
        $output .= '<div class="fg-error-banner">TRANSMISSION ERROR — PLEASE RETRY</div>';
    }

    // Query published flyer_submission posts
    $query = new WP_Query( [
        'post_type'      => 'flyer_submission',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => [ 'menu_order' => 'ASC', 'date' => 'DESC' ],
    ] );

    if ( ! $query->have_posts() ) {
        $output .= '<div class="flyer-gallery-empty"><div>NO FLYERS ON RECORD.</div></div>';
        add_filter( 'the_content', 'wpautop', 12 );
        return $output;
    }

    $output .= '<div class="flyer-gallery-grid">';

    while ( $query->have_posts() ) {
        $query->the_post();
        $post_id       = get_the_ID();
        $fg_image_url  = get_post_meta( $post_id, 'fg_image_url', true );
        $fg_date       = get_post_meta( $post_id, 'fg_date', true );
        $fg_notes      = get_post_meta( $post_id, 'fg_notes', true );

        $date_label = $fg_date ? esc_html( strtoupper( $fg_date ) ) : 'REDACTED';

        $output .= '<div class="flyer-card">';
        $output .= '<div class="flyer-card-header">EVIDENCE FILE — ' . $date_label . '</div>';

        if ( ! empty( $fg_image_url ) ) {
            $output .= '<div class="flyer-card-image">';
            $output .= '<img src="' . esc_url( $fg_image_url ) . '" alt="' . esc_attr( 'Flyer: ' . $date_label ) . '" loading="lazy">';
            $output .= '</div>';
        } else {
            $output .= '<div class="flyer-card-placeholder">CLASSIFIED — PENDING DECLASSIFICATION</div>';
        }

        if ( ! empty( $fg_notes ) ) {
            $output .= '<div class="flyer-card-notes">' . esc_html( $fg_notes ) . '</div>';
        }

        $output .= '</div>'; // .flyer-card
    }
    wp_reset_postdata();

    $output .= '</div>'; // .flyer-gallery-grid

    // [Prevention: PATTERN-001/ISSUE-006] — Restore wpautop after shortcode output is complete.
    add_filter( 'the_content', 'wpautop', 12 );

    return $output;
}

// =============================================================================
// Section 7: Submission Form Shortcode [area51_flyer_submit]
// =============================================================================

add_shortcode( 'area51_flyer_submit', 'area51_flyer_submit_shortcode' );
function area51_flyer_submit_shortcode(): string {
    // [Prevention: PATTERN-001/ISSUE-006] — Remove wpautop while building shortcode output.
    remove_filter( 'the_content', 'wpautop', 12 );

    ob_start();
    ?>
    <div class="fg-submit-form">
        <form method="post"
              action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
              enctype="multipart/form-data">

            <input type="hidden" name="action" value="area51_flyer_submit">
            <?php wp_nonce_field( 'area51_flyer_submit_nonce', '_wpnonce_fg' ); ?>

            <div class="fg-form-field">
                <label for="fg-image">IMAGE (JPEG, PNG, GIF, WEBP — MAX 2MB) <span style="color:#ff4444">*</span></label>
                <input type="file"
                       name="fg_image"
                       id="fg-image"
                       accept="image/jpeg,image/png,image/gif,image/webp"
                       required>
            </div>

            <div class="fg-form-field">
                <label for="fg-date">DATE / ERA (OPTIONAL)</label>
                <input type="text"
                       name="fg_date"
                       id="fg-date"
                       placeholder="e.g. Halloween 1998, March 1997">
            </div>

            <div class="fg-form-field">
                <label for="fg-notes">NOTES (OPTIONAL)</label>
                <textarea name="fg_notes" id="fg-notes" rows="4"
                          placeholder="Any context about this flyer..."></textarea>
            </div>

            <div class="fg-form-field">
                <label for="fg-submitter-name">YOUR NAME OR ALIAS (OPTIONAL)</label>
                <input type="text"
                       name="fg_submitter_name"
                       id="fg-submitter-name"
                       placeholder="How should we credit you?">
            </div>

            <button type="submit" class="area51-btn">TRANSMIT EVIDENCE</button>
        </form>
    </div>
    <?php
    $output = ob_get_clean();

    // [Prevention: PATTERN-001/ISSUE-006] — Restore wpautop after shortcode output is complete.
    add_filter( 'the_content', 'wpautop', 12 );

    return $output;
}

// =============================================================================
// Section 8: Upload Handler
// =============================================================================

add_action( 'admin_post_nopriv_area51_flyer_submit', 'area51_handle_flyer_submit' );
add_action( 'admin_post_area51_flyer_submit',        'area51_handle_flyer_submit' );

function area51_handle_flyer_submit(): void {
    // Step 1: CSRF check — admin-post.php context uses check_admin_referer, NOT check_ajax_referer.
    // check_admin_referer calls wp_die() on failure; check_ajax_referer returns false.
    check_admin_referer( 'area51_flyer_submit_nonce', '_wpnonce_fg' );

    // Error redirect target
    $error_redirect_base = home_url( '/flyer-gallery/?submission=error' );

    // Step 2: Validate file present
    if ( ! isset( $_FILES['fg_image'] ) ) {
        wp_redirect( $error_redirect_base . '&reason=no_file' );
        exit();
    }

    // Step 3: Check PHP upload error codes
    $upload_error = (int) $_FILES['fg_image']['error'];
    if ( UPLOAD_ERR_INI_SIZE === $upload_error ) {
        // PHP rejected the file at the ini level before we saw it
        wp_redirect( $error_redirect_base . '&reason=file_too_large' );
        exit();
    }
    if ( UPLOAD_ERR_OK !== $upload_error ) {
        wp_redirect( $error_redirect_base . '&reason=upload_failed' );
        exit();
    }

    // Step 4: In-handler 2 MB size check
    if ( (int) $_FILES['fg_image']['size'] > 2 * 1024 * 1024 ) {
        wp_redirect( $error_redirect_base . '&reason=file_too_large' );
        exit();
    }

    // Allowed MIME types
    $allowed_mimes = [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'gif'          => 'image/gif',
        'png'          => 'image/png',
        'webp'         => 'image/webp',
    ];

    // Step 5: Pre-validate MIME type (content inspection, not extension-only)
    $file_data = wp_check_filetype_and_ext(
        $_FILES['fg_image']['tmp_name'],
        $_FILES['fg_image']['name'],
        $allowed_mimes
    );
    if ( empty( $file_data['ext'] ) ) {
        wp_redirect( $error_redirect_base . '&reason=invalid_type' );
        exit();
    }

    // Step 6: Defensive includes
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    // Step 7: Process the upload
    // 'test_form' => false is REQUIRED — without it wp_handle_upload returns
    // ['error' => 'Invalid form submission.'] silently. The actual CSRF check is
    // check_admin_referer() above; test_form=false is not a security bypass.
    $upload = wp_handle_upload( $_FILES['fg_image'], [
        'test_form' => false,
        'mimes'     => $allowed_mimes,
    ] );

    // Step 8: Check for upload error
    if ( isset( $upload['error'] ) ) {
        wp_redirect( $error_redirect_base . '&reason=upload_failed' );
        exit();
    }

    // Step 9: Insert as WP media attachment (parent = 0 — unattached, visible in Media Library)
    $attachment_id = wp_insert_attachment(
        [
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name( basename( $upload['file'] ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ],
        $upload['file'],
        0,
        true // $wp_error — return WP_Error on failure instead of 0
    );

    if ( is_wp_error( $attachment_id ) ) {
        wp_redirect( $error_redirect_base . '&reason=upload_failed' );
        exit();
    }

    // Step 10: Generate attachment metadata (thumbnails, dimensions)
    $attach_metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );

    // Step 11: Store the metadata
    wp_update_attachment_metadata( $attachment_id, $attach_metadata );

    // Step 12: Get the public URL
    $fg_image_url = wp_get_attachment_url( $attachment_id );

    // Step 13: Create flyer_submission post with status pending
    $post_id = wp_insert_post( [
        'post_type'   => 'flyer_submission',
        'post_status' => 'pending',
        'post_title'  => 'Flyer — ' . current_time( 'Y-m-d H:i' ),
    ] );

    if ( ! $post_id || is_wp_error( $post_id ) ) {
        wp_redirect( $error_redirect_base . '&reason=upload_failed' );
        exit();
    }

    // Step 14–17: Store all meta
    update_post_meta( $post_id, 'fg_image_url',      esc_url_raw( $fg_image_url ) );
    update_post_meta( $post_id, 'fg_date',           sanitize_text_field( wp_unslash( $_POST['fg_date'] ?? '' ) ) );
    update_post_meta( $post_id, 'fg_notes',          sanitize_textarea_field( wp_unslash( $_POST['fg_notes'] ?? '' ) ) );
    update_post_meta( $post_id, 'fg_submitter_name', sanitize_text_field( wp_unslash( $_POST['fg_submitter_name'] ?? '' ) ) );

    // Step 18: Redirect to gallery page with success signal
    // home_url() is available in all handler contexts without a DB query.
    wp_redirect( home_url( '/flyer-gallery/?submission=received' ) );
    exit();
}
