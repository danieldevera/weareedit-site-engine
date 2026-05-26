<?php
/**
 * FIX #11: Hreflang Tags
 *
 * Issues fixed:
 *  - No hreflang tags found on any page
 *  - x-default hreflang missing
 *
 * This class outputs hreflang link tags based on configured language mappings.
 * If WPML or Polylang is active, it integrates with those instead of using
 * manual configuration.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Hreflang {

    public static function init() {
        add_action( 'wp_head', [ __CLASS__, 'output_hreflang' ], 6 );
    }

    public static function output_hreflang() {
        // If WPML is active, let it handle hreflang
        if ( defined( 'ICL_SITEPRESS_VERSION' ) ) return;

        // If Polylang is active, let it handle hreflang
        if ( function_exists( 'pll_the_languages' ) ) return;

        // NOTE: Rank Math FREE does not support hreflang — do not skip for Rank Math.
        // Only skip if a dedicated multilingual plugin is active.

        $settings  = get_option( 'edit_seo_fix_settings', [] );
        $hreflang  = $settings['hreflang_mappings'] ?? self::default_mappings();

        if ( empty( $hreflang ) ) return;

        $current_url = EDIT_Canonical::get_canonical_url();
        if ( ! $current_url ) return;

        echo "\n<!-- EDIT. SEO Fix: Hreflang -->\n";

        foreach ( $hreflang as $lang => $base_url ) {
            // For single-language sites, all languages point to the same URL structure
            $url = self::build_hreflang_url( $current_url, $base_url );
            echo '<link rel="alternate" hreflang="' . esc_attr( $lang ) . '" href="' . esc_url( $url ) . '">' . "\n";
        }

        // x-default should point to the primary version
        $default_url = self::build_hreflang_url( $current_url, home_url() );
        echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $default_url ) . '">' . "\n";

        echo "<!-- /EDIT. SEO Fix: Hreflang -->\n";
    }

    /**
     * Default hreflang mappings for weareedit.io.
     * Site is pt-PT only — no BR or ES variants.
     */
    private static function default_mappings(): array {
        return [ 'pt-PT' => home_url() ];
    }

    /**
     * Convert a canonical URL to the equivalent URL under a different base.
     * For single-language sites this is a no-op, but it correctly handles
     * subdomain-based multilingual setups.
     */
    private static function build_hreflang_url( string $current_url, string $base_url ): string {
        $home    = rtrim( home_url(), '/' );
        $base    = rtrim( $base_url, '/' );
        $current = rtrim( $current_url, '/' );

        if ( $home === $base ) {
            return trailingslashit( $current );
        }

        $path = str_replace( $home, '', $current );
        return trailingslashit( $base . $path );
    }
}
