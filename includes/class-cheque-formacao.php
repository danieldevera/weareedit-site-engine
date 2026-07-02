<?php
/**
 * Cheque-Formação + Digital Notice
 *
 * Auto-injects a configurable eligibility notice on course pages.
 * This subsidy (up to €750) is a key conversion driver — surfacing it
 * at the point of purchase directly increases enrolment rates.
 *
 * The notice is appended to the_content on course post types.
 * Style is minimal and inherits from the theme; a small inline style
 * is included as a safe default that the theme can override.
 *
 * Settings keys (in edit_seo_fix_settings):
 *   cheque_formacao_enabled    — bool, master toggle
 *   cheque_formacao_text       — custom notice body text
 *   cheque_formacao_link       — URL to gov application page
 *   course_post_types          — shared with course schema module
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Cheque_Formacao {

    const DEFAULT_TEXT = 'Este curso é elegível para o <strong>Cheque-Formação + Digital</strong> do programa Emprego + Digital. Trabalhadores elegíveis podem receber até <strong>€750 de apoio governamental</strong> para cobrir o custo desta formação.';

    const DEFAULT_LINK = 'https://www.iefp.pt/cheque-formacao';

    public static function init() {
        // SUNSET (2026-07-02, Daniel): the Cheque-Formacao + Digital program ended 30 Jun 2026.
        // Notices are hard-off regardless of the stored toggle; the nav item is hidden and the
        // landing page 301s to /formacao/. Remove this block if the program ever returns.
        add_filter( 'wp_nav_menu_objects', [ __CLASS__, 'hide_menu_items' ] );
        add_action( 'template_redirect', [ __CLASS__, 'redirect_landing' ] );
        // The Semplice theme renders its nav itself (primaryNavItem__*), bypassing WP menu
        // filters — hide the item at the DOM level too (CSS catches dynamically-rendered
        // nodes instantly; the observer removes wrappers so no empty gap remains).
        add_action( 'wp_head', [ __CLASS__, 'hide_menu_css' ], 99 );
        add_action( 'wp_footer', [ __CLASS__, 'hide_menu_js' ], 99 );
        return;

        // -- dormant pre-sunset behaviour below (unreachable) --
        $settings = get_option( 'edit_seo_fix_settings', [] );
        if ( empty( $settings['cheque_formacao_enabled'] ) ) return;

        add_filter( 'the_content', [ __CLASS__, 'maybe_inject_notice' ] );
    }

    /** Drop any nav item pointing at the retired cheque pages (mega-menu incl.). */
    public static function hide_menu_items( $items ) {
        foreach ( $items as $k => $item ) {
            $url = isset( $item->url ) ? (string) $item->url : '';
            if ( stripos( $url, 'cheque' ) !== false ) unset( $items[ $k ] );
        }
        return $items;
    }

    /** CSS first line of defence — hides the link wherever it appears, incl. late-rendered nav. */
    public static function hide_menu_css(): void {
        echo '<style id="edit-cheque-sunset">a[href*="/cheque-digital"]{display:none!important;}</style>' . "\n";
    }

    /** Remove the (possibly animated) wrapper so the hidden item leaves no gap. */
    public static function hide_menu_js(): void {
        ?>
<script id="edit-cheque-sunset-js">
(function () {
    'use strict';
    function zap( root ) {
        ( root.querySelectorAll ? root : document ).querySelectorAll( 'a[href*="/cheque-digital"]' ).forEach( function ( a ) {
            var w = a.closest( 'li' ) || a.parentElement || a;
            // only collapse the wrapper when the link is its sole content
            if ( w !== a && w.textContent.trim() !== a.textContent.trim() ) w = a;
            w.style.display = 'none';
        } );
    }
    zap( document );
    if ( window.MutationObserver && document.body ) {
        new MutationObserver( function () { zap( document ); } ).observe( document.body, { childList: true, subtree: true } );
    }
})();
</script>
        <?php
    }

    /** 301 the retired landing page to the catalog hub. */
    public static function redirect_landing() {
        if ( is_admin() ) return;
        $path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( (string) $_SERVER['REQUEST_URI'], '?' ) : '';
        if ( rtrim( $path, '/' ) === '/cheque-digital' ) {
            wp_redirect( home_url( '/formacao/' ), 301 );
            exit;
        }
    }

    public static function maybe_inject_notice( string $content ): string {
        if ( ! is_singular() || is_admin() ) return $content;

        $settings   = get_option( 'edit_seo_fix_settings', [] );
        $post_types = self::get_course_post_types( $settings );

        if ( ! in_array( get_post_type(), $post_types, true ) ) return $content;

        return $content . self::render_notice( $settings );
    }

    private static function render_notice( array $settings ): string {
        $text = ! empty( $settings['cheque_formacao_text'] )
            ? wp_kses_post( $settings['cheque_formacao_text'] )
            : self::DEFAULT_TEXT;

        $link = ! empty( $settings['cheque_formacao_link'] )
            ? esc_url( $settings['cheque_formacao_link'] )
            : self::DEFAULT_LINK;

        return '
<div class="edit-cheque-formacao-notice" style="
    background: #f0f7ff;
    border-left: 4px solid #0057b8;
    border-radius: 4px;
    padding: 16px 20px;
    margin: 32px 0;
    font-size: 0.95em;
    line-height: 1.6;
">
    <p style="margin: 0 0 8px 0;">
        <strong style="color: #0057b8;">💡 Apoio Governamental Disponível</strong>
    </p>
    <p style="margin: 0 0 12px 0;">' . $text . '</p>
    <a href="' . $link . '" target="_blank" rel="noopener noreferrer"
       style="display:inline-block; background:#0057b8; color:#fff; padding:8px 16px;
              border-radius:4px; text-decoration:none; font-weight:600; font-size:0.9em;">
        Como candidatar-se ao Cheque-Formação →
    </a>
</div>';
    }

    private static function get_course_post_types( array $settings ): array {
        $raw = $settings['course_post_types'] ?? 'formacao,curso,course';
        return array_filter( array_map( 'trim', explode( ',', $raw ) ) );
    }
}
