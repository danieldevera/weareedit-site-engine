<?php
/**
 * empresas.weareedit.io — B2B sub-brand surface.
 * ─────────────────────────────────────────────────────────────────────────────
 * Sub-brand replacement for the legacy /formacao-in-company/ page. Targets
 * CEOs, HR Directors, L&D Managers in Portugal. Funnel destination for
 * LinkedIn organic content (EDIT. company page) + LinkedIn Ads campaign.
 *
 * Architecture: subdomain-only. When HTTP_HOST = empresas.weareedit.io we
 * short-circuit WP rendering at `template_redirect` priority 0 and emit our
 * own HTML — completely bypassing the formacao theme. This gives us:
 *   - clean stack (no theme CSS leaking, no header/footer chrome)
 *   - fastest possible TTFB (no template_part overhead)
 *   - independent SEO surface (own H1, own title, own meta)
 *
 * The subdomain points at the same WordPress install via DNS CNAME, so we
 * stay in the existing workflow: edit code → bump version → commit → tag
 * → push → one-click WP update.
 *
 * Content reuse: client logos, áreas, process, value props, FAQ are reused
 * by reference from EDIT_Formacao_Corporativa_Page constants (curated by
 * Daniel 2026-05-29). That class stays at STATUS='draft' as a content
 * holder — we deprecate it once this surface is stable.
 *
 * Status switch:
 *   STATUS = 'live'      — subdomain renders to the public
 *   STATUS = 'preview'   — renders only for logged-in admins (others get 404)
 *   STATUS = 'off'       — class is inert (subdomain returns the default WP page)
 *
 * Sprint plan:
 *   Sprint 1 (tonight): plumbing — subdomain detect + hero + logo wall +
 *                       stats + value props + footer
 *   Sprint 2 (Sat):     áreas cards + process + financing deep-dive + FAQ
 *   Sprint 3 (Sat):     lead form wired to Brevo Empresas Inbound pipeline
 *   Sprint 4 (Sat/Sun): 301 redirects from /formacao-corporativa/,
 *                       /formacao-in-company/, /formacao-digital-para-empresas/
 *   Sprint 5 (Sun):     GA4 + GTM tracking, soft launch
 *
 * @since 1.5.288
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Empresas_Page {

    /**
     * Master visibility switch.
     *
     *   'live'    — public renders the sub-brand page
     *   'preview' — only logged-in admins see it (404 for everyone else)
     *   'off'     — class is fully inert
     *
     * Sprint 1 ships as 'preview' so Daniel + team can review at the
     * subdomain without exposing an in-progress page to crawlers.
     * Flip to 'live' for soft launch.
     */
    const STATUS = 'preview';

    /**
     * Exact match: HTTP_HOST must equal this string. The www.empresas.*
     * variant is normalised to non-www by the WP_HOME canonical redirect.
     */
    const SUBDOMAIN = 'empresas.weareedit.io';

    /**
     * Legacy URLs to 301-redirect into the new home. Gated by
     * `redirects_active()` so they stay dormant until STATUS = 'live'.
     */
    const LEGACY_PATHS = [
        '/formacao-corporativa/',
        '/formacao-corporativa',
        '/formacao-in-company/',
        '/formacao-in-company',
        '/formacao-digital-para-empresas/',
        '/formacao-digital-para-empresas',
    ];

    /**
     * REST API — lead form endpoint.
     * Form on the page POSTs JSON here; we validate, send 2 emails, log,
     * and (when configured) push a deal into Brevo Sales Hub.
     */
    const REST_NAMESPACE        = 'edit/v1';
    const REST_ROUTE            = '/lead-empresas';
    const ADMIN_EMAIL           = 'empresas@weareedit.io';
    const ADMIN_EMAIL_FALLBACK  = 'geral@weareedit.io';
    const RATE_LIMIT_PER_HOUR   = 20;

    // Brevo Reuniões booking link offered in the lead auto-reply email.
    const BOOKING_URL           = 'https://meet.brevo.com/daniel-devera';

    /**
     * Brevo Sales Hub integration — STUBBED until Daniel creates the
     * "Empresas Inbound" pipeline and provides the IDs. Setting both
     * to non-empty strings activates the Brevo posting in post_to_brevo().
     *
     * Where to find:
     *   Brevo → Sales Hub → Pipelines → click "Empresas Inbound" →
     *   pipeline_id is in the URL or pipeline settings.
     *   First stage ID (e.g., "Novo Lead") is in the pipeline stages list.
     */
    const BREVO_PIPELINE_ID = '';
    const BREVO_STAGE_ID    = '';

    /**
     * Preview token — share-link bypass for STATUS = 'preview'.
     * Visit https://empresas.weareedit.io/?preview=<TOKEN> to view the page
     * without needing WP admin login (which doesn't carry cross-subdomain
     * cookies by default). Rotate the token any time by changing this string.
     */
    const PREVIEW_TOKEN = 'edit-2026';

    public static function init(): void {
        if ( self::STATUS === 'off' ) return;

        // Subdomain renderer — fires only when the request host matches.
        if ( self::is_subdomain_request() ) {
            // Strip Rank Math + theme noise from <head>. We control everything.
            add_action( 'template_redirect', [ __CLASS__, 'render_page' ], 0 );
        }

        // 301 redirects from legacy URLs to the new home — only active
        // when STATUS = 'live' (otherwise legacy URLs keep working as-is).
        if ( self::redirects_active() ) {
            add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect_legacy' ], 1 );
        }

        // REST API — lead form endpoint. Registered unconditionally so the
        // form on the empresas surface can POST even while STATUS = 'preview'
        // (admins testing the form before public launch).
        add_action( 'rest_api_init', [ __CLASS__, 'register_rest_route' ] );
    }

    private static function is_subdomain_request(): bool {
        $host = strtolower( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
        // Normalise: strip port + leading www.
        $host = preg_replace( '/:\d+$/', '', $host );
        $host = preg_replace( '/^www\./', '', $host );
        return $host === self::SUBDOMAIN;
    }

    private static function redirects_active(): bool {
        return self::STATUS === 'live';
    }

    /**
     * Final 301 handler — bounces /formacao-corporativa/, /formacao-in-company/,
     * /formacao-digital-para-empresas/ to https://empresas.weareedit.io/.
     */
    public static function maybe_redirect_legacy(): void {
        $path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
        if ( ! in_array( $path, self::LEGACY_PATHS, true ) ) return;

        wp_safe_redirect( 'https://' . self::SUBDOMAIN . '/', 301 );
        exit;
    }

    /**
     * Preview gate — non-admins see a clean 404 during preview mode so the
     * page doesn't get crawled before we open it.
     */
    private static function should_render_publicly(): bool {
        if ( self::STATUS === 'live' ) return true;
        if ( self::STATUS === 'preview' ) {
            if ( current_user_can( 'manage_options' ) ) return true;
            // Share-link bypass: ?preview=<TOKEN> grants visibility without
            // needing admin login (cookies don't cross subdomain boundary).
            $token = isset( $_GET['preview'] ) ? (string) wp_unslash( $_GET['preview'] ) : '';
            if ( ! empty( self::PREVIEW_TOKEN ) && hash_equals( self::PREVIEW_TOKEN, $token ) ) {
                return true;
            }
        }
        return false;
    }

    /* ─────────────────────────────────────────────────────────────────────
     * REST API — lead form endpoint
     * POST /wp-json/edit/v1/lead-empresas
     * ────────────────────────────────────────────────────────────────── */

    public static function register_rest_route(): void {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_lead_submission' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Handle a lead-form POST submission.
     *
     * Pipeline:
     *   1. Honeypot check (silent OK to confuse bots)
     *   2. Required field validation
     *   3. Email format check
     *   4. Per-IP rate limit (5/hour)
     *   5. Send admin notification email (to empresas@weareedit.io)
     *   6. Send user auto-reply
     *   7. Push deal to Brevo Sales Hub (if configured)
     *   8. Log to CF7 Debug Log (single-pane visibility)
     *
     * @return WP_REST_Response
     */
    public static function handle_lead_submission( $request ) {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) || empty( $params ) ) {
            $params = $request->get_params();
        }

        // Honeypot — silent fake-OK so bots think it worked.
        if ( ! empty( $params['website'] ) ) {
            return new WP_REST_Response( [ 'status' => 'ok', 'message' => 'Recebido.' ], 200 );
        }

        // Required field validation.
        $required = [
            'nome'    => 'Nome',
            'email'   => 'Email',
            'empresa' => 'Empresa',
            'cargo'   => 'Cargo',
            'size'    => 'Tamanho da equipa',
        ];
        $missing = [];
        foreach ( $required as $key => $label ) {
            if ( empty( trim( (string) ( $params[ $key ] ?? '' ) ) ) ) {
                $missing[] = $label;
            }
        }
        if ( ! empty( $missing ) ) {
            return new WP_REST_Response( [
                'status'  => 'error',
                'message' => 'Campos obrigatórios em falta: ' . implode( ', ', $missing ),
            ], 400 );
        }

        if ( ! is_email( $params['email'] ) ) {
            return new WP_REST_Response( [
                'status'  => 'error',
                'message' => 'Email inválido.',
            ], 400 );
        }

        // Per-IP rate limit. Admins bypass for testing.
        $ip       = self::client_ip();
        $is_admin = is_user_logged_in() && current_user_can( 'manage_options' );
        if ( ! $is_admin ) {
            $rl_key = 'edit_empresas_rl_' . md5( $ip );
            $count  = (int) get_transient( $rl_key );
            if ( $count >= self::RATE_LIMIT_PER_HOUR ) {
                return new WP_REST_Response( [
                    'status'  => 'error',
                    'message' => 'Demasiadas tentativas. Tente novamente dentro de uma hora.',
                ], 429 );
            }
            set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );
        }

        // Sanitize.
        $areas = isset( $params['areas'] ) && is_array( $params['areas'] )
            ? array_map( 'sanitize_text_field', $params['areas'] )
            : [];

        $data = [
            'nome'     => sanitize_text_field( $params['nome'] ),
            'email'    => sanitize_email( $params['email'] ),
            'empresa'  => sanitize_text_field( $params['empresa'] ),
            'cargo'    => sanitize_text_field( $params['cargo'] ),
            'size'     => sanitize_text_field( $params['size'] ),
            'timeline' => sanitize_text_field( $params['timeline'] ?? '' ),
            'areas'    => $areas,
            'mensagem' => sanitize_textarea_field( $params['mensagem'] ?? '' ),
            'ip'       => filter_var( $ip, FILTER_VALIDATE_IP ) ?: '',
            'ua'       => isset( $_SERVER['HTTP_USER_AGENT'] )
                           ? mb_substr( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ), 0, 200 )
                           : '',
            'ts'       => current_time( 'c' ),
        ];

        // Send emails.
        $admin_sent = self::send_admin_mail( $data );
        $user_sent  = self::send_user_mail( $data );

        // Brevo deal — returns deal ID on success, or "fail:<reason>" on failure.
        $brevo_result = self::post_to_brevo( $data );

        // Log to CF7 Debug Log if available.
        if ( class_exists( 'EDIT_CF7_Debug' ) ) {
            // Compact diagnostic of what discovery captured for the 5 custom attrs.
            $diag = [];
            if ( $brevo_result !== 'fail:no_api_key' ) {
                $settings = get_option( 'edit_seo_fix_settings', [] );
                $api_key  = trim( (string) ( $settings['brevo_api_key'] ?? '' ) );
                if ( $api_key !== '' ) {
                    $meta_map = self::discover_brevo_deal_attribute_meta( $api_key );
                    if ( is_array( $meta_map ) ) {
                        foreach ( [ 'Cargo', 'Tamanho da equipa', 'Urgência', 'Áreas de interesse', 'Origem do lead', 'Descrição do acordo' ] as $label ) {
                            $key  = self::normalize_attr_label( $label );
                            $meta = $meta_map[ $key ] ?? null;
                            if ( ! $meta ) { $diag[] = $label . '=missing'; continue; }
                            $type = $meta['type'] ?? '?';
                            $opts = $meta['options'] ?? [];
                            $opts_snippet = '';
                            if ( ! empty( $opts ) ) {
                                $labels_only = array_map( function( $o ) { return $o['label']; }, array_slice( $opts, 0, 6 ) );
                                $opts_snippet = '=[' . implode( '|', $labels_only ) . ']';
                            }
                            $raw_first = $meta['raw_first'] ?? '';
                            $raw_snippet = $raw_first !== '' && $raw_first !== null ? ' raw=' . mb_substr( $raw_first, 0, 80 ) : '';
                            $diag[] = sprintf( '%s[%s:%s/opt=%d%s%s]', $label, $meta['slug'], $type, count( $opts ), $opts_snippet, $raw_snippet );
                        }
                    }
                }
            }

            EDIT_CF7_Debug::write( [
                'event'       => 'empresas_lead_submit',
                'form_title'  => 'Empresas Inbound (empresas.weareedit.io)',
                'company'     => $data['empresa'],
                'name'        => $data['nome'],
                'email'       => $data['email'],
                'cargo'       => $data['cargo'],
                'size'        => $data['size'],
                'timeline'    => $data['timeline'],
                'admin_mail'  => $admin_sent ? 'sent' : 'failed',
                'user_mail'   => $user_sent ? 'sent' : 'failed',
                'brevo_deal'  => $brevo_result,
                'brevo_attrs' => empty( $diag ) ? '(none)' : implode( ' | ', $diag ),
                'ip'          => $data['ip'],
            ] );
        }

        return new WP_REST_Response( [
            'status'  => 'ok',
            'message' => 'Pedido recebido. Voltamos em 24h úteis com um plano e uma proposta.',
        ], 200 );
    }

    private static function client_ip(): string {
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            return (string) wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] );
        }
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $list = explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            return trim( $list[0] );
        }
        return (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
    }

    private static function send_admin_mail( array $data ): bool {
        $subject = sprintf( '[Empresas] Pedido de %s — %s', $data['empresa'], $data['cargo'] );

        $areas_str = empty( $data['areas'] ) ? '—' : implode( ', ', $data['areas'] );

        $body  = "Novo pedido de proposta recebido em empresas.weareedit.io\n";
        $body .= "─────────────────────────────────────────────────────\n\n";
        $body .= "Nome: {$data['nome']}\n";
        $body .= "Email: {$data['email']}\n";
        $body .= "Empresa: {$data['empresa']}\n";
        $body .= "Cargo: {$data['cargo']}\n";
        $body .= "Tamanho da equipa: {$data['size']}\n";
        $body .= "Quando começar: " . ( $data['timeline'] ?: '—' ) . "\n";
        $body .= "Áreas de interesse: {$areas_str}\n\n";
        $body .= "Mensagem:\n" . ( $data['mensagem'] ?: '—' ) . "\n\n";
        $body .= "─────────────────────────────────────────────────────\n";
        $body .= "Meta:\n";
        $body .= "IP: {$data['ip']}\n";
        $body .= "Timestamp: {$data['ts']}\n";

        $headers = [
            'Reply-To: ' . $data['nome'] . ' <' . $data['email'] . '>',
            'Bcc: ' . self::ADMIN_EMAIL_FALLBACK,
            'X-Mailer: weareedit-site-engine/empresas',
        ];

        return (bool) wp_mail( self::ADMIN_EMAIL, $subject, $body, $headers );
    }

    private static function send_user_mail( array $data ): bool {
        $subject = 'Pedido recebido — EDIT. para Empresas';

        // Trim trailing period so PT brand names ending in "." (EDIT., Co. Lda.,
        // S.A., etc.) don't produce double-period sentences ("para EDIT..").
        $empresa_clean = rtrim( $data['empresa'], '.' );

        $body  = "Olá " . $data['nome'] . ",\n\n";
        $body .= "Sou o Daniel Devera, fundador da EDIT. Recebi o vosso pedido para " . $empresa_clean . " — obrigado pelo interesse.\n\n";
        $body .= "Eu próprio respondo a todos os pedidos vindos pela empresas.weareedit.io. ";
        $body .= "Em 24 horas úteis contacto-vos para agendar uma conversa de 30 minutos onde percebemos o vosso contexto, equipa e objetivos. ";
        $body .= "Depois desenhamos juntos um programa à medida do vosso setor.\n\n";
        $body .= "Se quiser adiantar, pode reservar já um horário aqui:\n";
        $body .= self::BOOKING_URL . "\n\n";
        $body .= "Para qualquer coisa urgente, responda diretamente a este email.\n\n";
        $body .= "Cumprimentos,\n";
        $body .= "Daniel Devera\n\n";
        $body .= "—\n";
        $body .= "Daniel Devera · Fundador, EDIT.\n";
        $body .= "EDIT. — Disruptive Digital Education\n";
        $body .= "Entidade Formadora Certificada DGERT nº 18391\n";
        $body .= "https://weareedit.io\n";

        $headers = [
            'From: Daniel Devera (EDIT.) <' . self::ADMIN_EMAIL . '>',
            'Reply-To: ' . self::ADMIN_EMAIL,
            'X-Mailer: weareedit-site-engine/empresas',
        ];

        return (bool) wp_mail( $data['email'], $subject, $body, $headers );
    }

    /**
     * Auto-discover the Brevo Empresas Inbound pipeline + first stage IDs by NAME.
     *
     * Avoids the dev-pain of extracting IDs from Brevo's UI. The plugin calls
     * GET /crm/v3/pipelines once, finds the pipeline named "Empresas Inbound",
     * picks the first stage as the entry point (Novo Lead), caches both in a
     * transient for 12 hours. Future pipeline renames / reorders auto-adapt.
     *
     * @return array|null  ['pipeline' => '...', 'stage' => '...'] or null on failure
     */
    /**
     * Verbose variant of discover_brevo_pipeline_ids — returns either an
     * ['pipeline'=>..., 'stage'=>...] array on success, or a string tagged
     * with the specific failure reason for diagnostic logging.
     *
     * @return array|string
     */
    private static function discover_brevo_pipeline_ids_verbose( string $api_key ) {
        $cache_key = 'edit_empresas_brevo_pipeline_ids';
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached['pipeline'] ) && ! empty( $cached['stage'] ) ) {
            return $cached;
        }

        // Brevo Sales Hub: list all pipelines.
        $res = wp_remote_get( 'https://api.brevo.com/v3/crm/pipeline/details/all', [
            'timeout' => 8,
            'headers' => [
                'api-key' => $api_key,
                'Accept'  => 'application/json',
            ],
        ] );
        if ( is_wp_error( $res ) ) return 'fail:discovery_wp_error';

        $code = wp_remote_retrieve_response_code( $res );
        if ( $code !== 200 ) return 'fail:discovery_http_' . $code;

        $raw  = wp_remote_retrieve_body( $res );
        $body = json_decode( $raw, true );
        if ( ! is_array( $body ) ) return 'fail:discovery_bad_json';

        // Endpoint returns top-level array of pipeline objects.
        $pipelines = isset( $body[0] ) ? $body : ( $body['items'] ?? $body['pipelines'] ?? [] );
        if ( ! is_array( $pipelines ) || empty( $pipelines ) ) {
            $snippet = mb_substr( preg_replace( '/\s+/', ' ', $raw ), 0, 120 );
            return 'fail:discovery_no_pipelines:' . $snippet;
        }

        $available_names = [];
        foreach ( $pipelines as $pipeline ) {
            $name = $pipeline['pipeline_name'] ?? $pipeline['name'] ?? '';
            $available_names[] = $name;
            if ( strcasecmp( trim( $name ), 'Empresas Inbound' ) !== 0 ) continue;

            // Brevo returns the pipeline ID in the `pipeline` field (legacy) or `id`.
            $pipeline_id = (string) ( $pipeline['pipeline'] ?? $pipeline['id'] ?? '' );
            $stages      = $pipeline['stages'] ?? $pipeline['deal_stages'] ?? [];
            if ( empty( $pipeline_id ) ) return 'fail:discovery_no_pipeline_id';
            if ( empty( $stages ) || ! is_array( $stages ) ) return 'fail:discovery_no_stages';

            $stage_id = '';
            foreach ( $stages as $stage ) {
                $stage_name = $stage['name'] ?? '';
                $sid        = (string) ( $stage['id'] ?? '' );
                if ( empty( $sid ) ) continue;
                if ( strcasecmp( trim( $stage_name ), 'Novo Lead' ) === 0 ) { $stage_id = $sid; break; }
                if ( empty( $stage_id ) ) $stage_id = $sid;
            }
            if ( empty( $stage_id ) ) return 'fail:discovery_no_stage_id';

            $ids = [ 'pipeline' => $pipeline_id, 'stage' => $stage_id ];
            set_transient( $cache_key, $ids, 12 * HOUR_IN_SECONDS );
            return $ids;
        }

        return 'fail:discovery_pipeline_not_found:' . implode( '|', array_slice( $available_names, 0, 5 ) );
    }

    private static function discover_brevo_pipeline_ids( string $api_key ): ?array {
        $cache_key = 'edit_empresas_brevo_pipeline_ids';
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached['pipeline'] ) && ! empty( $cached['stage'] ) ) {
            return $cached;
        }

        $res = wp_remote_get( 'https://api.brevo.com/crm/v3/pipelines', [
            'timeout' => 8,
            'headers' => [
                'api-key' => $api_key,
                'Accept'  => 'application/json',
            ],
        ] );
        if ( is_wp_error( $res ) ) return null;
        if ( wp_remote_retrieve_response_code( $res ) !== 200 ) return null;

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! is_array( $body ) ) return null;

        // Brevo returns either a top-level array of pipelines or a wrapper { items: [...] }.
        $pipelines = isset( $body[0] ) ? $body : ( $body['items'] ?? $body['pipelines'] ?? [] );
        if ( ! is_array( $pipelines ) ) return null;

        foreach ( $pipelines as $pipeline ) {
            $name = $pipeline['pipeline_name'] ?? $pipeline['name'] ?? '';
            if ( strcasecmp( trim( $name ), 'Empresas Inbound' ) !== 0 ) continue;

            $pipeline_id = (string) ( $pipeline['pipeline'] ?? $pipeline['id'] ?? '' );
            $stages      = $pipeline['stages'] ?? $pipeline['deal_stages'] ?? [];
            if ( empty( $pipeline_id ) || empty( $stages ) || ! is_array( $stages ) ) continue;

            // First non-closed stage is "Novo Lead" — pick that as entry point.
            $stage_id = '';
            foreach ( $stages as $stage ) {
                $stage_name = $stage['name'] ?? '';
                $sid        = (string) ( $stage['id'] ?? '' );
                if ( empty( $sid ) ) continue;
                if ( strcasecmp( trim( $stage_name ), 'Novo Lead' ) === 0 ) { $stage_id = $sid; break; }
                if ( empty( $stage_id ) ) $stage_id = $sid; // fallback: first stage
            }
            if ( empty( $stage_id ) ) continue;

            $ids = [ 'pipeline' => $pipeline_id, 'stage' => $stage_id ];
            set_transient( $cache_key, $ids, 12 * HOUR_IN_SECONDS );
            return $ids;
        }

        return null;
    }

    /**
     * Normalize a Brevo attribute label for case/accent-insensitive matching.
     * Lowercase + strip combining marks so "Urgência" matches "urgencia".
     */
    private static function normalize_attr_label( string $label ): string {
        $label = trim( $label );
        if ( function_exists( 'transliterator_transliterate' ) ) {
            $stripped = transliterator_transliterate( 'Any-Latin; Latin-ASCII;', $label );
            if ( is_string( $stripped ) ) $label = $stripped;
        }
        return mb_strtolower( $label );
    }

    /**
     * Loosely normalize a value for option-matching. Lowercase, strip accents,
     * strip ALL hyphens/dashes/whitespace so "1-10 pessoas", "1–10 pessoas",
     * "1 10 pessoas", and "110pessoas" all collapse to the same key.
     */
    private static function normalize_option_value( string $s ): string {
        $s = trim( $s );
        if ( function_exists( 'transliterator_transliterate' ) ) {
            $t = transliterator_transliterate( 'Any-Latin; Latin-ASCII;', $s );
            if ( is_string( $t ) ) $s = $t;
        }
        $s = mb_strtolower( $s );
        $s = preg_replace( '/[\s\-\x{2013}\x{2014}]+/u', '', $s );
        return (string) $s;
    }

    /**
     * Auto-discover Brevo deal attribute metadata by label name. Returns map
     *   normalized-label => [ 'slug' => internalName, 'options' => [string,...] ]
     * (options only populated for Múltipla escolha / select-type attrs).
     * Cached 12h. Returns null if API call fails.
     */
    private static function discover_brevo_deal_attribute_meta( string $api_key ): ?array {
        $cache_key = 'edit_empresas_brevo_deal_attr_meta_v6';
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached ) ) return $cached;

        $res = wp_remote_get( 'https://api.brevo.com/v3/crm/attributes/deals', [
            'timeout' => 8,
            'headers' => [
                'api-key' => $api_key,
                'Accept'  => 'application/json',
            ],
        ] );
        if ( is_wp_error( $res ) ) return null;
        if ( wp_remote_retrieve_response_code( $res ) !== 200 ) return null;

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! is_array( $body ) ) return null;
        $items = isset( $body[0] ) ? $body : ( $body['items'] ?? $body['attributes'] ?? [] );
        if ( ! is_array( $items ) ) return null;

        $map = [];
        foreach ( $items as $attr ) {
            $label = (string) ( $attr['label'] ?? '' );
            $slug  = (string) ( $attr['internalName'] ?? $attr['name'] ?? '' );
            if ( $label === '' || $slug === '' ) continue;

            // Capture options as label/value pairs so we can match by label
            // but send the value (Brevo's internal ID, if any).
            $options    = [];
            $raw_first  = null;
            $raw_opts   = $attr['attributeOptions'] ?? $attr['options'] ?? [];
            if ( is_array( $raw_opts ) ) {
                foreach ( $raw_opts as $opt ) {
                    if ( $raw_first === null ) $raw_first = $opt; // for diagnostic
                    if ( is_string( $opt ) ) {
                        $options[] = [ 'label' => $opt, 'value' => $opt ];
                        continue;
                    }
                    if ( is_array( $opt ) ) {
                        // Brevo's deal-attr option shape: { key: "<id>", value: "<display>" }.
                        // Send the key; match user-facing form input against the display value.
                        $display = (string) ( $opt['value'] ?? $opt['label'] ?? $opt['name'] ?? '' );
                        $id      = (string) ( $opt['key'] ?? $opt['id'] ?? $display );
                        if ( $display === '' && $id === '' ) continue;
                        $options[] = [
                            'label' => $display !== '' ? $display : $id,
                            'value' => $id !== ''      ? $id      : $display,
                        ];
                    }
                }
            }

            $type = mb_strtolower( (string) ( $attr['attributeTypeName'] ?? $attr['type'] ?? '' ) );

            $map[ self::normalize_attr_label( $label ) ] = [
                'slug'      => $slug,
                'type'      => $type,
                'options'   => $options,
                'raw_first' => is_array( $raw_first ) ? wp_json_encode( $raw_first ) : ( is_string( $raw_first ) ? $raw_first : null ),
            ];
        }
        if ( empty( $map ) ) return null;

        set_transient( $cache_key, $map, 12 * HOUR_IN_SECONDS );
        return $map;
    }

    /**
     * Auto-discover the Brevo contact list ID for "Empresas * Inbound" so the
     * plugin doesn't have to hardcode it. Cached 12h. Returns null on failure.
     */
    private static function discover_brevo_empresas_list_id( string $api_key ): ?int {
        $cache_key = 'edit_empresas_brevo_list_id_v1';
        $cached    = get_transient( $cache_key );
        if ( $cached !== false && (int) $cached > 0 ) return (int) $cached;

        // Paginate through lists — Brevo defaults to 10/page; we ask for 50.
        $res = wp_remote_get( 'https://api.brevo.com/v3/contacts/lists?limit=50&offset=0', [
            'timeout' => 8,
            'headers' => [
                'api-key' => $api_key,
                'Accept'  => 'application/json',
            ],
        ] );
        if ( is_wp_error( $res ) ) return null;
        if ( wp_remote_retrieve_response_code( $res ) !== 200 ) return null;

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! is_array( $body ) ) return null;
        $lists = $body['lists'] ?? $body['items'] ?? ( isset( $body[0] ) ? $body : [] );
        if ( ! is_array( $lists ) ) return null;

        foreach ( $lists as $list ) {
            $name = (string) ( $list['name'] ?? '' );
            if ( strcasecmp( trim( $name ), 'Empresas * Inbound' ) !== 0 ) continue;
            $id = (int) ( $list['id'] ?? 0 );
            if ( $id > 0 ) {
                set_transient( $cache_key, $id, 12 * HOUR_IN_SECONDS );
                return $id;
            }
        }
        return null;
    }

    /**
     * Push deal to Brevo Sales Hub.
     *
     * Two-step: (1) upsert contact via /v3/contacts, (2) create deal via /crm/v3/deals.
     * Pipeline + first stage IDs are auto-discovered by name "Empresas Inbound"
     * via discover_brevo_pipeline_ids() — see that method's docblock.
     *
     * Reuses the Brevo API key from wp_options the existing EDIT_Brevo_Mail_Router
     * class already consumes — no new config needed.
     *
     * @return string|null  Brevo deal ID on success, null otherwise (form falls back to email-only).
     */
    private static function post_to_brevo( array $data ): string {
        // Reuse Brevo API key from existing plugin config (same source the mail router uses).
        $settings = get_option( 'edit_seo_fix_settings', [] );
        $api_key  = trim( (string) ( $settings['brevo_api_key'] ?? '' ) );
        if ( $api_key === '' ) return 'fail:no_api_key';

        // Auto-discover pipeline + stage IDs by name (cached 12h).
        // Manual override: set BREVO_PIPELINE_ID + BREVO_STAGE_ID consts above to skip discovery.
        if ( ! empty( self::BREVO_PIPELINE_ID ) && ! empty( self::BREVO_STAGE_ID ) ) {
            $ids = [ 'pipeline' => self::BREVO_PIPELINE_ID, 'stage' => self::BREVO_STAGE_ID ];
        } else {
            $discovery = self::discover_brevo_pipeline_ids_verbose( $api_key );
            if ( is_string( $discovery ) ) return $discovery; // tagged failure
            $ids = $discovery;
        }

        // 1) Upsert the contact via /v3/contacts.
        $contact_attrs = [
            'NOME'             => $data['nome'],
            'EMPRESA'          => $data['empresa'],
            'CARGO'            => $data['cargo'],
            'EMPRESAS_SOURCE'  => 'empresas.weareedit.io',
        ];
        $contact_body = [
            'email'         => $data['email'],
            'attributes'    => $contact_attrs,
            'updateEnabled' => true,
        ];
        // Add to the "Empresas * Inbound" contact list (auto-discovered by name).
        $list_id = self::discover_brevo_empresas_list_id( $api_key );
        if ( $list_id ) {
            $contact_body['listIds'] = [ $list_id ];
        }
        $contact_res = wp_remote_post( 'https://api.brevo.com/v3/contacts', [
            'timeout' => 8,
            'headers' => [
                'api-key'      => $api_key,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode( $contact_body ),
        ] );
        if ( is_wp_error( $contact_res ) ) return 'fail:contact_wp_error';
        $contact_code = wp_remote_retrieve_response_code( $contact_res );
        if ( $contact_code === 401 || $contact_code === 403 ) return 'fail:contact_' . $contact_code;

        // Capture contact ID from upsert response — needed to link the deal.
        // 201 returns { id }, 204 (existing contact) returns empty body — we need a follow-up GET.
        $contact_id = 0;
        $contact_body = json_decode( wp_remote_retrieve_body( $contact_res ), true );
        if ( is_array( $contact_body ) && ! empty( $contact_body['id'] ) ) {
            $contact_id = (int) $contact_body['id'];
        } elseif ( $contact_code === 204 ) {
            // Existing contact — fetch their ID by email.
            $lookup = wp_remote_get( 'https://api.brevo.com/v3/contacts/' . rawurlencode( $data['email'] ), [
                'timeout' => 8,
                'headers' => [
                    'api-key' => $api_key,
                    'Accept'  => 'application/json',
                ],
            ] );
            if ( ! is_wp_error( $lookup ) && wp_remote_retrieve_response_code( $lookup ) === 200 ) {
                $lookup_body = json_decode( wp_remote_retrieve_body( $lookup ), true );
                if ( is_array( $lookup_body ) && ! empty( $lookup_body['id'] ) ) {
                    $contact_id = (int) $lookup_body['id'];
                }
            }
        }

        // 2) Create the deal in the Empresas Inbound pipeline.
        $name_parts = [ $data['empresa'], $data['cargo'], $data['size'] ];
        if ( ! empty( $data['timeline'] ) ) $name_parts[] = $data['timeline'];
        $deal_name = implode( ' · ', $name_parts );

        // Baseline attrs always required.
        $deal_attrs = [
            'pipeline'   => $ids['pipeline'],
            'deal_stage' => $ids['stage'],
        ];

        // Try to enrich with custom attributes — auto-discover slugs by label.
        // If discovery fails or attrs aren't registered yet, fall back to baseline.
        $meta_map = self::discover_brevo_deal_attribute_meta( $api_key );
        if ( is_array( $meta_map ) ) {
            // field => [ Brevo attr label, raw form value ]
            $field_map = [
                [ 'Cargo',                       $data['cargo'] ],
                [ 'Tamanho da equipa',           $data['size'] ],
                [ 'Urgência',                    $data['timeline'] ?? '' ],
                [ 'Áreas de interesse',          implode( ', ', (array) ( $data['areas'] ?? [] ) ) ],
                [ 'Origem do lead',              'Website' ],
                [ 'Descrição do acordo',         $data['mensagem'] ?? '' ],
            ];
            foreach ( $field_map as [ $label, $value ] ) {
                $value = (string) $value;
                if ( $value === '' ) continue;
                $meta = $meta_map[ self::normalize_attr_label( $label ) ] ?? null;
                if ( ! $meta || empty( $meta['slug'] ) ) continue;

                // Decide payload shape from the attribute TYPE (not from whether
                // options were captured — Brevo's API doesn't always expose them).
                $type     = (string) ( $meta['type'] ?? '' );
                $is_text  = ( $type === '' || stripos( $type, 'text' ) !== false || stripos( $type, 'string' ) !== false );

                if ( $is_text ) {
                    $deal_attrs[ $meta['slug'] ] = $value;
                    continue;
                }

                // Multi-choice / select: must match against a known option, else SKIP.
                // Options are stored as [ 'label' => ..., 'value' => ... ] pairs.
                // Match by label; send the value (Brevo's internal ID, if distinct).
                if ( empty( $meta['options'] ) ) continue;
                $target  = self::normalize_option_value( $value );
                $matched_value = null;
                foreach ( $meta['options'] as $opt ) {
                    if ( self::normalize_option_value( $opt['label'] ) === $target
                      || self::normalize_option_value( $opt['value'] ) === $target ) {
                        $matched_value = $opt['value'];
                        break;
                    }
                }
                if ( $matched_value === null ) continue;
                $deal_attrs[ $meta['slug'] ] = [ $matched_value ];
            }
        }

        $deal_payload = [
            'name'       => $deal_name,
            'attributes' => $deal_attrs,
        ];
        if ( $contact_id > 0 ) {
            $deal_payload['linkedContactsIds'] = [ $contact_id ];
        }

        $deal_res = wp_remote_post( 'https://api.brevo.com/v3/crm/deals', [
            'timeout' => 8,
            'headers' => [
                'api-key'      => $api_key,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode( $deal_payload ),
        ] );

        if ( is_wp_error( $deal_res ) ) return 'fail:deal_wp_error';

        $code = wp_remote_retrieve_response_code( $deal_res );
        if ( $code < 200 || $code >= 300 ) {
            $body_snippet = mb_substr( (string) wp_remote_retrieve_body( $deal_res ), 0, 120 );
            return 'fail:deal_' . $code . ':' . preg_replace( '/\s+/', ' ', $body_snippet );
        }

        $body = json_decode( wp_remote_retrieve_body( $deal_res ), true );
        return $body['id'] ?? 'fail:deal_no_id';
    }

    /**
     * Main render — full HTML out, no theme chrome, no template_part calls.
     */
    public static function render_page(): void {
        if ( ! self::should_render_publicly() ) {
            // Preview mode + not admin: clean 404 (don't poison the index).
            status_header( 404 );
            nocache_headers();
            echo '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><title>404 — Em construção</title></head><body><p style="font-family:system-ui;text-align:center;padding:80px 20px;color:#666">empresas.weareedit.io — em construção. Em breve.</p></body></html>';
            exit;
        }

        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: text/html; charset=UTF-8' );

        self::emit_html();
        exit;
    }

    /* ─────────────────────────────────────────────────────────────────────
     * Content (Sprint 1 — minimal, will grow over the weekend)
     * Reuses curated constants from EDIT_Formacao_Corporativa_Page so we
     * keep a single source of truth.
     * ────────────────────────────────────────────────────────────────── */

    /**
     * Empresas-specific client logo list — all converted to black-on-transparent
     * PNGs, bundled in plugin assets/img/clients/. Avoids the original CDN's
     * white-on-transparent and white-on-black variants that don't render
     * cleanly on a light-bg page.
     */
    private static function clients(): array {
        $base = WEAREDIT_SITE_ENGINE_URL . 'assets/img/clients/';
        return [
            [ 'name' => 'Pfizer',              'slug' => 'pfizer',     'logo' => $base . 'pfizer-header.png' ],
            [ 'name' => 'FNAC',                'slug' => 'fnac',       'logo' => $base . 'fnac-header.png' ],
            [ 'name' => 'Adidas',              'slug' => 'adidas',     'logo' => $base . 'adidas-header.png' ],
            [ 'name' => 'Galp',                'slug' => 'galp',       'logo' => $base . 'galp-header.png' ],
            [ 'name' => 'Worten',              'slug' => 'worten',     'logo' => $base . 'worten-header.png' ],
            [ 'name' => 'CM Porto',            'slug' => 'cm-porto',   'logo' => $base . 'porto-cm-1.png' ],
            [ 'name' => 'Nestlé',              'slug' => 'nestle',     'logo' => $base . 'nestle-logo-1.png' ],
            [ 'name' => 'TAP Air Portugal',    'slug' => 'tap',        'logo' => $base . 'tap-air-portugal-1.png' ],
            [ 'name' => 'Altice',              'slug' => 'altice',     'logo' => $base . 'altice-logo.png' ],
            [ 'name' => 'Sonae MC',            'slug' => 'sonae-mc',   'logo' => $base . 'sonae-mc-1.png' ],
            [ 'name' => 'Santa Casa',          'slug' => 'santa-casa', 'logo' => $base . 'santa-casa.png' ],
            [ 'name' => 'Governo de Portugal', 'slug' => 'governo',    'logo' => $base . 'governo-de-portugal.png' ],
        ];
    }

    private static function value_props(): array {
        if ( class_exists( 'EDIT_Formacao_Corporativa_Page' ) ) {
            return EDIT_Formacao_Corporativa_Page::VALUE_PROPS;
        }
        return [];
    }

    private static function stats(): array {
        if ( class_exists( 'EDIT_Formacao_Corporativa_Page' ) ) {
            return EDIT_Formacao_Corporativa_Page::STATS;
        }
        return [];
    }

    private static function areas(): array {
        if ( class_exists( 'EDIT_Formacao_Corporativa_Page' ) ) {
            return EDIT_Formacao_Corporativa_Page::AREAS;
        }
        return [];
    }

    private static function process(): array {
        if ( class_exists( 'EDIT_Formacao_Corporativa_Page' ) ) {
            return EDIT_Formacao_Corporativa_Page::PROCESS;
        }
        return [];
    }

    private static function faq(): array {
        if ( class_exists( 'EDIT_Formacao_Corporativa_Page' ) ) {
            return EDIT_Formacao_Corporativa_Page::FAQ;
        }
        return [];
    }

    /* ─────────────────────────────────────────────────────────────────────
     * HTML template
     * ────────────────────────────────────────────────────────────────── */

    private static function emit_html(): void {
        $title       = 'Formação para Empresas — EDIT.';
        $description = 'A escola digital portuguesa para upskilling e reskilling corporativo. Programas à medida em IA, Data, UX, Design, Marketing Digital e Programação. Elegíveis para Fundos de Compensação e Cheque-Formação.';
        $canonical   = 'https://' . self::SUBDOMAIN . '/';

        $clients     = self::clients();
        $value_props = self::value_props();
        $stats       = self::stats();
        $areas       = self::areas();
        $process     = self::process();
        $faq         = self::faq();
        ?><!doctype html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $title ); ?></title>
<meta name="description" content="<?php echo esc_attr( $description ); ?>">
<link rel="canonical" href="<?php echo esc_url( $canonical ); ?>">

<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
<meta property="og:description" content="<?php echo esc_attr( $description ); ?>">
<meta property="og:url" content="<?php echo esc_url( $canonical ); ?>">
<meta property="og:site_name" content="EDIT. para Empresas">
<meta property="og:locale" content="pt_PT">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo esc_attr( $title ); ?>">
<meta name="twitter:description" content="<?php echo esc_attr( $description ); ?>">

<?php if ( self::STATUS === 'preview' ) : ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>

<link rel="preconnect" href="https://weareedit.io" crossorigin>
<link rel="icon" href="https://weareedit.io/wp-content/uploads/2021/05/cropped-favicon-edit-32x32.png">

<?php // GTM (production only — preview mode keeps analytics quiet) ?>
<?php if ( self::STATUS === 'live' ) : ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-TSP85L');</script>
<!-- End Google Tag Manager -->
<?php endif; ?>

<?php // JSON-LD — Service schema for the B2B offer ?>
<script type="application/ld+json">
<?php echo wp_json_encode( [
    '@context' => 'https://schema.org',
    '@type'    => 'Service',
    'serviceType' => 'Formação Corporativa Digital',
    'name'     => 'EDIT. para Empresas — Formação Digital Corporativa',
    'url'      => 'https://' . self::SUBDOMAIN . '/',
    'description' => $description,
    'provider' => [
        '@type' => 'EducationalOrganization',
        '@id'   => 'https://weareedit.io/#organization',
        'name'  => 'EDIT. — Disruptive Digital Education',
        'url'   => 'https://weareedit.io/',
        'sameAs' => [
            'https://www.linkedin.com/school/weareedit/',
            'https://www.wikidata.org/wiki/Q139907765',
        ],
    ],
    'areaServed' => [
        '@type' => 'Country',
        'name'  => 'Portugal',
    ],
    'audience' => [
        '@type' => 'Audience',
        'audienceType' => 'B2B — CEOs, HR Directors, L&D Managers',
    ],
    'hasOfferCatalog' => [
        '@type' => 'OfferCatalog',
        'name'  => 'Disciplinas',
        'itemListElement' => array_map( function( $area ) {
            return [
                '@type' => 'Offer',
                'itemOffered' => [
                    '@type' => 'Course',
                    'name'  => 'Formação Corporativa · ' . $area['title'],
                    'description' => $area['lede'],
                ],
            ];
        }, $areas ),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); ?>
</script>

<style>
@font-face {
  font-family: 'SctoGroteskA';
  font-weight: 300;
  font-style: normal;
  font-display: swap;
  src: url('<?php echo esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/fonts/SctoGroteskA-Light.woff2' ); ?>') format('woff2');
}
@font-face {
  font-family: 'SctoGroteskA';
  font-weight: 400;
  font-style: normal;
  font-display: swap;
  src: url('<?php echo esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/fonts/SctoGroteskA-Regular.woff2' ); ?>') format('woff2');
}
:root {
  --edit-yellow: #ffdd06;
  --edit-pink: #f92869;
  --edit-teal: #60c5b3;
  --edit-coral: #ec8172;
  --ink: #0a0a0a;
  --grey-1: #f4f4f4;
  --grey-2: #e5e5e5;
  --grey-3: #888;
  --grey-4: #444;
}

*, *::before, *::after { box-sizing: border-box; }

html { -webkit-text-size-adjust: 100%; }

body {
  margin: 0;
  font-family: 'SctoGroteskA', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica, Arial, sans-serif;
  color: var(--ink);
  background: #fff;
  line-height: 1.5;
  font-size: 16px;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

a { color: inherit; text-decoration: none; }

.wrap { max-width: 1240px; margin: 0 auto; padding: 0 28px; }

/* ── HEADER ──────────────────────────────────────────────────────── */
.site-header {
  position: sticky; top: 0; z-index: 50;
  background: #fff;
  border-bottom: 1px solid var(--grey-2);
}
.site-header .wrap {
  display: flex; align-items: center; justify-content: space-between;
  padding-top: 18px; padding-bottom: 18px;
}
.brand {
  display: inline-flex; flex-direction: column; align-items: flex-start; gap: 10px;
  line-height: 1;
}
.brand-lockup {
  display: block; height: 44px; width: auto;
}
.brand-tag {
  font-family: 'SctoGroteskA', sans-serif;
  font-weight: 300;
  font-size: 11px;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--ink);
  padding-left: 3px;
}
.cta-header {
  display: inline-block;
  background: var(--ink); color: #fff;
  padding: 11px 20px;
  font-weight: 600; font-size: 14px;
  border-radius: 4px;
  transition: background 0.18s ease;
}
.cta-header:hover { background: var(--edit-pink); }

/* ── HERO ────────────────────────────────────────────────────────── */
.hero {
  padding: 80px 0 80px 0;
  background: #fff;
}
.hero .eyebrow {
  font-size: 12px; font-weight: 700;
  letter-spacing: 0.22em; text-transform: uppercase;
  color: var(--grey-3);
  margin: 0 0 24px 0;
}
.hero h1 {
  font-size: clamp(40px, 6.4vw, 88px);
  line-height: 0.98;
  letter-spacing: -0.035em;
  font-weight: 700;
  margin: 0 0 28px 0;
  max-width: 16ch;
}
.hero h1 .accent {
  background: var(--edit-yellow);
  padding: 0 0.08em;
  border-radius: 4px;
  white-space: nowrap;
}
.hero .lede {
  font-size: clamp(17px, 1.5vw, 21px);
  line-height: 1.45;
  color: var(--grey-4);
  max-width: 60ch;
  margin: 0 0 40px 0;
}
.hero .ctas {
  display: flex; gap: 12px; flex-wrap: wrap;
}
.btn {
  display: inline-block;
  padding: 16px 26px;
  font-weight: 600; font-size: 15px;
  border-radius: 4px;
  transition: all 0.18s ease;
  cursor: pointer;
  border: 0;
}
.btn-primary {
  background: var(--ink); color: #fff;
}
.btn-primary:hover { background: var(--edit-pink); }
.btn-secondary {
  background: transparent; color: var(--ink);
  border: 1.5px solid var(--ink);
}
.btn-secondary:hover { background: var(--ink); color: #fff; }

/* ── LOGO WALL ───────────────────────────────────────────────────── */
.logo-wall {
  padding: 56px 0 64px 0;
  border-top: 1px solid var(--grey-2);
}
.logo-wall .label {
  font-size: 12px; font-weight: 700;
  letter-spacing: 0.22em; text-transform: uppercase;
  color: var(--grey-3);
  text-align: center;
  margin: 0 0 36px 0;
}
.logo-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 48px 36px;
  align-items: center;
}
.logo-cell {
  display: flex; align-items: center; justify-content: center;
  height: 88px;
}
.logo-cell img {
  max-width: 100%; max-height: 100%;
  object-fit: contain;
  opacity: 0.85;
  transition: opacity 0.2s ease;
}
.logo-cell:hover img {
  opacity: 1;
}

@media (max-width: 880px) {
  .logo-grid { grid-template-columns: repeat(3, 1fr); }
}

/* ── STATS ───────────────────────────────────────────────────────── */
.stats {
  padding: 64px 0;
  background: var(--grey-1);
}
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 28px;
}
.stat {
  text-align: left;
  padding: 0 8px;
  border-left: 3px solid var(--edit-yellow);
  padding-left: 18px;
}
.stat .num {
  font-size: clamp(36px, 4vw, 52px);
  font-weight: 800; letter-spacing: -0.025em;
  line-height: 1;
  color: var(--ink);
  margin-bottom: 8px;
}
.stat .lbl {
  font-size: 14px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: var(--ink);
  margin-bottom: 6px;
}
.stat .sub {
  font-size: 13px;
  color: var(--grey-4);
  line-height: 1.45;
}

@media (max-width: 880px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 36px 28px; }
}

/* ── VALUE PROPS ─────────────────────────────────────────────────── */
.value-props {
  padding: 90px 0;
}
.section-eyebrow {
  font-size: 12px; font-weight: 700;
  letter-spacing: 0.22em; text-transform: uppercase;
  color: var(--grey-3);
  margin: 0 0 12px 0;
}
.section-title {
  font-size: clamp(28px, 3.6vw, 46px);
  line-height: 1.1;
  letter-spacing: -0.02em;
  font-weight: 700;
  margin: 0 0 56px 0;
  max-width: 22ch;
}
.vp-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 56px 64px;
}
.vp {
  padding-top: 22px;
  border-top: 2px solid var(--ink);
}
.vp h3 {
  font-size: 21px; font-weight: 700;
  letter-spacing: -0.01em;
  margin: 0 0 12px 0;
  line-height: 1.25;
}
.vp p {
  font-size: 15.5px;
  color: var(--grey-4);
  line-height: 1.55;
  margin: 0;
}

@media (max-width: 720px) {
  .vp-grid { grid-template-columns: 1fr; gap: 40px; }
}

/* ── AREAS ───────────────────────────────────────────────────────── */
.areas {
  padding: 90px 0;
  background: var(--grey-1);
}
.areas-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  margin-top: 40px;
}
.area-card {
  position: relative;
  background: #fff;
  padding: 32px 28px 28px;
  border-radius: 6px;
  border: 1px solid var(--grey-2);
  transition: all 0.2s ease;
  overflow: hidden;
}
.area-card:hover {
  border-color: var(--ink);
  transform: translateY(-2px);
}
.area-card .icon {
  position: absolute;
  top: 18px; right: 22px;
  font-size: 56px;
  font-weight: 900;
  letter-spacing: -0.04em;
  line-height: 1;
  color: var(--edit-yellow);
  opacity: 0.85;
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
}
.area-card h3 {
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.01em;
  margin: 0 60px 12px 0;
  line-height: 1.2;
}
.area-card .area-lede {
  font-size: 14.5px;
  color: var(--grey-4);
  margin: 0 0 18px 0;
  line-height: 1.55;
}
.area-card .topics {
  list-style: none;
  padding: 14px 0 0 0; margin: 0;
  border-top: 1px solid var(--grey-2);
}
.area-card .topics li {
  font-size: 13px;
  color: var(--grey-4);
  padding: 4px 0;
  display: flex; align-items: center; gap: 8px;
}
.area-card .topics li::before {
  content: '';
  width: 6px; height: 6px;
  background: var(--edit-yellow);
  border-radius: 50%;
  flex-shrink: 0;
}

