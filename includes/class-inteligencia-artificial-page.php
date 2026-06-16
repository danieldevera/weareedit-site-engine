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
    /** Keywords matched against tutors' ACF profile_knowsabout (comma-separated areas). */
    const TUTOR_AREAS = [ 'Inteligência Artificial', 'Artificial Intelligence', 'Generative AI', 'IA Generativa', 'IA Aplicada', 'Machine Learning', 'LLM', 'Prompt Engineering', 'AI Engineering', 'AI manager', 'AI Driven', 'AI Marketing', 'Gen AI', 'Agentes Inteligentes', 'Data Science', 'Advanced Analytics' ];

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

    /**
     * Long-form editorial intro between hero and catalog. Conservative
     * variant — alumni employers shown via the modular logo wall
     * (EDIT_Alumni_Employers component) instead of inline name-drop.
     */
    const INTRO = [
        'eyebrow'  => 'PORQUÊ INTELIGÊNCIA ARTIFICIAL?',
        'title'    => 'A formação que torna a IA <span>uma vantagem prática, não uma promessa</span>',
        'lead'     => 'A Inteligência Artificial é a tecnologia mais transformadora desde a internet — e ao mesmo tempo a mais difícil de adotar bem. Saber usar ChatGPT, Claude ou Gemini para uma tarefa pontual é trivial; integrar IA generativa em fluxos de trabalho, criar agentes que executam tarefas reais, e construir produtos que aprendem com utilizadores são competências que mudam carreiras. A EDIT. forma essa geração com programas DGERT-certificados em Lisboa, Porto e online — leccionados por profissionais que constroem com IA todos os dias.',
        'blocks'   => [
            [
                'title' => 'O que cobre hoje IA aplicada',
                'body'  => 'Em 2026, Inteligência Artificial profissional cobre cinco frentes: <strong>prompt engineering avançado</strong> (chain-of-thought, few-shot, structured outputs), <strong>agentes inteligentes</strong> (multi-step workflows, tool use, function calling), <strong>RAG e knowledge bases</strong> (vector stores, embeddings, retrieval pipelines), <strong>integração de IA em produto</strong> (APIs OpenAI/Anthropic/Google, fine-tuning, custos), e <strong>IA generativa para conteúdo e operações</strong> (Midjourney, Stable Diffusion, geração de vídeo, automação com n8n e Make). Os profissionais que se destacam dominam o fluxo completo — não só conversam com modelos.',
            ],
            [
                'title' => 'Para quem é esta formação',
                'body'  => 'Os nossos programas foram desenhados para três perfis. <strong>Profissionais não-técnicos em transformação</strong> (marketers, gestores, criativos, jornalistas, consultores) que querem incorporar IA no fluxo diário e na carreira. <strong>Engenheiros e designers em activo</strong> que precisam de construir produtos com LLMs, RAG, agentes ou automação avançada. <strong>Founders e líderes</strong> que querem literacia técnica suficiente para decidir stack de IA, contratar perfis certos e medir ROI real de iniciativas com IA.',
            ],
            [
                'title' => 'Onde vão trabalhar os alumni',
                'body'  => 'Os alunos saem com competências aplicáveis em qualquer função — porque IA é hoje uma layer horizontal, não um cargo específico. Os principais sectores recrutadores em Portugal: <strong>agências de comunicação e marketing</strong> (automação criativa, content production), <strong>scale-ups tech</strong> (LLM apps, RAG em produto), <strong>media e publishing</strong> (content workflows assistidos por IA), <strong>consultoras</strong> (transformação digital com IA) e o <strong>ecossistema de freelancers e founders</strong> a construir produtos AI-native. Faixas salariais 2026 (referência mercado PT): <strong>€30-45K</strong> para AI specialist sem experiência prévia; <strong>€45-65K</strong> para AI consultor com 2-4 anos; <strong>€65-100K+</strong> para AI lead ou senior AI engineer em scale-up.',
            ],
        ],
        'cta_lead' => 'Abaixo estão os programas activos em Inteligência Artificial — Bootcamps intensivos para profissionais não-técnicos, Cursos completos para construção de produtos com IA, e Workshops curtos para domínio de ferramentas específicas. Todos elegíveis para SIFIDE.',
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
                $c_desc = wp_strip_all_tags( $post->post_excerpt );
                if ( $c_desc === '' ) {
                    $c_desc = 'Programa de formação certificado DGERT da EDIT. — ' . wp_strip_all_tags( get_the_title( $post ) ) . '.';
                }
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'item'     => [
                        '@type'       => 'Course',
                        '@id'         => get_permalink( $post ) . '#course',
                        'url'         => get_permalink( $post ),
                        'name'        => wp_strip_all_tags( get_the_title( $post ) ),
                        'description' => $c_desc,
                        'provider'    => [
                            '@type' => 'EducationalOrganization',
                            '@id'   => home_url( '/' ) . '#organization',
                            'name'  => 'EDIT. — Disruptive Digital Education',
                            'url'   => home_url( '/' ),
                        ],
                    ],
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
        <section class="md-pillar md-pillar--light">
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

            <section class="md-intro">
                <div class="md-intro__inner">
                    <div class="md-intro__header">
                        <div class="md-intro__header-left">
                            <p class="md-intro__eyebrow"><?php echo esc_html( self::INTRO['eyebrow'] ); ?></p>
                            <h2 class="md-intro__title"><?php echo wp_kses_post( self::INTRO['title'] ); ?></h2>
                        </div>
                        <p class="md-intro__lead"><?php echo wp_kses_post( self::INTRO['lead'] ); ?></p>
                    </div>
                    <div class="md-intro__blocks">
                        <?php foreach ( self::INTRO['blocks'] as $b ) : ?>
                            <div class="md-intro__block">
                                <h3 class="md-intro__block-title"><?php echo esc_html( $b['title'] ); ?></h3>
                                <div class="md-intro__block-body"><?php echo wp_kses_post( $b['body'] ); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="md-intro__alumni-wall">
                        <?php echo EDIT_Alumni_Employers::render( 'wall' ); ?>
                    </div>
                    <p class="md-intro__cta-lead"><?php echo wp_kses_post( self::INTRO['cta_lead'] ); ?></p>
                </div>
            </section>

            <div id="catalogo" class="md-catalog">
                <div class="md-catalog__heading">
                    <div class="md-section-header">
                        <div class="md-section-header__left">
                            <p class="md-section-header__eyebrow">11 programas DGERT-certificados</p>
                            <h2 class="md-section-header__title">Catálogo de Formação <span>em Inteligência Artificial</span></h2>
                        </div>
                        <p class="md-section-header__lead">Bootcamps intensivos para career-changers, cursos completos para especialização profunda, e workshops curtos para upskilling pontual. Programas SIFIDE-elegíveis, leccionados por profissionais que constroem com IA generativa, agentes e machine learning todos os dias.</p>
                    </div>
                </div>
                <?php foreach ( self::CATALOG as $group => $slugs ) : ?>
                    <div class="md-group">
                        <div class="md-group__heading">
                            <h3 class="md-group__title"><?php echo esc_html( $group ); ?></h3>
                        </div>
                        <section class="filter-result">
                            <div class="container">
                                <div class="row">
                                    <?php foreach ( EDIT_Pillar_Courses::sort_slugs_dated_first( $slugs ) as $slug ) {
                                        echo EDIT_Pillar_Courses::render_card( $slug, $group );
                                    } ?>
                                </div>
                            </div>
                        </section>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- EDIT. para Empresas — cinematic banner driving traffic to empresas subdomain -->
            <section class="md-empresas-cta">
                <div class="md-empresas-cta__bg" style="background-image:url('<?php echo esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/img/empresas-hero.webp' ); ?>');" aria-hidden="true"></div>
                <div class="md-empresas-cta__veil" aria-hidden="true"></div>
                <div class="md-empresas-cta__inner">
                    <p class="md-empresas-cta__eyebrow">EDIT. PARA EMPRESAS</p>
                    <h2 class="md-empresas-cta__title">Sobe o nível da tua equipa digital<span class="md-dot md-dot--pink">.</span></h2>
                    <p class="md-empresas-cta__lede">Bootcamps, cursos e workshops à medida da tua organização. DGERT-certificados, SIFIDE-elegíveis, leccionados por marketers em activo nas marcas que recrutam talento digital em Portugal.</p>
                    <a class="btn btn-yellow swipe-cta" href="https://empresas.weareedit.io/" target="_blank" rel="noopener"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Conhecer EDIT. Empresas →</span></a>
                </div>
            </section>

            <?php echo EDIT_Pillar_Tutors::render_by_area( self::TUTOR_AREAS, 'Os tutores da EDIT. usam IA em projetos reais — desde geração de imagem e vídeo para cinema, a agentes inteligentes para marketing, a UX research assistido por LLMs.', 20, self::TUTORS ); ?>

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
