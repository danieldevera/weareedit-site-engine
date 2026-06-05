<?php
/**
 * Plugin Name: * weareedit.io Site Engine
 * Plugin URI:  https://github.com/danieldevera/weareedit-site-engine
 * Description: Custom site engine for weareedit.io — SEO (meta tags, OG, schema.org, sitemap, hreflang), GEO/LLM optimization (llms.txt, AI crawler rules, Wikidata-linked Person/Organization schema), brand customization (hero typography, dot accents, CTA hover animations), Google Reviews aggregation, output-buffer HTML rewrites, virtual pages, WP Rocket cache integration, and one-time data fixes.
 * Version:     1.5.282
 * Author:      Daniel Devera
 * License:     GPL-2.0+
 * Text Domain: weareedit-site-engine
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WEAREDIT_SITE_ENGINE_VERSION', '1.5.282' );
define( 'WEAREDIT_SITE_ENGINE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WEAREDIT_SITE_ENGINE_URL', plugin_dir_url( __FILE__ ) );

/**
 * One-click updates via Plugin Update Checker (PUC) — polls GitHub releases.
 * Tag a release on https://github.com/danieldevera/weareedit-site-engine as
 * `vX.Y.Z` and WP Admin will offer a one-click update within ~12h (or instantly
 * via "Verificar atualizações" on the plugin row).
 */
require_once WEAREDIT_SITE_ENGINE_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
$weareedit_site_engine_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/danieldevera/weareedit-site-engine/',
    __FILE__,
    'weareedit-site-engine'
);
$weareedit_site_engine_update_checker->setBranch( 'main' );
// Use GitHub Releases as the canonical source (matches the GH Action that
// auto-builds the release zip on every `vX.Y.Z` tag push).
$weareedit_site_engine_update_checker->getVcsApi()->enableReleaseAssets();

/**
 * Header search rescue — two patches for theme bugs discovered 2026-05-27:
 *
 *   1. CSS: `.headerDesktop__search` has `width:0` by default and only
 *      expands to 100% when `.sticky___w5zBW` is on an ancestor. The
 *      theme's menu.js adds that class to `<header>`, but there are
 *      THREE <header> tags on the page and the class lands on one that
 *      isn't an ancestor of the desktop search container — so the
 *      container never expands and the search input stays invisible.
 *      Fix: when the wrapper has `.autocomplete__inputWrapperEnabled`,
 *      force any `.headerDesktop__search` open via :has() selector.
 *
 *   2. JS: the theme's inline `fetch2()` function (called on each keyup
 *      of the search input) is defined inside a broken IIFE that fails
 *      with "$ is not a function". So typing in the search box throws
 *      `fetch2 is not defined`. Replace with a working version at the
 *      top of <head> so it's globally available before the input renders.
 */
