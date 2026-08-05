<?php
/**
 * Area 51 Reunion — Theme functions
 * FSE block theme: CSS design tokens delivered via theme.json, not functions.php
 *
 * [Prevention: PATTERN-038 does NOT apply] — This is an FSE block theme.
 * Do NOT add wp_enqueue_style( get_stylesheet_uri() ) — that is a classic theme pattern.
 * CSS custom properties flow through theme.json, not style.css enqueue.
 */

// Site title: the stored title ("Area 51" newline "30 Year Reunion") uses a
// literal newline + CSS white-space:pre-line so mobile shows two lines while
// desktop collapses it to a single line. Desktop should show a dash where
// that collapse happens; mobile should stay exactly as it was (clean two
// lines, no dash). A single text string can't do both with CSS alone, so
// replace the newline with an explicit dash span + <br> and control each
// independently per breakpoint (see area51-components.css).
add_filter( 'render_block_core/site-title', 'area51_site_title_add_dash', 10, 1 );
function area51_site_title_add_dash( string $block_content ): string {
    return str_replace(
        "\n",
        '<span class="area51-title-dash"> &mdash; </span><br class="area51-title-break">',
        $block_content
    );
}

// Security hardening (post-incident, 2026-08-05): XML-RPC is a well-known
// brute-force/amplification vector (pingback abuse, system.multicall).
// This site has no use for remote publishing or XML-RPC-based integrations.
// The xmlrpc_enabled filter alone does NOT block the endpoint (verified —
// system.listMethods still responded with it set) since xmlrpc.php is a
// separate bootstrap script that only checks that filter for specific
// methods like pingback. Hard-block the request itself instead.
add_filter( 'xmlrpc_enabled', '__return_false' );
if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
    http_response_code( 403 );
    die( 'XML-RPC is disabled on this site.' );
}

// [Layer 03] ISSUE-006 fix: reorder wpautop to run after do_shortcode (priority 11).
// wpautop at default priority 10 adds stray </p> inside shortcode output.
// Moving to priority 12 ensures shortcodes run first, then wpautop wraps any remaining prose.
// Place at top level — must execute at parse time, not inside a hook.
remove_filter( 'the_content', 'wpautop' );
add_filter( 'the_content', 'wpautop', 12 );

