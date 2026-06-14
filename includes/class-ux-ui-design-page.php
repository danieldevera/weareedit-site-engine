<?php
/**
 * UX/UI Design pillar page — /ux-ui-design/
 *
 * Strategic context (audit 2026-05-27): EDIT. ranks #5 for "curso ux ui design
 * lisboa" — visible but mid-pack. LSD #1, PwC #2, FLAG #3 dominate. UXUI Remote
 * is the #2 lead generator. Tutor profile depth (Catarina + Daniel + others)
 * is the moat competitors can't easily copy.
 *
 * Mirror of EDIT_Marketing_Digital_Page / EDIT_Data_Science_Page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_UX_UI_Design_Page {

    // Initially tried 'ux-ui-design' but WP _wp_old_slug auto-301'd to the
    // legacy /profissoes/ux-ui-designer/ post. Switched to 'curso-uxui-design'
    // which matches the site's existing course slug convention (curso-uxui-*).
    const SLUG       = 'curso-uxui-design';
    const TITLE      = 'UX/UI Design — Formação Especializada na EDIT.';
    const OPTION_KEY = 'edit_seo_fix_ux_ui_design_page_id';
    const SHORTCODE  = 'edit_ux_ui_design_pillar';

    const TUTORS = [ 'mao-barros', 'miguel-rao-vieira', 'daniel-devera', 'naiara-back' ];
    /** Keywords matched against tutors' ACF profile_knowsabout (comma-separated areas). */
    const TUTOR_AREAS = [ 'UX Design', 'UI Design', 'UX/UI', 'UX', 'UI', 'Product Designer', 'Product Design', 'Design Thinking', 'UX Research', 'User Experience', 'Researcher', 'Research', 'Figma', 'Design Ops', 'Designer de Serviço', 'Service Designer', 'Inteligência Artificial', 'Artificial Intelligence', 'Generative AI', 'IA Generativa', 'IA Aplicada' ];

    const CATALOG = [
        'Bootcamps' => [
            'bootcamp-figma-remote',
            'bootcamp-ia-para-ux-research',
        ],
        'Cursos' => [
            'curso-uxui-online',
            'curso-uxui-porto',
            'curso-online-ux-design-foundations',
            'design-ops',
        ],
        'Workshops' => [
            'workshop-ux-research',
            'workshop-design-thinking',
            'remote-learning-workshop-design-thinking-foundations',
            'remote-learning-workshop-ux-writing-foundations',
            'remote-learning-workshop-emotional-design-design-psychology',
            'workshop-design-de-futuros',
        ],
    ];

    const FAQ = [
        [
            'q' => 'Qual curso de UX/UI Design é o mais adequado para mim?',
            'a' => 'Se estás a começar, o <strong>UX Design Foundations</strong> dá-te a visão base em formato online. Para o percurso completo, escolhe o <strong>Curso UX/UI Online</strong> ou <strong>UX/UI Porto</strong> (presencial). Já trabalhas em design e queres especializar-te? Olha para o <strong>Bootcamp Figma</strong> ou <strong>Design Ops</strong>. Workshops curtos (UX Research, Design Thinking, UX Writing) são ideais para upskilling pontual.',
        ],
        [
            'q' => 'Preciso de saber desenhar ou ter formação em design?',
            'a' => 'Não. UX/UI Design é muito mais sobre pensamento estruturado, pesquisa com utilizadores, hipóteses e iteração do que sobre habilidade de desenho. O Foundations e o Curso UX/UI começam do zero e assumem zero experiência. Profissionais com background em marketing, gestão de produto, programação ou ciências sociais transitam para UX com sucesso através destes percursos.',
        ],
        [
            'q' => 'Que ferramentas vou aprender?',
            'a' => 'O currículo gira em torno de <strong>Figma</strong> (a ferramenta dominante na indústria), com sessões dedicadas em <strong>FigJam, Maze, Hotjar, Optimal Workshop, Lookback, Notion</strong>. Para Design Ops e bootcamps avançados também cobrimos <strong>Framer, Webflow, prototipagem com IA</strong> e integrações com sistemas de design.',
        ],
        [
            'q' => 'Quanto tempo demora cada formação?',
            'a' => 'Bootcamps Remote duram 8 semanas em formato intensivo. Cursos completos (UXUI Online, UXUI Porto, UX Foundations) têm 90 a 120 horas distribuídas por 3 a 4 meses em regime pós-laboral. Workshops são módulos curtos (6 a 16 horas) sobre tópicos específicos.',
        ],
        [
            'q' => 'Posso usar o Cheque Formação + Digital?',
            'a' => 'Sim. Todos os cursos de UX/UI Design da EDIT. são elegíveis ao Cheque Formação + Digital até <strong>30 de Junho de 2026</strong>. A EDIT. é entidade formadora certificada pela DGERT (nº 18391).',
        ],
        [
            'q' => 'A EDIT. ajuda na colocação profissional?',
            'a' => 'Sim. A <a href="https://disruptivejobs.io/" target="_blank" rel="noopener">Disruptive Jobs</a> — a agência de recrutamento do grupo EDIT. — coloca alunos de UX/UI em produtos e agências como Farfetch, Worten, Bliss Applications, Glintt, NOS, EDP e muitas outras. Os tutores são UX leads em activo, abrindo portas reais.',
        ],
    ];

    /**
     * Long-form editorial intro between hero and catalog. Conservative
     * variant: alumni employers shown via the modular logo wall
     * (EDIT_Alumni_Employers component) rather than inline name-drop.
     */
    const INTRO = [
        'eyebrow'  => 'PORQUÊ UX/UI DESIGN?',
        'title'    => 'A formação que liga design a <span>produto e negócio</span>',
        'lead'     => 'O design de produto digital evoluiu de "fazer ecrãs bonitos" para arquitetar a experiência ponta-a-ponta — research, fluxos, interaction, visual, design systems e métricas de adoção. As scale-ups portuguesas e marcas internacionais com hubs em Lisboa e Porto recrutam designers que combinam sensibilidade visual com pensamento de produto e literacia técnica. A EDIT. forma esses perfis com programas DGERT-certificados em Lisboa, Porto e online — leccionados por product designers em activo.',
        'blocks'   => [
            [
                'title' => 'O que cobre hoje UX/UI Design',
                'body'  => 'Em 2026, UX/UI Design integra cinco competências essenciais: <strong>user research</strong> (entrevistas, testes de usabilidade, surveys, análise comportamental), <strong>information architecture e interaction design</strong> (fluxos, navegação, microinterações, prototipagem em Figma), <strong>visual design e design systems</strong> (tipografia, cor, componentes reutilizáveis, tokens), <strong>UX writing e content design</strong> (microcopy, voice & tone), e <strong>colaboração com produto e engenharia</strong> (handoff, métricas, A/B testing). Os designers mais valorizados dominam toda a cadeia — não apenas Figma.',
            ],
            [
                'title' => 'Para quem é esta formação',
                'body'  => 'Os nossos programas foram desenhados para três perfis. <strong>Designers visuais em transição</strong> (gráficos, ilustradores, web designers) que querem mover-se para produto digital. <strong>Profissionais não-designers em transição de carreira</strong> (engenheiros, marketers, gestores, investigadores) que querem fazer da experiência do utilizador o centro do trabalho. <strong>Product managers e founders</strong> que precisam de literacia de design suficiente para decidir prioridades de UX, brief de designers e validar entregas.',
            ],
            [
                'title' => 'Onde vão trabalhar os alumni',
                'body'  => 'Os alunos saem para roles em product designer, UX designer, UI designer, design lead e UX researcher. Os principais sectores recrutadores em Portugal: <strong>scale-ups tech</strong> (product teams), <strong>banca digital</strong> (mobile apps, internet banking), <strong>telcos</strong> (digital product), <strong>retalho e e-commerce</strong> (UX de conversão) e <strong>estúdios de design e agências</strong> (interaction + visual). Faixas salariais 2026 (referência mercado PT): <strong>€22-30K</strong> para junior UX/UI; <strong>€35-48K</strong> para product designer com 2-4 anos; <strong>€55-75K+</strong> para senior em scale-up; <strong>€65-90K</strong> para design lead.',
            ],
            [
                'title' => 'Porquê a EDIT.',
                'body'  => '<ul><li><strong>DGERT-certificada (nº 18391)</strong> — todas as formações elegíveis para SIFIDE (crédito fiscal até 35% sobre o investimento em formação).</li><li><strong>Tutores em activo</strong> — product designers, design leads e UX researchers que trabalham na área, não académicos.</li><li><strong>Briefs reais sobre produtos reais</strong> — projetos com requisitos de negócio autênticos, não exercícios fictícios.</li><li><strong>4.1 ★ / 67 reviews no Google</strong> — feedback verificável dos alumni de Lisboa e Porto.</li><li><strong>Disruptive Jobs</strong> — agência de recrutamento própria da EDIT., dedicada a ligar alunos a marcas. Mais do que formação: um pipeline de carreira.</li></ul>',
            ],
        ],
        'cta_lead' => 'Abaixo estão os programas activos em UX/UI Design — Bootcamps intensivos para entrada na área, Cursos completos para especialização profunda em Lisboa, Porto ou Remote, e Workshops curtos para upskilling específico. Todos elegíveis para SIFIDE.',
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
                // Slug drift — class constant changed. Rename the existing
                // post so it serves at the new URL.
                if ( $post->post_name !== self::SLUG ) {
                    wp_update_post( [
                        'ID'        => $stored_id,
                        'post_name' => self::SLUG,
                    ] );
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
            error_log( 'EDIT_UX_UI_Design_Page: page creation failed — ' . $page_id->get_error_message() );
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
        update_post_meta( $page_id, 'rank_math_title',       'Curso UX/UI Design Lisboa & Porto | EDIT. — 12 Programas DGERT' );
        update_post_meta( $page_id, 'rank_math_description', '12 formações em UX/UI Design, Figma, UX Research e Design Thinking. DGERT certificada, Cheque Formação + Digital elegível. Lisboa, Porto e online. Tutores UX leads em activo.' );
        update_post_meta( $page_id, 'rank_math_focus_keyword', 'curso ux ui design' );
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
            'description' => 'Catálogo completo de formações em UX/UI Design, Figma, UX Research e Design Thinking da EDIT.',
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
                    <h1 class="md-hero__title md-hero__title--xl">UX/UI Design<span class="h1-dot h1-dot-pink">.</span><br>Inteligência Artificial<span class="h1-dot h1-dot-teal">.</span></h1>
                    <p class="md-hero__lede md-hero__lede--yellow">12 formações DGERT-certificadas em Lisboa, Porto e online. Bootcamps, cursos e workshops em UX/UI Design, Figma e Research — leccionados por UX leads em activo nas marcas que mais recrutam talento digital em Portugal. Programas SIFIDE-elegíveis.</p>
                    <div class="md-hero__cta">
                        <a class="btn btn-yellow swipe-cta" href="#catalogo"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Ver os 12 cursos</span></a>
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
                    <h2 class="md-section-title">Catálogo de Formação <span>em UX/UI Design</span></h2>
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

            <?php echo EDIT_Pillar_Tutors::render_by_area( self::TUTOR_AREAS, 'Os tutores da EDIT. são UX leads, product designers e research specialists em activo nos produtos digitais que recrutam talento UX em Portugal.', 20, self::TUTORS ); ?>

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
                <h2>Pronto para a próxima formação em <span>UX/UI Design</span>?</h2>
                <p>Os nossos consultores ajudam-te a escolher o percurso certo — Foundations, Curso completo ou Bootcamp Figma — e a tirar partido do Cheque Formação + Digital antes de 30 de Junho 2026.</p>
                <button type="button" class="md-btn md-btn--primary md-btn--lg swipe-cta" data-contact="true"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Falar com um consultor</span></button>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
