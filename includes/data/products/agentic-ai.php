<?php
/**
 * Product config — Agentic AI Bootcamp (Remote Learning).
 * Consumed by EDIT_Product_Launch + templates/product-launch.php.
 * One file per product: this is the streamlined "new product launch" unit.
 *
 * status: 'preview' (token-gated, noindex) | 'live' (index,follow + schema) | 'off'
 * [A CONFIRMAR] = placeholders pending the team's launch data (price, dates,
 * schedule, instructor). Schema omits offers/startDate while those are blank.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

return [
	'slug'          => 'agentic-ai',
	'status'        => 'preview',
	'preview_token' => 'agentic-2026-preview',
	'live_path'     => '/formacao/bootcamp-agentic-ai/',
	'donor_url'     => 'https://weareedit.io/formacao/bootcamp-advanced-artificial-intelligence/', // proxied for header/footer chrome only
	'format_color'  => 'pink',
	'name'          => 'Agentic AI: Da Arquitetura à Implementação',
	'has_alumni'    => false, // brand-new bootcamp: no alumni cohort yet -> hide alumni-proof section

	'hero' => [
		'badge'   => 'Entidade Formadora Certificada',
		'eyebrow' => 'Remote Learning · Bootcamp',
		'h1_html' => 'Agentic AI<span class="dp">.</span>',
		'sub'     => '27 horas, ao vivo. Da arquitetura de agentes ao deployment em produção. Para profissionais técnicos que já dominam Generative AI e querem construir, não só usar.',
	],

	// Floating info-card (mirrors the AAI dates/action bar). Values [A CONFIRMAR].
	'info_card' => [
		'cols' => [
			[ 'INÍCIO', '[A CONFIRMAR]' ],
			[ 'FIM', '[A CONFIRMAR]' ],
			[ 'DURAÇÃO', '27 horas' ],
			[ 'INVESTIMENTO', '[A CONFIRMAR]' ],
			[ 'HORÁRIOS', '[A CONFIRMAR]' ],
		],
		'tags'    => [ 'Remote', 'Avançado' ],
		'cta'     => 'Quero-me inscrever',
	],

	'whatis' => [
		'eyebrow'   => 'O que é',
		'h2_html'   => 'Agentes que não se limitam a <span class="hl">responder.</span>',
		'paras'     => [
			'A Agentic AI foca-se na construção de sistemas que planeiam, decidem, utilizam ferramentas, interagem com outros sistemas e executam tarefas de forma controlada. É uma abordagem que se está a tornar central no desenvolvimento de produtos e workflows autónomos baseados em LLMs.',
			'Este bootcamp parte de uma base já consolidada em Generative AI e evolui para padrões de agent architecture, tool design, integração com MCP, memória, guardrails, avaliação, observabilidade e multi-agent workflows. Combina fundamentos arquiteturais com implementação prática e termina com um projeto final orientado para produção.',
		],
	],

	// "Para quem é" persona cards (AAI-style, accent-topped).
	'personas' => [
		[ 'Software e AI engineers', 'Que querem construir agentes e integrá-los com tools, APIs e o protocolo MCP.' ],
		[ 'Data scientists', 'Que já dominam Generative AI e querem evoluir para sistemas agentic estruturados.' ],
		[ 'Builders de produto', 'Que querem automação e workflows autónomos com agentes em contexto empresarial.' ],
	],
	'persona_foot' => 'Este é um bootcamp avançado. Precisas de base técnica, não de a construir do zero.',

	'curriculum' => [
		'eyebrow' => 'O programa · 27 horas, ao vivo',
		'h2_html' => 'Da arquitetura de agentes ao <span class="hl">deployment em produção.</span>',
		'lead'    => 'Nove módulos práticos que vão dos fundamentos arquiteturais à implementação real, com um projeto final orientado para produção.',
		'modules' => [
			[ 'num' => '01', 'title' => 'Agent Architectures e PydanticAI', 'level' => 'Avançado', 'bullets' => [ 'Diferença entre pipelines e agentes', 'Padrões como ReAct, plan-and-execute e reflection', 'Construção do primeiro agente com PydanticAI' ] ],
			[ 'num' => '02', 'title' => 'Tool Design e Integração com APIs Reais', 'level' => 'Avançado', 'bullets' => [ 'Boas práticas no desenho de tools', 'Integração com APIs externas, ficheiros e bases de dados', 'Gestão de erros e composição de ferramentas' ] ],
			[ 'num' => '03', 'title' => 'MCP, Model Context Protocol', 'level' => 'Avançado', 'bullets' => [ 'Conceitos base do protocolo', 'Construção de servidores MCP', 'Integração de MCP com agentes' ] ],
			[ 'num' => '04', 'title' => 'Memory e Conversation Management', 'level' => 'Avançado', 'bullets' => [ 'Memória de curto e longo prazo', 'Gestão de histórico, sessões e contexto', 'Estratégias para agentes com memória' ] ],
			[ 'num' => '05', 'title' => 'Guardrails, Safety e Hooks', 'level' => 'Avançado', 'bullets' => [ 'Validação de inputs e outputs', 'Prompt injection e mitigação', 'Hooks, middleware e controlo de comportamento' ] ],
			[ 'num' => '06', 'title' => 'Evaluation, Testing e LLMOps', 'level' => 'Avançado', 'bullets' => [ 'Testes para tools, loops de agente e cenários end-to-end', 'Observabilidade, tracing e cost tracking', 'Avaliação da qualidade de resposta e uso de ferramentas' ] ],
			[ 'num' => '07', 'title' => 'Coding Agents e Code Execution', 'level' => 'Avançado', 'bullets' => [ 'Agentes que escrevem e executam código', 'Sandboxing e segurança na execução', 'Loops de correção, iteração e ficheiros de contexto' ] ],
			[ 'num' => '08', 'title' => 'Multi-Agent Workflows', 'level' => 'Avançado', 'bullets' => [ 'Quando usar múltiplos agentes', 'Orquestração, delegação e comunicação entre agentes', 'Trade-offs entre single-agent e multi-agent systems' ] ],
			[ 'num' => '09', 'title' => 'Production Deployment e Projeto Final', 'level' => 'Avançado', 'flag' => true, 'project' => 'Projeto final integrador, orientado para produção', 'bullets' => [ 'Estrutura de um sistema agentic em produção', 'Containerização, configuração e componentes de runtime' ] ],
		],
		'outcomes' => [
			'Distinguir aplicações baseadas em prompts, pipelines e agentes.',
			'Construir agentes com PydanticAI e integrá-los com tools e serviços externos.',
			'Trabalhar com o protocolo MCP para ligar agentes a ferramentas e recursos.',
			'Implementar memória, gestão de contexto e histórico conversacional.',
			'Definir guardrails e mecanismos de validação para reduzir riscos.',
			'Avaliar e testar sistemas agentic de forma sistemática.',
			'Construir coding agents e compreender os seus limites.',
			'Desenhar workflows multi-agente e preparar sistemas para produção.',
		],
	],

	// "O teu arsenal" — grouped tool columns (AAI-style).
	'arsenal' => [
		'eyebrow' => 'O teu arsenal',
		'h2_html' => 'O stack que vais usar para <span class="hl">construir.</span>',
		'lead'    => 'Um stack técnico orientado para a implementação real de agentes em produção.',
		'groups'  => [
			[ '1 · Construir', [ 'Python', 'PydanticAI' ] ],
			[ '2 · Integrar', [ 'MCP', 'LLM APIs', 'Embeddings / RAG' ] ],
			[ '3 · Operar', [ 'Tracing / LLMOps', 'Git', 'VS Code' ] ],
		],
	],

	'admission' => [
		[ 'Idade mínima 18 anos', 'Aberto a profissionais e estudantes com a base técnica indicada.' ],
		[ 'Python, Git e VS Code', 'Conhecimentos sólidos das ferramentas e do fluxo de trabalho de desenvolvimento.' ],
		[ 'Experiência com APIs de LLMs', 'Já trabalhaste com modelos de linguagem por API.' ],
		[ 'Fundamentos de Generative AI', 'Conhecimento prático de structured outputs, embeddings e RAG.' ],
		[ 'Autonomia em Python', 'Capacidade de desenvolver pequenos projetos com alguma autonomia.' ],
		[ 'Equipamento', 'Computador com internet, webcam e microfone, e permissões para instalar software e usar APIs.' ],
	],

	'instructor' => [
		'name'  => '[A CONFIRMAR]',
		'role'  => 'Especialista em Agentic AI e engenharia de LLMs',
		'quote' => 'Construir agentes não é juntar prompts. É arquitetura, tool design, memória, guardrails e avaliação. Neste bootcamp sais a implementar sistemas agentic de forma estruturada, prontos para produção.',
		'photo' => '',
	],

	'outcomes_cards' => [
		[ 'RESULTADO', 'O que sais a saber fazer', 'Implementar agentes do zero: arquitetura, tools, MCP, memória, guardrails, avaliação e deployment.' ],
		[ 'RESULTADO', 'Projeto final', 'Terminas com um sistema agentic integrador, orientado para produção. Não sais com apontamentos, sais com algo construído.' ],
		[ 'PÓS-BOOTCAMP', 'Documentação e recrutamento', 'Acesso a documentação de apoio e à rede de parceiros de recrutamento da EDIT.' ],
	],

	'investment' => [
		'included'  => [
			'27 horas de formação ao vivo, em remoto, em sala de aula virtual.',
			'Projeto final integrador orientado para produção.',
			'Certificado digital DGERT com carga horária, módulos e classificação.',
			'Acesso a documentação de apoio e recursos bibliográficos.',
			'Acesso à rede de parceiros de recrutamento da EDIT.',
		],
		'price'     => 'A confirmar',
		'price_sub' => 'valor e plano de pagamento [A CONFIRMAR]',
	],

	'faq' => [
		[ 'Em que formato decorre o bootcamp?', 'Em Remote Learning: 27 horas ao vivo, transmitidas em direto numa sala de aula virtual, com formador e turma em tempo real.' ],
		[ 'Preciso de que conhecimentos prévios?', 'É um bootcamp avançado. Precisas de Python, Git e VS Code sólidos, experiência com APIs de LLMs e fundamentos práticos de Generative AI, incluindo structured outputs, embeddings e RAG.' ],
		[ 'A certificação é reconhecida?', 'Sim. A EDIT. é entidade formadora certificada DGERT. O certificado é digital e inclui carga horária, módulos, temáticas, notas finais e média do curso.' ],
		[ 'Há apoio à empregabilidade?', 'Sim. A EDIT. dá acesso a uma rede de parceiros de recrutamento, como parte da missão de acelerar carreiras técnicas.' ],
		[ 'De que equipamento preciso?', 'Um computador com ligação à internet, webcam e microfone, e permissões para instalar software e utilizar APIs.' ],
		[ 'Termino com um projeto?', 'Sim. O bootcamp encerra com um projeto final integrador, orientado para produção, que junta a arquitetura, as tools, a memória, os guardrails e a avaliação.' ],
	],

	'cta' => [
		'date' => '[A CONFIRMAR]',
		'line' => 'Remote Learning · 27h · certificado DGERT · vagas limitadas por turma.',
	],

	'seo' => [
		'title'             => 'Bootcamp Agentic AI: Da Arquitetura à Implementação | EDIT.',
		'meta'              => 'Bootcamp Agentic AI em remoto, 27h ao vivo. Constrói agentes com PydanticAI, MCP, memória, guardrails, LLMOps e multi-agent workflows. Certificação DGERT.',
		'canonical'         => 'https://weareedit.io/formacao/bootcamp-agentic-ai/',
		'og_image'          => '',
		'time_required'     => 'PT27H',
		'educational_level' => 'Advanced',
		'teaches'           => [ 'Agent architectures', 'PydanticAI', 'Tool design', 'Model Context Protocol (MCP)', 'Agent memory', 'Guardrails e safety', 'Evaluation e LLMOps', 'Coding agents', 'Multi-agent workflows', 'Production deployment' ],
		'about'             => [ 'Agentic AI', 'Large Language Models', 'AI agents', 'LLMOps' ],
		'credential'        => 'Certificado DGERT',
		'currency'          => 'EUR',
		'price'             => '',
		'start_date'        => '',
	],
];
