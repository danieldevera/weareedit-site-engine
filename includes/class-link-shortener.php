<?php
/**
 * EDIT. self-hosted branded link shortener.
 * ─────────────────────────────────────────────────────────────────────────────
 * Routes weareedit.io/go/<slug> → 302 redirect to the full UTM'd campaign URL.
 *
 * Branded on our OWN domain (weareedit.io), self-hosted in this plugin — no
 * external shortener, no extra DNS, no Metricool custom-domain plan, and no
 * per-link manual work. Slugs are managed here in code (one line per link);
 * destination URLs keep their UTMs so GA attribution is unchanged. 302 (not
 * 301) so browsers don't permanently cache the hop and every click re-hits us.
 *
 * Usage in posts: https://weareedit.io/go/<slug>  (e.g. /go/gama-li)
 *
 * @since 1.5.776
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Link_Shortener {

    const BASE = '/go/';

    /** slug => destination URL (full, with UTMs). Add a line per new link. */
    private static function links(): array {
        return [
            // Webflow 2.0 bootcamp
            'webflow-fb' => 'https://weareedit.io/formacao/bootcamp-webflow-2-0-cria-sites-completos-ia/?utm_source=facebook&utm_medium=social&utm_campaign=webflow_2_0',
            'webflow-li' => 'https://weareedit.io/formacao/bootcamp-webflow-2-0-cria-sites-completos-ia/?utm_source=linkedin&utm_medium=social&utm_campaign=webflow_2_0',
            // Advanced AI bootcamp
            'aai-fb'     => 'https://weareedit.io/formacao/bootcamp-advanced-artificial-intelligence/?utm_source=facebook&utm_medium=social&utm_campaign=advanced_ai',
            'aai-li'     => 'https://weareedit.io/formacao/bootcamp-advanced-artificial-intelligence/?utm_source=linkedin&utm_medium=social&utm_campaign=advanced_ai',
            // Ética em IA workshop
            'etica-fb'   => 'https://weareedit.io/formacao/workshop-etica-em-ia-online-2/?utm_source=facebook&utm_medium=social&utm_campaign=etica_ia',
            'etica-li'   => 'https://weareedit.io/formacao/workshop-etica-em-ia-online-2/?utm_source=linkedin&utm_medium=social&utm_campaign=etica_ia',
            // UX/UI curso (online)
            'uxui-fb'    => 'https://weareedit.io/formacao/curso-uxui-online/?utm_source=facebook&utm_medium=social&utm_campaign=uxui',
            'uxui-li'    => 'https://weareedit.io/formacao/curso-uxui-online/?utm_source=linkedin&utm_medium=social&utm_campaign=uxui',
            // Data Science & AI Analytics curso (online)
            'dsba-fb'    => 'https://weareedit.io/data-science-business-analytics/?utm_source=facebook&utm_medium=social&utm_campaign=data_science',
            'dsba-li'    => 'https://weareedit.io/data-science-business-analytics/?utm_source=linkedin&utm_medium=social&utm_campaign=data_science',
            // GAMA — Google Ads & Meta Ads bootcamp
            'gama-fb'    => 'https://weareedit.io/formacao/bootcamp-online-google-ads-meta-ads/?utm_source=facebook&utm_medium=social&utm_campaign=google_meta_ads',
            'gama-li'    => 'https://weareedit.io/formacao/bootcamp-online-google-ads-meta-ads/?utm_source=linkedin&utm_medium=social&utm_campaign=google_meta_ads',
            // Editorial #4 — "A verdadeira vítima da IA no marketing de performance" (Afonso Monteiro)
            'vitima-fb'  => 'https://weareedit.io/blog/a-verdadeira-vitima-da-ia-no-marketing-de-performance/?utm_source=facebook&utm_medium=social&utm_campaign=editorial_04',
            'vitima-li'  => 'https://weareedit.io/blog/a-verdadeira-vitima-da-ia-no-marketing-de-performance/?utm_source=linkedin&utm_medium=social&utm_campaign=editorial_04',
            'vitima-ig'  => 'https://weareedit.io/blog/a-verdadeira-vitima-da-ia-no-marketing-de-performance/?utm_source=instagram&utm_medium=bio&utm_campaign=editorial_04',
            // Early Bird -10% — cursos com início em Setembro 2026 (deadline 31 Jul)
            'early-fb'   => 'https://weareedit.io/formacao/?campanha=early10&utm_source=facebook&utm_medium=social&utm_campaign=early_bird_10',
            'early-li'   => 'https://weareedit.io/formacao/?campanha=early10&utm_source=linkedin&utm_medium=social&utm_campaign=early_bird_10',
            'early-ig'   => 'https://weareedit.io/formacao/?campanha=early10&utm_source=instagram&utm_medium=bio&utm_campaign=early_bird_10',
        ];
    }

    public static function init(): void {
        add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect' ], 0 );
    }

    public static function maybe_redirect(): void {
        $path = (string) parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
        if ( strpos( $path, self::BASE ) !== 0 ) return;
        $slug  = strtolower( trim( substr( $path, strlen( self::BASE ) ), '/' ) );
        $links = self::links();
        if ( $slug === '' || ! isset( $links[ $slug ] ) ) return;   // unknown slug → normal WP 404
        nocache_headers();
        wp_redirect( $links[ $slug ], 302 );
        exit;
    }
}
