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
    const OPT_CLIENT_ID         = 'edit_gbp_client_id';            // plain (it's a public value)
    const OPT_CLIENT_SECRET     = 'edit_gbp_client_secret';        // encrypted blob

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
        // wp-config constants take precedence (more secure); fall back to
        // encrypted wp_options for environments where editing wp-config
        // isn't convenient.
        if ( defined( 'EDIT_GBP_CLIENT_ID' ) && EDIT_GBP_CLIENT_ID ) return (string) EDIT_GBP_CLIENT_ID;
        return (string) get_option( self::OPT_CLIENT_ID, '' );
    }

    private static function client_secret(): string {
        if ( defined( 'EDIT_GBP_CLIENT_SECRET' ) && EDIT_GBP_CLIENT_SECRET ) return (string) EDIT_GBP_CLIENT_SECRET;
        $blob = get_option( self::OPT_CLIENT_SECRET, '' );
        return $blob ? self::decrypt( (string) $blob ) : '';
    }

    private static function redirect_uri(): string {
        // NOTE: 'action' is a reserved Google OAuth param — Google rejects any
        // redirect_uri containing 'action=' with 'invalid_request'. Using
        // 'gbp_callback=1' instead. If you change this, also update the
        // Authorised redirect URI in Google Cloud Console.
        return admin_url( 'admin.php?page=edit-gbp-publisher&gbp_callback=1' );
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
        // NOTE: NOT using add_query_arg() here. WP's add_query_arg has a quirk
        // where array values containing '&' don't get fully encoded, so the
        // redirect_uri arrives at Google with everything after the first '&'
        // chopped off (= redirect_uri_mismatch). http_build_query with
        // PHP_QUERY_RFC3986 strict-encodes every value including the '&' in
        // our redirect_uri's query string, so Google sees the full URL.
        $params = http_build_query( [
            'client_id'              => self::client_id(),
            'redirect_uri'           => self::redirect_uri(),
            'response_type'          => 'code',
            'scope'                  => self::SCOPE,
            'access_type'            => 'offline',
            'prompt'                 => 'consent',
            'include_granted_scopes' => 'true',
            'state'                  => $state,
        ], '', '&', PHP_QUERY_RFC3986 );
        return self::OAUTH_AUTH_URL . '?' . $params;
    }

    /**
     * OAuth callback handler — fires on every admin_init, no-ops unless we're
     * on our settings page with the oauth-callback action.
     */
    public static function handle_oauth_callback(): void {
        if ( ! is_admin() ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ( $_GET['page'] ?? '' ) !== 'edit-gbp-publisher' ) return;
        // Accept both the new gbp_callback param (post-v1.5.459) AND the legacy
        // action=oauth-callback for OAuth clients registered before the rename.
        $is_callback = ( $_GET['gbp_callback'] ?? '' ) === '1'
            || ( $_GET['action'] ?? '' ) === 'oauth-callback';
        if ( ! $is_callback ) return;

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
       API helpers
       ────────────────────────────────────────────────────────────────── */

    /**
     * Authenticated GET against the GBP REST APIs. Returns decoded JSON array
     * on success, WP_Error on failure.
     */
    public static function api_get( string $url ) {
        $token = self::access_token();
        if ( ! $token ) return new WP_Error( 'edit_gbp_no_token', 'Not connected to GBP.' );
        $res = wp_remote_get( $url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ] );
        if ( is_wp_error( $res ) ) return $res;
        $code = (int) wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( $code >= 400 ) {
            $msg = is_array( $body ) && isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code;
            return new WP_Error( 'edit_gbp_api_' . $code, $msg, $body );
        }
        return is_array( $body ) ? $body : [];
    }

    /**
     * Fetch the list of GBP accounts (location groups) the connected user
     * has access to.
     */
    public static function fetch_accounts() {
        return self::api_get( self::API_ACCOUNTS_BASE . '/accounts' );
    }

    /**
     * Fetch locations for a given account_id. Returns array of location
     * objects (each has 'name' like "locations/12345", 'title', address,
     * etc.). The fields we want must be in readMask query param.
     */
    public static function fetch_locations_for_account( string $account_id ) {
        $url = self::API_BUSINESS_INFO . '/' . $account_id . '/locations'
             . '?readMask=name,title,storefrontAddress,phoneNumbers,categories,websiteUri,openInfo,metadata';
        return self::api_get( $url );
    }

    /**
     * Fetch a single location including its serviceItems + categories
     * (with serviceTypes). This is the read-side primitive for Stage 4A —
     * Services Sync. `$location_id` is the form "locations/12345".
     *
     * The readMask must include `serviceItems` to get the current services
     * list, and `categories` (with its nested `serviceTypes`) to discover
     * which Google-defined services the location's category allows. EDIT.'s
     * primary category is "Vocational School" (gcid:vocational_school)
     * which allows free-form service items — courses become free-form
     * ServiceItem entries with displayName + description per language.
     *
     * Returns the raw location JSON array on success, WP_Error on failure.
     */
    public static function fetch_location_full( string $location_id ) {
        $location_id = ltrim( $location_id, '/' );
        if ( $location_id === '' ) {
            return new WP_Error( 'edit_gbp_no_location', 'Empty location_id.' );
        }
        $url = self::API_BUSINESS_INFO . '/' . $location_id
             . '?readMask=name,title,serviceItems,categories';
        return self::api_get( $url );
    }

    /**
     * Convenience: return just the serviceItems array for a location, or
     * empty array if none / on error. Errors are logged to
     * edit_gbp_last_error so admin UI can surface them.
     */
    public static function fetch_services_for_location( string $location_id ): array {
        $res = self::fetch_location_full( $location_id );
        if ( is_wp_error( $res ) ) {
            update_option( 'edit_gbp_last_error',
                'fetch_services_for_location(' . $location_id . '): ' . $res->get_error_message(),
                false
            );
            return [];
        }
        $items = $res['serviceItems'] ?? [];
        return is_array( $items ) ? $items : [];
    }

    /**
     * Normalise a ServiceItem entry into a flat array for UI/diff use.
     * Handles both freeFormServiceItem and structuredServiceItem variants
     * (a single ServiceItem has exactly one of them).
     */
    public static function normalise_service_item( array $item ): array {
        $out = [
            'kind'          => 'unknown',
            'category'      => '',
            'display_name'  => '',
            'description'   => '',
            'language_code' => '',
            'price_units'   => '',
            'price_currency'=> '',
            'service_type_id' => '',
        ];
        if ( isset( $item['freeFormServiceItem'] ) ) {
            $ff = $item['freeFormServiceItem'];
            $out['kind']          = 'free_form';
            $out['category']      = (string) ( $ff['category'] ?? '' );
            $out['display_name']  = (string) ( $ff['label']['displayName'] ?? '' );
            $out['description']   = (string) ( $ff['label']['description'] ?? '' );
            $out['language_code'] = (string) ( $ff['label']['languageCode'] ?? '' );
        } elseif ( isset( $item['structuredServiceItem'] ) ) {
            $ss = $item['structuredServiceItem'];
            $out['kind']            = 'structured';
            $out['service_type_id'] = (string) ( $ss['serviceTypeId'] ?? '' );
            $out['description']     = (string) ( $ss['description'] ?? '' );
        }
        if ( isset( $item['price'] ) && is_array( $item['price'] ) ) {
            $out['price_units']    = (string) ( $item['price']['units'] ?? '' );
            $out['price_currency'] = (string) ( $item['price']['currencyCode'] ?? '' );
        }
        return $out;
    }

    /**
     * High-level: get a flat list of all locations across all accounts the
     * user can access. Cached for 1 hour to avoid hammering the API on
     * every admin page load. Pass $force=true to bypass cache.
     *
     * Returns array of normalised entries:
     *   [ ['account_id'=>'accounts/X', 'location_id'=>'locations/Y',
     *      'title'=>..., 'address'=>..., 'phone'=>..., 'category'=>...,
     *      'status'=>..., 'website'=>...], ... ]
     */
    public static function get_all_locations( bool $force = false ): array {
        if ( ! $force ) {
            $cached = get_option( self::OPT_LOCATIONS_CACHE );
            if ( is_array( $cached ) && isset( $cached['expires_at'] ) && $cached['expires_at'] > time() ) {
                return is_array( $cached['data'] ?? null ) ? $cached['data'] : [];
            }
        }

        // Rate-limit backoff. GBP's My Business APIs default to ~1 req/min on
        // fresh Cloud projects. If a previous call hit the per-minute quota,
        // skip the next fetch for 90 seconds rather than re-hit and re-fail.
        $backoff = (int) get_option( 'edit_gbp_quota_backoff_until', 0 );
        if ( $backoff > time() ) {
            $secs_left = $backoff - time();
            update_option( 'edit_gbp_last_error',
                'Backoff de quota activo — aguarda ' . $secs_left . 's antes de tentar novamente. ' .
                'Pede aumento de quota em support.google.com/business/contact/api_default.',
                false
            );
            $cached = get_option( self::OPT_LOCATIONS_CACHE );
            return is_array( $cached['data'] ?? null ) ? $cached['data'] : [];
        }

        $out = [];
        $errors = [];
        $accounts_res = self::fetch_accounts();
        if ( is_wp_error( $accounts_res ) ) {
            $errors[] = 'fetch_accounts: ' . $accounts_res->get_error_message();
            // If quota error, set 90s backoff so subsequent reloads don't
            // hammer the same dead end.
            if ( strpos( $accounts_res->get_error_message(), 'Quota exceeded' ) !== false ) {
                update_option( 'edit_gbp_quota_backoff_until', time() + 90, false );
            }
            update_option( 'edit_gbp_last_error', implode( ' | ', $errors ), false );
            return [];
        }
        // Successful call clears any prior backoff.
        delete_option( 'edit_gbp_quota_backoff_until' );
        $accounts = $accounts_res['accounts'] ?? [];
        if ( empty( $accounts ) ) {
            update_option( 'edit_gbp_last_error', 'fetch_accounts returned 0 accounts. Raw body: ' . wp_json_encode( $accounts_res ), false );
        }

        foreach ( $accounts as $acct ) {
            $acct_id   = (string) ( $acct['name'] ?? '' );      // "accounts/12345"
            $acct_name = (string) ( $acct['accountName'] ?? '' );
            if ( ! $acct_id ) continue;
            $locs_res = self::fetch_locations_for_account( $acct_id );
            if ( is_wp_error( $locs_res ) ) {
                $errors[] = 'fetch_locations(' . $acct_id . '): ' . $locs_res->get_error_message();
                continue;
            }
            $locations = $locs_res['locations'] ?? [];
            foreach ( $locations as $loc ) {
                $address_lines = [];
                if ( ! empty( $loc['storefrontAddress']['addressLines'] ) ) {
                    $address_lines = $loc['storefrontAddress']['addressLines'];
                }
                $address_suffix = trim( implode( ', ', array_filter( [
                    $loc['storefrontAddress']['postalCode']      ?? '',
                    $loc['storefrontAddress']['locality']        ?? '',
                    $loc['storefrontAddress']['regionCode']      ?? '',
                ] ) ), ', ' );
                $address = trim( implode( ', ', array_filter( [
                    implode( ', ', $address_lines ),
                    $address_suffix,
                ] ) ), ', ' );

                $out[] = [
                    'account_id'   => $acct_id,
                    'account_name' => $acct_name,
                    'location_id'  => (string) ( $loc['name'] ?? '' ),       // "locations/Y"
                    'title'        => (string) ( $loc['title'] ?? '' ),
                    'address'      => $address,
                    'locality'     => (string) ( $loc['storefrontAddress']['locality'] ?? '' ),
                    'region'       => (string) ( $loc['storefrontAddress']['regionCode'] ?? '' ),
                    'phone'        => (string) ( $loc['phoneNumbers']['primaryPhone'] ?? '' ),
                    'website'      => (string) ( $loc['websiteUri'] ?? '' ),
                    'category'     => (string) ( $loc['categories']['primaryCategory']['displayName'] ?? '' ),
                    'status'       => (string) ( $loc['openInfo']['status'] ?? '' ),
                    'maps_uri'     => (string) ( $loc['metadata']['mapsUri'] ?? '' ),
                    'place_id'     => (string) ( $loc['metadata']['placeId'] ?? '' ),
                ];
            }
        }

        update_option( self::OPT_LOCATIONS_CACHE, [
            'data'       => $out,
            'expires_at' => time() + 3600, // 1h cache
        ], false );

        if ( ! empty( $errors ) ) {
            update_option( 'edit_gbp_last_error', implode( ' | ', $errors ), false );
        } elseif ( ! empty( $out ) ) {
            delete_option( 'edit_gbp_last_error' );
        }

        return $out;
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

    private static function render_credentials_form(): void {
        ?>
        <form method="post" style="display:flex;flex-direction:column;gap:12px;max-width:680px;">
            <?php wp_nonce_field( 'edit_gbp_save_creds' ); ?>
            <label style="display:flex;flex-direction:column;gap:4px;">
                <span style="font-size:13px;color:#444;font-weight:600;">Client ID</span>
                <input type="text" name="client_id" placeholder="XXXXXXXX.apps.googleusercontent.com" required style="padding:8px 10px;border:1px solid #ccc;border-radius:3px;font-family:monospace;font-size:13px;">
            </label>
            <label style="display:flex;flex-direction:column;gap:4px;">
                <span style="font-size:13px;color:#444;font-weight:600;">Client Secret</span>
                <input type="password" name="client_secret" placeholder="GOCSPX-XXXXXXXX" required style="padding:8px 10px;border:1px solid #ccc;border-radius:3px;font-family:monospace;font-size:13px;">
            </label>
            <div>
                <button type="submit" name="edit_gbp_save_creds" value="1" class="button button-primary">Guardar credenciais</button>
                <span style="margin-left:12px;font-size:12px;color:#666;">Secret encriptado em base de dados via AES-256-CBC.</span>
            </div>
        </form>
        <?php
    }

    /**
     * Render the Stage 4A Services Audit panel. For each detected location
     * (Lisboa, Porto, São Paulo), fetches the current ServiceItems +
     * categories and presents them in a per-location card. Read-only —
     * no writes happen here, this is the baseline view we'll diff against
     * in Phase 4B.
     */
    private static function render_services_audit(): void {
        $locations = self::get_all_locations( false );
        if ( empty( $locations ) ) {
            echo '<p style="color:#888;margin:0;font-size:13px;">Sem localizações disponíveis. Recarrega o painel 3 acima.</p>';
            return;
        }

        // Filter to the canonical EDIT. campuses by locality. Anything else
        // (test listings, archived locations) is hidden.
        $target_localities = [ 'Lisboa', 'Porto', 'São Paulo', 'Sao Paulo' ];
        $targets = array_values( array_filter( $locations, function( $l ) use ( $target_localities ) {
            return in_array( $l['locality'] ?? '', $target_localities, true );
        } ) );

        if ( empty( $targets ) ) {
            echo '<p style="color:#888;margin:0;font-size:13px;">Nenhuma das localizações conhecidas (Lisboa, Porto, São Paulo) foi encontrada na lista do painel 3.</p>';
            return;
        }

        $force_audit = isset( $_GET['refresh_services'] );

        foreach ( $targets as $loc ) {
            $location_id = $loc['location_id'];
            $cache_key   = 'edit_gbp_services_cache_' . md5( $location_id );
            $full        = $force_audit ? false : get_transient( $cache_key );
            $errored     = false;

            if ( ! is_array( $full ) ) {
                $res = self::fetch_location_full( $location_id );
                if ( is_wp_error( $res ) ) {
                    $errored = $res->get_error_message();
                    $full    = [];
                } else {
                    $full = $res;
                    set_transient( $cache_key, $full, 3600 ); // 1h
                }
            }

            $service_items = is_array( $full['serviceItems'] ?? null ) ? $full['serviceItems'] : [];
            $categories    = is_array( $full['categories']   ?? null ) ? $full['categories']   : [];

            $primary_cat   = (string) ( $categories['primaryCategory']['displayName'] ?? '' );
            $primary_gcid  = (string) ( $categories['primaryCategory']['name']        ?? '' );
            $extra_cats    = $categories['additionalCategories'] ?? [];

            echo '<div style="background:#fafafa;border:1px solid #e5e5e5;border-radius:4px;padding:18px 22px;margin-bottom:18px;">';
            echo '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin:0 0 14px;">';
            echo '<div>';
            echo '<h3 style="margin:0 0 4px;font-size:16px;">' . esc_html( $loc['title'] ) . ' · <span style="color:#888;font-weight:400;">' . esc_html( $loc['locality'] ) . '</span></h3>';
            echo '<small style="color:#888;font-family:monospace;">' . esc_html( $location_id ) . '</small>';
            echo '</div>';
            echo '<span style="background:#fff;border:1px solid #ddd;border-radius:99px;padding:4px 12px;font-size:12px;font-weight:600;color:' . ( count( $service_items ) ? '#1f6e1f' : '#b45309' ) . ';">' . count( $service_items ) . ' serviço' . ( count( $service_items ) === 1 ? '' : 's' ) . '</span>';
            echo '</div>';

            if ( $errored ) {
                echo '<p style="background:#fef2f2;color:#7c2d12;padding:10px 14px;border-radius:4px;font-size:13px;font-family:monospace;margin:0;">' . esc_html( $errored ) . '</p>';
                echo '</div>';
                continue;
            }

            // Categories block — these define the gcid: codes we can use for
            // free-form services in Phase 4B's mapper.
            echo '<details style="margin:0 0 14px;">';
            echo '<summary style="cursor:pointer;font-size:13px;color:#444;font-weight:600;">Categorias da localização (' . ( 1 + count( $extra_cats ) ) . ')</summary>';
            echo '<div style="margin:10px 0 0;padding:10px 14px;background:#fff;border-left:3px solid #60c5b3;font-size:13px;">';
            echo '<div style="margin-bottom:6px;"><strong>Primária:</strong> ' . esc_html( $primary_cat ) . ' <code style="background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:11px;">' . esc_html( $primary_gcid ) . '</code></div>';
            if ( ! empty( $extra_cats ) ) {
                echo '<div><strong>Adicionais:</strong><ul style="margin:6px 0 0 18px;padding:0;line-height:1.7;">';
                foreach ( $extra_cats as $cat ) {
                    $cat_name = (string) ( $cat['displayName'] ?? '' );
                    $cat_gcid = (string) ( $cat['name']        ?? '' );
                    echo '<li>' . esc_html( $cat_name ) . ' <code style="background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:11px;">' . esc_html( $cat_gcid ) . '</code></li>';
                }
                echo '</ul></div>';
            }
            echo '</div>';
            echo '</details>';

            // Service items block.
            if ( empty( $service_items ) ) {
                echo '<p style="margin:0;padding:12px 14px;background:#fffbeb;border-left:3px solid #f59e0b;font-size:13px;color:#78350f;">Sem serviços publicados nesta localização. Phase 4B vai propor a lista a partir do CPT <code>formacao</code>.</p>';
            } else {
                echo '<table class="widefat striped" style="margin:0;">';
                echo '<thead><tr><th style="width:28%;">Nome</th><th style="width:36%;">Descrição</th><th style="width:10%;">Tipo</th><th style="width:14%;">Categoria</th><th style="width:12%;">Idioma · Preço</th></tr></thead><tbody>';
                foreach ( $service_items as $item ) {
                    $n = self::normalise_service_item( $item );
                    echo '<tr>';
                    echo '<td><strong>' . esc_html( $n['display_name'] ?: '—' ) . '</strong>';
                    if ( $n['kind'] === 'structured' && $n['service_type_id'] ) {
                        echo '<br><small style="color:#888;font-family:monospace;font-size:11px;">' . esc_html( $n['service_type_id'] ) . '</small>';
                    }
                    echo '</td>';
                    echo '<td style="font-size:13px;color:#444;line-height:1.5;">' . esc_html( wp_trim_words( $n['description'], 24, '…' ) ) . '</td>';
                    echo '<td><span style="font-size:11px;background:' . ( $n['kind'] === 'structured' ? '#dbeafe' : '#fef3c7' ) . ';padding:2px 8px;border-radius:99px;color:#1f2937;">' . esc_html( $n['kind'] ) . '</span></td>';
                    echo '<td><code style="font-size:11px;background:#f0f0f0;padding:2px 6px;border-radius:3px;">' . esc_html( $n['category'] ?: '—' ) . '</code></td>';
                    echo '<td style="font-size:12px;color:#666;">' . esc_html( $n['language_code'] ?: '—' );
                    if ( $n['price_units'] ) {
                        echo '<br>' . esc_html( $n['price_units'] . ' ' . $n['price_currency'] );
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }

            echo '</div>'; // /location card
        }

        echo '<div style="margin-top:8px;">';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=edit-gbp-publisher&refresh_services=1' ) ) . '" class="button button-secondary">Recarregar serviços do GBP</a>';
        echo '<span style="margin-left:12px;font-size:12px;color:#888;">Cache de 1 hora por localização</span>';
        echo '</div>';
    }

    public static function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Handle disconnect action.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'disconnect' && check_admin_referer( 'edit_gbp_disconnect' ) ) {
            self::disconnect();
            wp_safe_redirect( admin_url( 'admin.php?page=edit-gbp-publisher&disconnected=1' ) );
            exit;
        }

        // Handle credentials save (POST form below).
        if ( isset( $_POST['edit_gbp_save_creds'] ) && check_admin_referer( 'edit_gbp_save_creds' ) ) {
            $cid = isset( $_POST['client_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) ) : '';
            $cs  = isset( $_POST['client_secret'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) ) : '';
            if ( $cid && $cs ) {
                update_option( self::OPT_CLIENT_ID, $cid, false );
                update_option( self::OPT_CLIENT_SECRET, self::encrypt( $cs ), false );
                wp_safe_redirect( admin_url( 'admin.php?page=edit-gbp-publisher&creds_saved=1' ) );
                exit;
            }
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
            <?php if ( isset( $_GET['creds_saved'] ) ): ?>
                <div class="notice notice-success"><p>Credenciais OAuth guardadas com sucesso.</p></div>
            <?php endif; ?>

            <div style="background:#fff;border:1px solid #e0e0e0;padding:24px 28px;margin-bottom:24px;">
                <h2 style="margin:0 0 16px;font-size:18px;">1. Credenciais OAuth</h2>
                <?php if ( $configured ): ?>
                    <p style="margin:0 0 12px;color:#1f6e1f;">✅ Credenciais OAuth configuradas.</p>
                    <details>
                        <summary style="cursor:pointer;color:#666;font-size:13px;">Substituir credenciais</summary>
                        <div style="margin-top:14px;">
                            <?php self::render_credentials_form(); ?>
                        </div>
                    </details>
                <?php else: ?>
                    <p style="margin:0 0 12px;color:#444;">Cola o <strong>Client ID</strong> e o <strong>Client secret</strong> do ficheiro JSON descarregado da Google Cloud Console.</p>
                    <?php self::render_credentials_form(); ?>
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
            <div style="background:#fff;border:1px solid #e0e0e0;padding:24px 28px;margin-bottom:24px;">
                <h2 style="margin:0 0 16px;font-size:18px;">3. Localizações</h2>
                <?php
                $force = isset( $_GET['refresh_locations'] );
                $locations = self::get_all_locations( $force );
                ?>
                <?php if ( empty( $locations ) ): ?>
                    <p style="color:#b45309;margin:0 0 12px;">⚠️ Nenhuma localização encontrada.</p>
                    <?php $err = get_option( 'edit_gbp_last_error', '' ); if ( $err ): ?>
                        <details style="background:#fef3c7;padding:14px 18px;border-radius:4px;font-size:12px;font-family:monospace;color:#7c2d12;">
                            <summary style="cursor:pointer;font-weight:600;">Detalhes do erro</summary>
                            <pre style="margin:10px 0 0;white-space:pre-wrap;word-break:break-word;"><?php echo esc_html( (string) $err ); ?></pre>
                        </details>
                    <?php endif; ?>
                    <p style="margin:14px 0 0;font-size:13px;color:#666;">Possíveis causas:</p>
                    <ul style="margin:6px 0 0 20px;font-size:13px;color:#666;line-height:1.6;">
                        <li>Scope <code>business.manage</code> não foi concedido durante o OAuth (re-aprova via Desligar + Ligar)</li>
                        <li>A conta Google ligada não é gestora das fichas (verifica em business.google.com)</li>
                        <li>API My Business Account Management não está activa</li>
                    </ul>
                <?php else: ?>
                    <table class="widefat striped" style="margin:0 0 14px;">
                        <thead>
                            <tr>
                                <th style="width:32%;">Localização</th>
                                <th style="width:30%;">Morada</th>
                                <th style="width:14%;">Categoria</th>
                                <th style="width:10%;">Estado</th>
                                <th style="width:14%;">Place ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $locations as $loc ): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $loc['title'] ); ?></strong><br>
                                        <small style="color:#888;font-family:monospace;"><?php echo esc_html( $loc['location_id'] ); ?></small>
                                        <?php if ( $loc['maps_uri'] ): ?>
                                            · <a href="<?php echo esc_url( $loc['maps_uri'] ); ?>" target="_blank" style="font-size:12px;">Maps ↗</a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html( $loc['address'] ); ?>
                                        <?php if ( $loc['phone'] ): ?><br><small style="color:#666;"><?php echo esc_html( $loc['phone'] ); ?></small><?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $loc['category'] ); ?></td>
                                    <td>
                                        <?php
                                        $status = $loc['status'] ?: 'UNKNOWN';
                                        $color  = $status === 'OPEN' ? '#1f6e1f' : ( $status === 'CLOSED_TEMPORARILY' ? '#b45309' : '#888' );
                                        ?>
                                        <span style="color:<?php echo esc_attr( $color ); ?>;font-weight:600;font-size:12px;"><?php echo esc_html( $status ); ?></span>
                                    </td>
                                    <td><small style="font-family:monospace;font-size:11px;color:#666;"><?php echo esc_html( $loc['place_id'] ); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=edit-gbp-publisher&refresh_locations=1' ) ); ?>" class="button button-secondary">Recarregar do GBP</a>
                    <span style="margin-left:12px;font-size:12px;color:#888;">Cache de 1 hora · <?php echo count( $locations ); ?> localizações</span>
                <?php endif; ?>
            </div>

            <div style="background:#fff;border:1px solid #e0e0e0;padding:24px 28px;margin-bottom:24px;">
                <h2 style="margin:0 0 8px;font-size:18px;">4. Stage 4A · Auditoria de Serviços</h2>
                <p style="margin:0 0 16px;color:#666;font-size:13px;">
                    Lista o que cada localização Lisboa + Porto + SP tem actualmente publicado como Serviços no Google.
                    Esta é a base de comparação antes de fazer sincronização do CPT <code>formacao</code> (Phase 4B + 4C).
                </p>
                <?php self::render_services_audit(); ?>
            </div>

            <div style="background:#fff;border:1px solid #e0e0e0;padding:24px 28px;">
                <h2 style="margin:0 0 16px;font-size:18px;">5. Próximos passos</h2>
                <ul style="margin:0;padding-left:20px;color:#444;line-height:1.7;">
                    <li><strong>Phase 4B</strong> · Dry-run preview do diff (cursos activos do CPT <code>formacao</code> vs. serviços actuais)</li>
                    <li><strong>Phase 4C</strong> · Botão "Sincronizar agora" por localização (escrita real via PATCH)</li>
                    <li><strong>Phase 4D</strong> · Cron diário automático (apenas após 4C correr estável 7 dias)</li>
                    <li>Posts requerem allowlist Google (Track B / Stage 3) — submetida em <em>data a indicar</em></li>
                </ul>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }
}
EDIT_GBP_Publisher::init();
