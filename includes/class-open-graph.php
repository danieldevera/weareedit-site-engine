<?php
/**
 * Open Graph Tags
 * Safe: skips entirely if Rank Math is active (Rank Math outputs OG tags).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Open_Graph {

    public static function init() {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        // Rank Math handles OG tags — skip unless admin has forced output.
        // The og:type override for course/in-company/criticas pages is
        // handled via in-place str_replace in class-output-buffer.php
        // (appending a second tag was a no-op: FB/LinkedIn use first-seen).
        if ( defined( 'RANK_MATH_VERSION' ) && empty( $settings['force_meta_output'] ) ) {
            return;
        }
        add_action( 'wp_head', [ __CLASS__, 'output_og_tags' ], 2 );
    }

    public static function output_og_tags() {
        $settings    = get_option( 'edit_seo_fix_settings', [] );
        $site_name   = $settings['og_site_name'] ?? get_bloginfo( 'name' );
        $description = EDIT_Meta_Tags::get_description();
        $image       = self::get_og_image();

        echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
        echo '<meta property="og:locale" content="pt_PT">' . "\n";
        echo '<meta property="og:type" content="' . esc_attr( is_singular( 'post' ) ? 'article' : 'website' ) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( get_the_title() ?: get_bloginfo( 'name' ) ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( EDIT_Canonical::get_canonical_url() ?: home_url() ) . '">' . "\n";

        if ( $image ) {
            echo '<meta property="og:image" content="' . esc_url( $image['url'] ) . '">' . "\n";
            echo '<meta property="og:image:width" content="' . esc_attr( $image['width'] ) . '">' . "\n";
            echo '<meta property="og:image:height" content="' . esc_attr( $image['height'] ) . '">' . "\n";
        }
    }

    private static function get_og_image(): ?array {
        if ( is_singular() && has_post_thumbnail() ) {
            $img = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
            if ( $img ) return [ 'url' => $img[0], 'width' => $img[1], 'height' => $img[2] ];
        }
        return [
            'url'    => 'https://weareedit.io/wp-content/uploads/2018/12/SHARE-EDIT.jpg',
            'width'  => 1200,
            'height' => 630,
        ];
    }
}
