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
/* Hide non-matching cards once JS has tagged the survivors. The body
   class gates the rule so a JS failure leaves cards visible by default
   (the safety net — better to show all September courses than a blank
   archive). */
body.early15-filtering [data-formacao-card]:not(.early15-keep) { display: none !important; }
</style>
<script id="edit-early15-filter-js">
(function () {
    'use strict';
    // Preferred match: visible EARLY15 badge anywhere in card text.
    var RE_EARLY15 = /EARLY\s*15/i;
    // Floor match: any Setembro 2026 start date.
    var RE_SEPT_2026 = /Setembro\s+2026/i;

    function findCards() {
        var selectors = [
            '.card-formacao', '.formacao-card', '.course-card',
            'article.formacao', '.card-curso', '.grid-formacao > *',
            'article[class*="formacao"]', 'li[class*="formacao"]'
        ];
        var seen = [];
        for ( var i = 0; i < selectors.length; i++ ) {
            var nodes = document.querySelectorAll( selectors[ i ] );
            for ( var j = 0; j < nodes.length; j++ ) {
                if ( seen.indexOf( nodes[ j ] ) === -1 ) seen.push( nodes[ j ] );
            }
        }
        // Anchor fallback: each course link bubbles up to its card wrapper.
        var anchors = document.querySelectorAll( 'a[href*="/formacao/"]' );
        for ( var k = 0; k < anchors.length; k++ ) {
            var href = ( anchors[ k ].getAttribute( 'href' ) || '' ).replace( /\/$/, '' );
            if ( href.endsWith( '/formacao' ) ) continue; // skip self-link to archive
            var card = anchors[ k ].closest( 'article, li, [class*="card"], [class*="formacao"], [class*="curso"]' );
            if ( card && seen.indexOf( card ) === -1 ) seen.push( card );
        }
        return seen;
    }

    function run() {
        var cards = findCards();
        if ( ! cards.length ) return;

        var earlyCards = [];
        var septCards  = [];
        for ( var i = 0; i < cards.length; i++ ) {
            var txt = cards[ i ].textContent || '';
            if ( RE_EARLY15.test( txt ) ) earlyCards.push( cards[ i ] );
            if ( RE_SEPT_2026.test( txt ) ) septCards.push( cards[ i ] );
        }

        // Prefer EARLY15 badge matches; fall back to September date; bail if
        // neither matches anything (don't blank the page).
        var keep;
        if ( earlyCards.length )      keep = earlyCards;
        else if ( septCards.length )  keep = septCards;
        else                          return;

        for ( var j = 0; j < cards.length; j++ ) cards[ j ].setAttribute( 'data-formacao-card', '1' );
        for ( var k = 0; k < keep.length; k++ )  keep[ k ].classList.add( 'early15-keep' );
        document.body.classList.add( 'early15-filtering' );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', run );
    } else {
        run();
    }
    // Re-run on window.load in case cards are injected after DOMContentLoaded.
    window.addEventListener( 'load', run );
})();
</script>
        <?php
    }

}
