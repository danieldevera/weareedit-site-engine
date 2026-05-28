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
        <section class="md-pillar">
            <div class="md-hero">
                <div class="md-hero__inner">
                    <p class="md-hero__eyebrow">FORMAÇÃO ESPECIALIZADA</p>
                    <h1 class="md-hero__title">Data Science<br><span>na EDIT.</span></h1>
                    <p class="md-hero__lede">11 formações DGERT certificadas em Data Science, Engineering e Machine Learning. Bootcamps, cursos e workshops em Lisboa, Porto e remote. Alumni colocados em Farfetch, NOS, Sonae, EDP e mais.</p>
                    <div class="md-hero__bar">
                        <span class="md-pill">DGERT nº 18391</span>
                        <span class="md-pill">★ 4.1 / 67 reviews Google</span>
                        <span class="md-pill">600+ alumni colocados</span>
                        <span class="md-pill">Lisboa · Porto · Remote</span>
                    </div>
                    <div class="md-hero__cta">
                        <a class="md-btn md-btn--primary" href="#catalogo">Ver os 11 cursos</a>
                        <a class="md-btn md-btn--ghost" data-contact="true" href="#">Falar com um consultor</a>
                    </div>
                </div>
            </div>

            <div id="catalogo" class="md-catalog">
                <h2 class="md-section-title">Catálogo de Formação <span>em Data Science</span></h2>
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
                <h2>Pronto para a próxima formação em <span>Data Science</span>?</h2>
                <p>Os nossos consultores ajudam-te a escolher o percurso certo — Data Science, Engineering ou Machine Learning — e a tirar partido do Cheque Formação + Digital antes de 30 de Junho 2026.</p>
                <a class="md-btn md-btn--primary md-btn--lg" data-contact="true" href="#">Falar com um consultor</a>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
