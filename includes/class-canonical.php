<?php
/**
 * Canonical URL Tags (safe — only adds if Rank Math hasn't already)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Canonical {

    public static function init() {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        // Only add canonical if Rank Math is NOT handling it (or admin has forced output)
        if ( ! defined( 'RANK_MATH_VERSION' ) || ! empty( $settings['force_meta_output'] ) ) {
            remove_action( 'wp_head', 'rel_canonical' );
            add_action( 'wp_head', [ __CLASS__, 'output_canonical' ], 4 );
        }

        // Always canonicalize filtered/sorted archive URLs (e.g. /formacao/?tpoid=...&areaid=...)
        // back to the clean archive URL. Works via Rank Math's filter when Rank Math is active,
        // or via wp_head otherwise. Fixes "No self-referencing hreflang" SEMrush errors.
        if ( defined( 'RANK_MATH_VERSION' ) ) {
            add_filter( 'rank_math/frontend/canonical', [ __CLASS__, 'canonical_filtered_archives' ] );
        } else {
            add_filter( 'wp_head', [ __CLASS__, 'maybe_output_filtered_archive_canonical' ], 3 );
        }
    }

    public static function output_canonical() {
        $canonical = self::get_canonical_url();
        if ( $canonical && ! is_wp_error( $canonical ) ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
        }
    }

    public static function get_canonical_url() {
        global $wp;

        if ( is_front_page() ) {
            return trailingslashit( home_url() );
        }

        if ( is_singular() ) {
            return get_permalink();
        }

        if ( is_category() ) {
            return get_category_link( get_queried_object_id() );
        }

        if ( is_tag() ) {
            return get_tag_link( get_queried_object_id() );
        }

        if ( is_tax() ) {
            $term = get_queried_object();
            $link = get_term_link( $term );
            return is_wp_error( $link ) ? null : $link;
        }

        if ( is_author() ) {
            return get_author_posts_url( get_queried_object_id() );
        }

        if ( is_search() ) {
            return null;
        }

        return trailingslashit( home_url( $wp->request ) );
    }

    /**
     * When Rank Math is active, override its canonical for filtered archive pages.
     * e.g. /formacao/?tpoid=41794&areaid=1398  →  /formacao/
     *
     * Rank Math passes its computed canonical as the argument; we only change it
     * when the current URL is a post-type archive with query string parameters.
     */
    public static function canonical_filtered_archives( string $canonical ): string {
        if ( is_post_type_archive() && ! empty( $_GET ) ) {
            $clean = get_post_type_archive_link( get_query_var( 'post_type' ) );
            if ( $clean ) return $clean;
        }
        return $canonical;
    }

    /**
     * Non-Rank Math fallback: output a canonical <link> for filtered archive pages.
     * Only fires when our main canonical module is not already active.
     */
    public static function maybe_output_filtered_archive_canonical() {
        if ( is_post_type_archive() && ! empty( $_GET ) ) {
            $clean = get_post_type_archive_link( get_query_var( 'post_type' ) );
            if ( $clean ) {
                echo '<link rel="canonical" href="' . esc_url( $clean ) . '">' . "\n";
            }
        }
    }
}
