<?php
/**
 * Area 51 Reunion — Personnel Files
 * Layer 07
 *
 * Contains:
 *   1a. CPT registration (personnel_file)
 *   1b. Meta registration (7 pf_* keys)
 *   1c. Admin meta box
 *   1d. Meta save hook
 *   1e. Display shortcode [area51_personnel_files]
 *
 * [Prevention: ISSUE-006]   — Shortcode uses scoped wpautop remove/restore
 * [Critical]  — pf_then_playlist_embed and pf_now_playlist_embed MUST use
 *               wp_kses_post as sanitizer. sanitize_textarea_field strips iframes.
 */

// ===========================================================================
// 1a. CPT Registration
// ===========================================================================

add_action( 'init', 'area51_register_personnel_file_cpt' );
function area51_register_personnel_file_cpt(): void {
    register_post_type( 'personnel_file', [
        'label'              => 'Personnel Files',
        'labels'             => [
            'name'               => 'Personnel Files',
            'singular_name'      => 'Personnel File',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Personnel File',
            'edit_item'          => 'Edit Personnel File',
            'new_item'           => 'New Personnel File',
            'view_item'          => 'View Personnel File',
            'search_items'       => 'Search Personnel Files',
            'not_found'          => 'No personnel files found.',
            'not_found_in_trash' => 'No personnel files found in Trash.',
        ],
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => false,
        'rewrite'            => false,
        'has_archive'        => false,
        'supports'           => [ 'title', 'editor', 'custom-fields', 'page-attributes' ],
        'menu_icon'          => 'dashicons-id-alt',
        'show_in_rest'       => false,
    ] );

    // ===========================================================================
    // 1b. Meta Registration (all seven pf_* keys)
    // ===========================================================================

    $meta_args_text = [
        'object_subtype'    => 'personnel_file',
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => false,
    ];

    // [Critical] Playlist embed fields use wp_kses_post — NOT sanitize_textarea_field.
    // sanitize_textarea_field silently strips iframe HTML on save.
    $meta_args_embed = [
        'object_subtype'    => 'personnel_file',
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'wp_kses_post',
        'show_in_rest'      => false,
    ];

    $meta_args_area = [
        'object_subtype'    => 'personnel_file',
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_textarea_field',
        'show_in_rest'      => false,
    ];

    $meta_args_url = [
        'object_subtype'    => 'personnel_file',
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'esc_url_raw',
        'show_in_rest'      => false,
    ];

    register_post_meta( 'personnel_file', 'pf_alias',               $meta_args_text );
    register_post_meta( 'personnel_file', 'pf_role',                $meta_args_text );
    register_post_meta( 'personnel_file', 'pf_era',                 $meta_args_text );
    register_post_meta( 'personnel_file', 'pf_photo_url',           $meta_args_url );
    register_post_meta( 'personnel_file', 'pf_then_playlist_embed', $meta_args_embed );
    register_post_meta( 'personnel_file', 'pf_now_playlist_embed',  $meta_args_embed );
    register_post_meta( 'personnel_file', 'pf_bio',                 $meta_args_area );
}

// ===========================================================================
// 1c. Admin Meta Box
// ===========================================================================

add_action( 'add_meta_boxes', 'area51_personnel_file_meta_box' );
function area51_personnel_file_meta_box(): void {
    add_meta_box(
        'personnel_file_fields',
        'PERSONNEL FILE DETAILS',
        'area51_personnel_file_meta_box_callback',
        'personnel_file',
        'normal',
        'high'
    );
}

