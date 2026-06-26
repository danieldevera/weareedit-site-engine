<?php
/**
 * Search AJAX — replaces the theme's slow + incomplete `data_fetch` handler.
 *
 * Why this exists (audit 2026-05-27):
 *   - Theme's data_fetch handler returned 0 bytes for the query "Daniel Devera"
 *     even though /equipa/daniel-devera/ exists. The team profile post type
 *     wasn't included in its search.
 *   - Each AJAX call took ~9 SECONDS. Front-end debouncing can't compensate
 *     for a server response that slow; the backend itself was the bottleneck.
 *
 * Replacement strategy:
 *   - Remove all existing handlers for wp_ajax_data_fetch at 'init' (priority 999)
 *   - Register our own that uses a single WP_Query across all relevant CPTs
 *   - Output HTML in the same shape the theme's front-end expects
 *     (.resultadosPesquisa .postTypeSearch markup)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Search_Ajax {

    /**
     * Post types our search includes. Add more here if a new CPT needs to
     * appear in the autocomplete dropdown.
     */
    const POST_TYPES = [
        'post',           // Notícias / blog
        'page',
        'formacao',       // Courses (bootcamp, workshop, curso intensivo, etc.)
        'eventos',
        'noticias',
        'equipa',         // Team profiles — KEY MISSING ONE in the original handler
        'entrevistas',
        'profissoes',
    ];

    const MAX_RESULTS = 8;

    public static function init() {
        // Run late on `init` so the theme + other plugins have registered
        // their handlers before we wipe them.
        add_action( 'init', [ __CLASS__, 'replace_data_fetch_handler' ], 999 );
        // Title-only search filter — applied only when our handler runs
        // (audit 2026-05-27: WP_Query with default 's' scanned title +
        // content + excerpt across 8 post types and took ~9.2s. Limiting
        // to post_title cuts response time to ~150-400ms because content
        // LIKE is the slow part).
        add_filter( 'posts_search', [ __CLASS__, 'title_only_search' ], 10, 2 );
    }

    /**
     * Override WP_Query's search SQL to LIKE post_title only, but only
     * when our `weareedit_title_only` query arg is set. Other searches
     * on the site (Google, other plugins) get the default behaviour.
     */
    public static function title_only_search( $search, $wp_query ) {
        if ( ! $wp_query->get( 'weareedit_title_only' ) ) return $search;
        global $wpdb;
        $term = $wp_query->get( 's' );
        if ( ! $term ) return $search;
        $like = '%' . $wpdb->esc_like( $term ) . '%';
        return $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s ", $like );
    }

    public static function replace_data_fetch_handler() {
        remove_all_actions( 'wp_ajax_data_fetch' );
        remove_all_actions( 'wp_ajax_nopriv_data_fetch' );
        add_action( 'wp_ajax_data_fetch',        [ __CLASS__, 'handle' ] );
        add_action( 'wp_ajax_nopriv_data_fetch', [ __CLASS__, 'handle' ] );
    }

    /**
     * Transient cache TTL — keeps results in DB for 5 minutes so repeat
     * queries are instant. New content added during the window won't show
     * until cache expires; for autocomplete that's an acceptable trade-off.
     */
    const CACHE_TTL = 300;

    public static function handle() {
        $keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

        // 2-char minimum — covers common course-name abbreviations like
        // AI, UX, ML, DS. Lowered from 3 in v1.5.89.
        if ( strlen( $keyword ) < 2 ) {
            wp_die();
        }

        // Server-side cache. Repeats of the same query (different users, same
        // session, repeat visits) hit the transient and skip WP_Query entirely.
        $cache_key = 'edit_search_v1_' . md5( strtolower( $keyword ) );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            // Log even cache hits — popular repeat queries skip WP_Query but
            // still represent real demand. Result count = number of rendered rows.
            if ( class_exists( 'EDIT_Search_Insights' ) ) {
                EDIT_Search_Insights::log( $keyword, substr_count( (string) $cached, '<h2>' ), (string) wp_get_referer() );
            }
            echo $cached;
            wp_die();
        }

        $post_types = array_filter( self::POST_TYPES, function ( $pt ) {
            return post_type_exists( $pt );
        } );

        $query = new WP_Query( [
            's'                      => $keyword,
            'post_type'              => array_values( $post_types ),
            'post_status'            => 'publish',
            'posts_per_page'         => self::MAX_RESULTS,
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            // Triggers title_only_search() filter above — cuts content LIKE scan.
            'weareedit_title_only'   => true,
        ] );

        ob_start();
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $label = self::get_post_type_label( get_post_type() );
                self::render_result( get_permalink(), get_the_title(), $label );
            }
            wp_reset_postdata();
        }
        $html = ob_get_clean();

        // Log the query + its result count (Search Insights). post_count is
        // capped at MAX_RESULTS, but only the 0-vs->0 distinction is relied on.
        if ( class_exists( 'EDIT_Search_Insights' ) ) {
            EDIT_Search_Insights::log( $keyword, (int) $query->post_count, (string) wp_get_referer() );
        }

        // Cache even empty results — a query that returns nothing should
        // also be instant on the second call.
        set_transient( $cache_key, $html, self::CACHE_TTL );

        echo $html;
        wp_die();
    }

    /**
     * Friendly label per post type — matches what the theme rendered as
     * <div class="postTypeSearch"> so visuals are consistent.
     */
    public static function get_post_type_label( string $post_type ): string {
        $map = [
            'post'        => 'Notícias',
            'page'        => 'Página',
            'formacao'    => 'Formação',
            'eventos'     => 'Evento',
            'noticias'    => 'Notícias',
            'equipa'      => 'Equipa',
            'entrevistas' => 'Entrevistas',
            'profissoes'  => 'Profissões',
        ];
        return $map[ $post_type ] ?? ucfirst( $post_type );
    }

    /**
     * One result row. Markup matches the structure the theme's CSS targets
     * (.resultadosPesquisa h2 a, .postTypeSearch). The original handler
     * emitted obsolete <font color="#ffffff"> tags; we keep them only
     * because the existing CSS selectors expect the `color` attribute.
     */
    private static function render_result( string $url, string $title, string $label ) {
        printf(
            '<h2><a href="%s"><font color="#ffffff">%s</font><font color="#ffffff"><div class="postTypeSearch">%s</div></font></a></h2>',
            esc_url( $url ),
            esc_html( $title ),
            esc_html( $label )
        );
    }
}
