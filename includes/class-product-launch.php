<?php
/**
 * EDIT_Product_Launch — streamlined "new product" launch surface.
 * ─────────────────────────────────────────────────────────────────────────────
 * One generic renderer for every new product (bootcamp/course) landing page.
 * Each product is a single data file in includes/data/products/*.php that returns
 * a config array (content + SEO/GEO). This class discovers them, routes a
 * token-gated preview at /<slug>-preview/, and (when status='live') serves the
 * same page at the product's live_path with full schema + index,follow.
 *
 * Add a new product = drop one config file. No new PHP, no new template.
 *
 *   preview : /<slug>-preview/?preview=<token>   → noindex, admins/token only
 *   live    : <live_path>                        → index,follow + Course/FAQ schema
 *
 * Visuals + SEO head live in templates/product-launch.php.
 *
 * @since 1.5.817
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Product_Launch {

	const DATA_DIR = 'includes/data/products/';
	const TEMPLATE = 'includes/templates/product-launch.php';

	/** Load every product config keyed by slug. */
	public static function products(): array {
		$out = [];
		foreach ( (array) glob( WEAREDIT_SITE_ENGINE_PATH . self::DATA_DIR . '*.php' ) as $file ) {
			$cfg = include $file;
			if ( is_array( $cfg ) && ! empty( $cfg['slug'] ) ) {
				$out[ $cfg['slug'] ] = $cfg;
			}
		}
		return $out;
	}

	public static function init(): void {
		add_action( 'template_redirect', [ __CLASS__, 'maybe_render' ], 0 );
	}

	public static function maybe_render(): void {
		$path = rtrim( (string) parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
		if ( $path === '' ) return;

		// Cheap gate: only touch product configs on routes that could match a
		// product (preview surface or a /formacao/ live path). Keeps the config
		// include off the homepage + every other request — perf and a blast-radius
		// guard so a bad config never affects unrelated pages.
		$is_preview = substr( $path, -8 ) === '-preview';
		if ( ! $is_preview && strpos( $path, '/formacao/' ) !== 0 ) return;

		foreach ( self::products() as $slug => $cfg ) {
			$status = $cfg['status'] ?? 'preview';
			if ( $status === 'off' ) continue;

			// Preview surface — always available (any status except off), token-gated.
			if ( $path === '/' . $slug . '-preview' ) {
				self::render( $cfg, 'preview' );
				return;
			}
			// Live surface — only when explicitly flipped live.
			if ( $status === 'live' && ! empty( $cfg['live_path'] ) && $path === rtrim( $cfg['live_path'], '/' ) ) {
				self::render( $cfg, 'live' );
				return;
			}
		}
	}

	private static function authorized( array $cfg ): bool {
		if ( current_user_can( 'manage_options' ) ) return true;
		$token = isset( $_GET['preview'] ) ? sanitize_text_field( wp_unslash( $_GET['preview'] ) ) : '';
		return $token !== '' && hash_equals( (string) ( $cfg['preview_token'] ?? '' ), $token );
	}

	private static function render( array $cfg, string $mode ): void {
		// Never cache: previews change every release; live re-splices fresh chrome.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );

		// Preview is private: unauthorized visitors + all crawlers get a noindex 404.
		if ( $mode === 'preview' && ! self::authorized( $cfg ) ) {
			status_header( 404 );
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow', true );
			header( 'Content-Type: text/html; charset=UTF-8' );
			echo '<!doctype html><meta name="robots" content="noindex,nofollow"><title>Em breve</title><p style="font-family:system-ui;text-align:center;padding:80px 20px;color:#666">Página em construção.</p>';
			exit;
		}

		$cfg['_mode'] = $mode;

		// Render the self-contained rich page (this is also the safe fallback).
		$full = self::render_template( $cfg );
		if ( $full === '' ) { status_header( 500 ); echo 'Unavailable.'; exit; }

		// Prefer: splice our sections into the real proxied header/footer chrome.
		// Any failure → serve $full as-is (self-contained). Can never white-screen.
		$out = self::with_chrome( $full, $cfg, ( $mode === 'live' ) );
		if ( $out === '' ) $out = $full;

		status_header( 200 );
		if ( $mode === 'preview' ) {
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo $out;
		exit;
	}

	private static function render_template( array $cfg ): string {
		$tpl = WEAREDIT_SITE_ENGINE_PATH . self::TEMPLATE;
		if ( ! is_readable( $tpl ) ) return '';
		$C = $cfg; // exposed to template
		ob_start();
		include $tpl;
		return (string) ob_get_clean();
	}

	/**
	 * Splice the template's body sections (between PRD:SECTIONS markers) into a
	 * real EDIT page proxied for chrome (header/footer/theme CSS). Returns '' on
	 * any failure so the caller falls back to the self-contained page.
	 */
	private static function with_chrome( string $full, array $cfg, bool $is_live ): string {
		$donor_url = $cfg['donor_url'] ?? '';
		if ( $donor_url === '' ) return '';

		// Extract our sections.
		$s = strpos( $full, '<!--PRD:SECTIONS-START-->' );
		$e = strpos( $full, '<!--PRD:SECTIONS-END-->' );
		if ( $s === false || $e === false || $e <= $s ) return '';
		$sections = substr( $full, $s, $e - $s );

		// Fetch raw donor chrome (?edit_src=raw → our AAI/GAMA hooks bail, WP Rocket bypasses).
		$donor = self::fetch_donor( $donor_url );
		if ( $donor === '' ) return '';

		$cut  = strpos( $donor, '<section' );
		$foot = strpos( $donor, '<footer' );
		if ( $cut === false || $foot === false || $foot <= $cut ) return '';
		$top    = substr( $donor, 0, $cut );
		$footer = substr( $donor, $foot );

		// Swap the donor's <head> SEO (title/canonical/og) for ours; add robots + JSON-LD.
		$top = preg_replace( '#<title\b[^>]*>.*?</title>#is', '', $top, 1 );
		$top = preg_replace( '#<link\b[^>]*rel=["\']canonical["\'][^>]*>#i', '', $top );
		$top = preg_replace( '#<meta\b[^>]*property=["\']og:(title|description|url)["\'][^>]*>#i', '', $top );
		$head_inject = self::seo_head( $cfg, $is_live );
		$top = preg_replace( '/<head[^>]*>/i', '$0' . "\n" . str_replace( '$', '\$', $head_inject ), $top, 1 );

		// Balance any wrappers left open by cutting the donor body.
		$open = substr_count( $top, '<div' ) - substr_count( $top, '</div>' );
		if ( $open > 0 ) $top .= str_repeat( '</div>', $open );

		return $top . "\n<!-- product-launch sections -->\n<div id=\"prd\">" . $sections . "</div>\n" . $footer;
	}

	private static function fetch_donor( string $url ): string {
		$key = 'edit_prd_donor_' . md5( $url );
		if ( empty( $_GET['nocache'] ) ) {
			$c = get_transient( $key );
			if ( is_string( $c ) && $c !== '' ) return $c;
		}
		$resp = wp_remote_get( add_query_arg( 'edit_src', 'raw', $url ), [
			'timeout'     => 20,
			'redirection' => 3,
			'sslverify'   => false,
			'headers'     => [ 'User-Agent' => 'edit-product-launch/1.0 (+weareedit.io)' ],
		] );
		if ( is_wp_error( $resp ) || (int) wp_remote_retrieve_response_code( $resp ) !== 200 ) return '';
		$body = (string) wp_remote_retrieve_body( $resp );
		if ( $body !== '' ) set_transient( $key, $body, 300 );
		return $body;
	}

	/** Full SEO/GEO head: meta + (live only) Course/FAQPage/BreadcrumbList JSON-LD. */
	public static function seo_head( array $C, bool $is_live ): string {
		$seo   = $C['seo'] ?? [];
		$canon = $seo['canonical'] ?? home_url( '/' . ( $C['slug'] ?? '' ) . '/' );
		$title = $seo['title'] ?? ( $C['name'] ?? '' );
		$desc  = $seo['meta'] ?? '';
		$robots = $is_live ? 'index,follow,max-image-preview:large' : 'noindex,nofollow';

		$h  = '<meta charset="UTF-8">' . "\n";
		$h .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
		$h .= '<title>' . esc_html( $title ) . '</title>' . "\n";
		$h .= '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		$h .= '<meta name="robots" content="' . $robots . '">' . "\n";
		$h .= '<link rel="canonical" href="' . esc_url( $canon ) . '">' . "\n";
		$h .= '<meta property="og:type" content="website"><meta property="og:locale" content="pt_PT">' . "\n";
		$h .= '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		$h .= '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		$h .= '<meta property="og:url" content="' . esc_url( $canon ) . '">' . "\n";
		if ( ! empty( $seo['og_image'] ) ) {
			$h .= '<meta property="og:image" content="' . esc_url( $seo['og_image'] ) . '"><meta name="twitter:card" content="summary_large_image">' . "\n";
		}
		if ( ! $is_live ) return $h;

		$has_instructor = ! empty( $C['instructor']['name'] ) && strpos( $C['instructor']['name'], '[A CONFIRMAR]' ) === false;
		$instance = array_filter( [
			'@type'          => 'CourseInstance',
			'courseMode'     => 'online',
			'courseWorkload' => $seo['time_required'] ?? null,
			'location'       => [ '@type' => 'VirtualLocation', 'url' => $canon ],
		] );
		if ( ! empty( $seo['start_date'] ) ) $instance['startDate'] = $seo['start_date'];
		$course = array_filter( [
			'@context'                     => 'https://schema.org',
			'@type'                        => 'Course',
			'name'                         => $C['name'] ?? '',
			'description'                  => $desc,
			'url'                          => $canon,
			'inLanguage'                   => 'pt-PT',
			'provider'                     => [ '@type' => 'Organization', 'name' => 'EDIT. - Disruptive Digital Education', 'url' => 'https://weareedit.io', 'sameAs' => [ 'https://www.wikidata.org/wiki/Q139907765' ] ],
			'educationalLevel'             => $seo['educational_level'] ?? null,
			'timeRequired'                 => $seo['time_required'] ?? null,
			'teaches'                      => $seo['teaches'] ?? null,
			'about'                        => $seo['about'] ?? null,
			'educationalCredentialAwarded' => $seo['credential'] ?? null,
			'courseMode'                   => 'online',
			'hasCourseInstance'            => $instance,
		] );
		if ( $has_instructor ) $course['instructor'] = [ '@type' => 'Person', 'name' => $C['instructor']['name'], 'jobTitle' => $C['instructor']['role'] ?? '' ];
		if ( ! empty( $seo['price'] ) ) $course['offers'] = [ '@type' => 'Offer', 'category' => 'Bootcamp', 'price' => (string) $seo['price'], 'priceCurrency' => $seo['currency'] ?? 'EUR', 'availability' => 'https://schema.org/InStock', 'url' => $canon ];

		$blocks = [ $course ];
		if ( ! empty( $C['faq'] ) ) {
			$blocks[] = [ '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map( function ( $f ) {
				return [ '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $f[1] ] ];
			}, $C['faq'] ) ];
		}
		$blocks[] = [ '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
			[ '@type' => 'ListItem', 'position' => 1, 'name' => 'EDIT.', 'item' => 'https://weareedit.io/' ],
			[ '@type' => 'ListItem', 'position' => 2, 'name' => 'Formação', 'item' => 'https://weareedit.io/formacao/' ],
			[ '@type' => 'ListItem', 'position' => 3, 'name' => $C['name'] ?? '', 'item' => $canon ],
		] ];
		foreach ( $blocks as $b ) {
			$h .= '<script type="application/ld+json">' . wp_json_encode( $b, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		}
		return $h;
	}
}
