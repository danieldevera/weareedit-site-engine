<?php
/**
 * empresas.weareedit.io — B2B sub-brand surface.
 * ─────────────────────────────────────────────────────────────────────────────
 * Sub-brand replacement for the legacy /formacao-in-company/ page. Targets
 * CEOs, HR Directors, L&D Managers in Portugal. Funnel destination for
 * LinkedIn organic content (EDIT. company page) + LinkedIn Ads campaign.
 *
 * Architecture: subdomain-only. When HTTP_HOST = empresas.weareedit.io we
 * short-circuit WP rendering at `template_redirect` priority 0 and emit our
 * own HTML — completely bypassing the formacao theme. This gives us:
 *   - clean stack (no theme CSS leaking, no header/footer chrome)
 *   - fastest possible TTFB (no template_part overhead)
 *   - independent SEO surface (own H1, own title, own meta)
 *
 * The subdomain points at the same WordPress install via DNS CNAME, so we
 * stay in the existing workflow: edit code → bump version → commit → tag
 * → push → one-click WP update.
 *
 * Content reuse: client logos, áreas, process, value props, FAQ are reused
 * by reference from EDIT_Formacao_Corporativa_Page constants (curated by
 * Daniel 2026-05-29). That class stays at STATUS='draft' as a content
 * holder — we deprecate it once this surface is stable.
 *
 * Status switch:
 *   STATUS = 'live'      — subdomain renders to the public
 *   STATUS = 'preview'   — renders only for logged-in admins (others get 404)
 *   STATUS = 'off'       — class is inert (subdomain returns the default WP page)
 *
 * Sprint plan:
 *   Sprint 1 (tonight): plumbing — subdomain detect + hero + logo wall +
 *                       stats + value props + footer
 *   Sprint 2 (Sat):     áreas cards + process + financing deep-dive + FAQ
 *   Sprint 3 (Sat):     lead form wired to Brevo Empresas Inbound pipeline
 *   Sprint 4 (Sat/Sun): 301 redirects from /formacao-corporativa/,
 *                       /formacao-in-company/, /formacao-digital-para-empresas/
 *   Sprint 5 (Sun):     GA4 + GTM tracking, soft launch
 *
 * @since 1.5.288
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Empresas_Page {

    /**
     * Master visibility switch.
     *
     *   'live'    — public renders the sub-brand page
     *   'preview' — only logged-in admins see it (404 for everyone else)
     *   'off'     — class is fully inert
     *
     * Sprint 1 ships as 'preview' so Daniel + team can review at the
     * subdomain without exposing an in-progress page to crawlers.
     * Flip to 'live' for soft launch.
     */
    const STATUS = 'preview';

    /**
     * Exact match: HTTP_HOST must equal this string. The www.empresas.*
     * variant is normalised to non-www by the WP_HOME canonical redirect.
     */
    const SUBDOMAIN = 'empresas.weareedit.io';

    /**
     * Legacy URLs to 301-redirect into the new home. Gated by
     * `redirects_active()` so they stay dormant until STATUS = 'live'.
     */
    const LEGACY_PATHS = [
        '/formacao-corporativa/',
        '/formacao-corporativa',
        '/formacao-in-company/',
        '/formacao-in-company',
        '/formacao-digital-para-empresas/',
        '/formacao-digital-para-empresas',
    ];

    public static function init(): void {
        if ( self::STATUS === 'off' ) return;

        // Subdomain renderer — fires only when the request host matches.
        if ( self::is_subdomain_request() ) {
            // Strip Rank Math + theme noise from <head>. We control everything.
            add_action( 'template_redirect', [ __CLASS__, 'render_page' ], 0 );
        }

        // 301 redirects from legacy URLs to the new home — only active
        // when STATUS = 'live' (otherwise legacy URLs keep working as-is).
        if ( self::redirects_active() ) {
            add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect_legacy' ], 1 );
        }
    }

    private static function is_subdomain_request(): bool {
        $host = strtolower( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
        // Normalise: strip port + leading www.
        $host = preg_replace( '/:\d+$/', '', $host );
        $host = preg_replace( '/^www\./', '', $host );
        return $host === self::SUBDOMAIN;
    }

    private static function redirects_active(): bool {
        return self::STATUS === 'live';
    }

    /**
     * Final 301 handler — bounces /formacao-corporativa/, /formacao-in-company/,
     * /formacao-digital-para-empresas/ to https://empresas.weareedit.io/.
     */
    public static function maybe_redirect_legacy(): void {
        $path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
        if ( ! in_array( $path, self::LEGACY_PATHS, true ) ) return;

        wp_safe_redirect( 'https://' . self::SUBDOMAIN . '/', 301 );
        exit;
    }

    /**
     * Preview gate — non-admins see a clean 404 during preview mode so the
     * page doesn't get crawled before we open it.
     */
    private static function should_render_publicly(): bool {
        if ( self::STATUS === 'live' )    return true;
        if ( self::STATUS === 'preview' ) return current_user_can( 'manage_options' );
        return false;
    }

    /**
     * Main render — full HTML out, no theme chrome, no template_part calls.
     */
    public static function render_page(): void {
        if ( ! self::should_render_publicly() ) {
            // Preview mode + not admin: clean 404 (don't poison the index).
            status_header( 404 );
            nocache_headers();
            echo '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><title>404 — Em construção</title></head><body><p style="font-family:system-ui;text-align:center;padding:80px 20px;color:#666">empresas.weareedit.io — em construção. Em breve.</p></body></html>';
            exit;
        }

        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: text/html; charset=UTF-8' );

        self::emit_html();
        exit;
    }

    /* ─────────────────────────────────────────────────────────────────────
     * Content (Sprint 1 — minimal, will grow over the weekend)
     * Reuses curated constants from EDIT_Formacao_Corporativa_Page so we
     * keep a single source of truth.
     * ────────────────────────────────────────────────────────────────── */

    private static function clients(): array {
        if ( class_exists( 'EDIT_Formacao_Corporativa_Page' ) ) {
            return EDIT_Formacao_Corporativa_Page::CLIENTS;
        }
        return [];
    }

    private static function value_props(): array {
        if ( class_exists( 'EDIT_Formacao_Corporativa_Page' ) ) {
            return EDIT_Formacao_Corporativa_Page::VALUE_PROPS;
        }
        return [];
    }

    private static function stats(): array {
        if ( class_exists( 'EDIT_Formacao_Corporativa_Page' ) ) {
            return EDIT_Formacao_Corporativa_Page::STATS;
        }
        return [];
    }

    private static function areas(): array {
        if ( class_exists( 'EDIT_Formacao_Corporativa_Page' ) ) {
            return EDIT_Formacao_Corporativa_Page::AREAS;
        }
        return [];
    }

    private static function process(): array {
        if ( class_exists( 'EDIT_Formacao_Corporativa_Page' ) ) {
            return EDIT_Formacao_Corporativa_Page::PROCESS;
        }
        return [];
    }

    private static function faq(): array {
        if ( class_exists( 'EDIT_Formacao_Corporativa_Page' ) ) {
            return EDIT_Formacao_Corporativa_Page::FAQ;
        }
        return [];
    }

    /* ─────────────────────────────────────────────────────────────────────
     * HTML template
     * ────────────────────────────────────────────────────────────────── */

    private static function emit_html(): void {
        $title       = 'Formação para Empresas — EDIT.';
        $description = 'A escola digital portuguesa para upskilling e reskilling corporativo. Programas à medida em IA, Data, UX, Design, Marketing Digital e Programação. Elegíveis para Fundos de Compensação e Cheque-Formação.';
        $canonical   = 'https://' . self::SUBDOMAIN . '/';

        $clients     = self::clients();
        $value_props = self::value_props();
        $stats       = self::stats();
        $areas       = self::areas();
        $process     = self::process();
        $faq         = self::faq();
        ?><!doctype html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $title ); ?></title>
<meta name="description" content="<?php echo esc_attr( $description ); ?>">
<link rel="canonical" href="<?php echo esc_url( $canonical ); ?>">

<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
<meta property="og:description" content="<?php echo esc_attr( $description ); ?>">
<meta property="og:url" content="<?php echo esc_url( $canonical ); ?>">
<meta property="og:site_name" content="EDIT. para Empresas">
<meta property="og:locale" content="pt_PT">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo esc_attr( $title ); ?>">
<meta name="twitter:description" content="<?php echo esc_attr( $description ); ?>">

<?php if ( self::STATUS === 'preview' ) : ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>

<link rel="preconnect" href="https://weareedit.io" crossorigin>
<link rel="icon" href="https://weareedit.io/wp-content/uploads/2021/05/cropped-favicon-edit-32x32.png">

<style>
:root {
  --edit-yellow: #ffdd06;
  --edit-pink: #f92869;
  --edit-teal: #60c5b3;
  --edit-coral: #ec8172;
  --ink: #0a0a0a;
  --grey-1: #f4f4f4;
  --grey-2: #e5e5e5;
  --grey-3: #888;
  --grey-4: #444;
}

*, *::before, *::after { box-sizing: border-box; }

html { -webkit-text-size-adjust: 100%; }

body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica, Arial, sans-serif;
  color: var(--ink);
  background: #fff;
  line-height: 1.5;
  font-size: 16px;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

a { color: inherit; text-decoration: none; }

.wrap { max-width: 1240px; margin: 0 auto; padding: 0 28px; }

/* ── HEADER ──────────────────────────────────────────────────────── */
.site-header {
  position: sticky; top: 0; z-index: 50;
  background: #fff;
  border-bottom: 1px solid var(--grey-2);
}
.site-header .wrap {
  display: flex; align-items: center; justify-content: space-between;
  padding-top: 18px; padding-bottom: 18px;
}
.brand {
  display: flex; align-items: center; gap: 12px;
}
.brand-mark {
  width: 40px; height: 40px;
  background: var(--edit-yellow);
  display: flex; align-items: center; justify-content: center;
  font-weight: 900; font-size: 17px;
  color: var(--ink);
  border-radius: 4px;
  letter-spacing: -0.02em;
}
.brand-text {
  display: flex; flex-direction: column; line-height: 1.05;
}
.brand-text strong {
  font-weight: 800; letter-spacing: -0.02em; font-size: 16px;
}
.brand-text small {
  font-size: 11px; color: var(--grey-3);
  text-transform: uppercase; letter-spacing: 0.18em; margin-top: 2px;
}
.cta-header {
  display: inline-block;
  background: var(--ink); color: #fff;
  padding: 11px 20px;
  font-weight: 600; font-size: 14px;
  border-radius: 4px;
  transition: background 0.18s ease;
}
.cta-header:hover { background: var(--edit-pink); }

/* ── HERO ────────────────────────────────────────────────────────── */
.hero {
  padding: 80px 0 80px 0;
  background: #fff;
}
.hero .eyebrow {
  font-size: 12px; font-weight: 700;
  letter-spacing: 0.22em; text-transform: uppercase;
  color: var(--grey-3);
  margin: 0 0 24px 0;
}
.hero h1 {
  font-size: clamp(40px, 6.4vw, 88px);
  line-height: 0.98;
  letter-spacing: -0.035em;
  font-weight: 700;
  margin: 0 0 28px 0;
  max-width: 16ch;
}
.hero h1 .accent {
  background: var(--edit-yellow);
  padding: 0 0.08em;
  border-radius: 4px;
  white-space: nowrap;
}
.hero .lede {
  font-size: clamp(17px, 1.5vw, 21px);
  line-height: 1.45;
  color: var(--grey-4);
  max-width: 60ch;
  margin: 0 0 40px 0;
}
.hero .ctas {
  display: flex; gap: 12px; flex-wrap: wrap;
}
.btn {
  display: inline-block;
  padding: 16px 26px;
  font-weight: 600; font-size: 15px;
  border-radius: 4px;
  transition: all 0.18s ease;
  cursor: pointer;
  border: 0;
}
.btn-primary {
  background: var(--ink); color: #fff;
}
.btn-primary:hover { background: var(--edit-pink); }
.btn-secondary {
  background: transparent; color: var(--ink);
  border: 1.5px solid var(--ink);
}
.btn-secondary:hover { background: var(--ink); color: #fff; }

/* ── LOGO WALL ───────────────────────────────────────────────────── */
.logo-wall {
  padding: 56px 0 64px 0;
  border-top: 1px solid var(--grey-2);
}
.logo-wall .label {
  font-size: 12px; font-weight: 700;
  letter-spacing: 0.22em; text-transform: uppercase;
  color: var(--grey-3);
  text-align: center;
  margin: 0 0 36px 0;
}
.logo-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 36px 28px;
  align-items: center;
}
.logo-cell {
  display: flex; align-items: center; justify-content: center;
  height: 56px;
}
.logo-cell img {
  max-width: 100%; max-height: 100%;
  object-fit: contain;
  filter: grayscale(100%) brightness(0.7);
  opacity: 0.78;
  transition: filter 0.2s ease, opacity 0.2s ease;
}
.logo-cell:hover img {
  filter: none;
  opacity: 1;
}

@media (max-width: 880px) {
  .logo-grid { grid-template-columns: repeat(3, 1fr); }
}

/* ── STATS ───────────────────────────────────────────────────────── */
.stats {
  padding: 64px 0;
  background: var(--grey-1);
}
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 28px;
}
.stat {
  text-align: left;
  padding: 0 8px;
  border-left: 3px solid var(--edit-yellow);
  padding-left: 18px;
}
.stat .num {
  font-size: clamp(36px, 4vw, 52px);
  font-weight: 800; letter-spacing: -0.025em;
  line-height: 1;
  color: var(--ink);
  margin-bottom: 8px;
}
.stat .lbl {
  font-size: 14px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: var(--ink);
  margin-bottom: 6px;
}
.stat .sub {
  font-size: 13px;
  color: var(--grey-4);
  line-height: 1.45;
}

