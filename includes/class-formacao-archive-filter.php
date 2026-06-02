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
    flex: 1 1 auto; font-size: 14px; line-height: 1.45; margin: 0; text-align: right;
}
#edit-early15-banner .sub strong { font-weight: 700; }
@media (max-width: 720px) {
    #edit-early15-banner .wrap { padding: 14px 20px; gap: 12px; }
    #edit-early15-banner .headline { font-size: 18px; }
    #edit-early15-banner .sub { text-align: left; }
}
</style>
        <?php
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
                .   '<p class="sub">Inscreve-te até <strong>30 Junho</strong> e fica com 15% de desconto. Procura pelos cursos com o selo <strong>EARLY15</strong> em baixo.</p>'
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