add_action( 'wp_head', function () {
    if ( is_admin() ) return;
    ?>
<style id="weareedit-search-rescue">
  /* Force the desktop search container open whenever the input wrapper
     is in its "Enabled" state (set by menu.js on click). Bypasses the
     broken `.sticky___w5zBW` ancestor selector on multi-header pages. */
  .headerDesktop__search:has(.autocomplete__inputWrapperEnabled){width:100% !important;}
  /* Fallback for browsers without :has() (older Safari, older Firefox) —
     use direct class targeting. The .autocomplete__inputWrapperEnabled
     class lands on the wrapper, then we hope the JS also adds searchOpen
     to .autocomplete.compact (which it does). */
  .autocomplete.searchOpen .autocomplete__inputWrapper{opacity:1 !important;animation:none !important;}
  /* Restore the right-side header CTAs (In-company + Fala connosco) on
     /formacao/ product pages. Theme applies display:none to both on
     body.single-formacao + an extra right:40px on inCompany (which
     would collide with Fala connosco at right:0). We override the
     display + restore inCompany's base right:230px so the layout
     matches the homepage exactly. Base rules already handle font,
     colour, position:fixed, etc. — we just unhide and re-anchor. */
  body.single-formacao .headerDesktop__contact{display:inline-flex !important;}
  body.single-formacao .headerDesktop__inCompany{display:inline-flex !important;right:230px !important;}
  /* Mobile only — breathing room between the HORÁRIOS / details rows
     and the sticky product action menu (Remote / Pós-Laboral / Programa
     / Quero-me inscrever). On the live page the two blocks touch with
     zero gap; this lifts the action menu off the meta text. */
  @media (max-width: 768px) {
    body.single-formacao #info_bar{margin-top:28px !important;}
  }
</style>
<script id="weareedit-search-fetch2">
/* Search-as-you-type via STATIC JSON INDEX. No admin-ajax.php call,
   no WP boot overhead — the index lives as a static file at
   /wp-content/uploads/edit-search-index.json, fetched ONCE per page
   load (deferred to browser idle time), then all filtering is
   client-side. Sub-millisecond response per keystroke regardless
   of WP overhead. Index regenerates server-side on post save. */
(function () {
    if ( typeof window.fetch2 === 'function' ) return;
    var INDEX_URL = <?php echo wp_json_encode( EDIT_Search_Index::get_index_url() ); ?>;
    var indexPromise = null;
    var debounceTimer = null;

    function loadIndex() {
        if ( indexPromise ) return indexPromise;
        indexPromise = fetch( INDEX_URL, { credentials: 'same-origin' } )
            .then( function ( r ) { return r.ok ? r.json() : []; } )
            .catch( function () { return []; } );
        return indexPromise;
    }
    // Preload on idle so the first user keystroke has the index ready.
    if ( 'requestIdleCallback' in window ) {
        requestIdleCallback( loadIndex, { timeout: 3000 } );
    } else {
        setTimeout( loadIndex, 1500 );
    }

    function escapeHtml( s ) {
        return String( s ).replace( /[&<>"']/g, function ( c ) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
        } );
    }

    function render( matches, $list ) {
        if ( ! matches.length ) { $list.removeClass( 'aberto' ).empty(); return; }
        var html = '';
        for ( var i = 0; i < matches.length; i++ ) {
            var m = matches[ i ];
            html += '<h2><a href="' + escapeHtml( m.u ) + '">'
                 +    '<font color="#ffffff">' + escapeHtml( m.t ) + '</font>'
                 +    '<font color="#ffffff"><div class="postTypeSearch">' + escapeHtml( m.l ) + '</div></font>'
                 +  '</a></h2>';
        }
        $list.addClass( 'aberto' ).html( html );
    }

    window.fetch2 = function () {
        if ( debounceTimer ) clearTimeout( debounceTimer );
        debounceTimer = setTimeout( function () {
            if ( typeof jQuery !== 'function' ) return;
            var $ = jQuery;
            var isDesktop = $( window ).width() > 991;
            var sel = isDesktop ? '.keywordSearch.desktop' : '.keywordSearch.mobile';
            var content = ( $( sel ).val() || '' ).trim().toLowerCase();
            var $list = $( '.resultadosPesquisa' );
            if ( content.length < 2 ) { $list.removeClass( 'aberto' ).empty(); return; }
            loadIndex().then( function ( index ) {
                if ( ! Array.isArray( index ) ) { render( [], $list ); return; }
                var matches = [];
                for ( var i = 0; i < index.length && matches.length < 8; i++ ) {
                    if ( index[ i ].t.toLowerCase().indexOf( content ) !== -1 ) {
                        matches.push( index[ i ] );
                    }
                }
                render( matches, $list );
            } );
        }, 80 );
    };
})();
</script>
    <?php
}, 5 );

/**
 * Hero visibility safety net — inline JS in <head> that strips WOW.js's
 * inline `style.visibility="hidden"` from the homepage hero's H1, H2,
 * DGERT pill, corporate divider, and reviews block. iOS Safari has been
 * observed (audit 2026-05-26) keeping these elements hidden even when
 * !important stylesheet rules try to override the inline style. The JS
 * runs both immediately and on DOMContentLoaded to catch both timings.
 */
