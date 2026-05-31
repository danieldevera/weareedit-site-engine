<?php
/**
 * Newsletter Signup — Brevo integration
 *
 * Hero-adjacent newsletter signup strip on the homepage. Single-field email
 * capture posts to a Brevo list via REST API v3.
 *
 * REST endpoint: POST /wp-json/edit/v1/newsletter-signup
 *   - Public (no auth) — protected by honeypot + email format + IP rate limit
 *   - On success, adds contact to Brevo list `Newsletter · Site organic (2026+)`
 *     (list ID configurable via Settings → EDIT. SEO Fix → Brevo Newsletter)
 *   - Returns JSON { status, message }
 *
 * Frontend:
 *   - JS injects a `<section id="edit-newsletter-strip">` on body.home only,
 *     right after the hero. No theme template edits required.
 *   - Swipe-CTA submit button (reuses site-wide locked CTA standard from v1.5.112).
 *   - Pushes 5 dataLayer events + gtag direct calls for GA4/GTM tracking.
 *
 * Config (Settings → EDIT. SEO Fix):
 *   - brevo_api_key            : v3 API key from Brevo
 *   - brevo_newsletter_list_id : Brevo list ID for new signups (default 4)
 *
 * @package WeareEditSiteEngine
 * @since   1.5.148
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Newsletter_Signup {

    /** REST namespace + route */
    const REST_NAMESPACE = 'edit/v1';
    const REST_ROUTE     = '/newsletter-signup';

    /** Default Brevo list ID — overridden by setting */
    const DEFAULT_LIST_ID = 4;

    /** Rate limit — submissions per IP per hour */
    const RATE_LIMIT_PER_HOUR = 5;

    public static function init(): void {
        add_action( 'rest_api_init',      [ __CLASS__, 'register_rest_route' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    // ---------------------------------------------------------------- REST

    public static function register_rest_route(): void {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_signup' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function handle_signup( WP_REST_Request $request ) {
        $params = $request->get_params();

        // Honeypot — bots fill `website`; humans don't see it.
        if ( ! empty( $params['website'] ) ) {
            // Pretend success so bots don't retry. Don't log either.
            return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
        }

        // Email required.
        $email = trim( (string) ( $params['email'] ?? '' ) );
        if ( $email === '' ) {
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Email obrigatório.' ], 400 );
        }
        if ( ! is_email( $email ) ) {
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Email inválido.' ], 400 );
        }
        $email = sanitize_email( $email );

        // Rate limit per IP (Cloudflare-aware). Logged-in admins are exempt
        // so we can test the form without burning the quota on our own IP.
        $ip = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] )
            ? wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] )
            : ( $_SERVER['REMOTE_ADDR'] ?? '' );
        $is_admin_user = is_user_logged_in() && current_user_can( 'manage_options' );
        if ( ! $is_admin_user ) {
            $rl_key = 'edit_newsletter_rl_' . md5( $ip );
            $count  = (int) get_transient( $rl_key );
            if ( $count >= self::RATE_LIMIT_PER_HOUR ) {
                return new WP_REST_Response( [
                    'status'  => 'error',
                    'message' => 'Demasiadas tentativas. Tente de novo dentro de uma hora.',
                ], 429 );
            }
            set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );
        }

        // Optional first name (form is currently email-only, but support it).
        $first_name = sanitize_text_field( $params['first_name'] ?? '' );

        // Call Brevo.
        $result = self::call_brevo_create_contact( $email, $first_name, [
            'ip'         => filter_var( $ip, FILTER_VALIDATE_IP ) ?: '',
            'placement'  => sanitize_text_field( $params['placement'] ?? 'hero' ),
            'source_url' => esc_url_raw( $params['source_url'] ?? '' ),
        ] );

        // CF7 Debug log for single-pane visibility.
        if ( class_exists( 'EDIT_CF7_Debug' ) ) {
            EDIT_CF7_Debug::write( [
                'event'      => 'newsletter_signup',
                'form_title' => 'Newsletter Signup (Brevo)',
                'email'      => $email,
                'first_name' => $first_name,
                'placement'  => $params['placement'] ?? 'hero',
                'brevo'      => $result['ok'] ? 'ok' : ( 'error: ' . $result['message'] ),
            ] );
        }

        if ( $result['ok'] ) {
            return new WP_REST_Response( [
                'status'  => 'ok',
                'message' => 'Subscrito, até breve.',
            ], 200 );
        }

        // Brevo returned an error. Most common: duplicate contact (already in list).
        // Treat duplicate as soft-success so the UI doesn't shame returning users.
        if ( ! empty( $result['duplicate'] ) ) {
            return new WP_REST_Response( [
                'status'  => 'ok',
                'message' => 'Já estás subscrito. Obrigado!',
            ], 200 );
        }

        return new WP_REST_Response( [
            'status'  => 'error',
            'message' => 'Erro temporário. Tenta novamente em alguns minutos.',
        ], 502 );
    }

    // ----------------------------------------------------------- Brevo API

    /**
     * POST /v3/contacts — create or update contact in the newsletter list.
     *
     * Returns:
     *   - [ 'ok' => true ]                                  on 200/201/204
     *   - [ 'ok' => false, 'duplicate' => true ]            on Brevo "duplicate_parameter"
     *   - [ 'ok' => false, 'message' => '...' ]             on any other failure
     */
    private static function call_brevo_create_contact( string $email, string $first_name, array $meta ): array {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        $api_key  = (string) ( $settings['brevo_api_key'] ?? '' );
        $list_id  = (int) ( $settings['brevo_newsletter_list_id'] ?? self::DEFAULT_LIST_ID );

        if ( $api_key === '' ) {
            return [ 'ok' => false, 'message' => 'Brevo API key not configured.' ];
        }
        if ( $list_id <= 0 ) {
            return [ 'ok' => false, 'message' => 'Brevo list ID not configured.' ];
        }

        $attributes = array_filter( [
            'FIRSTNAME'      => $first_name,
            'SIGNUP_IP'      => $meta['ip']         ?? '',
            'SIGNUP_PLACEMENT' => $meta['placement']  ?? '',
            'SIGNUP_SOURCE'  => $meta['source_url'] ?? '',
        ], static function ( $v ) { return $v !== ''; } );

        $body = [
            'email'         => $email,
            'listIds'       => [ $list_id ],
            'updateEnabled' => true,
        ];
        if ( ! empty( $attributes ) ) {
            $body['attributes'] = $attributes;
        }

        $response = wp_remote_post( 'https://api.brevo.com/v3/contacts', [
            'method'  => 'POST',
            'timeout' => 15,
            'headers' => [
                'api-key'      => $api_key,
                'Content-Type' => 'application/json',
                'accept'       => 'application/json',
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'ok' => false, 'message' => $response->get_error_message() ];
        }

        $code   = (int) wp_remote_retrieve_response_code( $response );
        $b_body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 ) {
            return [ 'ok' => true ];
        }

        // Brevo duplicate response shape: {"code":"duplicate_parameter","message":"..."}
        if ( is_array( $b_body ) && isset( $b_body['code'] ) && $b_body['code'] === 'duplicate_parameter' ) {
            // Update the existing contact's list membership (idempotent re-add).
            self::call_brevo_update_existing( $email, $list_id, $attributes, $api_key );
            return [ 'ok' => false, 'duplicate' => true ];
        }

        $msg = is_array( $b_body ) && isset( $b_body['message'] )
            ? $b_body['message']
            : 'Brevo HTTP ' . $code;
        return [ 'ok' => false, 'message' => $msg ];
    }

    /** Add an existing contact to the list (used on duplicate response). */
    private static function call_brevo_update_existing( string $email, int $list_id, array $attributes, string $api_key ): void {
        wp_remote_request( 'https://api.brevo.com/v3/contacts/' . rawurlencode( $email ), [
            'method'  => 'PUT',
            'timeout' => 10,
            'headers' => [
                'api-key'      => $api_key,
                'Content-Type' => 'application/json',
                'accept'       => 'application/json',
            ],
            'body' => wp_json_encode( array_filter( [
                'listIds'    => [ $list_id ],
                'attributes' => $attributes ?: null,
            ] ) ),
        ] );
    }

    // ----------------------------------------------------------- Frontend

    /**
     * Map of product typology → strip background colour. Mirrors the
     * accent colour each product uses elsewhere on its own page so the
     * strip feels native to the brand surface it lives on.
     */
    const TYPOLOGY_COLORS = [
        'homepage'        => [ 'bg' => '#ffdd06', 'bg2' => '#ffe847' ], // yellow
        'workshop'        => [ 'bg' => '#60c5b3', 'bg2' => '#7ad6c5' ], // teal
        'bootcamp'        => [ 'bg' => '#f92869', 'bg2' => '#ff4f86' ], // pink
        'curso_remote'    => [ 'bg' => '#0090eb', 'bg2' => '#3aa9f0' ], // blue (Cursos Remote)
        'curso_presencial'=> [ 'bg' => '#ffdd06', 'bg2' => '#ffe847' ], // yellow (Cursos Presenciais)
        'default'         => [ 'bg' => '#ffdd06', 'bg2' => '#ffe847' ],
    ];

    /** Slug-based typology detection on the `formacao` CPT. */
    private static function detect_formacao_typology(): ?string {
        if ( ! is_singular( 'formacao' ) ) return null;
        $slug = (string) get_post_field( 'post_name', get_queried_object_id() );
        if ( stripos( $slug, 'workshop' ) !== false ) return 'workshop';
        if ( stripos( $slug, 'bootcamp' ) !== false ) return 'bootcamp';
        if ( stripos( $slug, 'curso' ) !== false ) {
            // Cursos Presenciais live in Lisboa / Porto; Remote cursos
            // carry "online" or "remote" in the slug. Default to remote
            // when ambiguous since most cursos are remote-first today.
            if ( stripos( $slug, 'porto' ) !== false || stripos( $slug, 'lisboa' ) !== false ) {
                return 'curso_presencial';
            }
            return 'curso_remote';
        }
        return null;
    }

    /** Whether to render the strip on the current page at all. */
    private static function should_render(): bool {
        return is_front_page() || self::detect_formacao_typology() !== null;
    }

    /** Resolve the current page's typology key for color mapping. */
    private static function current_typology(): string {
        if ( is_front_page() ) return 'homepage';
        return self::detect_formacao_typology() ?? 'default';
    }

    public static function enqueue_assets(): void {
        // Render on homepage + product pages (workshop / bootcamp / curso).
        if ( ! self::should_render() ) return;

        wp_enqueue_style(
            'edit-newsletter-signup',
            WEAREDIT_SITE_ENGINE_URL . 'assets/newsletter-signup.css',
            [],
            WEAREDIT_SITE_ENGINE_VERSION
        );
        wp_enqueue_script(
            'edit-newsletter-signup',
            WEAREDIT_SITE_ENGINE_URL . 'assets/newsletter-signup.js',
            [],
            WEAREDIT_SITE_ENGINE_VERSION,
            true
        );
        $typology = self::current_typology();
        $colors   = self::TYPOLOGY_COLORS[ $typology ] ?? self::TYPOLOGY_COLORS['default'];

        wp_localize_script( 'edit-newsletter-signup', 'editNewsletter', [
            'restUrl'  => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'typology' => $typology,
            'bg'       => $colors['bg'],
            'bg2'      => $colors['bg2'],
            'copy'    => [
                'eyebrow'   => 'Newsletter EDIT.',
                'headline'  => 'Recebe a próxima edição.',
                'pitch'     => "Notas semanais, entrevistas inéditas com tutores, cursos em pré-lançamento.\nConteúdos de especialidade com a minha Curadoria.",
                'disclaimer'=> "Junta-te a +11.000 profissionais digitais portugueses.\nSem spam. Cancela quando quiseres.",
                'submit'    => 'Subscrever',
                'placeholder' => 'o.teu.email@dominio.pt',
                'success'   => 'Subscrito, até breve.',
                'duplicate' => 'Já estás na lista — obrigado!',
                'invalid'   => 'Email inválido.',
                'error'     => 'Algo correu mal. Tenta novamente em instantes.',
                'aria_close'=> 'Fechar',
                'founder_name'       => 'Daniel Devera',
                'founder_role'       => 'Founder',
                'founder_photo'      => 'https://weareedit.io/wp-content/uploads/2026/05/daniel-devera-sign-off.png',
                'founder_signature'  => 'https://weareedit.io/wp-content/uploads/2026/05/DANIEL-DEVERA-ASSINATURA.png',
            ],
        ] );
    }
}