@media (max-width: 880px) {
  .areas-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 560px) {
  .areas-grid { grid-template-columns: 1fr; }
}

/* ── PROCESS ─────────────────────────────────────────────────────── */
.process {
  padding: 90px 0;
}
.process-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 32px;
  margin-top: 40px;
}
.process-step {
  position: relative;
}
.process-step .num-tile {
  display: inline-flex;
  align-items: center; justify-content: center;
  width: 56px; height: 56px;
  background: var(--ink);
  color: #fff;
  font-weight: 800;
  font-size: 18px;
  letter-spacing: -0.02em;
  border-radius: 6px;
  margin-bottom: 18px;
}
.process-step .time-chip {
  display: inline-block;
  background: var(--edit-yellow);
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.04em;
  padding: 3px 10px;
  border-radius: 12px;
  margin-bottom: 12px;
}
.process-step h3 {
  font-size: 19px;
  font-weight: 700;
  letter-spacing: -0.01em;
  margin: 0 0 10px 0;
  line-height: 1.25;
}
.process-step p {
  font-size: 14.5px;
  color: var(--grey-4);
  line-height: 1.5;
  margin: 0;
}

@media (max-width: 880px) {
  .process-grid { grid-template-columns: repeat(2, 1fr); gap: 40px 32px; }
}
@media (max-width: 560px) {
  .process-grid { grid-template-columns: 1fr; gap: 40px; }
}