add_action( 'wp_head', function () {
    if ( ! is_front_page() ) return;
    ?>
<script>(function(){function s(){var l=document.querySelectorAll('.hero h1, .hero h2, .hero .dgert-hero-pill, .hero .hero-corporate-row, .hero .hero-reviews, .hero .swipe-cta');for(var i=0;i<l.length;i++){l[i].style.visibility='visible';l[i].style.opacity='1';l[i].style.transform='none';}}s();if(document.readyState!=='loading'){s();}else{document.addEventListener('DOMContentLoaded',s);}setTimeout(s,200);setTimeout(s,1000);})();</script>
    <?php
}, 100 );

/**
 * After this plugin is updated via WP Admin (or auto-update), flush the
 * caches so CSS / HTML rewrites take effect without a manual cache purge.
 * Covers WP Rocket, WP core object cache, and the cached pages dir if
 * present. Guarded so it only runs when THIS plugin was updated.
 */
add_action( 'upgrader_process_complete', function ( $upgrader, $options ) {
    if ( ( $options['action'] ?? '' ) !== 'update' || ( $options['type'] ?? '' ) !== 'plugin' ) return;
    if ( ! in_array( plugin_basename( __FILE__ ), $options['plugins'] ?? [], true ) ) return;

    // WP Rocket page cache
    if ( function_exists( 'rocket_clean_domain' ) ) rocket_clean_domain();
    // WP Rocket minified assets (CSS / JS), so the injected <style> blocks refresh
    if ( function_exists( 'rocket_clean_minify' ) ) rocket_clean_minify();
    // Core object cache
    if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
}, 10, 2 );

// Load all modules
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-meta-tags.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-open-graph.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-twitter-cards.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-canonical.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-sitemap.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-image-alt.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-script-optimizer.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-structured-data.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-hreflang.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-analytics-dedup.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-output-buffer.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-tracking.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-course-schema.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-cheque-formacao.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-404-redirects.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-security.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-llms-txt-cleanup.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-title-spacing.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-bfcache-rerender.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-wellknown-ai.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-criticas-page.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-wp-rocket-shield.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-cf7-debug.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-cf7-mail-tags.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-cf7-bcc-archive.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-search-ajax.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-search-index.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-breadcrumbs.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-pillar-tutors.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-pillar-courses.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-pillar-cross-links.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-marketing-digital-page.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-data-science-page.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-ux-ui-design-page.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-inteligencia-artificial-page.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-programacao-page.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-formacao-corporativa-page.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-newsletter-signup.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-newsletter-picks.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-promo-overlay.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-formacao-archive-filter.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-formacao-hero.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-wp-mail-sender.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-brevo-mail-router.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-jquery-alias.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-admin-panel.php';
require_once WEAREDIT_SITE_ENGINE_PATH . 'includes/class-email-engines-panel.php';

/**
 * Bootstrap all SEO fix modules.
 */
