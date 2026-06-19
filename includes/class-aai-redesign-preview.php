<?php
/**
 * AAI Bootcamp — redesign preview surface.
 * ─────────────────────────────────────────────────────────────────────────────
 * A disposable, token-gated working duplicate of the Advanced Artificial
 * Intelligence bootcamp page, used to iterate the new on-page conversion
 * sections LIVE without touching the real Formação page.
 *
 * Rendering: proxies the REAL course page server-side (so we inherit the live
 * theme chrome — head/CSS, nav header, hero, footer) and SPLICES our new
 * sections into the body, dropping the old course body:
 *
 *     [ real page: top … through the hero ]   (up to <section class="ceb-mini">)
 *     [ our scoped #aai-rd sections fragment ]
 *     [ real page: <footer> … </html> ]
 *
 * Our sections are scoped under #aai-rd so the theme CSS and ours don't clash.
 * Path-matched at /aai-preview/, token-gated like the Augment page: admins or
 * ?preview=<TOKEN> see it; everyone else (and every crawler) gets a noindex
 * 404. No WP post is created. If the live fetch/splice fails, falls back to a
 * self-contained standalone template.
 *
 * @since 1.5.605
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_AAI_Redesign_Preview {

    const STATUS        = 'preview';
    const PATH          = '/aai-preview';
    const PREVIEW_TOKEN = 'aai-2026-preview';

    /** Real course page we proxy + splice into. */
    const LIVE_URL = 'https://weareedit.io/formacao/bootcamp-advanced-artificial-intelligence/';

    /** Splice markers in the live markup. */
    const HERO_BODY_MARK = 'banner-image"';       // start of the course hero <section> — cut here to drop the real hero + body (we render our own Augment-adapted hero instead)
    const FOOTER_MARK    = '<footer ';            // site footer start

    const SECTIONS  = 'includes/templates/aai-redesign-sections.html';  // scoped fragment
    const TEMPLATE  = 'includes/templates/aai-redesign-preview.html';   // standalone fallback

    public static function init(): void {
        if ( self::STATUS === 'off' ) return;
        if ( self::is_target_request() ) {
            add_action( 'template_redirect', [ __CLASS__, 'render_page' ], 0 );
        }
    }

    private static function is_target_request(): bool {
        $path = (string) parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
        return rtrim( $path, '/' ) === self::PATH;
    }

    private static function should_render_publicly(): bool {
        if ( current_user_can( 'manage_options' ) ) return true;
        $token = isset( $_GET['preview'] ) ? (string) wp_unslash( $_GET['preview'] ) : '';
        return ! empty( self::PREVIEW_TOKEN ) && hash_equals( self::PREVIEW_TOKEN, $token );
    }

    public static function render_page(): void {
        if ( ! self::should_render_publicly() ) {
            status_header( 404 );
            nocache_headers();
            header( 'X-Robots-Tag: noindex, nofollow', true );
            echo '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><title>Em breve</title></head><body><p style="font-family:system-ui;text-align:center;padding:80px 20px;color:#666">Página em construção.</p></body></html>';
            exit;
        }

        $fragment = self::sections_fragment();

        // Preferred: splice our sections into the real (proxied) course page.
        $live = self::live_html();
        if ( $live !== '' ) {
            $spliced = self::splice( $live, $fragment );
            if ( $spliced !== '' ) { self::emit( $spliced ); return; }
        }

        // Fallback: self-contained standalone template.
        $file = WEAREDIT_SITE_ENGINE_PATH . self::TEMPLATE;
        if ( file_exists( $file ) ) {
            $html = (string) file_get_contents( $file );
            $html = str_replace( '<!--EDIT-HEAD-->', '<meta name="robots" content="noindex,nofollow">', $html );
            $html = str_replace( '<!--CERT-URL-->', esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/aai/cert-sample.jpg' ), $html );
            self::emit( $html );
            return;
        }

        status_header( 500 );
        nocache_headers();
        echo 'AAI preview unavailable.';
        exit;
    }

    /** Scoped #aai-rd sections fragment, with the certificate URL resolved. */
    private static function sections_fragment(): string {
        $f = WEAREDIT_SITE_ENGINE_PATH . self::SECTIONS;
        $html = file_exists( $f ) ? (string) file_get_contents( $f ) : '';
        return str_replace( '<!--CERT-URL-->', esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/aai/cert-sample.jpg' ), $html );
    }

    /** Fetch the live course page (5-min transient cache; ?nocache to bypass). */
    private static function live_html(): string {
        $key = 'edit_aai_live_html';
        if ( empty( $_GET['nocache'] ) ) {
            $cached = get_transient( $key );
            if ( is_string( $cached ) && $cached !== '' ) return $cached;
        }
        $resp = wp_remote_get( self::LIVE_URL, [
            'timeout'     => 12,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => [ 'User-Agent' => 'edit-aai-preview/1.0 (+weareedit.io)' ],
        ] );
        if ( is_wp_error( $resp ) ) return '';
        if ( (int) wp_remote_retrieve_response_code( $resp ) !== 200 ) return '';
        $body = (string) wp_remote_retrieve_body( $resp );
        if ( $body === '' ) return '';
        set_transient( $key, $body, 300 );
        return $body;
    }

    /** Splice: [top..hero] + our sections + [footer..end]. '' on failure. */
    private static function splice( string $live, string $fragment ): string {
        $mark = strpos( $live, self::HERO_BODY_MARK );
        if ( $mark === false ) return '';
        $cut = strrpos( substr( $live, 0, $mark ), '<section' );
        if ( $cut === false ) return '';

        $foot = strpos( $live, self::FOOTER_MARK );
        if ( $foot === false || $foot <= $cut ) return '';

        $top     = substr( $live, 0, $cut );
        $dropped = substr( $live, $cut, $foot - $cut );
        $footer  = substr( $live, $foot );

        // The dropped body region may close a wrapper <div> that was opened up
        // in the hero/top region. Re-balance: append however many </div> the
        // dropped region closed-but-didn't-open, so $top isn't left dangling.
        $opens   = substr_count( $dropped, '<div' );
        $closes  = substr_count( $dropped, '</div>' );
        $missing = max( 0, $closes - $opens );
        if ( $missing > 0 ) {
            $top .= str_repeat( '</div>', $missing );
        }

        // Force noindex on the proxied <head> (defence-in-depth; the route is
        // already 404 to non-admins/crawlers).
        $top = preg_replace( '/<head[^>]*>/i', '$0<meta name="robots" content="noindex,nofollow">', $top, 1 );

        // Hard-strip the EARLY15 promo bar/overlay (JS-built — sets inline
        // styles a CSS rule can't beat) and the breadcrumbs nav, so they're
        // gone on the preview rather than merely hidden.
        $kill_scripts = '#<(script|style)\b[^>]*\bid="edit-(promo|early15)[^"]*"[^>]*>.*?</\1>#is';
        $kill_bcrumb  = '#<nav\b[^>]*class="edit-breadcrumbs"[^>]*>.*?</nav>#is';
        foreach ( array( 'top', 'footer' ) as $part ) {
            $$part = preg_replace( $kill_scripts, '', $$part );
            $$part = preg_replace( $kill_bcrumb, '', $$part );
        }

        return $top . "\n<!-- AAI redesign sections (preview) -->\n" . $fragment . "\n" . $footer;
    }

    private static function emit( string $html ): void {
        status_header( 200 );
        nocache_headers();
        header( 'X-Robots-Tag: noindex, nofollow', true );
        header( 'Content-Type: text/html; charset=UTF-8' );
        echo $html;
        exit;
    }
}
