<?php
/**
 * Breadcrumbs — site-wide BreadcrumbList JSON-LD emitter.
 *
 * Why this exists (audit 2026-05-27):
 *   - Rank Math's default BreadcrumbList is incomplete: "Home" instead of "Início",
 *     string positions instead of int, and no CPT archive intermediate (Início → Page
 *     instead of Início → Formação → Page).
 *   - edit-profiles plugin already emits a proper one for /equipa/* — we leave that alone.
 *
 * Strategy:
 *   1. Strip Rank Math's BreadcrumbList from the JSON-LD graph for all pages (we replace it).
 *   2. Emit our own BreadcrumbList in wp_head for CPT singles, archives, taxonomies, and pages.
 *   3. Skip /equipa/* — edit-profiles owns it.
 *
 * Visual breadcrumbs (HTML rendering) are out of scope for v1 — schema-only ships the
 * rich-snippet eligibility, which is 80% of the SEO value.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Breadcrumbs {

    /**
     * CPT slug → human label (PT-PT). The label drives both the archive name
     * and the intermediate breadcrumb item for that CPT's singles.
     */
    const CPT_LABELS = [
        'formacao'    => 'Formação',
        'eventos'     => 'Eventos',
        'noticias'    => 'Notícias',
        'post'        => 'Notícias',
        'entrevistas' => 'Entrevistas',
        'profissoes'  => 'Profissões',
    ];

    public static function init() {
        // Replace Rank Math's broken BreadcrumbList with ours via its json_ld filter.
        add_filter( 'rank_math/json_ld', [ __CLASS__, 'strip_rank_math_breadcrumb' ], 99, 2 );

        // Emit our own schema in wp_head (priority 7 — after edit-profiles' priority 6).
        add_action( 'wp_head', [ __CLASS__, 'emit_schema' ], 7 );
    }

    /**
     * Remove Rank Math's BreadcrumbList from its JSON-LD graph. Leaves other
     * schema types (WebPage, Article, Course, etc.) untouched.
     */
    public static function strip_rank_math_breadcrumb( $data, $jsonld ) {
        if ( ! is_array( $data ) ) return $data;
        foreach ( $data as $key => $node ) {
            if ( is_array( $node ) && ( $node['@type'] ?? '' ) === 'BreadcrumbList' ) {
                unset( $data[ $key ] );
            }
        }
        return $data;
    }

    /**
     * Emit BreadcrumbList JSON-LD. Skips:
     *   - Home (no breadcrumb)
     *   - /equipa/* (edit-profiles owns it)
     *   - admin / feed / sitemap requests
     */
    public static function emit_schema(): void {
        if ( is_admin() || is_feed() || is_404() ) return;
        if ( is_front_page() || is_home() ) return;

        // /equipa/* is owned by edit-profiles plugin. Don't duplicate.
        if ( is_singular( 'equipa' ) || is_post_type_archive( 'equipa' ) ) return;

        $items = self::build_trail();
        if ( count( $items ) < 2 ) return; // Need at least Home + 1 to be meaningful.

        $list = [];
        foreach ( $items as $i => $it ) {
            $list[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $it['name'],
                'item'     => $it['url'],
            ];
        }

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $list,
        ];

        echo "\n<script type=\"application/ld+json\">"
           . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
           . "</script>\n";
    }

    /**
     * Build the trail as an ordered array of [name, url] pairs. Home is always first.
     */
    private static function build_trail(): array {
        $home = [ 'name' => 'Início', 'url' => home_url( '/' ) ];
        $trail = [ $home ];

        // CPT single — add CPT archive intermediate + the post title.
        if ( is_singular() ) {
            $post = get_queried_object();
            if ( ! $post instanceof WP_Post ) return $trail;

            $pt = $post->post_type;
            if ( isset( self::CPT_LABELS[ $pt ] ) ) {
                $archive_url = get_post_type_archive_link( $pt );
                if ( $archive_url ) {
                    $trail[] = [ 'name' => self::CPT_LABELS[ $pt ], 'url' => $archive_url ];
                }
            }

            // Page parent hierarchy (for nested pages like /escola/sobre/).
            if ( $pt === 'page' && $post->post_parent ) {
                $ancestors = array_reverse( get_post_ancestors( $post ) );
                foreach ( $ancestors as $aid ) {
                    $trail[] = [
                        'name' => wp_strip_all_tags( get_the_title( $aid ) ),
                        'url'  => get_permalink( $aid ),
                    ];
                }
            }

            $trail[] = [
                'name' => wp_strip_all_tags( get_the_title( $post ) ),
                'url'  => get_permalink( $post ),
            ];
            return $trail;
        }

        // CPT archive (e.g. /formacao/, /eventos/).
        if ( is_post_type_archive() ) {
            $pt = get_query_var( 'post_type' );
            if ( is_array( $pt ) ) $pt = reset( $pt );
            $label = self::CPT_LABELS[ $pt ] ?? post_type_archive_title( '', false );
            $trail[] = [
                'name' => $label,
                'url'  => get_post_type_archive_link( $pt ),
            ];
            return $trail;
        }

        // Taxonomy term archive (categoria, etiqueta, áreas formação, etc.).
        if ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term ) {
                $trail[] = [
                    'name' => wp_strip_all_tags( $term->name ),
                    'url'  => get_term_link( $term ),
                ];
            }
            return $trail;
        }

        // Search results.
        if ( is_search() ) {
            $trail[] = [
                'name' => 'Resultados: ' . get_search_query(),
                'url'  => home_url( '/?s=' . urlencode( get_search_query() ) ),
            ];
            return $trail;
        }

        // Date archives — minimal trail.
        if ( is_year() || is_month() || is_day() ) {
            $trail[] = [
                'name' => wp_get_document_title(),
                'url'  => home_url( add_query_arg( null, null ) ),
            ];
            return $trail;
        }

        return $trail;
    }
}
