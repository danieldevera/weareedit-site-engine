<?php
/**
 * Search Index — generates a static JSON file with all searchable posts,
 * served directly by the webserver (no PHP / no WP bootstrap). Client
 * fetches it once, filters in JS. Sub-50ms response regardless of WP
 * admin-ajax.php overhead.
 *
 * Architecture (audit 2026-05-27):
 *   - Theme's admin-ajax data_fetch handler took 9.2s per query
 *   - v1.5.84 title-only filter cut to ~5s (still slow — WP boot overhead)
 *   - v1.5.87 transient cache made repeats instant (first query still slow)
 *   - v1.5.88 (this): static JSON index = first query also instant
 *
 * The JSON is regenerated on post save/update/delete via hooks, so it
 * stays fresh with no manual intervention.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Search_Index {

    const INDEX_FILENAME = 'edit-search-index.json';
    const INDEX_VERSION  = 1; // Bump if the JSON schema changes (forces client cache-bust)

    public static function init() {
        // Build on first load if missing.
        add_action( 'init', [ __CLASS__, 'ensure_index_exists' ], 999 );
        // Regenerate when content changes.
        add_action( 'save_post',              [ __CLASS__, 'regenerate_on_post_change' ], 20, 3 );
        add_action( 'deleted_post',           [ __CLASS__, 'regenerate' ] );
        add_action( 'transition_post_status', [ __CLASS__, 'regenerate_on_status' ], 10, 3 );
    }

    /**
     * Public URL where the static JSON lives. Served by the webserver
     * without invoking PHP — that's the entire point of this approach.
     */
    public static function get_index_url(): string {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['baseurl'] ) . self::INDEX_FILENAME;
    }

    private static function get_index_path(): string {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . self::INDEX_FILENAME;
    }

    public static function ensure_index_exists() {
        if ( ! file_exists( self::get_index_path() ) ) {
            self::regenerate();
        }
    }

    public static function regenerate_on_post_change( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! in_array( $post->post_type, EDIT_Search_Ajax::POST_TYPES, true ) ) return;
        self::regenerate();
    }

    public static function regenerate_on_status( $new, $old, $post ) {
        if ( $new === $old ) return;
        if ( ! in_array( $post->post_type, EDIT_Search_Ajax::POST_TYPES, true ) ) return;
        self::regenerate();
    }

    /**
     * Build the static JSON. Runs at most once per save_post event +
     * once on first hit. Direct DB query for speed (no get_permalink
     * loop — we construct permalinks via get_permalink in batch
     * because CPT URL structures vary per rewrite rule).
     */
    public static function regenerate() {
        global $wpdb;

        $post_types = array_filter( EDIT_Search_Ajax::POST_TYPES, 'post_type_exists' );
        if ( empty( $post_types ) ) return;

        $placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        $sql = "SELECT ID, post_title, post_type FROM {$wpdb->posts}
                WHERE post_status = 'publish'
                AND post_title != ''
                AND post_type IN ($placeholders)
                ORDER BY post_date DESC";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...array_values( $post_types ) ), ARRAY_A );

        if ( empty( $rows ) ) {
            file_put_contents( self::get_index_path(), '[]' );
            return;
        }

        $index = [];
        foreach ( $rows as $row ) {
            $permalink = get_permalink( $row['ID'] );
            if ( ! $permalink ) continue;
            $index[] = [
                't' => $row['post_title'],
                'u' => $permalink,
                'l' => EDIT_Search_Ajax::get_post_type_label( $row['post_type'] ),
            ];
        }

        $json = wp_json_encode( $index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( $json === false ) return;

        // Ensure upload dir exists. wp_upload_dir creates it on access but
        // be defensive in case of permission issues.
        $path = self::get_index_path();
        $dir  = dirname( $path );
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        file_put_contents( $path, $json );
    }
}