// Block theme support declaration
function area51_setup() {
    add_theme_support( 'block-templates' );
    add_theme_support( 'editor-color-palette' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'html5', [ 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption' ] );
}
add_action( 'after_setup_theme', 'area51_setup' );

// [Layer 04] Add dossier-page body class for scoped CSS targeting
// FSE block themes do not expose <body> in template HTML — body_class filter is the canonical mechanism.
// Page slug is 'lore' (renamed from 'dossier' 2026-07-28); class name kept as
// dossier-page/dossier-* since CSS selectors throughout area51-components.css
// and the page-dossier.html template still use that internal naming.
add_filter( 'body_class', 'area51_dossier_body_class' );
function area51_dossier_body_class( array $classes ): array {
    if ( is_page( 'lore' ) ) {
        $classes[] = 'dossier-page';
    }
    return $classes;
}

// [Layer 05] Add music-page body class for scoped CSS targeting
// Same pattern as area51_dossier_body_class. is_page('music') matches post_name=music.
add_filter( 'body_class', 'area51_music_body_class' );
function area51_music_body_class( array $classes ): array {
    if ( is_page( 'music' ) ) {
        $classes[] = 'music-page';
    }
    return $classes;
}

// [Layer 06] Add memory-wall-page body class for scoped CSS targeting
// Same pattern as area51_dossier_body_class and area51_music_body_class.
// is_page('memory-wall') matches post_name=memory-wall.
add_filter( 'body_class', 'area51_memory_wall_body_class' );
function area51_memory_wall_body_class( array $classes ): array {
    if ( is_page( 'memory-wall' ) ) {
        $classes[] = 'memory-wall-page';
    }
    return $classes;
}

// [Layer 07] Add personnel-files-page body class for scoped CSS targeting
add_filter( 'body_class', 'area51_personnel_files_body_class' );
function area51_personnel_files_body_class( array $classes ): array {
    if ( is_page( 'personnel-files' ) ) {
        $classes[] = 'personnel-files-page';
    }
    return $classes;
}

// [Layer 08] Add flyer-gallery-page body class for scoped CSS targeting
add_filter( 'body_class', 'area51_flyer_gallery_body_class' );
function area51_flyer_gallery_body_class( array $classes ): array {
    if ( is_page( 'flyer-gallery' ) ) {
        $classes[] = 'flyer-gallery-page';
    }
    return $classes;
}

// [Layer 09] Add faq-page body class for scoped CSS targeting
add_filter( 'body_class', 'area51_faq_body_class' );
function area51_faq_body_class( array $classes ): array {
    if ( is_page( 'faq' ) ) {
        $classes[] = 'faq-page';
    }
    return $classes;
}

// [Layer 09] Add tribute-page body class for scoped CSS targeting
add_filter( 'body_class', 'area51_tribute_body_class' );
function area51_tribute_body_class( array $classes ): array {
    if ( is_page( 'tribute' ) ) {
        $classes[] = 'tribute-page';
    }
    return $classes;
}

// [Layer 09] Add press-page body class for scoped CSS targeting
add_filter( 'body_class', 'area51_press_body_class' );
function area51_press_body_class( array $classes ): array {
    if ( is_page( 'press' ) ) {
        $classes[] = 'press-page';
    }
    return $classes;
}

// Skin decision locked in: "Gear / Signal" (skin-terminal) is now the permanent
// sitewide style, applied unconditionally (replaces the removed admin-only
// style switcher from Layer 11). Same body_class pattern as the filters above.
add_filter( 'body_class', 'area51_skin_terminal_body_class' );
function area51_skin_terminal_body_class( array $classes ): array {
    $classes[] = 'skin-terminal';
    return $classes;
}

// Custom Post Types — Layer 02+ will register Missing Persons (classified_person) and
// Clearance Requests (clearance_request) here. Stubs only at Layer 00.
// function area51_register_post_types() { ... }
// add_action( 'init', 'area51_register_post_types' );

// Register WordPress options on init (programmatic — not exposed in Settings API UI)
add_action( 'init', 'area51_register_options' );
function area51_register_options(): void {
    if ( false === get_option( 'area51_located_count' ) ) {
        add_option( 'area51_located_count', 0 );
    }
    if ( false === get_option( 'area51_kit_form_id' ) ) {
        add_option( 'area51_kit_form_id', 0 );
    }
}

// Register shortcodes
add_action( 'init', 'area51_register_shortcodes' );
function area51_register_shortcodes(): void {
    add_shortcode( 'area51_counter', 'area51_counter_shortcode' );
    add_shortcode( 'area51_board',   'area51_board_shortcode' );
}

function area51_counter_shortcode(): string {
    $count = (int) get_option( 'area51_located_count', 0 );
    return '<span class="area51-counter-value">' . esc_html( $count ) . '</span> out of <span class="area51-redacted">&nbsp;&nbsp;47&nbsp;&nbsp;</span> subjects located';
}

/**
 * [area51_board] shortcode — renders the Missing Persons Board card grid.
 *
 * [Layer 03] Status-conditional rendering: DECLASSIFIED cards reveal first_name.
 * CLASSIFIED cards retain alias. first_name is NEVER shown on classified cards.
 *
 * ISSUE-006 scoped fix: remove wpautop before returning so the block renderer
 * does not wrap <img> elements in <p> tags. In FSE block themes the shortcode
 * block does not go through the_content filter the same way as in classic themes,
 * so the top-level priority-12 reorder is insufficient. Removing wpautop for the
 * duration of this shortcode callback is the reliable fix.
 */
function area51_board_shortcode(): string {
    // [Layer 03] ISSUE-006 scoped fix: temporarily remove wpautop while building
    // shortcode output. The top-level remove_filter + add_filter at priority 12
    // does not prevent wpautop from processing inside FSE block shortcode output.
    remove_filter( 'the_content', 'wpautop', 12 );

    $query = new WP_Query( [
        'post_type'      => 'missing_person',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    if ( ! $query->have_posts() ) {
        add_filter( 'the_content', 'wpautop', 12 );
        return '<p class="area51-board-empty">No subjects on file.</p>';
    }

    $silhouette_url = esc_url( get_template_directory_uri() . '/images/silhouette-classified.svg' );
    $output = '<div class="area51-board-grid">';

    while ( $query->have_posts() ) {
        $query->the_post();
        $status     = get_post_meta( get_the_ID(), 'status', true );
        $alias      = esc_html( get_post_meta( get_the_ID(), 'alias', true ) );
        $role       = esc_html( get_post_meta( get_the_ID(), 'role', true ) );
        $era        = esc_html( get_post_meta( get_the_ID(), 'era', true ) );
        $first_name = esc_html( get_post_meta( get_the_ID(), 'first_name', true ) );

        if ( 'declassified' === $status ) {
            $output .= '<div class="area51-card area51-card--declassified">';
            $output .= '<div class="area51-card__silhouette-wrap"><img class="area51-card__silhouette" src="' . $silhouette_url . '" alt="' . $first_name . '" width="80" height="80"></div>';
            $output .= '<div class="area51-card__name">' . $first_name . '</div>';
            $output .= '<div class="area51-card__role">' . $role . '</div>';
            $output .= '<div class="area51-card__era">' . $era . '</div>';
            $output .= '</div>';
        } else {
            $output .= '<div class="area51-card area51-card--classified">';
            $output .= '<div class="area51-card__silhouette-wrap"><img class="area51-card__silhouette" src="' . $silhouette_url . '" alt="Unknown subject" width="80" height="80"></div>';
            $output .= '<div class="area51-card__alias">' . $alias . '</div>';
            $output .= '<div class="area51-card__role">' . $role . '</div>';
            $output .= '<div class="area51-card__era">' . $era . '</div>';
            $output .= '</div>';
        }
    }
    wp_reset_postdata();

    $output .= '</div>';

    // Re-add wpautop after shortcode output is complete
    add_filter( 'the_content', 'wpautop', 12 );

    return $output;
}

// Include subscribe handler (must be at top level — AJAX hooks need early registration)
require_once get_template_directory() . '/inc/subscribe-handler.php';

// Layer 02: Custom post types, post meta, and admin meta boxes
require_once get_template_directory() . '/inc/post-types.php';

// Layer 02: Clearance request AJAX handler (must be at top level — AJAX hooks need early registration)
require_once get_template_directory() . '/inc/clearance-handler.php';

// Layer 06: Memory Wall — Incident Reports CPT, meta, shortcodes, AJAX handler
require_once get_template_directory() . '/inc/incident-reports.php';

// Layer 07: Personnel Files CPT, meta, meta box, shortcode
require_once get_template_directory() . '/inc/personnel-files.php';

// [Layer 08] Flyer Gallery: CPT, meta box, shortcodes, upload handler
require_once get_template_directory() . '/inc/flyer-gallery.php';

// Layer 14: Unified Contact CPT — area51_contact registration + area51_upsert_contact()
require_once get_template_directory() . '/inc/contact-post-type.php';

// Register AJAX actions — subscribe
add_action( 'wp_ajax_nopriv_area51_subscribe', 'area51_handle_subscribe' );
add_action( 'wp_ajax_area51_subscribe',        'area51_handle_subscribe' );

// Register AJAX actions — clearance request (handler registered in clearance-handler.php)
// Hooks added in clearance-handler.php: wp_ajax_nopriv_area51_clearance_request, wp_ajax_area51_clearance_request

// Enqueue scripts
add_action( 'wp_enqueue_scripts', 'area51_enqueue_scripts' );
function area51_enqueue_scripts(): void {
    $theme_uri = get_template_directory_uri();

    // Layer 02: Component-level CSS (resolves ISSUE-005)
    // Enqueued before JS so styles are available on all pages including homepage
    // [Layer 04] Version bumped to 1.4.0 to bust browser cache after Layer 04 CSS additions.
    // [Layer 05] Version bumped to 1.5.0 to bust browser cache after Layer 05 CSS additions.
    // [Layer 06] Version bumped to 1.6.0 to bust browser cache after Layer 06 CSS additions.
    // [Layer 07] Version bumped to 1.7.0 to bust browser cache after Layer 07 CSS additions.
    // Version bumped to 2.9.2 to bust browser cache after Music page teaser-row/Sound CSS additions.
    // Version bumped to 2.9.3 to bust browser cache after Flyer Gallery green-to-purple recolor.
    // Version bumped to 2.9.5 to bust browser cache after reverting homepage email input resize/alignment.
    // Version bumped to 2.9.6 to bust browser cache after mobile site-title 2-line break.
    // Version bumped to 2.9.7 to bust browser cache after homepage section-spacing fix and footer 2-line break.
    wp_enqueue_style(
        'area51-components',
        $theme_uri . '/css/area51-components.css',
        [],
        '2.9.7'
    );

    // Skin decision locked in: "Gear / Signal" — was Layer 11's admin-only
    // skin-terminal option, now the permanent sitewide style (switcher removed).
    wp_enqueue_style(
        'area51-skin-terminal',
        $theme_uri . '/css/skins/skin-terminal.css',
        [ 'area51-components' ],
        '1.0.10'
    );

    wp_enqueue_script(
        'area51-countdown',
        $theme_uri . '/js/countdown.js',
        [],
        '1.0.0',
        true // load in footer
    );

    wp_enqueue_script(
        'area51-subscribe',
        $theme_uri . '/js/subscribe.js',
        [],
        '1.0.0',
        true // load in footer
    );

    wp_localize_script( 'area51-subscribe', 'area51Subscribe', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'area51_subscribe_nonce' ),
    ] );

    // Layer 02: Clearance request AJAX JS
    wp_enqueue_script(
        'area51-clearance',
        $theme_uri . '/js/clearance.js',
        [],
        '1.0.0',
        true // load in footer
    );
    wp_localize_script( 'area51-clearance', 'area51Clearance', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'area51_clearance_nonce' ),
    ] );

    // Layer 06: Memory Wall — enqueue incident-report.js only on memory-wall page
    // [Prevention: PATTERN-042] — Conditional enqueue prevents area51IncidentReport nonce
    // appearing on every page. Only /memory-wall needs this object.
    if ( is_page( 'memory-wall' ) ) {
        wp_enqueue_script(
            'area51-incident-report',
            $theme_uri . '/js/incident-report.js',
            [],
            '1.0.0',
            true // load in footer
        );
        wp_localize_script( 'area51-incident-report', 'area51IncidentReport', [  // [Prevention: PATTERN-042]
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'area51_incident_report_nonce' ),
        ] );
    }
}

