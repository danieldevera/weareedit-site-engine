<?php
/**
 * Formação archive — campaign URL banner.
 *
 * Server-side render: when ?campanha=early15 is on the URL and we're on a
 * /formacao/ path, the banner gets inserted directly into the page HTML
 * via the same output-buffer filter the breadcrumbs use. No JS, no
 * deferred-script timing issues, no cache surprises.
 *
 * Earlier attempts (v1.5.242–v1.5.252) used JS to either filter the grid
 * or inject the banner. The filter blanked the page; the JS injection
 * never fired in production (likely WP Rocket / Cloudflare interference
 * with the inline script).
 *
 * @package WeareEditSiteEngine
 * @since   1.5.254
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Formacao_Archive_Filter {

    const QUERY_PARAM   = 'campanha';
    const EARLY15_VALUE = 'early15';

    public static function init(): void {
        // CSS still goes via wp_head — keeps the inline style cacheable.
        add_action( 'wp_head', [ __CLASS__, 'maybe_print_css' ], 7 );
        // Banner HTML injected into the buffered response.
        add_filter( 'weareedit_site_engine_output_buffer', [ __CLASS__, 'maybe_inject_html' ], 7 );
        // Card filter — hides non-EARLY15 course-boxes server-side.
        add_filter( 'weareedit_site_engine_output_buffer', [ __CLASS__, 'maybe_filter_cards' ], 8 );
    }

    private static function is_active(): bool {
        if ( is_admin() || is_feed() ) return false;
        if ( empty( $_GET[ self::QUERY_PARAM ] ) ) return false;
        $campaign = sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_PARAM ] ) );
        if ( $campaign !== self::EARLY15_VALUE ) return false;
        $path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( (string) $_SERVER['REQUEST_URI'], '?' ) : '';
        if ( strpos( (string) $path, '/formacao' ) !== 0 ) return false;
        return true;
    }

    public static function maybe_print_css(): void {
        if ( ! self::is_active() ) return;
        ?>
<style id="edit-early15-banner-css">
#edit-early15-banner {
    position: relative !important; z-index: 5 !important;
    display: block !important; visibility: visible !important;
    opacity: 1 !important; height: auto !important;
    background: #ffdd06 !important; color: #0a0a0a !important;
    border-top: 3px solid #0a0a0a !important; border-bottom: 3px solid #0a0a0a !important;
    font-family: 'SctoGroteskA', 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
    margin: 0 !important; padding: 0 !important;
}
#edit-early15-banner .wrap {
    max-width: 1280px; margin: 0 auto; padding: 16px 40px;
    display: flex; flex-wrap: wrap; align-items: center; gap: 32px;
}
#edit-early15-banner .meta { flex: 0 0 auto; }
#edit-early15-banner .eyebrow {
    font-size: 11px; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; margin: 0 0 4px 0;
}
#edit-early15-banner .eyebrow .promo { color: #0090eb; }
#edit-early15-banner .headline {
    font-size: 22px; font-weight: 800; letter-spacing: -0.02em; line-height: 1.15; margin: 0;
}
#edit-early15-banner .headline .pct { color: #f92869; }
#edit-early15-banner .sub {
    flex: 1 1 auto; font-size: 14px; line-height: 1.45; margin: 0; text-align: right; color: #0a0a0a;
}
#edit-early15-banner .sub .early15-tag { color: #f92869; font-weight: 700; }
@media (max-width: 720px) {
    #edit-early15-banner .wrap { padding: 14px 20px; gap: 12px; }
    #edit-early15-banner .headline { font-size: 18px; }
    #edit-early15-banner .sub { text-align: left; }
}
/* Card filter — non-EARLY15 cards get this class server-side. */
.course-box.early15-hidden { display: none !important; }
</style>
        <?php
    }

    /**
     * Hide every `<a class="course-box">…</a>` that does NOT contain a
     * `<div class="course-promo-code">early15</div>` badge.
     *
     * The promo code appears inside `<div class="special-labels-container">`
     * at the top-left of each card; we just check for the text node inside
     * `course-promo-code` so case + whitespace are tolerant.
     */
    public static function maybe_filter_cards( string $html ): string {
        if ( ! self::is_active() ) return $html;
        if ( strpos( $html, 'course-box' ) === false ) return $html;

        return preg_replace_callback(
            '#<a([^>]*?)class="([^"]*?course-box[^"]*?)"([^>]*?)>(.*?)</a>#s',
            function ( array $m ): string {
                if ( preg_match( '#course-promo-code"[^>]*>\s*early15\s*<#i', $m[4] ) ) {
                    return $m[0]; // keep — has the badge
                }
                return '<a' . $m[1] . 'class="' . $m[2] . ' early15-hidden"' . $m[3] . '>' . $m[4] . '</a>';
            },
            $html
        );
    }

    public static function maybe_inject_html( string $html ): string {
        if ( ! self::is_active() ) return $html;
        // Avoid double-injection if the filter runs twice for any reason.
        if ( strpos( $html, 'id="edit-early15-banner"' ) !== false ) return $html;

        $banner = '<div id="edit-early15-banner" role="region" aria-label="Promoção Early 15">'
                . '<div class="wrap">'
                .   '<div class="meta">'
                .     '<p class="eyebrow"><span class="promo">PROMO</span> &middot; Setembro 2026</p>'
                .     '<h2 class="headline">Early <span class="pct">15%</span> &mdash; nas edições de Setembro.</h2>'
                .   '</div>'
                .   '<p class="sub">Inscreve-te até 30 Junho e fica com 15% de desconto. Procura pelos cursos com o selo <span class="early15-tag">EARLY15</span> em baixo.</p>'
                . '</div>'
                . '</div>';

        // Preferred anchor: right after our own breadcrumb nav (sits below
        // the dark hero, above the grid). Multiple fallbacks if it's missing.
        $patterns = [
            '#(</nav>\s*)(?=<!--\s*end\s+edit-breadcrumbs\s*-->|<main|<section|<div\s+class="container)#i',
            '#(<nav[^>]*class="edit-breadcrumbs[^"]*"[^>]*>.*?</nav>)#is',
            '#(<!--\s*#masthead\s*-->)#i',
            '#(<main[^>]*>)#i',
        ];
        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $html ) ) {
                return preg_replace( $pattern, '$1' . $banner, $html, 1 );
            }
        }
        // Last resort: prepend to <body>.
        return preg_replace( '#(<body[^>]*>)#i', '$1' . $banner, $html, 1 );
    }
}
