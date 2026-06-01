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

class EDIT_Brevo_Mail_Router {

    const DEFAULT_SENDER_EMAIL = 'geral@weareedit.io';
    const DEFAULT_SENDER_NAME  = 'EDIT.';

    public static function init(): void {
        // pre_wp_mail filter exists since WP 5.7. Returning anything
        // other than null short-circuits the rest of wp_mail().
        add_filter( 'pre_wp_mail', [ __CLASS__, 'route_via_brevo' ], 10, 2 );
    }

    /**
     * @param null|bool $short  Filter short-circuit value. null = continue.
     * @param array     $atts   wp_mail() args: to, subject, message, headers, attachments
     */
    public static function route_via_brevo( $short, $atts ) {
        // Already handled? Let it pass.
        if ( $short !== null ) return $short;

        $settings = get_option( 'edit_seo_fix_settings', [] );
        $api_key  = (string) ( $settings['brevo_api_key'] ?? '' );
        if ( $api_key === '' ) return $short; // No key — let WP fall back (will still fail, but logs cleaner)

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

        $sender_email = $parsed['from_email'] ?: (string) ( $settings['welcome_sender_email'] ?? self::DEFAULT_SENDER_EMAIL );
        $sender_name  = $parsed['from_name']  ?: (string) ( $settings['welcome_sender_name']  ?? self::DEFAULT_SENDER_NAME );
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
        if ( ! empty( $parsed['reply_to'] ) ) $payload['replyTo'] = $parsed['reply_to'];

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
            if ( strpos( $line, ':' ) === false ) continue;
            [ $name, $value ] = array_map( 'trim', explode( ':', $line, 2 ) );
            $key = strtolower( $name );
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