@media (max-width: 880px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 36px 28px; }
}

/* ── VALUE PROPS ─────────────────────────────────────────────────── */
.value-props {
  padding: 90px 0;
}
.section-eyebrow {
  font-size: 12px; font-weight: 700;
  letter-spacing: 0.22em; text-transform: uppercase;
  color: var(--grey-3);
  margin: 0 0 12px 0;
}
.section-title {
  font-size: clamp(28px, 3.6vw, 46px);
  line-height: 1.1;
  letter-spacing: -0.02em;
  font-weight: 700;
  margin: 0 0 56px 0;
  max-width: 22ch;
}
.vp-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 56px 64px;
}
.vp {
  padding-top: 22px;
  border-top: 2px solid var(--ink);
}
.vp h3 {
  font-size: 21px; font-weight: 700;
  letter-spacing: -0.01em;
  margin: 0 0 12px 0;
  line-height: 1.25;
}
.vp p {
  font-size: 15.5px;
  color: var(--grey-4);
  line-height: 1.55;
  margin: 0;
}

@media (max-width: 720px) {
  .vp-grid { grid-template-columns: 1fr; gap: 40px; }
}

/* ── AREAS ───────────────────────────────────────────────────────── */
.areas {
  padding: 90px 0;
  background: var(--grey-1);
}
.areas-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  margin-top: 40px;
}
.area-card {
  position: relative;
  background: #fff;
  padding: 32px 28px 28px;
  border-radius: 6px;
  border: 1px solid var(--grey-2);
  transition: all 0.2s ease;
  overflow: hidden;
}
.area-card:hover {
  border-color: var(--ink);
  transform: translateY(-2px);
}
.area-card .icon {
  position: absolute;
  top: 18px; right: 22px;
  font-size: 56px;
  font-weight: 900;
  letter-spacing: -0.04em;
  line-height: 1;
  color: var(--edit-yellow);
  opacity: 0.85;
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
}
.area-card h3 {
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.01em;
  margin: 0 60px 12px 0;
  line-height: 1.2;
}
.area-card .area-lede {
  font-size: 14.5px;
  color: var(--grey-4);
  margin: 0 0 18px 0;
  line-height: 1.55;
}
.area-card .topics {
  list-style: none;
  padding: 14px 0 0 0; margin: 0;
  border-top: 1px solid var(--grey-2);
}
.area-card .topics li {
  font-size: 13px;
  color: var(--grey-4);
  padding: 4px 0;
  display: flex; align-items: center; gap: 8px;
}
.area-card .topics li::before {
  content: '';
  width: 6px; height: 6px;
  background: var(--edit-yellow);
  border-radius: 50%;
  flex-shrink: 0;
}

