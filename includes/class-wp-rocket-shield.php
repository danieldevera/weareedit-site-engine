<?php
/**
 * WP Rocket Shield
 *
 * Defensive filters that protect this plugin's inline CSS and the
 * site's critical third-party scripts (GTM, Cookiebot, Hotjar, jQuery,
 * etc.) from breaking when "Atrasar JavaScript" or "Remover CSS não
 * usado" are enabled in WP Rocket.
 *
 * Audit (2026-05-26) flagged these two toggles as the biggest remaining
 * CWV win on the site (~-1,160 ms render-blocking on mobile). They're
 * safe to flip once these exclusions are in place.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_WP_Rocket_Shield {

    public static function init() {
        if ( ! function_exists( 'rocket_clean_domain' ) ) return; // WP Rocket not active

        // 1. Keep our inline <style> blocks out of the "Remove Unused CSS"
        //    analyser. The injected hero CSS is dynamic + carries hover
        //    states that RUCSS can't observe as "used" on first crawl.
        add_filter( 'rocket_rucss_excluded_inline_css', [ __CLASS__, 'protect_inline_css' ] );

        // 2. Add safe-by-default delay-JS exclusions. Anything matching
        //    one of these patterns is allowed to run before user interaction.
        add_filter( 'rocket_delay_js_exclusions',          [ __CLASS__, 'delay_js_exclusions' ] );

        // 3. Protect any inline JS that contains one of these substrings
        //    from being delayed (consent layer + analytics bootstraps).
        add_filter( 'rocket_excluded_inline_js_content',   [ __CLASS__, 'protect_inline_js' ] );

        // 4. Skip our own plugin assets from JS optimisation altogether.
        add_filter( 'rocket_exclude_js',                   [ __CLASS__, 'exclude_plugin_js' ] );
    }

    /**
     * Substrings — if found inside ANY injected inline <style>, that
     * block is excluded from the "Remove Unused CSS" analyser pass.
     */
    public static function protect_inline_css( array $excluded ): array {
        return array_merge( $excluded, [
            // Hero (homepage) — injected dynamically via inject_global_overrides()
            'dgert-hero-pill',
            'h1-dot-pink',
            'h1-dot-teal',
            'hero-corporate-row',
            'hero-corporate',
            'hero-reviews',
            'g-wordmark',
            'swipe-cta',
            'swipe-layer',
            'swipe-label',
            // DGERT badge sizing (footer + course pages)
            'dgert-cert-link',
            'dgert-entidade-formadora',
            // Google Reviews badge (homepage stats row)
            'edit-google-reviews-badge',
            // Críticas page hero rebuilt in v1.5.66
            'cg-hero__source',
            'cg-hero__quote',
            'cg-hero__reviews',
            'cg-statbar__brand',
        ] );
    }

    /**
     * Regex / substring patterns — matching JS files/URLs that must NOT
     * be delayed by "Atrasar JavaScript". These are the standard safe-
     * list for the third-party stack on weareedit.io.
     */
    public static function delay_js_exclusions( array $exclusions ): array {
        return array_merge( $exclusions, [
            // jQuery (all variants + dependencies) — the theme + many plugins assume jQuery is alive on DOMContentLoaded
            '/jquery-?[0-9.]*(.min|.slim|.slim.min)?.js',
            '/wp-includes/js/jquery',
            '/wp-content/plugins/[^/]+/.*jquery',
            // Cookiebot consent — must fire before any tracker, by law
            'consent.cookiebot',
            'cookiebot',
            'CookieConsent',
            // GDPR plugin (Moove)
            'moove-gdpr',
            // Google Tag Manager + GA + gtag (the consent + analytics bootstrap)
            'google-tag-manager',
            'googletagmanager',
            'gtm-',
            'gtag/js',
            'gtag(',
            'dataLayer',
            // Facebook Pixel
            'fbevents',
            'connect.facebook',
            // LinkedIn Insight tag
            'linkedin.com/insight',
            'snap.licdn.com',
            // Hotjar
            'static.hotjar.com',
            'hotjar-',
            // Microsoft Clarity
            'clarity.ms',
            // InLinks — already deferred at body bottom, but exclude defensively
            'inlinks',
            'jscloud.net',
            // WP Rocket's own preload + critical-path scripts
            '/wp-rocket-assets/',
            // WhatsApp button (theme-injected)
            'whatsapp',
            // Lottie / WOW.js (theme animation lib that drives the hero fadeInUp)
            'wow.min.js',
            'wow.js',
            // PUC (plugin-update-checker) admin polls
            'plugin-update-checker',
        ] );
    }

    /**
     * Substrings — if found inside ANY inline <script>, that script
     * block is allowed to run before user interaction. Covers GTM
     * bootstrap, Cookiebot, dataLayer init, etc.
     */
    public static function protect_inline_js( array $excluded ): array {
        return array_merge( $excluded, [
            'window.dataLayer',
            'gtag(',
            '(window,document,\'script\',\'dataLayer\'',
            'CookieConsent',
            'Cookiebot',
            '_hjSettings',
            'hj=function',
            '_linkedin_partner_id',
            'fbq(',
            'clarity(',
        ] );
    }

    /**
     * Skip our own plugin's JS from any WP Rocket optimisation pass.
     * Currently the plugin doesn't enqueue JS at all (everything is
     * inline CSS + HTML rewrites), but this is the defensive guard
     * for any JS we add later.
     */
    public static function exclude_js( array $excluded ): array {
        return self::exclude_plugin_js( $excluded );
    }

    public static function exclude_plugin_js( array $excluded ): array {
        $excluded[] = '/wp-content/plugins/weareedit-site-engine/assets/(.*).js';
        return $excluded;
    }
}