/* ── FINANCING ───────────────────────────────────────────────────── */
.financing {
  padding: 100px 0;
  background: var(--ink);
  color: #fff;
}
.financing .section-eyebrow { color: rgba(255,255,255,0.6); }
.financing .section-title {
  color: #fff;
  max-width: 24ch;
}
.financing .section-title .accent {
  background: var(--edit-yellow);
  color: var(--ink);
  padding: 0 0.1em;
  border-radius: 4px;
}
.financing-lede {
  font-size: 17px;
  color: rgba(255,255,255,0.78);
  max-width: 60ch;
  margin: -32px 0 56px 0;
  line-height: 1.55;
}
.financing-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  margin-bottom: 48px;
}
.fin-card {
  padding: 28px 26px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 6px;
  transition: all 0.2s ease;
}
.fin-card:hover {
  background: rgba(255,255,255,0.08);
  border-color: var(--edit-yellow);
}
.fin-card .tag {
  display: inline-block;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  background: var(--edit-yellow);
  color: var(--ink);
  padding: 3px 10px;
  border-radius: 3px;
  margin-bottom: 16px;
}
.fin-card h3 {
  font-size: 21px;
  font-weight: 700;
  letter-spacing: -0.01em;
  margin: 0 0 12px 0;
  line-height: 1.2;
  color: #fff;
}
.fin-card p {
  font-size: 14.5px;
  color: rgba(255,255,255,0.72);
  line-height: 1.55;
  margin: 0;
}
.financing-trust {
  border-top: 1px solid rgba(255,255,255,0.12);
  padding-top: 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}
