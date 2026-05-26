<?php
/**
 * Resource Hints + Script Deferral + Duplicate Script Removal
 *
 * - Adds preconnect/dns-prefetch for actual third-party origins
 * - Defers analytics and theme animation/slider scripts
 * - Removes the theme's duplicate old jQuery (v2.2.4 from code.jquery.com)
 * - Adds LCP image preload hint
 * - Adds font-display=swap to any Google Fonts URLs (if used)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Script_Optimizer {

    /**
     * Script handles (partial match) safe to add defer.
     * Covers both analytics scripts and theme animation/slider libraries
     * that do not need to block page rendering.
     */
    private static $deferrable = [
        // Analytics & tracking
        'google-tag-manager',
        'gtm',
        'google-analytics',
        'ga-',
        'facebook-pixel',
        'fb-pixel',
        'hotjar',
        'monsterinsights',
        'analytify',
        'moove-gdpr',
        // Theme render-blocking libraries
        'wow',
        'scrolla',
        'gsap',
        'splitting',
        'scrolltrigger',
        'cookiw',
        'misha_scripts',
        'weareedit-menu',
        'weareedit-animations',
        'weareedit-app',
    ];

    public static function init() {
        $settings = get_option( 'edit_seo_fix_settings', [] );

        if ( ! empty( $settings['preload_fonts'] ) ) {
            add_action( 'wp_head', [ __CLASS__, 'add_resource_hints' ], 1 );
            // Load fonts.css asynchronously so it doesn't block rendering (FOIT fix)
            add_filter( 'style_loader_tag', [ __CLASS__, 'async_font_stylesheet' ], 10, 4 );
        }

        // Always dequeue the theme's duplicate old jQuery regardless of defer setting.
        // WordPress already bundles jQuery v3 — the extra copy wastes an external request.
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'dequeue_duplicate_jquery' ], 100 );

        if ( ! empty( $settings['defer_scripts'] ) ) {
            add_filter( 'script_loader_tag', [ __CLASS__, 'defer_scripts' ], 10, 2 );
        }

        // Add font-display: swap to Google Fonts URLs (if any are loaded)
        add_filter( 'style_loader_src', [ __CLASS__, 'add_font_display_swap' ], 10, 2 );
    }

    /**
     * Remove the theme's manually-enqueued old jQuery (handle: jqueryEDIT).
     * WordPress core already provides jQuery — the duplicate adds 29 KB + an
     * extra external connection to code.jquery.com for nothing.
     */
    public static function dequeue_duplicate_jquery() {
        wp_dequeue_script( 'jqueryEDIT' );
        wp_deregister_script( 'jqueryEDIT' );
    }

    /**
     * Add defer attribute to render-blocking and non-critical script tags.
     */
    public static function defer_scripts( string $tag, string $handle ): string {
        foreach ( self::$deferrable as $match ) {
            if ( strpos( strtolower( $handle ), strtolower( $match ) ) !== false ) {
                if ( strpos( $tag, ' defer' ) === false ) {
                    $tag = str_replace( ' src=', ' defer src=', $tag );
                }
                return $tag;
            }
        }
        return $tag;
    }

    /**
     * Add preconnect/dns-prefetch hints for the actual third-party origins
     * loaded by this site. Previously pointed to Google Fonts which are NOT
     * used (fonts are self-hosted) — corrected to real CDN origins.
     *
     * Also adds a preload hint for the LCP hero image.
     */
    public static function add_resource_hints() {
        // Preconnect: origins that serve render-critical or above-the-fold resources
        $preconnect = [
            'https://code.jquery.com',          // theme loads jQuery from here
            'https://cdnjs.cloudflare.com',     // GSAP + ScrollTrigger
            'https://unpkg.com',                // Splitting.js
            'https://www.googletagmanager.com', // GTM
        ];

        // DNS-prefetch: origins used later (below the fold or async)
        $dns_prefetch = [
            'https://www.google-analytics.com',
            'https://connect.facebook.net',
            'https://livechat.hellomedian.com',
            'https://www.google.com',           // reCAPTCHA
        ];

        echo "\n<!-- EDIT. SEO Fix: Resource Hints -->\n";

        foreach ( $preconnect as $origin ) {
            echo '<link rel="preconnect" href="' . esc_url( $origin ) . '" crossorigin>' . "\n";
        }
        foreach ( $dns_prefetch as $origin ) {
            echo '<link rel="dns-prefetch" href="' . esc_url( $origin ) . '">' . "\n";
        }

        // Preload the LCP hero image so the browser discovers it immediately,
        // before render-blocking scripts finish loading.
        $lcp_image = self::get_lcp_image();
        if ( $lcp_image ) {
            echo '<link rel="preload" as="image" href="' . esc_url( $lcp_image ) . '">' . "\n";
        }

        echo "<!-- /EDIT. SEO Fix: Resource Hints -->\n";
    }

    /**
     * Return the URL of the LCP hero image for the current page.
     * Falls back to the featured image of the current post/page.
     */
    private static function get_lcp_image(): string {
        $settings = get_option( 'edit_seo_fix_settings', [] );

        // On singular pages, the featured image is always the correct LCP candidate.
        // get_the_post_thumbnail_url() falls back to the original upload if a crop isn't available.
        if ( is_singular() ) {
            $thumb_url = get_the_post_thumbnail_url( null, 'full' );
            if ( $thumb_url ) return $thumb_url;
        }

        // Admin-configured URL is a global fallback (e.g. homepage hero)
        if ( ! empty( $settings['lcp_image_url'] ) ) {
            return $settings['lcp_image_url'];
        }

        return '';
    }

    /**
     * Load the theme's fonts.css asynchronously to prevent render-blocking FOIT.
     *
     * Uses the standard LoadCSS pattern:
     *  - rel="preload" + onload swap triggers non-blocking load
     *  - <noscript> fallback ensures fonts load without JS
     *
     * This achieves the same result as adding font-display:swap to every
     * @font-face rule in fonts.css, without modifying the theme file.
     */
    public static function async_font_stylesheet( string $tag, string $handle, string $href, string $media ): string {
        // Only target the theme's self-hosted font stylesheet
        if ( strpos( $href, '/fonts/fonts.css' ) === false ) {
            return $tag;
        }

        $escaped = esc_url( $href );
        return '<link rel="preload" href="' . $escaped . '" as="style" '
             . 'onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n"
             . '<noscript><link rel="stylesheet" href="' . $escaped . '"></noscript>' . "\n";
    }

    /**
     * Add font-display=swap to Google Fonts URLs to prevent FOIT.
     */
    public static function add_font_display_swap( string $src, string $handle ): string {
        if ( strpos( $src, 'fonts.googleapis.com' ) !== false && strpos( $src, 'display=' ) === false ) {
            $src = add_query_arg( 'display', 'swap', $src );
        }
        return $src;
    }
}
