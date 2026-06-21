<?php
/**
 * Bootcamps Inbound -> Brevo CRM.
 *
 * Hooks CF7 `wpcf7_mail_sent` on bootcamp course pages and creates a Brevo
 * contact + deal in the "Bootcamps Inbound" pipeline (auto-discovered by name,
 * cached 12h), attaching full lead attribution as a deal note.
 *
 * FAIL-SAFE BY DESIGN:
 *   - Runs AFTER the form mail is sent, so it can never block/break a submit.
 *   - Whole handler wrapped in try/catch; any error is logged, never thrown.
 *   - Flip ENABLED to false to disable the integration instantly.
 *
 * SETUP (one-time, in Brevo): create a Sales pipeline named exactly
 * "Bootcamps Inbound" with a first stage "Novo Lead". No code change needed.
 *
 * @package weareedit-site-engine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EDIT_Brevo_Bootcamp_Inbound {

	/** Master on/off switch. Set to false to disable the whole integration. */
	const ENABLED = true;

	/** Brevo pipeline + first-stage names (matched case-insensitively by name). */
	const PIPELINE_NAME = 'Bootcamps Inbound';
	const STAGE_NAME    = 'Novo Lead';

	/** Course slugs whose lead forms feed this pipeline (extensible). */
	const SLUGS = array( 'bootcamp-advanced-artificial-intelligence' );

	/** Deal value (EUR) stamped on the deal. */
	const DEAL_AMOUNT = 750;

	public static function init(): void {
		if ( ! self::ENABLED ) { return; }
		add_action( 'wpcf7_mail_sent', array( __CLASS__, 'on_mail_sent' ), 20, 1 );
	}

	public static function on_mail_sent( $contact_form ): void {
		try {
			self::handle();
		} catch ( \Throwable $e ) {
			self::log( 'exception: ' . $e->getMessage() );
		}
	}

	private static function handle(): void {
		if ( ! class_exists( 'WPCF7_Submission' ) ) { return; }
		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) { return; }

		$url = (string) $submission->get_meta( 'url' );

		// Scope strictly to bootcamp course pages.
		$matched = false;
		foreach ( self::SLUGS as $slug ) {
			if ( strpos( $url, $slug ) !== false ) { $matched = true; break; }
		}
		if ( ! $matched ) { return; }

		$data = $submission->get_posted_data();
		if ( ! is_array( $data ) ) { return; }

		$email = self::field( $data, 'your-email' );
		$name  = self::field( $data, 'your-name' );

		// Only true lead forms (have name + valid email) — skips newsletter forms.
		if ( $email === '' || ! is_email( $email ) || $name === '' ) { return; }

		$api_key = trim( (string) ( ( (array) get_option( 'edit_seo_fix_settings', array() ) )['brevo_api_key'] ?? '' ) );
		if ( $api_key === '' ) { self::log( 'no_api_key' ); return; }

		$contact_id = self::upsert_contact( $api_key, $email, $name );

		$ids = self::discover_pipeline( $api_key );
		if ( ! $ids ) { self::log( 'pipeline_not_found (create "' . self::PIPELINE_NAME . '" in Brevo)' ); return; }

		$deal_id = self::create_deal( $api_key, 'AAI Bootcamp · ' . $name, $ids, $contact_id );
		if ( $deal_id === '' ) { self::log( 'deal_create_failed' ); return; }

		self::add_note( $api_key, $deal_id, $data, $url );
		self::log( 'ok deal=' . $deal_id . ' contact=' . $contact_id . ' email=' . $email );
	}

	private static function field( array $data, string $key ): string {
		$v = $data[ $key ] ?? '';
		if ( is_array( $v ) ) { $v = implode( ', ', $v ); }
		return trim( (string) $v );
	}

	/** Phone can arrive as your-phone or a CF7 tel-NNN field — accept either. */
	private static function phone( array $data ): string {
		$p = self::field( $data, 'your-phone' );
		if ( $p !== '' ) { return $p; }
		foreach ( $data as $k => $v ) {
			if ( strpos( (string) $k, 'tel-' ) === 0 ) {
				$val = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
				if ( trim( $val ) !== '' ) { return trim( $val ); }
			}
		}
		return '';
	}

	/** Upsert contact via /v3/contacts. Returns Brevo contact ID (0 on failure). */
	private static function upsert_contact( string $api_key, string $email, string $name ): int {
		$res = wp_remote_post( 'https://api.brevo.com/v3/contacts', array(
			'timeout' => 8,
			'headers' => array( 'api-key' => $api_key, 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'email'         => $email,
				'attributes'    => array( 'FIRSTNAME' => $name ),
				'updateEnabled' => true,
			) ),
		) );
		if ( is_wp_error( $res ) ) { return 0; }
		$rb = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( is_array( $rb ) && ! empty( $rb['id'] ) ) { return (int) $rb['id']; }
		// Existing contact (204, empty body) -> look up by email.
		$look = wp_remote_get( 'https://api.brevo.com/v3/contacts/' . rawurlencode( $email ), array(
			'timeout' => 8,
			'headers' => array( 'api-key' => $api_key, 'Accept' => 'application/json' ),
		) );
		if ( ! is_wp_error( $look ) && wp_remote_retrieve_response_code( $look ) === 200 ) {
			$lb = json_decode( wp_remote_retrieve_body( $look ), true );
			if ( is_array( $lb ) && ! empty( $lb['id'] ) ) { return (int) $lb['id']; }
		}
		return 0;
	}

	/** Discover "Bootcamps Inbound" pipeline + first stage by name (cached 12h). */
	private static function discover_pipeline( string $api_key ): ?array {
		$cache_key = 'edit_bootcamps_brevo_pipeline_ids';
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && ! empty( $cached['pipeline'] ) && ! empty( $cached['stage'] ) ) { return $cached; }

		$res = wp_remote_get( 'https://api.brevo.com/v3/crm/pipeline/details/all', array(
			'timeout' => 8,
			'headers' => array( 'api-key' => $api_key, 'Accept' => 'application/json' ),
		) );
		if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) { return null; }
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) ) { return null; }
		$pipelines = isset( $body[0] ) ? $body : ( $body['items'] ?? $body['pipelines'] ?? array() );
		if ( ! is_array( $pipelines ) ) { return null; }

		foreach ( $pipelines as $p ) {
			$pname = $p['pipeline_name'] ?? $p['name'] ?? '';
			if ( strcasecmp( trim( (string) $pname ), self::PIPELINE_NAME ) !== 0 ) { continue; }
			$pid    = (string) ( $p['pipeline'] ?? $p['id'] ?? '' );
			$stages = $p['stages'] ?? $p['deal_stages'] ?? array();
			if ( $pid === '' || ! is_array( $stages ) || ! $stages ) { return null; }
			$stage_id = '';
			foreach ( $stages as $st ) {
				$sid = (string) ( $st['id'] ?? '' );
				if ( $sid === '' ) { continue; }
				if ( strcasecmp( trim( (string) ( $st['name'] ?? '' ) ), self::STAGE_NAME ) === 0 ) { $stage_id = $sid; break; }
				if ( $stage_id === '' ) { $stage_id = $sid; }
			}
			if ( $stage_id === '' ) { return null; }
			$ids = array( 'pipeline' => $pid, 'stage' => $stage_id );
			set_transient( $cache_key, $ids, 12 * HOUR_IN_SECONDS );
			return $ids;
		}
		return null;
	}

	/** Create deal via /v3/crm/deals. Returns deal ID ('' on failure). */
	private static function create_deal( string $api_key, string $name, array $ids, int $contact_id ): string {
		$payload = array(
			'name'       => $name,
			'attributes' => array(
				'pipeline'   => $ids['pipeline'],
				'deal_stage' => $ids['stage'],
				'amount'     => self::DEAL_AMOUNT,
			),
		);
		if ( $contact_id > 0 ) { $payload['linkedContactsIds'] = array( $contact_id ); }

		$res = wp_remote_post( 'https://api.brevo.com/v3/crm/deals', array(
			'timeout' => 8,
			'headers' => array( 'api-key' => $api_key, 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
			'body'    => wp_json_encode( $payload ),
		) );
		if ( is_wp_error( $res ) ) { return ''; }
		$code = wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			self::log( 'deal_http_' . $code . ': ' . mb_substr( (string) wp_remote_retrieve_body( $res ), 0, 120 ) );
			return '';
		}
		$rb = json_decode( wp_remote_retrieve_body( $res ), true );
		return ( is_array( $rb ) && ! empty( $rb['id'] ) ) ? (string) $rb['id'] : '';
	}

	/** Attach lead attribution (phone/city/LinkedIn/promo/message/UTM) as a deal note. */
	private static function add_note( string $api_key, string $deal_id, array $data, string $url ): void {
		$lines = array();
		$pairs = array(
			'Telefone' => self::phone( $data ),
			'Cidade'   => self::field( $data, 'your-location' ),
			'LinkedIn' => self::field( $data, 'your-linkedin' ),
			'Código'   => self::field( $data, 'your-code' ),
			'Mensagem' => self::field( $data, 'your-message' ),
		);
		foreach ( $pairs as $k => $v ) {
			if ( $v !== '' ) { $lines[] = $k . ': ' . $v; }
		}
		$q = wp_parse_url( $url, PHP_URL_QUERY );
		if ( $q ) {
			parse_str( $q, $params );
			$utm = array();
			foreach ( (array) $params as $k => $v ) {
				if ( strpos( (string) $k, 'utm_' ) === 0 ) { $utm[] = $k . '=' . ( is_array( $v ) ? implode( ',', $v ) : $v ); }
			}
			if ( $utm ) { $lines[] = 'UTM: ' . implode( ' · ', $utm ); }
		}
		$lines[] = 'Origem: ' . $url;
		$text    = "Lead AAI Bootcamp (formulário do site)\n" . implode( "\n", $lines );

		wp_remote_post( 'https://api.brevo.com/v3/crm/notes', array(
			'timeout' => 8,
			'headers' => array( 'api-key' => $api_key, 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
			'body'    => wp_json_encode( array( 'text' => $text, 'dealIds' => array( $deal_id ) ) ),
		) );
	}

	private static function log( string $msg ): void {
		error_log( '[EDIT Bootcamps Inbound] ' . $msg );
	}
}
