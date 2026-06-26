<?php
/**
 * EDIT — Search Insights.
 *
 * Logs every on-site search query (server-side, at the point results are
 * computed in EDIT_Search_Ajax::handle) and surfaces them in a token-gated
 * dashboard. The goal (Daniel, 2026-06-26): "knowing what users want is key."
 * The headline signal is ZERO-RESULT searches — people telling us which
 * courses to build and which content to rank for ("Sem cursos para X").
 *
 * Why server-side, not a JS hook:
 *   - The search handler already knows the exact result count, so the
 *     zero-result flag is 100% accurate (no scraping "Sem cursos para").
 *   - Both the fresh path and the transient-cache path flow through handle(),
 *     so popular repeat queries are still counted.
 *
 * Instant-search noise: the autocomplete fires on every debounced keystroke
 * ("cl" -> "cla" -> ... -> "claude design"). We collapse a typing burst into
 * one row per session: if the same session searched a prefix/extension of the
 * new query in the last 25s, we UPDATE that row to the latest state instead of
 * inserting. The log therefore records user *intent*, not every keystroke.
 *
 * Privacy: no cookies, no PII. "session" is an hour-salted hash of IP+UA used
 * only to collapse a single person's typing burst.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Search_Insights {

	const SCHEMA_VERSION = 1;
	const SCHEMA_OPTION  = 'edit_search_log_schema';
	const MIN_LEN        = 2;     // matches EDIT_Search_Ajax minimum
	const MAX_LEN        = 120;   // truncate junk/paste
	const BURST_WINDOW   = 25;    // seconds — collapse keystrokes within this
	// Dashboard access: logged-in admins always; OPTIONAL shareable link works
	// only if EDIT_ANALYTICS_TOKEN is defined in wp-config.php (shared with the
	// bootcamp dashboard). Never hardcode a token — this repo is public.

	public static function init(): void {
		self::maybe_install();   // already on the 'init' hook
		add_action( 'template_redirect', [ __CLASS__, 'maybe_dashboard' ], 0 );
	}

	/* ---------------------------------------------------------------- table */

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'edit_search_log';
	}

	public static function maybe_install(): void {
		if ( (int) get_option( self::SCHEMA_OPTION ) === self::SCHEMA_VERSION ) return;
		self::create_table();
	}

	private static function ensure_table(): bool {
		global $wpdb;
		$sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', self::table() );
		if ( (bool) $wpdb->get_var( $sql ) ) return true;
		self::create_table();
		return (bool) $wpdb->get_var( $sql );
	}

	private static function create_table(): void {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			query VARCHAR(150) NOT NULL DEFAULT '',
			query_norm VARCHAR(150) NOT NULL DEFAULT '',
			results SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			is_zero TINYINT(1) NOT NULL DEFAULT 0,
			url VARCHAR(190) NOT NULL DEFAULT '',
			session CHAR(24) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY norm (query_norm),
			KEY zero (is_zero),
			KEY created (created_at),
			KEY sess (session)
		) $charset;" );
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}

	/* ----------------------------------------------------------------- log */

	/**
	 * Record one search. Called from EDIT_Search_Ajax::handle on every query.
	 * Must never throw or interfere with the search response.
	 *
	 * @param string $keyword Raw query as typed.
	 * @param int    $results Result count (capped at MAX_RESULTS by the handler;
	 *                        only 0 vs >0 is relied upon).
	 * @param string $url     Referring page URL.
	 */
	public static function log( string $keyword, int $results, string $url = '' ): void {
		$keyword = self::clean( $keyword, self::MAX_LEN );
		if ( mb_strlen( $keyword ) < self::MIN_LEN ) return;
		if ( ! self::ensure_table() ) return;

		global $wpdb;
		$t       = self::table();
		$norm    = self::normalize( $keyword );
		$is_zero = $results <= 0 ? 1 : 0;
		$session = self::session_hash();
		$now     = current_time( 'mysql' );
		$url     = self::clean( $url, 190 );

		// Collapse a typing burst: reuse the session's most recent row if the
		// new query is a prefix/extension of it (latest state wins).
		$prev = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, query_norm FROM $t WHERE session=%s AND created_at >= %s ORDER BY id DESC LIMIT 1",
			$session,
			gmdate( 'Y-m-d H:i:s', time() - self::BURST_WINDOW )
		), ARRAY_A );

		if ( $prev ) {
			$a = (string) $prev['query_norm'];
			$b = $norm;
			$related = ( $a !== '' && ( strpos( $b, $a ) === 0 || strpos( $a, $b ) === 0 ) );
			if ( $related ) {
				$wpdb->update(
					$t,
					[ 'query' => $keyword, 'query_norm' => $norm, 'results' => max( 0, $results ), 'is_zero' => $is_zero, 'url' => $url, 'created_at' => $now ],
					[ 'id' => (int) $prev['id'] ],
					[ '%s', '%s', '%d', '%d', '%s', '%s' ],
					[ '%d' ]
				);
				return;
			}
		}

		$wpdb->insert(
			$t,
			[ 'created_at' => $now, 'query' => $keyword, 'query_norm' => $norm, 'results' => max( 0, $results ), 'is_zero' => $is_zero, 'url' => $url, 'session' => $session ],
			[ '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
		);
	}

	private static function normalize( string $s ): string {
		$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $s ) ) : strtolower( trim( $s ) );
		return preg_replace( '/\s+/', ' ', $s );
	}

	private static function clean( $v, int $len ): string {
		$v = is_scalar( $v ) ? (string) $v : '';
		$v = trim( preg_replace( '/[\x00-\x1f\x7f]+/', '', $v ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $len ) : substr( $v, 0, $len );
	}

	/** Hour-salted, truncated hash of IP+UA — collapses one person's burst, stores no PII. */
	private static function session_hash(): string {
		$ip   = (string) ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '' );
		$ua   = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		$salt = wp_salt( 'nonce' ) . gmdate( 'Y-m-d-H' );
		return substr( hash( 'sha256', $ip . '|' . $ua . '|' . $salt ), 0, 24 );
	}

	/* -------------------------------------------------------------- dashboard */

	public static function maybe_dashboard(): void {
		$path = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		if ( $path !== 'search-insights' ) return;

		$ok  = current_user_can( 'manage_options' );
		$key = (string) ( $_GET['key'] ?? '' );
		if ( ! $ok && defined( 'EDIT_ANALYTICS_TOKEN' ) && EDIT_ANALYTICS_TOKEN !== '' && $key !== '' ) {
			$ok = hash_equals( (string) EDIT_ANALYTICS_TOKEN, $key );
		}
		if ( ! $ok ) {
			status_header( 404 ); nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow', true );
			echo '<!doctype html><meta name="robots" content="noindex,nofollow"><title>Em breve</title>';
			exit;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );

		if ( ( $_GET['export'] ?? '' ) === 'csv' ) { self::export_csv(); exit; }

		header( 'Content-Type: text/html; charset=UTF-8' );
		echo self::render_dashboard();
		exit;
	}

	private static function range(): array {
		$days  = max( 1, min( 365, (int) ( $_GET['days'] ?? 30 ) ) );
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		return [ $days, $since ];
	}

	private static function export_csv(): void {
		global $wpdb;
		$t = self::table();
		if ( ! self::ensure_table() ) { header( 'Content-Type: text/plain' ); echo 'no data'; return; }
		[ $days, $since ] = self::range();
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT MAX(query) q, COUNT(*) searches, SUM(is_zero) zero_results, MAX(created_at) last_seen
			 FROM $t WHERE created_at>=%s GROUP BY query_norm ORDER BY searches DESC, zero_results DESC",
			$since
		), ARRAY_A ) ?: [];
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="edit-search-' . $days . 'd.csv"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'query', 'searches', 'zero_results', 'last_seen' ] );
		foreach ( $rows as $r ) {
			fputcsv( $out, [ $r['q'], (int) $r['searches'], (int) $r['zero_results'], $r['last_seen'] ] );
		}
		fclose( $out );
	}

	private static function render_dashboard(): string {
		global $wpdb;
		$t = self::table();
		[ $days, $since ] = self::range();

		if ( ! self::ensure_table() ) {
			return self::shell( $days, '<p class="empty" style="padding:40px">Could not create the search-log table (check DB permissions). Once a search runs, data appears here.</p>' );
		}

		$w = $wpdb->prepare( ' WHERE created_at>=%s ', $since );

		$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t $w" );
		$unique  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT query_norm) FROM $t $w" );
		$zeros   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t $w AND is_zero=1" );
		$zrate   = $total > 0 ? round( $zeros / $total * 100 ) : 0;
		$since7  = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
		$last7   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE created_at>=%s", $since7 ) );

		// Zero-result queries — the demand gaps (the headline insight).
		$zeroRows = $wpdb->get_results(
			"SELECT MAX(query) k, COUNT(*) c FROM $t $w AND is_zero=1 AND query_norm<>'' GROUP BY query_norm ORDER BY c DESC, MAX(created_at) DESC LIMIT 40",
			ARRAY_A
		) ?: [];

		// Top searches overall, with how often they returned nothing.
		$topRows = $wpdb->get_results(
			"SELECT MAX(query) k, COUNT(*) c, SUM(is_zero) z FROM $t $w AND query_norm<>'' GROUP BY query_norm ORDER BY c DESC, MAX(created_at) DESC LIMIT 40",
			ARRAY_A
		) ?: [];

		// Daily trend: total vs zero-result.
		$daily = $wpdb->get_results(
			"SELECT DATE(created_at) d, COUNT(*) c, SUM(is_zero) z FROM $t $w GROUP BY DATE(created_at) ORDER BY d",
			ARRAY_A
		) ?: [];

		// Latest searches (spot-check).
		$latest = $wpdb->get_results(
			"SELECT query, results, is_zero, created_at FROM $t $w ORDER BY id DESC LIMIT 30",
			ARRAY_A
		) ?: [];

		$body  = self::stat_cards( [
			[ 'Total searches', $total ],
			[ 'Unique queries', $unique ],
			[ 'Zero-result', $zeros ],
			[ 'Zero-result rate', $zrate . '%' ],
			[ 'Last 7 days', $last7 ],
		] );

		$body .= self::chart_block( 'Searches over time', 'line-daily', 'Total searches and zero-result searches per day' );

		$body .= self::bars( '🔎 Zero-result searches — demand gaps', $zeroRows,
			'Queries that returned nothing. Each is a course or content opportunity.', '#f92869' );

		$topFmt = array_map( function ( $r ) {
			$z = (int) $r['z'];
			$label = $r['k'] . ( $z > 0 ? '  ·  ' . $z . ' empty' : '' );
			return [ 'k' => $label, 'c' => (int) $r['c'] ];
		}, $topRows );
		$body .= self::bars( 'Top searches', $topFmt, 'Most frequent queries (collapsed per typing burst)', '#60c5b3' );

		$body .= self::latest_table( $latest );

		$labels = array_map( fn( $r ) => $r['d'], $daily );
		$cAll   = array_map( fn( $r ) => (int) $r['c'], $daily );
		$cZero  = array_map( fn( $r ) => (int) $r['z'], $daily );
		$json   = wp_json_encode( [ 'labels' => $labels, 'all' => $cAll, 'zero' => $cZero ] );
		$body  .= '<script>window.__edSearch=' . $json . ';</script>';

		return self::shell( $days, $body );
	}

	private static function stat_cards( array $stats ): string {
		$h = '<div class="cards">';
		foreach ( $stats as [$label, $n] ) {
			$val = is_int( $n ) ? number_format_i18n( $n ) : esc_html( (string) $n );
			$h  .= '<div class="card"><div class="n">' . $val . '</div><div class="l">' . esc_html( $label ) . '</div></div>';
		}
		return $h . '</div>';
	}

	private static function bars( string $title, array $rows, string $sub, string $color ): string {
		$max = 0; foreach ( $rows as $r ) { $max = max( $max, (int) $r['c'] ); }
		$h = '<section class="panel"><h2>' . esc_html( $title ) . '</h2>';
		if ( $sub ) $h .= '<p class="sub">' . esc_html( $sub ) . '</p>';
		if ( empty( $rows ) ) return $h . '<p class="empty">No searches in this range yet.</p></section>';
		$h .= '<div class="bars">';
		foreach ( $rows as $r ) {
			$pct = $max > 0 ? round( (int) $r['c'] / $max * 100 ) : 0;
			$h  .= '<div class="bar"><span class="bk">' . esc_html( $r['k'] ) . '</span>'
				.  '<span class="bt"><span class="bf" style="width:' . $pct . '%;background:' . esc_attr( $color ) . '"></span></span>'
				.  '<span class="bn">' . number_format_i18n( (int) $r['c'] ) . '</span></div>';
		}
		return $h . '</div></section>';
	}

	private static function latest_table( array $rows ): string {
		$h = '<section class="panel"><h2>Latest searches</h2><p class="sub">Most recent 30 queries (live spot-check)</p>';
		if ( empty( $rows ) ) return $h . '<p class="empty">Nothing yet.</p></section>';
		$h .= '<table class="tbl"><thead><tr><th>Query</th><th>Results</th><th>When</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$zero = (int) $r['is_zero'] === 1;
			$res  = $zero ? '<span class="z">0</span>' : esc_html( (string) (int) $r['results'] );
			$h   .= '<tr><td>' . esc_html( $r['query'] ) . '</td><td>' . $res . '</td><td class="t">' . esc_html( $r['created_at'] ) . '</td></tr>';
		}
		return $h . '</tbody></table></section>';
	}

	private static function chart_block( string $title, string $id, string $sub = '' ): string {
		return '<section class="panel"><h2>' . esc_html( $title ) . '</h2>'
			. ( $sub ? '<p class="sub">' . esc_html( $sub ) . '</p>' : '' )
			. '<canvas id="' . esc_attr( $id ) . '" height="92"></canvas></section>';
	}

	private static function shell( int $days, string $body ): string {
		$key  = (string) ( $_GET['key'] ?? '' );
		$keyq = $key !== '' ? '&key=' . rawurlencode( $key ) : '';
		$switch = '';
		foreach ( [ 7, 30, 90 ] as $d ) {
			$cls = $d === $days ? ' on' : '';
			$switch .= '<a class="rng' . $cls . '" href="?days=' . $d . $keyq . '">' . $d . 'd</a>';
		}
		$csv = '<a class="rng" href="?days=' . $days . '&export=csv' . $keyq . '">Export CSV</a>';
		$css = '
		*{box-sizing:border-box}body{margin:0;background:#0e0e12;color:#e9e9ee;font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;font-size:14px}
		.wrap{max-width:1160px;margin:0 auto;padding:28px 22px 80px}
		header.top{display:flex;align-items:baseline;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:22px}
		h1{font-size:22px;margin:0;letter-spacing:-.3px}h1 b{color:#60c5b3}
		.meta{color:#9a9aa6;font-size:13px}
		.rng{display:inline-block;padding:5px 12px;border:1px solid #2a2a33;border-radius:6px;color:#cfcfd6;text-decoration:none;margin-left:6px}
		.rng.on{background:#60c5b3;border-color:#60c5b3;color:#06231f}
		.cards{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:18px}
		.card{background:#16161d;border:1px solid #23232c;border-radius:10px;padding:16px 18px}
		.card .n{font-size:26px;font-weight:800;letter-spacing:-.5px}.card .l{color:#9a9aa6;font-size:12px;margin-top:3px}
		.panel{background:#16161d;border:1px solid #23232c;border-radius:10px;padding:18px 20px;margin-bottom:16px}
		.panel h2{font-size:15px;margin:0 0 2px}.panel .sub{color:#9a9aa6;font-size:12px;margin:0 0 14px}
		.bars{display:flex;flex-direction:column;gap:9px}
		.bar{display:grid;grid-template-columns:300px 1fr 60px;align-items:center;gap:10px;font-size:13px}
		.bk{color:#c7c7d0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
		.bt{background:#23232c;border-radius:5px;height:18px;overflow:hidden}
		.bf{display:block;height:100%}
		.bn{text-align:right;font-variant-numeric:tabular-nums;color:#e9e9ee;font-weight:700}
		.empty{color:#6e6e78;font-size:13px}
		.tbl{width:100%;border-collapse:collapse;font-size:13px}
		.tbl th{text-align:left;color:#9a9aa6;font-weight:600;padding:7px 10px;border-bottom:1px solid #23232c}
		.tbl td{padding:7px 10px;border-bottom:1px solid #1c1c24;color:#d7d7de}
		.tbl td.t{color:#8a8a95;font-variant-numeric:tabular-nums;white-space:nowrap}
		.tbl .z{color:#f92869;font-weight:700}
		@media(max-width:820px){.cards{grid-template-columns:repeat(2,1fr)}.bar{grid-template-columns:150px 1fr 48px}}
		';
		return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Search Insights — EDIT.</title>'
			. '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>'
			. '<style>' . $css . '</style></head><body><div class="wrap">'
			. '<header class="top"><div><h1>Search <b>Insights</b></h1>'
			. '<div class="meta">Last ' . $days . ' days · on-site search · first-party · no cookies</div></div>'
			. '<div>' . $switch . $csv . '</div></header>'
			. $body
			. '<script>(function(){var d=window.__edSearch;if(!d||!window.Chart)return;var c=document.getElementById("line-daily");if(!c)return;'
			. 'new Chart(c,{type:"line",data:{labels:d.labels,datasets:['
			. '{label:"Searches",data:d.all,borderColor:"#60c5b3",backgroundColor:"rgba(96,197,179,.12)",fill:true,tension:.3},'
			. '{label:"Zero-result",data:d.zero,borderColor:"#f92869",backgroundColor:"rgba(249,40,105,.10)",fill:true,tension:.3}]},'
			. 'options:{plugins:{legend:{labels:{color:"#c7c7d0"}}},scales:{x:{ticks:{color:"#9a9aa6"},grid:{color:"#23232c"}},y:{ticks:{color:"#9a9aa6"},grid:{color:"#23232c"},beginAtZero:true}}}});})();</script>'
			. '</div></body></html>';
	}
}
