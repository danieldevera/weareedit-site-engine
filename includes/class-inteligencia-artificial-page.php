<?php
/**
 * Inteligência Artificial pillar page — /curso-inteligencia-artificial/
 *
 * Strategic context (audit 2026-05-27): EDIT. ranks #10 for "curso inteligência
 * artificial bootcamp Portugal". SERP is wide open — no clear dominant
 * competitor. EDIT.'s differentiator: applied AI per industry (Cinema, UX,
 * Marketing, Web Design) vs competitors' generic "AI for everyone".
 *
 * Mirror of the previous pillar classes. Same swipe-cta, popup bridge,
 * and shared CSS pattern.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Inteligencia_Artificial_Page {

    const SLUG       = 'curso-inteligencia-artificial';
    const TITLE      = 'Inteligência Artificial — Formação Especializada na EDIT.';
    const OPTION_KEY = 'edit_seo_fix_inteligencia_artificial_page_id';
    const SHORTCODE  = 'edit_inteligencia_artificial_pillar';

    const TUTORS = [ 'naiara-back', 'daniel-devera', 'miguel-rao-vieira', 'catarina-sp-neves' ];

    const CATALOG = [
        'Bootcamps' => [
            'bootcamp-advanced-artificial-intelligence',
            'bootcamp-prompt-engineering-2',
            'bootcamp-generative-ai-aplicado-ao-marketing-digital',
            'bootcamp-ia-para-ux-research',
            'bootcamp-webflow-2-0-cria-sites-completos-ia',
            'introducao-lovable-criacao-de-produtos-digitais-com-ia',
            'inteligencia-artificial-audiovisual-cinema',
        ],
        'Workshops' => [
            'workshop-etica-em-ia-online-2',
            'workshop-data-analytics-with-ai-2',
        ],
        'Crossover IA' => [
            'agentes-inteligentes-para-marketing',
            'seo-engineering-automacao-claude-code',
        ],
    ];

    const FAQ = [
        [
            'q' => 'Qual curso de Inteligência Artificial é o mais adequado para mim?',
            'a' => 'Se queres a visão completa, o <strong>Bootcamp Advanced Artificial Intelligence</strong> (8 semanas) é o percurso de referência. Para aplicação imediata, escolhe pela tua área: <strong>Gen AI Marketing</strong> (campanhas), <strong>IA UX Research</strong> (entrevistas e síntese), <strong>IA Cinema e Audiovisual</strong> (vídeo + imagem), <strong>Lovable</strong> (produtos digitais), <strong>Prompt Engineering</strong> (transversal). Workshops curtos (Ética em IA, Data Analytics with AI) são ideais para upskilling pontual.',
        ],
        [
            'q' => 'Preciso de saber programar para começar?',
            'a' => 'Não. A maior parte das formações de IA da EDIT. é projetada para profissionais não-técnicos (marketing, design, gestão, audiovisual). Foco em aplicação prática: como usar ChatGPT, Claude, Midjourney, Lovable, etc. para acelerar o trabalho. Para profundidade técnica (modelos, MLOps, deployment), o <strong>Advanced AI Bootcamp</strong> assume base de Python.',
        ],
        [
            'q' => 'Que ferramentas e modelos vou aprender?',
            'a' => 'O currículo cobre os modelos e plataformas mais usados na indústria: <strong>ChatGPT, Claude, Gemini, Midjourney, Sora, Runway, ElevenLabs, Stable Diffusion, Hugging Face, Lovable, v0, Bolt, Cursor, n8n, Zapier</strong>. Os tutores são profissionais que usam estas ferramentas em projetos reais — não vendedores de produto.',
        ],
        [
            'q' => 'A EDIT. ensina IA aplicada a uma indústria específica?',
            'a' => 'Sim — essa é a diferenciação face a cursos genéricos. Temos percursos dedicados a <strong>IA no Cinema e Audiovisual</strong>, <strong>IA no Marketing Digital</strong>, <strong>IA no UX Research</strong>, e <strong>IA na criação de produtos digitais</strong>. Cada um trabalha casos reais da indústria com tutores em activo na área.',
        ],
        [
            'q' => 'Posso usar o Cheque Formação + Digital?',
            'a' => 'Sim. Todos os cursos de Inteligência Artificial da EDIT. são elegíveis ao Cheque Formação + Digital até <strong>30 de Junho de 2026</strong>. A EDIT. é entidade formadora certificada pela DGERT (nº 18391).',
        ],
        [
            'q' => 'Aprendo a construir agentes e automações?',
            'a' => 'Sim. O <strong>Bootcamp Prompt Engineering</strong> cobre os fundamentos; o curso de <strong>Agentes Inteligentes para Marketing</strong> trabalha automações end-to-end com n8n, Zapier, Make e LLMs orquestrados; o <strong>SEO Engineering + Automação com Claude Code</strong> ensina a usar agentes em workflows reais de desenvolvimento.',
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
                if ( $post->post_name !== self::SLUG ) {
                    wp_update_post( [ 'ID' => $stored_id, 'post_name' => self::SLUG ] );
                    flush_rewrite_rules();
                }
                if ( strpos( $post->post_content, $shortcode_token ) === false ) {
                    self::force_update_to_shortcode( $stored_id );
                }
                return $stored_id;
            }
        }

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
            error_log( 'EDIT_Inteligencia_Artificial_Page: page creation failed — ' . $page_id->get_error_message() );
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
        update_post_meta( $page_id, 'rank_math_title',       'Curso Inteligência Artificial Bootcamp Portugal | EDIT. — 11 Programas DGERT' );
        update_post_meta( $page_id, 'rank_math_description', '11 formações em Inteligência Artificial: bootcamps avançados, IA aplicada (Marketing, Cinema, UX, Web), Prompt Engineering, Ética e Agentes. DGERT certificada, Cheque Formação + Digital elegível.' );
        update_post_meta( $page_id, 'rank_math_focus_keyword', 'curso inteligência artificial' );
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
            'description' => 'Catálogo completo de formações em Inteligência Artificial da EDIT. — Bootcamps avançados, IA aplicada por indústria, Prompt Engineering, agentes e automações.',
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
            <div class="md-hero md-hero--video">
                <video class="md-hero__video" autoplay muted loop playsinline aria-hidden="true">
                    <source src="<?php echo esc_url( home_url( '/wp-content/uploads/2026/03/waves-sequence-compressed.mp4' ) ); ?>" type="video/mp4">
                </video>
                <div class="md-hero__veil" aria-hidden="true"></div>
                <div class="md-hero__inner">
                    <a class="dgert-hero-pill" href="https://www.dgert.gov.pt/entidades-formadoras-certificadas" target="_blank" rel="noopener noreferrer" aria-label="DGERT — Entidade Formadora Certificada">
                        <img width="1024" height="483" src="<?php echo esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/dgert-entidade-formadora-branco.png' ); ?>" alt="DGERT" loading="eager">
                        <span class="dgert-hero-pill-text">Entidade Formadora Certificada</span>
                        <span class="dgert-hero-pill-arrow" aria-hidden="true">↗</span>
                    </a>
                    <h1 class="md-hero__title md-hero__title--xl">Inteligência Artificial<span class="h1-dot h1-dot-pink">.</span><br>Aplicada<span class="h1-dot h1-dot-teal">.</span></h1>
                    <p class="md-hero__lede md-hero__lede--yellow">11 formações DGERT-certificadas em Lisboa, Porto e online. Bootcamps, workshops e percursos avançados em IA aplicada — Cinema, Marketing, UX, Web e Produtos Digitais, leccionados por tutores que usam estas ferramentas em projectos reais. Programas SIFIDE-elegíveis.</p>
                    <div class="md-hero__cta">
                        <a class="btn btn-yellow swipe-cta" href="#catalogo"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Ver os 11 cursos</span></a>
                        <a class="hero-reviews" href="https://weareedit.io/avaliacoes-google/" rel="noopener">
                            <span class="hr-star">★</span>
                            <span class="hr-rating">4.1</span>
                            <span class="hr-sep">/</span>
                            <span class="hr-count">67 reviews no</span>
                            <span class="g-wordmark"><span class="g-G">G</span><span class="g-o1">o</span><span class="g-o2">o</span><span class="g-g">g</span><span class="g-l">l</span><span class="g-e">e</span></span>
                        </a>
                    </div>
                </div>
            </div>

            <div id="catalogo" class="md-catalog">
                <div class="md-catalog__heading">
                    <h2 class="md-section-title">Catálogo de Formação <span>em Inteligência Artificial</span></h2>
                </div>
                <?php foreach ( self::CATALOG as $group => $slugs ) : ?>
                    <div class="md-group">
                        <div class="md-group__heading">
                            <h3 class="md-group__title"><?php echo esc_html( $group ); ?></h3>
                        </div>
                        <section class="filter-result">
                            <div class="container">
                                <div class="row">
                                    <?php foreach ( $slugs as $slug ) {
                                        echo EDIT_Pillar_Courses::render_card( $slug, $group );
                                    } ?>
                                </div>
                            </div>
                        </section>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php echo EDIT_Pillar_Tutors::render( self::TUTORS, 'Os tutores da EDIT. usam IA em projetos reais — desde geração de imagem e vídeo para cinema, a agentes inteligentes para marketing, a UX research assistido por LLMs.' ); ?>

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
                <h2>Pronto para a próxima formação em <span>Inteligência Artificial</span>?</h2>
                <p>Os nossos consultores ajudam-te a escolher o percurso certo — bootcamp avançado, IA aplicada à tua indústria ou workshop pontual — e a tirar partido do Cheque Formação + Digital antes de 30 de Junho 2026.</p>
                <button type="button" class="md-btn md-btn--primary md-btn--lg swipe-cta" data-contact="true"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Falar com um consultor</span></button>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
