<?php
/**
 * Pillar Cross-Links — site-wide internal linking from the existing content
 * graph (formacao archive + course singles) into the 5 new pillar hubs.
 *
 * Two injections:
 *  1. PILL BAR on /formacao/ — row of 5 chip-links at the top, one per pillar
 *  2. "FAZ PARTE DE:" BADGE on every course single, linking to the pillar(s)
 *     whose CATALOG constant contains that course's slug
 *
 * Strategy decided in the 2026-05-28 site-audit ("pillars are orphaned").
 * Reverse-lookup against pillar CATALOG constants is the authoritative way
 * to decide membership — it can't drift from what the pillar actually lists.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Pillar_Cross_Links {

    /**
     * Each pillar's label + URL slug + class name. Order = display order in the pill bar.
     */
    const PILLARS = [
        [ 'label' => 'Marketing Digital',       'slug' => 'marketing-digital',            'class' => 'EDIT_Marketing_Digital_Page' ],
        [ 'label' => 'Data Science',            'slug' => 'data-science',                 'class' => 'EDIT_Data_Science_Page' ],
        [ 'label' => 'UX/UI Design',            'slug' => 'curso-uxui-design',            'class' => 'EDIT_UX_UI_Design_Page' ],
        [ 'label' => 'Inteligência Artificial', 'slug' => 'curso-inteligencia-artificial','class' => 'EDIT_Inteligencia_Artificial_Page' ],
        [ 'label' => 'Programação',             'slug' => 'curso-programacao',            'class' => 'EDIT_Programacao_Page' ],
    ];

    /**
     * Per-pillar brand colour + block text colour + one-line description for the
     * "D · color-block" related-areas banner. Keyed by pillar slug.
     */
    const PILLAR_META = [
        'marketing-digital'             => [ 'acc' => '#ffdd06', 'tx' => '#0a0a0a', 'desc' => 'Paid media, performance, growth, SEO e analytics — a stack completa do marketing moderno.' ],
        'data-science'                  => [ 'acc' => '#f92869', 'tx' => '#ffffff', 'desc' => 'Data engineering, machine learning e analytics — transformar dados em decisões e produtos.' ],
        'curso-uxui-design'             => [ 'acc' => '#60c5b3', 'tx' => '#0a0a0a', 'desc' => 'Research, interação e interface — desenhar produtos digitais que as pessoas usam.' ],
        'curso-inteligencia-artificial' => [ 'acc' => '#ec8172', 'tx' => '#0a0a0a', 'desc' => 'IA generativa, agentes, machine learning e IA aplicada — a camada transversal de 2026.' ],
        'curso-programacao'             => [ 'acc' => '#0090eb', 'tx' => '#ffffff', 'desc' => 'Front-end, back-end e full-stack — construir produto real, não exercícios.' ],
    ];

    /**
     * ISOLATED LIVE TEST: the D color-block related-areas banner renders only on
     * this one course single for now, so we can iterate live before rolling it
     * out to all formacao singles. Change the slug to test elsewhere.
     */
    const BANNER_TEST_SLUG = 'bootcamp-generative-ai-aplicado-ao-marketing-digital';

    public static function init() {
        add_filter( 'weareedit_site_engine_output_buffer', [ __CLASS__, 'inject' ], 10 );
        add_action( 'wp_head', [ __CLASS__, 'emit_styles' ], 7 );
    }

    public static function emit_styles(): void {
        if ( is_admin() || is_feed() ) return;
        ?>
<style id="edit-pillar-cross-css">
.edit-pillar-pillbar{background:#0a0a0a;padding:24px 60px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;border-bottom:1px solid rgba(255,255,255,.06);}
.edit-pillar-pillbar__label{font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.5);font-weight:600;margin-right:8px;}
.edit-pillar-pillbar a{display:inline-flex;align-items:center;padding:8px 16px;border:1px solid rgba(255,255,255,.18);border-radius:999px;font-size:13px;color:rgba(255,255,255,.85);text-decoration:none;transition:background .15s ease,border-color .15s ease,color .15s ease;}
.edit-pillar-pillbar a:hover{background:#ffdd06;border-color:#ffdd06;color:#0a0a0a;}
.edit-pillar-pillbar a[aria-current="page"]{background:#ffdd06;border-color:#ffdd06;color:#0a0a0a;}
.edit-pillar-faz-parte{display:inline-flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0 16px;font-family:inherit;}
.edit-pillar-faz-parte__label{font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.55);font-weight:600;}
.edit-pillar-faz-parte a{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;background:rgba(255,221,6,.12);border:1px solid rgba(255,221,6,.35);color:#ffdd06;font-size:12px;text-decoration:none;transition:background .15s ease,color .15s ease;}
.edit-pillar-faz-parte a:hover{background:#ffdd06;color:#0a0a0a;}
@media (max-width:768px){.edit-pillar-pillbar{padding:16px 20px;gap:8px;}.edit-pillar-pillbar a{padding:6px 12px;font-size:12px;}.edit-pillar-pillbar__label{display:none;}}
</style>
        <?php
        // D · color-block banner CSS — only on the isolated test course single.
        $obj = get_queried_object();
        if ( ! ( $obj instanceof WP_Post ) || $obj->post_name !== self::BANNER_TEST_SLUG ) return;
        ?>
<style id="edit-pillar-banner-css">
.epb-banner{background:#e3e1db;padding:64px 24px;}
.epb-inner{max-width:1080px;margin:0 auto;padding:40px 40px;background:#f4f3ef;border:1px solid rgba(10,10,10,.12);}
.epb-kicker{display:inline-flex;align-items:center;gap:12px;font-size:11.5px;letter-spacing:.22em;text-transform:uppercase;color:rgba(10,10,10,.5);font-weight:700;margin:0 0 14px;}
.epb-kicker::before{content:"";width:28px;height:2px;background:#0a0a0a;}
.epb-head{font-size:clamp(24px,2.6vw,32px);line-height:1.05;letter-spacing:-.03em;font-weight:800;color:#0a0a0a;margin:0 0 28px;max-width:24ch;}
.epb-head em{font-family:Georgia,'Times New Roman',serif;font-style:italic;font-weight:400;color:#f92869;}
.epb-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;}
.epb-card{text-decoration:none;color:#0a0a0a;display:block;background:#fff;border:1px solid rgba(10,10,10,.12);transition:transform .24s ease,box-shadow .24s ease;}
.epb-card:hover{transform:translateY(-6px);box-shadow:0 34px 60px -28px rgba(10,10,10,.28);}
.epb-top{background:var(--acc);padding:18px 22px 16px;}
.epb-idx{font-size:40px;line-height:.85;font-weight:800;letter-spacing:-.05em;color:var(--tx);margin:0 0 6px;}
.epb-k{font-size:10px;letter-spacing:.16em;text-transform:uppercase;font-weight:800;color:var(--tx);opacity:.62;margin:0 0 8px;}
.epb-nm{font-size:21px;font-weight:800;letter-spacing:-.02em;margin:0;color:var(--tx);}
.epb-bd{padding:18px 22px 20px;}
.epb-ds{font-size:13.5px;line-height:1.5;color:rgba(10,10,10,.62);margin:0 0 16px;}
.epb-foot{display:flex;align-items:center;justify-content:space-between;padding-top:16px;border-top:1px solid rgba(10,10,10,.12);}
.epb-cnt{font-size:12.5px;font-weight:600;color:rgba(10,10,10,.5);}
.epb-cta{font-size:13px;font-weight:800;display:inline-flex;gap:8px;align-items:center;color:#0a0a0a;}
</style>
        <?php
    }

    public static function inject( string $html ): string {
        if ( is_post_type_archive( 'formacao' ) || is_page( 'formacao' ) ) {
            $html = self::inject_pillbar( $html );
        }
        if ( is_singular( 'formacao' ) ) {
            $html = self::inject_faz_parte( $html );
            $html = self::inject_pillar_banner( $html );
        }
        return $html;
    }

    /**
     * Inject the 5-pillar pill bar at the top of /formacao/ archive, right
     * after the masthead anchor (same anchor the breadcrumb class uses).
     */
    private static function inject_pillbar( string $html ): string {
        if ( strpos( $html, 'edit-pillar-pillbar' ) !== false ) return $html;

        $current_path = parse_url( home_url( add_query_arg( null, null ) ), PHP_URL_PATH );
        $bar  = '<div class="edit-pillar-pillbar" aria-label="Áreas de formação">';
        $bar .= '<span class="edit-pillar-pillbar__label">Áreas:</span>';
        foreach ( self::PILLARS as $p ) {
            $url     = home_url( '/' . $p['slug'] . '/' );
            $current = ( $current_path === '/' . $p['slug'] . '/' );
            $aria    = $current ? ' aria-current="page"' : '';
            $bar    .= sprintf(
                '<a href="%s"%s>%s</a>',
                esc_url( $url ),
                $aria,
                esc_html( $p['label'] )
            );
        }
        $bar .= '</div>';

        // Place right after the theme's masthead anchor so it lands between
        // the sticky nav and the page hero.
        $anchor = '<!-- #masthead -->';
        $pos    = strpos( $html, $anchor );
        if ( $pos === false ) return $html;

        return substr_replace( $html, $anchor . "\n" . $bar, $pos, strlen( $anchor ) );
    }

    /**
     * Inject "Faz parte de: [Pillar Name]" badge inside the .head wrapper of
     * a course single — right under the dgert pill / promo labels, just above
     * the H1 area. Reverse-lookup: scan each pillar's CATALOG constant.
     */
    private static function inject_faz_parte( string $html ): string {
        if ( strpos( $html, 'edit-pillar-faz-parte' ) !== false ) return $html;

        $post = get_queried_object();
        if ( ! $post instanceof WP_Post ) return $html;
        $slug = $post->post_name;

        $hits = [];
        foreach ( self::PILLARS as $p ) {
            if ( ! class_exists( $p['class'] ) ) continue;
            $catalog = call_user_func( [ $p['class'], 'get_catalog_flat' ] ) ?: self::flatten_catalog( $p['class'] );
            if ( in_array( $slug, $catalog, true ) ) {
                $hits[] = $p;
            }
        }
        if ( empty( $hits ) ) return $html;

        $badge  = '<div class="edit-pillar-faz-parte">';
        $badge .= '<span class="edit-pillar-faz-parte__label">Faz parte de:</span>';
        foreach ( $hits as $p ) {
            $badge .= sprintf(
                '<a href="%s">↗ %s</a>',
                esc_url( home_url( '/' . $p['slug'] . '/' ) ),
                esc_html( $p['label'] )
            );
        }
        $badge .= '</div>';

        // Inject right before the course-promo-code container, falling back to
        // before <h1 if that's missing.
        $anchor = '<div class="special-labels-container';
        $pos    = strpos( $html, $anchor );
        if ( $pos === false ) {
            $anchor = '<h1';
            $pos    = strpos( $html, $anchor );
        }
        if ( $pos === false ) return $html;

        return substr_replace( $html, $badge . $anchor, $pos, strlen( $anchor ) );
    }

    /**
     * D · color-block related-areas banner. ISOLATED to BANNER_TEST_SLUG for now.
     * Reverse-looks-up which pillar(s) list this course, then renders one
     * color-block card per parent pillar (with brand colour + program count).
     */
    private static function inject_pillar_banner( string $html ): string {
        $post = get_queried_object();
        if ( ! $post instanceof WP_Post ) return $html;
        if ( $post->post_name !== self::BANNER_TEST_SLUG ) return $html;
        if ( strpos( $html, 'epb-banner' ) !== false ) return $html;

        $hits = [];
        foreach ( self::PILLARS as $p ) {
            if ( ! class_exists( $p['class'] ) ) continue;
            $catalog = self::flatten_catalog( $p['class'] );
            if ( in_array( $post->post_name, $catalog, true ) ) {
                $hits[] = [ 'p' => $p, 'count' => count( $catalog ) ];
            }
        }
        if ( empty( $hits ) ) return $html;

        $cards = '';
        $i = 0;
        foreach ( $hits as $hit ) {
            $i++;
            $p    = $hit['p'];
            $meta = self::PILLAR_META[ $p['slug'] ] ?? [ 'acc' => '#ffdd06', 'tx' => '#0a0a0a', 'desc' => '' ];
            $cards .= sprintf(
                '<a class="epb-card" href="%s" style="--acc:%s;--tx:%s;">'
                . '<div class="epb-top"><p class="epb-idx">%02d</p><p class="epb-k">Área de Formação</p><h3 class="epb-nm">%s</h3></div>'
                . '<div class="epb-bd"><p class="epb-ds">%s</p>'
                . '<div class="epb-foot"><span class="epb-cnt">%d programas</span><span class="epb-cta">Ver todos <span aria-hidden="true">→</span></span></div></div></a>',
                esc_url( home_url( '/' . $p['slug'] . '/' ) ),
                esc_attr( $meta['acc'] ), esc_attr( $meta['tx'] ),
                $i, esc_html( $p['label'] ), esc_html( $meta['desc'] ), (int) $hit['count']
            );
        }

        $banner = '<section class="epb-banner"><div class="epb-inner">'
            . '<p class="epb-kicker">Esta formação faz parte de</p>'
            . '<h2 class="epb-head">Explore as <em>áreas</em> a que este programa pertence.</h2>'
            . '<div class="epb-grid">' . $cards . '</div></div></section>';

        // Place at the bottom of the course content, just before the footer strip.
        $anchor = '<div class="footer-newsletter">';
        $pos    = strpos( $html, $anchor );
        if ( $pos === false ) return $html;

        return substr_replace( $html, $banner . $anchor, $pos, strlen( $anchor ) );
    }

    /**
     * Flatten a pillar class's CATALOG constant into a single array of slugs.
     */
    private static function flatten_catalog( string $class ): array {
        if ( ! defined( $class . '::CATALOG' ) ) return [];
        $catalog = constant( $class . '::CATALOG' );
        $flat = [];
        foreach ( $catalog as $group ) {
            foreach ( $group as $slug ) $flat[] = $slug;
        }
        return $flat;
    }
}
