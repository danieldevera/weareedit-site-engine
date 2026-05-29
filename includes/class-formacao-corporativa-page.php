<?php
/**
 * Formação Corporativa pillar page — /formacao-corporativa/
 *
 * B2B sales surface. Replaces (eventually) /formacao-in-company/ which
 * shipped with: generic hero, no CTA above the fold, logos buried,
 * no quantified value, repeated area sections, no case studies, no
 * Service / FAQPage schema. Daniel (2026-05-29): "current design is
 * not generating leads".
 *
 * Architecture mirrors the 5 SEO pillar pages (class-marketing-digital-page,
 * etc.): WP page at `/formacao-corporativa/` carries a single shortcode
 * token; this class renders the body + emits schema + enqueues CSS.
 *
 * Built in chunks 2026-05-29:
 *   Chunk 1 (this commit): scaffold + hero (with logos + CTA) + value
 *                          props + Why EDIT for B2B + FAQ stub + final CTA
 *   Chunk 2 (next): 5 áreas cards + 4-step process visual
 *   Chunk 3: case studies skeleton (6 clients, placeholder content)
 *   Chunk 4: real content swap as Daniel provides testimonials + metrics
 *   Chunk 5: Service / hasOfferCatalog / FAQPage / Org.makesOffer schema
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Formacao_Corporativa_Page {

    const SLUG       = 'formacao-corporativa';
    const TITLE      = 'Formação Corporativa para Empresas Líderes em Portugal — EDIT.';
    const OPTION_KEY = 'edit_seo_fix_formacao_corporativa_page_id';
    const SHORTCODE  = 'edit_formacao_corporativa_pillar';

    /**
     * Corporate clients featured on the page. Logo filenames match the
     * existing homepage `<ul class="logos-flex-container">` block so we
     * can reuse the same image URLs (already on CDN, already optimized).
     */
    const CLIENTS = [
        [ 'name' => 'Pfizer',   'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/pfizer-header.png' ],
        [ 'name' => 'FNAC',     'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/fnac-header.png' ],
        [ 'name' => 'Adidas',   'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/adidas-header.png' ],
        [ 'name' => 'Galp',     'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/galp-header.png' ],
        [ 'name' => 'Worten',   'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/worten-header.png' ],
        [ 'name' => 'CM Porto', 'logo' => 'https://weareedit.io/wp-content/uploads/2023/05/porto-cm-1.png' ],
    ];

    /**
     * Above-the-fold quantified value props. Numbers MUST be defensible
     * (no fabrication — Google penalises false claims). Placeholders flagged
     * as such for Chunk 4 swap.
     */
    const STATS = [
        [ 'number' => '6+',    'label' => 'Empresas líderes',     'sub' => 'Pfizer, FNAC, Adidas, Galp, Worten, CM Porto' ],
        [ 'number' => '1000+', 'label' => 'Formandos B2B',        'sub' => 'em equipas in-house e remote', 'placeholder' => true ],
        [ 'number' => '5',     'label' => 'Áreas DGERT',          'sub' => 'Marketing · UX/UI · Dev · Data · IA' ],
        [ 'number' => '100%',  'label' => 'SIFIDE + Cheque',      'sub' => 'Elegível para subsídios', ],
    ];

    /**
     * "Why EDIT. for B2B" — 4 differentiators that beat the generic copy
     * on the legacy page.
     */
    const VALUE_PROPS = [
        [
            'title' => 'Customizado para o vosso setor',
            'body'  => 'Programa desenhado em torno das ferramentas que a vossa equipa já usa — Adobe, Figma, Meta, Google, AWS, Salesforce, HubSpot, Looker.',
        ],
        [
            'title' => 'In-house ou Remote Learning',
            'body'  => 'Sessões nas vossas instalações em Lisboa/Porto, no nosso campus, ou 100% remoto em tempo real. Híbridos suportados.',
        ],
        [
            'title' => 'Tutores em activo, não académicos',
            'body'  => 'Cada formação é leccionada por profissionais que continuam a trabalhar no mercado — paid media managers, UX leads, data engineers, founders.',
        ],
        [
            'title' => 'DGERT-certificada · SIFIDE elegível',
            'body'  => 'Entidade Formadora Certificada DGERT nº 18391. Todas as formações são elegíveis para reembolso via SIFIDE, Cheque Formação + Digital, e POPH.',
        ],
    ];

    /**
     * 10 canonical B2B FAQs — chunk 4 will replace with the real questions
     * Daniel gets on discovery calls. Current questions are educated guesses
     * based on common enterprise training procurement concerns.
     */
    const FAQ = [
        [ 'q' => 'Quanto custa uma formação in-company?', 'a' => 'O investimento depende do número de formandos, da duração, e do nível de customização. Bootcamps de 40h para 10-15 formandos começam habitualmente em € 8.000. Pedimos sempre um briefing gratuito para enviar uma proposta detalhada — incluindo opções SIFIDE / Cheque Formação que podem reembolsar até 100% do valor.', 'placeholder' => true ],
        [ 'q' => 'Quanto tempo demora a desenhar e entregar um programa?', 'a' => 'Tipicamente: <strong>1-2 semanas</strong> de diagnóstico + desenho do programa, <strong>4-12 semanas</strong> de entrega (dependendo da carga horária), <strong>1 semana</strong> de avaliação de impacto. Para necessidades urgentes, conseguimos arrancar em 5 dias úteis com programas standard.', 'placeholder' => true ],
        [ 'q' => 'Posso usar SIFIDE, Cheque Formação ou POPH?', 'a' => 'Sim, todas as formações da EDIT. são elegíveis. A EDIT. é entidade formadora certificada pela DGERT (nº 18391). Apoiamos no processo de candidatura. <strong>Cheque Formação + Digital</strong> é particularmente vantajoso até <strong>30 de Junho 2026</strong>.', ],
        [ 'q' => 'As formações podem ser nas nossas instalações?', 'a' => 'Sim. Entregamos in-house em qualquer ponto de Portugal continental. Também temos campus em Lisboa (Av. Aquilino Ribeiro Machado) e Porto (Rua Alferes Malheiro) caso prefiram. Para equipas distribuídas, o formato remoto em tempo real funciona excepcionalmente bem.', ],
        [ 'q' => 'Qual o tamanho mínimo e máximo de grupo?', 'a' => 'O mínimo recomendado é <strong>6 formandos</strong> para garantir dinâmica de grupo. O máximo depende do formato: até <strong>15 em sala</strong>, até <strong>25 em remoto</strong>. Para programas de larga escala (50+), dividimos em coortes paralelas com sincronização semanal.', 'placeholder' => true ],
        [ 'q' => 'Como customizam o programa ao nosso negócio?', 'a' => 'Começamos com uma sessão de descoberta de 60 minutos para entender (a) o problema de negócio que querem resolver, (b) os perfis dos formandos, (c) as ferramentas e fluxos de trabalho atuais. Depois o lead instructor desenha um programa-piloto que validamos convosco antes do kick-off.', ],
        [ 'q' => 'Que indicadores de impacto medimos?', 'a' => 'Pré/pós-teste de competências, NPS por sessão, projeto final aplicado ao vosso contexto real, e seguimento a 3 e 6 meses para medir adoção das competências no dia-a-dia. O relatório final entrega métricas comparáveis para HR / L&D dashboards.', 'placeholder' => true ],
        [ 'q' => 'Os tutores assinam NDA?', 'a' => 'Sim. Todos os tutores e a equipa pedagógica da EDIT. assinam acordo de confidencialidade antes do kick-off, cobrindo dados, processos internos, e materiais proprietários partilhados durante o programa.', ],
        [ 'q' => 'Podemos contratar formação em inglês?', 'a' => 'Sim. Várias formações têm versão EN — Inteligência Artificial, Data Science, e UX/UI Design são as mais procuradas por equipas internacionais. Para empresas multi-país, entregamos sessões em paralelo PT/EN com syllabus unificado.', ],
        [ 'q' => 'Como começamos?', 'a' => 'Submetam o formulário no fundo da página com a área, número de formandos, e prazo desejado. Respondemos em <strong>24 horas úteis</strong> com uma proposta inicial ou um pedido de chamada de descoberta. Sem compromisso.', ],
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
            error_log( 'EDIT_Formacao_Corporativa_Page: page creation failed — ' . $page_id->get_error_message() );
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
        update_post_meta( $page_id, 'rank_math_title',         'Formação Corporativa Portugal | EDIT. — Pfizer, Adidas, FNAC, Galp formam aqui' );
        update_post_meta( $page_id, 'rank_math_description',   'Formação corporativa DGERT-certificada para equipas. Marketing Digital, UX/UI, Data, IA, Desenvolvimento. SIFIDE + Cheque Formação elegível. Programas customizados in-house ou remote.' );
        update_post_meta( $page_id, 'rank_math_focus_keyword', 'formação corporativa' );
        update_post_meta( $page_id, 'rank_math_robots',        [ 'index', 'follow' ] );
    }

    public static function enqueue_assets() {
        if ( ! is_page( self::SLUG ) ) return;
        $url = site_url( '/wp-content/plugins/' . basename( WEAREDIT_SITE_ENGINE_PATH ) . '/assets/formacao-corporativa.css' );
        wp_enqueue_style( 'edit-formacao-corporativa', $url, [], WEAREDIT_SITE_ENGINE_VERSION );
    }

    public static function emit_schema(): void {
        if ( ! is_page( self::SLUG ) ) return;

        $url = home_url( '/' . self::SLUG . '/' );

        // FAQPage schema for rich snippets in SERP.
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

        // Service schema with hasOfferCatalog — Phase B of the in-company plan.
        $service = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Service',
            '@id'         => $url . '#service',
            'serviceType' => 'Formação Corporativa',
            'provider'    => [ '@id' => home_url( '/' ) . '#organization' ],
            'areaServed'  => [ '@type' => 'Country', 'name' => 'Portugal' ],
            'name'        => 'Formação Corporativa EDIT.',
            'description' => 'Formação personalizada para equipas em Marketing Digital, UX/UI Design, Desenvolvimento, Data & Business, e Inteligência Artificial. DGERT-certificada, SIFIDE elegível.',
            'audience'    => [ '@type' => 'BusinessAudience', 'audienceType' => 'B2B Enterprise Training Buyer' ],
            'hasOfferCatalog' => [
                '@type' => 'OfferCatalog',
                'name'  => 'Áreas de Formação Corporativa',
                'itemListElement' => [
                    [ '@type' => 'Offer', 'itemOffered' => [ '@type' => 'Course', 'name' => 'Marketing Digital Corporativo' ] ],
                    [ '@type' => 'Offer', 'itemOffered' => [ '@type' => 'Course', 'name' => 'UX/UI Design Corporativo' ] ],
                    [ '@type' => 'Offer', 'itemOffered' => [ '@type' => 'Course', 'name' => 'Desenvolvimento Web Corporativo' ] ],
                    [ '@type' => 'Offer', 'itemOffered' => [ '@type' => 'Course', 'name' => 'Data & Business Corporativo' ] ],
                    [ '@type' => 'Offer', 'itemOffered' => [ '@type' => 'Course', 'name' => 'Inteligência Artificial Corporativa' ] ],
                ],
            ],
        ];

        echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $service, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
        echo "<script type=\"application/ld+json\">" . wp_json_encode( $faq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
    }

    public static function render_shortcode(): string {
        ob_start();
        ?>
        <section class="fc-pillar">

            <!-- HERO ─────────────────────────────────────────────────── -->
            <div class="fc-hero">
                <div class="fc-hero__inner">
                    <p class="fc-hero__eyebrow">FORMAÇÃO CORPORATIVA</p>
                    <h1 class="fc-hero__title">Formação Corporativa para Equipas que Querem Liderar a <span>Transformação Digital</span></h1>
                    <p class="fc-hero__lede">DGERT-certificada. <strong>6+ empresas líderes</strong> em Portugal já formaram as suas equipas com a EDIT. Programas customizados em Marketing Digital, UX/UI, Desenvolvimento, Data e Inteligência Artificial.</p>

                    <ul class="fc-hero__logos" aria-label="Empresas que formam com a EDIT.">
                        <?php foreach ( self::CLIENTS as $c ) : ?>
                            <li class="fc-hero__logo-item">
                                <img src="<?php echo esc_url( $c['logo'] ); ?>" alt="<?php echo esc_attr( $c['name'] ); ?>" loading="lazy" width="160" height="48">
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="fc-hero__cta">
                        <a class="fc-btn fc-btn--primary fc-btn--lg" href="#proposta">Pedir Proposta Personalizada</a>
                        <a class="fc-btn fc-btn--ghost" href="#casos">Ver Casos de Sucesso ↓</a>
                    </div>
                </div>
            </div>

            <!-- STATS BAR ────────────────────────────────────────────── -->
            <div class="fc-stats">
                <div class="fc-stats__inner">
                    <?php foreach ( self::STATS as $s ) : ?>
                        <div class="fc-stat<?php echo ! empty( $s['placeholder'] ) ? ' fc-stat--placeholder' : ''; ?>">
                            <div class="fc-stat__number"><?php echo esc_html( $s['number'] ); ?></div>
                            <div class="fc-stat__label"><?php echo esc_html( $s['label'] ); ?></div>
                            <div class="fc-stat__sub"><?php echo esc_html( $s['sub'] ); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- WHY EDIT. FOR B2B ─────────────────────────────────────── -->
            <div class="fc-why">
                <div class="fc-why__inner">
                    <h2 class="fc-section-title">Porquê escolher a EDIT. para a <span>vossa equipa</span></h2>
                    <div class="fc-why__grid">
                        <?php foreach ( self::VALUE_PROPS as $v ) : ?>
                            <div class="fc-prop">
                                <div class="fc-prop__check" aria-hidden="true">✓</div>
                                <h3 class="fc-prop__title"><?php echo esc_html( $v['title'] ); ?></h3>
                                <p class="fc-prop__body"><?php echo esc_html( $v['body'] ); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ÁREAS + PROCESS + CASOS DE SUCESSO ─ Chunk 2/3 placeholder -->
            <div id="casos" class="fc-placeholder">
                <div class="fc-placeholder__inner">
                    <p><em>Áreas de Formação, Processo, Casos de Sucesso — A chegar nos próximos chunks.</em></p>
                </div>
            </div>

            <!-- FAQ ──────────────────────────────────────────────────── -->
            <div id="faq" class="fc-faq">
                <div class="fc-faq__inner">
                    <h2 class="fc-section-title">Perguntas <span>Frequentes</span></h2>
                    <div class="fc-faq__list">
                        <?php foreach ( self::FAQ as $f ) : ?>
                            <details class="fc-faq__item">
                                <summary class="fc-faq__q"><span class="fc-faq__q-text"><?php echo esc_html( $f['q'] ); ?></span></summary>
                                <div class="fc-faq__a"><?php echo wp_kses_post( $f['a'] ); ?></div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- FINAL CTA ────────────────────────────────────────────── -->
            <div id="proposta" class="fc-final-cta">
                <div class="fc-final-cta__inner">
                    <h2>Pronto para formar a vossa equipa?</h2>
                    <p>Submetam o formulário e respondemos em <strong>24h úteis</strong> com uma proposta inicial ou um pedido de chamada de descoberta. Sem compromisso.</p>
                    <button type="button" class="fc-btn fc-btn--primary fc-btn--lg swipe-cta" data-contact="true">
                        <span class="swipe-layer swipe-pink"></span>
                        <span class="swipe-layer swipe-teal"></span>
                        <span class="swipe-layer swipe-black"></span>
                        <span class="swipe-label">Pedir Proposta Personalizada</span>
                    </button>
                </div>
            </div>

        </section>
        <?php
        return ob_get_clean();
    }
}
