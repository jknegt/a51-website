<?php
/**
 * [Layer 11] Style Switcher Infrastructure
 * Admin-only, self-contained, trivially removable feature.
 * To fully remove: delete this file and its one require_once line in functions.php.
 *
 * Gating: current_user_can( 'manage_options' ) on ALL THREE hooks below.
 * This is a deliberate, spec-mandated departure from this theme's existing
 * edit_posts / edit_post gating elsewhere (declassify-handler.php,
 * clearance-approval-handler.php, post-types.php, incident-reports.php,
 * personnel-files.php, flyer-gallery.php). Do not harmonize this down to
 * edit_posts — manage_options is intentional and correct for a sitewide
 * visual toggle.
 */

// Single source of truth for the rendered radio list.
// [Layer 11] Decision 3: style-switcher.js discovers skins dynamically from
// the rendered radios — adding a skin here (Layer 12) requires no JS change.
function area51_style_switcher_skins(): array {
    return [
        'skin-classified' => 'Classified (default)',
        'skin-terminal'   => 'Gear / Signal',
        'skin-rave'       => 'Deep Transmission',
        'skin-archive'    => 'Halo',
    ];
}

// 1. Anti-flash: apply the persisted skin class before first paint.
// wp_body_open fires immediately after <body ...> opens, via WP core's
// block-theme template-canvas.php — <body> exists, nothing painted yet.
// wp_head is too early (no <body> element exists yet at that point).
add_action( 'wp_body_open', 'area51_style_switcher_antiflash_script' );
function area51_style_switcher_antiflash_script(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <script id="area51-style-switcher-antiflash">
    (function() {
        var skin = localStorage.getItem('area51_active_skin') || 'skin-classified';
        if (document.body) {
            document.body.classList.add(skin);
        }
    })();
    </script>
    <?php
}

// 2. Switcher UI markup — rendered in wp_footer, standard hook, fires just
// before </body>. Widget CSS is inlined here (not added to
// area51-components.css) so the entire feature stays in this one file.
add_action( 'wp_footer', 'area51_style_switcher_render_ui' );
function area51_style_switcher_render_ui(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $skins = area51_style_switcher_skins();
    ?>
    <style id="area51-style-switcher-widget-css">
        #area51-style-switcher {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 999999;
            background-color: #0d0d0d;
            color: #f5e6c8;
            border: 1px solid #BF5FFF;
            border-radius: 4px;
            padding: 0.75rem 1rem;
            font-family: "Courier New", Courier, monospace;
            font-size: 0.85rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.5);
        }
        #area51-style-switcher legend {
            font-weight: bold;
            padding: 0 0.25rem;
        }
        #area51-style-switcher label {
            display: block;
            margin-top: 0.25rem;
            cursor: pointer;
        }
    </style>
    <div id="area51-style-switcher">
        <fieldset>
            <legend>STYLE SWITCHER (admin only)</legend>
            <?php foreach ( $skins as $value => $label ) : ?>
                <label>
                    <input type="radio" name="area51-skin" value="<?php echo esc_attr( $value ); ?>">
                    <?php echo esc_html( $label ); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
    </div>
    <?php
}

// 3. Stylesheet + JS enqueue — admin-only, kept in its own callback (NOT
// merged into functions.php's area51_enqueue_scripts()) so the whole feature
// is deletable by removing one require_once line.
add_action( 'wp_enqueue_scripts', 'area51_style_switcher_enqueue' );
function area51_style_switcher_enqueue(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $theme_uri = get_template_directory_uri();

    wp_enqueue_style( 'area51-skin-classified', $theme_uri . '/css/skins/skin-classified.css', [ 'area51-components' ], '1.0.0' );
    wp_enqueue_style( 'area51-skin-terminal',   $theme_uri . '/css/skins/skin-terminal.css',   [ 'area51-components' ], '1.0.10' );
    wp_enqueue_style( 'area51-skin-rave',       $theme_uri . '/css/skins/skin-rave.css',       [ 'area51-components' ], '1.0.1' );
    wp_enqueue_style( 'area51-skin-archive',    $theme_uri . '/css/skins/skin-archive.css',    [ 'area51-components' ], '1.0.1' );

    wp_enqueue_script( 'area51-style-switcher', $theme_uri . '/js/style-switcher.js', [], '1.0.0', true );
}