@media (max-width: 880px) {
  .areas-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 560px) {
  .areas-grid { grid-template-columns: 1fr; }
}

/* ── PROCESS ─────────────────────────────────────────────────────── */
.process {
  padding: 90px 0;
}
.process-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 32px;
  margin-top: 40px;
}
.process-step {
  position: relative;
}
.process-step .num-tile {
  display: inline-flex;
  align-items: center; justify-content: center;
  width: 56px; height: 56px;
  background: var(--ink);
  color: #fff;
  font-weight: 800;
  font-size: 18px;
  letter-spacing: -0.02em;
  border-radius: 6px;
  margin-bottom: 18px;
}
.process-step .time-chip {
  display: inline-block;
  background: var(--edit-yellow);
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.04em;
  padding: 3px 10px;
  border-radius: 12px;
  margin-bottom: 12px;
}
.process-step h3 {
  font-size: 19px;
  font-weight: 700;
  letter-spacing: -0.01em;
  margin: 0 0 10px 0;
  line-height: 1.25;
}
.process-step p {
  font-size: 14.5px;
  color: var(--grey-4);
  line-height: 1.5;
  margin: 0;
}

@media (max-width: 880px) {
  .process-grid { grid-template-columns: repeat(2, 1fr); gap: 40px 32px; }
}
@media (max-width: 560px) {
  .process-grid { grid-template-columns: 1fr; gap: 40px; }
}

