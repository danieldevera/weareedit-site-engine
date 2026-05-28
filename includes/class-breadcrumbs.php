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
     * CPT slug → [label, archive URL]. The URL is hardcoded because most of these
     * CPTs in the formacao theme don't have `has_archive` enabled — /formacao/,
     * /eventos/, /noticias/, etc. are static WordPress Pages with the same slugs,
     * not WP_Query CPT archives. get_post_type_archive_link() returns empty for
     * those, so we fall back to a fixed URL.
     */
    const CPT_INFO = [
        'formacao'    => [ 'label' => 'Formação',    'url' => '/formacao/' ],
        'eventos'     => [ 'label' => 'Eventos',     'url' => '/eventos/' ],
        'noticias'    => [ 'label' => 'Notícias',    'url' => '/noticias/' ],
        'post'        => [ 'label' => 'Notícias',    'url' => '/noticias/' ],
        'entrevistas' => [ 'label' => 'Entrevistas', 'url' => '/entrevistas/' ],
        'profissoes'  => [ 'label' => 'Profissões',  'url' => '/profissoes/' ],
        'equipa'      => [ 'label' => 'Equipa',      'url' => '/equipa/' ],
    ];

    public static function init() {
        // Replace Rank Math's broken BreadcrumbList with ours via its json_ld filter.
        add_filter( 'rank_math/json_ld', [ __CLASS__, 'strip_rank_math_breadcrumb' ], 99, 2 );

        // Emit our own schema in wp_head (priority 7 — after edit-profiles' priority 6).
        add_action( 'wp_head', [ __CLASS__, 'emit_schema' ], 7 );

        // Inline CSS for the visual breadcrumb bar.
        add_action( 'wp_head', [ __CLASS__, 'emit_styles' ], 8 );

        // Inject visual HTML into the output buffer (after the theme's </header> + #masthead).
        add_filter( 'weareedit_site_engine_output_buffer', [ __CLASS__, 'inject_html' ], 5 );
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
     * Inline CSS for the visual bar. Kept small + scoped — under 1KB minified.
     */
    public static function emit_styles(): void {
        if ( is_admin() || is_feed() || is_404() ) return;
        if ( is_front_page() || is_home() ) return;
        ?>
<style id="edit-breadcrumbs-css">
.edit-breadcrumbs{background:#0a0a0a;border-bottom:1px solid rgba(255,255,255,.06);padding:24px 60px 14px;font-size:13px;line-height:1.4;color:rgba(255,255,255,.55);font-family:inherit;position:relative;z-index:1}
.edit-breadcrumbs ol{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:0}
.edit-breadcrumbs li{display:inline-flex;align-items:center}
.edit-breadcrumbs li:not(:last-child)::after{content:'›';color:rgba(255,255,255,.35);margin:0 10px;font-size:14px}
.edit-breadcrumbs a{color:rgba(255,255,255,.7);text-decoration:none;transition:color .15s ease}
.edit-breadcrumbs a:hover{color:#ffdd06}
.edit-breadcrumbs li:first-child a{color:#ffdd06}
.edit-breadcrumbs li:first-child a:hover{color:#fff}
.edit-breadcrumbs [aria-current="page"]{color:#fff;font-weight:500}
@media (max-width:768px){.edit-breadcrumbs{padding:18px 20px 10px;font-size:12px}.edit-breadcrumbs li:not(:last-child)::after{margin:0 6px}}
</style>
        <?php
    }

    /**
     * Inject visible breadcrumb HTML right before the theme's masthead/hero wrapper.
     * Anchor: `<!-- #masthead -->` comment emitted by the formacao theme's header.php.
     */
    public static function inject_html( string $html ): string {
        if ( is_admin() || is_feed() || is_404() ) return $html;
        if ( is_front_page() || is_home() ) return $html;

        $bc = self::render_html();
        if ( $bc === '' ) return $html;

        // Theme emits `</header>\n<!-- #masthead -->` right before the hero on
        // single + archive pages. Inject our HTML after that comment so it lands
        // between the sticky nav and the dark hero band.
        $anchor = '<!-- #masthead -->';
        $pos    = strpos( $html, $anchor );
        if ( $pos === false ) return $html;

        return substr_replace( $html, $anchor . "\n" . $bc, $pos, strlen( $anchor ) );
    }

    /**
     * Render the visible breadcrumb HTML. Last item is the current page (no link).
     */
    private static function render_html(): string {
        $items = self::build_trail();
        if ( count( $items ) < 2 ) return '';

        $last = count( $items ) - 1;
        $out  = '<nav class="edit-breadcrumbs" aria-label="Breadcrumb"><ol>';
        foreach ( $items as $i => $it ) {
            if ( $i === $last ) {
                $out .= '<li><span aria-current="page">' . esc_html( $it['name'] ) . '</span></li>';
            } else {
                $out .= '<li><a href="' . esc_url( $it['url'] ) . '">' . esc_html( $it['name'] ) . '</a></li>';
            }
        }
        $out .= '</ol></nav>';
        return $out;
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
            if ( isset( self::CPT_INFO[ $pt ] ) ) {
                $info = self::CPT_INFO[ $pt ];
                $trail[] = [
                    'name' => $info['label'],
                    'url'  => home_url( $info['url'] ),
                ];
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
            if ( isset( self::CPT_INFO[ $pt ] ) ) {
                $info = self::CPT_INFO[ $pt ];
                $trail[] = [
                    'name' => $info['label'],
                    'url'  => home_url( $info['url'] ),
                ];
            } else {
                $trail[] = [
                    'name' => post_type_archive_title( '', false ),
                    'url'  => get_post_type_archive_link( $pt ),
                ];
            }
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
