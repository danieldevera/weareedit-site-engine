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

    public static function init(): void {
        // Drop all pillar-tutor caches whenever an equipa profile is created,
        // edited, deleted, or transitions status. Equipa edits are rare so
        // bluntly clearing all keyword-keyed transients is fine — far better
        // than letting a stale tutor list outlive a profile edit.
        add_action( 'save_post_equipa', [ __CLASS__, 'invalidate_caches' ] );
        add_action( 'deleted_post',     [ __CLASS__, 'invalidate_caches' ] );
        add_action( 'transition_post_status', function( $new, $old, $post ) {
            if ( $post && $post->post_type === 'equipa' ) {
                self::invalidate_caches();
            }
        }, 10, 3 );
    }

    /**
     * Render tutors filtered by area-tag keywords (ACF `profile_knowsabout`
     * comma-separated values). Returns the 15 most-recently-published equipa
     * posts whose knowsAbout array contains ANY of the supplied keywords
     * (case-insensitive substring match).
     *
     * Pillar callers pass their pillar-specific keywords PLUS the IA terms,
     * so each pillar shows both relevant-area tutors and IA specialists.
     *
     * Falls back to the supplied static slugs list if dynamic resolution
     * returns no matches (defensive: prevents an empty section if the
     * profile_knowsabout field is unset on every tutor).
     */
    public static function render_by_area( array $area_keywords, string $section_title = 'Aprende com profissionais em activo', int $limit = 20, array $core_slugs = [] ): string {
        // Merge strategy: dynamic results first (recency wins), then the core
        // fallback list appended for any not already surfaced. Guarantees the
        // hand-curated team always shows even when no cargo/knowsAbout match.
        $dynamic = self::get_tutor_slugs_by_area( $area_keywords, $limit );
        $merged  = $dynamic;
        foreach ( $core_slugs as $slug ) {
            if ( ! in_array( $slug, $merged, true ) ) $merged[] = $slug;
        }
        $merged = array_slice( $merged, 0, $limit );
        return self::render( $merged, $section_title );
    }

    /**
     * Query equipa posts and filter by keywords matched against:
     *   1. ACF `profile_knowsabout` (comma-separated areas) — primary signal
     *   2. ACF `hero_cargo` (job title) — fallback signal (e.g. "Paid Media
     *      Specialist" matches Marketing Digital pillar keywords)
     *   3. ACF `hero_empresa` (employer) — softest signal
     *
     * Most equipa posts pre-2026-05 have profile_knowsabout unset, so the
     * cargo fallback dramatically broadens the pool. Cargo is set on
     * virtually every tutor.
     */
    public static function get_tutor_slugs_by_area( array $keywords, int $limit = 20 ): array {
        if ( empty( $keywords ) ) return [];

        // Result-level transient cache. The full uncached path runs up to
        // 12 ACF probes × 200 equipa posts = 2400 get_field() calls and
        // dominates pillar TTFB (4s+ uncached, per audit 2026-06-14).
        // Cache the resolved slug list per keyword-set. Invalidated by the
        // save_post_equipa hook below, so edits propagate within a request.
        $cache_key = 'edit_pillar_tutors_' . md5( serialize( [ $keywords, $limit ] ) );
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        // Pull a generous pool, then filter — equipa CPT has ~95 posts as of 2026-06-12.
        // Order by post_modified (last edit) so freshly-touched profiles surface
        // first — covers both newly-created and recently-updated tutors.
        $posts = get_posts( [
            'post_type'      => 'equipa',
            'posts_per_page' => 200,
            'post_status'    => 'publish',
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ] );

        // Legacy equipa posts store cargo/company under a handful of different
        // ACF field names. Probe each. (Matches the candidate list in
        // EDIT_Team_Listing::resolve_job/resolve_company.)
        $cargo_fields    = [ 'hero_cargo', 'cargo', 'job', 'job_title', 'position', 'role', 'titulo_cargo' ];
        $empresa_fields  = [ 'hero_empresa', 'empresa', 'company', 'employer' ];

        $matched = [];
        foreach ( $posts as $p ) {
            if ( count( $matched ) >= $limit ) break;

            $haystack_parts = [];
            $knows = function_exists( 'get_field' ) ? get_field( 'profile_knowsabout', $p->ID ) : get_post_meta( $p->ID, 'profile_knowsabout', true );
            if ( is_string( $knows ) && $knows !== '' ) $haystack_parts[] = $knows;

            foreach ( $cargo_fields as $f ) {
                $v = function_exists( 'get_field' ) ? get_field( $f, $p->ID ) : get_post_meta( $p->ID, $f, true );
                if ( is_string( $v ) && $v !== '' ) { $haystack_parts[] = $v; break; }
            }
            foreach ( $empresa_fields as $f ) {
                $v = function_exists( 'get_field' ) ? get_field( $f, $p->ID ) : get_post_meta( $p->ID, $f, true );
                if ( is_string( $v ) && $v !== '' ) { $haystack_parts[] = $v; break; }
            }

            // Fallback: post title (e.g. "Daniel Devera") — coarse but ensures
            // a tutor with no ACF fields still gets considered against
            // name-bearing keywords. Rare path.
            if ( empty( $haystack_parts ) ) {
                $haystack_parts[] = $p->post_title;
            }

            $haystack = implode( ' | ', $haystack_parts );

            foreach ( $keywords as $kw ) {
                if ( $kw === '' ) continue;
                if ( stripos( $haystack, $kw ) !== false ) {
                    $matched[ $p->post_name ] = true;
                    break;
                }
            }
        }
        $slugs = array_keys( $matched );
        // 14 days TTL with explicit save_post invalidation. Equipa edits are
        // rare; this lets the result survive across many pillar visits.
        set_transient( $cache_key, $slugs, 14 * DAY_IN_SECONDS );
        return $slugs;
    }

    /**
     * Invalidate all pillar-tutor result caches. Hooked on save_post_equipa
     * so any tutor profile edit propagates to all pillar pages immediately.
     * Also covers post deletion and status transitions.
     */
    public static function invalidate_caches(): void {
        global $wpdb;
        // Pattern matches both the slug-list cache (`edit_pillar_tutors_`)
        // and the render-output cache (`edit_pillar_tutors_html_`) — the
        // `%` covers both suffixes.
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_edit_pillar_tutors_%'
                OR option_name LIKE '_transient_timeout_edit_pillar_tutors_%'"
        );
    }

    /**
     * Render a tutors grid HTML for an array of equipa slugs.
     * Section title and CTA copy provided by the calling pillar.
     */
    public static function render( array $slugs, string $section_title = 'Aprende com profissionais em activo' ): string {
        if ( empty( $slugs ) ) return '';

        // Render-level transient cache. Without this, every pillar visit
        // pays ~5 ACF probes × 20 tutors = ~100 ACF calls + 20
        // get_page_by_path() lookups + 20 attachment URL resolutions.
        // Invalidated by the same save_post_equipa hook as the slugs cache.
        $render_key = 'edit_pillar_tutors_html_' . md5( serialize( [ $slugs, $section_title ] ) );
        $cached_html = get_transient( $render_key );
        if ( is_string( $cached_html ) && $cached_html !== '' ) {
            return $cached_html;
        }

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
                                        <?php if ( ! empty( $t['flag'] ) ) : ?>
                                            <div class="bandeiraPais">
                                                <img src="<?php echo esc_url( $t['flag'] ); ?>" alt="<?php echo esc_attr( $t['name'] ); ?> country" loading="lazy">
                                            </div>
                                        <?php endif; ?>
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
        $html = (string) ob_get_clean();
        // Cache for 14 days — invalidation via save_post_equipa fires for
        // any tutor profile edit (init() registers the hook).
        set_transient( $render_key, $html, 14 * DAY_IN_SECONDS );
        return $html;
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

        // Nationality flag — defer to edit-profiles plugin's resolver which
        // handles legacy ACF field aliases + ISO-code lookup + the UK
        // underscore quirk (`uk_.png`). Falls back to PT default if the
        // resolver isn't loaded (defensive: edit-profiles plugin may not
        // be active on every environment).
        $flag = '';
        if ( class_exists( 'EDIT_Team_Listing' ) && method_exists( 'EDIT_Team_Listing', 'resolve_country_flag' ) ) {
            $flag = (string) EDIT_Team_Listing::resolve_country_flag( $pid );
        }
        if ( $flag === '' ) {
            // Default PT flag (same URL the edit-profiles resolver returns by
            // default). Hardcoded so the flag always renders even if the
            // sister plugin is missing.
            $flag = 'https://weareedit.io/wp-content/uploads/2015/06/pt.png';
        }

        return [
            'name'    => wp_strip_all_tags( $post->post_title ),
            'cargo'   => $cargo,
            'empresa' => $empresa,
            'photo'   => $photo,
            'flag'    => $flag,
            'url'     => get_permalink( $post ),
        ];
    }
}
