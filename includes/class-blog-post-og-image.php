<?php
/**
 * Blog Post CPT — Open Graph image enhancement.
 * ─────────────────────────────────────────────────────────────────────────────
 * Rank Math's default og:image falls back to the WP Featured Image, but the
 * Blog Post CPT stores its hero in ACF `fundo_header` (Background) — not the
 * standard featured image slot. Without this fix, LinkedIn / WhatsApp / Slack
 * scrape the article URL and find no `og:image` → render a text-only link
 * card (or pick an arbitrary image from the page).
 *
 * This filter intercepts Rank Math's OG image pipeline. If the Blog Post has
 * no WP Featured Image, we substitute the ACF Background image (fallback to
 * Imagem Small). Posts with a Featured Image set keep Rank Math's default
 * behaviour — no override.
 *
 * Hooks:
 *   - rank_math/opengraph/facebook/og_image       — primary OG image (LinkedIn, FB, WhatsApp, iMessage)
 *   - rank_math/opengraph/twitter/twitter_image   — Twitter / X card image
 *   - rank_math/opengraph/facebook/og_image_secure_url — HTTPS variant
 *
 * Fallback order:
 *   1. WP Featured Image (Rank Math's default — we don't touch)
 *   2. ACF `fundo_header` (Background image)
 *   3. ACF `home_image_small` (Imagem Small / Listagem thumbnail)
 *   4. Rank Math's site-default OG image
 *
 * @since 1.5.284
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Blog_Post_OG_Image {

    const CPT_SLUG        = 'blog-post';
    const ACF_BACKGROUND  = 'fundo_header';
    const ACF_IMAGE_SMALL = 'home_image_small';

    public static function init(): void {
        add_filter( 'rank_math/opengraph/facebook/og_image',            [ __CLASS__, 'get_og_image' ], 10 );
        add_filter( 'rank_math/opengraph/twitter/twitter_image',        [ __CLASS__, 'get_og_image' ], 10 );
        add_filter( 'rank_math/opengraph/facebook/og_image_secure_url', [ __CLASS__, 'get_og_image' ], 10 );
    }

    /**
     * Filter callback — returns the URL Rank Math should use for og:image.
     *
     * @param string $image_url  URL Rank Math computed (may be empty if no fallback found).
     * @return string
     */
    public static function get_og_image( $image_url ) {
        // Only act on Blog Post CPT
        if ( ! is_singular( self::CPT_SLUG ) ) return $image_url;

        $post_id = get_queried_object_id();
        if ( ! $post_id ) return $image_url;

        // Featured Image set? Let Rank Math handle it.
        if ( has_post_thumbnail( $post_id ) ) return $image_url;

        // No Featured Image — try ACF fundo_header (Background)
        $bg_url = self::resolve_acf_image( self::ACF_BACKGROUND, $post_id );
        if ( $bg_url ) return $bg_url;

        // Fall back to ACF home_image_small (Imagem Small)
        $small_url = self::resolve_acf_image( self::ACF_IMAGE_SMALL, $post_id );
        if ( $small_url ) return $small_url;

        // Nothing matched — return whatever Rank Math had (likely site default)
        return $image_url;
    }

    /**
     * ACF image field can return int (attachment ID), string (URL or ID), or
     * array (full image data). Normalise to a URL string.
     */
    private static function resolve_acf_image( string $field_name, int $post_id ): ?string {
        if ( ! function_exists( 'get_field' ) ) return null;

        $value = get_field( $field_name, $post_id );
        if ( empty( $value ) ) return null;

        // ACF "Return as: Image Array"
        if ( is_array( $value ) ) {
            return ! empty( $value['url'] ) ? $value['url'] : null;
        }

        // ACF "Return as: Image ID" (int)
        if ( is_numeric( $value ) ) {
            $url = wp_get_attachment_image_url( (int) $value, 'large' );
            return $url ?: null;
        }

        // ACF "Return as: Image URL" (string)
        if ( is_string( $value ) ) {
            if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
                return $value;
            }
            // String of digits → treat as attachment ID
            if ( ctype_digit( $value ) ) {
                $url = wp_get_attachment_image_url( (int) $value, 'large' );
                return $url ?: null;
            }
        }

        return null;
    }
}