.financing-trust .badge {
  font-size: 13px;
  color: rgba(255,255,255,0.7);
  letter-spacing: 0.02em;
}
.financing-trust .badge strong {
  color: #fff;
  font-weight: 700;
}
.financing-trust .cta-link {
  font-size: 14px;
  font-weight: 600;
  color: var(--edit-yellow);
  border-bottom: 1px solid var(--edit-yellow);
  padding-bottom: 2px;
}
.financing-trust .cta-link:hover {
  color: #fff;
  border-color: #fff;
}

@media (max-width: 880px) {
  .financing-grid { grid-template-columns: 1fr; }
}

/* ── FAQ ─────────────────────────────────────────────────────────── */
.faq {
  padding: 90px 0;
  background: var(--grey-1);
}
.faq-list {
  max-width: 820px;
  margin: 40px auto 0 auto;
}
.faq-item {
  border-bottom: 1px solid var(--grey-2);
}
.faq-item summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
  padding: 22px 0;
  font-size: 17px;
  font-weight: 600;
  letter-spacing: -0.005em;
  cursor: pointer;
  list-style: none;
  color: var(--ink);
  transition: color 0.15s ease;
}
.faq-item summary::-webkit-details-marker { display: none; }
.faq-item summary::after {
  content: '+';
  font-size: 28px;
  font-weight: 300;
  color: var(--ink);
  width: 24px;
  text-align: center;
  flex-shrink: 0;
  transition: transform 0.2s ease;
}
.faq-item[open] summary::after { content: '−'; }
.faq-item summary:hover { color: var(--edit-pink); }
.faq-item .answer {
  font-size: 15px;
  color: var(--grey-4);
  padding: 0 40px 22px 0;
  line-height: 1.6;
  max-width: 70ch;
}
.faq-item .answer strong { color: var(--ink); }

