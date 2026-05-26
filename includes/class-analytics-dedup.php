<?php
/**
 * FIX #12: Duplicate Analytics / Tracking Code
 *
 * Issues fixed:
 *  - Both Universal Analytics (UA-23379783-1) and GA4 (G-R11CP4ELEH) firing simultaneously
 *  - GTM (GTM-TSP85L) also injecting GA, causing triple-counting
 *
 * Safe strategy (no output buffering — avoids regex backtracking on large script blocks):
 *  - Dequeue any WordPress-registered legacy GA script handles
 *  - UA removal via Rank Math is handled by admin instructions (in the settings page)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Analytics_Dedup {

    public static function init() {
        $settings = get_option( 'edit_seo_fix_settings', [] );

        if ( empty( $settings['fix_duplicate_ga'] ) ) return;

        // Dequeue/deregister any WordPress-registered duplicate GA script handles
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'dequeue_legacy_analytics' ], 100 );
    }

    /**
     * Dequeue known legacy/duplicate analytics script handles.
     * Only targets scripts registered via wp_enqueue_script() — safe, no buffering.
     */
    public static function dequeue_legacy_analytics() {
        $legacy_handles = [
            'google-analytics',
            'ua-analytics',
            'monsterinsights-js',
            'monsterinsights-frontend',
            'analytify-tracking',
        ];

        foreach ( $legacy_handles as $handle ) {
            wp_dequeue_script( $handle );
            wp_deregister_script( $handle );
        }
    }
}