function area51_personnel_file_meta_box_callback( WP_Post $post ): void {
    wp_nonce_field( 'area51_personnel_file_meta', 'area51_personnel_file_meta_nonce' );

    $alias        = get_post_meta( $post->ID, 'pf_alias',               true );
    $role         = get_post_meta( $post->ID, 'pf_role',                true );
    $era          = get_post_meta( $post->ID, 'pf_era',                 true );
    $photo_url    = get_post_meta( $post->ID, 'pf_photo_url',           true );
    $then_embed   = get_post_meta( $post->ID, 'pf_then_playlist_embed', true );
    $now_embed    = get_post_meta( $post->ID, 'pf_now_playlist_embed',  true );
    $bio          = get_post_meta( $post->ID, 'pf_bio',                 true );
    ?>
    <table class="form-table">
        <tr>
            <th><label for="pf_alias">Alias / Name</label></th>
            <td>
                <input type="text" id="pf_alias" name="pf_alias"
                       value="<?php echo esc_attr( $alias ); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="pf_role">Role</label></th>
            <td>
                <input type="text" id="pf_role" name="pf_role"
                       value="<?php echo esc_attr( $role ); ?>" class="regular-text">
                <p class="description">e.g., "Resident DJ", "Partner", "Notable Guest"</p>
            </td>
        </tr>
        <tr>
            <th><label for="pf_era">Era</label></th>
            <td>
                <input type="text" id="pf_era" name="pf_era"
                       value="<?php echo esc_attr( $era ); ?>" class="regular-text">
                <p class="description">e.g., "1994–1999", "1996–2001"</p>
            </td>
        </tr>
        <tr>
            <th><label for="pf_photo_url">Photo URL</label></th>
            <td>
                <input type="url" id="pf_photo_url" name="pf_photo_url"
                       value="<?php echo esc_attr( $photo_url ); ?>" class="large-text">
                <p class="description">Paste a media URL or leave blank to use the silhouette placeholder.</p>
            </td>
        </tr>
        <tr>
            <th><label for="pf_then_playlist_embed">THEN Playlist Embed</label></th>
            <td>
                <textarea id="pf_then_playlist_embed" name="pf_then_playlist_embed"
                          rows="5" class="large-text"
                          placeholder="Paste Spotify iframe embed code here (THEN)"><?php echo esc_textarea( $then_embed ); ?></textarea>
                <p class="description">Full Spotify iframe HTML for the THEN playlist.</p>
            </td>
        </tr>
        <tr>
            <th><label for="pf_now_playlist_embed">NOW Playlist Embed</label></th>
            <td>
                <textarea id="pf_now_playlist_embed" name="pf_now_playlist_embed"
                          rows="5" class="large-text"
                          placeholder="Paste Spotify iframe embed code here (NOW)"><?php echo esc_textarea( $now_embed ); ?></textarea>
                <p class="description">Full Spotify iframe HTML for the NOW playlist.</p>
            </td>
        </tr>
        <tr>
            <th><label for="pf_bio">Bio</label></th>
            <td>
                <textarea id="pf_bio" name="pf_bio"
                          rows="4" class="large-text"
                          placeholder="Optional bio text (leave blank to hide from card)"><?php echo esc_textarea( $bio ); ?></textarea>
            </td>
        </tr>
    </table>
    <?php
}

// ===========================================================================
// 1d. Meta Save Hook
// ===========================================================================

