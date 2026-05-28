<?php
/**
 * Pillar Courses — shared helper to render course cards on pillar pages
 * using the EXACT same markup + classes as /formacao/ archive.
 *
 * Per `feedback_theme_css_reuse.md`: reuse theme card styles instead of
 * writing custom CSS. Emits `<a class="course-box text-black">` with the
 * type-specific data-bg + icon SVGs the theme expects.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Pillar_Courses {

    /**
     * Map of formacao_tipo term slugs → [background URL, icon URL, text-color class].
     * Verified 2026-05-28 from /formacao/ archive markup.
     */
    const TYPE_MAP = [
        'bootcamp'         => [ 'bg' => 'https://weareedit.io/wp-content/uploads/2021/12/bootcamp-bg.svg',     'icon' => 'https://weareedit.io/wp-content/uploads/2021/12/bootcamp-branco.svg',           'text' => 'text-black' ],
        'curso'            => [ 'bg' => 'https://weareedit.io/wp-content/uploads/2015/05/bg-curso.svg',       'icon' => 'https://weareedit.io/wp-content/uploads/2015/05/icon-curso.svg',                'text' => 'text-black' ],
        'curso-intensivo'  => [ 'bg' => 'https://weareedit.io/wp-content/uploads/2015/05/bg-curso.svg',       'icon' => 'https://weareedit.io/wp-content/uploads/2015/05/icon-curso.svg',                'text' => 'text-black' ],
        'workshop'         => [ 'bg' => 'https://weareedit.io/wp-content/uploads/2015/05/workshop-bg-1.svg',  'icon' => 'https://weareedit.io/wp-content/uploads/2015/05/icon-workshop-preto.svg',       'text' => 'text-black' ],
        'remote-learning'  => [ 'bg' => 'https://weareedit.io/wp-content/uploads/2020/03/bg-remote.svg',      'icon' => 'https://weareedit.io/wp-content/uploads/2020/03/icon-remote-learning-preto.svg','text' => 'text-black' ],
    ];

    public static function render_card( string $slug ): string {
        $post = get_page_by_path( $slug, OBJECT, 'formacao' );
        if ( ! $post || $post->post_status !== 'publish' ) return '';

        $pid   = $post->ID;
        $title = wp_strip_all_tags( get_the_title( $post ) );
        $url   = get_permalink( $post );

        // Resolve type from formacao_tipo taxonomy. Fall back to slug-prefix
        // detection if the term lookup misses (some old posts have empty terms).
        $type_key = 'bootcamp';
        $tipo_label = '';
        $terms = get_the_terms( $pid, 'formacao_tipo' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                if ( isset( self::TYPE_MAP[ $term->slug ] ) ) {
                    $type_key = $term->slug;
                    $tipo_label = $term->name;
                    break;
                }
            }
        }
        if ( ! $tipo_label ) {
            if ( strpos( $slug, 'workshop' ) === 0 || strpos( $slug, '-workshop-' ) !== false ) { $type_key = 'workshop'; $tipo_label = 'Workshop'; }
            elseif ( strpos( $slug, 'bootcamp' ) === 0 || strpos( $slug, '-bootcamp-' ) !== false ) { $type_key = 'bootcamp'; $tipo_label = 'Bootcamp'; }
            elseif ( strpos( $slug, 'curso' ) === 0 ) { $type_key = 'curso'; $tipo_label = 'Curso'; }
            else { $tipo_label = 'Formação'; }
        }

        $cfg = self::TYPE_MAP[ $type_key ];

        ob_start();
        ?>
        <div class="course" data-wow-duration="1s">
            <a data-bg="<?php echo esc_url( $cfg['bg'] ); ?>" href="<?php echo esc_url( $url ); ?>" class="course-box <?php echo esc_attr( $cfg['text'] ); ?>" style="background-image:url('<?php echo esc_url( $cfg['bg'] ); ?>');">
                <div class="course-icon">
                    <img width="40" height="40" alt="<?php echo esc_attr( $tipo_label ); ?>" src="<?php echo esc_url( $cfg['icon'] ); ?>">
                </div>
                <div class="course-text">
                    <div class="course-time"><?php echo esc_html( $tipo_label ); ?></div>
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
