<?php
/**
 * Brevo Mail Router — intercept wp_mail() and send via Brevo v3 API.
 *
 * WordPress's wp_mail() falls back to PHP's mail() when no SMTP plugin
 * is configured, which most managed hosts block. Result: CF7 form
 * submissions, password resets, comment notifications all silently fail.
 *
 * This class hooks the `pre_wp_mail` filter (WP 5.7+) to short-circuit
 * the normal send path and route every wp_mail() call through Brevo's
 * transactional /v3/smtp/email endpoint using our existing API key.
 *
 * Sender domain is already DKIM/SPF/DMARC-authenticated in Brevo, so
 * deliverability is guaranteed without any extra config.
 *
 * @package WeareEditSiteEngine
 * @since   1.5.195
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Override WP's pluggable wp_mail() — loaded BEFORE wp-includes/pluggable.php
 * during plugin bootstrap, so our function wins. Sends every wp_mail call
 * directly via Brevo's transactional API. Bypasses PHPMailer entirely so
 * blocked-host SMTP can't hang the request.
 *
 * Returns:
 *   true  — Brevo accepted the message (CF7 sees success → form transitions out
 *           of "A enviar..." into success state)
 *   false — Brevo rejected, no API key, or no recipients. CF7 shows error.
 */
if ( ! function_exists( 'wp_mail' ) ) {
    function wp_mail( $to, $subject, $message, $headers = '', $attachments = [] ) {
        $atts = [
            'to'          => $to,
            'subject'     => $subject,
            'message'     => $message,
            'headers'     => $headers,
            'attachments' => $attachments,
        ];

        // pre_wp_mail emulation — preserves WP 5.7+ semantics so any other
        // plugin hooking pre_wp_mail still works (and our own PHP_INT_MAX
        // handler will finally fire on this older WP core).
        if ( function_exists( 'apply_filters' ) ) {
            $short = apply_filters( 'pre_wp_mail', null, $atts );
            if ( $short !== null ) return (bool) $short;
            $atts  = apply_filters( 'wp_mail', $atts );
        }

        if ( ! class_exists( 'EDIT_Brevo_Mail_Router' ) ) return false;
        try {
            return EDIT_Brevo_Mail_Router::external_route( $atts ) === true;
        } catch ( \Throwable $e ) {
            return false;
        }
    }
}

class EDIT_Brevo_Mail_Router {

    const DEFAULT_SENDER_EMAIL = 'geral@weareedit.io';
    const DEFAULT_SENDER_NAME  = 'EDIT.';

    public static function init(): void {
        // pre_wp_mail filter exists since WP 5.7.
        // Run at PHP_INT_MAX so we ALWAYS have the last word — any other
        // SMTP plugin / e-goi addon that hooked pre_wp_mail and returned
        // false to mark failure gets overridden by our successful Brevo
        // send (Brevo's API already accepted the message at this point).
        add_filter( 'pre_wp_mail', [ __CLASS__, 'route_via_brevo' ], PHP_INT_MAX, 2 );

        // Fallback path — if wp_mail() has been replaced via the pluggable
        // mechanism by another plugin, pre_wp_mail won't fire. The wp_mail
        // filter (older API, runs INSIDE the function) usually still does.
        // Route via Brevo here as a defence in depth.
        add_filter( 'wp_mail', [ __CLASS__, 'route_via_brevo_filter' ], 1, 1 );

        // One-shot diagnostic — confirms who owns wp_mail() and whether
        // pre_wp_mail has any registered callbacks. Runs once per request
        // on the next wp_mail() call only.
        add_filter( 'wp_mail', [ __CLASS__, 'one_shot_diagnostic' ], 0, 1 );
    }

    private static $diag_fired = false;
    public static function one_shot_diagnostic( $args ) {
        if ( self::$diag_fired ) return $args;
        self::$diag_fired = true;
        if ( ! class_exists( 'EDIT_CF7_Debug' ) ) return $args;
        $owner = 'unknown';
        try {
            if ( function_exists( 'wp_mail' ) ) {
                $rf = new \ReflectionFunction( 'wp_mail' );
                $owner = ( $rf->getFileName() ?: 'internal' ) . ':' . $rf->getStartLine();
            }
        } catch ( \Throwable $e ) {
            $owner = 'reflection_error: ' . $e->getMessage();
        }
        global $wp_filter;
        $pre_cb = isset( $wp_filter['pre_wp_mail'] ) ? count( (array) $wp_filter['pre_wp_mail']->callbacks ?? [] ) : 0;
        EDIT_CF7_Debug::write( [
            'event'       => 'wp_mail_diag',
            'wp_mail_at'  => $owner,
            'pre_wp_mail' => 'priorities=' . $pre_cb,
            'class_loaded'=> class_exists( 'EDIT_Brevo_Mail_Router' ) ? 'yes' : 'no',
        ] );
        return $args;
    }

