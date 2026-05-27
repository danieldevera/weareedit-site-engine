<?php
/**
 * Full-Page Output Buffer — Image Fix
 *
 * Intercepts the complete HTML output before it is sent to the browser and:
 *  1. Adds alt text to every <img> missing one (media library → filename fallback)
 *  2. Marks decorative icons/backgrounds with alt="" (correct for accessibility)
 *  3. Adds loading="lazy" to every image except the configured LCP hero image
 *  4. Fixes staging.weareedit.io URLs → weareedit.io on the production page
 *  5. Fixes relative /wp-content/ src paths missing the domain
 *
 * Works on ALL images regardless of whether they come from post content,
 * theme templates, widgets, or plugin output.
 *
 * Uses a static URL→attachment cache to avoid redundant DB queries.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Output_Buffer {

    /** Resolved LCP image URL — excluded from lazy loading. */
    private static string $lcp_image = '';

    /** Cache: URL → attachment ID (0 = not in media library). */
    private static array $id_cache = [];

    /**
     * SVG/image filename patterns that are purely decorative UI elements.
     * These get alt="" (empty, not missing) which tells screen readers to skip them.
     */
    private static array $decorative = [
        'icon-remote-learning',
        'icon-curso',
        'icon-cursos-intensivos',
        'bg-remote',
        'bg-curso',
        'bg-curso-intensivo',
        'lupa.svg',
        'close.svg',
        'fechar.svg',
        'filter.svg',
        'arrow-left.svg',
        'arrow-right.svg',
        'arrow-right-white.svg',
        'whatsapp',           // floating button — has its own alt already
    ];

    public static function init() {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        if ( empty( $settings['fix_output_buffer'] ) ) return;

        self::$lcp_image = rtrim( $settings['lcp_image_url'] ?? '', '/' );

        // Only run on frontend, non-AJAX, non-REST page requests
        add_action( 'template_redirect', [ __CLASS__, 'start_buffer' ], 0 );
        add_action( 'shutdown',          [ __CLASS__, 'end_buffer'   ], 0 );

        // Site-wide CSS overrides for assets the plugin rewrites.
        add_action( 'wp_head',           [ __CLASS__, 'inject_global_overrides' ], 99 );
    }

    /**
     * Inline CSS overrides applied site-wide. Currently:
     * - Footer DGERT badge: new 2025 logo is square (1283x1283) vs old wide
     *   format (800x542). Without a constraint it'd render ~1.6× larger than
     *   the Figma design. Cap at 90px to match the original badge proportions.
     * - Generic safety: any DGERT logo image uses object-fit:contain so
     *   aspect-ratio mismatches don't distort the image inside fixed slots.
     */
    public static function inject_global_overrides(): void {
        // DGERT badge sizing:
        //  - Footer (white variant): max 140px wide — fits the footer logo row
        //  - Course pages (black variant): keep theme-default sizing, just
        //    ensure aspect ratio isn't distorted (max-width:100%, height:auto)
        echo "\n<style>"
            . 'footer img[alt="certificado"],footer img[src*="dgert-entidade-formadora-branco"]{max-width:140px !important;height:auto !important;width:auto !important;}'
            . 'img[src*="dgert-entidade-formadora-negro"]{max-width:100%;height:auto;}'
            . 'a.dgert-cert-link{display:inline-block;line-height:0;text-decoration:none;border:none;transition:opacity 0.15s;}'
            . 'a.dgert-cert-link:hover{opacity:0.78;}'
            // Homepage hero — restore Figma proportions:
            //  - H1 white, bold, much larger than the theme's default 50px
            //  - "WE ARE EDIT" sub-heading hidden (Figma doesn't include it)
            . 'body.page-template-page-home .hero h1{color:#fff !important;font-size:clamp(64px,9.5vw,152px) !important;font-weight:500 !important;letter-spacing:-0.025em !important;line-height:1.02 !important;text-align:left !important;}'
            // Brand-accented dots in the H1: pink dot for "Future Proof.",
            // teal dot for "Transformation." (Bootcamp + Workshop class colours).
            . 'body.page-template-page-home .hero h1 .h1-dot-pink{color:#f92869 !important;}'
            . 'body.page-template-page-home .hero h1 .h1-dot-teal{color:#60c5b3 !important;}'
            // UNIVERSAL hero visibility safety net — applies on desktop AND mobile.
            // WOW.js sets inline `style.visibility="hidden"` on .wow elements; this
            // is supposed to be overridden by !important stylesheet rules per spec,
            // but iOS Safari has been observed honouring the inline style anyway
            // (audit 2026-05-26). Target the elements DIRECTLY (not via .wow class)
            // so the override doesn\'t depend on class matching.
            . 'body.page-template-page-home .hero h1,body.page-template-page-home .hero h2,body.page-template-page-home .hero .dgert-hero-pill,body.page-template-page-home .hero .hero-corporate-row,body.page-template-page-home .hero-reviews{visibility:visible !important;opacity:1 !important;}'
            // DGERT trust pill — small inline badge above the H1. Logo (40px)
            // + text + ↗ external-link glyph. Reuses the white DGERT badge
            // already in the plugin assets. Opacity hover (matches existing
            // .dgert-cert-link rule on the footer badge).
            . 'body.page-template-page-home .dgert-hero-pill{display:inline-flex;align-items:center;gap:12px;text-decoration:none;color:#fff !important;margin:0 0 24px 0;transition:opacity 0.2s ease;align-self:flex-start;}'
            . 'body.page-template-page-home .dgert-hero-pill:hover{opacity:0.78;}'
            . 'body.page-template-page-home .dgert-hero-pill img{height:40px !important;width:auto !important;display:block;border:none;}'
            . 'body.page-template-page-home .dgert-hero-pill-text{font-size:14px !important;font-weight:500 !important;letter-spacing:0.01em;color:#fff !important;}'
            . 'body.page-template-page-home .dgert-hero-pill-arrow{font-size:12px !important;margin-left:4px;opacity:0.6;color:#fff !important;}'
            . 'body.page-template-page-home .hero .sub-heading{display:none !important;}'
            // Widen hero container + force column to full width so the big H1
            // has horizontal room to render without word-wrapping.
            . 'body.page-template-page-home .hero .container{max-width:1600px !important;}'
            . 'body.page-template-page-home .hero [class*="col-"]{text-align:left !important;width:100% !important;max-width:100% !important;flex:0 0 100% !important;margin-left:0 !important;}'
            // Subtitle H2 only ("Escola especializada em Digital...") →
            // left-align, original size, yellow.
            . 'body.page-template-page-home .hero h2,body.page-template-page-home .hero h2 font,body.page-template-page-home .hero h2 *{text-align:left !important;font-size:32px !important;line-height:1.3 !important;color:#ffdd06 !important;margin-left:0 !important;padding-left:0 !important;max-width:none !important;}'
            // "Clientes Corporativos · Formação à Medida" — Option B section
            // divider treatment in iter 3 register: uppercase, tracked, with
            // thin horizontal lines flanking the pill. Acts as a section
            // heading for the corporate-client logos directly below.
            . 'body.page-template-page-home .hero .hero-corporate-row{display:flex;align-items:center;gap:18px;max-width:900px;margin:0 auto;padding:0 8px;}'
            . 'body.page-template-page-home .hero .hero-corporate-row .hc-line{flex:1;height:1px;background:rgba(255,255,255,0.18);}'
            . 'body.page-template-page-home .hero a.hero-corporate{color:#c8c8c8 !important;line-height:1.3 !important;display:inline-flex;flex-wrap:nowrap;align-items:center;gap:0;text-decoration:none !important;transition:opacity 0.2s ease;white-space:nowrap;text-transform:uppercase;letter-spacing:0.18em;}'
            . 'body.page-template-page-home .hero a.hero-corporate:hover{opacity:0.78;}'
            . 'body.page-template-page-home .hero a.hero-corporate .hc-main{font-size:12px !important;font-weight:600 !important;color:#c8c8c8 !important;}'
            . 'body.page-template-page-home .hero a.hero-corporate .hc-sep{font-size:12px !important;font-weight:400 !important;opacity:0.4;margin:0 12px;color:#c8c8c8 !important;}'
            . 'body.page-template-page-home .hero a.hero-corporate .hc-sub{font-size:12px !important;font-weight:600 !important;color:#c8c8c8 !important;}'
            . 'body.page-template-page-home .hero a.hero-corporate .hc-arrow{font-size:11px !important;margin-left:8px;opacity:0.6 !important;color:#c8c8c8 !important;position:relative;top:-1px;letter-spacing:0;}'
            // Reviews score block directly below the CTA. Real numbers anchor
            // social proof close to the conversion point. Star uses brand
            // yellow; rating bold white; count light grey. Linked to
            // /criticas-google/ so a click leads to the full review wall.
            // Left-align the CTA container (theme centers it by default) +
            // the reviews block, to match the approved hero design (iter 3).
            . 'body.page-template-page-home .hero .hero-btn-container{text-align:left !important;display:block !important;}'
            . 'body.page-template-page-home .hero .hero-btn-container .btn-slide{display:inline-block !important;margin:0 !important;}'
            . 'body.page-template-page-home .hero-reviews{display:flex;width:fit-content;margin:14px 0 28px 0 !important;align-items:center;gap:8px;color:#c8c8c8 !important;text-decoration:none !important;font-size:13px;text-underline-offset:5px;text-decoration-thickness:2px;}'
            . 'body.page-template-page-home .hero-reviews:hover,body.page-template-page-home .hero-reviews:hover *{text-decoration:underline !important;text-decoration-color:#fff !important;text-decoration-thickness:2px !important;text-underline-offset:5px !important;}'
            . 'body.page-template-page-home .hero-reviews .hr-star{color:#ffdd06 !important;font-size:16px;position:relative;top:-1px;}'
            . 'body.page-template-page-home .hero-reviews .hr-rating{color:#fff !important;font-weight:700;font-size:14px;}'
            . 'body.page-template-page-home .hero-reviews .hr-sep{opacity:0.4;margin:0 2px;}'
            . 'body.page-template-page-home .hero-reviews .hr-count{color:#c8c8c8 !important;margin-right:4px;}'
            // Google wordmark — multi-colour rendering matching Google's
            // brand palette (Blue/Red/Yellow/Blue/Green/Red across G-o-o-g-l-e).
            // Used as a source attribution inside the reviews score block.
            . 'body.page-template-page-home .hero-reviews .g-wordmark{display:inline-flex;align-items:baseline;gap:0;font-family:"Helvetica Neue",Arial,sans-serif;font-weight:500;font-size:15px;line-height:1;letter-spacing:-0.005em;}'
            . 'body.page-template-page-home .hero-reviews .g-wordmark .g-G{color:#4285F4 !important;}'
            . 'body.page-template-page-home .hero-reviews .g-wordmark .g-o1{color:#EA4335 !important;}'
            . 'body.page-template-page-home .hero-reviews .g-wordmark .g-o2{color:#FBBC04 !important;}'
            . 'body.page-template-page-home .hero-reviews .g-wordmark .g-g{color:#4285F4 !important;}'
            . 'body.page-template-page-home .hero-reviews .g-wordmark .g-l{color:#34A853 !important;}'
            . 'body.page-template-page-home .hero-reviews .g-wordmark .g-e{color:#EA4335 !important;}'
            // Larger corporate-client logos — bumped to 72px so they read at
            // a distance and balance the new section-divider treatment above.
            . 'body.page-template-page-home .logos-flex-container{align-items:center;gap:32px;}'
            . 'body.page-template-page-home .logos-flex-item img{max-height:72px !important;height:auto !important;width:auto !important;max-width:100% !important;object-fit:contain;}'
            // Hero CTA "Ver todos os Cursos" — sequenced 3-layer swipe:
            //   1. pink (#f92869) swipes L→R       [0.00s start, 0.15s travel]
            //   2. teal (#60c5b3) swipes R→L       [0.15s start, 0.15s travel]
            //   3. black (#000)   swipes L→R       [0.30s start, 0.15s travel]
            //   Text colour flips to brand yellow once black has landed.
            // Final state: black bg + yellow text. Click activates over black.
            . 'body.page-template-page-home .btn-yellow.swipe-cta{position:relative;overflow:hidden;z-index:0;isolation:isolate;}'
            . 'body.page-template-page-home .btn-yellow.swipe-cta .swipe-layer{position:absolute;inset:-2px;pointer-events:none;will-change:transform;}'
            // Each layer has its own easing personality (softer curves):
            //  - pink  → ease-out-cubic cubic-bezier(0.33,1,0.68,1): gentle decisive arrival
            //  - teal  → ease-in-out-cubic cubic-bezier(0.65,0,0.35,1): smooth glide
            //  - black → ease-out-cubic, slightly longer: soft settle into place (no overshoot)
            . 'body.page-template-page-home .btn-yellow.swipe-cta .swipe-pink{background:#f92869;transform:translateX(-105%);transition:transform 0.18s cubic-bezier(0.33,1,0.68,1);z-index:1;}'
            . 'body.page-template-page-home .btn-yellow.swipe-cta .swipe-teal{background:#60c5b3;transform:translateX(105%);transition:transform 0.22s cubic-bezier(0.65,0,0.35,1) 0.18s;z-index:2;}'
            . 'body.page-template-page-home .btn-yellow.swipe-cta .swipe-black{background:#000;transform:translateX(-105%);transition:transform 0.25s cubic-bezier(0.33,1,0.68,1) 0.40s;z-index:3;}'
            . 'body.page-template-page-home .btn-yellow.swipe-cta .swipe-label{position:relative;z-index:4;transition:color 0.18s ease 0.50s;}'
            . 'body.page-template-page-home .btn-yellow.swipe-cta:hover .swipe-pink{transform:translateX(0);}'
            . 'body.page-template-page-home .btn-yellow.swipe-cta:hover .swipe-teal{transform:translateX(0);}'
            . 'body.page-template-page-home .btn-yellow.swipe-cta:hover .swipe-black{transform:translateX(0);}'
            . 'body.page-template-page-home .btn-yellow.swipe-cta:hover .swipe-label{color:#ffdd06 !important;}'
            // ────────────────────────────────────────────────────────────
            // MOBILE — fixes for hero on viewports ≤ 768px (audit 2026-05-26).
            // Root cause of "invisible H1/H2/DGERT pill on mobile": the theme
            // applies `visibility:hidden` via WOW.js .wow class and the init
            // sometimes mistimes on mobile (URL bar collapse / short viewport
            // means the elements never trigger). Force visibility back ON for
            // the homepage hero wow elements specifically — animation still
            // plays via animate__fadeInUp, just guaranteed to land visible.
            // ────────────────────────────────────────────────────────────
            . '@media (max-width:768px){'
            // Safety net — force homepage hero .wow elements visible even if WOW.js doesn\'t fire.
            // animate.css's @keyframes fadeInUp starts at opacity:0 + translate3d(0,100%,0).
            // If the animation never plays (mistimed on mobile / URL bar collapse / JS error),
            // elements stay invisible. Force ALL four possible hide-states off.
            . 'body.page-template-page-home .hero .wow,body.page-template-page-home .hero .wow.animate__fadeInUp,body.page-template-page-home .hero h1.wow,body.page-template-page-home .hero h2.wow,body.page-template-page-home .dgert-hero-pill.wow{visibility:visible !important;opacity:1 !important;transform:none !important;animation:none !important;animation-name:none !important;}'
            // H1: clamp scales down so "Transformation." fits in 375px viewport.
            // At 375px, 9vw = 33.75px which is the floor we want to avoid overflow.
            . 'body.page-template-page-home .hero h1{font-size:clamp(32px,9vw,64px) !important;line-height:1.04 !important;letter-spacing:-0.02em !important;margin-bottom:18px !important;word-break:break-word;}'
            // H2: shrink to 18-22px so it fits ~3 lines and doesn\'t crowd the CTA
            . 'body.page-template-page-home .hero h2,body.page-template-page-home .hero h2 font,body.page-template-page-home .hero h2 *{font-size:18px !important;line-height:1.35 !important;margin-bottom:24px !important;}'
            // DGERT pill — smaller logo + tighter gap on mobile
            . 'body.page-template-page-home .dgert-hero-pill{margin-bottom:16px !important;gap:8px !important;}'
            . 'body.page-template-page-home .dgert-hero-pill img{height:28px !important;}'
            . 'body.page-template-page-home .dgert-hero-pill-text{font-size:12px !important;}'
            . 'body.page-template-page-home .dgert-hero-pill-arrow{font-size:10px !important;}'
            // CTA + reviews stay left-aligned (already overridden in v1.5.65) — just smaller padding
            . 'body.page-template-page-home .hero .hero-btn-container .btn-yellow{padding:14px 24px !important;font-size:14px !important;}'
            . 'body.page-template-page-home .hero-reviews{font-size:12px !important;flex-wrap:wrap;margin:12px 0 24px 0 !important;}'
            . 'body.page-template-page-home .hero-reviews .hr-rating{font-size:13px !important;}'
            . 'body.page-template-page-home .hero-reviews .g-wordmark{font-size:13px !important;}'
            // Corporate-clients section divider — let it wrap on mobile. The single-line
            // 'CLIENTES CORPORATIVOS · FORMAÇÃO À MEDIDA ↗' was getting truncated.
            . 'body.page-template-page-home .hero .hero-corporate-row{flex-wrap:wrap;justify-content:center;gap:8px;padding:0 16px;}'
            . 'body.page-template-page-home .hero .hero-corporate-row .hc-line{display:none;}'
            . 'body.page-template-page-home .hero a.hero-corporate{white-space:normal !important;flex-wrap:wrap;justify-content:center;line-height:1.5 !important;}'
            . 'body.page-template-page-home .hero a.hero-corporate .hc-main,body.page-template-page-home .hero a.hero-corporate .hc-sub{font-size:11px !important;letter-spacing:0.12em !important;}'
            . 'body.page-template-page-home .hero a.hero-corporate .hc-sep{margin:0 8px;}'
            // Logos — force-wrap on mobile. Theme's flex may be set in an
            // unreachable stylesheet; explicit display:flex + !important
            // on every property to guarantee wrapping + sizing.
            . 'body.page-template-page-home .logos-flex-container{display:flex !important;flex-wrap:wrap !important;justify-content:center !important;align-items:center !important;gap:16px 20px !important;padding:0 16px !important;max-width:100% !important;width:100% !important;margin:0 auto !important;list-style:none !important;box-sizing:border-box !important;}'
            . 'body.page-template-page-home .logos-flex-item{flex:0 0 auto !important;margin:0 !important;padding:0 !important;list-style:none !important;}'
            . 'body.page-template-page-home .logos-flex-item img{max-height:32px !important;max-width:90px !important;width:auto !important;height:auto !important;display:block !important;}'
            // Hero padding — tighter top/bottom on mobile + side padding override
            . 'body.page-template-page-home .hero{padding:80px 20px 48px !important;}'
            . 'body.page-template-page-home .hero .container{padding-left:0 !important;padding-right:0 !important;max-width:100% !important;}'
            . '}'
            . "</style>\n";
    }

    public static function start_buffer() {
        if ( is_admin() || defined( 'DOING_AJAX' ) || defined( 'REST_REQUEST' ) || is_feed() ) return;
        ob_start();
    }

    public static function end_buffer() {
        if ( ! ob_get_level() ) return;
        $html = ob_get_clean();
        if ( ! $html ) return;

        $html = self::process( $html );

        // Inject TOC on formacao CPT single pages (template doesn't use the_content())
        if ( is_singular( 'formacao' ) ) {
            $html = self::inject_formacao_toc( $html );
        }

        // Visible Google reviews badge — pairs with the AggregateRating schema
        // shipped in class-structured-data.php. Schema rich-results data must
        // be reflected by visible page content (Google policy requirement).
        $html = self::inject_reviews_badge( $html );

        // /criticas-google/ page — theme's default page template renders an
        // empty entry-content div. Fill it with the shortcode-rendered HTML.
        if ( is_page( 'criticas-google' ) && class_exists( 'EDIT_Criticas_Page' ) ) {
            $html = self::inject_criticas_content( $html );
        }

        echo $html;
    }

    private static function inject_criticas_content( string $html ): string {
        if ( strpos( $html, 'class="cg-page"' ) !== false ) return $html; // already filled

        $content = EDIT_Criticas_Page::render_shortcode();
        if ( ! $content ) return $html;

        // Strip the wrong entry-header (theme's broken page-template pulls
        // a different post's H1 — e.g. "Bootcamp" — instead of ours).
        $stripped = preg_replace(
            '#<header class="entry-header">\s*<h1[^>]*>[^<]+</h1>\s*</header><!-- \.entry-header -->#s',
            '',
            $html,
            1
        );
        if ( $stripped !== null ) $html = $stripped;

        // Fill the empty entry-content div with our shortcode HTML.
        return preg_replace(
            '#(<div class="entry-content">)\s*(</div><!-- \.entry-content -->)#',
            '$1' . $content . '$2',
            $html,
            1
        ) ?? $html;
    }

    /**
     * Build and inject the Google reviews badge.
     *
     * Rating numbers MUST match `class-structured-data.php` aggregateRating.
     * Source: Google Business profile aggregates (Lisboa + Porto, weighted).
     * When refreshing the schema numbers, update this method in lockstep.
     */
    public static function inject_reviews_badge( string $html ): string {
        // Idempotency guard — never double-inject if upstream rewrites somehow re-trigger.
        if ( strpos( $html, 'edit-google-reviews-badge' ) !== false ) return $html;

        if ( is_front_page() ) {
            // Inject between the stats row and the in-company section. The
            // row-in_company div sits inside the existing bootstrap container,
            // so the badge inherits that width without an extra wrapper.
            $badge  = self::build_reviews_badge_html( 'home' );
            $anchor = '<div class="row is-flex v-center row-in_company">';
            if ( $badge !== '' && strpos( $html, $anchor ) !== false ) {
                return str_replace( $anchor, $badge . $anchor, $html );
            }
        } elseif ( is_singular( 'formacao' ) ) {
            // Inject between the description block and the "Visão Geral" section.
            // The class suffix varies (bootcamp/curso variants), so match on the
            // stable prefix `<section class="visao-geral` with strpos+substr_replace.
            $badge         = self::build_reviews_badge_html( 'course' );
            $anchor_prefix = '<section class="visao-geral';
            $pos           = strpos( $html, $anchor_prefix );
            if ( $badge !== '' && $pos !== false ) {
                return substr_replace( $html, $badge, $pos, 0 );
            }
        }
        return $html;
    }

    /**
     * @param string $context  'home' | 'course' | 'compact'
     *  - 'home'    → wide, left-aligned, no wrapper (parent container exists)
     *  - 'course'  → wide, centered, with own container wrapper
     *  - 'compact' → narrow centered (legacy default)
     */
    private static function build_reviews_badge_html( string $context = 'compact' ): string {
        $rating = '4.1';
        $count  = 67;
        // /criticas-google/ page is auto-created by EDIT_Criticas_Page on activation.
        $url    = home_url( '/criticas-google/' );

        $a_rating = esc_attr( $rating );
        $a_url    = esc_url( $url );

        // Official Google "G" 4-color mark, inlined to avoid a third-party request.
        // Standard reference use for indicating Google as the data source — does not
        // imply Google endorsement.
        $google_g = '<svg class="edit-google-reviews-badge__logo" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
            . '<path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/>'
            . '<path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/>'
            . '<path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/>'
            . '<path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/>'
            . '</svg>';

        $modifier      = '';
        $wrapper_open  = '';
        $wrapper_close = '';
        if ( $context === 'home' ) {
            $modifier = ' edit-google-reviews-badge--wide edit-google-reviews-badge--left';
        } elseif ( $context === 'course' ) {
            $modifier      = ' edit-google-reviews-badge--wide';
            $wrapper_open  = '<div class="edit-google-reviews-badge-wrap"><div class="container">';
            $wrapper_close = '</div></div>';
        }

        // "Ver críticas" link suppressed on homepage — the criticas page is
        // already linked from the main nav / footer, so the homepage badge
        // becomes a pure social-proof line, not a CTA. Course-page badges
        // keep the link since they sit in a buying context.
        $link_html = ( $context === 'home' )
            ? ''
            : '<a class="edit-google-reviews-badge__link" href="' . $a_url . '">Ver críticas →</a>';

        return <<<HTML
<style>
.edit-google-reviews-badge-wrap{padding:24px 0;background:#fff;}
.edit-google-reviews-badge{background:#f5f5f7;border:1px solid #d2d2d7;border-radius:8px;padding:14px 24px;margin:24px auto;max-width:760px;width:100%;box-sizing:border-box;font-family:inherit;}
.edit-google-reviews-badge--wide{max-width:100%;margin:24px 0;padding:18px 28px;}
.edit-google-reviews-badge--left .edit-google-reviews-badge__inner{justify-content:flex-start;}
.edit-google-reviews-badge__inner{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px 14px;font-size:14px;color:#1d1d1f;line-height:1.5;}
.edit-google-reviews-badge__brand{display:inline-flex;align-items:center;gap:6px;}
.edit-google-reviews-badge__logo{width:18px;height:18px;flex-shrink:0;}
.edit-google-reviews-badge__brand-text{font-weight:600;color:#1d1d1f;font-size:14px;letter-spacing:-0.01em;}
.edit-google-reviews-badge__stars{color:#fbbc05;font-size:18px;letter-spacing:1.5px;line-height:1;}
.edit-google-reviews-badge__rating{font-weight:700;color:#1d1d1f;}
.edit-google-reviews-badge__sep{color:#86868b;}
.edit-google-reviews-badge__count{color:#424245;}
.edit-google-reviews-badge__link{color:#60c5b3;font-weight:600;text-decoration:none;white-space:nowrap;}
.edit-google-reviews-badge__link:hover{text-decoration:underline;}
@media (min-width:720px){.edit-google-reviews-badge--wide .edit-google-reviews-badge__link{margin-left:auto;}.edit-google-reviews-badge--left .edit-google-reviews-badge__link{margin-left:auto;}}
</style>
{$wrapper_open}<div class="edit-google-reviews-badge{$modifier}" role="complementary" aria-label="Avaliação Google">
<div class="edit-google-reviews-badge__inner">
<span class="edit-google-reviews-badge__brand">{$google_g}<span class="edit-google-reviews-badge__brand-text">Google</span></span>
<span class="edit-google-reviews-badge__stars" aria-label="{$a_rating} de 5 estrelas">★★★★<span style="opacity:0.55;">★</span></span>
<strong class="edit-google-reviews-badge__rating">{$rating} / 5</strong>
<span class="edit-google-reviews-badge__sep" aria-hidden="true">•</span>
<span class="edit-google-reviews-badge__count">{$count} avaliações verificadas</span>
{$link_html}
</div>
</div>{$wrapper_close}
HTML;
    }

    // -------------------------------------------------------------------------

    public static function process( string $html ): string {
        // 1. Fix staging server URLs leaking into production HTML
        $html = str_replace(
            'https://staging.weareedit.io/wp-content/',
            'https://weareedit.io/wp-content/',
            $html
        );

        // 1a. Plugin was renamed edit-seo-fix → weareedit-site-engine at
        // v1.5.55 (2026-05-26). Any hardcoded references to the old plugin
        // folder (in theme code, Customizer custom HTML, code-snippet plugins,
        // widget HTML) now point to a 404. Universal rewrite catches all of
        // them — incl. the floating WhatsApp button SVG.
        $html = str_replace(
            '/wp-content/plugins/edit-seo-fix/',
            '/wp-content/plugins/weareedit-site-engine/',
            $html
        );

        // 1aa. Rank Math emits og:type=article for every singular CPT (course
        // pages, /formacao-in-company/, /criticas-google/ etc.). These are
        // NOT articles in the OG sense — they're pages/services/reviews.
        // FB + LinkedIn use FIRST-seen og:type so appending a second tag was
        // a no-op (audit found this 2026-05-26). Replace in-place. Only
        // applied on non-`post` singulars so real blog posts stay og:type=article.
        if ( ! is_singular( 'post' ) ) {
            $html = str_replace(
                '<meta property="og:type" content="article" />',
                '<meta property="og:type" content="website" />',
                $html
            );
        }

        // 1b. DGERT "Entidade Formadora Certificada" badge — site-wide refresh
        // to the new schools' artwork in two color variants:
        //   - white (positive)  → footer (dark background)
        //   - black (negative)  → course pages (light backgrounds)
        $schools_badge_white = WEAREDIT_SITE_ENGINE_URL . 'assets/dgert-entidade-formadora-branco.png';
        $schools_badge_black = WEAREDIT_SITE_ENGINE_URL . 'assets/dgert-entidade-formadora-negro.png';
        $html = str_replace( [
            'https://weareedit.io/wp-content/uploads/2021/09/DGERT_Logo.png',
            '//weareedit.io/wp-content/uploads/2021/09/DGERT_Logo.png',
        ], $schools_badge_white, $html );
        $html = str_replace( [
            'https://weareedit.io/wp-content/uploads/2021/12/certificado-dgert@2x.png',
            '//weareedit.io/wp-content/uploads/2021/12/certificado-dgert@2x.png',
        ], $schools_badge_black, $html );

        // 1d. Wrap the DGERT badges in a link to the DGERT certified-entities
        // public page. This creates a verifiable trust signal:
        //   - SEO: outbound .gov.pt link → authority signal
        //   - GEO: LLMs follow the link to validate the certification claim
        //   - Knowledge Graph: reinforces the hasCredential schema (v1.5.1)
        // Earlier versions linked to certifica.dgert.gov.pt/Entidade/Detalhe/18391 —
        // that URL pattern doesn't exist (DGERT's portal is ASP.NET with
        // pesquisa.aspx-style URLs, no clean GET-by-cert-ID), so we point at
        // the canonical certified-entities page on the main DGERT site.
        // Idempotency guard: check for the anchor opener specifically.
        if ( strpos( $html, '<a class="dgert-cert-link"' ) === false ) {
            $html = preg_replace(
                '#(<img[^>]*src="[^"]*dgert-entidade-formadora-(?:branco|negro)\.png"[^>]*>)#',
                '<a class="dgert-cert-link" href="https://www.dgert.gov.pt/entidades-formadoras-certificadas" target="_blank" rel="noopener noreferrer" aria-label="DGERT — Entidades Formadoras Certificadas" title="DGERT — Entidades Formadoras Certificadas (entidade nº 18391)">$1</a>',
                $html
            ) ?? $html;
        }

        // 1c. Homepage hero H1 — restore original Figma copy.
        // The source markup is intentionally malformed (the <span> opens but
        // never closes before </h1>), so the swap target deliberately omits
        // the closing </span>.
        if ( is_front_page() ) {
            // Brand accent dots: pink (#f92869, Bootcamp colour) after "Future Proof",
            // teal (#60c5b3, Workshop colour) after "Transformation". The wrapping
            // span class names are styled in inject_global_overrides().
            $html = str_replace(
                'Future Proof<br><span style="color:#ffdd06;font-weight:1000;">Education',
                'Future Proof<span class="h1-dot h1-dot-pink">.</span><br>Transformation<span class="h1-dot h1-dot-teal">.</span>',
                $html
            );
            // DGERT trust pill — injected above the H1, clickable, links to the
            // verified DGERT registry. Reuses the existing white DGERT badge
            // already shipped in assets/. Styled in inject_global_overrides()
            // under `.dgert-hero-pill`. The locked-hero H1 markup is unique
            // ("wow animate__fadeInUp " + Future Proof) so the replace is safe.
            $dgert_badge_url = WEAREDIT_SITE_ENGINE_URL . 'assets/dgert-entidade-formadora-branco.png';
            $html = str_replace(
                '<h1 class="wow animate__fadeInUp " data-wow-duration="1s">Future Proof',
                '<a class="dgert-hero-pill wow animate__fadeInUp" data-wow-duration="1s" href="https://www.dgert.gov.pt/entidades-formadoras-certificadas" target="_blank" rel="noopener noreferrer" aria-label="DGERT — Entidade Formadora Certificada"><img src="' . esc_url( $dgert_badge_url ) . '" alt="DGERT" loading="eager"><span class="dgert-hero-pill-text">Entidade Formadora Certificada</span><span class="dgert-hero-pill-arrow" aria-hidden="true">&#x2197;</span></a><h1 class="wow animate__fadeInUp " data-wow-duration="1s">Future Proof',
                $html
            );
            // H2 subtitle: let it wrap naturally into ~2 lines at the
            // container's right edge. Earlier versions forced a <br> after
            // "Data Science," but that produced 3 visual lines at desktop
            // widths once the H2 size dropped to 32px (v1.5.40).
            // Split the corporate-clients tagline into a larger primary
            // line ("Clientes Corporativos") and a smaller sub-line
            // ("Formação à medida para Empresas") — styled in inject_global_overrides().
            $html = str_replace(
                '<p><b>Clientes Corporativos | Formação à medida para Empresas</b></p>',
                '<div class="hero-corporate-row wow animate__fadeInUp" data-wow-duration="1s"><span class="hc-line"></span><a class="hero-corporate" href="' . esc_url( home_url( '/formacao-in-company/' ) ) . '" aria-label="Clientes Corporativos — Formação à medida para Empresas"><span class="hc-main">Clientes Corporativos</span><span class="hc-sep">·</span><span class="hc-sub">Formação à Medida</span><span class="hc-arrow" aria-hidden="true">&#x2197;</span></a><span class="hc-line"></span></div>',
                $html
            );
            // Hero CTA — add `swipe-cta` class to the button and wrap the
            // label in a span, plus inject 3 coloured swipe layers. Hover
            // plays a sequenced pink→teal→black sweep (CSS in
            // inject_global_overrides). No guard needed: the rewritten markup
            // no longer matches the regex (class becomes "btn btn-yellow
            // swipe-cta" and the label is wrapped in a span), so re-runs are
            // naturally idempotent.
            // MUST run BEFORE the reviews-block injection below — that
            // injection's str_replace targets the swipe-label span, which
            // only exists after this rewrite fires.
            $html = preg_replace(
                '#<a([^>]*?)class="btn btn-yellow"([^>]*?)>Ver todos os Cursos</a>#',
                '<a$1class="btn btn-yellow swipe-cta"$2><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Ver todos os Cursos</span></a>',
                $html
            ) ?? $html;
            // Reviews score — must render OUTSIDE the `<div class="btn
            // btn-slide …">` wrapper that contains the CTA, otherwise it
            // visually nests inside the yellow button. Injected as a
            // sibling between the CTA container and the corporate-row.
            // Targets the corporate-row opening (rewritten just above) so
            // the reviews block lands in the correct DOM position.
            // Idempotency guard: skip if already injected.
            if ( strpos( $html, 'class="hero-reviews ' ) === false ) {
                $html = str_replace(
                    '<div class="hero-corporate-row',
                    '<a class="hero-reviews wow animate__fadeInUp" data-wow-duration="1s" href="' . esc_url( home_url( '/criticas-google/' ) ) . '" aria-label="Avaliações Google — 4.1 de 5 baseado em 67 reviews"><span class="hr-star">&#x2605;</span><span class="hr-rating">4.1</span><span class="hr-sep">/</span><span class="hr-count">67 reviews no</span><span class="g-wordmark" aria-hidden="true"><span class="g-G">G</span><span class="g-o1">o</span><span class="g-o2">o</span><span class="g-g">g</span><span class="g-l">l</span><span class="g-e">e</span></span></a><div class="hero-corporate-row',
                    $html
                );
            }
        }

        // 2. Fix relative /wp-content/ src paths missing the domain
        $html = preg_replace(
            '/(<img\b[^>]*\bsrc\s*=\s*["\'])\/wp-content\//i',
            '$1' . home_url( '/wp-content/' ),
            $html
        );

        // 3. Process every <img> tag
        $html = preg_replace_callback(
            '/<img\b[^>]*>/i',
            [ __CLASS__, 'process_img' ],
            $html
        );

        // 4. Add contextual aria-label to repeated "Ver curso" links
        $html = preg_replace_callback(
            '/<a\b([^>]*)>(Ver curso)<\/a>/i',
            [ __CLASS__, 'process_ver_curso_link' ],
            $html
        );

        // 5. Fix Cookiebot injecting <h1> for cookie banner — demote to <h2>
        //    Every page ends up with a second <h1> from CybotCookiebot dialog.
        $html = preg_replace(
            '/<h1(\s+id="CybotCookiebotDialogBodyContentTitle"[^>]*)>([^<]*)<\/h1>/i',
            '<h2$1>$2</h2>',
            $html
        );

        // 6. Ensure lang="pt-PT" on <html> tag (theme doesn't use language_attributes())
        $html = preg_replace(
            '/<html\b([^>]*)>/i',
            '<html lang="pt-PT"$1>',
            $html,
            1
        );
        // If lang already existed in attributes, clean up the duplicate
        $html = preg_replace( '/(<html[^>]*)\blang="[^"]*"([^>]*)\blang="pt-PT"/i', '$1lang="pt-PT"$2', $html );

        // 7. Remove Brazil from areaServed in any JSON-LD schema block (Rank Math output).
        //    Targets both comma-after and comma-before variants to avoid leaving trailing commas.
        $html = preg_replace(
            '/\s*\{\s*"@type"\s*:\s*"Country"\s*,\s*"name"\s*:\s*"Brazil"\s*\}\s*,?/i',
            '',
            $html
        );
        // Clean up any trailing comma before closing bracket left by the removal above
        $html = preg_replace( '/,(\s*\])/', '$1', $html );

        // 8. Insert a word boundary into decorative titles.
        //    "<font>Curso Online</font></br>Course Name" extracts as "OnlineCourse Name"
        //    in Google NLP / InLinks. Inserting a space before the malformed </br>
        //    fixes entity extraction; trailing-space-before-break is visually invisible.
        //    Mirrors the the_title filter in class-title-spacing.php for render paths
        //    (formacao H1, course cards) that bypass the_title().
        $html = str_replace( '</font></br>', '</font> </br>', $html );

        return $html;
    }

    // -------------------------------------------------------------------------

    private static function process_img( array $matches ): string {
        $tag = $matches[0];

        // Extract src
        preg_match( '/\bsrc\s*=\s*["\']([^"\']*)["\']/', $tag, $src_m );
        $src = $src_m[1] ?? '';

        // --- Alt text ---
        if ( ! preg_match( '/\balt\s*=/', $tag ) ) {
            $alt = self::derive_alt( $src );
            // Insert alt right after <img
            $tag = preg_replace( '/(<img\b)/i', '$1 alt="' . esc_attr( $alt ) . '"', $tag );
        }

        // --- loading="lazy" (skip LCP image and images that already have loading) ---
        if ( ! preg_match( '/\bloading\s*=/', $tag ) ) {
            $is_lcp = self::$lcp_image && strpos( $src, self::$lcp_image ) !== false;
            if ( ! $is_lcp ) {
                $tag = preg_replace( '/(<img\b)/i', '$1 loading="lazy"', $tag );
            }
        }

        return $tag;
    }

    // -------------------------------------------------------------------------

    private static function derive_alt( string $src ): string {
        if ( ! $src ) return '';

        $filename = pathinfo( parse_url( $src, PHP_URL_PATH ) ?? $src, PATHINFO_FILENAME );

        // Decorative UI element → empty alt (correct accessibility practice)
        foreach ( self::$decorative as $pattern ) {
            if ( stripos( $src, $pattern ) !== false ) {
                return '';
            }
        }

        // Try WordPress media library (cached to avoid duplicate DB queries)
        if ( strpos( $src, '/wp-content/uploads/' ) !== false ) {
            $attachment_id = self::get_attachment_id( $src );
            if ( $attachment_id ) {
                $alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
                if ( $alt ) return $alt;

                $post = get_post( $attachment_id );
                if ( $post && $post->post_title ) return $post->post_title;
            }
        }

        // Fallback: derive readable text from the filename
        // Strip size suffix (e.g. -500x741), replace separators, title-case
        $clean = preg_replace( '/-\d+x\d+$/', '', $filename );
        $clean = preg_replace( '/[-_]+/', ' ', $clean );
        $clean = preg_replace( '/\s\d+$/', '', trim( $clean ) ); // trailing numbers
        $clean = ucwords( $clean );

        return $clean ?: get_bloginfo( 'name' );
    }

    /**
     * Add aria-label="Ver curso: [Course Name]" to generic "Ver curso" links.
     * Derives the course name from the URL slug so screen readers get context.
     */
    private static function process_ver_curso_link( array $matches ): string {
        $attrs     = $matches[1];
        $link_text = $matches[2];

        // Skip if aria-label already present
        if ( stripos( $attrs, 'aria-label' ) !== false ) {
            return $matches[0];
        }

        // Extract href
        preg_match( '/\bhref\s*=\s*["\']([^"\']*)["\']/', $attrs, $href_m );
        $href = $href_m[1] ?? '';

        // Derive readable course name from the last path segment of the URL
        $path  = trim( parse_url( $href, PHP_URL_PATH ) ?? '', '/' );
        $parts = array_filter( explode( '/', $path ) );
        $slug  = end( $parts );
        $name  = $slug ? ucwords( str_replace( '-', ' ', $slug ) ) : '';

        $aria_label = $name ? 'Ver curso: ' . $name : 'Ver curso';

        return '<a' . $attrs . ' aria-label="' . esc_attr( $aria_label ) . '">' . $link_text . '</a>';
    }

    private static function get_attachment_id( string $url ): int {
        // Strip size suffix before lookup (e.g. image-500x741.jpg → image.jpg)
        $clean = preg_replace( '/-\d+x\d+(\.[a-z]+)$/i', '$1', $url );

        foreach ( [ $url, $clean ] as $candidate ) {
            if ( isset( self::$id_cache[ $candidate ] ) ) {
                return self::$id_cache[ $candidate ];
            }
            $id = attachment_url_to_postid( $candidate );
            self::$id_cache[ $candidate ] = $id;
            if ( $id ) return $id;
        }

        return 0;
    }

    // -------------------------------------------------------------------------

    /**
     * Inject a Table of Contents into formacao CPT pages.
     *
     * The formacao template renders content from ACF fields and never calls
     * the_content(), so TOC plugins can't auto-insert. We:
     *  1. Collect only content H2s (skip .course-title and .titulo-curso — template UI elements)
     *  2. Build a TOC using ez-toc's container structure so Rank Math recognises it
     *  3. Inject it immediately after the </h1> closing tag
     *
     * Injection point rationale: the first H2 in the DOM is inside a related-courses
     * carousel that appears BEFORE the H1 (position ~148k vs H1 at ~322k). Injecting
     * after </h1> places the TOC at the correct position in the main content area.
     */
    private static function inject_formacao_toc( string $html ): string {
        // Collect content H2s — exclude template UI classes (course-title, titulo-curso)
        if ( ! preg_match_all( '/<h2\b([^>]*)>(.*?)<\/h2>/is', $html, $matches, PREG_SET_ORDER ) ) {
            return $html;
        }

        // Find the H1 position — we only want H2s that come AFTER it
        $h1_pos = strpos( $html, '<h1' );
        if ( $h1_pos === false ) return $html;

        // Heading texts to exclude — template section labels that aren't real content sections
        $excluded_texts = [ 'relacionados', 'este website utiliza cookies' ];

        $content_headings = [];
        foreach ( $matches as $m ) {
            $attrs    = $m[1];
            $text     = trim( wp_strip_all_tags( $m[2] ) );
            $full_pos = strpos( $html, $m[0] );

            // Skip template UI headings by class
            if ( preg_match( '/\bclass\s*=\s*["\'][^"\']*\b(course-title|titulo-curso)\b/i', $attrs ) ) {
                continue;
            }

            // Skip Cookiebot dialog heading by id
            if ( preg_match( '/\bid\s*=\s*["\'][^"\']*cookiebot[^"\']*["\']/', $attrs ) ) {
                continue;
            }

            // Skip headings that appear before the H1 (nav/breadcrumb elements)
            if ( $full_pos !== false && $full_pos < $h1_pos ) {
                continue;
            }

            // Skip excluded text labels
            if ( in_array( strtolower( $text ), $excluded_texts, true ) ) {
                continue;
            }

            if ( $text ) {
                $content_headings[] = $m;
            }
        }

        // Need at least 2 real content headings for a meaningful TOC
        if ( count( $content_headings ) < 2 ) {
            return $html;
        }

        $toc_items = '';
        $modified  = $html;

        foreach ( $content_headings as $m ) {
            $full_tag     = $m[0];
            $attrs        = $m[1];
            $inner_html   = $m[2];
            $heading_text = trim( wp_strip_all_tags( $inner_html ) );

            // Reuse existing id, or generate one from the heading text
            if ( preg_match( '/\bid\s*=\s*["\']([^"\']+)["\']/', $attrs, $id_m ) ) {
                $anchor = $id_m[1];
            } else {
                $anchor   = 'toc-' . sanitize_title( $heading_text );
                $new_tag  = '<h2' . $attrs . ' id="' . esc_attr( $anchor ) . '">' . $inner_html . '</h2>';
                $modified = str_replace( $full_tag, $new_tag, $modified );
            }

            $toc_items .= '<li class="ez-toc-page-1 ez-toc-heading-level-2">';
            $toc_items .= '<a class="ez-toc-link ez-toc-heading-2" href="#' . esc_attr( $anchor ) . '">';
            $toc_items .= esc_html( $heading_text );
            $toc_items .= '</a></li>' . "\n";
        }

        if ( ! $toc_items ) return $html;

        // Build TOC container using ez-toc's class/id so Rank Math recognises it
        // Visually hidden but present in HTML for SEO crawlers
        $toc_html  = '<div id="ez-toc-container" class="ez-toc-v2_0_73 counter-hierarchy ez-toc-counter ez-toc-grey ez-toc-container-direction" aria-hidden="true" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;">' . "\n";
        $toc_html .= '<div class="ez-toc-title-container"><p class="ez-toc-title">Índice</p></div>' . "\n";
        $toc_html .= '<nav><ul class="ez-toc-list ez-toc-list-level-1">' . "\n";
        $toc_html .= $toc_items;
        $toc_html .= '</ul></nav></div>' . "\n";

        // Inject immediately after the </h1> closing tag — safe anchor point that
        // exists on every formacao page and comes after the courses carousel in the DOM.
        $modified = preg_replace( '/<\/h1>/i', '</h1>' . $toc_html, $modified, 1 );

        return $modified;
    }
}
