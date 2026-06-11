<?php
/**
 * Pillar Courses — render course cards on pillar pages with PIXEL-IDENTICAL
 * markup to the /formacao/ archive.
 *
 * Strategy: scrape /formacao/ once, cache a slug→card-HTML map as a transient,
 * and replay each card's exact HTML on pillar pages. This guarantees the cards
 * match the theme's archive cards 1:1 — same promo labels, same styled title
 * (with <font color="#fff"> + </br>), same course-date, same hover transform,
 * same "Ver curso" reveal — without needing to know ACF field names.
 *
 * Cards are emitted inside .filter-result > .container > .row so the theme's
 * archive-only .filter-result .course-box rules (height, hover, padding) apply.
 *
 * Cache invalidates when any formacao post changes.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Pillar_Courses {

    const CACHE_KEY = 'edit_pillar_card_map_v3';
    const CACHE_TTL = HOUR_IN_SECONDS;

    public static function init(): void {
        add_action( 'save_post_formacao',     [ __CLASS__, 'invalidate_cache' ] );
        add_action( 'deleted_post',           [ __CLASS__, 'maybe_invalidate' ] );
        add_action( 'trashed_post',           [ __CLASS__, 'maybe_invalidate' ] );
        add_action( 'transition_post_status', [ __CLASS__, 'maybe_invalidate_status' ], 10, 3 );
    }

    public static function invalidate_cache(): void {
        delete_transient( self::CACHE_KEY );
    }

    public static function maybe_invalidate( $post_id ): void {
        if ( get_post_type( $post_id ) === 'formacao' ) self::invalidate_cache();
    }

    public static function maybe_invalidate_status( $new, $old, $post ): void {
        if ( isset( $post->post_type ) && $post->post_type === 'formacao' ) self::invalidate_cache();
    }

    public static function render_card( string $slug ): string {
        $map = self::get_card_map();
        if ( isset( $map[ $slug ] ) ) return self::rewrite_for_pillar( $map[ $slug ] );
        return self::render_fallback_card( $slug );
    }

    /**
     * Pillar-page card rewrite — neutralise WP Rocket lazy-load and bake the
     * SVG background-image into the inline style so the card renders with its
     * correct typology background (bootcamp pink / workshop teal / remote blue
     * / curso yellow) on the first paint, without depending on lazy-load JS
     * that doesn't reliably fire for HTML injected by the pillar shortcode.
     *
     * Preserves the data-bg attribute so other code paths (theme CSS, future
     * JS) keep working unchanged. Idempotent: cards without data-bg pass
     * through untouched.
     */
    private static function rewrite_for_pillar( string $html ): string {
        if ( ! preg_match( '/data-bg="([^"]+)"/', $html, $m ) ) return $html;
        $bg_url = $m[1];

        // Override upstream-mis-tagged cards. Some workshop + bootcamp posts
        // in WP have the wrong "Tipo Destaque" / formacao_tipo taxonomy,
        // resulting in data-bg pointing at bg-curso.svg (yellow) rather than
        // their correct typology background. Detect by URL pattern and force
        // the right SVG so the pillar renders the locked typology colours
        // (bootcamp pink / workshop teal) even when WP data is wrong.
        if ( preg_match( '#/formacao/(remote-learning-workshop-|workshop-)#', $html ) ) {
            $bg_url = home_url( '/wp-content/uploads/2015/05/workshop-bg-1.svg' );
        } elseif ( preg_match( '#/formacao/(bootcamp-|digital-marketing-foundations-bootcamp)#', $html ) ) {
            $bg_url = home_url( '/wp-content/uploads/2021/12/bootcamp-bg.svg' );
        }

        $bg_url = esc_url( $bg_url );
        $html = preg_replace( '/\s+rocket-lazyload/', '', $html );
        // Update both the data-bg attribute (for any downstream JS) and the
        // inline style so the SVG renders on first paint.
        $html = preg_replace(
            '/data-bg="[^"]+"/',
            'data-bg="' . $bg_url . '"',
            $html,
            1
        );
        $html = preg_replace(
            '/style=""/',
            'style="background-image:url(\'' . $bg_url . '\')"',
            $html,
            1
        );
        return $html;
    }

    private static function get_card_map(): array {
        $cached = get_transient( self::CACHE_KEY );
        if ( is_array( $cached ) && ! empty( $cached ) ) return $cached;

        $map = self::scrape_archive();
        if ( ! empty( $map ) ) {
            set_transient( self::CACHE_KEY, $map, self::CACHE_TTL );
        }
        return $map;
    }

    /**
     * Fetch /formacao/ and extract each course card's full HTML keyed by slug.
     * Each match is the outer <div class="course col-sm-6 col-md-4 ..."> wrapper
     * including the inner <a class="course-box"> with all theme decoration.
     */
    private static function scrape_archive(): array {
        $url = home_url( '/formacao/' );
        $res = wp_remote_get( $url, [
            'timeout'   => 8,
            'sslverify' => false,
            'headers'   => [ 'User-Agent' => 'edit-pillar-courses/1.0 (+weareedit.io)' ],
        ] );
        if ( is_wp_error( $res ) ) return [];
        if ( (int) wp_remote_retrieve_response_code( $res ) !== 200 ) return [];
        $body = wp_remote_retrieve_body( $res );
        if ( empty( $body ) ) return [];

        $map = [];
        $pattern = '#<div\s+data-category="[^"]*"\s+class="course\s+col-sm-6\s+col-md-4[^"]*"[^>]*>\s*<a[^>]*href="[^"]*?/formacao/([^/"]+)/?"[^>]*>.*?</a>\s*</div>#s';
        if ( preg_match_all( $pattern, $body, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $m ) {
                $slug = sanitize_title( $m[1] );
                if ( $slug && ! isset( $map[ $slug ] ) ) {
                    $map[ $slug ] = $m[0];
                }
            }
        }
        return $map;
    }

    /**
     * Minimal markup for slugs missing from the archive (e.g. unpublished,
     * archived, or hidden from the filter). Same outer wrapper so it fits
     * the same .row / col-md-4 grid as the real cards.
     */
    private static function render_fallback_card( string $slug ): string {
        $post = get_page_by_path( $slug, OBJECT, 'formacao' );
        if ( ! $post || $post->post_status !== 'publish' ) return '';

        $title = wp_strip_all_tags( get_the_title( $post ) );
        $url   = get_permalink( $post );

        $type_map = [
            'bootcamp'        => [ 'bg' => 'https://weareedit.io/wp-content/uploads/2021/12/bootcamp-bg.svg',    'icon' => 'https://weareedit.io/wp-content/uploads/2021/12/bootcamp-branco.svg',     'tag' => 'Bootcamp' ],
            'curso'           => [ 'bg' => 'https://weareedit.io/wp-content/uploads/2015/05/bg-curso.svg',       'icon' => 'https://weareedit.io/wp-content/uploads/2015/05/icon-curso.svg',          'tag' => 'Curso' ],
            'curso-intensivo' => [ 'bg' => 'https://weareedit.io/wp-content/uploads/2015/05/bg-curso.svg',       'icon' => 'https://weareedit.io/wp-content/uploads/2015/05/icon-curso.svg',          'tag' => 'Curso' ],
            'workshop'        => [ 'bg' => 'https://weareedit.io/wp-content/uploads/2015/05/workshop-bg-1.svg', 'icon' => 'https://weareedit.io/wp-content/uploads/2015/05/icon-workshop-preto.svg', 'tag' => 'Workshop' ],
            'remote-learning' => [ 'bg' => 'https://weareedit.io/wp-content/uploads/2020/03/bg-remote.svg',      'icon' => 'https://weareedit.io/wp-content/uploads/2020/03/icon-remote-learning-preto.svg', 'tag' => 'Remote' ],
        ];

        $type_key = 'curso';
        $terms    = get_the_terms( $post->ID, 'formacao_tipo' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                if ( isset( $type_map[ $t->slug ] ) ) { $type_key = $t->slug; break; }
            }
        }
        $cfg = $type_map[ $type_key ];

        ob_start();
        ?>
        <div data-category="" class="course col-sm-6 col-md-4" data-wow-duration="1s">
            <a data-bg="<?php echo esc_url( $cfg['bg'] ); ?>" href="<?php echo esc_url( $url ); ?>" class="course-box text-black" style="background-image:url('<?php echo esc_url( $cfg['bg'] ); ?>');">
                <div class="course-icon">
                    <img width="40" height="40" alt="<?php echo esc_attr( $cfg['tag'] ); ?>" src="<?php echo esc_url( $cfg['icon'] ); ?>">
                </div>
                <div class="course-text">
                    <h2 class="course-title"><?php echo esc_html( $title ); ?></h2>
                </div>
                <div class="course-view">
                    <span>Ver curso</span>
                </div>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
}
