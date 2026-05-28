<?php
/**
 * Marketing Digital pillar page — /marketing-digital/
 *
 * Strategic context (audit 2026-05-27):
 *   - GAMA is EDIT.'s #1 lead-generating course but EDIT. is invisible in the
 *     top 10 for "curso marketing digital lisboa". Lisbon Digital School (LDS)
 *     dominates with 5 of the top 10 results — they win by topical concentration.
 *   - EDIT. has 16+ marketing-touching courses scattered across the site with no
 *     pillar to consolidate them. This page is the canonical hub.
 *
 * v1 scope: hero + course catalog + FAQ. Alumni stories + employer logos + tutor
 * cards deferred to v2.
 *
 * Implementation mirrors EDIT_Criticas_Page (the /avaliacoes-google/ pattern):
 * WP page at /marketing-digital/ with a single shortcode token; this class
 * renders the body + emits schema + enqueues CSS.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Marketing_Digital_Page {

    const SLUG       = 'marketing-digital';
    const TITLE      = 'Marketing Digital — Formação Especializada na EDIT.';
    const OPTION_KEY = 'edit_seo_fix_marketing_digital_page_id';
    const SHORTCODE  = 'edit_marketing_digital_pillar';

    /** Featured tutors for this pillar (equipa slugs). Resolved by EDIT_Pillar_Tutors. */
    const TUTORS = [ 'catarina-sp-neves', 'daniel-devera', 'naiara-back', 'mao-barros' ];

    /**
     * Course catalog — grouped by intensity. Source-of-truth slugs on the
     * formacao CPT. Render-time fetch pulls title + permalink + thumbnail
     * dynamically so course renames don't break the pillar.
     */
    const CATALOG = [
        'Bootcamps' => [
            'digital-marketing-foundations-bootcamp-remote',
            'bootcamp-online-google-ads-meta-ads',
            'bootcamp-generative-ai-aplicado-ao-marketing-digital',
        ],
        'Cursos' => [
            'curso-marketing-digital-online',
            'curso-marketing-digital-porto',
            'curso-remote-learning-digital-marketing-leadership',
            'curso-google-analytics-4',
        ],
        'Workshops' => [
            'workshop-tiktok-ads-strategy-online',
            'workshop-paid-media-performance',
            'workshop-data-analytics-with-ai-2',
            'remote-learning-workshop-loyalty-marketing',
            'influencer-marketing-online',
            'workshop-professional-growth-success',
        ],
        'Crossover IA' => [
            'agentes-inteligentes-para-marketing',
            'seo-engineering-automacao-claude-code',
        ],
    ];

    /**
     * FAQ — 6 questions covering the highest-intent searcher concerns
     * (chosen course, duration, financing, placement, format). Renders
     * as visible content AND as FAQPage JSON-LD for rich snippets.
     */
    const FAQ = [
        [
            'q' => 'Qual curso de Marketing Digital é o mais adequado para mim?',
            'a' => 'Se estás a começar, o <strong>Bootcamp Digital Marketing Foundations</strong> dá-te a visão completa em formato intensivo remoto. Se já trabalhas na área e queres especializar-te em performance, escolhe o <strong>Bootcamp Online Google Ads + Meta Ads</strong>. Para liderança, o <strong>Curso Digital Marketing Leadership</strong> é o caminho. Workshops curtos (TikTok Ads, Paid Media Performance, Loyalty) são ideais para upskilling pontual.',
        ],
        [
            'q' => 'Quanto tempo demora cada formação?',
            'a' => 'Bootcamps duram entre 8 e 12 semanas em formato intensivo. Cursos completos têm 60 a 120 horas distribuídas por 3 a 4 meses em regime pós-laboral. Workshops são módulos curtos de 6 a 16 horas, geralmente em fim-de-semana ou pós-laboral.',
        ],
        [
            'q' => 'Posso usar o Cheque Formação + Digital?',
            'a' => 'Sim, todos os cursos de Marketing Digital da EDIT. são elegíveis ao Cheque Formação + Digital até <strong>30 de Junho de 2026</strong>. A EDIT. é entidade formadora certificada pela DGERT (nº 18391). Após essa data, aguardam-se novidades sobre o programa de substituição.',
        ],
        [
            'q' => 'A EDIT. ajuda na colocação profissional?',
            'a' => 'Sim. A EDIT. tem uma agência de recrutamento própria, a <a href="https://disruptivejobs.io/" target="_blank" rel="noopener">Disruptive Jobs</a>, dedicada a ligar alunos a marcas que recrutam talento digital. Mais de 600 alunos foram colocados em empresas como Farfetch, Worten, EDP, Sonae, NOS, entre outras.',
        ],
        [
            'q' => 'As formações são online, presenciais ou híbridas?',
            'a' => 'Temos formatos para todas as preferências: <strong>Online em tempo real</strong> (Remote Learning, com aulas ao vivo), <strong>Presencial em Lisboa</strong> (Av. Aquilino Ribeiro Machado) e <strong>Presencial no Porto</strong> (Rua Alferes Malheiro). Alguns workshops são exclusivamente online; cursos completos têm geralmente as três opções.',
        ],
        [
            'q' => 'Que ferramentas e plataformas vou aprender?',
            'a' => 'O currículo é atualizado anualmente para refletir as ferramentas que as marcas usam hoje: <strong>Meta Ads, Google Ads, LinkedIn Ads, TikTok Ads, Google Analytics 4, Google Tag Manager, Looker Studio, HubSpot, Mailchimp</strong>, plus integrações de IA aplicada (ChatGPT, Claude, agentes inteligentes para campanhas e conteúdo).',
        ],
    ];

    public static function init() {
        add_shortcode( self::SHORTCODE,    [ __CLASS__, 'render_shortcode' ] );
        add_action( 'admin_init',          [ __CLASS__, 'ensure_page_exists' ] );
        add_action( 'wp_enqueue_scripts',  [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_head',             [ __CLASS__, 'emit_schema' ], 8 );
    }

    public static function ensure_page_exists() {
        $shortcode_token = '[' . self::SHORTCODE . ']';

        $stored_id = (int) get_option( self::OPTION_KEY );
        if ( $stored_id ) {
            $post = get_post( $stored_id );
            if ( $post && $post->post_status === 'publish' ) {
                if ( strpos( $post->post_content, $shortcode_token ) === false ) {
                    self::force_update_to_shortcode( $stored_id );
                }
                return $stored_id;
            }
        }

        // Adopt manually-created page if present.
        $existing = get_page_by_path( self::SLUG );
        if ( $existing ) {
            update_option( self::OPTION_KEY, $existing->ID );
            if ( strpos( $existing->post_content, $shortcode_token ) === false ) {
                self::force_update_to_shortcode( $existing->ID );
            }
            return $existing->ID;
        }

        $page_id = wp_insert_post( [
            'post_title'     => self::TITLE,
            'post_name'      => self::SLUG,
            'post_content'   => $shortcode_token,
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => get_current_user_id() ?: 1,
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ], true );

        if ( is_wp_error( $page_id ) ) {
            error_log( 'EDIT_Marketing_Digital_Page: page creation failed — ' . $page_id->get_error_message() );
            return 0;
        }

        update_option( self::OPTION_KEY, $page_id );
        self::set_rank_math_meta( $page_id );
        flush_rewrite_rules();

        return $page_id;
    }

    private static function force_update_to_shortcode( int $page_id ): void {
        wp_update_post( [
            'ID'           => $page_id,
            'post_content' => '[' . self::SHORTCODE . ']',
        ] );
        self::set_rank_math_meta( $page_id );
    }

    private static function set_rank_math_meta( int $page_id ): void {
        update_post_meta( $page_id, 'rank_math_title',       'Curso Marketing Digital Lisboa & Porto | EDIT. — 16 Programas DGERT' );
        update_post_meta( $page_id, 'rank_math_description', '16 formações em Marketing Digital, DGERT certificadas, com Cheque Formação + Digital. Bootcamps, cursos e workshops em Lisboa, Porto e online. 600+ alumni colocados.' );
        update_post_meta( $page_id, 'rank_math_focus_keyword', 'curso marketing digital' );
        update_post_meta( $page_id, 'rank_math_robots',      [ 'index', 'follow' ] );
    }

    public static function enqueue_assets() {
        if ( ! is_page( self::SLUG ) ) return;
        $url = site_url( '/wp-content/plugins/' . basename( WEAREDIT_SITE_ENGINE_PATH ) . '/assets/marketing-digital.css' );
        wp_enqueue_style( 'edit-marketing-digital', $url, [], WEAREDIT_SITE_ENGINE_VERSION );
    }

    public static function emit_schema(): void {
        if ( ! is_page( self::SLUG ) ) return;

        $url = home_url( '/' . self::SLUG . '/' );

        // CollectionPage with embedded ItemList (the course catalog) + FAQPage.
        $items = [];
        $position = 1;
        foreach ( self::CATALOG as $group => $slugs ) {
            foreach ( $slugs as $slug ) {
                $post = get_page_by_path( $slug, OBJECT, 'formacao' );
                if ( ! $post ) continue;
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'url'      => get_permalink( $post ),
                    'name'     => wp_strip_all_tags( get_the_title( $post ) ),
                ];
            }
        }

        $collection = [
            '@context'    => 'https://schema.org',
            '@type'       => 'CollectionPage',
            '@id'         => $url . '#collection',
            'url'         => $url,
            'name'        => self::TITLE,
            'description' => 'Catálogo completo de formações em Marketing Digital da EDIT. — Bootcamps, cursos e workshops em Lisboa, Porto e online. DGERT certificada.',
            'inLanguage'  => 'pt-PT',
            'isPartOf'    => [ '@id' => home_url( '/' ) . '#website' ],
            'mainEntity'  => [
                '@type'           => 'ItemList',
                'numberOfItems'   => count( $items ),
                'itemListElement' => $items,
            ],
        ];

        $faq_items = [];
        foreach ( self::FAQ as $f ) {
            $faq_items[] = [
                '@type'          => 'Question',
                'name'           => $f['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $f['a'] ),
                ],
            ];
        }
        $faq = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            '@id'        => $url . '#faq',
            'mainEntity' => $faq_items,
        ];

        echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $collection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
        echo "<script type=\"application/ld+json\">" . wp_json_encode( $faq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
    }

    public static function render_shortcode(): string {
        ob_start();
        ?>
        <section class="md-pillar">
            <div class="md-hero">
                <div class="md-hero__inner">
                    <p class="md-hero__eyebrow">FORMAÇÃO ESPECIALIZADA</p>
                    <h1 class="md-hero__title">Marketing Digital<br><span>na EDIT.</span></h1>
                    <p class="md-hero__lede">16 formações DGERT certificadas em Lisboa, Porto e online. Bootcamps, cursos e workshops desenhados com marcas que recrutam talento digital. Cheque Formação + Digital elegível.</p>
                    <div class="md-hero__bar">
                        <span class="md-pill">DGERT nº 18391</span>
                        <span class="md-pill">★ 4.1 / 67 reviews Google</span>
                        <span class="md-pill">600+ alumni colocados</span>
                        <span class="md-pill">Lisboa · Porto · Online</span>
                    </div>
                    <div class="md-hero__cta">
                        <a class="md-btn md-btn--primary" href="#catalogo">Ver os 16 cursos</a>
                        <button type="button" class="md-btn md-btn--ghost swipe-cta" data-contact="true"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Falar com um consultor</span></button>
                    </div>
                </div>
            </div>

            <div id="catalogo" class="md-catalog">
                <h2 class="md-section-title">Catálogo de Formação <span>em Marketing Digital</span></h2>
                <?php foreach ( self::CATALOG as $group => $slugs ) : ?>
                    <div class="md-group">
                        <h3 class="md-group__title"><?php echo esc_html( $group ); ?></h3>
                        <div class="md-grid course-cards">
                            <?php foreach ( $slugs as $slug ) {
                                echo EDIT_Pillar_Courses::render_card( $slug );
                            } ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php echo EDIT_Pillar_Tutors::render( self::TUTORS, 'Os tutores da EDIT. são paid media managers, performance leads e estrategistas em activo nas marcas que recrutam talento digital em Portugal.' ); ?>

            <div id="faq" class="md-faq">
                <h2 class="md-section-title">Perguntas <span>Frequentes</span></h2>
                <div class="md-faq__list">
                    <?php foreach ( self::FAQ as $f ) : ?>
                        <details class="md-faq__item">
                            <summary class="md-faq__q"><span class="md-faq__q-text"><?php echo esc_html( $f['q'] ); ?></span></summary>
                            <div class="md-faq__a"><?php echo wp_kses_post( $f['a'] ); ?></div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="md-final-cta">
                <h2>Pronto para a próxima formação em <span>Marketing Digital</span>?</h2>
                <p>Os nossos consultores ajudam-te a escolher o curso certo para os teus objetivos e a tirar partido do Cheque Formação + Digital antes de 30 de Junho 2026.</p>
                <button type="button" class="md-btn md-btn--primary md-btn--lg swipe-cta" data-contact="true"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Falar com um consultor</span></button>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