/* ── LEAD SECTION (form) ─────────────────────────────────────────── */
.lead-section {
  padding: 100px 0;
  background: var(--ink);
  color: #fff;
}
.lead-section .section-eyebrow { color: rgba(255,255,255,0.55); }
.lead-section h2 {
  font-size: clamp(30px, 3.8vw, 46px);
  line-height: 1.05; letter-spacing: -0.025em;
  font-weight: 700;
  margin: 0 0 18px 0;
  color: #fff;
  max-width: 16ch;
}
.lead-section .lead-intro {
  font-size: 17px;
  color: rgba(255,255,255,0.75);
  line-height: 1.5;
  margin: 0 0 24px 0;
  max-width: 42ch;
}
.lead-grid {
  display: grid;
  grid-template-columns: 1fr 1.15fr;
  gap: 64px;
  align-items: start;
}
.lead-bullets {
  list-style: none;
  padding: 0; margin: 28px 0 0 0;
}
.lead-bullets li {
  padding: 12px 0 12px 28px;
  font-size: 15px;
  color: rgba(255,255,255,0.85);
  border-bottom: 1px solid rgba(255,255,255,0.1);
  position: relative;
}
.lead-bullets li::before {
  content: '→';
  position: absolute;
  left: 0; top: 12px;
  color: var(--edit-yellow);
  font-weight: 700;
}

