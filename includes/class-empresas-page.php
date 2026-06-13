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
    // Slug "borderless" = Daniel's customized Discovery 30 min meeting type URL.
    const BOOKING_URL           = 'https://meet.brevo.com/daniel-devera/borderless';

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

        $nome_safe    = esc_html( $data['nome'] );
        $empresa_safe = esc_html( $empresa_clean );
        $booking_url  = esc_url( self::BOOKING_URL );

        $body = <<<HTML
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pedido recebido — EDIT. para Empresas</title>
<style>
@media only screen and (max-width:600px){
  .container{width:100% !important;padding:0 !important;}
  .px-md{padding-left:24px !important;padding-right:24px !important;}
  .cta-btn{display:block !important;width:auto !important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#f4f4f2;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#0a0a0a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f2;">
  <tr>
    <td align="center" style="padding:32px 16px;">
      <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#ffffff;">

        <!-- Header / brand line -->
        <tr>
          <td class="px-md" style="padding:32px 40px 8px 40px;">
            <p style="margin:0;font-size:13px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#0a0a0a;">EDIT. <span style="color:#f92869;">·</span> for Business</p>
          </td>
        </tr>

        <!-- Greeting -->
        <tr>
          <td class="px-md" style="padding:24px 40px 0 40px;">
            <p style="margin:0;font-size:18px;line-height:1.5;color:#0a0a0a;">Olá {$nome_safe},</p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td class="px-md" style="padding:16px 40px 0 40px;">
            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:#0a0a0a;">Sou o <strong>Daniel Devera</strong>, fundador da EDIT. Recebi o vosso pedido para <strong>{$empresa_safe}</strong> — obrigado pelo interesse.</p>
            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:#0a0a0a;">Eu próprio respondo a todos os pedidos vindos pela <strong>empresas.weareedit.io</strong>. Em 24 horas úteis contacto-vos para agendar uma conversa de 30 minutos onde percebemos o vosso contexto, equipa e objetivos. Depois desenhamos juntos um programa à medida do vosso setor.</p>
            <p style="margin:0;font-size:16px;line-height:1.6;color:#0a0a0a;">Se quiser adiantar, pode reservar já o horário que lhe convém:</p>
          </td>
        </tr>

        <!-- CTA button -->
        <tr>
          <td class="px-md" align="left" style="padding:24px 40px 8px 40px;">
            <a href="{$booking_url}" class="cta-btn" style="display:inline-block;background:#0a0a0a;color:#ffffff;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:15px;font-weight:600;letter-spacing:0.01em;text-decoration:none;padding:14px 28px;border-radius:32px;mso-padding-alt:0;box-shadow:inset 0 -2px 0 rgba(0,0,0,0.15);">Reservar 30 min →</a>
          </td>
        </tr>

        <!-- Subtle helper -->
        <tr>
          <td class="px-md" style="padding:0 40px 24px 40px;">
            <p style="margin:0;font-size:13px;line-height:1.5;color:#666666;">Para qualquer coisa urgente, responda diretamente a este email.</p>
          </td>
        </tr>

        <!-- Divider -->
        <tr>
          <td class="px-md" style="padding:8px 40px 0 40px;">
            <div style="height:1px;background:#e5e5e2;line-height:1px;font-size:0;">&nbsp;</div>
          </td>
        </tr>

        <!-- Founder signature card (locked spec) -->
        <tr>
          <td class="px-md" style="padding:24px 40px 32px 40px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td valign="top" width="84" style="padding-right:18px;">
                  <a href="https://weareedit.io/equipa/daniel-devera/" style="display:inline-block;text-decoration:none;border:0;"><img src="https://weareedit.io/wp-content/uploads/2026/05/daniel-devera-sign-off.png" alt="Daniel Devera" width="84" height="84" style="display:block;width:84px;height:84px;border-radius:50%;border:3px solid #f92869;box-sizing:border-box;"></a>
                </td>
                <td valign="top" style="padding-top:0;">
                  <a href="https://weareedit.io/equipa/daniel-devera/" style="display:inline-block;text-decoration:none;border:0;"><img src="https://weareedit.io/wp-content/uploads/2026/06/daniel-devera-assinatura-cropped.png" alt="Daniel Devera" width="180" height="auto" style="display:block;width:180px;height:auto;border:0;"></a>
                  <p style="margin:8px 0 0 4px;font-size:11px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#0a0a0a;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://weareedit.io/equipa/daniel-devera/" style="color:#0a0a0a;text-decoration:none;">Founder · EDIT.</a></p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Footer DGERT line -->
        <tr>
          <td class="px-md" style="padding:24px 40px;background:#0a0a0a;color:#888888;font-size:11px;line-height:1.6;text-align:center;letter-spacing:0.04em;">
            EDIT. — Disruptive Digital Education<br>
            Entidade Formadora Certificada DGERT nº 18391<br>
            <a href="https://weareedit.io" style="color:#ffdd06;text-decoration:none;">weareedit.io</a>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

        $headers = [
            'From: Daniel Devera (EDIT.) <' . self::ADMIN_EMAIL . '>',
            'Reply-To: ' . self::ADMIN_EMAIL,
            'Content-Type: text/html; charset=UTF-8',
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

        // v1.5.330: re-enable theme integration. Theme chrome wraps; empresas body
        // content renders inside with v1.5.329 visual fidelity via scoped CSS.
        add_filter( 'body_class', [ __CLASS__, 'add_body_class' ] );
        add_action( 'wp_head',   [ __CLASS__, 'emit_head_meta' ], 1 );
        add_action( 'wp_head',   [ __CLASS__, 'emit_inline_css' ], PHP_INT_MAX );
        add_action( 'wp_footer', [ __CLASS__, 'emit_logo_swap_js' ], PHP_INT_MAX );

        get_header();
        self::emit_body();
        get_footer();
        exit;
    }

    /**
     * JS-based logo swap. CSS `content: url()` only works when the selector
     * matches the theme's specific markup; this is more robust — it grabs the
     * first <img> inside the page header (excluding the yellow CTA button area)
     * and swaps src to the empresas lockup.
     */
    public static function emit_logo_swap_js(): void {
        // DOT-LESS PNG + CSS yellow dot overlay. The original FINAL.png
        // had a baked-in yellow dot. v1.5.358-360 tried covering the
        // inverted-blue version of it with a CSS overlay, but ANY size
        // mismatch between PNG dot and overlay produced a visible blob.
        // Solution: surgically remove the dot from the PNG (magick DstOut
        // with a circular mask), then the CSS overlay is the ONLY dot.
        // Single source of truth, no overlap artifacts possible.
        $logo_url = esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/img/logo-empresas-master-lockup-nodot.png' );
        echo <<<HTML
<script>
(function(){
  function swap(){
    var hdr = document.querySelector('header[role="banner"], #masthead, header.site-header, .site-header, header.header, #header, body > header');
    if (!hdr) return;
    var img = hdr.querySelector('img');
    if (!img) return;
    // Replace the theme's <img> with a wrapper: ONE <img class="lockup-img">
    // for the PNG itself, plus a yellow dot overlay span. CSS handles all
    // state transitions via filter on the img + the dot stays yellow.
    var lockup = document.createElement('span');
    lockup.className = 'empresas-lockup';
    lockup.innerHTML =
      '<img class="lockup-img" src="{$logo_url}" alt="EDIT. for business" loading="eager">' +
      '<span class="lockup-dot" aria-hidden="true"></span>';
    img.parentNode.replaceChild(lockup, img);
    // Walk header buttons just to TAG the hamburger with .empresas-menu-btn
    // (theme class names are unpredictable). All colour/filter work is now
    // done via CSS scoped to header:not([class*="menuOpen"]) — see emit_inline_css —
    // so the icons revert to natural white when the dark menu overlay opens.
    var allButtons = hdr.querySelectorAll('button, a[role="button"], [class*="toggle"], [aria-label*="menu" i], [aria-label*="navega" i], [aria-label*="search" i], [aria-label*="open" i]');
    for (var k = 0; k < allButtons.length; k++) {
      var btn = allButtons[k];
      var cls = (btn.className && (btn.className.baseVal || btn.className) || '').toString().toLowerCase();
      var aria = (btn.getAttribute('aria-label') || '').toLowerCase();
      if (cls.indexOf('connosco') > -1 || cls.indexOf('fala') > -1 || cls.indexOf('cta') > -1 || cls.indexOf('brand') > -1) continue;
      if (btn.querySelector && btn.querySelector('img[src*="logo-empresas"]')) continue;
      var isMenu = aria.indexOf('menu') > -1 || aria.indexOf('navega') > -1 ||
                   cls.indexOf('menu') > -1 || cls.indexOf('hamburger') > -1 ||
                   cls.indexOf('burger') > -1 || cls.indexOf('toggle') > -1;
      var isSearch = aria.indexOf('search') > -1 || cls.indexOf('search') > -1;
      if (isMenu && !isSearch) {
        btn.classList.add('empresas-menu-btn');
      }
    }
    // Logo state tracking removed in v1.5.354 — CSS handles colour entirely
    // via currentColor + header[class*="menuOpen"] cascade. No src swap, no
    // preload, no observers needed for the logo. Hamburger tagging above is
    // the only JS work this function still does.
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', swap);
  } else {
    swap();
  }
})();
</script>
HTML;
    }

    /**
     * Add 'empresas-page' to body classes so the page-specific CSS can be
     * scoped under that selector + win specificity vs theme defaults.
     */
    public static function add_body_class( array $classes ): array {
        $classes[] = 'empresas-page';
        return $classes;
    }

    /**
     * Captured emit_html() output, generated once per request and reused.
     * The big emit_html() method outputs a full HTML doc; the helpers below
     * slice out the head/CSS/body portions we need to hand off to the theme.
     */
    private static function captured_emit_html(): string {
        static $cache = null;
        if ( $cache !== null ) return $cache;
        ob_start();
        self::emit_html();
        $cache = ob_get_clean();
        return $cache;
    }

    /**
     * Emit empresas-specific meta tags (title/description/OG/canonical/JSON-LD)
     * inside the theme's <head>. Strips out the doctype/html/head wrappers and
     * keeps just the meta children.
     */
    public static function emit_head_meta(): void {
        $full = self::captured_emit_html();
        // Pull what's inside <head>...</head>, drop the <style> block since
        // emit_inline_css() emits that separately at a later wp_head priority.
        if ( ! preg_match( '/<head[^>]*>(.*?)<\/head>/s', $full, $m ) ) return;
        $head = $m[1];
        $head = preg_replace( '/<style\b.*?<\/style>/s', '', $head );
        // Avoid duplicating tags the theme already sets — charset, viewport, generator.
        $head = preg_replace( '/<meta\s+charset[^>]*>/i', '', $head );
        $head = preg_replace( '/<meta\s+name=["\']viewport["\'][^>]*>/i', '', $head );
        echo $head;
    }

    /**
     * Emit the empresas inline CSS block scoped under .empresas-page body class
     * so it beats theme styles via specificity without needing !important.
     */
    public static function emit_inline_css(): void {
        $full = self::captured_emit_html();
        if ( ! preg_match( '/<style\b[^>]*>(.*?)<\/style>/s', $full, $m ) ) return;
        $css = $m[1];

        // Theme integration overrides: scoped under body.empresas-page so they
        // win specificity vs theme defaults without leaking to other pages.
        $logo_url = esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/img/logo-empresas-lockup-white.svg' );
        $overrides = <<<CSS

/* ── EMPRESAS THEME OVERRIDES (v1.5.327+) ─────────────────────────── */
body.empresas-page,
body.empresas-page h1,
body.empresas-page h2,
body.empresas-page h3,
body.empresas-page h4,
body.empresas-page h5,
body.empresas-page p,
body.empresas-page a,
body.empresas-page button,
body.empresas-page input,
body.empresas-page select,
body.empresas-page textarea {
  font-family: 'SctoGroteskA', 'Inter', 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
}

/* Theme CSS-modules menu chrome (e.g. .primaryNav__heading___2SZOh,
   .secondaryNavItem__link___3JbgC) ships with hashed classnames that escape
   the h1-h5/p/a override above. The "SECÇÕES" label rendered in serif
   because the theme's font-family declaration on those modules wasn't being
   beaten by our overrides. Cast a wider net via attribute partial-matches
   covering every menu / navigation / footer chrome module. */
body.empresas-page [class*="primaryNav"],
body.empresas-page [class*="primaryNavItem"],
body.empresas-page [class*="secondaryNav"],
body.empresas-page [class*="secondaryNavItem"],
body.empresas-page [class*="navigation__"],
body.empresas-page [class*="menu__"],
body.empresas-page [class*="menu___"],
body.empresas-page [class*="content__heading"],
body.empresas-page [class*="content__inner"],
body.empresas-page [class*="panel__"],
body.empresas-page [class*="footer__"],
body.empresas-page [class*="headerDesktop"],
body.empresas-page [class*="headerMobile"] {
  font-family: 'SctoGroteskA', 'Inter', 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
}

/* Top bar — WHITE background (approved). EDIT. for Business lockup in BLACK on white. */
body.empresas-page header,
body.empresas-page .site-header,
body.empresas-page #masthead,
body.empresas-page #header,
body.empresas-page header[role="banner"] {
  background: #ffffff !important;
  border-bottom: 1px solid #ececec !important;
}
body.empresas-page header a:not(.cta-yellow):not([class*="connosco"]),
body.empresas-page .site-header a:not(.cta-yellow):not([class*="connosco"]) {
  color: #0a0a0a !important;
}
/* Closed-state icon overrides: blacken header icons so they show on the
   WHITE top bar. Scoped to header WITHOUT the menuOpen___[hash] class —
   when the menu opens, the bar becomes the dark overlay and we MUST let
   the icons revert to their natural white state (theme designed them that
   way), otherwise they vanish black-on-black (v1.5.346 bug). */
body.empresas-page header:not([class*="menuOpen"]) svg,
body.empresas-page .headerDesktop:not([class*="menuOpen"]) svg,
body.empresas-page .headerMobile:not([class*="menuOpen"]) svg,
/* Theme's search uses <img src=".../lupa.svg"> (raster-like behaviour), not
   inline <svg>, so the svg selector above doesn't catch it. The native asset
   is white-on-transparent for use on dark headers; on our white bar it
   vanishes without a brightness(0) flip. Match any header img that isn't
   the EDIT lockup. */
body.empresas-page header:not([class*="menuOpen"]) img:not([src*="logo-empresas"]):not([src*="logo-edit"]),
body.empresas-page .headerDesktop:not([class*="menuOpen"]) img:not([src*="logo-empresas"]):not([src*="logo-edit"]),
body.empresas-page .headerMobile:not([class*="menuOpen"]) img:not([src*="logo-empresas"]):not([src*="logo-edit"]) {
  filter: brightness(0) !important;
}
body.empresas-page header:not([class*="menuOpen"]) [class*="menu-toggle"],
body.empresas-page header:not([class*="menuOpen"]) [class*="nav-toggle"],
body.empresas-page header:not([class*="menuOpen"]) [class*="hamburger"],
body.empresas-page header:not([class*="menuOpen"]) [aria-label*="menu" i],
body.empresas-page header:not([class*="menuOpen"]) [aria-label*="search" i],
body.empresas-page header:not([class*="menuOpen"]) .empresas-menu-btn,
body.empresas-page header:not([class*="menuOpen"]) .empresas-menu-btn span,
body.empresas-page header:not([class*="menuOpen"]) .menuButton__inner,
body.empresas-page header:not([class*="menuOpen"]) .searchButton__inner___1NAyP {
  color: #0a0a0a !important;
}
body.empresas-page header:not([class*="menuOpen"]) [class*="menu-toggle"] span,
body.empresas-page header:not([class*="menuOpen"]) [class*="nav-toggle"] span,
body.empresas-page header:not([class*="menuOpen"]) [class*="hamburger"] span,
body.empresas-page header:not([class*="menuOpen"]) [class*="hamburger"] div {
  background-color: #0a0a0a !important;
}

/* Open-state: explicitly revert filters so icons can show white on the dark overlay. */
body.empresas-page header[class*="menuOpen"] svg,
body.empresas-page .headerDesktop[class*="menuOpen"] svg,
body.empresas-page .headerMobile[class*="menuOpen"] svg,
body.empresas-page header[class*="menuOpen"] img:not([src*="logo-empresas"]),
body.empresas-page header[class*="menuOpen"] .menuButton__inner,
body.empresas-page header[class*="menuOpen"] .searchButton__inner___1NAyP {
  filter: none !important;
  color: #ffffff !important;
}
body.empresas-page header[class*="menuOpen"] .empresas-menu-btn {
  border-color: #ffffff !important;
}
body.empresas-page header[class*="menuOpen"] .empresas-menu-btn span,
body.empresas-page header[class*="menuOpen"] .empresas-menu-btn .menuButton__inner {
  color: #ffffff !important;
  filter: none !important;
}

/* "In-company" link is redundant — visitor is already on the empresas page.
   Hide via href match (any of the legacy slugs). */
body.empresas-page header a[href*="in-company"],
body.empresas-page header a[href*="formacao-corporativa"],
body.empresas-page header a[href*="formacao-in-company"],
body.empresas-page header a[href*="formacao-digital-para-empresas"] {
  display: none !important;
}

/* Hamburger: let the theme handle the entire 3-line + close-X animation
   (matches main weareedit.io HP exactly — image #29 closed, image #30 open).
   The theme has full hamburger architecture built in:
     - .menuButton::before          = top bar (y=15, 30x2)
     - .menuButton__inner::before   = middle bar (y=24, 30x2)
     - .menuButton::after           = bottom bar (y=33, 30x2)
     - .close___[hash] class triggers X animation:
         top    → translateY(9px) rotate(135deg)
         bottom → translateY(-9px) rotate(45deg)
         middle → opacity: 0
   All bars use currentColor, so we just need to drive the color per state.
   ANY override beyond color breaks the theme animation — earlier ships
   v1.5.346-362 were all consequences of fighting the theme. Stop fighting. */
body.empresas-page header[class*="menuOpen"] .empresas-menu-btn,
body.empresas-page .headerDesktop[class*="menuOpen"] .empresas-menu-btn,
body.empresas-page .headerMobile[class*="menuOpen"] .empresas-menu-btn {
  color: #ffffff !important;
}
body.empresas-page header:not([class*="menuOpen"]) .empresas-menu-btn,
body.empresas-page .headerDesktop:not([class*="menuOpen"]) .empresas-menu-btn,
body.empresas-page .headerMobile:not([class*="menuOpen"]) .empresas-menu-btn {
  color: #0a0a0a !important;
}

/* Search container: theme correctly hides it on menuOpen (matches main HP
   behavior — image #33 shows search gone, only X remains). Previously
   v1.5.357 force-showed it thinking the theme was broken, but the main
   HP intentionally hides search behind the menu overlay. Removed. */

/* Single-PNG-without-dot + CSS dot overlay (v1.5.361). The dot was
   surgically removed from the PNG so the CSS overlay is the ONLY dot.
   No PNG-vs-overlay alignment issues possible.

   Original dot bbox: 12x12 at (384,52) in 397x115 → centred at
   (389.5, 57.5) → 98.11% left, 50% top. At 60px render that's 6.26px
   diameter. Use 6.5px overlay (matches solid dot size + 0.24px margin).
   Overlay stays yellow in BOTH states because it's outside the
   filter:invert scope on the img. */
body.empresas-page header .empresas-lockup,
body.empresas-page .headerDesktop .empresas-lockup,
body.empresas-page .headerMobile .empresas-lockup {
  position: relative !important;
  display: inline-block !important;
  line-height: 0 !important;
  font-size: 0 !important;
}
body.empresas-page header .empresas-lockup .lockup-img,
body.empresas-page .headerDesktop .empresas-lockup .lockup-img,
body.empresas-page .headerMobile .empresas-lockup .lockup-img {
  height: 60px !important;
  width: auto !important;
  display: block !important;
  transition: filter 180ms ease !important;
  filter: none !important;
}
body.empresas-page .empresas-lockup .lockup-dot {
  position: absolute !important;
  left: 98.11% !important;
  top: 50% !important;
  width: 6.5px !important;
  height: 6.5px !important;
  border-radius: 50% !important;
  background: #FFDD06 !important;
  transform: translate(-50%, -50%) !important;
  pointer-events: none !important;
  z-index: 2 !important;
}
body.empresas-page header[class*="menuOpen"] .empresas-lockup .lockup-img,
body.empresas-page .headerDesktop[class*="menuOpen"] .empresas-lockup .lockup-img,
body.empresas-page .headerMobile[class*="menuOpen"] .empresas-lockup .lockup-img {
  filter: invert(1) !important;
}
/* Wider gap between hamburger and search. */
body.empresas-page header [aria-label*="search" i],
body.empresas-page header [class*="search"]:not(input):not(form) {
  margin-left: 12px !important;
}

/* Strip any residual whitespace the theme adds above our hero. */
body.empresas-page main,
body.empresas-page #primary,
body.empresas-page #content,
body.empresas-page .site-content,
body.empresas-page .site-main {
  padding-top: 0 !important;
  margin-top: 0 !important;
  max-width: none !important;
  width: 100% !important;
}

/* Hero alignment + typography — match approved (image #34):
   - All text LEFT-aligned (theme defaults to center; force it back)
   - H1 max-width widened 16ch → 22ch (approved wraps at ~22 chars/line,
     producing 3 lines: "Forme a sua equipa nas" / "competências [DIGITAIS]"
     / "que o futuro exige." — vs current 4-line center-wrap)
   - Lede + CTAs also left-aligned so they sit under the H1's left edge
   - Container stays centred via the existing .wrap rules; what changes
     is the internal alignment of the content within that container. */
body.empresas-page .hero {
  text-align: left !important;
}
body.empresas-page .hero .eyebrow,
body.empresas-page .hero h1,
body.empresas-page .hero .lede,
body.empresas-page .hero .ctas,
body.empresas-page .hero .wrap > * {
  text-align: left !important;
}
body.empresas-page .hero .ctas {
  justify-content: flex-start !important;
}
body.empresas-page .hero h1 {
  font-size: clamp(40px, 6.4vw, 88px) !important;
  line-height: 0.98 !important;
  letter-spacing: -0.035em !important;
  font-weight: 700 !important;
  max-width: 22ch !important;
  color: #fff !important;
}
body.empresas-page .hero .eyebrow {
  color: var(--edit-yellow, #ffdd06) !important;
  font-weight: 500 !important;
  letter-spacing: 0.30em !important;
  font-size: 13px !important;
}

/* DGERT pill — copied from homepage hero ([[hp-hero-locked-v1554]] spec).
   Sits above the eyebrow, links to dgert.gov.pt in a new tab. */
body.empresas-page .dgert-hero-pill {
  display: inline-flex !important;
  align-items: center !important;
  gap: 12px !important;
  text-decoration: none !important;
  color: #fff !important;
  margin: 0 0 24px 0 !important;
  transition: opacity 0.2s ease !important;
  align-self: flex-start !important;
}
body.empresas-page .dgert-hero-pill:hover { opacity: 0.78 !important; }
body.empresas-page .dgert-hero-pill img {
  height: 40px !important;
  width: auto !important;
  display: block !important;
  border: none !important;
}
body.empresas-page .dgert-hero-pill-text {
  font-size: 14px !important;
  font-weight: 500 !important;
  letter-spacing: 0.01em !important;
  color: #fff !important;
}
body.empresas-page .dgert-hero-pill-arrow {
  font-size: 12px !important;
  margin-left: 4px !important;
  opacity: 0.6 !important;
  color: #fff !important;
}
@media (max-width: 768px) {
  body.empresas-page .dgert-hero-pill { margin-bottom: 16px !important; gap: 8px !important; }
  body.empresas-page .dgert-hero-pill img { height: 28px !important; }
  body.empresas-page .dgert-hero-pill-text { font-size: 12px !important; }
  body.empresas-page .dgert-hero-pill-arrow { font-size: 10px !important; }
}
body.empresas-page .hero .lede {
  color: rgba(255,255,255,0.85) !important;
  max-width: 60ch !important;
}

/* Process section title: force black (matches image #48 user direction).
   Some theme h2 inheritance was rendering it lighter than intended. */
body.empresas-page .process .section-title,
body.empresas-page .process h2 {
  color: var(--ink, #0a0a0a) !important;
}

/* Hero CTAs — yellow swipe-cta (site-wide standard locked at v1.5.112).
   Both buttons use the same pink → teal → black sweep with yellow label
   flip on hover, matching the homepage hero button behaviour. */
body.empresas-page .hero .ctas {
  display: flex !important;
  flex-wrap: wrap !important;
  gap: 16px !important;
  align-items: center !important;
}
body.empresas-page .hero .ctas .btn.swipe-cta {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  background: #ffdd06 !important;
  color: #0a0a0a !important;
  border: 0 !important;
  border-radius: 4px !important;
  padding: 16px 26px !important;
  font-weight: 600 !important;
  font-size: 15px !important;
  text-decoration: none !important;
}
body.empresas-page .hero .ctas .btn.swipe-cta .swipe-label {
  color: #0a0a0a !important;
}
body.empresas-page .hero .ctas .btn.swipe-cta:hover .swipe-label {
  color: #ffdd06 !important;
}
/* "Financiamento" notation — wrapped around the secondary CTA so the
   button itself can keep its overflow: hidden (required for swipe-cta
   layers to stay clipped inside). The notation sits in the wrapper,
   positioned absolute above the button. */
body.empresas-page .hero .ctas .cta-wrap {
  position: relative !important;
  display: inline-block !important;
}
body.empresas-page .hero .ctas .cta-notation {
  position: absolute !important;
  bottom: calc(100% + 6px) !important;
  left: 0 !important;
  font-size: 11px !important;
  font-weight: 500 !important;
  letter-spacing: 0.20em !important;
  text-transform: uppercase !important;
  color: var(--edit-yellow, #ffdd06) !important;
  pointer-events: none !important;
  white-space: nowrap !important;
}
CSS;

        echo "<style id=\"empresas-page-css\">\n" . $css . "\n" . $overrides . "\n</style>";
    }

    /**
     * Emit the empresas body content (sections only). Strips:
     *   - everything outside <body>…</body>
     *   - our custom <header class="site-header"> (theme provides it)
     *   - our custom <footer class="site-footer"> (theme provides it)
     *   - GTM noscript (theme provides it via wp_body_open if needed)
     */
    public static function emit_body(): void {
        $full = self::captured_emit_html();
        if ( ! preg_match( '/<body\b[^>]*>(.*)<\/body>/s', $full, $m ) ) return;
        $body = $m[1];
        // Strip the custom header/footer markup — theme provides chrome.
        $body = preg_replace( '/<header\s+class="site-header"[^>]*>.*?<\/header>/s', '', $body );
        $body = preg_replace( '/<footer\s+class="site-footer"[^>]*>.*?<\/footer>/s', '', $body );
        // Strip GTM noscript (theme should already have its own GTM block).
        $body = preg_replace( '/<noscript><iframe\s+src="https:\/\/www\.googletagmanager\.com\/ns\.html[^"]+"[^>]*><\/iframe><\/noscript>/s', '', $body );
        // Strip the preview banner (Daniel can see admin bar for env signal).
        $body = preg_replace( '/<div\s+class="preview-banner"[^>]*>.*?<\/div>/s', '', $body );
        echo $body;
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

.site-nav {
  display: flex; gap: 28px; align-items: center;
}
.site-nav a {
  font-size: 14px;
  font-weight: 500;
  color: var(--grey-4);
  text-decoration: none;
  padding: 6px 0;
  position: relative;
  transition: color 0.18s ease;
}
.site-nav a:hover { color: var(--ink); }
.site-nav a.active {
  color: var(--ink);
  font-weight: 700;
}
.site-nav a.active::after {
  content: '';
  position: absolute;
  left: 0; right: 0; bottom: -2px;
  height: 2px;
  background: var(--edit-yellow);
}
@media (max-width: 900px) {
  .site-nav { display: none; }
}

/* ── HERO ────────────────────────────────────────────────────────── */
.hero {
  position: relative;
  min-height: 86vh;
  display: flex;
  align-items: flex-end;
  color: #fff;
  padding: 140px 0 80px 0;
  background:
    linear-gradient(180deg, rgba(0,0,0,0.25) 0%, rgba(0,0,0,0.55) 50%, rgba(0,0,0,0.92) 100%),
    url('<?php echo esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/img/empresas-hero.jpg' ); ?>') center 30% / cover no-repeat #0a0a0a;
}
.hero .eyebrow {
  font-size: 12px; font-weight: 700;
  letter-spacing: 0.22em; text-transform: uppercase;
  color: var(--edit-yellow);
  margin: 0 0 24px 0;
}
.hero h1 {
  font-size: clamp(40px, 6.4vw, 88px);
  line-height: 0.98;
  letter-spacing: -0.035em;
  font-weight: 700;
  color: #fff;
  margin: 0 0 28px 0;
  max-width: 16ch;
}
.hero h1 .accent {
  background: var(--edit-yellow);
  color: var(--ink);
  padding: 0 0.08em;
  border-radius: 4px;
  white-space: nowrap;
}
.hero .lede {
  font-size: clamp(17px, 1.5vw, 21px);
  line-height: 1.45;
  color: rgba(255,255,255,0.85);
  max-width: 60ch;
  margin: 0 0 40px 0;
}
.hero .lede strong { color: #fff; }
.hero .ctas {
  display: flex; gap: 12px; flex-wrap: wrap;
}
.hero .btn-secondary {
  color: #fff;
  border-color: rgba(255,255,255,0.4);
}
.hero .btn-secondary:hover {
  border-color: #fff;
  background: rgba(255,255,255,0.08);
  color: #fff;
}
@media (max-width: 768px) {
  .hero { min-height: 78vh; padding: 110px 0 56px 0; }
}
.btn,
.cta-header,
button {
  cursor: pointer;
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
  gap: 56px 48px;
  align-items: center;
}
.logo-cell {
  display: flex; align-items: center; justify-content: center;
  height: 120px;
}
.logo-cell img {
  max-width: 100%; max-height: 100%;
  width: auto;
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

/* ── FOUNDER QUOTE (replaced STATS in v1.5.372, refined v1.5.374) ─── */
.founder-quote {
  padding: 96px 0;
  background: var(--grey-2);  /* darker grey than the old --grey-1 */
}
.fq-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 64px;
  max-width: 1080px;  /* widened from 920px */
  margin: 0 auto;
}
.fq-quote {
  margin: 0;
  font-size: clamp(24px, 2.6vw, 38px);
  line-height: 1.30;
  letter-spacing: -0.015em;
  font-weight: 500;
  color: var(--ink);
  max-width: 44ch;  /* widened from 30ch — quote textbox wider per user */
}
/* Founder signature card — matches [[locked-founder-signature-card]]:
   84px photo + 3px PINK ring (not yellow) + cropped signature 180w +
   FOUNDER label + email link below. */
.fq-author {
  display: flex;
  align-items: center;
  gap: 22px;
}
.fq-photo {
  width: 84px;
  height: 84px;
  border-radius: 50%;
  object-fit: cover;
  box-shadow: 0 0 0 3px var(--edit-pink);  /* pink ring per locked spec */
  display: block;
  flex-shrink: 0;
}
.fq-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: flex-start;
}
.fq-signature {
  width: 180px;
  height: auto;
  display: block;
}
.fq-role {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--ink);
  margin-top: 4px;
}
.fq-email {
  font-size: 13px;
  color: var(--grey-4);
  text-decoration: underline;
  text-underline-offset: 3px;
}
.fq-email:hover { color: var(--ink); }
@media (max-width: 720px) {
  .founder-quote { padding: 64px 0; }
  .fq-grid { gap: 40px; }
  .fq-author { gap: 16px; }
  .fq-photo { width: 72px; height: 72px; }
  .fq-signature { width: 150px; }
}

/* ── VALUE PROPS ─────────────────────────────────────────────────── */
.value-props {
  padding: 90px 0;
}
.section-eyebrow {
  font-size: 13px; font-weight: 500;
  letter-spacing: 0.30em; text-transform: uppercase;
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

/* ── AREAS — tabbed deep-dive (v1.5.382) ───────────────────────────
   5 tabs across the top, panel below with brand-coloured visual side
   + content side (sub-disciplinas + ferramentas + CTA). All panels
   rendered into DOM for SEO/GEO; CSS hides non-active panels. */
.areas-deep-dive {
  padding: 90px 0;
  background: var(--grey-1);
}
.areas-deep-dive .section-title {
  max-width: 28ch;
  color: var(--ink);
}
.areas-deep-dive .section-eyebrow {
  color: var(--ink);
}

/* Tab nav */
.add-tabs {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0;
  margin-top: 48px;
  border-bottom: 1px solid var(--grey-2);
}
.add-tab {
  background: transparent;
  border: 0;
  padding: 20px 14px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  border-bottom: 3px solid transparent;
  margin-bottom: -1px;
  font-family: inherit;
  transition: background 0.15s ease;
  color: inherit;
}
.add-tab:hover { background: rgba(0,0,0,0.03); }
.add-tab.active { border-bottom-color: var(--accent); }
.add-tab-letter {
  font-size: 36px;
  font-weight: 800;
  color: var(--grey-3);
  letter-spacing: -0.04em;
  line-height: 1;
  transition: color 0.2s ease;
}
.add-tab.active .add-tab-letter { color: var(--accent); }
.add-tab-label {
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.04em;
  color: var(--grey-4);
  text-align: center;
  line-height: 1.3;
  transition: color 0.2s ease;
}
.add-tab.active .add-tab-label { color: var(--ink); }

/* Panels — server-rendered, CSS hides non-active */
.add-panels { margin-top: 48px; }
.add-panel { display: none; }
.add-panel.active { display: block; }
.add-panel-inner {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 56px;
  align-items: stretch;
}
.add-visual {
  background-color: var(--accent); /* fallback while image loads */
  background-image: url('<?php echo esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/img/areas-gradient.png' ); ?>');
  background-size: cover;
  background-position: center;
  border-radius: 8px;
  min-height: 320px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 32px;
  text-align: center;
}
.add-letter {
  font-size: 200px;
  font-weight: 900;
  color: var(--ink);
  line-height: 0.85;
  letter-spacing: -0.06em;
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
}
.add-visual-label {
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--ink);
  opacity: 0.72;
}
.add-content { padding-top: 4px; }
.add-content h3 {
  font-size: clamp(26px, 2.6vw, 34px);
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--ink);
  margin: 0 0 12px 0;
  line-height: 1.15;
}
.add-lede {
  font-size: 16.5px;
  color: var(--grey-4);
  line-height: 1.55;
  margin: 0 0 28px 0;
  max-width: 60ch;
}
.add-section-title {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--grey-3);
  margin: 28px 0 14px 0;
}
.add-subs {
  list-style: none;
  padding: 0;
  margin: 0 0 8px 0;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.add-subs li {
  font-size: 14.5px;
  color: var(--ink);
  padding: 10px 14px;
  background: #fff;
  border-radius: 999px;
  border: 1px solid var(--grey-2);
  font-weight: 500;
  line-height: 1.2;
}
.add-subs li:hover {
  border-color: var(--accent);
  color: var(--ink);
}
.add-tools {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 0 0 28px 0;
}
.add-tool {
  font-size: 13px;
  font-weight: 600;
  color: var(--grey-4);
  padding: 5px 11px;
  background: rgba(0,0,0,0.05);
  border-radius: 4px;
  letter-spacing: 0.01em;
}
.add-cta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
  color: var(--ink);
  text-decoration: none;
  border-bottom: 2px solid var(--accent);
  padding-bottom: 2px;
  transition: opacity 0.2s ease;
}
.add-cta:hover { opacity: 0.7; }

@media (max-width: 880px) {
  .add-tabs { grid-template-columns: repeat(2, 1fr); border-bottom: 0; }
  .add-tab { border: 1px solid var(--grey-2); margin: 0; border-bottom: 3px solid transparent; }
  .add-tab-letter { font-size: 28px; }
  .add-tab-label { font-size: 11px; }
  .add-panel-inner { grid-template-columns: 1fr; gap: 24px; }
  .add-visual { min-height: 200px; }
  .add-letter { font-size: 140px; }
  .add-subs { grid-template-columns: 1fr; }
}

/* ── PROCESS — exact infographic replica (v1.5.378) ──────────────────
   SVG holds the wave bands + white spheres + numbers (scales with viewport).
   HTML grid overlay holds text blocks alternating above/below the wave. */
.process {
  padding: 90px 0 100px 0;
}
.process-wave-v2 {
  position: relative;
  width: 100%;
  max-width: 1240px;
  margin: 40px auto 0;
  padding: 0 28px;
  box-sizing: border-box;
}
.pw-svg {
  display: block;
  width: 100%;
  height: auto;
}
.pw-grid {
  position: absolute;
  inset: 0;
  pointer-events: none;
}
.pw-step {
  position: absolute;
  width: 280px;
  margin-left: -140px;  /* offset by half width to centre on `left` */
  text-align: center;
  pointer-events: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
}
/* X positions match SVG circle cx values (200, 500, 800, 1100 in viewBox 1400). */
.pw-step:nth-child(1) { left: 14.2857%; }
.pw-step:nth-child(2) { left: 35.7143%; }
.pw-step:nth-child(3) { left: 57.1429%; }
.pw-step:nth-child(4) { left: 78.5714%; }
/* Above: anchor block's bottom edge above the circle's top (30% from top). */
.pw-step--above {
  bottom: 72%;
  flex-direction: column-reverse;  /* content on top, dots near circle */
}
/* Below: anchor block's top edge below the circle's bottom (70% from top). */
.pw-step--below {
  top: 72%;
}
.pw-dots {
  display: block;
  width: 2px;
  height: 28px;
  background-image: linear-gradient(to bottom, var(--accent) 50%, transparent 50%);
  background-size: 2px 6px;
  background-repeat: repeat-y;
  opacity: 0.65;
  margin: 12px 0;
}
.pw-content {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.pw-time {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: #0a0a0a;
  background: var(--accent);
  padding: 5px 11px;
  margin: 0 0 14px 0;
}
.pw-title {
  font-size: 19px;
  font-weight: 700;
  letter-spacing: -0.01em;
  margin: 0 0 8px 0;
  line-height: 1.25;
  color: var(--ink);
}
.pw-body {
  font-size: 14.5px;
  color: var(--grey-4);
  line-height: 1.5;
  margin: 0;
  max-width: 28ch;
}
@media (max-width: 880px) {
  .process-wave-v2 { margin-top: 24px; }
  .pw-svg {
    /* Mobile: hide the SVG wave entirely, show stacked text only */
    display: none;
  }
  .pw-grid {
    position: static;
    display: flex;
    flex-direction: column;
    gap: 36px;
    padding: 0 24px;
  }
  .pw-step,
  .pw-step--above,
  .pw-step--below {
    /* Reset the desktop absolute positioning so steps flow as a stack
       instead of overlapping on top of each other. */
    position: static;
    width: auto;
    margin-left: 0;
    top: auto;
    bottom: auto;
    left: auto;
    grid-row: auto;
    grid-column: auto;
    align-self: stretch;
    text-align: left;
    align-items: flex-start;
    max-width: none;
    padding: 0;
    flex-direction: column;
  }
  .pw-step--above { padding-bottom: 0; }
  .pw-step--below { padding-top: 0; }
  .pw-content { align-items: flex-start; }
  .pw-body { max-width: none; }
  .pw-dots { display: none; }
  .pw-title { font-size: 22px; }
  /* Mobile: show a coloured number tile in place of the SVG sphere */
  .pw-step::before {
    content: attr(data-num);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #fff;
    border: 4px solid var(--accent);
    font-weight: 800;
    color: var(--ink);
    margin-bottom: 14px;
  }
}

/* ── FINANCING BANNER (v1.5.381) — light brand-yellow break between
   logo wall + process. Replaces v1.5.380's dark band per user light-
   tone direction. Pink accent on "desbloqueio" instead of yellow-on-
   yellow which would have disappeared. */
.financing-banner {
  padding: 72px 0;
  background: var(--edit-yellow);
  color: var(--ink);
  text-align: center;
}
.financing-banner .wrap {
  max-width: 880px;
}
.fb-eyebrow {
  font-size: 13px;
  font-weight: 500;
  letter-spacing: 0.30em;
  text-transform: uppercase;
  color: var(--ink);
  margin: 0 0 18px 0;
}
.fb-title {
  font-size: clamp(28px, 3.4vw, 44px);
  line-height: 1.1;
  letter-spacing: -0.02em;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 18px 0;
}
.fb-accent {
  background: var(--ink);
  color: var(--edit-yellow);
  padding: 0 0.12em;
  border-radius: 4px;
  white-space: nowrap;
}
.fb-body {
  font-size: 17px;
  line-height: 1.55;
  color: rgba(10,10,10,0.78);
  max-width: 60ch;
  margin: 0 auto 24px auto;
}
.fb-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
  color: var(--ink);
  text-decoration: none;
  border-bottom: 1px solid var(--ink);
  padding-bottom: 2px;
  transition: opacity 0.2s ease;
}
.fb-link:hover {
  opacity: 0.7;
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
/* Success state: collapse the 2-column grid so the calendar gets full width. */
.lead-grid.is-success {
  display: block;
  max-width: 980px;
  margin: 0 auto;
  background: #fff;
  padding: 48px 56px 56px 56px;
  border-radius: 0;
}
.lead-grid.is-success .form-success {
  text-align: center;
  padding: 8px 0 32px 0;
  border-bottom: 1px solid #e5e5e2;
  margin-bottom: 0;
}
.lead-grid.is-success .form-success p {
  max-width: 52ch;
}
.lead-grid.is-success .booking-embed {
  margin-top: 32px;
  padding-top: 0;
  border-top: 0;
}
.booking-embed {
  margin-top: 32px;
  padding-top: 32px;
  border-top: 1px solid #e5e5e2;
}
.booking-embed .booking-eyebrow {
  margin: 0 0 8px 0;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--edit-pink, #f92869);
}
.booking-embed h4 {
  margin: 0 0 20px 0;
  font-size: 22px;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -0.01em;
}
.booking-embed iframe {
  width: 100%;
  min-height: 820px;
  border: 0;
  display: block;
  background: #fff;
}
@media (max-width: 768px) {
  .lead-grid.is-success { padding: 32px 20px 40px 20px; }
  .booking-embed iframe { min-height: 900px; }
}

@media (max-width: 880px) {
  .lead-grid { grid-template-columns: 1fr; gap: 40px; }
  .lead-form .field-row { grid-template-columns: 1fr; }
  .lead-form .checkbox-group { grid-template-columns: 1fr; }
}

/* ── FOUNDERS NL ──────────────────────────────────────────────────── */
.founders-nl {
  background: var(--ink);
  color: #fff;
  padding: 96px 0;
  position: relative;
}
.founders-nl .nl-grid {
  display: grid;
  grid-template-columns: 1.05fr 1fr;
  gap: 80px;
  align-items: center;
}
.founders-nl .eyebrow {
  font-size: 13px; font-weight: 500;
  letter-spacing: 0.30em; text-transform: uppercase;
  color: var(--edit-yellow);
  margin: 0 0 18px 0;
}
.founders-nl h2 {
  font-size: clamp(30px, 3.6vw, 44px);
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -0.02em;
  margin: 0 0 18px 0;
  color: #fff;
  max-width: 18ch;
}
.founders-nl p.nl-lede {
  font-size: 16px;
  line-height: 1.55;
  color: rgba(255,255,255,0.7);
  max-width: 50ch;
}
.founders-nl .nl-form {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 6px;
  padding: 32px 28px;
}
.founders-nl .nl-form label {
  display: block;
  font-size: 12px; font-weight: 700;
  letter-spacing: 0.18em; text-transform: uppercase;
  color: rgba(255,255,255,0.65);
  margin-bottom: 10px;
}
.founders-nl .nl-input-row {
  display: flex; gap: 8px;
}
.founders-nl .nl-input-row input {
  flex: 1; min-width: 0;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.15);
  color: #fff;
  font-size: 15px;
  padding: 12px 14px;
  border-radius: 4px;
  font-family: inherit;
}
.founders-nl .nl-input-row input::placeholder { color: rgba(255,255,255,0.4); }
.founders-nl .nl-input-row input:focus { outline: 0; border-color: var(--edit-yellow); background: rgba(255,255,255,0.1); }
.founders-nl .nl-input-row button {
  background: var(--edit-yellow); color: var(--ink);
  border: 0; border-radius: 4px;
  font-weight: 700; font-size: 14px;
  padding: 12px 22px;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.18s ease;
}
.founders-nl .nl-input-row button:hover { background: #fff; }
.founders-nl .nl-input-row button:disabled { opacity: 0.55; cursor: wait; }
.founders-nl .nl-hint {
  font-size: 12px; color: rgba(255,255,255,0.5);
  margin-top: 12px;
}
.founders-nl .nl-status {
  margin-top: 14px;
  font-size: 13px;
  color: var(--edit-yellow);
  min-height: 18px;
}
.founders-nl .nl-status.error { color: #ff8b9c; }
@media (max-width: 900px) {
  .founders-nl { padding: 64px 0; }
  .founders-nl .nl-grid { grid-template-columns: 1fr; gap: 40px; }
}

/* ── FOOTER ──────────────────────────────────────────────────────── */
.site-footer {
  background: #fafafa;
  border-top: 1px solid var(--grey-2);
  padding: 64px 0 28px 0;
  font-size: 14px; color: var(--grey-3);
}
.site-footer .footer-grid {
  display: grid;
  grid-template-columns: 1.3fr 1fr 1fr 1.2fr;
  gap: 56px;
  margin-bottom: 56px;
}
.site-footer .footer-col h4 {
  font-size: 12px; font-weight: 700;
  letter-spacing: 0.2em; text-transform: uppercase;
  color: var(--ink);
  margin: 0 0 14px 0;
}
.site-footer .footer-col a,
.site-footer .footer-col p {
  display: block;
  font-size: 14px;
  color: var(--grey-3);
  line-height: 1.7;
  text-decoration: none;
}
.site-footer .footer-col a:hover { color: var(--ink); }
.site-footer .footer-logo {
  font-size: 24px; font-weight: 900; letter-spacing: -0.02em;
  margin-bottom: 14px;
}
.site-footer .footer-logo .dot { color: var(--edit-yellow); }
.site-footer .footer-about p {
  max-width: 36ch;
  margin-top: 6px;
}
.site-footer .footer-bottom {
  border-top: 1px solid var(--grey-2);
  padding-top: 22px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 12px;
  color: var(--grey-3);
}
.site-footer .footer-bottom a { color: var(--grey-3); }
.site-footer .footer-bottom a:hover { color: var(--ink); }
@media (max-width: 900px) {
  .site-footer .footer-grid { grid-template-columns: 1fr 1fr; gap: 36px; }
}
@media (max-width: 560px) {
  .site-footer .footer-grid { grid-template-columns: 1fr; }
}

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
    <nav class="site-nav" aria-label="Navegação principal">
      <a href="https://weareedit.io/formacao/">Cursos</a>
      <a href="https://weareedit.io/workshops/">Workshops</a>
      <a href="https://weareedit.io/bootcamps/">Bootcamps</a>
      <a href="/" class="active">Empresas</a>
    </nav>
    <a href="#contacto" class="cta-header">Pedir Proposta</a>
  </div>
</header>

<!-- ─── HERO ───────────────────────────────────────────────────── -->
<section class="hero">
  <div class="wrap">
    <a class="dgert-hero-pill" href="https://www.dgert.gov.pt/entidades-formadoras-certificadas" target="_blank" rel="noopener noreferrer" aria-label="DGERT — Entidade Formadora Certificada"><img width="1024" height="483" src="https://weareedit.io/wp-content/plugins/weareedit-site-engine/assets/dgert-entidade-formadora-branco.png" alt="DGERT" loading="eager" data-skip-wrap="1"><span class="dgert-hero-pill-text">Entidade Formadora Certificada</span><span class="dgert-hero-pill-arrow" aria-hidden="true">&#x2197;</span></a>
    <p class="eyebrow">Formação para Empresas</p>
    <h1>Forme a sua equipa nas competências <span class="accent">digitais</span> que o futuro exige.</h1>
    <p class="lede">Programas à medida em <strong>IA, Data, UX, Design, Marketing Digital e Programação</strong>. Entregues presencialmente em Lisboa e Porto, nas vossas instalações, ou em formato remoto. Elegíveis para Cheque‑Formação e Fundos de Compensação.</p>
    <div class="ctas">
      <span class="cta-wrap">
        <span class="cta-notation">Financiamento</span>
        <a href="#financiamento" class="btn btn-yellow swipe-cta empresas-cta-secondary">
          <span class="swipe-layer swipe-pink"></span>
          <span class="swipe-layer swipe-teal"></span>
          <span class="swipe-layer swipe-black"></span>
          <span class="swipe-label">Fundos de Compensação</span>
        </a>
      </span>
      <a href="#contacto" class="btn btn-yellow swipe-cta empresas-cta-primary">
        <span class="swipe-layer swipe-pink"></span>
        <span class="swipe-layer swipe-teal"></span>
        <span class="swipe-layer swipe-black"></span>
        <span class="swipe-label">Discovery Call</span>
      </a>
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

<!-- ─── FINANCING BANNER (visual break between social proof and process,
     v1.5.380) — teaser only; full Financing section with cards stays
     further down the page. -->
<section class="financing-banner">
  <div class="wrap">
    <p class="fb-eyebrow">Financiamento</p>
    <h2 class="fb-title">Não é o motivo. É o <span class="fb-accent">desbloqueio</span>.</h2>
    <p class="fb-body">As empresas portuguesas têm acesso a mecanismos de apoio à formação que poucas conhecem. Os nossos programas são elegíveis para os principais — e ajudamos no processo de candidatura.</p>
    <a class="fb-link" href="#financiamento">Conhecer os apoios <span aria-hidden="true">→</span></a>
  </div>
</section>

<!-- ─── PROCESS — exact infographic replica (v1.5.378) ───────────────
   Reference: image #51. Thick colored sleeves alternately wrap circles
   (odd loop under, even loop over). White spheres with drop shadow +
   gradient. Text alternates above/below the wave. Full-bleed: sits
   outside .wrap. -->
<?php if ( ! empty( $process ) ) :
    $step_colors = [ '#f92869', '#ec8172', '#ffdd06', '#60c5b3' ];
?>
<section class="process" id="processo">
  <div class="wrap">
    <p class="section-eyebrow">Como Trabalhamos</p>
    <h2 class="section-title">4 passos. Do briefing à medição de impacto.</h2>
  </div>
  <div class="process-wave-v2">
    <svg class="pw-svg" viewBox="0 0 1400 400" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
      <defs>
        <radialGradient id="pw-sphere" cx="35%" cy="32%" r="72%">
          <stop offset="0%" stop-color="#ffffff"/>
          <stop offset="65%" stop-color="#f4f4f4"/>
          <stop offset="100%" stop-color="#d4d4d4"/>
        </radialGradient>
        <filter id="pw-shadow" x="-20%" y="-20%" width="140%" height="140%">
          <feGaussianBlur in="SourceAlpha" stdDeviation="7"/>
          <feOffset dy="9"/>
          <feComponentTransfer><feFuncA type="linear" slope="0.28"/></feComponentTransfer>
          <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>
        </filter>
        <!-- Multi-stop horizontal gradient: 4 brand colours with smooth
             5% transition zones centred on each seam (x=395/695/995 in
             a 1400-wide viewBox → 28.2% / 49.6% / 71.1%). Solid colour
             dominates each segment; the seam is a short gradient blend. -->
        <linearGradient id="pw-wave-gradient" x1="0" y1="0" x2="1400" y2="0" gradientUnits="userSpaceOnUse">
          <stop offset="0%"   stop-color="<?php echo esc_attr( $step_colors[0] ); ?>"/>
          <stop offset="25%"  stop-color="<?php echo esc_attr( $step_colors[0] ); ?>"/>
          <stop offset="31%"  stop-color="<?php echo esc_attr( $step_colors[1] ); ?>"/>
          <stop offset="47%"  stop-color="<?php echo esc_attr( $step_colors[1] ); ?>"/>
          <stop offset="52%"  stop-color="<?php echo esc_attr( $step_colors[2] ); ?>"/>
          <stop offset="68%"  stop-color="<?php echo esc_attr( $step_colors[2] ); ?>"/>
          <stop offset="74%"  stop-color="<?php echo esc_attr( $step_colors[3] ); ?>"/>
          <stop offset="100%" stop-color="<?php echo esc_attr( $step_colors[3] ); ?>"/>
        </linearGradient>
      </defs>
      <!-- Single unified wave: 4 arcs alternating UNDER/OVER, one continuous
           path. stroke-linecap="round" gives soft caps at the start and end.
           The multi-stop gradient blends the 4 brand colours horizontally,
           so seams between segments are smooth weaves instead of hard
           vertical cuts. -->
      <path d="M 0 200 L 95 200 A 105 105 0 0 0 305 200 L 395 200 A 105 105 0 0 1 605 200 L 695 200 A 105 105 0 0 0 905 200 L 995 200 A 105 105 0 0 1 1205 200 L 1400 200"
            stroke="url(#pw-wave-gradient)" stroke-width="50" stroke-linecap="round" fill="none"/>
      <!-- 4 white spheres on top of the band -->
      <?php
      $cxs = [ 200, 500, 800, 1100 ];
      foreach ( $cxs as $cx ) : ?>
        <circle cx="<?php echo $cx; ?>" cy="200" r="80" fill="url(#pw-sphere)" filter="url(#pw-shadow)"/>
      <?php endforeach; ?>
      <!-- Step numbers in circles -->
      <?php foreach ( $process as $i => $step ) :
        $cx = $cxs[ $i ] ?? 0;
      ?>
        <text x="<?php echo $cx; ?>" y="215" text-anchor="middle" font-family="SctoGroteskA, sans-serif" font-size="44" font-weight="800" fill="#0a0a0a"><?php echo esc_html( $step['number'] ); ?></text>
      <?php endforeach; ?>
    </svg>
    <div class="pw-grid">
      <?php foreach ( $process as $i => $step ) :
          $color = $step_colors[ $i % 4 ];
          $place = ( $i % 2 === 0 ) ? 'above' : 'below';
      ?>
        <div class="pw-step pw-step--<?php echo esc_attr( $place ); ?>" data-num="<?php echo esc_attr( $step['number'] ); ?>" style="--accent: <?php echo esc_attr( $color ); ?>;">
          <span class="pw-dots" aria-hidden="true"></span>
          <div class="pw-content">
            <?php if ( ! empty( $step['time'] ) ) : ?>
              <span class="pw-time"><?php echo esc_html( $step['time'] ); ?></span>
            <?php endif; ?>
            <h3 class="pw-title"><?php echo esc_html( $step['title'] ); ?></h3>
            <p class="pw-body"><?php echo esc_html( $step['body'] ); ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── FOUNDER QUOTE (replaced STATS in v1.5.372) ─────────────────── -->
<section class="founder-quote">
  <div class="wrap">
    <div class="fq-grid">
      <blockquote class="fq-quote">
        “We help individuals &amp; businesses grow in the Digital Ecosystem through our physical &amp; remote learning programs.”
      </blockquote>
      <div class="fq-author">
        <img class="fq-photo" src="https://weareedit.io/wp-content/uploads/2026/05/daniel-devera-sign-off.png" alt="Daniel Devera" width="84" height="84" loading="lazy">
        <div class="fq-meta">
          <img class="fq-signature" src="https://weareedit.io/wp-content/uploads/2026/05/DANIEL-DEVERA-ASSINATURA.png" alt="Daniel Devera" loading="lazy">
          <span class="fq-role">Founder</span>
          <a class="fq-email" href="mailto:daniel.devera@weareedit.io">daniel.devera@weareedit.io</a>
        </div>
      </div>
    </div>
  </div>
</section>

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

<!-- ─── AREAS — tabbed deep-dive (v1.5.382) ──────────────────────────
   All 5 panels rendered server-side (SEO/GEO friendly: LLMs + crawlers
   see every sub-vertical and tool name). CSS hides non-active panels;
   JS toggles `active` class. Schema markup at the section + per-panel
   level signals an EducationalOrganization OfferCatalog with Course
   entries — major signal for GEO. -->
<?php if ( ! empty( $areas ) ) : ?>
<section class="areas-deep-dive" id="areas" itemscope itemtype="https://schema.org/EducationalOrganization">
  <meta itemprop="name" content="EDIT. — Disruptive Digital Education">
  <meta itemprop="url" content="https://weareedit.io">
  <div class="wrap">
    <p class="section-eyebrow">Áreas de Formação</p>
    <h2 class="section-title">5 disciplinas digitais. 30 sub-disciplinas. Programas modulares ou completos.</h2>
    <!-- Tab nav -->
    <div class="add-tabs" role="tablist" aria-label="Áreas de formação">
      <?php foreach ( $areas as $i => $area ) :
          $accent = $area['accent'] ?? '#0a0a0a';
          $is_active = ( $i === 0 ) ? 'active' : '';
      ?>
        <button type="button"
                class="add-tab <?php echo esc_attr( $is_active ); ?>"
                role="tab"
                aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                aria-controls="add-panel-<?php echo $i; ?>"
                id="add-tab-<?php echo $i; ?>"
                data-target="add-panel-<?php echo $i; ?>"
                style="--accent: <?php echo esc_attr( $accent ); ?>;">
          <span class="add-tab-letter" aria-hidden="true"><?php echo esc_html( $area['icon'] ?? '' ); ?></span>
          <span class="add-tab-label"><?php echo esc_html( $area['title'] ); ?></span>
        </button>
      <?php endforeach; ?>
    </div>
    <!-- Panels (all rendered, CSS hides non-active for users; LLMs see all) -->
    <div class="add-panels" itemprop="hasOfferCatalog" itemscope itemtype="https://schema.org/OfferCatalog">
      <meta itemprop="name" content="Disciplinas digitais">
      <?php foreach ( $areas as $i => $area ) :
          $accent  = $area['accent'] ?? '#0a0a0a';
          $is_active = ( $i === 0 ) ? 'active' : '';
          $course_url = 'https://weareedit.io/' . ltrim( $area['slug'] ?? '', '/' ) . '/';
      ?>
        <div class="add-panel <?php echo esc_attr( $is_active ); ?>"
             id="add-panel-<?php echo $i; ?>"
             role="tabpanel"
             aria-labelledby="add-tab-<?php echo $i; ?>"
             style="--accent: <?php echo esc_attr( $accent ); ?>;"
             itemprop="itemListElement"
             itemscope itemtype="https://schema.org/Offer">
          <meta itemprop="position" content="<?php echo $i + 1; ?>">
          <div class="add-panel-inner" itemprop="itemOffered" itemscope itemtype="https://schema.org/Course">
            <meta itemprop="provider" content="EDIT. — Disruptive Digital Education">
            <meta itemprop="url" content="<?php echo esc_url( $course_url ); ?>">
            <!-- Visual side -->
            <div class="add-visual">
              <span class="add-letter" aria-hidden="true"><?php echo esc_html( $area['icon'] ?? '' ); ?></span>
              <span class="add-visual-label"><?php echo esc_html( $area['title'] ); ?></span>
            </div>
            <!-- Content side -->
            <div class="add-content">
              <h3 itemprop="name"><?php echo esc_html( $area['title'] ); ?></h3>
              <p class="add-lede" itemprop="description"><?php echo esc_html( $area['lede'] ); ?></p>
              <?php if ( ! empty( $area['sub_verticals'] ) ) : ?>
                <h4 class="add-section-title">Sub-disciplinas</h4>
                <ul class="add-subs">
                  <?php foreach ( $area['sub_verticals'] as $sub ) : ?>
                    <li itemprop="teaches"><?php echo esc_html( $sub ); ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
              <?php if ( ! empty( $area['tools'] ) ) : ?>
                <h4 class="add-section-title">Ferramentas que dominamos</h4>
                <div class="add-tools">
                  <?php foreach ( $area['tools'] as $tool ) : ?>
                    <span class="add-tool"><?php echo esc_html( $tool ); ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <meta itemprop="url" content="<?php echo esc_url( $course_url ); ?>">
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<script>
(function(){
  document.addEventListener('click', function(e){
    var t = e.target.closest('.add-tab');
    if (!t) return;
    var section = t.closest('.areas-deep-dive');
    if (!section) return;
    var targetId = t.getAttribute('data-target');
    section.querySelectorAll('.add-tab').forEach(function(x){
      var on = x === t;
      x.classList.toggle('active', on);
      x.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    section.querySelectorAll('.add-panel').forEach(function(p){
      p.classList.toggle('active', p.id === targetId);
    });
  });
})();
</script>
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
  var bookingUrl = '<?php echo esc_url( self::BOOKING_URL ); ?>';

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
        // Pre-fill Brevo Reuniões with data the user just gave us — no double entry.
        // Brevo doesn't publish its exact param names, so we send several common variants;
        // unknown params are ignored.
        function enc(s) { return encodeURIComponent(s || ''); }
        var nameParts = String(data.nome || '').trim().split(/\s+/);
        var fname = nameParts[0] || '';
        var lname = nameParts.slice(1).join(' ');
        // email pre-fill confirmed working v1.5.320. Name variants unknown —
        // throw every plausible Brevo / common-CRM param name; Brevo ignores
        // unrecognized ones, picks whatever matches its internal mapping.
        var prefillQs = ''
          + 'email='        + enc(data.email)
          + '&FIRSTNAME='   + enc(fname)
          + '&LASTNAME='    + enc(lname)
          + '&firstname='   + enc(fname)
          + '&lastname='    + enc(lname)
          + '&first_name='  + enc(fname)
          + '&last_name='   + enc(lname)
          + '&nome='        + enc(fname)
          + '&sobrenome='   + enc(lname)
          + '&name='        + enc(data.nome)
          + '&fullname='    + enc(data.nome)
          + '&company='     + enc(data.empresa)
          + '&COMPANY='     + enc(data.empresa);
        var iframeSrc = bookingUrl + (bookingUrl.indexOf('?') === -1 ? '?' : '&') + prefillQs;

        // Replace the WHOLE 2-column grid (not just the form column) so the
        // calendar embed gets the full container width to breathe.
        var grid = form.closest('.lead-grid') || form.parentElement;
        grid.classList.add('is-success');
        grid.innerHTML = ''
          + '<div class="form-success">'
          +   '<div class="check">✓</div>'
          +   '<h3>Pedido recebido.</h3>'
          +   '<p>' + (result.body.message || 'Voltamos em 24h úteis.') + '</p>'
          + '</div>'
          + '<div class="booking-embed">'
          +   '<p class="booking-eyebrow">Adiantar agora</p>'
          +   '<h4>Escolha o seu horário para a chamada de 30 min</h4>'
          +   '<iframe src="' + iframeSrc + '" title="Reservar reunião com Daniel Devera" loading="lazy"></iframe>'
          + '</div>';
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

<!-- ─── FOUNDERS NL REMOVED (v1.5.367) ─────────────────────────────
   Was a dark band with its own "Founder's note" eyebrow + form. The
   yellow sitewide Newsletter EDIT. strip ([[project-newsletter-strip-locked]])
   serves the same purpose (newsletter signup + Daniel's signature)
   and renders automatically on this page. Removing the dark variant
   eliminates the duplicate. -->

<!-- ─── FOOTER ─────────────────────────────────────────────────── -->
<footer class="site-footer">
  <div class="wrap">
    <div class="footer-grid">
      <div class="footer-col footer-about">
        <div class="footer-logo">EDIT<span class="dot">.</span></div>
        <p>Disruptive Digital Education.<br>DGERT nº 18391 · Entidade Formadora Certificada.</p>
      </div>
      <div class="footer-col">
        <h4>Formação</h4>
        <a href="https://weareedit.io/formacao/">Cursos</a>
        <a href="https://weareedit.io/workshops/">Workshops</a>
        <a href="https://weareedit.io/bootcamps/">Bootcamps</a>
        <a href="/">EDIT. for Business</a>
      </div>
      <div class="footer-col">
        <h4>Lisboa</h4>
        <p>Rua Sociedade Farmacêutica 3<br>1150-340 Lisboa<br><a href="tel:+351210182455">+351 210 182 455</a></p>
        <h4 style="margin-top:22px;">Porto</h4>
        <p>Rua Direita do Viso 130<br>4250-194 Porto<br><a href="tel:+351224960345">+351 224 960 345</a></p>
      </div>
      <div class="footer-col">
        <h4>Contacto</h4>
        <a href="mailto:empresas@weareedit.io">empresas@weareedit.io</a>
        <a href="https://api.whatsapp.com/send?phone=351936508449" target="_blank" rel="noopener">WhatsApp +351 936 508 449</a>
        <a href="https://www.linkedin.com/company/edit-disruptive-digital-education/" target="_blank" rel="noopener">LinkedIn</a>
        <a href="https://www.instagram.com/edit.education/" target="_blank" rel="noopener">Instagram</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© 2026 EDIT. — Disruptive Digital Education</span>
      <span>
        <a href="https://weareedit.io/politica-de-privacidade/">Privacidade</a> ·
        <a href="https://weareedit.io/termos-e-condicoes/">Termos</a>
      </span>
    </div>
  </div>
</footer>

</body>
</html>
        <?php
    }
}
