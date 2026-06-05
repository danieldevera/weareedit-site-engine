<?php
/**
 * Blog CPT — enable 'author' support.
 * ─────────────────────────────────────────────────────────────────────────────
 * The Blog Post CPT (post_type 'blog') ships without the 'author' supports
 * flag in the theme's CPT registration. This means:
 *   - WP doesn't expose the Author dropdown in the editor UI
 *   - post_author gets stuck at whoever created the post (often the plugin
 *     user or the WP admin running an import)
 *   - LinkedIn / Twitter / schema.org Article author meta pulls the wrong name
 *
 * This filter runs at `register_post_type_args` priority 10 — fires when the
 * theme registers the CPT, before WP locks in the registration. We append
 * 'author' to the supports array, leaving everything else untouched.
 *
 * After this is live:
 *   - Editor → Opções deste ecrã (Screen Options) → tick "Autor"
 *   - Sidebar gets an Author dropdown — change to the correct user, save
 *   - Schema, OG tags, and LinkedIn previews follow automatically
 *
 * @since 1.5.287
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Blog_CPT_Author_Support {

    const CPT_SLUG = 'blog';

    public static function init(): void {
        add_filter( 'register_post_type_args', [ __CLASS__, 'add_author_support' ], 10, 2 );
    }

    public static function add_author_support( $args, $post_type ) {
        if ( $post_type !== self::CPT_SLUG ) return $args;

        // Normalise supports — could be false, missing, or an array.
        $supports = $args['supports'] ?? [];
        if ( ! is_array( $supports ) ) $supports = [];

        if ( ! in_array( 'author', $supports, true ) ) {
            $supports[] = 'author';
        }

        $args['supports'] = $supports;
        return $args;
    }
}
