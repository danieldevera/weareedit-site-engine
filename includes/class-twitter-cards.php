<?php
/**
 * Twitter / X Card Meta Tags
 * Safe: skips if Rank Math is active (Rank Math outputs Twitter tags).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Twitter_Cards {

    public static function init() {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        // Rank Math handles Twitter tags — skip unless admin has forced output
        if ( defined( 'RANK_MATH_VERSION' ) && empty( $settings['force_meta_output'] ) ) {
            return;
        }
        add_action( 'wp_head', [ __CLASS__, 'output_twitter_tags' ], 3 );
    }

    public static function output_twitter_tags() {
        $settings    = get_option( 'edit_seo_fix_settings', [] );
        $handle      = $settings['twitter_handle'] ?? '@weareedit';
        $title       = get_the_title() ?: get_bloginfo( 'name' );
        $description = EDIT_Meta_Tags::get_description();
        $image       = self::get_image();

        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:site" content="' . esc_attr( $handle ) . '">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
        if ( $image ) {
            echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
        }
    }

    private static function get_image(): string {
        if ( is_singular() && has_post_thumbnail() ) {
            $img = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
            if ( $img ) return $img[0];
        }
        return 'https://weareedit.io/wp-content/uploads/2018/12/SHARE-EDIT.jpg';
    }
}
