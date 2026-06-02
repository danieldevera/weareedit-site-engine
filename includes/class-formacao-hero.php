<?php
/**
 * Formação hero — replaces the legacy hero paragraph with a quote-led
 * editorial intro. Output-buffer rewrite, scoped to /formacao/ only.
 *
 * Legacy hero shipped a long descriptive paragraph (Explora cursos…) under
 * the H1 — too dense, no rhythm. This rewrite swaps it for:
 *   • Pink pull-quote (Georgia italic)
 *   • Big "Formação." headline beneath
 *
 * The theme renders the hero inside a `<div class="filter-select">…</div>`
 * + two responsive `<h2>` paragraphs. We strip the paragraphs and inject
 * the quote block right before the filter-select.
 *
 * @package WeareEditSiteEngine
 * @since   1.5.269
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Formacao_Hero {

    public static function init(): void {
        add_action( 'wp_head', [ __CLASS__, 'maybe_print_css' ], 7 );
        // Run after the early15 banner injection so its anchor still works.
        add_filter( 'weareedit_site_engine_output_buffer', [ __CLASS__, 'maybe_rewrite_hero' ], 9 );
    }

    private static function is_active(): bool {
        if ( is_admin() || is_feed() ) return false;
        $path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( (string) $_SERVER['REQUEST_URI'], '?' ) : '';
        $normalised = '/' . trim( (string) $path, '/' ) . '/';
        // Match /formacao/ exactly — not course singulars under it.
        return $normalised === '/formacao/';
    }

    public static function maybe_print_css(): void {
        if ( ! self::is_active() ) return;
        ?>
<style id="edit-formacao-hero-css">
/* Load the brand font's heaviest weight directly so we don't depend on
   theme load order. Regular/Medium/Bold ship via the theme. */
@font-face {
    font-family: 'SctoGroteskA';
    src: url('https://weareedit.io/wp-content/themes/weareedit/css/fonts/SctoGroteskA-Black.woff2') format('woff2');
    font-weight: 900; font-style: normal; font-display: swap;
}

.edit-fhero {
    position: relative !important; z-index: 2 !important;
    max-width: 1100px !important; margin: 0 !important; padding: 80px 0 90px !important;
    font-family: 'SctoGroteskA', 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
    text-align: left !important;
}
.edit-fhero * { text-align: left !important; }
.edit-fhero__quote-row {
    display: flex !important; align-items: flex-start !important; gap: 24px !important;
    margin: 0 0 40px 0 !important;
}
.edit-fhero__mark {
    font-family: Georgia, 'Times New Roman', serif !important;
    font-size: 90px !important; line-height: 0.6 !important; color: #f92869 !important;
    flex-shrink: 0 !important;
}
.edit-fhero__quote {
    font-family: Georgia, 'Times New Roman', serif !important; font-style: italic !important;
    font-size: 30px !important; line-height: 1.35 !important; color: rgba(255,255,255,0.92) !important;
    max-width: 820px !important; margin: 0 !important; font-weight: 400 !important;
}
.edit-fhero__attr {
    display: block !important; margin: 16px 0 0 0 !important;
    font-family: 'SctoGroteskA', 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
    font-style: normal !important; font-weight: 700 !important;
    font-size: 11px !important; letter-spacing: 0.22em !important;
    text-transform: uppercase !important; color: rgba(255,255,255,0.55) !important;
}
.edit-fhero__title {
    /* Matches homepage hero h1: 100px / 700 / 1.1 / -3.13px (theme spec). */
    font-family: 'SctoGroteskA', 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
    font-size: 100px !important; font-weight: 700 !important; line-height: 1.1 !important;
    letter-spacing: -3.13px !important; color: #fff !important; margin: 0 !important;
    text-transform: none !important;
}
.edit-fhero__title .p { color: #f92869 !important; }

/* Hide the legacy markup that ships as siblings of our injected hero. */
.edit-fhero ~ .filter-select,
.edit-fhero ~ h2 { display: none !important; }

@media (max-width: 900px) {
    .edit-fhero { padding: 60px 24px 70px !important; }
    .edit-fhero__quote { font-size: 22px !important; }
    .edit-fhero__mark { font-size: 64px !important; }
    .edit-fhero__title { font-size: 56px !important; letter-spacing: -1.8px !important; }
}
</style>
        <?php
    }

    public static function maybe_rewrite_hero( string $html ): string {
        if ( ! self::is_active() ) return $html;
        // Guard against double-injection. Look for the section tag itself,
        // not just "edit-fhero" — that token also appears in the inline CSS.
        if ( strpos( $html, '<section class="edit-fhero"' ) !== false ) return $html;
        // Anchor: the existing hero's filter-select div. Insert our quote
        // hero immediately before it. CSS above hides the legacy markup.
        $anchor = '<div class="filter-select">';
        $pos    = strpos( $html, $anchor );
        if ( $pos === false ) return $html;

        $hero = '<section class="edit-fhero" aria-label="Hero">'
              . '<div class="edit-fhero__quote-row">'
              .   '<span class="edit-fhero__mark">&ldquo;</span>'
              .   '<p class="edit-fhero__quote">We help individuals &amp; businesses grow in the Digital Ecosystem through our physical &amp; remote learning programs.<span class="edit-fhero__attr">Daniel Devera &middot; Founder, EDIT.</span></p>'
              . '</div>'
              . '<h1 class="edit-fhero__title">Formação<span class="p">.</span></h1>'
              . '</section>';

        return substr_replace( $html, $hero . $anchor, $pos, strlen( $anchor ) );
    }
}
