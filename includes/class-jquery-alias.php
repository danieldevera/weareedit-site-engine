<?php
/**
 * jQuery $ alias — restore `$` as a global pointer to `jQuery`.
 *
 * The formacao theme + several plugins ship inline <script> blocks that
 * use `$(...)` at top level (e.g. `$(".wpcf7-spinner").css(...)`,
 * `$('section.programa-cheque a').on('click', ...)`).
 *
 * WordPress runs jQuery.noConflict() by default, so `$` is unbound and
 * those scripts throw "Uncaught TypeError: $ is not a function" — which
 * blocks the bootcamp inscription form's submit handler.
 *
 * Fix: prepend `window.$ = jQuery;` right after jQuery loads so the
 * legacy inline scripts work without rewriting them.
 *
 * @package WeareEditSiteEngine
 * @since   1.5.194
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_JQuery_Alias {

    public static function init(): void {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'inject_alias' ], 999 );
    }

    public static function inject_alias(): void {
        // Inject the alias AFTER the core jquery handle. WordPress
        // enqueues jquery + jquery-migrate as separate handles; aliasing
        // on `jquery` covers both since migrate depends on jquery.
        wp_add_inline_script( 'jquery', 'window.$ = window.jQuery;', 'after' );
    }
}
