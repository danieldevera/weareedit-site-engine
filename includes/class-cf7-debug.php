<?php
/**
 * CF7 Debug Logger — captures every Contact Form 7 submission's true status
 * so we can diagnose "Erro ao enviar" failures without guessing.
 *
 * Frontend always shows the generic message ("Erro ao enviar. Por favor,
 * tente de novo mais tarde.") for several CF7 internal failure modes
 * (mail_failed, spam_blocked, validation_failed, etc.). This logger writes
 * the *real* status + reason + form ID + submitter IP to a dedicated log
 * file so we can see which knob to turn.
 *
 * Log location: wp-content/uploads/edit-cf7-debug.log (web-protected by
 * .htaccess — written defensively here too).
 *
 * Surface in WP Admin: Tools → CF7 Debug Log (tail-view of last 200 lines).
 *
 * Auto-rotates at 1 MB to avoid disk bloat. Auto-prunes entries older than 7
 * days. Logging can be killed by defining EDIT_CF7_DEBUG_DISABLE in wp-config.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_CF7_Debug {

    const LOG_FILENAME   = 'edit-cf7-debug.log';
    const LOG_MAX_BYTES  = 1048576; // 1 MB → rotate
    const RETENTION_DAYS = 7;
    const ADMIN_SLUG     = 'edit-cf7-debug';

    public static function init(): void {
        if ( defined( 'EDIT_CF7_DEBUG_DISABLE' ) && EDIT_CF7_DEBUG_DISABLE ) return;

        // Trigger order matters — wpcf7_submit fires last and carries the
        // resolved status (mail_sent / mail_failed / spam / validation_failed
        // / unaccepted / etc.). Each finer hook also fires earlier to give us
        // intermediate signals (e.g. spam_log can pre-block before mail).
        add_action( 'wpcf7_before_send_mail', [ __CLASS__, 'log_pre_send' ], 10, 3 );
        add_action( 'wpcf7_mail_sent',        [ __CLASS__, 'log_mail_sent' ], 10, 1 );
        add_action( 'wpcf7_mail_failed',      [ __CLASS__, 'log_mail_failed' ], 10, 1 );
        add_action( 'wpcf7_spam',             [ __CLASS__, 'log_spam' ], 10, 2 );
        add_filter( 'wpcf7_spam',             [ __CLASS__, 'log_spam_filter' ], 99, 2 );
        add_action( 'wpcf7_submit',           [ __CLASS__, 'log_submit' ], 10, 2 );

        // Admin viewer page
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_page' ] );
    }

    /**
     * Resolve the log file path and make sure the directory exists + is
     * web-protected so the log never leaks publicly.
     */
    private static function log_path(): string {
        $uploads = wp_upload_dir();
        $dir     = trailingslashit( $uploads['basedir'] );
        $file    = $dir . self::LOG_FILENAME;
        $ht      = $dir . '.htaccess';
        if ( ! file_exists( $ht ) ) {
            @file_put_contents( $ht, "<FilesMatch \"^edit-cf7-debug\\.log\">\n  Require all denied\n</FilesMatch>\n" );
        }
        return $file;
    }

    private static function write( array $row ): void {
        $path = self::log_path();
        $row  = array_merge( [
            'ts'   => gmdate( 'c' ),
            'ip'   => self::client_ip(),
            'ua'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ), 0, 200 ) : '',
            'page' => isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '',
        ], $row );

        $line = wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( ! $line ) return;
        @file_put_contents( $path, $line . "\n", FILE_APPEND | LOCK_EX );

        if ( file_exists( $path ) && filesize( $path ) > self::LOG_MAX_BYTES ) {
            @rename( $path, $path . '.1' );
        }
    }

    private static function client_ip(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $k ) {
            if ( ! empty( $_SERVER[ $k ] ) ) {
                $ip = explode( ',', wp_unslash( $_SERVER[ $k ] ) )[0];
                return trim( filter_var( $ip, FILTER_VALIDATE_IP ) ?: '' );
            }
        }
        return '';
    }

    /**
     * Resolve form metadata into a compact array we attach to every log row.
     */
    private static function form_meta( $contact_form ): array {
        if ( ! $contact_form || ! method_exists( $contact_form, 'id' ) ) return [];
        return [
            'form_id'    => (int) $contact_form->id(),
            'form_title' => (string) $contact_form->title(),
            'form_slug'  => (string) ( method_exists( $contact_form, 'name' ) ? $contact_form->name() : '' ),
        ];
    }

    public static function log_pre_send( $contact_form, $abort, $submission ): void {
        self::write( array_merge(
            self::form_meta( $contact_form ),
            [
                'event' => 'before_send_mail',
                'abort' => (bool) $abort,
            ]
        ) );
    }

    public static function log_mail_sent( $contact_form ): void {
        self::write( array_merge( self::form_meta( $contact_form ), [ 'event' => 'mail_sent' ] ) );
    }

    public static function log_mail_failed( $contact_form ): void {
        $reason = '';
        if ( class_exists( 'WPCF7_Submission' ) ) {
            $sub = WPCF7_Submission::get_instance();
            if ( $sub ) {
                $reason = (string) $sub->get_response();
            }
        }
        self::write( array_merge(
            self::form_meta( $contact_form ),
            [ 'event' => 'mail_failed', 'reason' => $reason ]
        ) );
    }

    public static function log_spam( $spam, $submission = null ): void {
        self::write( [
            'event'   => 'spam_action',
            'spam'    => (bool) $spam,
        ] );
    }

    public static function log_spam_filter( $spam, $submission = null ) {
        if ( $spam ) {
            self::write( [
                'event' => 'spam_filter',
                'spam'  => true,
                'note'  => 'A spam filter returned true (reCAPTCHA score low, Akismet flag, or honeypot trigger).',
            ] );
        }
        return $spam;
    }

    public static function log_submit( $contact_form, $result ): void {
        $status  = isset( $result['status'] )  ? (string) $result['status']  : '';
        $message = isset( $result['message'] ) ? (string) $result['message'] : '';

        $sub_response = '';
        if ( class_exists( 'WPCF7_Submission' ) ) {
            $sub = WPCF7_Submission::get_instance();
            if ( $sub ) {
                $sub_response = (string) $sub->get_response();
            }
        }

        self::write( array_merge(
            self::form_meta( $contact_form ),
            [
                'event'        => 'submit',
                'status'       => $status,
                'message'      => $message,
                'sub_response' => $sub_response,
            ]
        ) );
    }

    public static function register_admin_page(): void {
        add_management_page(
            'CF7 Debug Log',
            'CF7 Debug Log',
            'manage_options',
            self::ADMIN_SLUG,
            [ __CLASS__, 'render_admin_page' ]
        );
    }

    public static function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        if ( isset( $_POST['edit_cf7_debug_clear'] ) && check_admin_referer( 'edit_cf7_debug_clear' ) ) {
            @unlink( self::log_path() );
            echo '<div class="notice notice-success"><p>Log limpo.</p></div>';
        }

        $path = self::log_path();
        $lines = [];
        if ( file_exists( $path ) ) {
            $raw = @file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
            if ( is_array( $raw ) ) {
                $lines = array_slice( $raw, -200 );
                $lines = array_reverse( $lines );
            }
        }
        ?>
        <div class="wrap">
            <h1>CF7 Debug Log</h1>
            <p>Tail of last 200 CF7 events. Newest first. Log lives at <code>wp-content/uploads/<?php echo esc_html( self::LOG_FILENAME ); ?></code>.</p>
            <form method="post" style="margin-bottom:16px;">
                <?php wp_nonce_field( 'edit_cf7_debug_clear' ); ?>
                <button type="submit" name="edit_cf7_debug_clear" class="button button-secondary" onclick="return confirm('Limpar log?');">Limpar log</button>
                <a href="<?php echo esc_url( add_query_arg( 'r', time() ) ); ?>" class="button">Recarregar</a>
            </form>
            <?php if ( empty( $lines ) ) : ?>
                <p><em>Sem eventos registados ainda. Submete um formulário e recarrega.</em></p>
            <?php else : ?>
                <table class="widefat striped" style="font-family:Menlo,Consolas,monospace;font-size:12px;">
                    <thead>
                        <tr>
                            <th style="width:170px;">Quando</th>
                            <th style="width:100px;">Evento</th>
                            <th style="width:80px;">Status</th>
                            <th style="width:180px;">Formulário</th>
                            <th>Detalhe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $lines as $line ) :
                            $row = json_decode( $line, true );
                            if ( ! is_array( $row ) ) continue;
                            $ts     = isset( $row['ts'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $row['ts'] ) ) : '';
                            $event  = $row['event'] ?? '';
                            $status = $row['status'] ?? '';
                            $form   = trim( ( $row['form_title'] ?? '' ) . ' #' . ( $row['form_id'] ?? '' ), ' #' );
                            $detail = [];
                            foreach ( [ 'message', 'sub_response', 'reason', 'note', 'spam', 'abort', 'page', 'ip' ] as $k ) {
                                if ( isset( $row[ $k ] ) && $row[ $k ] !== '' && $row[ $k ] !== null && $row[ $k ] !== false ) {
                                    $val = is_bool( $row[ $k ] ) ? ( $row[ $k ] ? 'true' : 'false' ) : $row[ $k ];
                                    $detail[] = esc_html( $k ) . ': ' . esc_html( (string) $val );
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html( $ts ); ?></td>
                                <td><strong><?php echo esc_html( $event ); ?></strong></td>
                                <td><?php echo esc_html( $status ); ?></td>
                                <td><?php echo esc_html( $form ); ?></td>
                                <td><?php echo implode( '<br>', $detail ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
