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
.edit-fhero {
    position: relative; z-index: 2;
    max-width: 1100px; margin: 0 auto; padding: 80px 60px 90px;
    font-family: 'SctoGroteskA', 'Helvetica Neue', Helvetica, Arial, sans-serif;
}
.edit-fhero__quote-row {
    display: flex; align-items: flex-start; gap: 24px; margin: 0 0 40px 0;
}
.edit-fhero__mark {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 90px; line-height: 0.6; color: #f92869; flex-shrink: 0;
}
.edit-fhero__quote {
    font-family: Georgia, 'Times New Roman', serif; font-style: italic;
    font-size: 30px; line-height: 1.35; color: rgba(255,255,255,0.92);
    max-width: 820px; margin: 0;
}
.edit-fhero__title {
    font-size: 160px; font-weight: 900; line-height: 0.9;
    letter-spacing: -0.04em; color: #fff; margin: 0;
}
.edit-fhero__title .p { color: #f92869; }

/* Hide the legacy markup that ships as siblings of our injected hero.
   The theme renders <section.edit-fhero> + <div.filter-select> + two h2
   paragraphs as siblings inside the same wrapper, so the general-sibling
   combinator catches both without needing a body-class anchor. */
.edit-fhero ~ .filter-select,
.edit-fhero ~ h2 {
    display: none !important;
}

@media (max-width: 900px) {
    .edit-fhero { padding: 60px 24px 70px; }
    .edit-fhero__quote { font-size: 22px; }
    .edit-fhero__mark { font-size: 64px; }
    .edit-fhero__title { font-size: 88px; }
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
              .   '<p class="edit-fhero__quote">We help individuals &amp; businesses grow in the Digital Ecosystem through our physical &amp; remote learning programs.</p>'
              . '</div>'
              . '<h1 class="edit-fhero__title">Formação<span class="p">.</span></h1>'
              . '</section>';

        return substr_replace( $html, $hero . $anchor, $pos, strlen( $anchor ) );
    }
}