/* ── FINANCING ───────────────────────────────────────────────────── */
.financing {
  padding: 100px 0;
  background: var(--ink);
  color: #fff;
}
.financing .section-eyebrow { color: rgba(255,255,255,0.6); }
.financing .section-title {
  color: #fff;
  max-width: 24ch;
}
.financing .section-title .accent {
  background: var(--edit-yellow);
  color: var(--ink);
  padding: 0 0.1em;
  border-radius: 4px;
}
.financing-lede {
  font-size: 17px;
  color: rgba(255,255,255,0.78);
  max-width: 60ch;
  margin: -32px 0 56px 0;
  line-height: 1.55;
}
.financing-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  margin-bottom: 48px;
}
.fin-card {
  padding: 28px 26px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 6px;
  transition: all 0.2s ease;
}
.fin-card:hover {
  background: rgba(255,255,255,0.08);
  border-color: var(--edit-yellow);
}
.fin-card .tag {
  display: inline-block;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  background: var(--edit-yellow);
  color: var(--ink);
  padding: 3px 10px;
  border-radius: 3px;
  margin-bottom: 16px;
}
.fin-card h3 {
  font-size: 21px;
  font-weight: 700;
  letter-spacing: -0.01em;
  margin: 0 0 12px 0;
  line-height: 1.2;
  color: #fff;
}
.fin-card p {
  font-size: 14.5px;
  color: rgba(255,255,255,0.72);
  line-height: 1.55;
  margin: 0;
}
.financing-trust {
  border-top: 1px solid rgba(255,255,255,0.12);
  padding-top: 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}
