<?php
/**
 * AAI Bootcamp — redesign preview surface.
 * ─────────────────────────────────────────────────────────────────────────────
 * A disposable, token-gated working duplicate of the Advanced Artificial
 * Intelligence bootcamp page, used to iterate the new on-page conversion
 * sections (alumni proof, curriculum/outcomes, student creations, tools,
 * certificate, instructor) LIVE without touching the real Formação page.
 *
 * Path-matched (not subdomain) at /aai-preview/. Mirrors the Augment-page
 * gating: STATUS='preview' → only logged-in admins or ?preview=<TOKEN> see it;
 * everyone else (and every crawler) gets a clean noindex 404. No WP post is
 * created — we short-circuit at template_redirect priority 0 and emit our own
 * standalone HTML document, then exit.
 *
 * Once the sections are locked, they get ported into the real AAI Formação
 * page and this class can be set to 'off' / removed.
 *
 * @since 1.5.605
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_AAI_Redesign_Preview {

    /** 'preview' = gated; 'off' = inert. (Never goes 'live' — port to real page instead.) */
    const STATUS = 'preview';

    /** Match path (with/without trailing slash). */
    const PATH = '/aai-preview';

    /** Share-link bypass token. */
    const PREVIEW_TOKEN = 'aai-2026-preview';

    const TEMPLATE = 'includes/templates/aai-redesign-preview.html';

    public static function init(): void {
        if ( self::STATUS === 'off' ) return;
        if ( self::is_target_request() ) {
            add_action( 'template_redirect', [ __CLASS__, 'render_page' ], 0 );
        }
    }

    private static function is_target_request(): bool {
        $path = (string) parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
        $path = rtrim( $path, '/' );
        return $path === self::PATH;
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

        $file = WEAREDIT_SITE_ENGINE_PATH . self::TEMPLATE;
        if ( ! file_exists( $file ) ) {
            status_header( 500 );
            nocache_headers();
            echo 'AAI preview template missing.';
            exit;
        }

        $html = (string) file_get_contents( $file );
        $html = str_replace( '<!--EDIT-HEAD-->', '<meta name="robots" content="noindex,nofollow">', $html );
        $html = str_replace( '<!--CERT-URL-->', esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/aai/cert-sample.jpg' ), $html );

        status_header( 200 );
        nocache_headers();
        header( 'X-Robots-Tag: noindex, nofollow', true );
        header( 'Content-Type: text/html; charset=UTF-8' );
        echo $html;
        exit;
    }
}
