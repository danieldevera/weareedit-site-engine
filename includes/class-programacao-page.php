<?php
/**
 * Programação pillar page — /curso-programacao/
 *
 * Strategic context (audit 2026-05-27): EDIT.'s programming/engineering
 * offerings (Front-End Engineer, Data Engineering, Webflow, Prompt
 * Engineering, SEO Engineering) were scattered with no consolidating hub.
 * The Full Stack slot is occupied by Front-End Engineer after the
 * 2026 rename. This pillar gathers the engineering-side narrative.
 *
 * Mirror of the four previous pillar classes.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Programacao_Page {

    const SLUG       = 'curso-programacao';
    const TITLE      = 'Programação — Formação Especializada na EDIT.';
    const OPTION_KEY = 'edit_seo_fix_programacao_page_id';
    const SHORTCODE  = 'edit_programacao_pillar';

    const TUTORS = [ 'daniel-devera', 'naiara-back', 'miguel-rao-vieira', 'mao-barros' ];
    /** Keywords matched against tutors' ACF profile_knowsabout (comma-separated areas). */
    const TUTOR_AREAS = [ 'Programação', 'Front-End', 'Frontend', 'Back-End', 'Backend', 'Full-Stack', 'Engineering', 'Engineer', 'Developer', 'Development', 'DevOps', 'Webflow', 'Web Dev', 'JavaScript', 'React', 'Python', 'Data Engineering', 'Inteligência Artificial', 'Artificial Intelligence', 'AI Engineering', 'Prompt Engineering' ];

    const CATALOG = [
        'Cursos' => [
            'curso-online-front-end-engineer',
            'data-engineering-lisboa',
            'data-engineering-porto',
        ],
        'Bootcamps' => [
            'bootcamp-webflow-2-0-cria-sites-completos-ia',
            'bootcamp-prompt-engineering-2',
            'bootcamp-python-para-data',
            'seo-engineering-automacao-claude-code',
            'bootcamp-criacao-de-sites',
            'bootcamp-tailwind-css',
        ],
        'Workshops' => [
            'workshop-introducao-a-programacao',
        ],
    ];

    const FAQ = [
        [
            'q' => 'Qual curso de Programação é o mais adequado para mim?',
            'a' => 'Se nunca programaste, começa pelo <strong>Workshop Introdução à Programação</strong>. Para front-end (HTML/CSS/JS/React), o <strong>Curso Front-End Engineer</strong> é o percurso completo. Quem quer pipelines de dados (Python/SQL/Airflow/dbt) escolhe <strong>Data Engineering Lisboa</strong> ou <strong>Porto</strong>. Para no-code/low-code com IA, o <strong>Bootcamp Webflow 2.0</strong>. Para automação assistida por IA, <strong>SEO Engineering com Claude Code</strong> ou <strong>Agentes Inteligentes</strong>.',
        ],
        [
            'q' => 'Preciso de experiência prévia em código?',
            'a' => 'Não para começar. O <strong>Workshop Introdução à Programação</strong> e o <strong>Front-End Engineer</strong> assumem zero experiência. Bootcamps mais avançados (Data Engineering, Python para Data, Prompt Engineering) recomendam alguma base — pelo menos confortável com lógica e ferramentas de software.',
        ],
        [
            'q' => 'Que linguagens e stacks vou aprender?',
            'a' => 'Por percurso: <strong>Front-End Engineer</strong> — HTML, CSS, JavaScript, TypeScript, React, Next.js. <strong>Data Engineering</strong> — Python, SQL, Airflow, dbt, Snowflake, BigQuery. <strong>Webflow 2.0</strong> — Webflow + Framer + integrações no-code com IA. <strong>Prompt Engineering / SEO Engineering</strong> — Claude Code, agentes, automações com LLMs.',
        ],
        [
            'q' => 'Quanto tempo demoram?',
            'a' => 'Bootcamps Remote duram 8 a 12 semanas em formato intensivo. Cursos completos (Front-End Engineer, Data Engineering) têm 180 a 240 horas distribuídas por 5 a 6 meses em regime pós-laboral. Workshops são módulos curtos (16 a 32 horas).',
        ],
        [
            'q' => 'Posso usar o Cheque Formação + Digital?',
            'a' => 'Sim. Todos os cursos de Programação da EDIT. são elegíveis ao Cheque Formação + Digital até <strong>30 de Junho de 2026</strong>. A EDIT. é entidade formadora certificada pela DGERT (nº 18391).',
        ],
        [
            'q' => 'A EDIT. ajuda na colocação como Developer?',
            'a' => 'Sim. A <a href="https://disruptivejobs.io/" target="_blank" rel="noopener">Disruptive Jobs</a> liga alunos de programação a equipas de produto e agências — front-end, full-stack, data engineering. Empresas que recrutam talento técnico da EDIT.: Farfetch, NOS, Worten, Bliss Applications, Glintt, OutSystems, EDP, entre outras.',
        ],
    ];

    /**
     * Long-form editorial intro between hero and catalog. Conservative
     * variant — alumni employers shown via the modular logo wall
     * (EDIT_Alumni_Employers component) instead of inline name-drop.
     */
    const INTRO = [
        'eyebrow'  => 'PORQUÊ PROGRAMAÇÃO?',
        'title'    => 'A formação que prepara developers para <span>produto real, não exercícios académicos</span>',
        'lead'     => 'Programar é hoje a competência que mais separa quem decide produtos de quem os executa, e a que tem o ROI de carreira mais alto em qualquer indústria. As scale-ups portuguesas e os hubs internacionais em Lisboa e Porto recrutam developers que pensam em produto, não só código — e que entregam features, não tickets fechados. A EDIT. forma esses developers com programas DGERT-certificados em Lisboa, Porto e online — leccionados por engineers em activo.',
        'blocks'   => [
            [
                'title' => 'O que cobre hoje programação profissional',
                'body'  => 'Em 2026, ser developer é dominar cinco camadas integradas: <strong>frontend moderno</strong> (TypeScript, React/Next.js, design systems, performance), <strong>backend e APIs</strong> (Node.js, Python/Django, PHP/Laravel, autenticação, REST/GraphQL), <strong>bases de dados</strong> (PostgreSQL, MongoDB, Redis, modelação relacional), <strong>cloud e DevOps</strong> (AWS, GCP, Docker, CI/CD, observabilidade), e <strong>IA aplicada ao código</strong> (Cursor, GitHub Copilot, Claude Code, agentes de programação). Os perfis full-stack que dominam toda a cadeia são os mais procurados — porque entregam features end-to-end.',
            ],
            [
                'title' => 'Para quem é esta formação',
                'body'  => 'Os nossos programas foram desenhados para três perfis. <strong>Profissionais em mudança de carreira</strong> (publicidade, gestão, finanças, ciências exactas) que querem entrar em engenharia de software com método. <strong>Developers self-taught ou bootcamp grads</strong> que querem subir nível com fundamentos sólidos e práticas profissionais. <strong>Product managers, designers e líderes técnicos</strong> que querem literacia de programação suficiente para colaborar com engenharia, validar entregas e tomar decisões de stack.',
            ],
            [
                'title' => 'Onde vão trabalhar os alumni',
                'body'  => 'Os alunos saem para roles em frontend developer, backend developer, full-stack engineer, mobile developer e DevOps. Os principais sectores recrutadores em Portugal: <strong>scale-ups tech</strong> (product engineering), <strong>banca digital</strong> (mobile apps, internet banking, API teams), <strong>consultoras tech</strong> (delivery em large clients), <strong>telcos</strong> (produto digital) e <strong>ecossistema de scale-ups internacionais com hubs em Lisboa e Porto</strong>. Faixas salariais 2026 (referência mercado PT): <strong>€25-35K</strong> para junior developer; <strong>€40-55K</strong> para mid developer com 2-4 anos; <strong>€55-80K+</strong> para senior em scale-up; <strong>€75-110K+</strong> para tech lead ou engineering manager.',
            ],
            [
                'title' => 'Porquê a EDIT.',
                'body'  => '<ul><li><strong>DGERT-certificada (nº 18391)</strong> — todas as formações elegíveis para SIFIDE (crédito fiscal até 35% sobre o investimento em formação).</li><li><strong>Tutores em activo</strong> — engineers, tech leads e CTOs que trabalham em produto real, não académicos.</li><li><strong>Projetos sobre stack moderno</strong> — usamos a stack que as empresas usam hoje, não tecnologia legacy.</li><li><strong>4.1 ★ / 67 reviews no Google</strong> — feedback verificável dos alumni de Lisboa e Porto.</li><li><strong>Disruptive Jobs</strong> — agência de recrutamento própria da EDIT., dedicada a ligar alunos a marcas. Mais do que formação: um pipeline de carreira.</li></ul>',
            ],
        ],
        'cta_lead' => 'Abaixo estão os programas activos em Programação — Bootcamps intensivos para entrada na área, Cursos completos para especialização profunda em Lisboa, Porto ou Remote, e Workshops curtos para upskilling específico. Todos elegíveis para SIFIDE.',
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
            error_log( 'EDIT_Programacao_Page: page creation failed — ' . $page_id->get_error_message() );
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
        update_post_meta( $page_id, 'rank_math_title',       'Curso Programação Web Lisboa & Porto | EDIT. — 8 Programas DGERT' );
        update_post_meta( $page_id, 'rank_math_description', '10 formações em Programação: Front-End Engineer, Data Engineering, Webflow, Prompt Engineering, automação com IA. DGERT certificada, SIFIDE-elegível.' );
        update_post_meta( $page_id, 'rank_math_focus_keyword', 'curso programação' );
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
            'description' => 'Catálogo completo de formações em Programação da EDIT. — Front-End, Data Engineering, Webflow, Prompt Engineering e automação com IA.',
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
                    <h1 class="md-hero__title md-hero__title--xl">Programação<span class="h1-dot h1-dot-pink">.</span><br>Inteligência Artificial<span class="h1-dot h1-dot-teal">.</span></h1>
                    <p class="md-hero__lede md-hero__lede--yellow">10 formações DGERT-certificadas em Lisboa, Porto e online. Bootcamps e workshops em Front-End Engineering, Data Engineering, Webflow e automação com IA — leccionados por developers em activo nas marcas que mais recrutam talento técnico em Portugal. Programas SIFIDE-elegíveis.</p>
                    <div class="md-hero__cta">
                        <a class="btn btn-yellow swipe-cta" href="#catalogo"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Ver os 10 cursos</span></a>
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
                    <h2 class="md-section-title">Catálogo de Formação <span>em Programação</span></h2>
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

            <?php echo EDIT_Pillar_Tutors::render_by_area( self::TUTOR_AREAS, 'Os tutores da EDIT. são front-end engineers, data engineers e developers em activo — em produtos, agências e em projetos próprios.', 20, self::TUTORS ); ?>

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
                <h2>Pronto para a próxima formação em <span>Programação</span>?</h2>
                <p>Os nossos consultores ajudam-te a escolher o percurso certo — Front-End, Data Engineering ou um bootcamp temático — e a tirar partido do Cheque Formação + Digital antes de 30 de Junho 2026.</p>
                <button type="button" class="md-btn md-btn--primary md-btn--lg swipe-cta" data-contact="true"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Falar com um consultor</span></button>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
