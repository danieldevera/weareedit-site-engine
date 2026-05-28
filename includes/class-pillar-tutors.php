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
        <section class="tutores md-tutores-section">
            <div class="container">
                <div class="row">
                    <div class="col-md-4">
                        <h2 class="md-section-title">Tutores <span>em destaque</span></h2>
                    </div>
                    <div class="col-md-8">
                        <p class="md-tutores__lede"><?php echo esc_html( $section_title ); ?></p>
                    </div>
                </div>
                <div class="row persons-grid">
                    <?php foreach ( $tutors as $t ) : ?>
                        <div class="col-md-3 col-sm-6 col-sm-offset-0 col-xs-12 col-xs-offset-0">
                            <a href="<?php echo esc_url( $t['url'] ); ?>">
                                <div class="adaptImage">
                                    <div class="adaptImage__inner">
                                        <div class="adaptImage__aspect" style="padding-bottom:145%;">
                                            <img class="adaptImage__image" src="<?php echo esc_url( $t['photo'] ); ?>" alt="<?php echo esc_attr( $t['name'] ); ?>" loading="lazy">
                                        </div>
                                        <div class="person-card">
                                            <div class="person-card-content">
                                                <div class="text">
                                                    <h3 class="name"><?php echo esc_html( $t['name'] ); ?></h3>
                                                    <?php if ( $t['cargo'] ) : ?>
                                                        <h4 class="job"><?php echo esc_html( $t['cargo'] ); ?></h4>
                                                    <?php endif; ?>
                                                    <?php if ( $t['empresa'] ) : ?>
                                                        <h4 class="company"><?php echo esc_html( $t['empresa'] ); ?></h4>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
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

        // Photo: try ACF foto (attachment ID OR array OR URL), featured image,
        // edit-profiles meta 'foto'. Same field name historically stored 3 ways
        // across edit-profiles plugin versions.
        $photo = '';
        if ( function_exists( 'get_field' ) ) {
            $foto_val = get_field( 'foto', $pid );
            if ( is_array( $foto_val ) ) {
                $photo = ! empty( $foto_val['url'] ) ? $foto_val['url'] : '';
                if ( ! $photo && ! empty( $foto_val['ID'] ) ) {
                    $photo = wp_get_attachment_image_url( (int) $foto_val['ID'], 'medium' ) ?: '';
                }
            } elseif ( is_numeric( $foto_val ) && $foto_val > 0 ) {
                $photo = wp_get_attachment_image_url( (int) $foto_val, 'medium' ) ?: '';
            } elseif ( is_string( $foto_val ) && $foto_val !== '' ) {
                $photo = $foto_val;
            }
        }
        if ( ! $photo ) {
            $raw = get_post_meta( $pid, 'foto', true );
            if ( is_numeric( $raw ) && $raw > 0 ) {
                $photo = wp_get_attachment_image_url( (int) $raw, 'medium' ) ?: '';
            } elseif ( is_string( $raw ) && $raw !== '' ) {
                $photo = $raw;
            }
        }
        if ( ! $photo ) {
            $thumb = get_the_post_thumbnail_url( $pid, 'medium' );
            if ( $thumb ) $photo = $thumb;
        }
        if ( ! $photo ) {
            // Last-resort generic placeholder so the card still renders.
            $photo = 'https://weareedit.io/wp-content/uploads/2024/11/weareedit-perfil-generico-mulher.jpg';
        }

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