.financing-trust .badge {
  font-size: 13px;
  color: rgba(255,255,255,0.7);
  letter-spacing: 0.02em;
}
.financing-trust .badge strong {
  color: #fff;
  font-weight: 700;
}
.financing-trust .cta-link {
  font-size: 14px;
  font-weight: 600;
  color: var(--edit-yellow);
  border-bottom: 1px solid var(--edit-yellow);
  padding-bottom: 2px;
}
.financing-trust .cta-link:hover {
  color: #fff;
  border-color: #fff;
}

@media (max-width: 880px) {
  .financing-grid { grid-template-columns: 1fr; }
}

/* ── FAQ ─────────────────────────────────────────────────────────── */
.faq {
  padding: 90px 0;
  background: var(--grey-1);
}
.faq-list {
  max-width: 820px;
  margin: 40px auto 0 auto;
}
.faq-item {
  border-bottom: 1px solid var(--grey-2);
}
.faq-item summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
  padding: 22px 0;
  font-size: 17px;
  font-weight: 600;
  letter-spacing: -0.005em;
  cursor: pointer;
  list-style: none;
  color: var(--ink);
  transition: color 0.15s ease;
}
.faq-item summary::-webkit-details-marker { display: none; }
.faq-item summary::after {
  content: '+';
  font-size: 28px;
  font-weight: 300;
  color: var(--ink);
  width: 24px;
  text-align: center;
  flex-shrink: 0;
  transition: transform 0.2s ease;
}
.faq-item[open] summary::after { content: '−'; }
.faq-item summary:hover { color: var(--edit-pink); }
.faq-item .answer {
  font-size: 15px;
  color: var(--grey-4);
  padding: 0 40px 22px 0;
  line-height: 1.6;
  max-width: 70ch;
}
.faq-item .answer strong { color: var(--ink); }

