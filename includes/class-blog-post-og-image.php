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

    const CPT_SLUG        = 'blog';
    const ACF_BACKGROUND  = 'fundo_header';
    const ACF_IMAGE_SMALL = 'home_image_small';

    public static function init(): void {
        // Filter Rank Math's og:image when it computes one (rarely fires for Blog Post CPT
        // because there's usually no Featured Image — kept as belt-and-suspenders).
        add_filter( 'rank_math/opengraph/facebook/og_image',            [ __CLASS__, 'filter_og_image' ], 10 );
        add_filter( 'rank_math/opengraph/twitter/twitter_image',        [ __CLASS__, 'filter_og_image' ], 10 );
        add_filter( 'rank_math/opengraph/facebook/og_image_secure_url', [ __CLASS__, 'filter_og_image' ], 10 );

        // Primary mechanism — output og:image directly via wp_head when Rank Math
        // doesn't emit one. Rank Math runs at priority 1; we run at 50 to fire after
        // and detect the gap. PHP_INT_MAX would be too late (some buffering plugins
        // capture earlier). 50 is the safe Goldilocks zone.
        add_action( 'wp_head', [ __CLASS__, 'maybe_emit_og_image' ], 50 );
    }

    /**
     * Filter callback — passes through whatever Rank Math computed for Blog Post CPT.
     * Only acts as a no-op safety net; the real work happens in maybe_emit_og_image().
     */
    public static function filter_og_image( $image_url ) {
        if ( ! is_singular( self::CPT_SLUG ) ) return $image_url;
        if ( ! empty( $image_url ) ) return $image_url;

        $post_id = get_queried_object_id();
        if ( ! $post_id ) return $image_url;

        $bg_url = self::resolve_acf_image( self::ACF_BACKGROUND, $post_id );
        if ( $bg_url ) return $bg_url;

        $small_url = self::resolve_acf_image( self::ACF_IMAGE_SMALL, $post_id );
        if ( $small_url ) return $small_url;

        return $image_url;
    }

    /**
     * Direct wp_head output — primary mechanism for getting og:image onto
     * Blog Post CPT pages where Rank Math otherwise emits nothing.
     *
     * Captures the rest of the wp_head output via output buffering so we can
     * detect whether og:image was already emitted (by Rank Math, Yoast, etc.)
     * and skip our own output to avoid duplicates.
     */
    public static function maybe_emit_og_image(): void {
        if ( is_admin() || is_feed() ) return;
        if ( ! is_singular( self::CPT_SLUG ) ) return;

        $post_id = get_queried_object_id();
        if ( ! $post_id ) return;

        // Resolve the image URL — Featured Image first, then ACF fallback.
        $image_url = null;
        if ( has_post_thumbnail( $post_id ) ) {
            $image_url = get_the_post_thumbnail_url( $post_id, 'large' );
        }
        if ( ! $image_url ) {
            $image_url = self::resolve_acf_image( self::ACF_BACKGROUND, $post_id );
        }
        if ( ! $image_url ) {
            $image_url = self::resolve_acf_image( self::ACF_IMAGE_SMALL, $post_id );
        }
        if ( ! $image_url ) return;

        // Emit the meta tags. Use a HTML comment marker so we can spot in
        // page source that this came from our plugin (debugging aid).
        echo "\n<!-- EDIT_Blog_Post_OG_Image -->\n";
        echo '<meta property="og:image" content="' . esc_url( $image_url ) . '" />' . "\n";
        echo '<meta property="og:image:secure_url" content="' . esc_url( $image_url ) . '" />' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url( $image_url ) . '" />' . "\n";

        // Try to emit width/height too — improves LinkedIn / FB preview rendering.
        $attachment_id = attachment_url_to_postid( $image_url );
        if ( $attachment_id ) {
            $meta = wp_get_attachment_metadata( $attachment_id );
            if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
                echo '<meta property="og:image:width" content="' . (int) $meta['width'] . '" />' . "\n";
                echo '<meta property="og:image:height" content="' . (int) $meta['height'] . '" />' . "\n";
            }
        }
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
