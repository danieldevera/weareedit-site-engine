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
        update_post_meta( $page_id, 'rank_math_description', '8 formações em Programação: Front-End Engineer, Data Engineering, Webflow, Prompt Engineering, automação com IA. DGERT certificada, Cheque Formação + Digital elegível.' );
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
        <section class="md-pillar">
            <div class="md-hero">
                <div class="md-hero__inner">
                    <p class="md-hero__eyebrow">FORMAÇÃO ESPECIALIZADA</p>
                    <h1 class="md-hero__title">Programação<br><span>na EDIT.</span></h1>
                    <p class="md-hero__lede">8 formações DGERT certificadas em Front-End Engineering, Data Engineering, Webflow, Prompt Engineering e automação com IA. Tutores que são developers em activo em Lisboa, Porto e remote.</p>
                    <div class="md-hero__bar">
                        <span class="md-pill">DGERT nº 18391</span>
                        <span class="md-pill">★ 4.1 / 67 reviews Google</span>
                        <span class="md-pill">600+ alumni colocados</span>
                        <span class="md-pill">Lisboa · Porto · Remote</span>
                    </div>
                    <div class="md-hero__cta">
                        <a class="md-btn md-btn--primary" href="#catalogo">Ver os 8 cursos</a>
                        <button type="button" class="md-btn md-btn--ghost swipe-cta" data-contact="true"><span class="swipe-layer swipe-pink"></span><span class="swipe-layer swipe-teal"></span><span class="swipe-layer swipe-black"></span><span class="swipe-label">Falar com um consultor</span></button>
                    </div>
                </div>
            </div>

            <div id="catalogo" class="md-catalog">
                <h2 class="md-section-title">Catálogo de Formação <span>em Programação</span></h2>
                <?php foreach ( self::CATALOG as $group => $slugs ) : ?>
                    <div class="md-group">
                        <h3 class="md-group__title"><?php echo esc_html( $group ); ?></h3>
                        <div class="md-grid">
                            <?php foreach ( $slugs as $slug ) :
                                $post = get_page_by_path( $slug, OBJECT, 'formacao' );
                                if ( ! $post ) continue;
                                $title = wp_strip_all_tags( get_the_title( $post ) );
                                $url   = get_permalink( $post );
                                $excerpt = wp_strip_all_tags( get_the_excerpt( $post ) );
                                $excerpt = $excerpt ? mb_substr( $excerpt, 0, 120 ) . '…' : '';
                            ?>
                                <a class="md-card" href="<?php echo esc_url( $url ); ?>">
                                    <h4 class="md-card__title"><?php echo esc_html( $title ); ?></h4>
                                    <?php if ( $excerpt ) : ?>
                                        <p class="md-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
                                    <?php endif; ?>
                                    <span class="md-card__arrow">→</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="faq" class="md-faq">
                <h2 class="md-section-title">Perguntas <span>Frequentes</span></h2>
                <div class="md-faq__list">
                    <?php foreach ( self::FAQ as $f ) : ?>
                        <details class="md-faq__item">
                            <summary class="md-faq__q"><?php echo esc_html( $f['q'] ); ?></summary>
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
