<?php
/**
 * Strip HTML and decode entities from titles in Rank Math LLMS Txt output.
 *
 * Several weareedit.io posts include decorative <font color="..."> and </br>
 * tags inside post_title to style the H1 visually. Rank Math's LLMS Txt module
 * passes those titles through esc_html(), so /llms.txt renders literal
 * "&lt;font color=&quot;...&quot;&gt;" markup — unreadable to LLM crawlers.
 *
 * This module hooks the LLMS Txt module's before_output / after_output actions
 * (rank_math/llms_txt/before_output and rank_math/llms_txt/after_output) and
 * registers a the_title filter scoped strictly to the LLMS Txt rendering pass.
 * Page H1s keep their decorative tags; /llms.txt gets clean readable text.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EDIT_LLMS_Txt_Cleanup {

    public static function init() {
        add_action( 'rank_math/llms_txt/before_output', [ __CLASS__, 'enable' ] );
        add_action( 'rank_math/llms_txt/after_output',  [ __CLASS__, 'disable' ] );
    }

    public static function enable() {
        add_filter( 'the_title', [ __CLASS__, 'clean' ], 99 );
    }

    public static function disable() {
        remove_filter( 'the_title', [ __CLASS__, 'clean' ], 99 );
    }

    public static function clean( $title ) {
        if ( '' === $title || null === $title ) {
            return $title;
        }

        $clean = wp_strip_all_tags( (string) $title );
        $clean = html_entity_decode( $clean, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $clean = preg_replace( '/\s+/u', ' ', $clean );

        return trim( $clean );
    }
}