/* Form card */
.lead-form-wrap {
  background: #fff;
  color: var(--ink);
  padding: 32px;
  border-radius: 8px;
  border-top: 4px solid var(--edit-yellow);
}
.lead-form-wrap h3 {
  font-size: 19px;
  font-weight: 700;
  margin: 0 0 6px 0;
  letter-spacing: -0.01em;
}
.lead-form-wrap .form-help {
  font-size: 13.5px;
  color: var(--grey-3);
  margin: 0 0 22px 0;
}

.lead-form .hp {
  position: absolute !important;
  left: -9999px !important;
  width: 1px; height: 1px;
  opacity: 0;
}
.lead-form .field { margin-bottom: 14px; }
.lead-form .field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}
.lead-form label {
  display: block;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 6px;
  letter-spacing: 0.02em;
}
.lead-form input[type="text"],
.lead-form input[type="email"],
.lead-form input[type="tel"],
.lead-form select,
.lead-form textarea {
  width: 100%;
  padding: 11px 12px;
  border: 1.5px solid var(--grey-2);
  border-radius: 4px;
  font-size: 14.5px;
  font-family: inherit;
  background: #fff;
  color: var(--ink);
  transition: border-color 0.15s ease;
  box-sizing: border-box;
}
.lead-form select { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 8' fill='none' stroke='%23444' stroke-width='1.5'><path d='M1 1.5l5 5 5-5'/></svg>"); background-repeat: no-repeat; background-position: right 14px center; background-size: 12px; padding-right: 34px; }
.lead-form input:focus,
.lead-form select:focus,
.lead-form textarea:focus {
  outline: none;
  border-color: var(--ink);
}
.lead-form textarea { resize: vertical; min-height: 70px; }

.lead-form .checkbox-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px 12px;
  background: var(--grey-1);
  padding: 12px 14px;
  border-radius: 4px;
}
.lead-form .checkbox-group label {
  font-weight: 500;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  margin-bottom: 0;
  padding: 4px 0;
  letter-spacing: 0;
}
.lead-form .checkbox-group input[type="checkbox"] {
  width: 15px; height: 15px;
  accent-color: var(--ink);
  flex-shrink: 0;
}