    /**
     * Filter-based router — same logic as route_via_brevo but on the
     * `wp_mail` filter instead of `pre_wp_mail`. Returns the args
     * unchanged (the filter doesn't short-circuit), but sends a parallel
     * email via Brevo so the user still gets the confirmation.
     */
    public static function route_via_brevo_filter( $args ) {
        if ( ! is_array( $args ) ) return $args;
        // Avoid double-send: if our pre_wp_mail handler already ran for
        // this same call, skip.
        if ( ! empty( $args['_edit_brevo_routed'] ) ) return $args;
        try {
            $result = self::do_route( null, $args );
            self::log_debug( 'brevo_filter_done', 'result=' . var_export( $result, true ), $args );
        } catch ( \Throwable $e ) {
            self::log_debug( 'brevo_filter_exception', $e->getMessage(), $args );
        }
        return $args;
    }

    /**
     * @param null|bool $short  Filter short-circuit value. null = continue.
     * @param array     $atts   wp_mail() args: to, subject, message, headers, attachments
     */
    public static function route_via_brevo( $short, $atts ) {
        // Unconditional entry log — confirms the filter is firing.
        self::log_debug( 'brevo_router_entry', 'fired', is_array( $atts ) ? $atts : [] );

        // Wrap everything in try/catch so a PHP error here doesn't kill the
        // request silently — we'd rather log + return false to CF7.
        try {
            return self::do_route( $short, $atts );
        } catch ( \Throwable $e ) {
            self::log_debug( 'brevo_router_exception', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), is_array( $atts ) ? $atts : [] );
            return false;
        }
    }

    /** Public wrapper so our overridden wp_mail() can call into the router. */
    public static function external_route( array $atts ) {
        try {
            return self::do_route( null, $atts );
        } catch ( \Throwable $e ) {
            self::log_debug( 'brevo_external_exception', $e->getMessage(), $atts );
            return false;
        }
    }

    private static function do_route( $short, $atts ) {
        // Another filter already short-circuited. Log + override anyway
        // so a failing SMTP plugin/e-goi addon can't block our Brevo route.
        if ( $short !== null ) {
            self::log_debug( 'brevo_router_override', 'overriding prior short=' . var_export( $short, true ), is_array( $atts ) ? $atts : [] );
        }

        $settings = get_option( 'edit_seo_fix_settings', [] );
        $api_key  = trim( (string) ( $settings['brevo_api_key'] ?? '' ) );
        if ( $api_key === '' ) {
            self::log_debug( 'brevo_router_skip', 'no api key configured', is_array( $atts ) ? $atts : [] );
            return $short;
        }

        // Extract attrs (wp_mail allows the filter to mutate them).
        $to          = $atts['to']          ?? '';
        $subject     = (string) ( $atts['subject']     ?? '' );
        $message     = (string) ( $atts['message']     ?? '' );
        $headers     = $atts['headers']     ?? '';
        $attachments = $atts['attachments'] ?? [];

        // Normalise recipients to a list of {email, name} objects.
        $to_list = self::parse_recipients( $to );
        if ( empty( $to_list ) ) {
            self::log_debug( 'brevo_router_skip', 'no recipients', $atts );
            return false;
        }

        // Parse headers for sender / Reply-To / CC / BCC / content-type.
        $parsed = self::parse_headers( $headers );

        // Product inscription forms (CF7) — both Mail 1 (admin
        // notification) and Mail 2 (user auto-reply) carry the subject
        // pattern "Pedido de informação / inscrição - <course>". Force
        // Francisco as the sender on those (override any From header CF7
        // attached). Everything else uses the welcome_sender_* settings
        // (Daniel default) or the parsed From header, whichever exists.
        $is_product_form = stripos( $subject, 'Pedido de' ) === 0;
        if ( $is_product_form ) {
            $sender_email = 'francisco.freitas@weareedit.io';
            $sender_name  = 'Francisco da EDIT.';
        } else {
            $sender_email = $parsed['from_email'] ?: (string) ( $settings['welcome_sender_email'] ?? self::DEFAULT_SENDER_EMAIL );
            $sender_name  = $parsed['from_name']  ?: (string) ( $settings['welcome_sender_name']  ?? self::DEFAULT_SENDER_NAME );
        }
        $is_html      = stripos( $parsed['content_type'] ?? '', 'html' ) !== false || self::looks_like_html( $message );

        $payload = [
            'sender'  => [ 'email' => $sender_email, 'name' => $sender_name ],
            'to'      => $to_list,
            'subject' => $subject,
        ];

        if ( $is_html ) {
            $payload['htmlContent'] = $message;
        } else {
            $payload['textContent'] = $message;
        }

        if ( ! empty( $parsed['cc'] ) )       $payload['cc']      = $parsed['cc'];
        if ( ! empty( $parsed['bcc'] ) )      $payload['bcc']     = $parsed['bcc'];
        // Brevo expects replyTo as a SINGLE object, not an array. Use the first only.
        if ( ! empty( $parsed['reply_to'] ) ) $payload['replyTo'] = $parsed['reply_to'][0];

        // Attachments: convert local file paths into base64 + name pairs.
        if ( ! empty( $attachments ) ) {
            $attachments = is_array( $attachments ) ? $attachments : [ $attachments ];
            $att_payload = [];
            foreach ( $attachments as $path ) {
                if ( file_exists( $path ) && is_readable( $path ) ) {
                    $att_payload[] = [
                        'name'    => basename( $path ),
                        'content' => base64_encode( file_get_contents( $path ) ),
                    ];
                }
            }
            if ( ! empty( $att_payload ) ) $payload['attachment'] = $att_payload;
        }

        // Tag the message so we can filter/track in Brevo.
        $payload['tags'] = [ 'wp-mail', parse_url( home_url(), PHP_URL_HOST ) ?: 'weareedit.io' ];

        $response = wp_remote_post( 'https://api.brevo.com/v3/smtp/email', [
            'method'  => 'POST',
            'timeout' => 15,
            'headers' => [
                'api-key'      => $api_key,
                'Content-Type' => 'application/json',
                'accept'       => 'application/json',
            ],
            'body' => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $response ) ) {
            self::log_debug( 'brevo_router_error', $response->get_error_message(), $atts );
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 ) {
            self::log_debug( 'brevo_router_ok', 'msgId ' . ( $body['messageId'] ?? '' ), $atts );
            return true;
        }

        $msg = is_array( $body ) && isset( $body['message'] ) ? $body['message'] : 'Brevo HTTP ' . $code;
        self::log_debug( 'brevo_router_fail', $msg, $atts );
        return false;
    }

    /** Normalise wp_mail's `to` arg into a list of {email, name?} objects. */
    private static function parse_recipients( $to ): array {
        $emails = is_array( $to ) ? $to : preg_split( '~[,;]+~', (string) $to );
        $out = [];
        foreach ( (array) $emails as $entry ) {
            $entry = trim( (string) $entry );
            if ( $entry === '' ) continue;
            $parsed = self::extract_email_and_name( $entry );
            if ( $parsed && is_email( $parsed['email'] ) ) {
                $obj = [ 'email' => $parsed['email'] ];
                if ( $parsed['name'] !== '' ) $obj['name'] = $parsed['name'];
                $out[] = $obj;
            }
        }
        return $out;
    }

    /** Handle "Name <email@host>" or bare email forms. */
    private static function extract_email_and_name( string $raw ): ?array {
        if ( preg_match( '~^(.*?)<(.+?)>\s*$~', $raw, $m ) ) {
            return [ 'name' => trim( $m[1], " \t\n\r\0\x0B\"'" ), 'email' => trim( $m[2] ) ];
        }
        return [ 'name' => '', 'email' => $raw ];
    }

    /** Parse WP-style headers (string OR array) into structured fields. */
    private static function parse_headers( $headers ): array {
        $out = [
            'from_email'   => '',
            'from_name'    => '',
            'reply_to'     => [],
            'cc'           => [],
            'bcc'          => [],
            'content_type' => '',
        ];

        if ( empty( $headers ) ) return $out;
        $lines = is_array( $headers ) ? $headers : preg_split( '~\r?\n~', (string) $headers );

        foreach ( $lines as $line ) {
            $line = (string) $line;
            if ( strpos( $line, ':' ) === false ) continue;
            $parts = array_map( 'trim', explode( ':', $line, 2 ) );
            $name  = isset( $parts[0] ) ? $parts[0] : '';
            $value = isset( $parts[1] ) ? $parts[1] : '';
            $key   = strtolower( $name );
            switch ( $key ) {
                case 'from':
                    $p = self::extract_email_and_name( $value );
                    if ( $p ) { $out['from_email'] = $p['email']; $out['from_name'] = $p['name']; }
                    break;
                case 'reply-to':
                    $p = self::extract_email_and_name( $value );
                    if ( $p && is_email( $p['email'] ) ) {
                        $obj = [ 'email' => $p['email'] ];
                        if ( $p['name'] !== '' ) $obj['name'] = $p['name'];
                        $out['reply_to'][] = $obj;
                    }
                    break;
                case 'cc':
                case 'bcc':
                    foreach ( preg_split( '~[,;]+~', $value ) as $entry ) {
                        $p = self::extract_email_and_name( trim( $entry ) );
                        if ( $p && is_email( $p['email'] ) ) {
                            $obj = [ 'email' => $p['email'] ];
                            if ( $p['name'] !== '' ) $obj['name'] = $p['name'];
                            $out[ $key ][] = $obj;
                        }
                    }
                    break;
                case 'content-type':
                    $out['content_type'] = $value;
                    break;
            }
        }
        return $out;
    }

    private static function looks_like_html( string $msg ): bool {
        return (bool) preg_match( '~<(html|body|p|div|table|br|a)\b~i', $msg );
    }

    private static function log_debug( string $event, string $detail, array $atts ): void {
        if ( ! class_exists( 'EDIT_CF7_Debug' ) ) return;
        EDIT_CF7_Debug::write( [
            'event'      => $event,
            'form_title' => 'wp_mail() router',
            'subject'    => $atts['subject'] ?? '',
            'to'         => is_array( $atts['to'] ?? '' ) ? implode( ', ', $atts['to'] ) : (string) ( $atts['to'] ?? '' ),
            'detail'     => $detail,
        ] );
    }
}