function weareedit_site_engine_init() {
    EDIT_Meta_Tags::init();
    EDIT_Open_Graph::init();
    EDIT_Twitter_Cards::init();
    EDIT_Canonical::init();
    EDIT_Sitemap::init();
    EDIT_Image_Alt::init();
    EDIT_Script_Optimizer::init();
    EDIT_Structured_Data::init();
    EDIT_Hreflang::init();
    EDIT_Analytics_Dedup::init();
    EDIT_Output_Buffer::init();
    EDIT_Tracking::init();
    EDIT_Course_Schema::init();
    EDIT_Cheque_Formacao::init();
    EDIT_404_Redirects::init();
    EDIT_Security::init();
    EDIT_LLMS_Txt_Cleanup::init();
    EDIT_Title_Spacing::init();
    EDIT_BFCache_Rerender::init();
    EDIT_Wellknown_AI::init();
    EDIT_Criticas_Page::init();
    EDIT_WP_Rocket_Shield::init();
    EDIT_CF7_Debug::init();
    EDIT_CF7_Mail_Tags::init();
    EDIT_CF7_BCC_Archive::init();
    EDIT_Search_Ajax::init();
    EDIT_Search_Index::init();
    EDIT_Breadcrumbs::init();
    EDIT_Pillar_Courses::init();
    EDIT_Marketing_Digital_Page::init();
    EDIT_Data_Science_Page::init();
    EDIT_UX_UI_Design_Page::init();
    EDIT_Inteligencia_Artificial_Page::init();
    EDIT_Programacao_Page::init();
    EDIT_Formacao_Corporativa_Page::init();
    EDIT_Pillar_Cross_Links::init();
    EDIT_Newsletter_Signup::init();
    EDIT_Newsletter_Picks::init();
    EDIT_Promo_Overlay::init();
    EDIT_Formacao_Archive_Filter::init();
    EDIT_Formacao_Hero::init();
    EDIT_WP_Mail_Sender::init();
    EDIT_Brevo_Mail_Router::init();
    EDIT_JQuery_Alias::init();
    EDIT_Email_Engines_Panel::init();
    EDIT_Admin_Panel::init();
}
add_action( 'init', 'weareedit_site_engine_init' );

/**
 * Activation: flush rewrite rules so sitemap endpoint works.
 */
function weareedit_site_engine_activate() {
    EDIT_Sitemap::register_endpoint();
    EDIT_Criticas_Page::ensure_page_exists();
    flush_rewrite_rules();
    // Store default settings
    if ( ! get_option( 'edit_seo_fix_settings' ) ) {
        add_option( 'edit_seo_fix_settings', [
            'default_description'   => 'EDIT. é uma escola de educação digital disruptiva em Portugal, com cursos em UX/UI Design, Inteligência Artificial, Data Science, Marketing Digital, Programação e Gestão de Negócios.',
            'og_site_name'          => 'EDIT. - Disruptive Digital Education',
            'twitter_handle'        => '@weareedit',
            'primary_language'      => 'pt-PT',
            'defer_scripts'         => true,
            'preload_fonts'         => true,
            'fix_image_alts'        => true,
            'fix_duplicate_ga'      => true,
            'primary_ga_id'         => 'G-R11CP4ELEH',
            'sitemap_entries'       => 1000,
            'fix_output_buffer'     => true,
        ] );
    }
}
register_activation_hook( __FILE__, 'weareedit_site_engine_activate' );

/**
 * Auto-flush PHP opcache after any plugin update so new bytecode is
 * picked up without a manual Deactivate → Activate cycle. Some hosts
 * (including weareedit.io's) run opcache.validate_timestamps=0, which
 * caches old PHP files indefinitely.
 */
/**
 * One-time migration: populate Card 2 + Card 3 fields in settings so
 * Daniel doesn't have to type each one. Runs on every plugin load but
 * uses a flag to only execute once. Existing non-empty values are
 * preserved — defaults only fill blanks.
 */
