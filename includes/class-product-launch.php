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
		// Preview is private: unauthorized visitors + all crawlers get a noindex 404.
		if ( $mode === 'preview' && ! self::authorized( $cfg ) ) {
			status_header( 404 );
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow', true );
			header( 'Content-Type: text/html; charset=UTF-8' );
			echo '<!doctype html><meta name="robots" content="noindex,nofollow"><title>404</title><p>Not found.</p>';
			exit;
		}

		$cfg['_mode'] = $mode;
		$C = $cfg; // exposed to the template

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=UTF-8' );
			if ( $mode === 'preview' ) {
				nocache_headers();
				header( 'X-Robots-Tag: noindex, nofollow', true );
			}
		}

		$tpl = WEAREDIT_SITE_ENGINE_PATH . self::TEMPLATE;
		if ( is_readable( $tpl ) ) {
			include $tpl;
		}
		exit;
	}
}
