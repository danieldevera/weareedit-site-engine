<?php
/**
 * Newsletter Picks — autonomous engine for the Brevo welcome email.
 *
 * Daily WP-Cron job picks the 3 closest upcoming `formacao` editions
 * (1 Bootcamp + 1 Workshop + 1 Curso Remote/Presencial), renders the
 * locked welcome-email template with those picks + today's date, and
 * PUTs the resulting HTML to a Brevo template via the v3 REST API.
 *
 * Brevo's welcome automation keeps firing for new subscribers — they
 * always see today's fresh template, no manual updates needed.
 *
 * Locked source template: /Users/danieldevera/Downloads/edit-newsletter-2026/_welcome-email-locked.html
 * PHP renderer:           includes/templates/welcome-email.php
 *
 * Admin UI: Settings → EDIT. SEO Fix → Brevo Newsletter section.
 *
 * @package WeareEditSiteEngine
 * @since   1.5.189
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Newsletter_Picks {

    const CRON_HOOK         = 'edit_newsletter_picks_daily_sync';
    const OPT_LAST_SYNC     = 'edit_newsletter_picks_last_sync';
    const OPT_LAST_STATUS   = 'edit_newsletter_picks_last_status';
    const OPT_LAST_MESSAGE  = 'edit_newsletter_picks_last_message';
    const OPT_LAST_HTML     = 'edit_newsletter_picks_last_html';

    /**
     * Typology → accent colour (mirrors newsletter-signup.js / strip).
     * Drives the eyebrow + CTA colour per product card.
     */
    const TYPOLOGY_COLORS = [
        'workshop'         => '#60c5b3',
        'bootcamp'         => '#f92869',
        'curso_remote'     => '#0090eb',
        'curso_presencial' => '#ffdd06',
    ];

    /** Default tutor photo when the formacao has no associated tutor */
    const FALLBACK_TUTOR_PHOTO = 'https://weareedit.io/wp-content/uploads/2026/05/edit-extended-logo.png';

    public static function init(): void {
        add_action( 'init',            [ __CLASS__, 'register_cron' ] );
        add_action( self::CRON_HOOK,   [ __CLASS__, 'run_daily_sync' ] );
        add_action( 'admin_post_edit_newsletter_picks_force_sync', [ __CLASS__, 'handle_force_sync' ] );
    }

    // ---------------------------------------------------------------- Cron

    public static function register_cron(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            // First run tomorrow at 06:00 Europe/Lisbon, then daily.
            $first_run = self::next_run_timestamp();
            wp_schedule_event( $first_run, 'daily', self::CRON_HOOK );
        }
    }

    private static function next_run_timestamp(): int {
        try {
            $tz  = new DateTimeZone( 'Europe/Lisbon' );
            $dt  = new DateTime( 'tomorrow 06:00:00', $tz );
            return $dt->getTimestamp();
        } catch ( Exception $e ) {
            return time() + DAY_IN_SECONDS;
        }
    }

    /** Cron entry point. Logs status + message to wp_options for the admin UI. */
    public static function run_daily_sync(): void {
        $result = self::sync_to_brevo();
        update_option( self::OPT_LAST_SYNC,    time(),                     false );
        update_option( self::OPT_LAST_STATUS,  $result['ok'] ? 'ok' : 'error', false );
        update_option( self::OPT_LAST_MESSAGE, (string) ( $result['message'] ?? '' ),  false );

        if ( class_exists( 'EDIT_CF7_Debug' ) ) {
            EDIT_CF7_Debug::write( [
                'event'      => 'newsletter_picks_sync',
                'form_title' => 'Newsletter Picks Daily Sync',
                'result'     => $result['ok'] ? 'ok' : 'error',
                'message'    => $result['message'] ?? '',
                'picks'      => array_map( static function ( $p ) { return $p['title']; }, $result['picks'] ?? [] ),
            ] );
        }
    }

    /** Admin "Force sync now" button POST handler. */
    public static function handle_force_sync(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'edit_newsletter_picks_force_sync' );
        self::run_daily_sync();
        // Redirect back to the new top-level Email Marketing menu, not
        // the legacy SEO Fix settings page.
        $redirect = add_query_arg( [
            'page'         => 'edit-email-engines',
            'picks_synced' => '1',
        ], admin_url( 'admin.php' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    // ---------------------------------------------------------------- Picks

    /**
     * Return 3 picks for the welcome email — one closest upcoming
     * Bootcamp, one Workshop, one Curso (Remote preferred, Presencial
     * as fallback). Skips sold-out editions.
     *
     * Each pick:
     *   [
     *     'typology'       => 'workshop'|'bootcamp'|'curso_remote'|'curso_presencial',
     *     'typology_label' => 'Workshop'|'Bootcamp'|'Curso Remote'|'Curso Presencial',
     *     'color'          => '#hex',
     *     'title'          => 'Course title',
     *     'description'    => 'Short description line',
     *     'url'            => 'https://weareedit.io/formacao/slug/',
     *     'date_label'     => '5 Junho',
     *     'tutor_name'     => 'First Last',
     *     'tutor_role'     => 'Tutora · EDIT.',
     *     'tutor_url'      => 'https://weareedit.io/equipa/slug/',
     *     'tutor_photo'    => 'https://...280x280.png',
     *   ]
     */
    public static function get_current_picks(): array {
        // 1. Honour any manual pin set in Email Marketing settings — when
        //    present, those override the auto-pick logic entirely.
        $pinned = self::get_pinned_picks();
        if ( ! empty( $pinned ) ) return $pinned;

        // 2. Otherwise auto-pick 1 closest upcoming per typology bucket.
        $by_typology = [
            'bootcamp'         => [],
            'workshop'         => [],
            'curso_remote'     => [],
            'curso_presencial' => [],
        ];

        $posts = get_posts( [
            'post_type'      => 'formacao',
            'post_status'    => 'publish',
            'numberposts'    => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        foreach ( $posts as $p ) {
            $typology = self::detect_typology( $p->post_name );
            if ( ! $typology || ! isset( $by_typology[ $typology ] ) ) continue;
            if ( self::is_sold_out( $p->ID ) ) continue;
            $start = self::get_start_timestamp( $p->ID );
            if ( ! $start || $start < time() ) continue;

            $by_typology[ $typology ][] = [
                'post'  => $p,
                'start' => $start,
            ];
        }

        // Sort each typology bucket by start date asc.
        foreach ( $by_typology as $k => &$bucket ) {
            usort( $bucket, static function ( $a, $b ) { return $a['start'] <=> $b['start']; } );
        }
        unset( $bucket );

        $picks = [];

        // 1 Bootcamp
        if ( ! empty( $by_typology['bootcamp'] ) ) {
            $picks[] = self::build_pick( $by_typology['bootcamp'][0]['post'], 'bootcamp' );
        }
        // 1 Workshop
        if ( ! empty( $by_typology['workshop'] ) ) {
            $picks[] = self::build_pick( $by_typology['workshop'][0]['post'], 'workshop' );
        }
        // 1 Curso (Remote preferred, Presencial fallback)
        if ( ! empty( $by_typology['curso_remote'] ) ) {
            $picks[] = self::build_pick( $by_typology['curso_remote'][0]['post'], 'curso_remote' );
        } elseif ( ! empty( $by_typology['curso_presencial'] ) ) {
            $picks[] = self::build_pick( $by_typology['curso_presencial'][0]['post'], 'curso_presencial' );
        }

        return $picks;
    }

    /**
     * Read the "Featured products (override)" textarea from settings.
     * Returns up to 3 build_pick() arrays in the order provided, or
     * an empty array when no pins are configured.
     */
    private static function get_pinned_picks(): array {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        $raw = trim( (string) ( $settings['welcome_pinned_picks'] ?? '' ) );
        if ( $raw === '' ) return [];

        $lines = preg_split( '~\r?\n~', $raw );
        $picks = [];
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( $line === '' ) continue;
            // Accept either a bare slug or a full /formacao/<slug>/ URL.
            if ( preg_match( '~/formacao/([^/?#]+)~', $line, $m ) ) {
                $slug = $m[1];
            } else {
                $slug = $line;
            }
            $post = get_page_by_path( $slug, OBJECT, 'formacao' );
            if ( ! $post instanceof WP_Post ) continue;
            $typology = self::detect_typology( $slug, $post->ID ) ?: 'bootcamp';
            $picks[] = self::build_pick( $post, $typology );
            if ( count( $picks ) >= 3 ) break;
        }
        return $picks;
    }

    private static function detect_typology( string $slug, int $post_id = 0 ): ?string {
        if ( stripos( $slug, 'workshop' ) !== false ) return 'workshop';
        if ( stripos( $slug, 'bootcamp' ) !== false ) return 'bootcamp';
        if ( stripos( $slug, 'curso' ) !== false ) {
            if ( stripos( $slug, 'porto' ) !== false || stripos( $slug, 'lisboa' ) !== false ) {
                return 'curso_presencial';
            }
            return 'curso_remote';
        }
        // Slug didn't carry the typology keyword (e.g. `agentes-inteligentes-
        // para-marketing` is actually a Bootcamp). Fall back to inspecting
        // the ACF `titulo` field + post_title for the keyword.
        if ( $post_id > 0 ) {
            $needle = '';
            if ( function_exists( 'get_field' ) ) {
                $needle .= ' ' . (string) get_field( 'titulo',       $post_id );
                $needle .= ' ' . (string) get_field( 'home_titulo',  $post_id );
            }
            $post = get_post( $post_id );
            if ( $post ) $needle .= ' ' . (string) $post->post_title;
            if ( stripos( $needle, 'workshop' ) !== false ) return 'workshop';
            if ( stripos( $needle, 'bootcamp' ) !== false ) return 'bootcamp';
            if ( stripos( $needle, 'curso' )    !== false ) return 'curso_remote';
        }
        return null;
    }

    private static function is_sold_out( int $post_id ): bool {
        if ( function_exists( 'get_field' ) ) {
            $esgotada = get_field( 'esgotada', $post_id );
            if ( $esgotada ) return true;
        }
        return false;
    }

    /** Parse the `home_data` ACF field (dd/mm/yyyy) into a unix timestamp. */
    private static function get_start_timestamp( int $post_id ): ?int {
        if ( ! function_exists( 'get_field' ) ) return null;
        $date_str = (string) get_field( 'home_data', $post_id );
        if ( $date_str === '' ) return null;
        // Expected dd/mm/yyyy. Be lenient.
        $parts = preg_split( '~[/\-\.]~', $date_str );
        if ( count( $parts ) === 3 ) {
            $d = (int) $parts[0];
            $m = (int) $parts[1];
            $y = (int) $parts[2];
            if ( $y < 100 ) $y += 2000;
            if ( $d > 0 && $m > 0 && $y > 0 ) {
                try {
                    $dt = new DateTime( sprintf( '%04d-%02d-%02d 00:00:00', $y, $m, $d ), new DateTimeZone( 'Europe/Lisbon' ) );
                    return $dt->getTimestamp();
                } catch ( Exception $e ) {}
            }
        }
        $fallback = strtotime( $date_str );
        return $fallback ?: null;
    }

    private static function build_pick( WP_Post $post, string $typology ): array {
        $start_ts = self::get_start_timestamp( $post->ID );
        $date_label = $start_ts ? self::format_date_pt( $start_ts ) : '';
        $tutor      = self::get_first_tutor( $post->ID );

        return [
            'typology'       => $typology,
            'typology_label' => self::typology_label( $typology ),
            'color'          => self::TYPOLOGY_COLORS[ $typology ] ?? '#0a0a0a',
            'title'          => self::clean_title( $post ),
            'description'    => self::build_description( $post, $typology ),
            'url'            => get_permalink( $post ),
            'date_label'     => $date_label,
            'tutor_name'     => $tutor['name'] ?? '',
            'tutor_role'     => $tutor['role'] ?? 'Tutor · EDIT.',
            'tutor_url'      => $tutor['url']  ?? '#',
            'tutor_photo'    => $tutor['photo'] ?? self::FALLBACK_TUTOR_PHOTO,
        ];
    }

    private static function typology_label( string $typology ): string {
        switch ( $typology ) {
            case 'workshop':         return 'Workshop';
            case 'bootcamp':         return 'Bootcamp';
            case 'curso_remote':     return 'Curso Remote';
            case 'curso_presencial': return 'Curso Presencial';
        }
        return 'EDIT.';
    }

    /** Strip the "<font color=\"#fff\">…</font></br>" prefix the theme injects. */
    private static function clean_title( WP_Post $post ): string {
        $title = (string) $post->post_title;
        if ( function_exists( 'get_field' ) ) {
            $acf_title = (string) get_field( 'titulo', $post->ID );
            if ( $acf_title !== '' ) $title = $acf_title;
        }
        $title = preg_replace( '~<font[^>]*>.*?</font>\s*</?br\s*/?>~i', '', $title );
        $title = wp_strip_all_tags( $title );
        $title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $title = trim( preg_replace( '~\s+~', ' ', $title ) );
        return $title;
    }

    private static function build_description( WP_Post $post, string $typology ): string {
        $bits = [];
        $is_remote = $typology !== 'curso_presencial';
        $bits[] = $is_remote ? 'Remote' : 'Presencial · Lisboa ou Porto';

        if ( function_exists( 'get_field' ) ) {
            $duracao = (string) get_field( 'duracao', $post->ID );
            // Sometimes lives inside `bloco_informacao.duracao`
            if ( $duracao === '' ) {
                $bloco   = get_field( 'bloco_informacao', $post->ID );
                $duracao = is_array( $bloco ) && isset( $bloco['duracao'] ) ? (string) $bloco['duracao'] : '';
            }
            if ( $duracao !== '' ) $bits[] = $duracao;
        }

        return implode( ' · ', array_filter( $bits ) );
    }

    /** Pull the first tutor relation off `seccao_equipa.elemento` (singular). */
    private static function get_first_tutor( int $post_id ): array {
        if ( ! function_exists( 'get_field' ) ) return [];
        $group = get_field( 'seccao_equipa', $post_id );
        if ( ! is_array( $group ) ) return [];

        $tutor_post = null;
        // Per project_formacao_acf_map: sub-field is `elemento` (singular).
        // Some legacy posts may have `elementos` (plural) — try both.
        foreach ( [ 'elemento', 'elementos' ] as $key ) {
            if ( ! isset( $group[ $key ] ) ) continue;
            $val = $group[ $key ];
            if ( is_array( $val ) && ! empty( $val ) ) {
                // ACF relation returns array of post IDs or post objects.
                $first = $val[0];
                $tutor_post = is_object( $first ) ? $first : get_post( (int) $first );
                if ( $tutor_post ) break;
            } elseif ( is_object( $val ) ) {
                $tutor_post = $val;
                break;
            } elseif ( is_numeric( $val ) ) {
                $tutor_post = get_post( (int) $val );
                if ( $tutor_post ) break;
            }
        }

        if ( ! $tutor_post ) return [];

        $name  = wp_strip_all_tags( $tutor_post->post_title );
        $url   = get_permalink( $tutor_post );
        $photo = self::FALLBACK_TUTOR_PHOTO;

        // Resolution order for the tutor photo:
        // 1. Newsletter-specific PNG uploaded as `{tutor-slug}-280x280-1` attachment
        // 2. Featured image on the tutor profile post (medium size)
        // 3. EDIT. fallback logo
        $nl_photo = self::resolve_nl_photo_url( (string) $tutor_post->post_name );
        if ( $nl_photo ) {
            $photo = $nl_photo;
        } else {
            $thumb = get_the_post_thumbnail_url( $tutor_post, 'medium' );
            if ( $thumb ) $photo = $thumb;
        }

        $role = 'Tutor · EDIT.';
        $acf_role = (string) get_field( 'cargo', $tutor_post->ID );
        if ( $acf_role !== '' ) $role = $acf_role;

        return [
            'name'  => $name,
            'role'  => $role,
            'url'   => $url,
            'photo' => $photo,
        ];
    }

    /**
     * Find a newsletter-specific tutor photo uploaded as an attachment
     * with slug `{tutor-slug}-280x280-1`. Returns the full URL or null.
     * Result cached for 1 day per tutor slug.
     */
    private static function resolve_nl_photo_url( string $tutor_slug ): ?string {
        if ( $tutor_slug === '' ) return null;
        $cache_key = 'edit_nl_photo_' . md5( $tutor_slug );
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) return $cached === '' ? null : $cached;

        $candidates = [
            $tutor_slug . '-280x280-1-1', // WP dedup suffix when an earlier version exists
            $tutor_slug . '-280x280-1',
            $tutor_slug . '-280x280',
        ];
        // Some tutor slugs have multi-part names (e.g. miguel-rao-vieira) but
        // the uploaded NL file uses a shorter form (miguel-rao-280x280-1).
        // Try truncating one segment at a time as a fallback.
        $parts = explode( '-', $tutor_slug );
        if ( count( $parts ) > 2 ) {
            $short = implode( '-', array_slice( $parts, 0, 2 ) );
            $candidates[] = $short . '-280x280-1-1';
            $candidates[] = $short . '-280x280-1';
            $candidates[] = $short . '-280x280';
        }

        foreach ( $candidates as $name ) {
            $found = get_posts( [
                'post_type'      => 'attachment',
                'name'           => $name,
                'post_status'    => 'inherit',
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ] );
            if ( ! empty( $found ) ) {
                $url = wp_get_attachment_url( $found[0]->ID );
                if ( $url ) {
                    set_transient( $cache_key, $url, DAY_IN_SECONDS );
                    return $url;
                }
            }
        }

        // Cache the miss for 1 hour — retry sooner than the hit cache in
        // case the user uploads the NL photo after this lookup.
        set_transient( $cache_key, '', HOUR_IN_SECONDS );
        return null;
    }

    private static function format_date_pt( int $ts ): string {
        $months = [ 1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro' ];
        $d = (int) wp_date( 'j', $ts );
        $m = (int) wp_date( 'n', $ts );
        return $d . ' ' . ( $months[ $m ] ?? '' );
    }

    /** Today's date in PT-PT format (e.g. "1 Junho 2026"). */
    public static function today_pt(): string {
        $months = [ 1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro' ];
        $now = current_time( 'timestamp' );
        $d = (int) wp_date( 'j', $now );
        $m = (int) wp_date( 'n', $now );
        $y = (int) wp_date( 'Y', $now );
        return $d . ' ' . ( $months[ $m ] ?? '' ) . ' ' . $y;
    }

    // ---------------------------------------------------------------- Render

    /** Render the locked welcome-email template with current picks. */
    public static function render_welcome_html(): string {
        $picks      = self::get_current_picks();
        $issue_date = self::today_pt();
        $copy       = self::welcome_copy();

        $template_file = WEAREDIT_SITE_ENGINE_PATH . 'includes/templates/welcome-email.php';
        if ( ! file_exists( $template_file ) ) {
            return '';
        }

        ob_start();
        $picks_data = $picks; // alias for template clarity
        include $template_file;
        return (string) ob_get_clean();
    }

    /**
     * Pull editable welcome-email copy from settings, falling back to
     * the locked defaults when a field is blank.
     */
    public static function welcome_copy(): array {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        $pick = static function ( $key, $default ) use ( $settings ) {
            $val = isset( $settings[ $key ] ) ? trim( (string) $settings[ $key ] ) : '';
            return $val !== '' ? $val : $default;
        };
        $default_body = "Sou o Daniel, fundador da EDIT.\n\nEsta newsletter é onde partilho, em primeira mão, as ideias que estamos a explorar, os tutores que estamos a entrevistar, e os cursos que estão a nascer aqui dentro.\n\nUma edição por semana. Sem fluff. Quando responderes a este email, sou eu que leio.\n\nDeixo-te aqui o que está em destaque agora.";
        return [
            'eyebrow'          => $pick( 'welcome_eyebrow',          'Bem-vindo à EDIT.' ),
            'headline'         => $pick( 'welcome_headline',         'Que bom ter-te por aqui.' ),
            'body'             => $pick( 'welcome_body',             $default_body ),
            'section_eyebrow'  => $pick( 'welcome_section_eyebrow',  'Em destaque agora' ),
            'section_headline' => $pick( 'welcome_section_headline', 'As próximas edições.' ),
            'signature_url'    => $pick( 'welcome_signature_url',    'https://weareedit.io/wp-content/uploads/2026/05/DANIEL-DEVERA-ASSINATURA.png' ),
        ];
    }

    // ---------------------------------------------------------------- Brevo

    /**
     * Default sender for the welcome email. Overridable via settings
     * (Email Marketing → Brevo connection → Welcome sender).
     */
    const DEFAULT_SENDER_EMAIL = 'daniel.devera@weareedit.io';
    const DEFAULT_SENDER_NAME  = 'Daniel Devera from EDIT.';
    const DEFAULT_SUBJECT      = 'Que bom ter-te por aqui na EDIT.';

    /**
     * Send the welcome email directly via Brevo's transactional API.
     * Path B — bypasses Brevo Automations entirely. Each call sends a
     * single welcome to one recipient with today's freshly rendered
     * HTML inlined. Returns ['ok' => bool, 'message' => string].
     *
     * Used by:
     *   - EDIT_Newsletter_Signup::handle_signup() on every new signup
     *   - The admin "Send test welcome" button
     *
     * @param string $email      Recipient email
     * @param string $first_name Optional first name for greeting personalization
     */
    public static function send_welcome_to( string $email, string $first_name = '' ): array {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        $api_key  = (string) ( $settings['brevo_api_key'] ?? '' );
        if ( $api_key === '' )       return [ 'ok' => false, 'message' => 'Brevo API key not configured.' ];
        if ( ! is_email( $email ) )  return [ 'ok' => false, 'message' => 'Invalid recipient email.' ];

        $sender_email = (string) ( $settings['welcome_sender_email'] ?? self::DEFAULT_SENDER_EMAIL );
        $sender_name  = (string) ( $settings['welcome_sender_name']  ?? self::DEFAULT_SENDER_NAME );
        $subject      = (string) ( $settings['welcome_subject']      ?? self::DEFAULT_SUBJECT );

        $html = self::render_welcome_html();
        if ( $html === '' ) return [ 'ok' => false, 'message' => 'Render failed: template file missing.' ];

        // Cache the last successful render for admin preview.
        update_option( self::OPT_LAST_HTML, $html, false );

        $payload = [
            'sender'      => [ 'name' => $sender_name, 'email' => $sender_email ],
            'to'          => [ [ 'email' => $email, 'name' => $first_name ?: $email ] ],
            'subject'     => $subject,
            'htmlContent' => $html,
            'tags'        => [ 'welcome', 'welcome-v1' ],
        ];

        $response = wp_remote_post( 'https://api.brevo.com/v3/smtp/email', [
            'method'  => 'POST',
            'timeout' => 20,
            'headers' => [
                'api-key'      => $api_key,
                'Content-Type' => 'application/json',
                'accept'       => 'application/json',
            ],
            'body' => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'ok' => false, 'message' => $response->get_error_message() ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 ) {
            $msg_id = is_array( $body ) && isset( $body['messageId'] ) ? $body['messageId'] : '';
            return [ 'ok' => true, 'message' => 'Welcome sent to ' . $email . ' (msgId ' . $msg_id . ')' ];
        }

        $msg = is_array( $body ) && isset( $body['message'] ) ? $body['message'] : 'Brevo HTTP ' . $code;
        return [ 'ok' => false, 'message' => $msg ];
    }

    /**
     * Admin "Send test welcome" — sends a copy of the current welcome
     * to the configured test recipient. Same logic as send_welcome_to()
     * but stores the result for the admin status badge.
     *
     * Also clears the tutor photo cache so newly-uploaded NL photos
     * (e.g. hugo-oliveira-vicente-280x280-1.png) are picked up
     * immediately on the next render — bypassing the 1-hour miss cache.
     */
    public static function send_test_welcome(): array {
        self::clear_nl_photo_cache();
        $settings  = get_option( 'edit_seo_fix_settings', [] );
        $recipient = (string) ( $settings['welcome_test_recipient'] ?? self::DEFAULT_SENDER_EMAIL );
        $result    = self::send_welcome_to( $recipient, '' );
        update_option( self::OPT_LAST_SYNC,    time(), false );
        update_option( self::OPT_LAST_STATUS,  $result['ok'] ? 'ok' : 'error', false );
        update_option( self::OPT_LAST_MESSAGE, (string) ( $result['message'] ?? '' ), false );
        return $result;
    }

    /** Wipe every cached NL photo lookup (transients). */
    private static function clear_nl_photo_cache(): void {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_edit_nl_photo_%' OR option_name LIKE '_transient_timeout_edit_nl_photo_%'" );
    }

    /** Backwards-compatibility alias — sync_to_brevo() now sends a test. */
    public static function sync_to_brevo(): array {
        return self::send_test_welcome();
    }

    // ---------------------------------------------------------------- Status

    public static function get_last_sync_status(): array {
        return [
            'time'    => (int) get_option( self::OPT_LAST_SYNC, 0 ),
            'status'  => (string) get_option( self::OPT_LAST_STATUS, '' ),
            'message' => (string) get_option( self::OPT_LAST_MESSAGE, '' ),
        ];
    }
}