add_action( 'init', function () {
    $flag_key = 'welcome_cards_seeded_v1';
    $settings = get_option( 'edit_seo_fix_settings', [] );
    if ( ! is_array( $settings ) ) $settings = [];
    if ( ! empty( $settings[ $flag_key ] ) ) return;

    $defaults = [
        // Card 2 — Workshop Ethics by Design (Hugo)
        'welcome_card2_typology'    => 'workshop',
        'welcome_card2_date_label'  => '13 Julho',
        'welcome_card2_title'       => 'Ethics by Design — Desenvolver e Usar IA com Responsabilidade',
        'welcome_card2_url'         => 'https://weareedit.io/formacao/workshop-online-ethics-by-design/',
        'welcome_card2_description' => 'Remote · Pós-Laboral · Novo programa',
        'welcome_card2_tutor_name'  => 'Hugo Oliveira Vicente',
        'welcome_card2_tutor_role'  => 'Head of Experience Design',
        'welcome_card2_tutor_url'   => 'https://weareedit.io/equipa/hugo-oliveira-vicente/',
        'welcome_card2_tutor_photo' => 'https://weareedit.io/wp-content/uploads/2026/06/hugo-oliveira-vicente-280x280-1.png',
        // Card 3 — Bootcamp Advanced AI (Naiara)
        'welcome_card3_typology'    => 'bootcamp',
        'welcome_card3_date_label'  => '29 Junho',
        'welcome_card3_title'       => 'Advanced Artificial Intelligence',
        'welcome_card3_url'         => 'https://weareedit.io/formacao/bootcamp-advanced-artificial-intelligence/',
        'welcome_card3_description' => 'Remote · 40 Horas · Pós-Laboral',
        'welcome_card3_tutor_name'  => 'Naiara Back',
        'welcome_card3_tutor_role'  => 'Estrategista Digital & IA aplicada ao negócio',
        'welcome_card3_tutor_url'   => 'https://weareedit.io/equipa/naiara-back/',
        'welcome_card3_tutor_photo' => 'https://weareedit.io/wp-content/uploads/2026/05/naiara-back-280x280-1.png',
    ];

    foreach ( $defaults as $k => $v ) {
        if ( empty( $settings[ $k ] ) ) $settings[ $k ] = $v;
    }
    $settings[ $flag_key ] = true;
    update_option( 'edit_seo_fix_settings', $settings, false );
}, 5 );

/**
 * One-time recovery (v1.5.220): the Email Marketing form was wiping
 * fix_output_buffer to false on every save (form didn't include the
 * checkbox; old sanitize_settings replaced rather than merged). The
 * settings sanitizer is now fixed; this migration also re-enables the
 * output buffer once so the locked homepage hero rewrites resume without
 * a manual toggle.
 */
add_action( 'init', function () {
    $flag_key = 'output_buffer_recovered_v1_5_220';
    $settings = get_option( 'edit_seo_fix_settings', [] );
    if ( ! is_array( $settings ) ) $settings = [];
    if ( ! empty( $settings[ $flag_key ] ) ) return;
    $settings['fix_output_buffer'] = true;
    $settings[ $flag_key ] = true;
    update_option( 'edit_seo_fix_settings', $settings, false );
    if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
    if ( function_exists( 'rocket_clean_domain' ) ) rocket_clean_domain();
}, 5 );

/**
 * One-time migration (v1.5.230): rename welcome sender name from
 * "Daniel Devera from EDIT." (English "from") to "Daniel Devera da
 * EDIT." (Portuguese "da") so the sender name matches the new
 * Francisco da EDIT. convention. Only touches the saved value if it
 * still matches the old default — preserves any custom name Daniel set.
 */
add_action( 'init', function () {
    $flag_key = 'welcome_sender_pt_rename_v1_5_226';
    $settings = get_option( 'edit_seo_fix_settings', [] );
    if ( ! is_array( $settings ) ) $settings = [];
    if ( ! empty( $settings[ $flag_key ] ) ) return;
    if ( ( $settings['welcome_sender_name'] ?? '' ) === 'Daniel Devera from EDIT.' ) {
        $settings['welcome_sender_name'] = 'Daniel Devera da EDIT.';
    }
    $settings[ $flag_key ] = true;
    update_option( 'edit_seo_fix_settings', $settings, false );
}, 5 );

add_action( 'upgrader_process_complete', function ( $upgrader, $hook_extra ) {
    if ( empty( $hook_extra['type'] ) || $hook_extra['type'] !== 'plugin' ) return;
    if ( empty( $hook_extra['plugins'] ) || ! is_array( $hook_extra['plugins'] ) ) return;
    $ours = false;
    foreach ( $hook_extra['plugins'] as $slug ) {
        if ( strpos( (string) $slug, 'weareedit-site-engine' ) !== false ) { $ours = true; break; }
    }
    if ( ! $ours ) return;
    if ( function_exists( 'opcache_reset' ) ) opcache_reset();
}, 10, 2 );

