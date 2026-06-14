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

    /**
     * Master visibility switch. Flip to 'publish' to go live, 'draft' to
     * pause (page returns 404 for public, shortcode/schema/REST/redirects
     * all skipped, code stays in repo, content stays in DB).
     *
     * Current state: PAUSED (Daniel 2026-05-29 — regrouping ideas for the
     * B2B redesign before going public).
     */
    const STATUS     = 'draft';

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
        // Row 1 — 6 known clients (logos already on the CDN)
        [ 'name' => 'Pfizer',     'slug' => 'pfizer',     'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/pfizer-header.png' ],
        [ 'name' => 'FNAC',       'slug' => 'fnac',       'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/fnac-header.png' ],
        [ 'name' => 'Adidas',     'slug' => 'adidas',     'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/adidas-header.png' ],
        [ 'name' => 'Galp',       'slug' => 'galp',       'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/galp-header.png' ],
        [ 'name' => 'Worten',     'slug' => 'worten',     'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/worten-header.png' ],
        [ 'name' => 'CM Porto',   'slug' => 'cm-porto',   'logo' => 'https://weareedit.io/wp-content/uploads/2023/05/porto-cm-1.png' ],
        // Row 2 — pulled from /formacao-in-company/ (Daniel 2026-05-29).
        // 6 highest-B2B-recognition picked from the 9 available; skipped
        // Cetelem, Cofina Media, Dia for lower brand recognition. Override
        // by adding them back here if you want all 9.
        [ 'name' => 'Nestlé',              'slug' => 'nestle',     'logo' => 'https://weareedit.io/wp-content/uploads/2022/04/nestle-logo-1.jpg' ],
        [ 'name' => 'TAP Air Portugal',    'slug' => 'tap',        'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/tap-air-portugal-1.jpg' ],
        [ 'name' => 'Altice',              'slug' => 'altice',     'logo' => 'https://weareedit.io/wp-content/uploads/2022/04/altice-logo.jpg' ],
        [ 'name' => 'Sonae MC',            'slug' => 'sonae-mc',   'logo' => 'https://weareedit.io/wp-content/uploads/2023/04/sonae-mc-1.jpg' ],
        [ 'name' => 'Santa Casa',          'slug' => 'santa-casa', 'logo' => 'https://weareedit.io/wp-content/uploads/2021/05/santa-casa.jpg' ],
        [ 'name' => 'Governo de Portugal', 'slug' => 'governo',    'logo' => 'https://weareedit.io/wp-content/uploads/2021/05/governo-de-portugal.jpg' ],
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
            'quote'      => 'Performance é cultura, não tática.',
            'quote_attr' => 'Princípio EDIT.',
            'topics' => [ 'Meta Ads + Google Ads', 'GA4 + Tag Manager', 'Estratégia de conteúdo + IA', 'Email + Automation' ],
            'sub_verticals' => [
                'Performance Marketing',
                'SEO & GEO',
                'Analytics & Attribution',
                'Content Strategy + IA',
                'Email + Marketing Automation',
                'Brand & Growth',
            ],
            'tools'  => [ 'Meta Ads', 'Google Ads', 'TikTok Ads', 'GA4', 'Tag Manager', 'Looker Studio', 'HubSpot', 'Brevo' ],
            'slug'   => 'marketing-digital',
            'icon'   => 'M',
            'accent' => '#f92869',
        ],
        [
            'title'  => 'UX/UI Design',
            'lede'   => 'Investigação, design e validação. Capacitamos product teams para entregar interfaces que clientes adoram e métricas de produto provam.',
            'quote'      => 'Design é a evidência da decisão.',
            'quote_attr' => 'Princípio EDIT.',
            'topics' => [ 'Figma + sistemas de design', 'UX Research aplicada', 'Design thinking + workshops', 'Acessibilidade + WCAG' ],
            'sub_verticals' => [
                'Design Systems',
                'UX Research & Discovery',
                'Information Architecture',
                'UI Design & Prototipagem',
                'Design Thinking & Facilitation',
                'Acessibilidade & WCAG 2.2',
            ],
            'tools'  => [ 'Figma', 'FigJam', 'Adobe XD', 'Notion', 'Maze', 'Hotjar', 'Webflow' ],
            'slug'   => 'curso-uxui-design',
            'icon'   => 'U',
            'accent' => '#60c5b3',
        ],
        [
            'title'  => 'Desenvolvimento Web',
            'lede'   => 'Front-end moderno, back-end pragmático, IA aplicada ao código. Preparamos developers para entregar produtos em produção, não tutoriais.',
            'quote'      => 'Código é a forma final da intenção.',
            'quote_attr' => 'Princípio EDIT.',
            'topics' => [ 'React + Next.js', 'Node.js + APIs', 'Webflow + low-code', 'AI-assisted development' ],
            'sub_verticals' => [
                'Front-End (React, Next.js)',
                'Back-End & APIs (Node.js)',
                'Low-code / No-code (Webflow)',
                'AI-Assisted Development',
                'DevOps & Cloud',
                'Testing & Quality',
            ],
            'tools'  => [ 'React', 'Next.js', 'Node.js', 'Webflow', 'GitHub Copilot', 'Cursor', 'AWS', 'Vercel' ],
            'slug'   => 'curso-programacao',
            'icon'   => 'D',
            'accent' => '#ec8172',
        ],
        [
            'title'  => 'Data & Business',
            'lede'   => 'Da pergunta de negócio ao dashboard accionável. Formamos analistas e líderes para tomar decisões com dados, não com opiniões.',
            'quote'      => 'Dados sem decisão são folclore.',
            'quote_attr' => 'Princípio EDIT.',
            'topics' => [ 'SQL + Python para análise', 'Power BI + Looker Studio', 'Machine Learning aplicado', 'Data storytelling' ],
            'sub_verticals' => [
                'Analytics & SQL',
                'Business Intelligence',
                'Python para Data',
                'Machine Learning Aplicado',
                'Data Engineering',
                'Data Storytelling',
            ],
            'tools'  => [ 'SQL', 'Python', 'Power BI', 'Looker Studio', 'Tableau', 'BigQuery', 'dbt' ],
            'slug'   => 'data-science',
            'icon'   => 'B',
            'accent' => '#ffdd06',
        ],
        [
            'title'  => 'Inteligência Artificial',
            'lede'   => 'Estratégia + execução. Da prompt engineering ao deploy de agentes inteligentes — IA aplicada ao vosso processo de negócio.',
            'quote'      => 'A IA não substitui pessoas. Substitui processos.',
            'quote_attr' => 'Princípio EDIT.',
            'topics' => [ 'LLMs + RAG + agentes', 'IA aplicada a marketing', 'Ética + governance', 'AI-first product design' ],
            'sub_verticals' => [
                'Prompt Engineering & LLMs',
                'RAG & Vector Databases',
                'Agentes Inteligentes',
                'IA Aplicada por Função',
                'Ética, Governance & AI Act',
                'AI-First Product Design',
            ],
            'tools'  => [ 'OpenAI', 'Anthropic Claude', 'LangChain', 'Pinecone', 'Hugging Face', 'Cursor', 'Perplexity' ],
            'slug'   => 'curso-inteligencia-artificial',
            'icon'   => 'A',
            'accent' => '#DF086F',
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
            'time'   => '48 horas',
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
        // Page-status sync runs ALWAYS so flipping STATUS const propagates
        // to the WP post on next admin_init load.
        add_action( 'admin_init',          [ __CLASS__, 'ensure_page_exists' ] );

        // Public-facing surface is only registered when published. Paused
        // state (STATUS = 'draft') skips shortcode, schema, asset enqueue,
        // 301 redirect, and the lead-capture REST endpoint.
        if ( self::STATUS === 'publish' ) {
            add_shortcode( self::SHORTCODE,    [ __CLASS__, 'render_shortcode' ] );
            add_action( 'wp_enqueue_scripts',  [ __CLASS__, 'enqueue_assets' ] );
            add_action( 'wp_head',             [ __CLASS__, 'emit_schema' ], 8 );
            add_action( 'template_redirect',   [ __CLASS__, 'redirect_old_slug' ], 1 );
            add_action( 'rest_api_init',       [ __CLASS__, 'register_rest_route' ] );
        }
    }

    /**
     * REST endpoint /wp-json/edit/v1/lead-b2b — receives the B2B lead form
     * submission, validates, sends 2 emails (admin + auto-reply), logs to
     * CF7 Debug Log for visibility. Public endpoint (no auth) — protected
     * by honeypot + nonce + rate-limit transient + email format check.
     */
    public static function register_rest_route(): void {
        register_rest_route( 'edit/v1', '/lead-b2b', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_lead_submission' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function handle_lead_submission( WP_REST_Request $request ) {
        $params = $request->get_params();

        // Honeypot — bot guard. Real users never fill this hidden field.
        if ( ! empty( $params['website'] ) ) {
            return new WP_REST_Response( [ 'status' => 'ok' ], 200 ); // fake-ok to confuse bots
        }

        // Required fields.
        $required = [
            'nome'    => 'Nome',
            'empresa' => 'Empresa',
            'cargo'   => 'Cargo',
            'email'   => 'Email',
            'size'    => 'Nº pessoas a formar',
        ];
        $missing = [];
        foreach ( $required as $key => $label ) {
            if ( empty( trim( (string) ( $params[ $key ] ?? '' ) ) ) ) $missing[] = $label;
        }
        if ( ! empty( $missing ) ) {
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Campos obrigatórios em falta: ' . implode( ', ', $missing ) ], 400 );
        }

        // Email format check.
        if ( ! is_email( $params['email'] ) ) {
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Email inválido.' ], 400 );
        }

        // Rate limit — 5 submissions per IP per hour.
        $ip = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) : ( $_SERVER['REMOTE_ADDR'] ?? '' );
        $rl_key = 'fc_lead_rl_' . md5( $ip );
        $count = (int) get_transient( $rl_key );
        if ( $count >= 5 ) {
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Demasiadas tentativas. Tente de novo dentro de uma hora.' ], 429 );
        }
        set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );

        // Normalize multi-value fields.
        $areas = isset( $params['areas'] ) && is_array( $params['areas'] ) ? array_map( 'sanitize_text_field', $params['areas'] ) : [];
        $data = [
            'nome'     => sanitize_text_field( $params['nome'] ),
            'empresa'  => sanitize_text_field( $params['empresa'] ),
            'cargo'    => sanitize_text_field( $params['cargo'] ),
            'email'    => sanitize_email( $params['email'] ),
            'telefone' => sanitize_text_field( $params['telefone'] ?? '' ),
            'size'     => sanitize_text_field( $params['size'] ),
            'timeline' => sanitize_text_field( $params['timeline'] ?? '' ),
            'areas'    => $areas,
            'formato'  => sanitize_text_field( $params['formato'] ?? '' ),
            'mensagem' => sanitize_textarea_field( $params['mensagem'] ?? '' ),
            'ip'       => filter_var( $ip, FILTER_VALIDATE_IP ) ?: '',
            'ua'       => isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ), 0, 200 ) : '',
            'ts'       => current_time( 'c' ),
        ];

        // Send 2 emails (admin notification + user auto-reply).
        $admin_ok = self::send_admin_mail( $data );
        $user_ok  = self::send_user_mail( $data );

        // Log to the CF7 Debug Log so we have a single pane of glass.
        if ( class_exists( 'EDIT_CF7_Debug' ) ) {
            EDIT_CF7_Debug::write( [
                'event'      => 'b2b_lead_submit',
                'form_title' => 'B2B Lead Form (formacao-digital-para-empresas)',
                'company'    => $data['empresa'],
                'name'       => $data['nome'],
                'size'       => $data['size'],
                'admin_mail' => $admin_ok ? 'sent' : 'failed',
                'user_mail'  => $user_ok ? 'sent' : 'failed',
                'ip'         => $data['ip'],
            ] );
        }

        return new WP_REST_Response( [
            'status'  => 'ok',
            'message' => 'Pedido recebido. Respondemos em 24h úteis.',
        ], 200 );
    }

    private static function send_admin_mail( array $d ): bool {
        $to      = 'geral@edit.com.pt';
        $subject = '[B2B Lead] ' . $d['empresa'] . ' — ' . $d['nome'];
        $areas   = empty( $d['areas'] ) ? '—' : implode( ', ', $d['areas'] );
        $body = '<h2>Novo pedido de proposta corporativa</h2>'
              . '<table cellpadding="6" cellspacing="0" border="0" style="font-family:Arial,sans-serif;font-size:14px;border-collapse:collapse;">'
              . '<tr><td><strong>Nome</strong></td><td>' . esc_html( $d['nome'] ) . '</td></tr>'
              . '<tr><td><strong>Empresa</strong></td><td>' . esc_html( $d['empresa'] ) . '</td></tr>'
              . '<tr><td><strong>Cargo</strong></td><td>' . esc_html( $d['cargo'] ) . '</td></tr>'
              . '<tr><td><strong>Email</strong></td><td><a href="mailto:' . esc_attr( $d['email'] ) . '">' . esc_html( $d['email'] ) . '</a></td></tr>'
              . '<tr><td><strong>Telefone</strong></td><td>' . esc_html( $d['telefone'] ?: '—' ) . '</td></tr>'
              . '<tr><td><strong>Equipa a formar</strong></td><td>' . esc_html( $d['size'] ) . '</td></tr>'
              . '<tr><td><strong>Prazo</strong></td><td>' . esc_html( $d['timeline'] ?: '—' ) . '</td></tr>'
              . '<tr><td><strong>Áreas</strong></td><td>' . esc_html( $areas ) . '</td></tr>'
              . '<tr><td><strong>Formato</strong></td><td>' . esc_html( $d['formato'] ?: '—' ) . '</td></tr>'
              . '<tr><td valign="top"><strong>Mensagem</strong></td><td>' . nl2br( esc_html( $d['mensagem'] ?: '—' ) ) . '</td></tr>'
              . '<tr><td colspan="2" style="padding-top:18px;color:#888;font-size:11px;">Origem: ' . esc_html( home_url( '/' . self::SLUG . '/' ) ) . ' · IP: ' . esc_html( $d['ip'] ) . ' · ' . esc_html( $d['ts'] ) . '</td></tr>'
              . '</table>';
        return (bool) wp_mail( $to, $subject, $body, [
            'Content-Type: text/html; charset=UTF-8',
            'Reply-To: ' . $d['nome'] . ' <' . $d['email'] . '>',
        ] );
    }

    private static function send_user_mail( array $d ): bool {
        $subject = 'Pedido de proposta recebido — EDIT.';
        $body = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#1a1a1a;line-height:1.6;max-width:560px;">'
              . '<p>Olá ' . esc_html( $d['nome'] ) . ',</p>'
              . '<p>Recebemos o vosso pedido de proposta para <strong>' . esc_html( $d['empresa'] ) . '</strong>. O nosso lead de programas corporativos vai analisar o brief e voltar a contactar-vos em <strong>24 horas úteis</strong> com:</p>'
              . '<ul><li>Confirmação das áreas + formato escolhidos</li><li>Proposta de duração + agenda preliminar</li><li>Estimativa de investimento + opções SIFIDE / Cheque Formação</li></ul>'
              . '<p>Para acelerar a resposta, podem partilhar antecipadamente:</p>'
              . '<ul><li>Material existente sobre as competências atuais da equipa (opcional)</li><li>Ferramentas em uso no dia-a-dia</li><li>Datas indisponíveis para sessões</li></ul>'
              . '<p>Caso seja urgente, podem ligar directamente para <strong>+351 211 451 200</strong> e referir este pedido.</p>'
              . '<p>Obrigado pela confiança,<br><strong>Equipa EDIT.</strong><br><a href="https://weareedit.io">weareedit.io</a></p>'
              . '<hr style="border:0;border-top:1px solid #eee;margin:24px 0;">'
              . '<p style="font-size:12px;color:#888;">DGERT nº 18391 · 4.1 ★ / 67 reviews Google · SIFIDE + Cheque Formação elegível</p>'
              . '</div>';
        return (bool) wp_mail( $d['email'], $subject, $body, [
            'Content-Type: text/html; charset=UTF-8',
        ] );
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
            if ( $post && in_array( $post->post_status, [ 'publish', 'draft', 'pending', 'private' ], true ) ) {
                // Idempotent status sync — flip post to match the STATUS
                // constant. This is how 'Set the page invisible' propagates:
                // STATUS='draft' here syncs the WP post to draft on next load.
                if ( $post->post_status !== self::STATUS ) {
                    wp_update_post( [ 'ID' => $post->ID, 'post_status' => self::STATUS ] );
                }
                // Slug-drift migration: rename if the stored page still
                // carries the old slug (from before v1.5.139).
                if ( $post->post_name === self::OLD_SLUG ) {
                    wp_update_post( [ 'ID' => $post->ID, 'post_name' => self::SLUG ] );
                }
                if ( strpos( $post->post_content, $shortcode_token ) === false ) {
                    self::force_update_to_shortcode( $stored_id );
                }
                // Always re-apply Rank Math meta — idempotent upsert so
                // title/desc/focus_keyword updates propagate after each
                // plugin release without needing a manual page edit.
                self::set_rank_math_meta( $stored_id );
                return $stored_id;
            }
        }

        $existing = get_page_by_path( self::SLUG );
        if ( $existing ) {
            update_option( self::OPTION_KEY, $existing->ID );
            if ( $existing->post_status !== self::STATUS ) {
                wp_update_post( [ 'ID' => $existing->ID, 'post_status' => self::STATUS ] );
            }
            if ( strpos( $existing->post_content, $shortcode_token ) === false ) {
                self::force_update_to_shortcode( $existing->ID );
            }
            return $existing->ID;
        }

        // Adopt + migrate a page still living at the OLD slug.
        $legacy = get_page_by_path( self::OLD_SLUG );
        if ( $legacy ) {
            wp_update_post( [
                'ID'          => $legacy->ID,
                'post_name'   => self::SLUG,
                'post_status' => self::STATUS,
            ] );
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
            'post_status'    => self::STATUS,
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
                        <?php foreach ( self::CLIENTS as $c ) : $is_slot = empty( $c['logo'] ); ?>
                            <li class="fc-hero__logo-item<?php echo $is_slot ? ' fc-hero__logo-item--slot' : ''; ?>">
                                <?php if ( $is_slot ) : ?>
                                    <span class="fc-hero__logo-slot" aria-label="Slot reservado — <?php echo esc_attr( $c['name'] ); ?>"><?php echo esc_html( $c['name'] ); ?></span>
                                <?php else : ?>
                                    <a class="fc-hero__logo-link" href="#caso-<?php echo esc_attr( $c['slug'] ); ?>" aria-label="Ver caso de sucesso — <?php echo esc_attr( $c['name'] ); ?>">
                                        <img src="<?php echo esc_url( $c['logo'] ); ?>" alt="<?php echo esc_attr( $c['name'] ); ?>" loading="lazy" width="240" height="88">
                                    </a>
                                <?php endif; ?>
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

            <!-- B2B LEAD FORM (Chunk 5) ──────────────────────────────── -->
            <div id="proposta" class="fc-lead">
                <div class="fc-lead__inner">
                    <div class="fc-lead__col fc-lead__col--copy">
                        <p class="fc-lead__eyebrow">VAMOS FALAR</p>
                        <h2 class="fc-lead__title">Pronto para formar a <span>vossa equipa</span>?</h2>
                        <p class="fc-lead__lede">Submetam o brief e respondemos em <strong>24h úteis</strong> com uma proposta inicial ou pedido de chamada de descoberta. Sem compromisso.</p>
                        <ul class="fc-lead__bullets">
                            <li><span class="fc-lead__check" aria-hidden="true">✓</span>Diagnóstico gratuito de 60 minutos</li>
                            <li><span class="fc-lead__check" aria-hidden="true">✓</span>Proposta customizada em 5 dias úteis</li>
                            <li><span class="fc-lead__check" aria-hidden="true">✓</span>SIFIDE + Cheque Formação elegível</li>
                            <li><span class="fc-lead__check" aria-hidden="true">✓</span>DGERT-certificada · 6+ líderes nacionais</li>
                        </ul>
                    </div>
                    <div class="fc-lead__col fc-lead__col--form">
                        <form class="fc-lead__form" novalidate>
                            <div class="fc-lead__form-status" role="status" aria-live="polite"></div>
                            <input type="text" name="website" class="fc-lead__honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
                            <div class="fc-field">
                                <label for="fc-nome">Nome <span class="fc-req">*</span></label>
                                <input id="fc-nome" name="nome" type="text" required autocomplete="name">
                            </div>
                            <div class="fc-field">
                                <label for="fc-empresa">Empresa <span class="fc-req">*</span></label>
                                <input id="fc-empresa" name="empresa" type="text" required autocomplete="organization">
                            </div>
                            <div class="fc-field">
                                <label for="fc-cargo">Cargo / Função <span class="fc-req">*</span></label>
                                <input id="fc-cargo" name="cargo" type="text" required autocomplete="organization-title">
                            </div>
                            <div class="fc-field">
                                <label for="fc-email">Email corporativo <span class="fc-req">*</span></label>
                                <input id="fc-email" name="email" type="email" required autocomplete="email">
                            </div>
                            <div class="fc-field">
                                <label for="fc-telefone">Telefone</label>
                                <input id="fc-telefone" name="telefone" type="tel" autocomplete="tel">
                            </div>
                            <div class="fc-row">
                                <div class="fc-field fc-field--half">
                                    <label for="fc-size">Nº pessoas a formar <span class="fc-req">*</span></label>
                                    <select id="fc-size" name="size" required>
                                        <option value="">Selecionar…</option>
                                        <option value="6-15">6 a 15</option>
                                        <option value="16-30">16 a 30</option>
                                        <option value="31-50">31 a 50</option>
                                        <option value="50+">Mais de 50</option>
                                    </select>
                                </div>
                                <div class="fc-field fc-field--half">
                                    <label for="fc-timeline">Prazo desejado</label>
                                    <select id="fc-timeline" name="timeline">
                                        <option value="">Selecionar…</option>
                                        <option value="Este trimestre">Este trimestre</option>
                                        <option value="Próximo trimestre">Próximo trimestre</option>
                                        <option value="Próximos 6 meses">Próximos 6 meses</option>
                                        <option value="Apenas a explorar">Apenas a explorar</option>
                                    </select>
                                </div>
                            </div>
                            <div class="fc-field">
                                <span class="fc-field__legend">Áreas de interesse</span>
                                <div class="fc-checks">
                                    <label><input type="checkbox" name="areas[]" value="Marketing Digital"> Marketing Digital</label>
                                    <label><input type="checkbox" name="areas[]" value="UX/UI Design"> UX/UI Design</label>
                                    <label><input type="checkbox" name="areas[]" value="Desenvolvimento Web"> Desenvolvimento Web</label>
                                    <label><input type="checkbox" name="areas[]" value="Data & Business"> Data & Business</label>
                                    <label><input type="checkbox" name="areas[]" value="Inteligência Artificial"> IA</label>
                                </div>
                            </div>
                            <div class="fc-field">
                                <span class="fc-field__legend">Formato preferido</span>
                                <div class="fc-radio">
                                    <label><input type="radio" name="formato" value="in-house"> In-house</label>
                                    <label><input type="radio" name="formato" value="remote"> Remote</label>
                                    <label><input type="radio" name="formato" value="hibrido"> Híbrido</label>
                                    <label><input type="radio" name="formato" value="aberto"> Em aberto</label>
                                </div>
                            </div>
                            <div class="fc-field">
                                <label for="fc-mensagem">Mensagem / contexto adicional</label>
                                <textarea id="fc-mensagem" name="mensagem" rows="4" placeholder="Que problema de negócio querem resolver? Que ferramentas usam hoje?"></textarea>
                            </div>
                            <button type="submit" class="fc-btn fc-btn--primary fc-btn--lg fc-lead__submit swipe-cta">
                                <span class="swipe-layer swipe-pink"></span>
                                <span class="swipe-layer swipe-teal"></span>
                                <span class="swipe-layer swipe-black"></span>
                                <span class="swipe-label">Pedir Proposta Personalizada</span>
                            </button>
                            <p class="fc-lead__legal">Ao submeter consente o uso dos vossos dados para resposta ao pedido. <a href="<?php echo esc_url( home_url( '/politica-de-privacidade/' ) ); ?>">Política de Privacidade</a>.</p>
                        </form>
                    </div>
                </div>
            </div>

        </section>
        <script>
        (function(){
            var form = document.querySelector('.fc-lead__form');
            if (!form) return;
            var status = form.querySelector('.fc-lead__form-status');
            var button = form.querySelector('.fc-lead__submit');
            var endpoint = '<?php echo esc_url_raw( rest_url( 'edit/v1/lead-b2b' ) ); ?>';

            form.addEventListener('submit', function(e){
                e.preventDefault();

                // Native validity check first.
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                status.textContent = '';
                status.className = 'fc-lead__form-status';
                button.disabled = true;
                var label = button.querySelector('.swipe-label');
                var original = label.textContent;
                label.textContent = 'A enviar…';

                var fd = new FormData(form);
                fetch(endpoint, { method:'POST', body: fd, credentials:'same-origin' })
                    .then(function(r){ return r.json().then(function(d){ return {ok:r.ok, body:d}; }); })
                    .then(function(res){
                        if (res.ok && res.body && res.body.status === 'ok') {
                            form.innerHTML = '<div class="fc-lead__success"><div class="fc-lead__success-icon" aria-hidden="true">✓</div><h3>Recebemos o vosso pedido</h3><p>' + (res.body.message || 'Respondemos em 24h úteis.') + '</p></div>';
                        } else {
                            button.disabled = false;
                            label.textContent = original;
                            status.textContent = (res.body && res.body.message) ? res.body.message : 'Não foi possível enviar. Tente de novo dentro de momentos.';
                            status.className = 'fc-lead__form-status fc-lead__form-status--error';
                        }
                    })
                    .catch(function(){
                        button.disabled = false;
                        label.textContent = original;
                        status.textContent = 'Erro de ligação. Tente de novo.';
                        status.className = 'fc-lead__form-status fc-lead__form-status--error';
                    });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
