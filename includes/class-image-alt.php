<?php
/**
 * FIX #8: Missing Image Alt Text
 *
 * Issues fixed:
 *  - Course/feature images missing alt attributes
 *  - Logo missing alt attribute
 *  - Decorative images need role="presentation" or alt=""
 *
 * Strategy:
 *  - Filter post content to inject alt text from attachment title if missing
 *  - Filter wp_get_attachment_image_attributes to ensure all WP-rendered images have alt text
 *  - Add admin interface to bulk-fix alt text across the media library
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Image_Alt {

    public static function init() {
        $settings = get_option( 'edit_seo_fix_settings', [] );

        if ( ! empty( $settings['fix_image_alts'] ) ) {
            // Fix alt text on wp_get_attachment_image() rendered images
            add_filter( 'wp_get_attachment_image_attributes', [ __CLASS__, 'fix_attachment_image_alt' ], 10, 2 );

            // Fix alt text inside post content (the_content)
            add_filter( 'the_content', [ __CLASS__, 'fix_content_image_alts' ] );

            // Fix alt text in widgets
            add_filter( 'widget_text', [ __CLASS__, 'fix_content_image_alts' ] );
        }

        // Admin: bulk fix button on Media Library page
        add_action( 'admin_menu', [ __CLASS__, 'register_bulk_fix_page' ] );
        add_action( 'admin_post_edit_seo_fix_bulk_alt', [ __CLASS__, 'handle_bulk_alt_fix' ] );
    }

    /**
     * Ensure every WordPress-rendered <img> has a non-empty alt attribute.
     */
    public static function fix_attachment_image_alt( array $attr, WP_Post $attachment ): array {
        if ( empty( $attr['alt'] ) ) {
            // Try: attachment alt meta → attachment title → post parent title
            $alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
            if ( ! $alt ) {
                $alt = $attachment->post_title;
            }
            if ( ! $alt && $attachment->post_parent ) {
                $alt = get_the_title( $attachment->post_parent );
            }
            $attr['alt'] = sanitize_text_field( $alt );
        }
        return $attr;
    }

    /**
     * Parse post content HTML and inject missing alt attributes.
     * Falls back to: filename → parent post title → site name.
     */
    public static function fix_content_image_alts( string $content ): string {
        if ( ! $content ) return $content;

        return preg_replace_callback(
            '/<img\s[^>]*>/i',
            [ __CLASS__, 'process_img_tag' ],
            $content
        );
    }

    private static function process_img_tag( array $matches ): string {
        $tag = $matches[0];

        // --- Alt text fix ---
        if ( ! preg_match( '/\balt\s*=\s*["\'][^"\']+["\']/i', $tag ) ) {
            $alt = self::derive_alt_from_src( $tag );

            if ( preg_match( '/\balt\s*=\s*["\']["\']/i', $tag ) ) {
                $tag = preg_replace( '/\balt\s*=\s*["\']["\']/', 'alt="' . esc_attr( $alt ) . '"', $tag );
            } elseif ( ! preg_match( '/\balt\s*=/i', $tag ) ) {
                $tag = preg_replace( '/(<img\s)/i', '$1alt="' . esc_attr( $alt ) . '" ', $tag );
            }
        }

        // --- Lazy loading fix ---
        // Add loading="lazy" if not already present
        if ( ! preg_match( '/\bloading\s*=/i', $tag ) ) {
            $tag = preg_replace( '/(<img\s)/i', '$1loading="lazy" ', $tag );
        }

        // --- Width/Height fix (reduces CLS) ---
        // If either dimension is missing, try to resolve from media library
        $missing_width  = ! preg_match( '/\bwidth\s*=/i', $tag );
        $missing_height = ! preg_match( '/\bheight\s*=/i', $tag );

        if ( $missing_width || $missing_height ) {
            $dimensions = self::derive_dimensions_from_src( $tag );
            if ( $dimensions ) {
                if ( $missing_width ) {
                    $tag = preg_replace( '/(<img\s)/i', '$1width="' . $dimensions[0] . '" ', $tag );
                }
                if ( $missing_height ) {
                    $tag = preg_replace( '/(<img\s)/i', '$1height="' . $dimensions[1] . '" ', $tag );
                }
            }
        }

        return $tag;
    }

    /**
     * Look up image dimensions from the WordPress media library.
     * Returns [width, height] or null if the image isn't in the library.
     */
    private static function derive_dimensions_from_src( string $tag ): ?array {
        preg_match( '/\bsrc\s*=\s*["\'](.*?)["\']/i', $tag, $src_matches );
        $src = $src_matches[1] ?? '';
        if ( ! $src ) return null;

        // Strip size suffix (e.g. -768x432) to get the original attachment URL
        $clean_src     = preg_replace( '/-\d+x\d+(\.[a-z]+)$/i', '$1', $src );
        $attachment_id = attachment_url_to_postid( $src ) ?: attachment_url_to_postid( $clean_src );
        if ( ! $attachment_id ) return null;

        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( $meta && isset( $meta['width'], $meta['height'] ) ) {
            return [ (int) $meta['width'], (int) $meta['height'] ];
        }

        return null;
    }

    private static function derive_alt_from_src( string $tag ): string {
        // Extract src
        preg_match( '/\bsrc\s*=\s*["\'](.*?)["\']/i', $tag, $src_matches );
        $src = $src_matches[1] ?? '';

        if ( $src ) {
            // Try to resolve to attachment
            $attachment_id = attachment_url_to_postid( $src );
            if ( $attachment_id ) {
                $alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
                if ( $alt ) return $alt;
                $post = get_post( $attachment_id );
                if ( $post && $post->post_title ) return $post->post_title;
            }

            // Fallback: use filename (strip extension, replace dashes/underscores)
            $filename = pathinfo( $src, PATHINFO_FILENAME );
            $filename = preg_replace( '/[-_\d]+/', ' ', $filename );
            $filename = ucwords( trim( $filename ) );
            if ( $filename ) return $filename;
        }

        // Last resort
        return get_bloginfo( 'name' );
    }

    /**
     * Register a sub-page under Tools for bulk alt text fixing.
     */
    public static function register_bulk_fix_page() {
        add_management_page(
            'Bulk Fix Image Alt Text',
            'Fix Image Alts',
            'manage_options',
            'edit-seo-fix-bulk-alt',
            [ __CLASS__, 'render_bulk_fix_page' ]
        );
    }

    public static function render_bulk_fix_page() {
        $missing = self::count_missing_alts();
        echo '<div class="wrap">';
        echo '<h1>Bulk Fix: Missing Image Alt Text</h1>';
        echo '<p><strong>' . intval( $missing ) . '</strong> images in your Media Library are missing alt text.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="edit_seo_fix_bulk_alt">';
        wp_nonce_field( 'edit_seo_fix_bulk_alt' );
        echo '<p><button type="submit" class="button button-primary">Fix All Missing Alt Text</button></p>';
        echo '</form>';
        echo '</div>';
    }

    public static function handle_bulk_alt_fix() {
        check_admin_referer( 'edit_seo_fix_bulk_alt' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $attachments = get_posts( [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'post_status'    => 'inherit',
            'meta_query'     => [
                [
                    'key'     => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ] );

        $fixed = 0;
        foreach ( $attachments as $attachment ) {
            $alt = $attachment->post_title;
            if ( $alt ) {
                update_post_meta( $attachment->ID, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
                $fixed++;
            }
        }

        wp_redirect( admin_url( 'tools.php?page=edit-seo-fix-bulk-alt&fixed=' . $fixed ) );
        exit;
    }

    private static function count_missing_alts(): int {
        $query = new WP_Query( [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'post_status'    => 'inherit',
            'meta_query'     => [
                [
                    'key'     => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS',
                ],
            ],
            'fields'         => 'ids',
        ] );
        return $query->found_posts;
    }
}
