<?php
/**
 * 404 Redirects — auto-redirects WordPress preview/draft URL patterns
 *
 * Handles two types of 404 URLs found in Ahrefs audit (Apr 2026):
 *
 * Type 2 — WordPress preview URLs (post type + ID combos that no longer exist):
 *   /?post_type=formacao&p=XXXXX  → /formacao/
 *   /?post_type=curso&p=XXXXX     → /formacao/
 *   /?post_type=entrevista&p=XXX  → /noticias/
 *
 * Type 3 — Generic deleted post IDs:
 *   /?p=XXXXX                     → / (homepage, unknown post type)
 *
 * All redirects are 301 Permanent.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_404_Redirects {

    public static function init() {
        add_action( 'template_redirect', [ __CLASS__, 'handle' ], 1 );
    }

    public static function handle() {
        if ( ! is_404() ) return;

        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
        $p         = isset( $_GET['p'] ) ? (int) $_GET['p'] : 0;

        // Type 2: /?post_type=formacao|curso|course&p=XXXXX
        if ( $p && in_array( $post_type, [ 'formacao', 'curso', 'course' ], true ) ) {
            wp_safe_redirect( home_url( '/formacao/' ), 301 );
            exit;
        }

        // Type 2b: /?post_type=entrevista&p=XXXXX
        if ( $p && $post_type === 'entrevista' ) {
            wp_safe_redirect( home_url( '/noticias/' ), 301 );
            exit;
        }

        // Type 3: /?p=XXXXX with no post_type (deleted post, unknown type → homepage)
        if ( $p && ! $post_type ) {
            wp_safe_redirect( home_url( '/' ), 301 );
            exit;
        }
    }
}
