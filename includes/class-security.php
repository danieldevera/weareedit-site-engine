<?php
/**
 * Security & Hardening — migrated from EDIT SEO Fixes v1.0.6 (Feb 2026)
 *
 * Covers everything the old plugin handled that our plugin didn't:
 *  1. Security headers (X-Frame-Options, X-Content-Type-Options, Referrer-Policy, etc.)
 *  2. Disable XML-RPC (unused, attack surface)
 *  3. Disable comments feed (no comments on site — stops indexing of junk URLs)
 *  4. Remove WordPress version from head (security through obscurity)
 *  5. Force lang="pt-PT" on <html> element
 *
 * NOTE: Cache-Control headers are intentionally left to WP Rocket — it handles
 * browser caching headers correctly for both logged-in and anonymous users.
 * Setting them here would conflict with WP Rocket's cache layer.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Security {

    public static function init() {
        // 1. Security headers on every frontend response
        add_action( 'send_headers', [ __CLASS__, 'send_security_headers' ] );

        // 2. Disable XML-RPC
        add_filter( 'xmlrpc_enabled', '__return_false' );

        // 3. Disable comments feed (redirect to homepage)
        add_action( 'template_redirect', [ __CLASS__, 'disable_comments_feed' ], 1 );

        // 4. Remove WP version from <head> and RSS
        remove_action( 'wp_head', 'wp_generator' );
        add_filter( 'the_generator', '__return_empty_string' );

        // 5. Disable directory listing via .htaccess
        add_action( 'admin_init', [ __CLASS__, 'ensure_no_indexes' ] );

        // lang="pt-PT" is injected via output buffer (class-output-buffer.php)
        // — theme doesn't use language_attributes() so the filter has no effect there.
    }

    /**
     * Send security response headers.
     * Skips admin, AJAX, and REST requests — only fires on frontend page loads.
     */
    public static function send_security_headers() {
        if ( is_admin() || defined( 'DOING_AJAX' ) || defined( 'REST_REQUEST' ) ) return;

        // Prevent clickjacking — only allow framing from same origin
        header( 'X-Frame-Options: SAMEORIGIN' );

        // Prevent MIME-type sniffing
        header( 'X-Content-Type-Options: nosniff' );

        // Control referrer information sent with requests
        header( 'Referrer-Policy: strict-origin-when-cross-origin' );

        // Restrict access to sensitive browser features
        header( 'Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()' );

        // Force HTTPS for 1 year — prevents downgrade attacks
        // max-age=31536000 (1 year), includeSubDomains covers all subdomains
        header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );

        // Remove Server header if possible (PHP may override this)
        header_remove( 'X-Powered-By' );
    }

    /**
     * Redirect the comments feed to the homepage.
     * The site has no comments — the feed just exposes junk URLs.
     */
    public static function disable_comments_feed() {
        if ( is_comment_feed() ) {
            wp_safe_redirect( home_url( '/' ), 301 );
            exit;
        }
    }

    /**
     * Ensure Options -Indexes is in .htaccess to prevent directory listing.
     * Uses WordPress insert_with_markers so the block is safely managed.
     * Runs on admin_init — infrequent enough to not impact performance.
     */
    public static function ensure_no_indexes() {
        $htaccess = ABSPATH . '.htaccess';
        if ( ! is_writable( $htaccess ) ) return;

        insert_with_markers( $htaccess, 'EDIT. SEO Fix - Security', [
            'Options -Indexes',
        ] );
    }

    /**
     * Force lang="pt-PT" on the <html> tag regardless of WP language setting.
     * Ensures correct language signal for search engines and assistive tech.
     *
     * @param string $output Current language_attributes string.
     * @return string
     */
    public static function force_lang_pt( string $output ): string {
        // Replace any existing lang="..." with pt-PT
        $output = preg_replace( '/lang="[^"]*"/', 'lang="pt-PT"', $output );

        // If no lang attribute present at all, add it
        if ( strpos( $output, 'lang=' ) === false ) {
            $output = 'lang="pt-PT" ' . $output;
        }

        return $output;
    }
}