/**
 * Deactivation: flush rewrite rules.
 */
function weareedit_site_engine_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'weareedit_site_engine_deactivate' );

/**
 * One-time AJAX handler: write robots.txt to disk with AI crawler rules.
 * Fires once when triggered from the admin, then removes itself.
 */
add_action( 'wp_ajax_edit_seo_write_robots', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    check_ajax_referer( 'edit_seo_robots', 'nonce' );

    $robots_content  = "# robots.txt \xe2\x80\x94 weareedit.io\n";
    $robots_content .= "# Updated by weareedit.io Site Engine plugin\n\n";
    $robots_content .= "User-agent: *\n";
    $robots_content .= "Allow: /\n\n";
    $robots_content .= "Sitemap: https://weareedit.io/sitemap_index.xml\n\n";
    $robots_content .= "# AI Search Crawlers\n";
    $robots_content .= "User-agent: GPTBot\n";
    $robots_content .= "Allow: /\n\n";
    $robots_content .= "User-agent: ChatGPT-User\n";
    $robots_content .= "Allow: /\n\n";
    $robots_content .= "User-agent: PerplexityBot\n";
    $robots_content .= "Allow: /\n\n";
    $robots_content .= "User-agent: ClaudeBot\n";
    $robots_content .= "Allow: /\n\n";
    $robots_content .= "User-agent: anthropic-ai\n";
    $robots_content .= "Allow: /\n\n";
    $robots_content .= "User-agent: Google-Extended\n";
    $robots_content .= "Allow: /\n\n";
    $robots_content .= "User-agent: Amazonbot\n";
    $robots_content .= "Allow: /\n\n";
    $robots_content .= "User-agent: Applebot\n";
    $robots_content .= "Allow: /\n";

    $path   = ABSPATH . 'robots.txt';
    $result = file_put_contents( $path, $robots_content );

    if ( $result !== false ) {
        wp_send_json_success( 'robots.txt updated successfully (' . $result . ' bytes written)' );
    } else {
        wp_send_json_error( 'Failed to write ' . $path . ' — check file permissions' );
    }
} );

/**
 * One-time fix: remove noindex from the Videos page (post 59504).
 * Rank Math Free has no UI to remove noindex — this deletes the meta directly.
 * Runs once, then marks itself done via an option flag.
 */
add_action( 'admin_init', function () {
    if ( get_option( 'edit_seo_fix_videos_noindex_removed' ) ) return;
    $robots = get_post_meta( 59504, 'rank_math_robots', true );
    if ( is_array( $robots ) ) {
        $robots = array_filter( $robots, fn( $v ) => $v !== 'noindex' );
        update_post_meta( 59504, 'rank_math_robots', array_values( $robots ) );
    }
    update_option( 'edit_seo_fix_videos_noindex_removed', true );
} );

/**
 * One-time fix: remove noindex from the FAQs page (post 50128).
 * Rank Math Free/Pro Advanced tab not accessible — delete the meta directly.
 * Runs once, then marks itself done via an option flag.
 */
add_action( 'admin_init', function () {
    if ( get_option( 'edit_seo_fix_faqs_noindex_removed' ) ) return;
    $robots = get_post_meta( 50128, 'rank_math_robots', true );
    if ( is_array( $robots ) ) {
        $robots = array_filter( $robots, fn( $v ) => $v !== 'noindex' );
        update_post_meta( 50128, 'rank_math_robots', array_values( $robots ) );
    }
    update_option( 'edit_seo_fix_faqs_noindex_removed', true );
} );

/**
 * Nonce endpoint so the robots.txt writer can verify the request.
 */
add_action( 'wp_ajax_edit_seo_get_robots_nonce', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No' );
    wp_send_json_success( wp_create_nonce( 'edit_seo_robots' ) );
} );