.form-meta {
  margin-top: 22px;
}
.btn-submit {
  background: var(--ink);
  color: #fff;
  padding: 14px 24px;
  border: 0;
  border-radius: 4px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  width: 100%;
  transition: background 0.18s ease;
  font-family: inherit;
  letter-spacing: 0.01em;
}
.btn-submit:hover:not(:disabled) { background: var(--edit-pink); }
.btn-submit:disabled { opacity: 0.6; cursor: wait; }

.privacy-note {
  font-size: 11.5px;
  color: var(--grey-3);
  margin: 12px 0 0 0;
  text-align: center;
  line-height: 1.4;
}

.form-error {
  background: #ffe5e5;
  color: #c62828;
  padding: 11px 14px;
  border-radius: 4px;
  font-size: 13.5px;
  margin-top: 14px;
  border-left: 3px solid #c62828;
}
.form-success {
  text-align: center;
  padding: 36px 16px;
}
.form-success .check {
  display: inline-flex;
  align-items: center; justify-content: center;
  width: 56px; height: 56px;
  background: var(--edit-yellow);
  border-radius: 50%;
  font-size: 28px;
  margin-bottom: 16px;
}
.form-success h3 {
  font-size: 22px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 10px 0;
  letter-spacing: -0.01em;
}
.form-success p {
  color: var(--grey-4);
  font-size: 15px;
  margin: 0;
  max-width: 40ch;
  margin-left: auto; margin-right: auto;
}

@media (max-width: 880px) {
  .lead-grid { grid-template-columns: 1fr; gap: 40px; }
  .lead-form .field-row { grid-template-columns: 1fr; }
  .lead-form .checkbox-group { grid-template-columns: 1fr; }
}

/* ── FOOTER ──────────────────────────────────────────────────────── */
.site-footer {
  padding: 40px 0;
  background: #fff;
  border-top: 1px solid var(--grey-2);
  font-size: 13px; color: var(--grey-3);
}
.site-footer .wrap {
  display: flex; justify-content: space-between; align-items: center;
  flex-wrap: wrap; gap: 16px;
}
.site-footer a { color: var(--grey-3); }
.site-footer a:hover { color: var(--ink); }

/* ── PREVIEW BANNER ──────────────────────────────────────────────── */
.preview-banner {
  background: var(--edit-yellow); color: var(--ink);
  text-align: center;
  padding: 10px 16px;
  font-size: 13px; font-weight: 700;
  letter-spacing: 0.04em;
}
.preview-banner code {
  background: rgba(0,0,0,0.08); padding: 1px 6px; border-radius: 3px;
  font-family: 'SF Mono', Menlo, Monaco, monospace; font-size: 12px;
}
</style>
</head>
<body>

<?php if ( self::STATUS === 'live' ) : ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TSP85L" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<?php endif; ?>

<?php if ( self::STATUS === 'preview' ) : ?>
<div class="preview-banner">
  PREVIEW · empresas.weareedit.io · visível só para admins · flip <code>EDIT_Empresas_Page::STATUS</code> para <code>'live'</code> para abrir ao público
</div>
<?php endif; ?>

<!-- ─── HEADER ─────────────────────────────────────────────────── -->
<header class="site-header">
  <div class="wrap">
    <a href="/" class="brand" aria-label="EDIT. for business">
      <img class="brand-lockup" src="<?php echo esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/img/logo-edit-master-black.png' ); ?>" alt="EDIT.">
      <span class="brand-tag">for business</span>
    </a>
    <a href="#contacto" class="cta-header">Pedir Proposta</a>
  </div>
</header>

<!-- ─── HERO ───────────────────────────────────────────────────── -->
<section class="hero">
  <div class="wrap">
    <p class="eyebrow">Formação para Empresas</p>
    <h1>Forme a sua equipa nas competências <span class="accent">digitais</span> que o futuro exige.</h1>
    <p class="lede">Programas à medida em <strong>IA, Data, UX, Design, Marketing Digital e Programação</strong>. Entregues presencialmente em Lisboa e Porto, nas vossas instalações, ou em formato remoto. Elegíveis para Cheque‑Formação e Fundos de Compensação.</p>
    <div class="ctas">
      <a href="#contacto" class="btn btn-primary">Pedir Proposta</a>
      <a href="#metodologia" class="btn btn-secondary">Conhecer a Metodologia</a>
    </div>
  </div>
</section>

<!-- ─── LOGO WALL ──────────────────────────────────────────────── -->
<?php if ( ! empty( $clients ) ) : ?>
<section class="logo-wall">
  <div class="wrap">
    <p class="label">Empresas que confiam na EDIT.</p>
    <div class="logo-grid">
      <?php foreach ( array_slice( $clients, 0, 12 ) as $client ) : if ( empty( $client['logo'] ) ) continue; ?>
        <div class="logo-cell">
          <img src="<?php echo esc_url( $client['logo'] ); ?>"
               alt="<?php echo esc_attr( $client['name'] ); ?>"
               loading="lazy">
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── STATS ──────────────────────────────────────────────────── -->
<?php if ( ! empty( $stats ) ) : ?>
<section class="stats">
  <div class="wrap">
    <div class="stats-grid">
      <?php foreach ( $stats as $s ) : ?>
        <div class="stat">
          <div class="num"><?php echo esc_html( $s['number'] ); ?></div>
          <div class="lbl"><?php echo esc_html( $s['label'] ); ?></div>
          <?php if ( ! empty( $s['sub'] ) ) : ?>
            <div class="sub"><?php echo esc_html( $s['sub'] ); ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── VALUE PROPS ────────────────────────────────────────────── -->
<?php if ( ! empty( $value_props ) ) : ?>
<section class="value-props" id="metodologia">
  <div class="wrap">
    <p class="section-eyebrow">Porquê EDIT.</p>
    <h2 class="section-title">A escola digital portuguesa que fala a língua das empresas.</h2>
    <div class="vp-grid">
      <?php foreach ( $value_props as $vp ) : ?>
        <div class="vp">
          <h3><?php echo esc_html( $vp['title'] ); ?></h3>
          <p><?php echo wp_kses_post( $vp['body'] ); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── AREAS ──────────────────────────────────────────────────── -->
<?php if ( ! empty( $areas ) ) : ?>
<section class="areas" id="areas">
  <div class="wrap">
    <p class="section-eyebrow">Áreas de Formação</p>
    <h2 class="section-title">5 disciplinas digitais. Programas modulares ou completos.</h2>
    <div class="areas-grid">
      <?php foreach ( $areas as $area ) : ?>
        <article class="area-card">
          <div class="icon"><?php echo esc_html( $area['icon'] ?? '' ); ?></div>
          <h3><?php echo esc_html( $area['title'] ); ?></h3>
          <p class="area-lede"><?php echo esc_html( $area['lede'] ); ?></p>
          <?php if ( ! empty( $area['topics'] ) ) : ?>
          <ul class="topics">
            <?php foreach ( $area['topics'] as $topic ) : ?>
              <li><?php echo esc_html( $topic ); ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── PROCESS ────────────────────────────────────────────────── -->
