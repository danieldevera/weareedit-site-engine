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

        // 1b. Safelist of CSS selectors for RUCSS. Unlike (1) which only
        //     covers inline <style>, this covers EXTERNAL stylesheets too —
        //     the theme's autocomplete/search reveal rules live in an
        //     external CSS file that RUCSS strips otherwise.
        add_filter( 'rocket_rucss_safelist',               [ __CLASS__, 'rucss_safelist' ] );

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
            // Header search overlay — class only used AFTER user clicks the
            // magnifying glass icon, which RUCSS can't detect on first scan,
            // so it strips the rule. Without these the search reveal silently
            // breaks (audit 2026-05-27).
            'autocomplete',
            'autocomplete__inputWrapper',
            'autocomplete__inputWrapperEnabled',
            'autocomplete__container',
            'autocomplete__button',
            'autocomplete__input',
            'searchOpen',
            'closeSearch',
            'searchButton',
            'searchButton__inner',
            'headerDesktop__search',
            'headerMobile__search',
            'ais-InstantSearch__root',
            'resultadosPesquisa',
            'keywordSearch',
            'hasFocus',
            'sticky___w5zBW',
            'postTypeSearch',
            // Visible breadcrumbs (v1.5.93) — inline CSS in class-breadcrumbs.php.
            // Without this, RUCSS strips the <style id="edit-breadcrumbs-css">.
            'edit-breadcrumbs',
            // Site-wide swipe-cta animation (locked in v1.5.112). Pillar pages
            // and other non-homepage CTAs need the global swipe rules kept.
            'swipe-cta',
            'swipe-layer',
            'swipe-pink',
            'swipe-teal',
            'swipe-black',
            'swipe-label',
            // Pillar cross-links: pill bar on /formacao/ + "Faz parte de" badge
            // on course singles (v1.5.124, 2026-05-28).
            'edit-pillar-pillbar',
            'edit-pillar-faz-parte',
        ] );
    }

    /**
     * RUCSS safelist — CSS selectors that the Remove-Unused-CSS analyser
     * must KEEP, even if it can't observe them as "used" on first scan.
     * Covers external stylesheets (theme + plugins). The corresponding
     * filter for inline-only is protect_inline_css() above.
     *
     * Reveal-on-click states (search overlay, mobile menu, modals) are
     * the classic RUCSS false-positive surface — RUCSS doesn't see them
     * in the initial DOM, so it strips them. Listed explicitly here.
     */
    public static function rucss_safelist( array $safelist ): array {
        return array_merge( $safelist, [
            // Header search reveal-on-click — confirmed RUCSS stripped this
            // (audit 2026-05-27: click handler bound + class added on click,
            // but no visual reveal because the rule was gone).
            '.autocomplete',
            '.autocomplete__inputWrapper',
            '.autocomplete__inputWrapperEnabled',
            '.autocomplete__container',
            '.autocomplete__button',
            '.autocomplete__input',
            '.autocomplete.compact',
            '.autocomplete.compact.hasFocus',
            '.autocomplete.compact.searchOpen',
            '.searchOpen',
            '.closeSearch',
            '.searchButton',
            '.searchButton__inner',
            '.headerDesktop__search',
            '.headerMobile__search',
            '.headerMobile__search.open',
            '.headerMobile__search.hidden',
            '.ais-InstantSearch__root',
            // Breadcrumbs visible bar — keep external + inline rules
            '.edit-breadcrumbs',
            '.edit-breadcrumbs ol',
            '.edit-breadcrumbs li',
            '.edit-breadcrumbs a',
            '.edit-breadcrumbs [aria-current="page"]',
            '.resultadosPesquisa',
            '.resultadosPesquisa.aberto',
            '.keywordSearch',
            '.hasFocus',
            '.sticky___w5zBW',
            '.postTypeSearch',
            // Defensive — protect our locked hero classes here too in case
            // RUCSS treats injected inline CSS as external in some scenarios.
            '.dgert-hero-pill',
            '.h1-dot',
            '.h1-dot-pink',
            '.h1-dot-teal',
            '.hero-corporate-row',
            '.hero-corporate',
            '.hero-reviews',
            '.g-wordmark',
            '.swipe-cta',
            '.swipe-layer',
            '.swipe-label',
            // Contact Form 7 — form layout, validation states, response box.
            // RUCSS doesn't see CF7's runtime-applied classes (mail-sent-ok,
            // mail-sent-ng, validation-errors) on first crawl and would strip
            // them, leaving error / success messages invisible.
            '.wpcf7',
            '.wpcf7-form',
            '.wpcf7-form-control',
            '.wpcf7-form-control-wrap',
            '.wpcf7-text',
            '.wpcf7-textarea',
            '.wpcf7-email',
            '.wpcf7-checkbox',
            '.wpcf7-radio',
            '.wpcf7-list-item',
            '.wpcf7-list-item-label',
            '.wpcf7-submit',
            '.wpcf7-spinner',
            '.has-spinner',
            '.wpcf7-response-output',
            '.wpcf7-mail-sent-ok',
            '.wpcf7-mail-sent-ng',
            '.wpcf7-validation-errors',
            '.wpcf7-spam-blocked',
            '.wpcf7-not-valid-tip',
            '.wpcf7-not-valid',
            '.wpcf7-acceptance-missing',
            // Contact panel (theme) — the side-filter / overlay container that
            // hosts the Fala connosco form. RUCSS hides reveal-on-click panels.
            '.side-filter-container',
            '.side-filter-container.open',
            '.side-filter',
            '.overlay-side-filter',
            '.filter-content',
            '.scroll-disabled',
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
            // Contact Form 7 — submit handler + validation engine. Without
            // these, "Diferir execução de JavaScript" delays CF7 past the
            // user's submit click → form falls back to native POST → server
            // returns generic mail_sent_ng error. (Incident 2026-05-29.)
            'contact-form-7',
            'wpcf7',
            '/plugins/contact-form-7/',
            'swv',
            // reCAPTCHA — token must generate before submit fires
            'recaptcha',
            'google.com/recaptcha',
            'gstatic.com/recaptcha',
            // Theme's contact panel + form bindings
            '/themes/weareedit/js/',
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
