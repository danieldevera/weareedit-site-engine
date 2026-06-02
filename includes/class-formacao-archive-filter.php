<?php
/**
 * Formação archive — campaign URL filter.
 *
 * When visitors land on /formacao/?campanha=early15 (from the promo overlay
 * CTA, the newsletter sales section, or a paid ad), narrow the archive to
 * courses starting in September 2026 only — the Early 15% campaign window.
 *
 * Start date is stored in the ACF field `home_data` as dd/mm/yyyy. We
 * meta_query LIKE '%/09/2026' which matches any day in September 2026.
 *
 * This is a v1 quick win — Task #27 ("September courses Early15 page")
 * will replace this filter with a dedicated landing page that adds a
 * campaign hero, countdown, FAQ, etc.
 *
 * @package WeareEditSiteEngine
 * @since   1.5.242
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Formacao_Archive_Filter {

    const QUERY_PARAM       = 'campanha';
    const EARLY15_VALUE     = 'early15';
    const EARLY15_DATE_LIKE = '%/09/2026';

    public static function init(): void {
        add_action( 'pre_get_posts', [ __CLASS__, 'maybe_filter' ] );
    }

    public static function maybe_filter( $query ): void {
        if ( is_admin() ) return;
        if ( ! $query->is_main_query() ) return;
        if ( ! is_post_type_archive( 'formacao' ) ) return;
        if ( empty( $_GET[ self::QUERY_PARAM ] ) ) return;

        $campaign = sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_PARAM ] ) );
        if ( $campaign !== self::EARLY15_VALUE ) return;

        $meta_query = (array) $query->get( 'meta_query' );
        $meta_query[] = [
            'key'     => 'home_data',
            'value'   => self::EARLY15_DATE_LIKE,
            'compare' => 'LIKE',
        ];
        $query->set( 'meta_query', $meta_query );
    }
}