<?php if ( ! empty( $process ) ) : ?>
<section class="process" id="processo">
  <div class="wrap">
    <p class="section-eyebrow">Como Trabalhamos</p>
    <h2 class="section-title">4 passos. Do briefing à medição de impacto.</h2>
    <div class="process-grid">
      <?php foreach ( $process as $step ) : ?>
        <div class="process-step">
          <div class="num-tile"><?php echo esc_html( $step['number'] ); ?></div>
          <?php if ( ! empty( $step['time'] ) ) : ?>
            <div class="time-chip"><?php echo esc_html( $step['time'] ); ?></div>
          <?php endif; ?>
          <h3><?php echo esc_html( $step['title'] ); ?></h3>
          <p><?php echo esc_html( $step['body'] ); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── FINANCING ──────────────────────────────────────────────── -->
<section class="financing" id="financiamento">
  <div class="wrap">
    <p class="section-eyebrow">Financiamento</p>
    <h2 class="section-title">Não é o motivo. É o <span class="accent">desbloqueio</span>.</h2>
    <p class="financing-lede">As empresas portuguesas têm acesso a mecanismos de apoio à formação que poucas conhecem. Os nossos programas são elegíveis para os principais — e ajudamos no processo de candidatura.</p>
    <div class="financing-grid">
      <div class="fin-card">
        <span class="tag">Até 30 Junho 2026</span>
        <h3>Cheque-Formação + Digital</h3>
        <p>Apoio direto a PME para formação dos trabalhadores e capacitação digital. Janela actual termina a 30 de Junho de 2026 — vale a pena verificar elegibilidade agora.</p>
      </div>
      <div class="fin-card">
        <span class="tag">Anual</span>
        <h3>SIFIDE</h3>
        <p>Sistema de Incentivos Fiscais à I&amp;D Empresarial. Permite deduzir parte do investimento em formação tecnológica ao IRC. Aplicável a programas com componente de inovação.</p>
      </div>
      <div class="fin-card">
        <span class="tag">A explorar</span>
        <h3>Outros apoios</h3>
        <p>POCH, PT 2030, fundos sectoriais. A elegibilidade depende do setor, dimensão da empresa e tipo de programa. Falamos consigo para mapear o melhor enquadramento.</p>
      </div>
    </div>
    <div class="financing-trust">
      <div class="badge"><strong>EDIT.</strong> é Entidade Formadora Certificada pela <strong>DGERT nº 18391</strong>. Todas as formações são elegíveis para mecanismos de apoio à formação corporativa.</div>
      <a href="#contacto" class="cta-link">Falar com a equipa →</a>
    </div>
  </div>
</section>

<!-- ─── FAQ ────────────────────────────────────────────────────── -->
<?php if ( ! empty( $faq ) ) : ?>
<section class="faq" id="faq">
  <div class="wrap">
    <p class="section-eyebrow">Perguntas Frequentes</p>
    <h2 class="section-title">Respostas às perguntas que os departamentos de compras fazem.</h2>
    <div class="faq-list">
      <?php foreach ( $faq as $item ) : ?>
        <details class="faq-item">
          <summary><?php echo esc_html( $item['q'] ); ?></summary>
          <div class="answer"><?php echo wp_kses_post( $item['a'] ); ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── LEAD SECTION (form) ────────────────────────────────────── -->
<section class="lead-section" id="contacto">
  <div class="wrap">
    <div class="lead-grid">
      <div class="lead-copy">
        <p class="section-eyebrow">Vamos conversar</p>
        <h2>Vamos formar a sua equipa.</h2>
        <p class="lead-intro">Em 24 horas úteis voltamos com um plano e uma proposta. Sem compromisso.</p>
        <ul class="lead-bullets">
          <li>Diagnóstico inicial em chamada de 30 minutos</li>
          <li>Programa desenhado em torno do vosso setor e ferramentas</li>
          <li>Apoio na elegibilidade para Cheque-Formação, SIFIDE e outros</li>
          <li>DGERT certificada · Tutores profissionais em activo</li>
        </ul>
      </div>
      <div class="lead-form-wrap">
        <h3>Pedir proposta</h3>
        <p class="form-help">7 campos · 2 minutos · resposta em 24h úteis</p>

        <form id="empresas-form" class="lead-form" novalidate>
          <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

          <div class="field">
            <label for="ef-nome">Nome *</label>
            <input type="text" id="ef-nome" name="nome" required autocomplete="name">
          </div>

          <div class="field">
            <label for="ef-email">Email corporativo *</label>
            <input type="email" id="ef-email" name="email" required autocomplete="email">
          </div>

          <div class="field-row">
            <div class="field">
              <label for="ef-empresa">Empresa *</label>
              <input type="text" id="ef-empresa" name="empresa" required autocomplete="organization">
            </div>
            <div class="field">
              <label for="ef-cargo">Cargo *</label>
              <select id="ef-cargo" name="cargo" required>
                <option value="">Selecionar</option>
                <option>CEO / Fundador(a)</option>
                <option>Director(a) RH</option>
                <option>Manager L&amp;D</option>
                <option>Head de Departamento</option>
                <option>Outro</option>
              </select>
            </div>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="ef-size">Equipa a formar *</label>
              <select id="ef-size" name="size" required>
                <option value="">Selecionar</option>
                <option>1–10 pessoas</option>
                <option>11–25 pessoas</option>
                <option>26–50 pessoas</option>
                <option>51–100 pessoas</option>
                <option>100+ pessoas</option>
              </select>
            </div>
            <div class="field">
              <label for="ef-timeline">Quando começar?</label>
              <select id="ef-timeline" name="timeline">
                <option value="">Selecionar</option>
                <option>Imediato</option>
                <option>1–3 meses</option>
                <option>3–6 meses</option>
                <option>A explorar</option>
              </select>
            </div>
          </div>

          <div class="field">
            <label>Áreas de interesse</label>
            <div class="checkbox-group">
              <label><input type="checkbox" name="areas[]" value="Marketing Digital"> Marketing Digital</label>
              <label><input type="checkbox" name="areas[]" value="UX/UI Design"> UX/UI Design</label>
              <label><input type="checkbox" name="areas[]" value="Desenvolvimento Web"> Desenvolvimento Web</label>
              <label><input type="checkbox" name="areas[]" value="Data & Business"> Data &amp; Business</label>
              <label><input type="checkbox" name="areas[]" value="Inteligência Artificial"> Inteligência Artificial</label>
            </div>
          </div>

          <div class="field">
            <label for="ef-mensagem">Mensagem (opcional)</label>
            <textarea id="ef-mensagem" name="mensagem" rows="3" placeholder="O desafio que querem resolver, prazo, contexto útil…"></textarea>
          </div>

          <div class="form-meta">
            <button type="submit" class="btn-submit">Pedir Proposta</button>
            <p class="privacy-note">Os dados são usados apenas para responder ao seu pedido. Sem newsletters automáticas.</p>
          </div>

          <div class="form-error" hidden></div>
        </form>
      </div>
    </div>
  </div>
</section>

<script>
(function() {
  var form = document.getElementById('empresas-form');
  if (!form) return;

  var errorBox  = form.querySelector('.form-error');
  var submitBtn = form.querySelector('.btn-submit');
  var endpoint  = '<?php echo esc_url( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ); ?>';

  function showError(msg) {
    errorBox.textContent = msg;
    errorBox.hidden = false;
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  function hideError() {
    errorBox.hidden = true;
    errorBox.textContent = '';
  }

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    hideError();

    var fd = new FormData(form);
    var data = {};
    fd.forEach(function(value, key) {
      if (key.slice(-2) === '[]') {
        var k = key.slice(0, -2);
        if (!data[k]) data[k] = [];
        data[k].push(value);
      } else {
        data[key] = value;
      }
    });

    submitBtn.disabled = true;
    submitBtn.textContent = 'A enviar…';

    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    })
    .then(function(res) {
      return res.json().then(function(body) {
        return { ok: res.ok, body: body };
      }).catch(function() {
        return { ok: res.ok, body: {} };
      });
    })
    .then(function(result) {
      if (result.ok && result.body.status === 'ok') {
        var wrap = form.parentElement;
        wrap.innerHTML = '<div class="form-success"><div class="check">✓</div><h3>Pedido recebido.</h3><p>' + (result.body.message || 'Voltamos em 24h úteis.') + '</p></div>';
        if (typeof window.gtag === 'function') {
          window.gtag('event', 'lead_submit', { event_category: 'empresas', event_label: 'form' });
        }
        if (typeof window.dataLayer !== 'undefined') {
          window.dataLayer.push({ event: 'empresas_lead_submit' });
        }
      } else {
        showError(result.body.message || 'Erro ao enviar. Tente novamente.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Pedir Proposta';
      }
    })
    .catch(function() {
      showError('Erro de ligação. Tente novamente em alguns segundos.');
      submitBtn.disabled = false;
      submitBtn.textContent = 'Pedir Proposta';
    });
  });
})();
</script>

<!-- ─── FOOTER ─────────────────────────────────────────────────── -->
<footer class="site-footer">
  <div class="wrap">
    <div>EDIT. — Disruptive Digital Education · DGERT nº 18391 · Entidade Formadora Certificada</div>
    <div>
      <a href="https://weareedit.io/">weareedit.io</a> ·
      <a href="mailto:empresas@weareedit.io">empresas@weareedit.io</a>
    </div>
  </div>
</footer>

</body>
</html>
        <?php
    }
}
