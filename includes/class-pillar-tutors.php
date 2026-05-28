<?php
/**
 * Pillar Tutors — shared helper to render a "Tutores" section on pillar pages.
 *
 * Each pillar specifies a list of equipa slugs in its TUTORS constant.
 * This class fetches the tutor data (photo, name, cargo, empresa) and
 * renders a grid of clickable cards linking to /equipa/[slug]/.
 *
 * Photo + cargo come from ACF fields populated via edit-profiles plugin:
 *   foto (attachment ID), hero_cargo, hero_empresa
 *
 * If a tutor slug doesn't resolve to a published equipa post, it's skipped.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Pillar_Tutors {

    /**
     * Render a tutors grid HTML for an array of equipa slugs.
     * Section title and CTA copy provided by the calling pillar.
     */
    public static function render( array $slugs, string $section_title = 'Aprende com profissionais em activo' ): string {
        if ( empty( $slugs ) ) return '';

        $tutors = [];
        foreach ( $slugs as $slug ) {
            $data = self::resolve_tutor( $slug );
            if ( $data ) $tutors[] = $data;
        }

        if ( empty( $tutors ) ) return '';

        ob_start();
        ?>
        <div class="md-tutores">
            <h2 class="md-section-title">Tutores <span>em destaque</span></h2>
            <p class="md-tutores__lede"><?php echo esc_html( $section_title ); ?></p>
            <div class="md-tutores__grid">
                <?php foreach ( $tutors as $t ) : ?>
                    <a class="md-tutor-card" href="<?php echo esc_url( $t['url'] ); ?>">
                        <div class="md-tutor-card__photo" style="background-image:url('<?php echo esc_url( $t['photo'] ); ?>');"></div>
                        <div class="md-tutor-card__meta">
                            <h4 class="md-tutor-card__name"><?php echo esc_html( $t['name'] ); ?></h4>
                            <?php if ( $t['cargo'] ) : ?>
                                <p class="md-tutor-card__cargo"><?php echo esc_html( $t['cargo'] ); ?><?php if ( $t['empresa'] ) : ?> · <span><?php echo esc_html( $t['empresa'] ); ?></span><?php endif; ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Resolve a single tutor slug → photo/name/cargo/empresa/url, or null.
     */
    private static function resolve_tutor( string $slug ): ?array {
        $post = get_page_by_path( $slug, OBJECT, 'equipa' );
        if ( ! $post || $post->post_status !== 'publish' ) return null;

        $pid = $post->ID;

        // Photo via ACF foto field (attachment ID), or featured image fallback.
        $photo = '';
        if ( function_exists( 'get_field' ) ) {
            $foto_id = (int) get_field( 'foto', $pid );
            if ( $foto_id ) {
                $url = wp_get_attachment_image_url( $foto_id, 'medium' );
                if ( $url ) $photo = $url;
            }
        }
        if ( ! $photo ) {
            $thumb = get_the_post_thumbnail_url( $pid, 'medium' );
            if ( $thumb ) $photo = $thumb;
        }
        if ( ! $photo ) return null; // Skip tutors without a photo — would look empty.

        $cargo = '';
        $empresa = '';
        if ( function_exists( 'get_field' ) ) {
            $cargo   = (string) ( get_field( 'hero_cargo', $pid ) ?: get_field( 'cargo', $pid ) );
            $empresa = (string) ( get_field( 'hero_empresa', $pid ) ?: get_field( 'empresa', $pid ) );
        }

        return [
            'name'    => wp_strip_all_tags( $post->post_title ),
            'cargo'   => $cargo,
            'empresa' => $empresa,
            'photo'   => $photo,
            'url'     => get_permalink( $post ),
        ];
    }
}
