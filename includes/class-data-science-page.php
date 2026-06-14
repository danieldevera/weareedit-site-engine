<?php
/**
 * Data Science pillar page — /data-science/
 *
 * Strategic context (audit 2026-05-27): EDIT. invisible in top 10 for
 * "curso data science bootcamp portugal" despite DSBA being the #3 lead
 * generator. Le Wagon + CMU + CodeLabs dominate via topical concentration.
 *
 * Mirror of EDIT_Marketing_Digital_Page. Separate class because the catalog,
 * FAQ, and schema differ. CSS uses the `.ds-` prefix.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Data_Science_Page {

    const SLUG       = 'data-science';
    const TITLE      = 'Data Science — Formação Especializada na EDIT.';
    const OPTION_KEY = 'edit_seo_fix_data_science_page_id';
    const SHORTCODE  = 'edit_data_science_pillar';

    const TUTORS = [ 'carla-geraldes', 'naiara-back', 'daniel-devera', 'mao-barros' ];
    /** Keywords matched against tutors' ACF profile_knowsabout (comma-separated areas). */
    const TUTOR_AREAS = [ 'Data Science', 'Data &', 'Data Engineering', 'Data Analyst', 'Data Engineer', 'Data Analytics', 'Analytics', 'Analyst', 'Machine Learning', 'Business Intelligence', 'Power BI', 'Python', 'SQL', 'PMO', 'Inteligência Artificial', 'Artificial Intelligence', 'Generative AI', 'IA Generativa', 'IA Aplicada', 'AI manager' ];

    const CATALOG = [
        'Bootcamps' => [
            'bootcamp-dataops',
            'data-science-business-analytics-foundations-bootcamp-remote',
            'bootcamp-python-para-data',
        ],
        'Cursos' => [
            'curso-data-science-business-analytics-online',
            'curso-data-science-business-analytics-porto',
            'curso-machine-learning',
            'data-engineering-lisboa',
            'data-engineering-porto',
            'curso-google-analytics-4',
        ],
        'Workshops' => [
            'workshop-data-analytics-with-ai-2',
        ],
        'Crossover IA' => [
            'bootcamp-prompt-engineering-2',
        ],
    ];

    const FAQ = [
        [
            'q' => 'Preciso de saber Python ou SQL para começar?',
            'a' => 'Não. Os bootcamps <strong>DSBA Foundations</strong> e <strong>Python para Data</strong> começam do zero — assumem zero experiência em programação. Os cursos completos de Data Science e Data Engineering pressupõem alguma base; se vens de marketing, gestão ou áreas não-técnicas, o caminho recomendado é começar pelo Foundations.',
        ],
        [
            'q' => 'Qual é a diferença entre Data Science, Data Engineering e Machine Learning?',
            'a' => '<strong>Data Science</strong> extrai insights de dados (estatística, visualização, modelos preditivos). <strong>Data Engineering</strong> constrói os pipelines e infraestrutura que servem esses dados. <strong>Machine Learning</strong> é uma subárea de Data Science focada em modelos que aprendem com dados (classificação, regressão, redes neuronais). EDIT. tem percursos dedicados a cada uma.',
        ],
        [
            'q' => 'Quanto tempo demora cada formação?',
            'a' => 'Bootcamps Remote duram 8 a 12 semanas em formato intensivo. Cursos completos (DSBA Lisboa, Porto, Data Engineering) têm 120 a 180 horas distribuídas por 4 a 6 meses em regime pós-laboral. Workshops são módulos curtos (16 a 32 horas) sobre tópicos específicos como Analytics with AI.',
        ],
        [
            'q' => 'Posso usar o Cheque Formação + Digital?',
            'a' => 'Sim. Todos os cursos de Data Science da EDIT. são elegíveis ao Cheque Formação + Digital até <strong>30 de Junho de 2026</strong>. A EDIT. é entidade formadora certificada pela DGERT (nº 18391). Após essa data, aguardam-se novidades sobre o programa de substituição.',
        ],
        [
            'q' => 'A EDIT. ajuda na colocação profissional?',
            'a' => 'Sim. A EDIT. tem uma agência de recrutamento dedicada — a <a href="https://disruptivejobs.io/" target="_blank" rel="noopener">Disruptive Jobs</a> — focada em ligar alunos de Data Science e Engenharia a marcas como Farfetch, NOS, Sonae, Worten, Banco BPI, EDP e outras empresas que recrutam talento data.',
        ],
        [
            'q' => 'Que ferramentas e plataformas vou aprender?',
            'a' => 'O currículo cobre o stack moderno de Data: <strong>Python, SQL, Pandas, NumPy, Scikit-learn, PyTorch, TensorFlow, Airflow, dbt, Snowflake, BigQuery, Tableau, Looker Studio</strong>, e integrações com LLMs (ChatGPT, Claude) para acelerar análise e automação.',
        ],
    ];

    /**
     * Long-form editorial intro between hero and catalog. Same structure
     * as Marketing Digital's INTRO (v1.5.147 pattern). Conservative
     * variant: only LinkedIn-verified alumni employers (Farfetch + Worten)
     * are named individually. Other recruiter categories described by
     * sector. Salary bands referenced as Portuguese 2026 market norms.
     */
    const INTRO = [
        'eyebrow'  => 'PORQUÊ DATA SCIENCE?',
        'title'    => 'A formação que transforma dados em <span>decisões e produtos reais</span>',
        'lead'     => 'O Data Science deixou de ser uma função técnica isolada — tornou-se a camada que decide preços, recomendações, deteção de fraude, otimização de operações e a próxima geração de produtos com IA. Bancos, telcos, retalho e scale-ups portuguesas estão a recrutar profissionais que sabem extrair valor de dados a um ritmo sem precedentes. A EDIT. forma essa geração com programas DGERT-certificados em Lisboa, Porto e online — leccionados por data scientists e engineers em activo.',
        'blocks'   => [
            [
                'title' => 'O que cobre hoje Data Science',
                'body'  => 'Em 2026, Data Science abrange quatro camadas inseparáveis: <strong>análise descritiva</strong> (SQL, Excel, Power BI, Tableau, Looker Studio), <strong>análise preditiva e Machine Learning</strong> (Python, Pandas, Scikit-learn, PyTorch), <strong>Data Engineering</strong> (pipelines, ETL/ELT, Airflow, dbt, Snowflake, BigQuery), e <strong>aplicação de IA generativa</strong> (RAG, agents, LLM tooling). Os perfis mais procurados dominam pelo menos duas — analista que sabe modelar, engineer que sabe interpretar, data scientist que sabe operacionalizar.',
            ],
            [
                'title' => 'Para quem é esta formação',
                'body'  => 'Os nossos programas foram desenhados para três perfis. <strong>Profissionais técnicos em transição</strong> (engenheiros, finanças, gestão) que querem fazer da análise de dados o centro da carreira. <strong>Analistas e BI specialists em activo</strong> que precisam de subir para Machine Learning ou Data Engineering. <strong>Líderes de negócio</strong> (PMs, founders, marketing leads) que querem literacia de dados suficiente para decidir produtos e estratégia sem dependerem em absoluto das equipas técnicas.',
            ],
            [
                'title' => 'Onde vão trabalhar os alumni',
                'body'  => 'Os alunos saem para roles em data analyst, data scientist, ML engineer, data engineer e BI. Os principais sectores recrutadores em Portugal: <strong>banca</strong> (perfis de risco, fraude, customer analytics), <strong>telcos</strong> (segmentação, churn, BI), <strong>retalho e e-commerce</strong> (recomendação, demand forecasting), <strong>scale-ups tech</strong> (data engineering, ML em produto) e <strong>consultoras</strong> (data strategy, transformação digital). Alumni da EDIT. estão colocados em empresas como <strong>Farfetch</strong> e <strong>Worten</strong>, entre outras. Faixas salariais 2026 (referência mercado PT): <strong>€22-30K</strong> para junior data analyst; <strong>€35-50K</strong> para data scientist com 2-4 anos; <strong>€55-80K+</strong> para senior data scientist ou data engineer em scale-up.',
            ],
            [
                'title' => 'Porquê a EDIT.',
                'body'  => '<ul><li><strong>DGERT-certificada (nº 18391)</strong> — todas as formações elegíveis para SIFIDE (crédito fiscal até 35% sobre o investimento em formação).</li><li><strong>Tutores em activo</strong> — data scientists, ML engineers e data leads que trabalham na área, não académicos.</li><li><strong>Projetos reais sobre dados reais</strong> — usamos datasets autênticos com briefs de negócio, não exemplos sintéticos.</li><li><strong>4.1 ★ / 67 reviews no Google</strong> — feedback verificável dos alumni de Lisboa e Porto.</li><li><strong>Disruptive Jobs</strong> — agência de recrutamento própria da EDIT., dedicada a ligar alunos a marcas. Mais do que formação: um pipeline de carreira.</li></ul>',
            ],
        ],
        'cta_lead' => 'Abaixo estão os programas activos em Data Science e Data Engineering — Bootcamps intensivos para entrada na área, Cursos completos para especialização profunda em Lisboa, Porto ou Remote, e Workshops curtos para upskilling específico. Todos elegíveis para SIFIDE.',
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
            error_log( 'EDIT_Data_Science_Page: page creation failed — ' . $page_id->get_error_message() );
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
        update_post_meta( $page_id, 'rank_math_title',       'Curso Data Science Bootcamp Portugal | EDIT. — 11 Programas DGERT' );
        update_post_meta( $page_id, 'rank_math_description', '11 formações em Data Science, Engineering e Machine Learning. DGERT certificada, Cheque Formação + Digital elegível. Lisboa, Porto e remote. Alumni colocados na Farfetch, NOS, Sonae.' );
        update_post_meta( $page_id, 'rank_math_focus_keyword', 'curso data science' );
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
            'description' => 'Catálogo completo de formações em Data Science, Data Engineering e Machine Learning da EDIT.',
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
                    <h1 class="md-hero__title md-hero__title--xl">Data Science<span class="h1-dot h1-dot-pink">.</span><br>Inteligência Artificial<span class="h1-dot h1-dot-teal">.</span></h1>
                    <p class="md-hero__lede md-hero__lede--yellow">11 formações DGERT-certificadas em Lisboa, Porto e online. Bootcamps, cursos e workshops em Data Science, Engineering e Machine Learning com tutores em activo nas marcas que mais recrutam talento digital em Portugal. Programas SIFIDE-elegíveis.</p>
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
                    <h2 class="md-section-title">Catálogo de Formação <span>em Data Science</span></h2>
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

            <!-- EDIT. para Empresas — cinematic banner driving traffic to empresas subdomain -->
            <section class="md-empresas-cta">
                <div class="md-empresas-cta__bg" style="background-image:url('<?php echo esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/img/empresas-hero.jpg' ); ?>');" aria-hidden="true"></div>
                <div class="md-empresas-cta__veil" aria-hidden="true"></div>
                <div class="md-empresas-cta__inner">
                    <p class="md-empresas-cta__eyebrow">EDIT. PARA EMPRESAS</p>
                    <h2 class="md-empresas-cta__title">Sobe o nível da tua equipa digital<span class="md-dot md-dot--pink">.</span></h2>
                    <p class="md-empresas-cta__lede">Bootcamps, cursos e workshops à medida da tua organização. DGERT-certificados, SIFIDE-elegíveis, leccionados por marketers em activo nas marcas que recrutam talento digital em Portugal.</p>
                    <a class="btn btn-yellow swipe-cta" href="https://empresas.weareedit.io/" target="_blank" rel="noopener"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Conhecer EDIT. Empresas →</span></a>
                </div>
            </section>

            <?php echo EDIT_Pillar_Tutors::render_by_area( self::TUTOR_AREAS, 'Os tutores da EDIT. são data scientists, analytics leads e engineers em activo em produtos e empresas que processam dados a sério.', 20, self::TUTORS ); ?>

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
                <h2>Pronto para a próxima formação em <span>Data Science</span>?</h2>
                <p>Os nossos consultores ajudam-te a escolher o percurso certo — Data Science, Engineering ou Machine Learning — e a tirar partido do Cheque Formação + Digital antes de 30 de Junho 2026.</p>
                <button type="button" class="md-btn md-btn--primary md-btn--lg swipe-cta" data-contact="true"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Falar com um consultor</span></button>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
