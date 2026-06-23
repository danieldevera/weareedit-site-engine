<?php
/**
 * Product config — Agentic AI Bootcamp (Remote Learning).
 * Consumed by EDIT_Product_Launch + templates/product-launch.php.
 * One file per product: this is the streamlined "new product launch" unit.
 *
 * status: 'preview' (token-gated, noindex) | 'live' (index,follow + schema) | 'off'
 * Fields marked [A CONFIRMAR] are placeholders pending Daniel's launch data
 * (price, dates, instructor). Schema omits offers/startDate while those are blank.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

return [
	'slug'          => 'agentic-ai',
	'status'        => 'preview',
	'preview_token' => 'agentic-2026-preview',
	'live_path'     => '/formacao/bootcamp-agentic-ai/', // future canonical course URL
	'format_color'  => 'pink', // bootcamp = pink (format-type colour rule)
	'name'          => 'Agentic AI: Da Arquitetura à Implementação',

	'hero' => [
		'eyebrow'  => 'Agentic AI · Bootcamp Remote Learning',
		'h1_html'  => 'Constrói agentes de IA que <span class="hl">planeiam, decidem e executam.</span>',
		'sub'      => 'Da arquitetura de agentes ao deployment em produção. 27 horas ao vivo, em remoto, para profissionais técnicos que já dominam Generative AI. Sais a implementar, não só a usar.',
		'facts'    => [
			[ '27h', 'ao vivo' ],
			[ 'Remote', 'Learning' ],
			[ 'DGERT', 'certificado' ],
			[ 'avançado', 'nível' ],
			[ '[A CONFIRMAR]', 'início' ],
		],
	],

	'whatis' => [
		'eyebrow'   => 'O que é',
		'h2_html'   => 'Agentes que não se limitam a <span class="hl">responder.</span>',
		'paras'     => [
			'A Agentic AI foca-se na construção de sistemas que planeiam, decidem, utilizam ferramentas, interagem com outros sistemas e executam tarefas de forma controlada. É uma abordagem que se está a tornar central no desenvolvimento de produtos e workflows autónomos baseados em LLMs.',
			'Este bootcamp parte de uma base já consolidada em Generative AI e evolui para padrões de agent architecture, tool design, integração com MCP, memória, guardrails, avaliação, observabilidade e multi-agent workflows. Combina fundamentos arquiteturais com implementação prática e termina com um projeto final orientado para produção.',
		],
		'para_quem' => [
			'Software engineers, AI engineers e data scientists com foco em automação e sistemas inteligentes.',
			'Profissionais técnicos que já dominam os fundamentos de Generative AI e querem aprofundar a construção de agentes.',
			'Quem pretende desenvolver soluções com tools, orchestration e agentes em contexto empresarial.',
			'Participantes que procuram uma formação aplicada, orientada para implementação e não apenas para o uso de ferramentas já feitas.',
		],
	],

	'stats' => [
		[ '27', 'h', 'ao vivo, em remoto', '' ],
		[ '9', '', 'módulos, da arquitetura à produção', 'pink' ],
		[ '1', '', 'projeto final integrador', 'teal' ],
		[ 'DGERT', '', 'entidade formadora certificada', '' ],
	],

	'curriculum' => [
		'eyebrow' => 'O programa · 27 horas, ao vivo',
		'h2_html' => 'Da arquitetura de agentes ao <span class="hl">deployment em produção.</span>',
		'lead'    => 'Nove módulos práticos que vão dos fundamentos arquiteturais à implementação real, com um projeto final orientado para produção.',
		'modules' => [
			[ '01', 'Agent Architectures e PydanticAI', 'Diferença entre pipelines e agentes. Padrões como ReAct, plan-and-execute e reflection. Construção do primeiro agente com PydanticAI.', false ],
			[ '02', 'Tool Design e Integração com APIs Reais', 'Boas práticas no desenho de tools. Integração com APIs externas, ficheiros e bases de dados. Gestão de erros e composição de ferramentas.', false ],
			[ '03', 'MCP, Model Context Protocol', 'Conceitos base do protocolo. Construção de servidores MCP. Integração de MCP com agentes.', false ],
			[ '04', 'Memory e Conversation Management', 'Memória de curto e longo prazo. Gestão de histórico, sessões e contexto. Estratégias para agentes com memória.', false ],
			[ '05', 'Guardrails, Safety e Hooks', 'Validação de inputs e outputs. Prompt injection e mitigação. Hooks, middleware e controlo de comportamento do agente.', false ],
			[ '06', 'Evaluation, Testing e LLMOps', 'Testes para tools, loops de agente e cenários end-to-end. Observabilidade, tracing e cost tracking. Avaliação da qualidade de resposta e uso de ferramentas.', false ],
			[ '07', 'Coding Agents e Code Execution', 'Agentes que escrevem e executam código. Sandboxing e segurança na execução. Loops de correção, iteração e uso de ficheiros de contexto.', false ],
			[ '08', 'Multi-Agent Workflows', 'Quando usar múltiplos agentes. Orquestração, delegação e comunicação entre agentes. Trade-offs entre single-agent e multi-agent systems.', false ],
			[ '09', 'Production Deployment e Projeto Final', 'Estrutura de um sistema agentic em produção. Containerização, configuração e componentes de runtime. Encerramento com projeto final integrador.', true ],
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

	'stack' => [ 'Python', 'PydanticAI', 'MCP', 'LLM APIs', 'Git', 'VS Code', 'Embeddings / RAG', 'Tracing / LLMOps' ],

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
		'price'             => '',  // fill when known -> enables Offer schema
		'start_date'        => '',  // ISO 8601 when known -> enables CourseInstance startDate
	],
];
