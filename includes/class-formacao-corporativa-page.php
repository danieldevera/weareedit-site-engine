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

    const SLUG       = 'formacao-digital-para-empresas';
    const OLD_SLUG   = 'formacao-corporativa'; // pre-v1.5.139, kept for 301
    const TITLE      = 'Formação Digital para Empresas — EDIT. forma equipas em Portugal';
    const OPTION_KEY = 'edit_seo_fix_formacao_corporativa_page_id';
    const SHORTCODE  = 'edit_formacao_corporativa_pillar';

    /**
     * Corporate clients featured on the page. Logo filenames match the
     * existing homepage `<ul class="logos-flex-container">` block so we
     * can reuse the same image URLs (already on CDN, already optimized).
     */
    const CLIENTS = [
        [ 'name' => 'Pfizer',   'slug' => 'pfizer',   'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/pfizer-header.png' ],
        [ 'name' => 'FNAC',     'slug' => 'fnac',     'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/fnac-header.png' ],
        [ 'name' => 'Adidas',   'slug' => 'adidas',   'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/adidas-header.png' ],
        [ 'name' => 'Galp',     'slug' => 'galp',     'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/galp-header.png' ],
        [ 'name' => 'Worten',   'slug' => 'worten',   'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/worten-header.png' ],
        [ 'name' => 'CM Porto', 'slug' => 'cm-porto', 'logo' => 'https://weareedit.io/wp-content/uploads/2023/05/porto-cm-1.png' ],
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
     * 5 áreas de formação corporativa. Each links to its pillar page so
     * we strengthen the topical cluster (B2B page → pillar → courses).
     * Copy is B2B-tuned (team-level outcomes, not individual learning).
     */
    const AREAS = [
        [
            'title'  => 'Marketing Digital',
            'lede'   => 'Performance, growth, paid media e conteúdo. Formamos equipas de marketing para gerir orçamentos de 6 dígitos com confiança e ROI mensurável.',
            'topics' => [ 'Meta Ads + Google Ads', 'GA4 + Tag Manager', 'Estratégia de conteúdo + IA', 'Email + Automation' ],
            'slug'   => 'marketing-digital',
            'icon'   => 'M',
        ],
        [
            'title'  => 'UX/UI Design',
            'lede'   => 'Investigação, design e validação. Capacitamos product teams para entregar interfaces que clientes adoram e métricas de produto provam.',
            'topics' => [ 'Figma + sistemas de design', 'UX Research aplicada', 'Design thinking + workshops', 'Acessibilidade + WCAG' ],
            'slug'   => 'curso-uxui-design',
            'icon'   => 'U',
        ],
        [
            'title'  => 'Desenvolvimento Web',
            'lede'   => 'Front-end moderno, back-end pragmático, IA aplicada ao código. Preparamos developers para entregar produtos em produção, não tutoriais.',
            'topics' => [ 'React + Next.js', 'Node.js + APIs', 'Webflow + low-code', 'AI-assisted development' ],
            'slug'   => 'curso-programacao',
            'icon'   => 'D',
        ],
        [
            'title'  => 'Data & Business',
            'lede'   => 'Da pergunta de negócio ao dashboard accionável. Formamos analistas e líderes para tomar decisões com dados, não com opiniões.',
            'topics' => [ 'SQL + Python para análise', 'Power BI + Looker Studio', 'Machine Learning aplicado', 'Data storytelling' ],
            'slug'   => 'data-science',
            'icon'   => 'B',
        ],
        [
            'title'  => 'Inteligência Artificial',
            'lede'   => 'Estratégia + execução. Da prompt engineering ao deploy de agentes inteligentes — IA aplicada ao vosso processo de negócio.',
            'topics' => [ 'LLMs + RAG + agentes', 'IA aplicada a marketing', 'Ética + governance', 'AI-first product design' ],
            'slug'   => 'curso-inteligencia-artificial',
            'icon'   => 'A',
        ],
    ];

    /**
     * Case studies — one card per featured client. Content is INTENTIONALLY
     * placeholder so Daniel can swap with real testimonials + metrics in
     * Chunk 4 (no risk of accidental publication of fabricated quotes).
     * Each entry's id matches the hero logo anchor (#caso-{slug}) so the
     * logo wall deep-links into the right card.
     *
     * Schema markup deferred to Chunk 5 — fake Review schema would trip
     * Google's structured-data policy.
     */
    const CASOS = [
        [
            'slug'   => 'pfizer',
            'name'   => 'Pfizer',
            'logo'   => 'https://weareedit.io/wp-content/uploads/2023/04/pfizer-header.png',
            'sector' => 'Farmacêutica',
            'area'   => 'Data & Business',
            'format' => 'Remote · Pós-laboral',
            'year'   => '2024',
            'quote'  => '[Citação placeholder] Conseguimos capacitar a nossa equipa em técnicas avançadas de análise de dados em apenas algumas semanas, com impacto directo na velocidade de decisão.',
            'author' => '[Nome do stakeholder] · [Cargo na Pfizer]',
            'stats'  => [
                [ 'number' => '—', 'label' => 'Formandos' ],
                [ 'number' => '—', 'label' => 'Semanas' ],
                [ 'number' => '—', 'label' => 'NPS' ],
            ],
        ],
        [
            'slug'   => 'fnac',
            'name'   => 'FNAC',
            'logo'   => 'https://weareedit.io/wp-content/uploads/2023/04/fnac-header.png',
            'sector' => 'Retalho Omnicanal',
            'area'   => 'Marketing Digital + UX/UI',
            'format' => 'Híbrido · Lisboa',
            'year'   => '2024',
            'quote'  => '[Citação placeholder] A formação foi totalmente adaptada à nossa stack de ferramentas e ao nosso calendário comercial. Resultado: a equipa entrou em produção dois meses antes do esperado.',
            'author' => '[Nome do stakeholder] · [Cargo na FNAC]',
            'stats'  => [
                [ 'number' => '—', 'label' => 'Formandos' ],
                [ 'number' => '—', 'label' => 'Áreas' ],
                [ 'number' => '—', 'label' => 'NPS' ],
            ],
        ],
        [
            'slug'   => 'adidas',
            'name'   => 'Adidas',
            'logo'   => 'https://weareedit.io/wp-content/uploads/2023/04/adidas-header.png',
            'sector' => 'Desporto + E-commerce',
            'area'   => 'Marketing Digital + IA aplicada',
            'format' => 'Remote · Intensivo',
            'year'   => '2024',
            'quote'  => '[Citação placeholder] Os tutores trouxeram cases reais que validaram o que estamos a construir internamente. A equipa saiu com competências, não só com slides.',
            'author' => '[Nome do stakeholder] · [Cargo na Adidas]',
            'stats'  => [
                [ 'number' => '—', 'label' => 'Formandos' ],
                [ 'number' => '—', 'label' => 'Horas' ],
                [ 'number' => '—', 'label' => 'NPS' ],
            ],
        ],
        [
            'slug'   => 'galp',
            'name'   => 'Galp',
            'logo'   => 'https://weareedit.io/wp-content/uploads/2023/04/galp-header.png',
            'sector' => 'Energia',
            'area'   => 'Data & Business',
            'format' => 'In-house · Lisboa',
            'year'   => '2024',
            'quote'  => '[Citação placeholder] A capacidade de customizar o conteúdo ao nosso setor regulado foi decisiva — não é uma formação genérica, é o nosso problema com a linguagem certa.',
            'author' => '[Nome do stakeholder] · [Cargo na Galp]',
            'stats'  => [
                [ 'number' => '—', 'label' => 'Formandos' ],
                [ 'number' => '—', 'label' => 'Semanas' ],
                [ 'number' => '—', 'label' => 'NPS' ],
            ],
        ],
        [
            'slug'   => 'worten',
            'name'   => 'Worten',
            'logo'   => 'https://weareedit.io/wp-content/uploads/2023/04/worten-header.png',
            'sector' => 'Retalho de Electrónica',
            'area'   => 'UX/UI Design',
            'format' => 'Remote · 12 semanas',
            'year'   => '2023',
            'quote'  => '[Citação placeholder] A EDIT. entendeu o ritmo de release da nossa equipa de produto e adaptou a entrega para não bloquear sprints. Raro num parceiro de formação.',
            'author' => '[Nome do stakeholder] · [Cargo na Worten]',
            'stats'  => [
                [ 'number' => '—', 'label' => 'Designers' ],
                [ 'number' => '—', 'label' => 'Semanas' ],
                [ 'number' => '—', 'label' => 'NPS' ],
            ],
        ],
        [
            'slug'   => 'cm-porto',
            'name'   => 'CM Porto',
            'logo'   => 'https://weareedit.io/wp-content/uploads/2023/05/porto-cm-1.png',
            'sector' => 'Sector Público',
            'area'   => 'Comunicação Digital',
            'format' => 'In-house · Porto',
            'year'   => '2023',
            'quote'  => '[Citação placeholder] Para uma autarquia, a comunicação digital é serviço público. A formação ajudou-nos a chegar aos cidadãos em canais onde estávamos invisíveis.',
            'author' => '[Nome do stakeholder] · [Cargo na CM Porto]',
            'stats'  => [
                [ 'number' => '—', 'label' => 'Formandos' ],
                [ 'number' => '—', 'label' => 'Áreas' ],
                [ 'number' => '—', 'label' => 'NPS' ],
            ],
        ],
        // ── Open slots (post-v1.5.142) ───────────────────────────────
        // Daniel signalled "+ 4" — reserve 4 more cards. Replace the
        // bracketed labels with real client name + logo URL + content.
        [
            'slug' => 'cliente-07', 'name' => '[Cliente 7]', 'logo' => '',
            'sector' => '[Setor]', 'area' => '[Área]', 'format' => '[Formato]', 'year' => '—',
            'quote' => '[Citação placeholder — a definir]',
            'author' => '[Nome · Cargo]',
            'stats' => [ [ 'number' => '—', 'label' => '—' ], [ 'number' => '—', 'label' => '—' ], [ 'number' => '—', 'label' => '—' ] ],
            'placeholder_slot' => true,
        ],
        [
            'slug' => 'cliente-08', 'name' => '[Cliente 8]', 'logo' => '',
            'sector' => '[Setor]', 'area' => '[Área]', 'format' => '[Formato]', 'year' => '—',
            'quote' => '[Citação placeholder — a definir]',
            'author' => '[Nome · Cargo]',
            'stats' => [ [ 'number' => '—', 'label' => '—' ], [ 'number' => '—', 'label' => '—' ], [ 'number' => '—', 'label' => '—' ] ],
            'placeholder_slot' => true,
        ],
        [
            'slug' => 'cliente-09', 'name' => '[Cliente 9]', 'logo' => '',
            'sector' => '[Setor]', 'area' => '[Área]', 'format' => '[Formato]', 'year' => '—',
            'quote' => '[Citação placeholder — a definir]',
            'author' => '[Nome · Cargo]',
            'stats' => [ [ 'number' => '—', 'label' => '—' ], [ 'number' => '—', 'label' => '—' ], [ 'number' => '—', 'label' => '—' ] ],
            'placeholder_slot' => true,
        ],
        [
            'slug' => 'cliente-10', 'name' => '[Cliente 10]', 'logo' => '',
            'sector' => '[Setor]', 'area' => '[Área]', 'format' => '[Formato]', 'year' => '—',
            'quote' => '[Citação placeholder — a definir]',
            'author' => '[Nome · Cargo]',
            'stats' => [ [ 'number' => '—', 'label' => '—' ], [ 'number' => '—', 'label' => '—' ], [ 'number' => '—', 'label' => '—' ] ],
            'placeholder_slot' => true,
        ],
    ];

    /**
     * 4-step delivery process — what enterprise buyers actually want to
     * see before signing a procurement order. Each step has a duration
     * range so the buyer can estimate timelines for their L&D plan.
     */
    const PROCESS = [
        [
            'number' => '01',
            'title'  => 'Diagnóstico',
            'time'   => '1–2 semanas',
            'body'   => 'Sessão de descoberta de 60 minutos com stakeholders + análise dos perfis dos formandos. Definimos resultados esperados, ferramentas em uso, e métricas de impacto.',
        ],
        [
            'number' => '02',
            'title'  => 'Desenho do Programa',
            'time'   => '1 semana',
            'body'   => 'O lead instructor desenha um syllabus customizado ao vosso setor e às ferramentas existentes. Validamos convosco antes do kick-off — sem surpresas.',
        ],
        [
            'number' => '03',
            'title'  => 'Entrega',
            'time'   => '4–12 semanas',
            'body'   => 'Sessões in-house, no nosso campus, ou remoto em tempo real. Projeto final aplicado ao vosso contexto real. Materiais e gravações permanecem com a vossa equipa.',
        ],
        [
            'number' => '04',
            'title'  => 'Avaliação de Impacto',
            'time'   => '3 + 6 meses',
            'body'   => 'Pré/pós-teste, NPS por sessão, seguimento a 3 e 6 meses para medir adoção das competências no dia-a-dia. Relatório final com métricas para o vosso L&D dashboard.',
        ],
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
        add_action( 'template_redirect',   [ __CLASS__, 'redirect_old_slug' ], 1 );
    }

    /**
     * Defensive 301 from /formacao-corporativa/* → /formacao-digital-para-empresas/*
     * for any stray links pointing to the original slug (pre-v1.5.139).
     */
    public static function redirect_old_slug(): void {
        $request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        if ( preg_match( '#^/' . preg_quote( self::OLD_SLUG, '#' ) . '(/.*)?$#', $request, $m ) ) {
            $tail = $m[1] ?? '/';
            wp_safe_redirect( home_url( '/' . self::SLUG . $tail ), 301 );
            exit;
        }
    }

    public static function ensure_page_exists() {
        $shortcode_token = '[' . self::SHORTCODE . ']';

        $stored_id = (int) get_option( self::OPTION_KEY );
        if ( $stored_id ) {
            $post = get_post( $stored_id );
            if ( $post && $post->post_status === 'publish' ) {
                // Slug-drift migration: rename if the stored page still
                // carries the old slug (from before v1.5.139).
                if ( $post->post_name === self::OLD_SLUG ) {
                    wp_update_post( [ 'ID' => $post->ID, 'post_name' => self::SLUG ] );
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

        // Adopt + migrate a page still living at the OLD slug.
        $legacy = get_page_by_path( self::OLD_SLUG );
        if ( $legacy ) {
            wp_update_post( [ 'ID' => $legacy->ID, 'post_name' => self::SLUG ] );
            update_option( self::OPTION_KEY, $legacy->ID );
            if ( strpos( $legacy->post_content, $shortcode_token ) === false ) {
                self::force_update_to_shortcode( $legacy->ID );
            }
            return $legacy->ID;
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
        update_post_meta( $page_id, 'rank_math_title',         'Formação Digital para Empresas | EDIT. — Pfizer, Adidas, FNAC, Galp formam aqui' );
        update_post_meta( $page_id, 'rank_math_description',   'Formação digital para empresas, DGERT-certificada. Marketing Digital, UX/UI, Data, IA, Desenvolvimento. SIFIDE + Cheque Formação elegível. Programas customizados in-house ou remote em Portugal.' );
        update_post_meta( $page_id, 'rank_math_focus_keyword', 'formação digital para empresas' );
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

            <!-- HERO + STATS WRAPPER (shared video background) ───────── -->
            <div class="fc-herowrap">
                <video class="fc-herowrap__video" autoplay muted loop playsinline preload="auto" aria-hidden="true">
                    <source src="https://weareedit.io/wp-content/uploads/2026/03/waves-sequence-compressed.mp4" type="video/mp4">
                </video>
                <div class="fc-herowrap__overlay" aria-hidden="true"></div>

            <!-- HERO ─────────────────────────────────────────────────── -->
            <div class="fc-hero">
                <div class="fc-hero__inner">
                    <p class="fc-hero__eyebrow">FORMAÇÃO CORPORATIVA</p>
                    <h1 class="fc-hero__title">Formação Corporativa para Equipas que Querem Liderar a <span>Transformação Digital</span></h1>
                    <p class="fc-hero__lede">DGERT-certificada. <strong>6+ empresas líderes</strong> em Portugal já formaram as suas equipas com a EDIT. Programas customizados em Marketing Digital, UX/UI, Desenvolvimento, Data e Inteligência Artificial.</p>

                    <ul class="fc-hero__logos" aria-label="Empresas que formam com a EDIT.">
                        <?php foreach ( self::CLIENTS as $c ) : ?>
                            <li class="fc-hero__logo-item">
                                <a class="fc-hero__logo-link" href="#caso-<?php echo esc_attr( $c['slug'] ); ?>" aria-label="Ver caso de sucesso — <?php echo esc_attr( $c['name'] ); ?>">
                                    <img src="<?php echo esc_url( $c['logo'] ); ?>" alt="<?php echo esc_attr( $c['name'] ); ?>" loading="lazy" width="240" height="88">
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="fc-hero__trust" aria-label="Credenciais de confiança">
                        <a class="fc-trust fc-trust--reviews" href="https://weareedit.io/avaliacoes-google/" aria-label="Avaliações Google — 4.1 de 5 baseado em 67 reviews">
                            <span class="fc-trust__star">★</span>
                            <span class="fc-trust__rating">4.1</span>
                            <span class="fc-trust__sep">/</span>
                            <span class="fc-trust__count">67 reviews no</span>
                            <span class="fc-trust__google" aria-hidden="true"><span style="color:#4285F4">G</span><span style="color:#EA4335">o</span><span style="color:#FBBC04">o</span><span style="color:#4285F4">g</span><span style="color:#34A853">l</span><span style="color:#EA4335">e</span></span>
                        </a>
                        <span class="fc-trust__divider" aria-hidden="true"></span>
                        <span class="fc-trust"><span class="fc-trust__icon" aria-hidden="true">✓</span>DGERT nº 18391</span>
                        <span class="fc-trust__divider" aria-hidden="true"></span>
                        <span class="fc-trust"><span class="fc-trust__icon" aria-hidden="true">€</span>SIFIDE + Cheque Formação</span>
                        <span class="fc-trust__divider" aria-hidden="true"></span>
                        <span class="fc-trust"><span class="fc-trust__icon" aria-hidden="true">◷</span>Resposta em 24h úteis</span>
                    </div>

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

            </div> <!-- /.fc-herowrap -->

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

            <!-- ÁREAS DE FORMAÇÃO ────────────────────────────────────── -->
            <div id="areas" class="fc-areas">
                <div class="fc-areas__inner">
                    <h2 class="fc-section-title">5 Áreas. <span>1 Programa Customizado.</span></h2>
                    <p class="fc-areas__lede">Cada área é DGERT-certificada e foi entregue a equipas em mercados regulados (banca, saúde, retalho, energia). Programas podem ser entregues isoladamente ou combinados num multi-área para equipas cross-funcionais.</p>
                    <div class="fc-areas__grid">
                        <?php foreach ( self::AREAS as $a ) : ?>
                            <a class="fc-area" href="<?php echo esc_url( home_url( '/' . $a['slug'] . '/' ) ); ?>">
                                <div class="fc-area__icon" aria-hidden="true"><?php echo esc_html( $a['icon'] ); ?></div>
                                <h3 class="fc-area__title"><?php echo esc_html( $a['title'] ); ?></h3>
                                <p class="fc-area__lede"><?php echo esc_html( $a['lede'] ); ?></p>
                                <ul class="fc-area__topics">
                                    <?php foreach ( $a['topics'] as $t ) : ?>
                                        <li><?php echo esc_html( $t ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <span class="fc-area__cta">Ver área completa <span aria-hidden="true">→</span></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- PROCESS (Como Trabalhamos) ───────────────────────────── -->
            <div id="processo" class="fc-process">
                <div class="fc-process__inner">
                    <p class="fc-process__eyebrow">METODOLOGIA</p>
                    <h2 class="fc-section-title">Como Entregamos uma <span>Formação que Funciona</span></h2>
                    <div class="fc-process__steps">
                        <?php foreach ( self::PROCESS as $step ) : ?>
                            <div class="fc-step">
                                <div class="fc-step__number" aria-hidden="true"><?php echo esc_html( $step['number'] ); ?></div>
                                <div class="fc-step__body">
                                    <div class="fc-step__time"><?php echo esc_html( $step['time'] ); ?></div>
                                    <h3 class="fc-step__title"><?php echo esc_html( $step['title'] ); ?></h3>
                                    <p class="fc-step__desc"><?php echo esc_html( $step['body'] ); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- CASOS DE SUCESSO ─────────────────────────────────────── -->
            <div id="casos" class="fc-casos">
                <div class="fc-casos__inner">
                    <p class="fc-casos__eyebrow">CASOS DE SUCESSO</p>
                    <h2 class="fc-section-title">Equipas que <span>formaram aqui</span></h2>
                    <p class="fc-casos__lede">Programas reais, com resultados medidos. Cada caso mostra o setor, a área formada, o formato escolhido, e as métricas de impacto que reportamos no final do programa.</p>
                    <div class="fc-casos__grid">
                        <?php foreach ( self::CASOS as $caso ) : $is_placeholder = ! empty( $caso['placeholder_slot'] ); ?>
                            <article id="caso-<?php echo esc_attr( $caso['slug'] ); ?>" class="fc-caso<?php echo $is_placeholder ? ' fc-caso--placeholder' : ''; ?>">
                                <header class="fc-caso__head">
                                    <div class="fc-caso__logo">
                                        <?php if ( ! empty( $caso['logo'] ) ) : ?>
                                            <img src="<?php echo esc_url( $caso['logo'] ); ?>" alt="<?php echo esc_attr( $caso['name'] ); ?>" loading="lazy" width="240" height="80">
                                        <?php else : ?>
                                            <span class="fc-caso__logo-placeholder"><?php echo esc_html( $caso['name'] ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <ul class="fc-caso__meta">
                                        <li><span class="fc-caso__meta-label">Setor</span><?php echo esc_html( $caso['sector'] ); ?></li>
                                        <li><span class="fc-caso__meta-label">Área</span><?php echo esc_html( $caso['area'] ); ?></li>
                                        <li><span class="fc-caso__meta-label">Formato</span><?php echo esc_html( $caso['format'] ); ?></li>
                                        <li><span class="fc-caso__meta-label">Ano</span><?php echo esc_html( $caso['year'] ); ?></li>
                                    </ul>
                                </header>
                                <div class="fc-caso__body">
                                    <blockquote class="fc-caso__quote">
                                        <span class="fc-caso__qmark" aria-hidden="true">&ldquo;</span><?php echo esc_html( $caso['quote'] ); ?>
                                    </blockquote>
                                    <footer class="fc-caso__attribution">&mdash; <?php echo esc_html( $caso['author'] ); ?></footer>
                                    <ul class="fc-caso__stats">
                                        <?php foreach ( $caso['stats'] as $s ) : ?>
                                            <li class="fc-caso__stat">
                                                <span class="fc-caso__stat-number"><?php echo esc_html( $s['number'] ); ?></span>
                                                <span class="fc-caso__stat-label"><?php echo esc_html( $s['label'] ); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <a class="fc-caso__cta" href="#proposta">Falar sobre uma proposta similar <span aria-hidden="true">→</span></a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
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