// [Layer 03] Admin-only includes — AJAX handlers + column registration
if ( is_admin() ) {
    require_once get_template_directory() . '/inc/admin-columns.php';
    require_once get_template_directory() . '/inc/declassify-handler.php';
    require_once get_template_directory() . '/inc/clearance-approval-handler.php';
    require_once get_template_directory() . '/inc/contact-send-handler.php';   // Layer 16
}

// [Layer 03] Admin script enqueue — DECLASSIFY and Clearance Approve JS
// [Prevention: PATTERN-042] — Two distinct localized objects, one per list screen.
// area51DeclassifyAdmin      → missing_person list     → nonce action: area51_declassify_nonce
// area51ClearanceApproveAdmin → clearance_request list → nonce action: area51_clearance_approve_nonce
add_action( 'admin_enqueue_scripts', 'area51_enqueue_admin_scripts' );
function area51_enqueue_admin_scripts( string $hook_suffix ): void {
    if ( 'edit.php' !== $hook_suffix ) return;
    global $post_type;

    if ( 'missing_person' === $post_type ) {
        wp_enqueue_script(
            'area51-admin-declassify',
            get_template_directory_uri() . '/js/admin-declassify.js',
            [],
            '1.0.0',
            true
        );
        wp_localize_script( 'area51-admin-declassify', 'area51DeclassifyAdmin', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'area51_declassify_nonce' ),
        ] );
    }

    if ( 'clearance_request' === $post_type ) {
        wp_enqueue_script(
            'area51-admin-clearance-approve',
            get_template_directory_uri() . '/js/admin-clearance-approve.js',
            [],
            '1.0.0',
            true
        );
        wp_localize_script( 'area51-admin-clearance-approve', 'area51ClearanceApproveAdmin', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'area51_clearance_approve_nonce' ),
        ] );
    }

    if ( 'area51_contact' === $post_type ) {
        wp_enqueue_script(
            'area51-admin-contact-send',
            get_template_directory_uri() . '/js/admin-contact-send.js',
            [],
            '1.0.0',
            true
        );
        wp_localize_script( 'area51-admin-contact-send', 'area51ContactSendAdmin', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'area51_contact_send_nonce' ),
        ] );
    }
}
