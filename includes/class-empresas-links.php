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
        $band .= '<p class="ee-empresas-band__body">Desenhamos programas à medida para empresas — presenciais em Lisboa e Porto, nas vossas instalações ou remotos. Certificados DGERT e elegíveis para Cheque-Formação e SIFIDE.</p>';
        $band .= '<a class="ee-empresas-band__cta" href="' . self::EMPRESAS_URL . '">Conhecer a EDIT. para Empresas <span aria-hidden="true">→</span></a>';
        $band .= '</div></section>';

        return str_replace( $anchor, $band . $anchor, $html );
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
