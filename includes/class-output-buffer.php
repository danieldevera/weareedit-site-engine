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

        // Strip auto-injected <a> tags (InLinks etc.) from the homepage
        // about-section paragraphs. Plain prose only — no third-party
        // tools should be turning brand terms into hyperlinks here.
        add_action( 'wp_footer',         [ __CLASS__, 'strip_about_links' ], 999 );
    }

    /**
     * Strip <a> children from the homepage "about" paragraphs (the Desde
     * 2011 block). InLinks auto-detects keywords like "DGERT", "UX/UI Design",
     * "Data Science" and wraps them in links — Daniel asked them gone for
     * this specific paragraph (2026-05-31).
     *
     * Runs in <footer> + on multiple delayed timers to catch InLinks' lazy
     * injection. Selector targets `section.about .col-sm-6 p`.
     */
    public static function strip_about_links(): void {
        if ( ! is_front_page() ) return;
        ?>
<script id="weareedit-about-unlink">
(function(){
    function unlinkAbout(){
        var paras = document.querySelectorAll('section.about .col-sm-6 p');
        for (var i = 0; i < paras.length; i++){
            var anchors = paras[i].getElementsByTagName('a');
            // Iterate backwards because we mutate the live HTMLCollection.
            for (var j = anchors.length - 1; j >= 0; j--){
                var a = anchors[j];
                var text = document.createTextNode(a.textContent);
                a.parentNode.replaceChild(text, a);
            }
        }
    }
    if (document.readyState !== 'loading') unlinkAbout();
    else document.addEventListener('DOMContentLoaded', unlinkAbout);
    // InLinks (and similar) inject lazily — run again on multiple intervals.
    setTimeout(unlinkAbout, 500);
    setTimeout(unlinkAbout, 1500);
    setTimeout(unlinkAbout, 3000);
    setTimeout(unlinkAbout, 6000);
})();
</script>
        <?php
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
            // Card top-right badge slot (.special-labels-container): show ALL
            // labels (NOVO PROGRAMA, AI UPGRADE, early15, …). The old blanket
            // `display:none` (added 2026-06-12) was wrongly hiding every label —
            // removed 2026-06-16 per Daniel. Hide ONLY the obsolete IEFP "100%
            // Reembolso" badge (image Group-1265-2.png), which is being sunset.
            // :has() hides the whole badge container; the bare-img rule is a
            // fallback for any browser without :has().
            . '.special-labels-container:has(img[src*="Group-1265"]){display:none !important;}'
            . '.special-labels-container img[src*="Group-1265"]{display:none !important;}'
            // Cards now carry MULTIPLE stacked tags (NOVO PROGRAMA + AI UPGRADE +
            // "early15" = the Setembro Early-Bird promo code). The theme styles a
            // SINGLE badge, so the 2nd/3rd ones clipped to empty bordered boxes
            // (label not showing). Force the slot to a clean vertical stack with
            // all labels visible + uppercase (so "early15" renders as "EARLY15").
            . '.special-labels-container{display:flex !important;flex-direction:column !important;gap:6px !important;align-items:flex-end !important;height:auto !important;max-height:none !important;overflow:visible !important;}'
            . '.special-labels-container .course-promo-code{display:inline-flex !important;align-items:center !important;width:auto !important;height:auto !important;max-width:none !important;color:#0a0a0a !important;font-weight:700 !important;font-size:11px !important;line-height:1.15 !important;letter-spacing:.03em !important;text-transform:uppercase !important;white-space:nowrap !important;overflow:visible !important;visibility:visible !important;}'
            // Grid cards (.course) have a tight corner — cap at the top 2 tags
            // (3rd+ hidden after the priority reorder, so early15 always wins).
            // The product-page HERO badge is a lone container NOT inside .course,
            // so it keeps all 3. Empty badge boxes hidden everywhere.
            . '.course .special-labels-container .course-promo-code:nth-of-type(n+3){display:none !important;}'
            . '.course-promo-code:empty{display:none !important;}'
            // On the single course (product) page the theme also echoes the promo
            // badge slot into the hero/CTA chrome, where it renders dark-on-dark and
            // is redundant — hide it. Keep the badges on the "outras formações"
            // related-courses grid cards (inside .course-box).
            . 'body.single-formacao .special-labels-container{display:none !important;}'
            . 'body.single-formacao .course-box .special-labels-container{display:flex !important;}'
            // Course "Estrutura da Formação" / programa section: every module block
            // carries `wow animate__fadeInUp`, and WOW.js hides `.wow` elements
            // (visibility:hidden) until scroll-revealed. On long course pages the
            // lower modules often never trigger, so the program renders CUT OFF —
            // only the first module shows, the rest stay invisible. Force the whole
            // program section visible on all viewports (same safety-net the homepage
            // hero already uses). Animation still plays via animate__fadeInUp where
            // WOW fires; this just guarantees the content always lands visible.
            . 'body.single-formacao .programa .wow,body.single-formacao .programa .wow.animate__fadeInUp,body.single-formacao .programa-curso .wow,body.single-formacao .programa2 .wow{visibility:visible !important;opacity:1 !important;transform:none !important;animation:none !important;animation-name:none !important;}'
            // Single blog post hero (.about-image): a wide ~2.23:1 editorial banner
            // rendered as a data-bg cover div. The theme gives the div a large
            // (near-square) height, so background-size:cover crops the wide banner
            // into a tall sliver. Pin the div to the banner's own aspect ratio so
            // the whole image shows as a clean letterbox, capped to the content width.
            . 'body.single-blog .about-image{max-width:1000px !important;height:auto !important;aspect-ratio:2549/1144 !important;background-size:cover !important;background-position:center !important;margin-left:auto !important;margin-right:auto !important;}'
            // Blog post hero H1 — dual colour (accent after the colon). Brand pink.
            . 'body.single-blog .hero h1 .eblog-h1-accent{color:#f92869 !important;}'
            // Blog body copy — soften from near-black to a lighter editorial grey.
            . 'body.single-blog .text-block-container,body.single-blog .text-block-container p,body.single-blog .text-block-container li{color:#aaaaaa !important;}'
            // Course "Ferramentas" tool-stack icons (Claude Code, ChatGPT, etc.)
            // ship as square PNGs with no size attrs and no CSS bounds, so they
            // render at intrinsic resolution and stretch the .adaptImage card.
            // Anchor each img inside its aspect-ratio box at centred, contained.
            . '.row.ferramentas .logos-grid .adaptImage{position:relative;}'
            . '.row.ferramentas .logos-grid .adaptImage__inner > a{position:absolute !important;inset:0 !important;}'
            . '.row.ferramentas .logos-grid .img-icon{display:flex;align-items:center;justify-content:center;width:100%;height:100%;padding:18px;box-sizing:border-box;}'
            . '.row.ferramentas .logos-grid .img-icon img{max-width:100% !important;max-height:100% !important;width:auto !important;height:auto !important;object-fit:contain !important;}'
            // Homepage hero — restore Figma proportions:
            //  - H1 white, bold, much larger than the theme's default 50px
            //  - "WE ARE EDIT" sub-heading hidden (Figma doesn't include it)
            . 'body.page-template-page-home .hero h1{color:#fff !important;font-size:clamp(64px,9.5vw,152px) !important;font-weight:500 !important;letter-spacing:-0.025em !important;line-height:1.02 !important;text-align:left !important;}'
            // Brand-accented dots in the H1: pink dot for "Future Proof.",
            // teal dot for "Transformation." (Bootcamp + Workshop class colours).
            . 'body.page-template-page-home .hero h1 .h1-dot-pink{color:#f92869 !important;}'
            . 'body.page-template-page-home .hero h1 .h1-dot-teal{color:#60c5b3 !important;}'
            // Homepage "Formação" cards carousel — align to the LEFT EDGE of
            // the viewport (instead of inheriting bootstrap container padding).
            // The cards still scroll horizontally, just start flush left.
            . 'body.page-template-page-home .courses-boxes-home .courses-container{padding-left:0 !important;margin-left:0 !important;}'
            . 'body.page-template-page-home .courses-boxes-home .courses-container .row{margin-left:0 !important;margin-right:0 !important;}'
            . 'body.page-template-page-home .courses-boxes-home .courses-container .col-md-12{padding-left:0 !important;padding-right:0 !important;}'
            . 'body.page-template-page-home .courses-boxes-home .swiper-boxes{padding-left:0 !important;margin-left:0 !important;}'
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
            // SITE-WIDE swipe-cta (locked in 2026-05-28 v1.5.112). Same
            // 3-layer pink → teal → black animation as the homepage CTA,
            // but available on ANY button across the site (pillar pages,
            // future course CTAs, etc.). HP rules above are more specific
            // (body prefix + .btn-yellow) so they continue to win there.
            // ────────────────────────────────────────────────────────────
            . '.swipe-cta{position:relative;overflow:hidden;z-index:0;isolation:isolate;cursor:pointer;border:0;font-family:inherit;}'
            . '.swipe-cta .swipe-layer{position:absolute;inset:-2px;pointer-events:none;will-change:transform;}'
            . '.swipe-cta .swipe-pink{background:#f92869;transform:translateX(-105%);transition:transform 0.18s cubic-bezier(0.33,1,0.68,1);z-index:1;}'
            . '.swipe-cta .swipe-teal{background:#60c5b3;transform:translateX(105%);transition:transform 0.22s cubic-bezier(0.65,0,0.35,1) 0.18s;z-index:2;}'
            . '.swipe-cta .swipe-black{background:#000;transform:translateX(-105%);transition:transform 0.25s cubic-bezier(0.33,1,0.68,1) 0.40s;z-index:3;}'
            . '.swipe-cta .swipe-label{position:relative;z-index:4;transition:color 0.18s ease 0.50s;display:inline-flex;align-items:center;}'
            . '.swipe-cta:hover .swipe-pink,.swipe-cta:hover .swipe-teal,.swipe-cta:hover .swipe-black{transform:translateX(0);}'
            . '.swipe-cta:hover .swipe-label{color:#ffdd06;}'
            // ────────────────────────────────────────────────────────────
            // CF7 response output — make errors visible and RED on the
            // Fala connosco popup + newsletter form. Default CF7 styling
            // is borderless plain text on a dark bg, which makes the
            // failure message read as a grey hint instead of an error.
            // Brand pink (#f92869) reads as urgent against the dark panel;
            // success state uses brand teal. Targets all CF7 forms site-wide.
            // ────────────────────────────────────────────────────────────
            . '.wpcf7-response-output{display:block;margin:18px 0 0 !important;padding:12px 16px !important;border:1px solid !important;border-radius:6px !important;font-size:14px !important;font-weight:600 !important;line-height:1.5 !important;}'
            . '.wpcf7-response-output:empty{display:none !important;}'
            . '.wpcf7 form.invalid .wpcf7-response-output,'
            . '.wpcf7 form.unaccepted .wpcf7-response-output,'
            . '.wpcf7 form.payment-required .wpcf7-response-output,'
            . '.wpcf7 form.failed .wpcf7-response-output,'
            . '.wpcf7 form.aborted .wpcf7-response-output,'
            . '.wpcf7 form.spam .wpcf7-response-output{color:#f92869 !important;border-color:rgba(249,40,105,.5) !important;background:rgba(249,40,105,.08) !important;}'
            . '.wpcf7 form.sent .wpcf7-response-output{color:#60c5b3 !important;border-color:rgba(96,197,179,.5) !important;background:rgba(96,197,179,.08) !important;}'
            . '.wpcf7-not-valid-tip{color:#f92869 !important;font-size:12px !important;font-weight:600 !important;margin-top:4px !important;display:block;}'
            . '.wpcf7-form-control.wpcf7-not-valid{border-color:#f92869 !important;}'
            // ────────────────────────────────────────────────────────────
            // Newsletter form panel — labels had drifted to centered + the
            // .wpcf7-list-item-label spans were rendering in brand yellow
            // (invisible on the white panel bg). Force left alignment site-
            // wide on the side-filter forms (Fala connosco, Newsletter, In
            // Company, Pedir Info — theme selectors group them) and pin the
            // checkbox/radio item labels back to black for legibility.
            // ────────────────────────────────────────────────────────────
            . '#newsletterForm .filter-content,#newsletterForm .filter-options,#newsletterForm .wpcf7,#newsletterForm .wpcf7-form,#newsletterForm .wpcf7-form p,#newsletterForm .wpcf7-form label,#newsletterForm .interesses,#newsletterForm h3{text-align:left !important;}'
            . '#newsletterForm .wpcf7-list-item-label,#falaConnosco .wpcf7-list-item-label,#inCompanyForm .wpcf7-list-item-label,#pedirInfo .wpcf7-list-item-label{color:#000 !important;font-weight:500 !important;}'
            . '#newsletterForm .wpcf7-list-item,#falaConnosco .wpcf7-list-item,#inCompanyForm .wpcf7-list-item,#pedirInfo .wpcf7-list-item{text-align:left !important;}'
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
            // Logos — 3-column grid on mobile so columns ALIGN across rows.
            // Flex-wrap with justify-content:center was centering each row
            // independently, so logos of different widths never lined up.
            // Grid + justify-items:center gives a proper logo-wall rhythm.
            . 'body.page-template-page-home .logos-flex-container{display:grid !important;grid-template-columns:repeat(3,1fr) !important;gap:24px 12px !important;padding:0 16px !important;max-width:100% !important;width:100% !important;margin:0 auto !important;list-style:none !important;box-sizing:border-box !important;align-items:center !important;justify-items:center !important;}'
            . 'body.page-template-page-home .logos-flex-item{margin:0 !important;padding:0 !important;list-style:none !important;display:flex !important;align-items:center !important;justify-content:center !important;}'
            // Fixed height so every logo lands at the SAME visual height
            // regardless of intrinsic SVG ratio. Bumped 32→40 so logos read
            // better on phone-sized viewports.
            . 'body.page-template-page-home .logos-flex-item img{height:56px !important;width:auto !important;max-width:100% !important;display:block !important;object-fit:contain !important;}'
            // Hero padding — tighter top/bottom on mobile + side padding override
            . 'body.page-template-page-home .hero{padding:80px 20px 48px !important;}'
            . 'body.page-template-page-home .hero .container{padding-left:0 !important;padding-right:0 !important;max-width:100% !important;}'
            . '}'
            . "</style>\n"
            // Bridge: pillar CTAs are <button data-contact="true"> elements.
            // The theme opens the Fale connosco panel via $('#falaConnosco').addClass('open')
            // + html.scroll-disabled. Replicate that DOM op directly so we
            // don't depend on hash-change listeners or jQuery binding order.
            //
            // Close handler — the theme's close button removes 'open' from the
            // panel but doesn't always remove 'scroll-disabled' from <html>,
            // leaving the page un-scrollable. Pair the open with our own close
            // listener on .close and .overlay-side-filter inside #falaConnosco.
            . "<script>document.addEventListener('DOMContentLoaded',function(){"
            . "var panel=document.getElementById('falaConnosco');"
            . "function closeContact(){if(panel){panel.classList.remove('open');}document.documentElement.classList.remove('scroll-disabled');}"
            . "document.querySelectorAll('[data-contact=\"true\"]:not(a)').forEach(function(b){"
            . "b.addEventListener('click',function(e){"
            . "e.preventDefault();"
            . "if(panel){panel.classList.add('open');document.documentElement.classList.add('scroll-disabled');}"
            . "});"
            . "});"
            . "if(panel){"
            . "panel.querySelectorAll('.close,.overlay-side-filter').forEach(function(c){c.addEventListener('click',closeContact);});"
            . "document.addEventListener('keydown',function(e){if(e.key==='Escape'&&panel.classList.contains('open'))closeContact();});"
            . "}"
            . "});</script>\n";
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

        // Extension point — other classes (e.g. EDIT_Breadcrumbs) hook here to
        // inject content after the core process() pass.
        $html = apply_filters( 'weareedit_site_engine_output_buffer', $html );

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
        if ( class_exists( 'EDIT_Criticas_Page' ) && is_page( EDIT_Criticas_Page::SLUG ) ) {
            $html = self::inject_criticas_content( $html );
        }

        // /marketing-digital/ pillar — same theme-template issue as criticas.
        if ( class_exists( 'EDIT_Marketing_Digital_Page' ) && is_page( EDIT_Marketing_Digital_Page::SLUG ) ) {
            $html = self::inject_pillar_content( $html, EDIT_Marketing_Digital_Page::class );
        }

        // /data-science/ pillar — same pattern.
        if ( class_exists( 'EDIT_Data_Science_Page' ) && is_page( EDIT_Data_Science_Page::SLUG ) ) {
            $html = self::inject_pillar_content( $html, EDIT_Data_Science_Page::class );
        }

        // /ux-ui-design/ pillar — same pattern.
        if ( class_exists( 'EDIT_UX_UI_Design_Page' ) && is_page( EDIT_UX_UI_Design_Page::SLUG ) ) {
            $html = self::inject_pillar_content( $html, EDIT_UX_UI_Design_Page::class );
        }

        // /curso-inteligencia-artificial/ pillar — same pattern.
        if ( class_exists( 'EDIT_Inteligencia_Artificial_Page' ) && is_page( EDIT_Inteligencia_Artificial_Page::SLUG ) ) {
            $html = self::inject_pillar_content( $html, EDIT_Inteligencia_Artificial_Page::class );
        }

        // /curso-programacao/ pillar — same pattern.
        if ( class_exists( 'EDIT_Programacao_Page' ) && is_page( EDIT_Programacao_Page::SLUG ) ) {
            $html = self::inject_pillar_content( $html, EDIT_Programacao_Page::class );
        }

        // /formacao-corporativa/ B2B redesign — same template-empty workaround.
        if ( class_exists( 'EDIT_Formacao_Corporativa_Page' ) && is_page( EDIT_Formacao_Corporativa_Page::SLUG ) ) {
            $html = self::inject_pillar_content( $html, EDIT_Formacao_Corporativa_Page::class );
        }

        echo $html;
    }

    /**
     * Shared injector for pillar pages — both /marketing-digital/ and
     * /data-science/ use the same theme-template workaround: strip the wrong
     * entry-header H1, then fill the empty entry-content with the shortcode.
     */
    private static function inject_pillar_content( string $html, string $class ): string {
        if ( strpos( $html, 'class="md-pillar"' ) !== false ) return $html;

        $content = $class::render_shortcode();
        if ( ! $content ) return $html;

        $stripped = preg_replace(
            '#<header class="entry-header">\s*<h1[^>]*>[^<]+</h1>\s*</header><!-- \.entry-header -->#s',
            '',
            $html,
            1
        );
        if ( $stripped !== null ) $html = $stripped;

        return preg_replace(
            '#(<div class="entry-content">)\s*(</div><!-- \.entry-content -->)#',
            '$1' . $content . '$2',
            $html,
            1
        ) ?? $html;
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

        // Homepage: hero now carries the reviews score (v1.5.59+, locked
        // v1.5.65). This secondary badge below the stats row is redundant
        // and was removed 2026-05-27. The Course-page injection below
        // stays — those pages don't have the hero reviews block.
        if ( is_singular( 'formacao' ) ) {
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
        // The reviews page is auto-created by EDIT_Criticas_Page on activation.
        $slug   = class_exists( 'EDIT_Criticas_Page' ) ? EDIT_Criticas_Page::SLUG : 'avaliacoes-google';
        $url    = home_url( '/' . $slug . '/' );

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

        // 1ab. Blog post hero H1 — dual colour. Accent the part after the first
        // ": " (e.g. "Do prompt ao produto: <accent>o design já não termina…</accent>").
        // Scoped to single blog posts; no-op if the title has no colon.
        if ( is_singular( 'blog' ) ) {
            $html = preg_replace_callback(
                '#(<h1>)([^<:]+):\s*(.+?)(</h1>)#u',
                function ( $m ) {
                    return $m[1] . $m[2] . ': <span class="eblog-h1-accent">' . $m[3] . '</span>' . $m[4];
                },
                $html, 1
            );

            // 1ac. Per-article enrichment that the WYSIWYG body editor strips on
            // save (external authority links + the styled "Sobre o autor" bleed
            // block). Injected here so it renders reliably. Scoped to the exact
            // post so the literal string replacements can't collide elsewhere.
            // TODO: generalise into the Blog Uploader / a reusable author field.
            if ( 'do-prompt-ao-produto' === get_post_field( 'post_name', get_queried_object_id() ) ) {

                // RELATIVE plugin path — the data-bg / og:image targets already
                // carry the https://weareedit.io prefix, so an absolute URL here
                // would double the domain (broken URL → black hero). v1.5.590 bug.
                $assets = wp_make_link_relative( WEAREDIT_SITE_ENGINE_URL ) . 'assets/img/';

                // Hero — just place the image. No cover/aspect/inline CSS hacks:
                // replace the theme's background-cover div with a plain <img> at
                // natural aspect, so the full 2000x840 art shows uncropped.
                $html = preg_replace(
                    '#<div\b[^>]*\bclass="about-image\b[^"]*"[^>]*>\s*</div>#',
                    '<img src="https://weareedit.io/wp-content/uploads/2026/06/editorial_3_trimmed-4.png" alt="Do prompt ao produto: o design na era da IA — EDIT. Disruptive Blog" style="display:block;width:100%;max-width:1200px;height:auto;margin:0 auto;">',
                    $html,
                    1
                );
                // Share image — point og:image at the full 1200x630 composition.
                $html = str_replace(
                    '/wp-content/uploads/2026/06/editorial_3_trimmed-768x345.png',
                    $assets . 'editorial-3-og.png',
                    $html
                );

                // External authority links woven onto the named tools/products.
                $auth = array(
                    'lançou os <strong>Agents</strong>'
                        => 'lançou os <strong><a href="https://www.framer.com/" target="_blank" rel="noopener">Agents</a></strong>',
                    'com o Make descreves'
                        => 'com o <a href="https://www.figma.com/make/" target="_blank" rel="noopener">Make</a> descreves',
                    'com o Sites, publicas'
                        => 'com o <a href="https://www.figma.com/sites/" target="_blank" rel="noopener">Sites</a>, publicas',
                    'via MCP (Claude Code, Cursor)'
                        => 'via <a href="https://modelcontextprotocol.io/" target="_blank" rel="noopener">MCP</a> (<a href="https://www.anthropic.com/claude-code" target="_blank" rel="noopener">Claude Code</a>, <a href="https://cursor.com/" target="_blank" rel="noopener">Cursor</a>)',
                    'A <strong>Framer</strong> levou'
                        => 'A <strong><a href="https://www.framer.com/" target="_blank" rel="noopener">Framer</a></strong> levou',
                    'A <strong>Webflow</strong>,'
                        => 'A <strong><a href="https://webflow.com/" target="_blank" rel="noopener">Webflow</a></strong>,',
                    'A Framer 3.0 já'
                        => 'A <a href="https://www.framer.com/" target="_blank" rel="noopener">Framer 3.0</a> já',
                );
                $html = str_replace( array_keys( $auth ), array_values( $auth ), $html );

                // "Sobre o autor" — Daniel (locked dark-bleed pattern). Injected
                // just above the "Partilhar:" share row, at the end of the body.
                $author_bio = '<aside class="edit-author-bio" style="background:transparent;color:#fff;padding:48px 0 56px 0;font-family:\'Helvetica Neue\',Arial,sans-serif;border-top:1px solid rgba(255,255,255,0.15);margin-top:48px;">'
                    . '<div style="display:flex;gap:36px;align-items:flex-start;flex-wrap:wrap;">'
                    . '<img src="https://weareedit.io/wp-content/uploads/2026/06/daniel-devera-avatar.png" alt="Daniel Devera — Founder · EDIT. Disruptive Digital Education" style="width:148px;height:148px;border-radius:50%;object-fit:cover;object-position:center;flex-shrink:0;">'
                    . '<div style="flex:1;min-width:280px;">'
                    . '<p style="margin:0 0 14px 0;font-size:12px;font-weight:700;color:#f92869;letter-spacing:0.22em;text-transform:uppercase;">Sobre o autor</p>'
                    . '<h3 style="margin:0 0 10px 0;font-size:38px;font-weight:800;color:#fff;letter-spacing:-0.02em;line-height:1.05;">Daniel Devera</h3>'
                    . '<p style="margin:0 0 26px 0;font-size:15px;color:rgba(255,255,255,0.65);font-weight:600;">Founder · EDIT. Disruptive Digital Education</p>'
                    . '<p style="margin:0 0 20px 0;font-size:16px;line-height:1.65;color:rgba(255,255,255,0.85);">Fundador da EDIT., onde há mais de uma década desenha programas de educação digital que preparam pessoas para o mercado real. Passou a carreira na intersecção entre design, tecnologia e negócio — e escreve sobre o que aí vem antes de virar consenso.</p>'
                    . '<p style="margin:0 0 32px 0;font-size:16px;line-height:1.65;color:rgba(255,255,255,0.85);">Escreve sobre: IA aplicada, design de produto, no-code e o futuro das profissões digitais.</p>'
                    . '<p style="margin:0;font-size:15px;font-weight:700;color:#fff;white-space:nowrap;"><a href="https://weareedit.io/equipa/daniel-devera/" style="color:#fff;text-decoration:underline;text-decoration-color:#f92869;text-decoration-thickness:2px;text-underline-offset:5px;">Perfil completo</a><span style="color:rgba(255,255,255,0.4);margin:0 16px;">·</span><a href="https://www.linkedin.com/in/danieldevera/" style="color:#fff;text-decoration:underline;text-decoration-color:#f92869;text-decoration-thickness:2px;text-underline-offset:5px;">LinkedIn</a><span style="color:rgba(255,255,255,0.4);margin:0 16px;">·</span><a href="https://www.wikidata.org/wiki/Q139907903" style="color:#fff;text-decoration:underline;text-decoration-color:#f92869;text-decoration-thickness:2px;text-underline-offset:5px;">Wikidata</a></p>'
                    . '</div></div></aside>';

                $html = preg_replace(
                    '#(<p class="partilhar">)#',
                    $author_bio . '$1',
                    $html, 1
                );
            }

            // 1ac-2. Editorial #4 (Afonso Monteiro) — same per-article "Sobre o
            // autor" enrichment. Afonso has NO profile page, so LinkedIn-only.
            // Hero handled by the $blog_hero map below. Substring match so a minor
            // slug variation still triggers.
            if ( false !== strpos( (string) get_post_field( 'post_name', get_queried_object_id() ), 'verdadeira-vitima' ) ) {
                // Inline authority links (external) woven onto named tools/concepts.
                $ed4_links = array(
                    'Smart Bidding'             => '<a href="https://support.google.com/google-ads/answer/7065882" target="_blank" rel="noopener">Smart Bidding</a>',
                    'Performance Max'           => '<a href="https://support.google.com/google-ads/answer/10724817" target="_blank" rel="noopener">Performance Max</a>',
                    'Google Tag Manager (GTM)'  => '<a href="https://tagmanager.google.com/" target="_blank" rel="noopener">Google Tag Manager (GTM)</a>',
                    'GA4 avançado'              => '<a href="https://support.google.com/analytics/answer/10089681" target="_blank" rel="noopener">GA4</a> avançado',
                    'básicas de Google Ads'     => 'básicas de <a href="https://ads.google.com/" target="_blank" rel="noopener">Google Ads</a>',
                );
                $html = str_replace( array_keys( $ed4_links ), array_values( $ed4_links ), $html );
                $author_bio = '<aside class="edit-author-bio" style="background:transparent;color:#fff;padding:8px 0 56px 0;font-family:\'Helvetica Neue\',Arial,sans-serif;margin-top:0;">'
                    . '<div style="display:flex;gap:36px;align-items:flex-start;flex-wrap:wrap;">'
                    . '<span style="flex-shrink:0;display:inline-block;border:2px solid #f92869;border-radius:50%;padding:5px;line-height:0;"><img src="https://weareedit.io/wp-content/uploads/2022/03/Afonso_Monteiro_Vert.png" alt="Afonso Monteiro, Senior Performance Marketing Specialist" style="width:138px;height:138px;border-radius:50%;object-fit:cover;object-position:center top;display:block;"></span>'
                    . '<div style="flex:1;min-width:280px;">'
                    . '<p style="margin:0 0 14px 0;font-size:12px;font-weight:700;color:#f92869;letter-spacing:0.22em;text-transform:uppercase;">Sobre o autor</p>'
                    . '<h3 style="margin:0 0 10px 0;font-size:38px;font-weight:800;color:#fff;letter-spacing:-0.02em;line-height:1.05;">Afonso Monteiro</h3>'
                    . '<p style="margin:0 0 26px 0;font-size:15px;color:rgba(255,255,255,0.65);font-weight:600;">Senior Performance Marketing Specialist na Ageas Group &middot; Tutor de <a href="https://weareedit.io/formacao/curso-marketing-digital-online/?utm_source=blog&amp;utm_medium=article&amp;utm_campaign=editorial_04&amp;utm_content=author_md" style="color:#fff;text-decoration:underline;text-decoration-color:#0090eb;text-decoration-thickness:2px;text-underline-offset:3px;">Marketing Digital</a> na EDIT.</p>'
                    . '<p style="margin:0 0 20px 0;font-size:16px;line-height:1.65;color:rgba(255,255,255,0.85);">Especialista em marketing de performance, com a gest&atilde;o de paid media e tr&aacute;fego pago como terreno de todos os dias. Na EDIT. &eacute; tutor de Marketing Digital, onde ensina o que aplica: estrat&eacute;gia, dados e criatividade ao servi&ccedil;o de resultados reais.</p>'
                    . '<p style="margin:0 0 32px 0;font-size:16px;line-height:1.65;color:rgba(255,255,255,0.85);">Escreve sobre: marketing de performance, paid media, Google Ads e Meta Ads, e IA aplicada ao marketing.</p>'
                    . '<p style="margin:0;font-size:15px;font-weight:700;color:#fff;"><a href="https://www.linkedin.com/in/afonso-monteiro123" style="color:#fff;text-decoration:underline;text-decoration-color:#f92869;text-decoration-thickness:2px;text-underline-offset:5px;">LinkedIn</a></p>'
                    . '</div></div></aside>';
                $html = preg_replace(
                    '#(<p class="partilhar">)#',
                    $author_bio . '$1',
                    $html, 1
                );
            }

            // 1ad. Blog hero fix — replicate the do-prompt treatment for other
            // articles whose hero still uses the theme's background-cover div
            // (constrained inside the black hero section → black band). Swap it
            // for a plain <img> at natural aspect so the full banner shows
            // uncropped. Per-article image: trimmed plugin asset where the
            // source PNG had a baked-in black band, else the original upload.
            $blog_hero = array(
                'vinte-e-cinco-cabecas-dois-pensamentos-o-paradoxo-da-criatividade-aumentada' => array(
                    'src' => WEAREDIT_SITE_ENGINE_URL . 'assets/img/carla-hero-trimmed.png',
                    'alt' => 'Vinte e cinco cabeças, dois pensamentos: o paradoxo da criatividade aumentada — EDIT. Disruptive Blog',
                ),
                'os-agentes-nao-tiveram-infancia-branding-na-era-da-ia' => array(
                    'src' => 'https://weareedit.io/wp-content/uploads/2026/06/og-image-padded.png',
                    'alt' => 'Os agentes não tiveram infância: branding na era da IA — EDIT. Disruptive Blog',
                ),
            );
            $blog_slug = get_post_field( 'post_name', get_queried_object_id() );
            $h = isset( $blog_hero[ $blog_slug ] ) ? $blog_hero[ $blog_slug ] : null;
            // Editorial #4 (Afonso) — substring match so a slug variant
            // ('...de-performance' vs '...de-performanc') still triggers the plain
            // <img> swap. The "dance" = the .about-image background-cover div is
            // left in place; replacing it with a plain img kills it for good.
            if ( null === $h && false !== strpos( (string) $blog_slug, 'verdadeira-vitima' ) ) {
                $h = array(
                    'src' => WEAREDIT_SITE_ENGINE_URL . 'assets/img/editorial-4-hero.png',
                    'alt' => 'A verdadeira vítima da IA no marketing de performance | EDIT. Disruptive Blog',
                );
            }
            if ( null !== $h ) {
                $html = preg_replace(
                    '#<div\b[^>]*\bclass="about-image\b[^"]*"[^>]*>\s*</div>#',
                    '<img src="' . esc_url( $h['src'] ) . '" alt="' . esc_attr( $h['alt'] ) . '" style="display:block;width:100%;max-width:1200px;height:auto;margin:0 auto;">',
                    $html,
                    1
                );
            }
        }

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
            // Negative lookahead `(?![^>]*data-skip-wrap)` excludes imgs marked
            // with data-skip-wrap="1" — used by the empresas hero pill, which
            // already lives inside a wrapping <a class="dgert-hero-pill">.
            // Without the skip, the auto-wrap nests <a> inside <a>, browsers
            // parse it as siblings, and the pill's CSS selector .dgert-hero-pill
            // img no longer matches → image renders at intrinsic 1024×483.
            $html = preg_replace(
                '#(<img(?![^>]*data-skip-wrap)[^>]*src="[^"]*dgert-entidade-formadora-(?:branco|negro)\.png"[^>]*>)#',
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
                    '<a class="hero-reviews wow animate__fadeInUp" data-wow-duration="1s" href="' . esc_url( home_url( '/' . ( class_exists( 'EDIT_Criticas_Page' ) ? EDIT_Criticas_Page::SLUG : 'avaliacoes-google' ) . '/' ) ) . '" aria-label="Avaliações Google — 4.1 de 5 baseado em 67 reviews"><span class="hr-star">&#x2605;</span><span class="hr-rating">4.1</span><span class="hr-sep">/</span><span class="hr-count">67 reviews no</span><span class="g-wordmark" aria-hidden="true"><span class="g-G">G</span><span class="g-o1">o</span><span class="g-o2">o</span><span class="g-g">g</span><span class="g-l">l</span><span class="g-e">e</span></span></a><div class="hero-corporate-row',
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

        // 8a. /escola-3/ legacy copy correction — EDIT. no longer has
        // Madrid + São Paulo campuses. Replace the dated multi-city
        // phrase with the current Lisboa+Porto reality. Site-wide
        // str_replace in case the same string appears elsewhere.
        $html = str_replace(
            'em Lisboa, Porto, Madrid e São Paulo',
            'em Lisboa e Porto',
            $html
        );

        // 9. Footer "Subscrever Newsletter" — apply the locked swipe-CTA
        // standard (pink→teal→black sweep + yellow text flip on hover) so
        // it matches the homepage "Ver todos os Cursos" button. Theme markup
        // is the bare anchor; we inject the 3 swipe-layer spans + wrap the
        // text in a swipe-label span so the existing site-wide .swipe-cta
        // CSS kicks in. Click behaviour (scroll to #edit-newsletter-strip)
        // is handled by newsletter-signup.js separately.
        $html = str_replace(
            '<a href="#" class="btn btn-yellow">Subscrever Newsletter</a>',
            '<a href="#" class="btn btn-yellow swipe-cta"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Subscrever Newsletter</span></a>',
            $html
        );

        // 10. Course promo video — the shared Vimeo embed (927121971) was
        // removed/made-private on Vimeo, so it rendered "vídeo não disponível"
        // on every course page that uses it (curso-uxui-porto, curso-marketing-
        // digital-online, curso-uxui-online, …). Daniel 2026-06-14: REPLACE (not
        // fix) with the new YouTube video https://youtu.be/Q8qqSbHz8uM. Swaps the
        // URL in BOTH the WP-Rocket data-lazy-src and the <noscript> fallback
        // src; the surrounding iframe attributes are YouTube-compatible. Targets
        // the specific id, so courses with other videos are untouched.
        $html = str_replace(
            'player.vimeo.com/video/927121971',
            'www.youtube.com/embed/Q8qqSbHz8uM',
            $html
        );

        // Card badge priority rule (Daniel 2026-06-16): reorder the stacked
        // tags inside every .special-labels-container to a fixed precedence —
        //   1. early15  (Setembro Early-Bird promo · financial urgency)
        //   2. AI UPGRADE
        //   3. NOVO PROGRAMA
        // Grid cards then cap to the top 2 via CSS (tight corner); the product-
        // page hero badge (the lone non-card container) keeps all 3. The
        // "early15" text is preserved verbatim so the EARLY15 ?campanha= filter
        // still matches, and it now sorts first so the card cap never drops it.
        $html = preg_replace_callback(
            '#<div class="special-labels-container">((?:<div class="course-promo-code"[^>]*>.*?</div>)+)</div>#s',
            static function ( $m ) {
                preg_match_all( '#<div class="course-promo-code"[^>]*>(.*?)</div>#s', $m[1], $items, PREG_SET_ORDER );
                $rank = static function ( $txt ) {
                    $t = strtolower( trim( strip_tags( $txt ) ) );
                    if ( $t === '' )                          return 9; // empty last
                    if ( strpos( $t, 'early15' ) !== false )  return 0;
                    if ( strpos( $t, 'ai upgrade' ) !== false ) return 1;
                    if ( strpos( $t, 'novo programa' ) !== false ) return 2;
                    return 3; // any other label keeps mid-priority
                };
                usort( $items, static function ( $a, $b ) use ( $rank ) {
                    return $rank( $a[1] ) <=> $rank( $b[1] );
                } );
                $inner = '';
                foreach ( $items as $it ) { $inner .= $it[0]; }
                return '<div class="special-labels-container">' . $inner . '</div>';
            },
            $html
        ) ?? $html;

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
