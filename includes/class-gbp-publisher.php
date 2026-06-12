<?php
/**
 * Google Business Profile Publisher
 *
 * WordPress integration for managing EDIT.'s Google Business Profile listings
 * (Lisboa + Porto + São Paulo) directly from WP Admin. Handles OAuth, encrypted
 * token storage, and write operations against the GBP API.
 *
 * Track A (this stage, no allowlist needed):
 *   - OAuth flow + token storage     v1.5.460 (Stage 1)
 *   - List locations + read insights v1.5.461 (Stage 2)
 *   - Products CRUD on formacao CPT  v1.5.462 (Stage 4)
 *
 * Track B (deferred, allowlist required):
 *   - Posts publishing               (Stage 3, after allowlist approval)
 *   - Q&A management                 (Future)
 *   - Reviews reply                  (Future)
 *
 * Credentials live in wp-config.php as constants:
 *   define( 'EDIT_GBP_CLIENT_ID',     '...apps.googleusercontent.com' );
 *   define( 'EDIT_GBP_CLIENT_SECRET', '...' );
 *
 * Token storage: encrypted via openssl_encrypt with AES-256-CBC using
 * AUTH_SALT (already defined by wp-config) as the key. Refresh token
 * persisted in wp_options key 'edit_gbp_refresh_token' so the connection
 * survives PHP restarts and plugin updates.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_GBP_Publisher {

    const OPT_REFRESH_TOKEN     = 'edit_gbp_refresh_token';        // encrypted blob
    const OPT_ACCESS_TOKEN      = 'edit_gbp_access_token';         // transient-style with expiry
    const OPT_CONNECTED_AT      = 'edit_gbp_connected_at';
    const OPT_CONNECTED_EMAIL   = 'edit_gbp_connected_email';
    const OPT_LOCATIONS_CACHE   = 'edit_gbp_locations_cache';      // [{id, name, address, status}, ...]
    const OPT_LOCATION_LISBOA   = 'edit_gbp_location_lisboa';      // location_id for fast targeting
    const OPT_LOCATION_PORTO    = 'edit_gbp_location_porto';
    const OPT_LOCATION_SP       = 'edit_gbp_location_sp';

    const OAUTH_AUTH_URL        = 'https://accounts.google.com/o/oauth2/v2/auth';
    const OAUTH_TOKEN_URL       = 'https://oauth2.googleapis.com/token';
    const SCOPE                 = 'https://www.googleapis.com/auth/business.manage';
    const API_ACCOUNTS_BASE     = 'https://mybusinessaccountmanagement.googleapis.com/v1';
    const API_BUSINESS_INFO     = 'https://mybusinessbusinessinformation.googleapis.com/v1';
    const API_PERFORMANCE       = 'https://businessprofileperformance.googleapis.com/v1';

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_admin_page' ] );
        add_action( 'admin_init',            [ __CLASS__, 'handle_oauth_callback' ] );
    }

    /* ─────────────────────────────────────────────────────────────────────
       Credential resolution
       ────────────────────────────────────────────────────────────────── */

    private static function client_id(): string {
        return defined( 'EDIT_GBP_CLIENT_ID' ) ? (string) EDIT_GBP_CLIENT_ID : '';
    }

    private static function client_secret(): string {
        return defined( 'EDIT_GBP_CLIENT_SECRET' ) ? (string) EDIT_GBP_CLIENT_SECRET : '';
    }

    private static function redirect_uri(): string {
        return admin_url( 'admin.php?page=edit-gbp-publisher&action=oauth-callback' );
    }

    private static function credentials_configured(): bool {
        return self::client_id() !== '' && self::client_secret() !== '';
    }

    /* ─────────────────────────────────────────────────────────────────────
       Token encryption (AES-256-CBC keyed on AUTH_SALT)
       ────────────────────────────────────────────────────────────────── */

    private static function encryption_key(): string {
        $base = defined( 'AUTH_SALT' ) ? AUTH_SALT : ABSPATH;
        return hash( 'sha256', $base, true );
    }

    private static function encrypt( string $plain ): string {
        $iv  = openssl_random_pseudo_bytes( 16 );
        $ct  = openssl_encrypt( $plain, 'aes-256-cbc', self::encryption_key(), OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $ct );
    }

    private static function decrypt( string $blob ): string {
        $raw = base64_decode( $blob, true );
        if ( $raw === false || strlen( $raw ) < 17 ) return '';
        $iv  = substr( $raw, 0, 16 );
        $ct  = substr( $raw, 16 );
        $out = openssl_decrypt( $ct, 'aes-256-cbc', self::encryption_key(), OPENSSL_RAW_DATA, $iv );
        return is_string( $out ) ? $out : '';
    }

    private static function save_refresh_token( string $token ): void {
        update_option( self::OPT_REFRESH_TOKEN, self::encrypt( $token ), false );
    }

    private static function get_refresh_token(): string {
        $blob = get_option( self::OPT_REFRESH_TOKEN, '' );
        return $blob ? self::decrypt( (string) $blob ) : '';
    }

    /* ─────────────────────────────────────────────────────────────────────
       OAuth flow
       ────────────────────────────────────────────────────────────────── */

    public static function get_oauth_url(): string {
        $state = wp_create_nonce( 'edit_gbp_oauth' );
        update_option( 'edit_gbp_oauth_state', $state, false );
        return add_query_arg( [
            'client_id'              => self::client_id(),
            'redirect_uri'           => self::redirect_uri(),
            'response_type'          => 'code',
            'scope'                  => self::SCOPE,
            'access_type'            => 'offline',
            'prompt'                 => 'consent',
            'include_granted_scopes' => 'true',
            'state'                  => $state,
        ], self::OAUTH_AUTH_URL );
    }

    /**
     * OAuth callback handler — fires on every admin_init, no-ops unless we're
     * on our settings page with the oauth-callback action.
     */
    public static function handle_oauth_callback(): void {
        if ( ! is_admin() ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ( $_GET['page'] ?? '' ) !== 'edit-gbp-publisher' ) return;
        if ( ( $_GET['action'] ?? '' ) !== 'oauth-callback' ) return;

        $state_ok = isset( $_GET['state'] )
            && get_option( 'edit_gbp_oauth_state' ) === $_GET['state'];
        if ( ! $state_ok ) {
            wp_die( 'OAuth state mismatch. Try connecting again.' );
        }
        delete_option( 'edit_gbp_oauth_state' );

        if ( isset( $_GET['error'] ) ) {
            wp_safe_redirect( add_query_arg( 'gbp_error', sanitize_text_field( wp_unslash( $_GET['error'] ) ), admin_url( 'admin.php?page=edit-gbp-publisher' ) ) );
            exit;
        }

        $code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
        if ( ! $code ) {
            wp_safe_redirect( add_query_arg( 'gbp_error', 'missing-code', admin_url( 'admin.php?page=edit-gbp-publisher' ) ) );
            exit;
        }

        $token_res = wp_remote_post( self::OAUTH_TOKEN_URL, [
            'timeout' => 15,
            'body'    => [
                'code'          => $code,
                'client_id'     => self::client_id(),
                'client_secret' => self::client_secret(),
                'redirect_uri'  => self::redirect_uri(),
                'grant_type'    => 'authorization_code',
            ],
        ] );

        if ( is_wp_error( $token_res ) ) {
            wp_safe_redirect( add_query_arg( 'gbp_error', 'token-exchange-failed', admin_url( 'admin.php?page=edit-gbp-publisher' ) ) );
            exit;
        }

        $body = json_decode( wp_remote_retrieve_body( $token_res ), true );
        if ( empty( $body['refresh_token'] ) ) {
            wp_safe_redirect( add_query_arg( 'gbp_error', 'no-refresh-token', admin_url( 'admin.php?page=edit-gbp-publisher' ) ) );
            exit;
        }

        self::save_refresh_token( (string) $body['refresh_token'] );
        update_option( self::OPT_CONNECTED_AT, time(), false );

        // Stash the new access token + expiry as a quick warm path.
        if ( ! empty( $body['access_token'] ) && ! empty( $body['expires_in'] ) ) {
            update_option( self::OPT_ACCESS_TOKEN, [
                'token'      => (string) $body['access_token'],
                'expires_at' => time() + (int) $body['expires_in'] - 60, // 60s safety margin
            ], false );
        }

        // Resolve the logged-in Google account email so the admin UI can
        // confirm WHICH account is connected (useful when multiple Google
        // accounts have access to multiple GBP listings).
        $email = self::fetch_connected_email( $body['access_token'] ?? '' );
        if ( $email ) update_option( self::OPT_CONNECTED_EMAIL, $email, false );

        wp_safe_redirect( admin_url( 'admin.php?page=edit-gbp-publisher&connected=1' ) );
        exit;
    }

    private static function fetch_connected_email( string $access_token ): string {
        if ( ! $access_token ) return '';
        $res = wp_remote_get( 'https://openidconnect.googleapis.com/v1/userinfo', [
            'timeout' => 10,
            'headers' => [ 'Authorization' => 'Bearer ' . $access_token ],
        ] );
        if ( is_wp_error( $res ) ) return '';
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        return is_array( $body ) && ! empty( $body['email'] ) ? (string) $body['email'] : '';
    }

    /* ─────────────────────────────────────────────────────────────────────
       Token refresh / API access
       ────────────────────────────────────────────────────────────────── */

    /**
     * Returns a valid access token, refreshing from the saved refresh token
     * if the cached one has expired. Empty string = not connected.
     */
    public static function access_token(): string {
        $cached = get_option( self::OPT_ACCESS_TOKEN );
        if ( is_array( $cached ) && ! empty( $cached['token'] ) && ! empty( $cached['expires_at'] ) && $cached['expires_at'] > time() ) {
            return (string) $cached['token'];
        }

        $refresh = self::get_refresh_token();
        if ( ! $refresh ) return '';

        $res = wp_remote_post( self::OAUTH_TOKEN_URL, [
            'timeout' => 15,
            'body'    => [
                'client_id'     => self::client_id(),
                'client_secret' => self::client_secret(),
                'refresh_token' => $refresh,
                'grant_type'    => 'refresh_token',
            ],
        ] );
        if ( is_wp_error( $res ) ) return '';
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( empty( $body['access_token'] ) ) return '';

        update_option( self::OPT_ACCESS_TOKEN, [
            'token'      => (string) $body['access_token'],
            'expires_at' => time() + (int) ( $body['expires_in'] ?? 3600 ) - 60,
        ], false );

        return (string) $body['access_token'];
    }

    public static function is_connected(): bool {
        return self::get_refresh_token() !== '';
    }

    public static function disconnect(): void {
        delete_option( self::OPT_REFRESH_TOKEN );
        delete_option( self::OPT_ACCESS_TOKEN );
        delete_option( self::OPT_CONNECTED_AT );
        delete_option( self::OPT_CONNECTED_EMAIL );
        delete_option( self::OPT_LOCATIONS_CACHE );
    }

    /* ─────────────────────────────────────────────────────────────────────
       Admin UI
       ────────────────────────────────────────────────────────────────── */

    public static function register_admin_page(): void {
        add_management_page(
            'GBP Publisher',
            'GBP Publisher',
            'manage_options',
            'edit-gbp-publisher',
            [ __CLASS__, 'render_admin_page' ]
        );
    }

    public static function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Handle disconnect action.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'disconnect' && check_admin_referer( 'edit_gbp_disconnect' ) ) {
            self::disconnect();
            wp_safe_redirect( admin_url( 'admin.php?page=edit-gbp-publisher&disconnected=1' ) );
            exit;
        }

        $connected   = self::is_connected();
        $configured  = self::credentials_configured();
        $email       = (string) get_option( self::OPT_CONNECTED_EMAIL, '' );
        $connected_at = (int) get_option( self::OPT_CONNECTED_AT, 0 );

        ?>
        <div class="wrap" style="max-width:880px;">
            <h1 style="margin-bottom:8px;">EDIT. · Google Business Profile Publisher</h1>
            <p style="color:#666;margin:0 0 32px;">Publica produtos e actualizações nos perfis EDIT. Lisboa, Porto e São Paulo directamente do WP Admin.</p>

            <?php if ( isset( $_GET['connected'] ) ): ?>
                <div class="notice notice-success"><p>✅ Conta Google ligada com sucesso<?php echo $email ? ' como <strong>' . esc_html( $email ) . '</strong>' : ''; ?>.</p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['disconnected'] ) ): ?>
                <div class="notice notice-info"><p>Conta Google desligada. Tokens removidos.</p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['gbp_error'] ) ): ?>
                <div class="notice notice-error"><p>Erro OAuth: <code><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['gbp_error'] ) ) ); ?></code></p></div>
            <?php endif; ?>

            <div style="background:#fff;border:1px solid #e0e0e0;padding:24px 28px;margin-bottom:24px;">
                <h2 style="margin:0 0 16px;font-size:18px;">1. Credenciais</h2>
                <?php if ( $configured ): ?>
                    <p style="margin:0;color:#1f6e1f;">✅ Credenciais OAuth configuradas em <code>wp-config.php</code>.</p>
                <?php else: ?>
                    <p style="margin:0 0 12px;color:#b45309;">⚠️ Adicione as constantes em <code>wp-config.php</code>:</p>
                    <pre style="background:#f5f5f5;padding:14px 18px;border-radius:4px;font-size:13px;overflow-x:auto;">define( 'EDIT_GBP_CLIENT_ID',     'XXX.apps.googleusercontent.com' );