add_action( 'save_post_personnel_file', 'area51_save_personnel_file_meta' );
function area51_save_personnel_file_meta( int $post_id ): void {
    // Nonce verification
    if ( ! isset( $_POST['area51_personnel_file_meta_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['area51_personnel_file_meta_nonce'], 'area51_personnel_file_meta' ) ) return;

    // Autosave check
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    // Capability check
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    // Save all seven pf_* keys with correct sanitizers
    if ( isset( $_POST['pf_alias'] ) ) {
        update_post_meta( $post_id, 'pf_alias', sanitize_text_field( $_POST['pf_alias'] ) );
    }
    if ( isset( $_POST['pf_role'] ) ) {
        update_post_meta( $post_id, 'pf_role', sanitize_text_field( $_POST['pf_role'] ) );
    }
    if ( isset( $_POST['pf_era'] ) ) {
        update_post_meta( $post_id, 'pf_era', sanitize_text_field( $_POST['pf_era'] ) );
    }
    if ( isset( $_POST['pf_photo_url'] ) ) {
        update_post_meta( $post_id, 'pf_photo_url', esc_url_raw( $_POST['pf_photo_url'] ) );
    }
    // [Critical] Playlist embeds — MUST use wp_kses_post, NOT sanitize_textarea_field.
    // sanitize_textarea_field strips iframe HTML silently.
    if ( isset( $_POST['pf_then_playlist_embed'] ) ) {
        update_post_meta( $post_id, 'pf_then_playlist_embed', wp_kses_post( $_POST['pf_then_playlist_embed'] ) );
    }
    if ( isset( $_POST['pf_now_playlist_embed'] ) ) {
        update_post_meta( $post_id, 'pf_now_playlist_embed', wp_kses_post( $_POST['pf_now_playlist_embed'] ) );
    }
    if ( isset( $_POST['pf_bio'] ) ) {
        update_post_meta( $post_id, 'pf_bio', sanitize_textarea_field( $_POST['pf_bio'] ) );
    }
}

// ===========================================================================
// 1e. Display Shortcode [area51_personnel_files]
// ===========================================================================

add_shortcode( 'area51_personnel_files', 'area51_personnel_files_shortcode' );
function area51_personnel_files_shortcode(): string {
    // [Prevention: ISSUE-006] Scoped wpautop fix — remove before building output, restore before return.
    // Prevents wpautop from wrapping iframe HTML or block-level divs in <p> tags.
    remove_filter( 'the_content', 'wpautop', 12 );

    $query = new WP_Query( [
        'post_type'      => 'personnel_file',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ] );

    if ( ! $query->have_posts() ) {
        add_filter( 'the_content', 'wpautop', 12 );
        return '<div class="personnel-files-empty"><div>NO PERSONNEL FILES ON RECORD.</div></div>';
    }

    $silhouette = esc_url( get_template_directory_uri() . '/images/silhouette-classified.svg' );
    $output = '<div class="personnel-files-grid">';

    while ( $query->have_posts() ) {
        $query->the_post();
        $post_id = get_the_ID();

        $alias      = esc_html( get_post_meta( $post_id, 'pf_alias',               true ) );
        $role       = esc_html( get_post_meta( $post_id, 'pf_role',                true ) );
        $era        = esc_html( get_post_meta( $post_id, 'pf_era',                 true ) );
        $photo_url  = get_post_meta( $post_id, 'pf_photo_url',           true );
        $then_embed = get_post_meta( $post_id, 'pf_then_playlist_embed', true );
        $now_embed  = get_post_meta( $post_id, 'pf_now_playlist_embed',  true );
        $bio        = esc_html( get_post_meta( $post_id, 'pf_bio', true ) );

        // Photo URL fallback to silhouette
        $img_src = ! empty( $photo_url ) ? esc_url( $photo_url ) : $silhouette;
        $img_alt = ! empty( $alias ) ? $alias : 'Personnel File';

        $output .= '<div class="personnel-file-card">';

        // Card header
        $output .= '<div class="pf-card-header">';
        $output .= '<div class="pf-card-label">PERSONNEL FILE — LEVEL CLASSIFIED</div>';
        $output .= '</div>';

        // Photo
        $output .= '<div class="pf-photo">';
        $output .= '<div class="pf-photo-wrap">';
        $output .= '<img src="' . $img_src . '" alt="' . esc_attr( $img_alt ) . '" width="80" height="80">';
        $output .= '</div>';
        $output .= '</div>';

        // Alias
        $output .= '<div class="pf-alias">' . $alias . '</div>';

        // Role
        $output .= '<div class="pf-role">' . $role . '</div>';

        // Era
        $output .= '<div class="pf-era">' . $era . '</div>';

        // Bio (only if non-empty)
        if ( ! empty( $bio ) ) {
            $output .= '<div class="pf-bio">' . $bio . '</div>';
        }

        // THEN playlist
        $output .= '<div class="pf-playlist pf-playlist--then">';
        $output .= '<div class="pf-playlist-label">THEN</div>';
        if ( ! empty( $then_embed ) ) {
            $output .= '<div class="pf-playlist-embed">' . wp_kses_post( $then_embed ) . '</div>';
        } else {
            $output .= '<div class="pf-playlist-embed pf-playlist-embed--placeholder">[PLAYLIST PENDING DECLASSIFICATION]</div>';
        }
        $output .= '</div>';

        // NOW playlist
        $output .= '<div class="pf-playlist pf-playlist--now">';
        $output .= '<div class="pf-playlist-label">NOW</div>';
        if ( ! empty( $now_embed ) ) {
            $output .= '<div class="pf-playlist-embed">' . wp_kses_post( $now_embed ) . '</div>';
        } else {
            $output .= '<div class="pf-playlist-embed pf-playlist-embed--placeholder">[PLAYLIST PENDING DECLASSIFICATION]</div>';
        }
        $output .= '</div>';

        $output .= '</div>'; // .personnel-file-card
    }

    wp_reset_postdata();

    $output .= '</div>'; // .personnel-files-grid

    // [Prevention: ISSUE-006] Restore wpautop after shortcode output is complete
    add_filter( 'the_content', 'wpautop', 12 );

    return $output;
}
