<?php
/**
 * augment.weareedit.io — Augment AI-program sub-brand surface.
 * ─────────────────────────────────────────────────────────────────────────────
 * New EDIT. product: "Augment" — practical AI for professionals (PT market,
 * cross-client B2B/B2C). Own subdomain, own SEO surface, signature purple
 * (#7a38b4). Confidential solo build until presentation greenlight.
 *
 * Architecture: subdomain-only, mirrored on EDIT_Empresas_Page. When
 * HTTP_HOST = augment.weareedit.io we short-circuit WP rendering at
 * `template_redirect` priority 0 and emit our OWN standalone HTML document
 * (the locked v7 mockup) — completely bypassing the formacao theme chrome.
 * Unlike the empresas surface (which renders inside the theme), the Augment
 * page is a fully self-contained document (own <head>, fonts, dark hero
 * video), so we readfile() the template and exit rather than wrapping it.
 *
 * The subdomain points at the same WordPress install via DNS CNAME, so we
 * stay in the existing workflow: edit code → bump version → commit → tag →
 * push → one-click WP update.
 *
 * Status switch (resolve via effective_status() — never read STATUS directly):
 *   'live'    — subdomain renders to the public (index,follow)
 *   'preview' — only logged-in admins (or ?preview=<TOKEN>) see it; everyone
 *               else gets a clean "em construção" 404. noindex.
 *   'off'     — class is inert (subdomain falls through to default WP).
 *
 * Template HTML lives at includes/templates/augment-page.html. Assets
 * (hero video, EDIT logo, DGERT logos) live at assets/augment/ and are
 * referenced by absolute weareedit.io plugin URLs so they resolve from the
 * subdomain. The `<!--EDIT-HEAD-->` marker in the template <head> is replaced
 * at render with the status-appropriate robots/canonical meta.
 *
 * @since 1.5.555
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Augment_Page {

    /**
     * Master visibility switch. Starts at 'preview' — the program is a
     * confidential build, not yet greenlit for public launch. Flip to 'live'
     * only on explicit go-live instruction.
     */
    const STATUS = 'preview';

    /** Exact host match (port + leading www. normalised away). */
    const SUBDOMAIN = 'augment.weareedit.io';

    /**
     * Share-link bypass for preview mode. Subdomain cookies don't cross the
     * weareedit.io ↔ augment.weareedit.io boundary, so admins logged in on the
     * main site won't be recognised here — append ?preview=<TOKEN> to view.
     */
    const PREVIEW_TOKEN = 'augment-2026-preview';

    /** Canonical home (used when live). */
    const HOME_URL = 'https://augment.weareedit.io/';

    const TEMPLATE = 'includes/templates/augment-page.html';

    public static function init(): void {
        if ( self::effective_status() === 'off' ) return;

        if ( self::is_subdomain_request() ) {
            add_action( 'template_redirect', [ __CLASS__, 'render_page' ], 0 );
        }
    }

    public static function effective_status(): string {
        return self::STATUS;
    }

    private static function is_subdomain_request(): bool {
        $host = strtolower( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
        $host = preg_replace( '/:\d+$/', '', $host );   // strip port
        $host = preg_replace( '/^www\./', '', $host );  // strip leading www.
        return $host === self::SUBDOMAIN;
    }

    private static function should_render_publicly(): bool {
        $status = self::effective_status();
        if ( $status === 'live' ) return true;
        if ( $status === 'preview' ) {
            if ( current_user_can( 'manage_options' ) ) return true;
            $token = isset( $_GET['preview'] ) ? (string) wp_unslash( $_GET['preview'] ) : '';
            if ( ! empty( self::PREVIEW_TOKEN ) && hash_equals( self::PREVIEW_TOKEN, $token ) ) {
                return true;
            }
        }
        return false;
    }

    public static function render_page(): void {
        if ( ! self::should_render_publicly() ) {
            // Preview + not admin/token: clean 404 so it never gets crawled.
            status_header( 404 );
            nocache_headers();
            header( 'X-Robots-Tag: noindex, nofollow', true );
            echo '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><title>Em breve</title></head><body><p style="font-family:system-ui;text-align:center;padding:80px 20px;color:#666">augment.weareedit.io — em breve.</p></body></html>';
            exit;
        }

        $file = WEAREDIT_SITE_ENGINE_PATH . self::TEMPLATE;
        if ( ! file_exists( $file ) ) {
            status_header( 500 );
            nocache_headers();
            echo 'Augment template missing.';
            exit;
        }

        $html   = (string) file_get_contents( $file );
        $status = self::effective_status();

        // Status-appropriate <head> meta injected at the marker.
        if ( $status === 'live' ) {
            $head = '<meta name="robots" content="index,follow,max-image-preview:large">' .
                    '<link rel="canonical" href="' . esc_url( self::HOME_URL ) . '">';
        } else {
            $head = '<meta name="robots" content="noindex,nofollow">';
        }
        $html = str_replace( '<!--EDIT-HEAD-->', $head, $html );

        status_header( 200 );
        if ( $status === 'live' ) {
            header( 'Cache-Control: public, max-age=600, s-maxage=600' );
        } else {
            nocache_headers();
            header( 'X-Robots-Tag: noindex, nofollow', true );
        }
        header( 'Content-Type: text/html; charset=UTF-8' );
        if ( class_exists( 'EDIT_Augment_Feedback' ) ) {
            $html = EDIT_Augment_Feedback::inject_widget( $html );
        }
        echo $html;
        exit;
    }
}