define( 'EDIT_GBP_CLIENT_SECRET', 'GOCSPX-XXX' );</pre>
                <?php endif; ?>
            </div>

            <div style="background:#fff;border:1px solid #e0e0e0;padding:24px 28px;margin-bottom:24px;">
                <h2 style="margin:0 0 16px;font-size:18px;">2. Ligação à conta Google</h2>
                <?php if ( ! $configured ): ?>
                    <p style="color:#666;margin:0;">Configure primeiro as credenciais OAuth.</p>
                <?php elseif ( $connected ): ?>
                    <p style="margin:0 0 12px;color:#1f6e1f;">
                        ✅ Ligada<?php echo $email ? ' como <strong>' . esc_html( $email ) . '</strong>' : ''; ?>
                        <?php if ( $connected_at ): ?>
                            · ligada em <?php echo esc_html( wp_date( 'd M Y, H:i', $connected_at ) ); ?>
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=edit-gbp-publisher&action=disconnect' ), 'edit_gbp_disconnect' ) ); ?>" class="button button-secondary">Desligar</a>
                <?php else: ?>
                    <p style="margin:0 0 16px;color:#666;">Inicie sessão com a conta Google que tem acesso aos perfis Lisboa + Porto + SP.</p>
                    <a href="<?php echo esc_url( self::get_oauth_url() ); ?>" class="button button-primary button-hero">Ligar Google Business Profile</a>
                <?php endif; ?>
            </div>

            <?php if ( $connected ): ?>
            <div style="background:#fff;border:1px solid #e0e0e0;padding:24px 28px;">
                <h2 style="margin:0 0 16px;font-size:18px;">3. Próximos passos</h2>
                <ul style="margin:0;padding-left:20px;color:#444;line-height:1.7;">
                    <li>Listagem de localizações + IDs (Stage 2) — em breve</li>
                    <li>Publicação de Produtos a partir do CPT formacao (Stage 4) — em breve</li>
                    <li>Posts requerem allowlist Google (Track B) — submetida em <em>data a indicar</em></li>
                </ul>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }
}
EDIT_GBP_Publisher::init();
