<?php
/**
 * EDIT — First-party engagement analytics for the bootcamp product pages.
 *
 * Three parts:
 *   1. A custom events table ({prefix}edit_bootcamp_events).
 *   2. A public, validated REST collector (POST /wp-json/edit-analytics/v1/track)
 *      that the client tracker (assets/aai-analytics.js) batches events to.
 *   3. A token-gated HTML dashboard at /bootcamp-analytics/?key=<token>&page=<slug>
 *      that aggregates and visualises the data (Chart.js).
 *
 * Privacy: no cookies, no PII. The "visitor" id is a daily-salted hash of
 * IP+UA used only to estimate unique visitors; the session id is a random
 * value the client keeps in sessionStorage (gone when the tab closes).
 *
 * The `page` column keys events by bootcamp slug so the same pipeline serves
 * AAI today and the next product pages later — no schema change per page.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_AAI_Analytics {

	const SCHEMA_VERSION = 1;
	const SCHEMA_OPTION  = 'edit_bootcamp_events_schema';
	const DASH_TOKEN     = 'aai-insights-2026';                 // /bootcamp-analytics/?key=...
	const REST_NS        = 'edit-analytics/v1';
	const MAX_BATCH      = 40;                                  // events per POST
	const RATE_PER_MIN   = 240;                                 // POSTs per IP per minute

	/** event => allowed: keeps the table clean + blocks junk. */
	const EVENTS = [
		'page_view', 'section_view', 'scroll_depth', 'video_play', 'video_complete',
		'cta_click', 'content_click', 'whatsapp_click', 'tool_click', 'logo_click',
		'faq_toggle', 'outbound_click', 'time_on_page',
	];

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'maybe_install' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
		add_action( 'template_redirect', [ __CLASS__, 'maybe_dashboard' ], 0 );
	}

	/* ---------------------------------------------------------------- table */

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'edit_bootcamp_events';
	}

	public static function maybe_install(): void {
		if ( (int) get_option( self::SCHEMA_OPTION ) === self::SCHEMA_VERSION ) return;
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ts DATETIME NOT NULL,
			page VARCHAR(48) NOT NULL DEFAULT 'aai',
			session VARCHAR(40) NOT NULL DEFAULT '',
			visitor CHAR(16) NOT NULL DEFAULT '',
			event VARCHAR(32) NOT NULL DEFAULT '',
			target VARCHAR(190) NOT NULL DEFAULT '',
			section VARCHAR(64) NOT NULL DEFAULT '',
			val INT NOT NULL DEFAULT 0,
			device VARCHAR(12) NOT NULL DEFAULT '',
			meta VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY ev (event),
			KEY ts_idx (ts),
			KEY pg (page),
			KEY sess (session)
		) $charset;" );
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}

	/* ------------------------------------------------------------- collector */

	public static function register_routes(): void {
		register_rest_route( self::REST_NS, '/track', [
			'methods'             => 'POST',
			'permission_callback' => '__return_true',          // public page → anonymous visitors
			'callback'            => [ __CLASS__, 'collect' ],
		] );
	}

	public static function collect( WP_REST_Request $req ) {
		// Same-origin guard (cheap anti-spam; analytics isn't sensitive).
		$origin = (string) $req->get_header( 'origin' );
		if ( $origin !== '' && strpos( $origin, 'weareedit.io' ) === false ) {
			return new WP_REST_Response( [ 'ok' => false ], 403 );
		}
		// Rate-limit per IP.
		$ip  = self::ip();
		$rk  = 'edit_an_rl_' . md5( $ip );
		$hit = (int) get_transient( $rk );
		if ( $hit > self::RATE_PER_MIN ) return new WP_REST_Response( [ 'ok' => false ], 429 );
		set_transient( $rk, $hit + 1, MINUTE_IN_SECONDS );

		$body   = $req->get_json_params();
		$events = is_array( $body['events'] ?? null ) ? $body['events'] : [];
		$page   = self::clean( $body['page'] ?? 'aai', 48 ) ?: 'aai';
		$sess   = self::clean( $body['session'] ?? '', 40 );
		$device = self::clean( $body['device'] ?? '', 12 );
		if ( empty( $events ) ) return [ 'ok' => true, 'stored' => 0 ];

		$visitor = self::visitor_hash( $ip );
		$now     = current_time( 'mysql' );
		$rows    = [];
		foreach ( array_slice( $events, 0, self::MAX_BATCH ) as $e ) {
			$ev = self::clean( $e['event'] ?? '', 32 );
			if ( ! in_array( $ev, self::EVENTS, true ) ) continue;
			$rows[] = [
				'ts'      => $now,
				'page'    => $page,
				'session' => $sess,
				'visitor' => $visitor,
				'event'   => $ev,
				'target'  => self::clean( $e['target'] ?? '', 190 ),
				'section' => self::clean( $e['section'] ?? '', 64 ),
				'val'     => (int) ( $e['val'] ?? 0 ),
				'device'  => $device,
				'meta'    => self::clean( $e['meta'] ?? '', 255 ),
			];
		}
		if ( empty( $rows ) ) return [ 'ok' => true, 'stored' => 0 ];

		global $wpdb;
		$fmt = [ '%s','%s','%s','%s','%s','%s','%s','%d','%s','%s' ];
		foreach ( $rows as $r ) { $wpdb->insert( self::table(), $r, $fmt ); }
		return [ 'ok' => true, 'stored' => count( $rows ) ];
	}

	private static function clean( $v, int $len ): string {
		$v = is_scalar( $v ) ? (string) $v : '';
		$v = trim( preg_replace( '/[\x00-\x1f\x7f]+/', '', $v ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $len ) : substr( $v, 0, $len );
	}

	private static function ip(): string {
		return (string) ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '' );
	}

	/** Daily-salted, truncated hash → anonymous unique-visitor counting (no PII stored). */
	private static function visitor_hash( string $ip ): string {
		$salt = wp_salt( 'nonce' ) . gmdate( 'Y-m-d' );
		$ua   = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		return substr( hash( 'sha256', $ip . '|' . $ua . '|' . $salt ), 0, 16 );
	}

	/* -------------------------------------------------------------- dashboard */

	public static function maybe_dashboard(): void {
		$path = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		if ( $path !== 'bootcamp-analytics' ) return;

		$key = (string) ( $_GET['key'] ?? '' );
		if ( ! current_user_can( 'manage_options' ) && ! hash_equals( self::DASH_TOKEN, $key ) ) {
			status_header( 404 ); nocache_headers();
			echo '<!doctype html><meta name="robots" content="noindex,nofollow"><title>404</title>';
			exit;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo self::render_dashboard();
		exit;
	}

	private static function render_dashboard(): string {
		global $wpdb;
		$t    = self::table();
		$page = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) ( $_GET['page'] ?? 'aai' ) ) ) ?: 'aai';
		$days = max( 1, min( 365, (int) ( $_GET['days'] ?? 30 ) ) );
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$w    = $wpdb->prepare( ' WHERE page=%s AND ts>=%s ', $page, $since );

		$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $t ) );
		if ( ! $exists ) return self::shell( $page, $days, '<p style="padding:40px">No data table yet — instrumentation will create it on first event.</p>' );

		// Headline numbers.
		$sessions = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session) FROM $t $w" );
		$visitors = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT visitor) FROM $t $w" );
		$pviews   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t $w AND event='page_view'" );
		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t $w" );

		// Helper: rows of [label, count] for an event, grouped by a column.
		$grp = function ( string $event, string $col = 'target' ) use ( $wpdb, $t, $w ) {
			$col = in_array( $col, [ 'target', 'section' ], true ) ? $col : 'target';
			$sql = "SELECT $col AS k, COUNT(*) c FROM $t $w AND event=%s AND $col<>'' GROUP BY $col ORDER BY c DESC LIMIT 40";
			return $wpdb->get_results( $wpdb->prepare( $sql, $event ), ARRAY_A ) ?: [];
		};

		$sections = $grp( 'section_view', 'section' );
		$scroll   = $wpdb->get_results( "SELECT val AS k, COUNT(DISTINCT session) c FROM $t $w AND event='scroll_depth' GROUP BY val ORDER BY val", ARRAY_A ) ?: [];
		$videos   = $grp( 'video_play' );
		$ctas     = $grp( 'cta_click' );
		$content  = $grp( 'content_click' );
		$tools    = $grp( 'tool_click' );
		$logos    = $grp( 'logo_click' );
		$wa       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t $w AND event='whatsapp_click'" );
		$faq      = $grp( 'faq_toggle' );
		$devices  = $wpdb->get_results( "SELECT device AS k, COUNT(DISTINCT session) c FROM $t $w AND device<>'' GROUP BY device ORDER BY c DESC", ARRAY_A ) ?: [];
		$daily    = $wpdb->get_results( "SELECT DATE(ts) d, COUNT(DISTINCT session) s, COUNT(*) e FROM $t $w GROUP BY DATE(ts) ORDER BY d", ARRAY_A ) ?: [];

		// Build HTML body.
		$cards = self::stat_cards( [
			[ 'Sessions', $sessions ], [ 'Unique visitors', $visitors ],
			[ 'Page views', $pviews ], [ 'Total events', $total ], [ 'WhatsApp clicks', $wa ],
		] );

		$body  = $cards;
		$body .= self::chart_block( 'Engagement over time', 'line-daily',
			'Sessions and events per day' );
		$body .= '<div class="grid2">';
		$body .= self::bars_block( 'Section reach (area views)', $sections, 'How many sessions saw each section' );
		$body .= self::bars_block( 'Scroll depth', self::scroll_rows( $scroll, $sessions ), 'Sessions reaching each depth', '%' );
		$body .= '</div>';
		$body .= '<div class="grid2">';
		$body .= self::bars_block( 'Video plays', $videos, 'Alumni + studio video opens' );
		$body .= self::bars_block( 'CTA / button clicks', $ctas, 'Inscrever, Fala connosco, etc.' );
		$body .= '</div>';
		$body .= '<div class="grid2">';
		$body .= self::bars_block( 'Content clicks', $content, 'Articles, related courses, FAQ' );
		$body .= self::bars_block( 'Tools clicked', $tools, 'The arsenal / stack items' );
		$body .= '</div>';
		$body .= '<div class="grid2">';
		$body .= self::bars_block( 'Company logos clicked', $logos, 'Alumni employers / partners' );
		$body .= self::bars_block( 'Devices', $devices, 'Sessions by device' );
		$body .= '</div>';

		// Data for the daily line chart.
		$labels = array_map( fn( $r ) => $r['d'], $daily );
		$sData  = array_map( fn( $r ) => (int) $r['s'], $daily );
		$eData  = array_map( fn( $r ) => (int) $r['e'], $daily );
		$json   = wp_json_encode( [ 'labels' => $labels, 'sessions' => $sData, 'events' => $eData ] );
		$body  .= '<script>window.__edDaily=' . $json . ';</script>';

		return self::shell( $page, $days, $body );
	}

	private static function scroll_rows( array $scroll, int $sessions ): array {
		$out = [];
		foreach ( $scroll as $r ) { $out[] = [ 'k' => $r['k'] . '%', 'c' => (int) $r['c'] ]; }
		return $out;
	}

	private static function stat_cards( array $stats ): string {
		$h = '<div class="cards">';
		foreach ( $stats as [$label, $n] ) {
			$h .= '<div class="card"><div class="n">' . number_format_i18n( (int) $n ) . '</div><div class="l">' . esc_html( $label ) . '</div></div>';
		}
		return $h . '</div>';
	}

	private static function bars_block( string $title, array $rows, string $sub = '', string $suffix = '' ): string {
		$max = 0; foreach ( $rows as $r ) { $max = max( $max, (int) $r['c'] ); }
		$h = '<section class="panel"><h2>' . esc_html( $title ) . '</h2>';
		if ( $sub ) $h .= '<p class="sub">' . esc_html( $sub ) . '</p>';
		if ( empty( $rows ) ) { return $h . '<p class="empty">No events yet.</p></section>'; }
		$h .= '<div class="bars">';
		foreach ( $rows as $r ) {
			$pct = $max > 0 ? round( (int) $r['c'] / $max * 100 ) : 0;
			$h  .= '<div class="bar"><span class="bk">' . esc_html( $r['k'] ) . '</span>'
				.  '<span class="bt"><span class="bf" style="width:' . $pct . '%"></span></span>'
				.  '<span class="bn">' . number_format_i18n( (int) $r['c'] ) . esc_html( $suffix ) . '</span></div>';
		}
		return $h . '</div></section>';
	}

	private static function chart_block( string $title, string $id, string $sub = '' ): string {
		return '<section class="panel"><h2>' . esc_html( $title ) . '</h2>'
			. ( $sub ? '<p class="sub">' . esc_html( $sub ) . '</p>' : '' )
			. '<canvas id="' . esc_attr( $id ) . '" height="92"></canvas></section>';
	}

	private static function shell( string $page, int $days, string $body ): string {
		$switch = '';
		foreach ( [ 7, 30, 90 ] as $d ) {
			$cls = $d === $days ? ' on' : '';
			$switch .= '<a class="rng' . $cls . '" href="?page=' . esc_attr( $page ) . '&days=' . $d . '&key=' . self::DASH_TOKEN . '">' . $d . 'd</a>';
		}
		$css = '
		*{box-sizing:border-box}body{margin:0;background:#0e0e12;color:#e9e9ee;font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;font-size:14px}
		.wrap{max-width:1160px;margin:0 auto;padding:28px 22px 80px}
		header.top{display:flex;align-items:baseline;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:22px}
		h1{font-size:22px;margin:0;letter-spacing:-.3px}h1 b{color:#f92869}
		.meta{color:#9a9aa6;font-size:13px}
		.rng{display:inline-block;padding:5px 12px;border:1px solid #2a2a33;border-radius:6px;color:#cfcfd6;text-decoration:none;margin-left:6px}
		.rng.on{background:#f92869;border-color:#f92869;color:#fff}
		.cards{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:18px}
		.card{background:#16161d;border:1px solid #23232c;border-radius:10px;padding:16px 18px}
		.card .n{font-size:26px;font-weight:800;letter-spacing:-.5px}.card .l{color:#9a9aa6;font-size:12px;margin-top:3px}
		.panel{background:#16161d;border:1px solid #23232c;border-radius:10px;padding:18px 20px;margin-bottom:16px}
		.panel h2{font-size:15px;margin:0 0 2px}.panel .sub{color:#9a9aa6;font-size:12px;margin:0 0 14px}
		.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
		.bars{display:flex;flex-direction:column;gap:9px}
		.bar{display:grid;grid-template-columns:180px 1fr 60px;align-items:center;gap:10px;font-size:13px}
		.bk{color:#c7c7d0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
		.bt{background:#23232c;border-radius:5px;height:18px;overflow:hidden}
		.bf{display:block;height:100%;background:linear-gradient(90deg,#f92869,#7a38b4)}
		.bn{text-align:right;font-variant-numeric:tabular-nums;color:#e9e9ee;font-weight:700}
		.empty{color:#6e6e78;font-size:13px}
		@media(max-width:820px){.cards{grid-template-columns:repeat(2,1fr)}.grid2{grid-template-columns:1fr}.bar{grid-template-columns:130px 1fr 52px}}
		';
		return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Bootcamp Analytics — ' . esc_html( $page ) . '</title>'
			. '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>'
			. '<style>' . $css . '</style></head><body><div class="wrap">'
			. '<header class="top"><div><h1>Bootcamp Analytics <b>' . esc_html( strtoupper( $page ) ) . '</b></h1>'
			. '<div class="meta">Last ' . $days . ' days · first-party · no cookies</div></div>'
			. '<div>' . $switch . '</div></header>'
			. $body
			. '<script>(function(){var d=window.__edDaily;if(!d||!window.Chart)return;var c=document.getElementById("line-daily");if(!c)return;'
			. 'new Chart(c,{type:"line",data:{labels:d.labels,datasets:['
			. '{label:"Sessions",data:d.sessions,borderColor:"#f92869",backgroundColor:"rgba(249,40,105,.12)",fill:true,tension:.3},'
			. '{label:"Events",data:d.events,borderColor:"#60c5b3",backgroundColor:"rgba(96,197,179,.10)",fill:true,tension:.3}]},'
			. 'options:{plugins:{legend:{labels:{color:"#c7c7d0"}}},scales:{x:{ticks:{color:"#9a9aa6"},grid:{color:"#23232c"}},y:{ticks:{color:"#9a9aa6"},grid:{color:"#23232c"},beginAtZero:true}}}});})();</script>'
			. '</div></body></html>';
	}
}
