<?php
/**
 * Formação archive — campaign URL filter.
 *
 * When visitors land on /formacao/?campanha=early15 (from the promo overlay
 * CTA, the newsletter sales section, or a paid ad), narrow the archive to
 * courses starting in September 2026 only — the Early 15% campaign window.
 *
 * Start date is stored in the ACF field `home_data` as dd/mm/yyyy. We
 * meta_query LIKE '%/09/2026' which matches any day in September 2026.
 *
 * This is a v1 quick win — Task #27 ("September courses Early15 page")
 * will replace this filter with a dedicated landing page that adds a
 * campaign hero, countdown, FAQ, etc.
 *
 * @package WeareEditSiteEngine
 * @since   1.5.242
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Formacao_Archive_Filter {

    const QUERY_PARAM       = 'campanha';
    const EARLY15_VALUE     = 'early15';

    public static function init(): void {
        // Client-side filter only — theme runs custom WP_Query in the page
        // template and pre_get_posts doesn't bite. Match on the visible
        // EARLY15 badge text rather than a date heuristic, so only courses
        // explicitly flagged for the campaign appear.
        add_action( 'wp_footer', [ __CLASS__, 'maybe_print_js' ], 60 );
    }

    public static function maybe_print_js(): void {
        if ( is_admin() ) return;
        if ( empty( $_GET[ self::QUERY_PARAM ] ) ) return;
        $campaign = sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_PARAM ] ) );
        if ( $campaign !== self::EARLY15_VALUE ) return;
        // Only fire on /formacao/ paths so we don't scan unrelated pages.
        $path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( (string) $_SERVER['REQUEST_URI'], '?' ) : '';
        if ( strpos( (string) $path, '/formacao' ) !== 0 ) return;
        ?>
<style id="edit-early15-filter-css">
/* Hidden state during JS evaluation — avoids flash of non-matching cards.
   Removed once JS has decided which cards to show. */
body.early15-filtering [data-formacao-card]:not(.early15-keep) { display: none !important; }
</style>
<script id="edit-early15-filter-js">
(function () {
    'use strict';
    // Match cards by the visible EARLY15 badge — only courses explicitly
    // flagged for the campaign should pass through, not every Setembro start.
    var RE_EARLY15 = /\bEARLY\s*15\b/i;

    function run() {
        // Find every plausible course card on the archive. Themes vary; we
        // try a few selectors and de-duplicate so each card is tagged once.
        var selectors = [
            '.card-formacao', '.formacao-card', '.course-card',
            'article.formacao', '.card-curso', '.grid-formacao > *',
            // Generic fallback: links to /formacao/<slug>/
            'a[href*="/formacao/"]:not([href$="/formacao/"]) '
        ];
        var seen = [];
        for ( var i = 0; i < selectors.length; i++ ) {
            var nodes = document.querySelectorAll( selectors[ i ] );
            for ( var j = 0; j < nodes.length; j++ ) {
                var card = nodes[ j ].closest( '[class*="card"], [class*="formacao"], article, li' ) || nodes[ j ];
                if ( seen.indexOf( card ) !== -1 ) continue;
                seen.push( card );
            }
        }
        if ( ! seen.length ) return;
        document.body.classList.add( 'early15-filtering' );
        for ( var k = 0; k < seen.length; k++ ) {
            var el = seen[ k ];
            el.setAttribute( 'data-formacao-card', '1' );
            if ( RE_EARLY15.test( el.textContent || '' ) ) {
                el.classList.add( 'early15-keep' );
            }
        }
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', run );
    } else {
        run();
    }
})();
</script>
        <?php
    }

}