/* ── FINAL CTA ───────────────────────────────────────────────────── */
.final-cta {
  padding: 100px 0;
  background: var(--ink);
  color: #fff;
}
.final-cta .wrap {
  text-align: center;
}
.final-cta h2 {
  font-size: clamp(32px, 4.4vw, 56px);
  line-height: 1.05; letter-spacing: -0.025em;
  font-weight: 700;
  margin: 0 0 24px 0;
}
.final-cta p {
  font-size: 18px;
  color: rgba(255,255,255,0.75);
  max-width: 50ch;
  margin: 0 auto 36px auto;
  line-height: 1.5;
}
.final-cta .btn-primary {
  background: var(--edit-yellow);
  color: var(--ink);
}
.final-cta .btn-primary:hover { background: #fff; }

/* ── FOOTER ──────────────────────────────────────────────────────── */
.site-footer {
  padding: 40px 0;
  background: #fff;
  border-top: 1px solid var(--grey-2);
  font-size: 13px; color: var(--grey-3);
}
.site-footer .wrap {
  display: flex; justify-content: space-between; align-items: center;
  flex-wrap: wrap; gap: 16px;
}
.site-footer a { color: var(--grey-3); }
.site-footer a:hover { color: var(--ink); }

/* ── PREVIEW BANNER ──────────────────────────────────────────────── */
.preview-banner {
  background: var(--edit-yellow); color: var(--ink);
  text-align: center;
  padding: 10px 16px;
  font-size: 13px; font-weight: 700;
  letter-spacing: 0.04em;
}
.preview-banner code {
  background: rgba(0,0,0,0.08); padding: 1px 6px; border-radius: 3px;
  font-family: 'SF Mono', Menlo, Monaco, monospace; font-size: 12px;
}
</style>
</head>
<body>

<?php if ( self::STATUS === 'preview' ) : ?>
<div class="preview-banner">
  PREVIEW · empresas.weareedit.io · visível só para admins · flip <code>EDIT_Empresas_Page::STATUS</code> para <code>'live'</code> para abrir ao público
</div>
<?php endif; ?>

<!-- ─── HEADER ─────────────────────────────────────────────────── -->
<header class="site-header">
  <div class="wrap">
    <a href="/" class="brand">
      <div class="brand-mark">E.</div>
      <div class="brand-text">
        <strong>EDIT.</strong>
        <small>para empresas</small>
      </div>
    </a>
    <a href="#contacto" class="cta-header">Pedir Proposta</a>
  </div>
</header>

<!-- ─── HERO ───────────────────────────────────────────────────── -->
<section class="hero">
  <div class="wrap">
    <p class="eyebrow">Formação para Empresas</p>
    <h1>Forme a sua equipa nas competências <span class="accent">digitais</span> que o futuro exige.</h1>
    <p class="lede">Programas à medida em <strong>IA, Data, UX, Design, Marketing Digital e Programação</strong>. Entregues presencialmente em Lisboa e Porto, nas vossas instalações, ou em formato remoto. Elegíveis para Cheque‑Formação e Fundos de Compensação.</p>
    <div class="ctas">
      <a href="#contacto" class="btn btn-primary">Pedir Proposta</a>
      <a href="#metodologia" class="btn btn-secondary">Conhecer a Metodologia</a>
    </div>
  </div>
</section>

<!-- ─── LOGO WALL ──────────────────────────────────────────────── -->
<?php if ( ! empty( $clients ) ) : ?>
<section class="logo-wall">
  <div class="wrap">
    <p class="label">Empresas que confiam na EDIT.</p>
    <div class="logo-grid">
      <?php foreach ( array_slice( $clients, 0, 12 ) as $client ) : if ( empty( $client['logo'] ) ) continue; ?>
        <div class="logo-cell">
          <img src="<?php echo esc_url( $client['logo'] ); ?>"
               alt="<?php echo esc_attr( $client['name'] ); ?>"
               loading="lazy">
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── STATS ──────────────────────────────────────────────────── -->
<?php if ( ! empty( $stats ) ) : ?>
<section class="stats">
  <div class="wrap">
    <div class="stats-grid">
      <?php foreach ( $stats as $s ) : ?>
        <div class="stat">
          <div class="num"><?php echo esc_html( $s['number'] ); ?></div>
          <div class="lbl"><?php echo esc_html( $s['label'] ); ?></div>
          <?php if ( ! empty( $s['sub'] ) ) : ?>
            <div class="sub"><?php echo esc_html( $s['sub'] ); ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── VALUE PROPS ────────────────────────────────────────────── -->
<?php if ( ! empty( $value_props ) ) : ?>
<section class="value-props" id="metodologia">
  <div class="wrap">
    <p class="section-eyebrow">Porquê EDIT.</p>
    <h2 class="section-title">A escola digital portuguesa que fala a língua das empresas.</h2>
    <div class="vp-grid">
      <?php foreach ( $value_props as $vp ) : ?>
        <div class="vp">
          <h3><?php echo esc_html( $vp['title'] ); ?></h3>
          <p><?php echo wp_kses_post( $vp['body'] ); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── AREAS ──────────────────────────────────────────────────── -->
<?php if ( ! empty( $areas ) ) : ?>
<section class="areas" id="areas">
  <div class="wrap">
    <p class="section-eyebrow">Áreas de Formação</p>
    <h2 class="section-title">5 disciplinas digitais. Programas modulares ou completos.</h2>
    <div class="areas-grid">
      <?php foreach ( $areas as $area ) : ?>
        <article class="area-card">
          <div class="icon"><?php echo esc_html( $area['icon'] ?? '' ); ?></div>
          <h3><?php echo esc_html( $area['title'] ); ?></h3>
          <p class="area-lede"><?php echo esc_html( $area['lede'] ); ?></p>
          <?php if ( ! empty( $area['topics'] ) ) : ?>
          <ul class="topics">
            <?php foreach ( $area['topics'] as $topic ) : ?>
              <li><?php echo esc_html( $topic ); ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── PROCESS ────────────────────────────────────────────────── -->
<?php if ( ! empty( $process ) ) : ?>
<section class="process" id="processo">
  <div class="wrap">
    <p class="section-eyebrow">Como Trabalhamos</p>
    <h2 class="section-title">4 passos. Do briefing à medição de impacto.</h2>
    <div class="process-grid">
      <?php foreach ( $process as $step ) : ?>
        <div class="process-step">
          <div class="num-tile"><?php echo esc_html( $step['number'] ); ?></div>
          <?php if ( ! empty( $step['time'] ) ) : ?>
            <div class="time-chip"><?php echo esc_html( $step['time'] ); ?></div>
          <?php endif; ?>
          <h3><?php echo esc_html( $step['title'] ); ?></h3>
          <p><?php echo esc_html( $step['body'] ); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── FINANCING ──────────────────────────────────────────────── -->
<section class="financing" id="financiamento">
  <div class="wrap">
    <p class="section-eyebrow">Financiamento</p>
    <h2 class="section-title">Não é o motivo. É o <span class="accent">desbloqueio</span>.</h2>
    <p class="financing-lede">As empresas portuguesas têm acesso a mecanismos de apoio à formação que poucas conhecem. Os nossos programas são elegíveis para os principais — e ajudamos no processo de candidatura.</p>
    <div class="financing-grid">
      <div class="fin-card">
        <span class="tag">Até 30 Junho 2026</span>
        <h3>Cheque-Formação + Digital</h3>
        <p>Apoio direto a PME para formação dos trabalhadores e capacitação digital. Janela actual termina a 30 de Junho de 2026 — vale a pena verificar elegibilidade agora.</p>
      </div>
      <div class="fin-card">
        <span class="tag">Anual</span>
        <h3>SIFIDE</h3>
        <p>Sistema de Incentivos Fiscais à I&amp;D Empresarial. Permite deduzir parte do investimento em formação tecnológica ao IRC. Aplicável a programas com componente de inovação.</p>
      </div>
      <div class="fin-card">
        <span class="tag">A explorar</span>
        <h3>Outros apoios</h3>
        <p>POCH, PT 2030, fundos sectoriais. A elegibilidade depende do setor, dimensão da empresa e tipo de programa. Falamos consigo para mapear o melhor enquadramento.</p>
      </div>
    </div>
    <div class="financing-trust">
      <div class="badge"><strong>EDIT.</strong> é Entidade Formadora Certificada pela <strong>DGERT nº 18391</strong>. Todas as formações são elegíveis para mecanismos de apoio à formação corporativa.</div>
      <a href="#contacto" class="cta-link">Falar com a equipa →</a>
    </div>
  </div>
</section>

<!-- ─── FAQ ────────────────────────────────────────────────────── -->
<?php if ( ! empty( $faq ) ) : ?>
<section class="faq" id="faq">
  <div class="wrap">
    <p class="section-eyebrow">Perguntas Frequentes</p>
    <h2 class="section-title">Respostas às perguntas que os departamentos de compras fazem.</h2>
    <div class="faq-list">
      <?php foreach ( $faq as $item ) : ?>
        <details class="faq-item">
          <summary><?php echo esc_html( $item['q'] ); ?></summary>
          <div class="answer"><?php echo wp_kses_post( $item['a'] ); ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── FINAL CTA ──────────────────────────────────────────────── -->
<section class="final-cta" id="contacto">
  <div class="wrap">
    <h2>Vamos formar a sua equipa.</h2>
    <p>Conte-nos do desafio. Em 24 horas úteis voltamos com um plano e uma proposta. Sem compromisso.</p>
    <a href="mailto:empresas@weareedit.io?subject=Pedido%20de%20Proposta%20-%20Forma%C3%A7%C3%A3o%20para%20Empresas" class="btn btn-primary">Pedir Proposta</a>
  </div>
</section>

<!-- ─── FOOTER ─────────────────────────────────────────────────── -->
<footer class="site-footer">
  <div class="wrap">
    <div>EDIT. — Disruptive Digital Education · DGERT nº 18391 · Entidade Formadora Certificada</div>
    <div>
      <a href="https://weareedit.io/">weareedit.io</a> ·
      <a href="mailto:empresas@weareedit.io">empresas@weareedit.io</a>
    </div>
  </div>
</footer>

</body>
</html>
        <?php
    }
}
