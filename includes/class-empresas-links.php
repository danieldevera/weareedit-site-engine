<?php
/**
 * Empresas internal linking — consolidates the legacy "in-company" B2B surface
 * into the new empresas.weareedit.io page, site-wide, via the output buffer.
 *
 * Three injections (all gated to empresas being LIVE):
 *   1. SITE-WIDE SWAP — repoint every in-company URL to empresas + relabel the
 *      visible "In-Company" text to "Empresas". Covers the header nav item, the
 *      homepage promo CTA, and anywhere else the old term/links appear.
 *   2. FOOTER LINK — add "Para Empresas" to the footer "Escola" column (sitewide
 *      internal link = strongest crawl/discovery signal for the new page).
 *   3. PILLAR CTA — a contextual B2B band on the 5 pillar pages.
 *
 * Decided 2026-06-14 with GA4 data in hand: /formacao-in-company/ drew ~110
 * sessions/mo but ~0 conversions over 6 months, so consolidating its traffic
 * into the conversion-built empresas page is a net win. The 301s live in
 * EDIT_Empresas_Page::LEGACY_PATHS; this class handles the link graph.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Empresas_Links {

    const EMPRESAS_URL = 'https://empresas.weareedit.io/';

    /** Pillar slugs that get the contextual B2B CTA. */
    const PILLAR_SLUGS = [
        'marketing-digital', 'data-science', 'curso-uxui-design',
        'curso-inteligencia-artificial', 'curso-programacao',
    ];

    public static function init(): void {
        add_filter( 'weareedit_site_engine_output_buffer', [ __CLASS__, 'inject' ], 12 );
        add_action( 'wp_head', [ __CLASS__, 'emit_styles' ], 7 );
        add_action( 'wp_head', [ __CLASS__, 'emit_homepage_styles' ], 8 );
        // Site-wide (incl. the empresas subdomain) — runs regardless of the
        // empresas-live gate, so it always tidies the address bar.
        add_action( 'wp_footer', [ __CLASS__, 'emit_url_cleanup' ], 99 );
    }

    /**
     * Strip GA4 cross-domain linker params (_gl, _ga, _ga_*, _gcl_au, …) from
     * the address bar AFTER the page loads. GA reads the linker on init (head),
     * so by footer time the session is already stitched — we only clean the
     * visible URL. Zero tracking impact; purely cosmetic. Real attribution
     * params (gclid/fbclid/utm_*) are deliberately left untouched.
     */
    public static function emit_url_cleanup(): void {
        if ( is_admin() || is_feed() ) return;
        ?>
<script id="ee-url-cleanup">
(function(){
  try{
    if(!window.history||!history.replaceState||!window.URL)return;
    var u=new URL(window.location.href), p=u.searchParams, changed=false;
    var kill=['_gl','_ga','_gcl_au','_gac','_gcl_aw','_gcl_dc'];
    Array.from(p.keys()).forEach(function(k){
      if(kill.indexOf(k)>-1||k.indexOf('_ga_')===0){p.delete(k);changed=true;}
    });
    if(changed){
      var qs=p.toString();
      history.replaceState(null,'',u.pathname+(qs?'?'+qs:'')+u.hash);
    }
  }catch(e){}
})();
</script>
        <?php
    }

    /** Only act once empresas is publicly live, and never on the subdomain itself. */
    private static function is_active(): bool {
        if ( is_admin() || is_feed() ) return false;
        if ( ! class_exists( 'EDIT_Empresas_Page' ) ) return false;
        if ( EDIT_Empresas_Page::effective_status() !== 'live' ) return false;
        // Don't rewrite the empresas subdomain's own output (would self-link).
        $host = strtolower( preg_replace( '/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '' ) );
        $host = preg_replace( '/^www\./', '', $host );
        if ( $host === 'empresas.weareedit.io' ) return false;
        return true;
    }

    public static function inject( string $html ): string {
        if ( ! self::is_active() ) return $html;

        $html = self::swap_in_company( $html );
        $html = self::inject_footer_link( $html );
        if ( is_front_page() ) {
            $html = self::inject_homepage_empresas( $html );
        }
        if ( self::is_pillar_page() ) {
            $html = self::inject_pillar_cta( $html );
        }
        return $html;
    }

    /**
     * Site-wide in-company → empresas swap. URLs first (the slug contains the
     * lowercase term), then the capitalised visible labels — so the URL slugs
     * are never corrupted and lowercase body prose is left intact.
     */
    private static function swap_in_company( string $html ): string {
        $url_map = [
            'https://weareedit.io/in-company/'            => self::EMPRESAS_URL,
            'https://weareedit.io/in-company'             => self::EMPRESAS_URL,
            'https://weareedit.io/formacao-in-company/'   => self::EMPRESAS_URL,
            'https://weareedit.io/formacao-in-company'    => self::EMPRESAS_URL,
        ];
        $html = str_replace( array_keys( $url_map ), array_values( $url_map ), $html );

        // Visible labels — capitalised variants only (nav item, homepage promo
        // eyebrow/CTA). Lowercase "in-company" left alone to avoid mangling
        // prose + URL slugs.
        $label_map = [
            'In-Company' => 'Empresas',
            'In-company' => 'Empresas',
            'IN-COMPANY' => 'EMPRESAS',
        ];
        $html = str_replace( array_keys( $label_map ), array_values( $label_map ), $html );

        return $html;
    }

    /** Add "Para Empresas" to the footer "Escola" column (both responsive copies). */
    private static function inject_footer_link( string $html ): string {
        $anchor = '<li><a href="https://weareedit.io/escola-3/recrutamento/">Recruitment Services</a></li>';
        if ( strpos( $html, $anchor ) === false ) return $html;
        $new = $anchor . '<li><a class="ee-footer-empresas" href="' . self::EMPRESAS_URL . '">Para Empresas</a></li>';
        return str_replace( $anchor, $new, $html );
    }

    private static function is_pillar_page(): bool {
        foreach ( self::PILLAR_SLUGS as $slug ) {
            if ( is_page( $slug ) ) return true;
        }
        return false;
    }

    /** Contextual B2B band on pillar pages, injected just before the footer strip. */
    private static function inject_pillar_cta( string $html ): string {
        if ( strpos( $html, 'ee-empresas-band' ) !== false ) return $html;
        $anchor = '<div class="footer-newsletter">';
        if ( strpos( $html, $anchor ) === false ) return $html;

        $band  = '<section class="ee-empresas-band" aria-label="Formação para empresas">';
        $band .= '<div class="ee-empresas-band__inner">';
        $band .= '<p class="ee-empresas-band__eyebrow">Para a sua empresa</p>';
        $band .= '<h2 class="ee-empresas-band__title">Quer formar a sua equipa nesta área?</h2>';
        $band .= '<p class="ee-empresas-band__body">Desenhamos programas à medida para empresas — presenciais em Lisboa e Porto, nas vossas instalações ou remotos. Certificados DGERT e elegíveis para Fundos de Compensação.</p>';
        $band .= '<a class="ee-empresas-band__cta" href="' . self::EMPRESAS_URL . '">Conhecer a EDIT. para Empresas <span aria-hidden="true">→</span></a>';
        $band .= '</div></section>';

        return str_replace( $anchor, $band . $anchor, $html );
    }

    /**
     * Homepage Empresas section (premium redesign, approved 2026-06-14). Hides
     * the theme's `row-in_company` block (via emit_homepage_styles CSS) and
     * injects this section right after the `<!--In Company-->` anchor inside the
     * same container. All classes are `eehe__`-prefixed + scoped under body.home.
     */
    private static function inject_homepage_empresas( string $html ): string {
        $anchor = '<!--In Company-->';
        if ( strpos( $html, $anchor ) === false ) return $html;
        // Idempotency must use a MARKUP-only marker: the eehe CSS (emitted in
        // wp_head before this filter) already contains class names like
        // eehe__grid, so guarding on those would always skip the injection.
        if ( strpos( $html, 'Forme a sua equipa nas competências' ) !== false ) return $html;

        $base    = WEAREDIT_SITE_ENGINE_URL;
        $hero    = esc_url( $base . 'assets/img/empresas-hero.webp' );
        $dgert   = esc_url( $base . 'assets/dgert-entidade-formadora-branco.png' );
        $clients = $base . 'assets/img/clients/';
        $emp     = 'https://empresas.weareedit.io/';

        $logo = function ( $file, $alt ) use ( $clients ) {
            return '<img src="' . esc_url( $clients . $file ) . '" alt="' . esc_attr( $alt ) . '">';
        };

        $section  = '<div class="eehe"><div class="eehe__grid">';
        $section .= '<div class="eehe__media">';
        $section .= '<div class="eehe__stat"><b>+30</b><span class="eehe__stat-txt"><span class="eehe__stat-lab">Empresas</span><span class="eehe__stat-cap">formaram as suas equipas com a EDIT. Empresas</span></span></div>';
        $section .= '<div class="eehe__frame"><img src="' . $hero . '" alt="Formação para empresas — EDIT.">';
        $section .= '<div class="eehe__badges"><span class="eehe__badge"><img class="eehe__dgert" data-no-lazy="1" src="' . $dgert . '" alt="Entidade Formadora Certificada DGERT"><span class="eehe__cert"><b>Certificação nº 18391</b>Formação certificada e elegível para financiamento</span></span></div>';
        $section .= '</div></div>';
        $section .= '<div class="eehe__content">';
        $section .= '<p class="eehe__kicker">EDIT. para Empresas</p>';
        $section .= '<h2 class="eehe__title">Forme a sua equipa nas competências <em>digitais</em> que o futuro exige.</h2>';
        $section .= '<p class="eehe__lede">Programas à medida em IA, Data, UX, Design, Marketing Digital e Programação — desenhados em torno do contexto real do vosso setor.</p>';
        $section .= '<div class="eehe__feats">';
        $section .= '<div class="eehe__feat"><span class="eehe__ic"></span><div><b>Programas à medida</b><span>Construídos para a vossa equipa</span></div></div>';
        $section .= '<div class="eehe__feat"><span class="eehe__ic"></span><div><b>Fundos de Compensação</b><span>Elegível para financiamento</span></div></div>';
        $section .= '<div class="eehe__feat"><span class="eehe__ic"></span><div><b>Lisboa · Porto · Remoto</b><span>Ou nas vossas instalações</span></div></div>';
        $section .= '<div class="eehe__feat"><span class="eehe__ic"></span><div><b>Tutores em activo</b><span>Profissionais do mercado</span></div></div>';
        $section .= '</div>';
        $section .= '<div class="eehe__actions">';
        $section .= '<a class="btn btn-yellow swipe-cta" href="' . esc_url( $emp ) . '"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Conhecer a EDIT. para Empresas →</span></a>';
        $section .= '<a class="eehe__link" href="' . esc_url( $emp . '#contacto' ) . '">Falar com a equipa</a>';
        $section .= '</div></div>';
        // Trust row removed 2026-06-14 at Daniel's request — close grid + eehe.
        $section .= '</div></div>';

        return str_replace( $anchor, $anchor . $section, $html );
    }

    public static function emit_homepage_styles(): void {
        if ( ! self::is_active() || ! is_front_page() ) return;
        ?>
<style id="ee-homepage-empresas-css">
body.home .row-in_company{display:none !important;}
body.home .eehe{position:relative;margin-top:64px;padding:80px 0 96px;border-top:1px solid rgba(255,255,255,.12);}
body.home .eehe__grid{display:grid;grid-template-columns:1.05fr 1fr;gap:72px;align-items:center;}
body.home .eehe__media{position:relative;}
body.home .eehe__frame{position:relative;border-radius:16px;overflow:hidden;aspect-ratio:5/4;box-shadow:0 40px 80px -30px rgba(0,0,0,.8);border:1px solid rgba(255,255,255,.08);}
body.home .eehe__frame img{width:100%;height:100%;object-fit:cover;display:block;}
body.home .eehe__frame::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(7,7,7,0) 45%,rgba(7,7,7,.5));}
body.home .eehe__stat{position:absolute;top:-26px;right:-16px;z-index:3;display:flex;align-items:center;gap:18px;background:rgba(12,12,12,.62);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.16);border-radius:14px;padding:18px 22px;max-width:330px;box-shadow:0 26px 54px -24px rgba(0,0,0,.8);}
body.home .eehe__stat>b{font-size:50px;line-height:.9;letter-spacing:-.03em;font-weight:700;color:#ffdd06;}
body.home .eehe__stat-txt{border-left:1px solid rgba(255,255,255,.18);padding-left:18px;}
body.home .eehe__stat-lab{display:block;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#fff;}
body.home .eehe__stat-cap{display:block;margin-top:5px;font-size:12.5px;line-height:1.4;color:rgba(255,255,255,.62);}
body.home .eehe__badges{position:absolute;left:20px;bottom:20px;z-index:2;}
body.home .eehe__badge{display:inline-flex;align-items:center;gap:13px;background:rgba(12,12,12,.62);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.2);border-radius:12px;padding:11px 16px;color:#fff;}
body.home .eehe__dgert{height:32px !important;max-height:32px !important;width:auto !important;display:block;}
body.home .eehe__cert{font-size:11px;font-weight:600;letter-spacing:.02em;color:rgba(255,255,255,.62);border-left:1px solid rgba(255,255,255,.2);padding-left:13px;line-height:1.35;}
body.home .eehe__cert b{display:block;color:#fff;font-size:12px;}
body.home .eehe__kicker{display:inline-flex;align-items:center;gap:12px;font-size:12px;letter-spacing:.26em;text-transform:uppercase;color:rgba(255,255,255,.6);font-weight:700;margin:0 0 24px;}
body.home .eehe__kicker::before{content:"";width:34px;height:1px;background:#ffdd06;}
body.home .eehe__title{font-size:clamp(32px,3.6vw,50px);line-height:1.05;letter-spacing:-.03em;font-weight:700;color:#fff;margin:0 0 20px;}
body.home .eehe__title em{font-family:Georgia,'Times New Roman',serif;font-style:italic;font-weight:400;color:#ffdd06;letter-spacing:-.01em;}
body.home .eehe__lede{font-size:18px;line-height:1.62;color:rgba(255,255,255,.68);max-width:46ch;margin:0 0 32px;font-weight:300;}
body.home .eehe__feats{display:grid;grid-template-columns:1fr 1fr;gap:0 36px;margin:0 0 36px;border-top:1px solid rgba(255,255,255,.1);}
body.home .eehe__feat{display:flex;gap:12px;padding:16px 0;border-bottom:1px solid rgba(255,255,255,.1);}
body.home .eehe__feat .eehe__ic{flex:none;width:7px;height:7px;border-radius:50%;margin-top:8px;background:#ffdd06;}
body.home .eehe__feat:nth-child(2) .eehe__ic{background:#f92869;}
body.home .eehe__feat:nth-child(3) .eehe__ic{background:#60c5b3;}
body.home .eehe__feat:nth-child(4) .eehe__ic{background:#ec8172;}
body.home .eehe__feat b{display:block;font-size:15px;font-weight:600;color:#fff;margin-bottom:2px;}
body.home .eehe__feat span{font-size:13px;color:rgba(255,255,255,.5);}
body.home .eehe__actions{display:flex;align-items:center;gap:24px;flex-wrap:wrap;}
/* Primary CTA uses the theme-global .btn.btn-yellow.swipe-cta markup so it has
   the EXACT default sweep animation (no custom replica). */
body.home .eehe__link{display:inline-flex;align-items:center;color:#fff;font-size:14.5px;font-weight:600;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.28);padding-bottom:3px;transition:border-color .2s ease;}
body.home .eehe__link:hover{border-color:#ffdd06;}
body.home .eehe__trust{margin-top:44px;padding-top:26px;border-top:1px solid rgba(255,255,255,.1);}
body.home .eehe__trust-row{display:flex;align-items:center;gap:34px;flex-wrap:wrap;}
body.home .eehe__trust-label{font-size:11.5px;letter-spacing:.16em;text-transform:uppercase;color:rgba(255,255,255,.4);}
body.home .eehe__trust-logos{display:flex;align-items:center;gap:38px;flex-wrap:wrap;}
body.home .eehe__trust-logos img{height:24px;width:auto;filter:brightness(0) invert(1);opacity:.48;transition:opacity .2s ease;}
body.home .eehe__trust-logos img:hover{opacity:.85;}
body.home .eehe__trust-stats{margin-top:14px;font-size:12.5px;color:rgba(255,255,255,.46);}
body.home .eehe__trust-stats b{color:rgba(255,255,255,.72);font-weight:600;}
@media (max-width:920px){
  body.home .eehe{padding:40px 0 16px;}
  body.home .eehe__grid{grid-template-columns:1fr;gap:40px;}
  body.home .eehe__media{order:-1;margin-top:14px;}
  body.home .eehe__feats{grid-template-columns:1fr;}
  body.home .eehe__stat{top:-18px;right:8px;left:8px;max-width:none;}
  body.home .eehe__stat>b{font-size:38px;}
}
</style>
        <?php
    }

    public static function emit_styles(): void {
        if ( ! self::is_active() || ! self::is_pillar_page() ) return;
        ?>
<style id="ee-empresas-links-css">
.ee-empresas-band{background:#0a0a0a;border-top:1px solid rgba(255,255,255,.08);padding:72px 24px;}
.ee-empresas-band__inner{max-width:760px;margin:0 auto;text-align:center;}
.ee-empresas-band__eyebrow{font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:#ffdd06;font-weight:700;margin:0 0 14px;}
.ee-empresas-band__title{font-size:clamp(26px,3.4vw,38px);line-height:1.12;letter-spacing:-.02em;color:#fff;font-weight:700;margin:0 0 16px;}
.ee-empresas-band__body{font-size:16px;line-height:1.6;color:rgba(255,255,255,.72);max-width:60ch;margin:0 auto 28px;}
.ee-empresas-band__cta{display:inline-flex;align-items:center;gap:8px;background:#ffdd06;color:#0a0a0a;font-weight:700;font-size:15px;text-decoration:none;padding:14px 28px;border-radius:999px;transition:transform .15s ease,background .15s ease;}
.ee-empresas-band__cta:hover{background:#fff;transform:translateY(-1px);}
</style>
        <?php
    }
}
