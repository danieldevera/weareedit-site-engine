<?php
/**
 * Internal Marketing Documents — link-only static HTML library.
 *
 * Routes:
 *   /internal-marketing-documents/             → index of available docs
 *   /internal-marketing-documents/{slug}/      → serves includes/templates/internal-docs/{slug}.html
 *
 * Access: PUBLIC (no login). All responses carry X-Robots-Tag noindex/nofollow
 * + <meta robots> so search engines skip these pages. Documents are
 * discoverable only via the index URL or a directly shared slug URL — they
 * aren't linked from any public page on the site.
 *
 * Login gate removed v1.5.386 — some team members don't have WP credentials.
 *
 * To add a doc: drop a new .html file in includes/templates/internal-docs/
 * named with the desired slug (e.g. brevo-playbook.html). It appears in the
 * index automatically on next request.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Internal_Marketing_Docs {

    const SLUG_BASE    = 'internal-marketing-documents';
    const QUERY_VAR    = 'edit_imd_doc';
    const INDEX_TOKEN  = '__index__';
    const DOCS_DIR     = 'includes/templates/internal-docs/';
    /** Version-keyed flush flag: every plugin release auto-flushes once.
     *  Resilient to opcache + rewrite-rule resets without manual intervention. */
    const FLUSH_FLAG_PREFIX = 'edit_imd_rewrites_flushed_';

    /**
     * Old slug → new slug 301 map for renamed / superseded docs, so existing
     * links and bookmarks to a retired doc bounce to its replacement.
     */
    const REDIRECTS = [
        'education-foresight-strategy-2026-06-10-pt' => 'education-foresight-strategy-2026-06-16-pt',
    ];

    public static function init() {
        // Belt + suspenders. Rewrite rules are the "clean URL" path but they
        // require .htaccess flushing which can fail silently in shared/managed
        // hosting. The parse_request hook below catches the URL directly from
        // $_SERVER['REQUEST_URI'] so the route works regardless of whether
        // rewrite rules were flushed correctly.
        add_action( 'init',              [ __CLASS__, 'register_rewrites' ] );
        add_filter( 'query_vars',        [ __CLASS__, 'add_query_var' ] );
        add_action( 'template_redirect', [ __CLASS__, 'maybe_render' ] );
        add_action( 'admin_init',        [ __CLASS__, 'maybe_flush_rewrites' ] );
        // Direct URL-pattern hijack — fires at parse_request (very early in
        // routing) BEFORE WP's 404 handler can run. Independent of rewrite rules.
        add_action( 'parse_request',     [ __CLASS__, 'maybe_render_from_uri' ], 1 );
    }

    /**
     * URL-pattern matcher that bypasses the rewrite-rule pipeline entirely.
     * If the request URI matches /internal-marketing-documents/{?slug}/,
     * render the doc directly and exit. Runs at parse_request (priority 1)
     * so it fires before any WP routing/404 logic.
     */
    public static function maybe_render_from_uri( $wp ) {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = strtok( $uri, '?' ); // strip query string
        $path = trim( $path, '/' );

        // Match /internal-marketing-documents/ (index) or /.../{slug}/
        if ( $path === self::SLUG_BASE ) {
            self::render_index();
            exit;
        }
        if ( preg_match( '#^' . preg_quote( self::SLUG_BASE, '#' ) . '/([^/]+)/?$#', $path, $m ) ) {
            $slug = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $m[1] ) );
            if ( ! $slug ) {
                status_header( 404 );
                self::render_404( $m[1] );
                exit;
            }
            self::maybe_redirect( $slug );
            $file = WEAREDIT_SITE_ENGINE_PATH . self::DOCS_DIR . $slug . '.html';
            if ( ! file_exists( $file ) ) {
                status_header( 404 );
                self::render_404( $slug );
                exit;
            }
            header( 'Content-Type: text/html; charset=utf-8' );
            header( 'X-Robots-Tag: noindex, nofollow', true );
            readfile( $file );
            exit;
        }
        // Not our URL; let WP continue normally.
    }

    public static function register_rewrites() {
        add_rewrite_rule(
            '^' . self::SLUG_BASE . '/?$',
            'index.php?' . self::QUERY_VAR . '=' . self::INDEX_TOKEN,
            'top'
        );
        add_rewrite_rule(
            '^' . self::SLUG_BASE . '/([^/]+)/?$',
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    public static function add_query_var( $vars ) {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    /** 301 a renamed/superseded doc slug to its replacement, then exit. */
    private static function maybe_redirect( $slug ) {
        if ( isset( self::REDIRECTS[ $slug ] ) ) {
            wp_safe_redirect( home_url( '/' . self::SLUG_BASE . '/' . self::REDIRECTS[ $slug ] . '/' ), 301 );
            exit;
        }
    }

    /**
     * One-time rewrite flush after deploy, so the new routes resolve
     * without a manual permalink-save.
     */
    public static function maybe_flush_rewrites() {
        $flag = self::FLUSH_FLAG_PREFIX . sanitize_key( WEAREDIT_SITE_ENGINE_VERSION );
        if ( get_option( $flag ) ) return;
        self::register_rewrites();
        flush_rewrite_rules( false );
        update_option( $flag, 1 );
    }

    public static function maybe_render() {
        $doc = get_query_var( self::QUERY_VAR );
        if ( ! $doc ) return;

        if ( $doc === self::INDEX_TOKEN ) {
            self::render_index();
            exit;
        }

        // Strict slug sanitization: lowercase, digits, hyphens only.
        $slug = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $doc ) );
        if ( ! $slug ) {
            status_header( 404 );
            exit;
        }

        self::maybe_redirect( $slug );

        $path = WEAREDIT_SITE_ENGINE_PATH . self::DOCS_DIR . $slug . '.html';
        if ( ! file_exists( $path ) ) {
            status_header( 404 );
            self::render_404( $slug );
            exit;
        }

        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow', true );
        readfile( $path );
        exit;
    }

    private static function discover_docs() {
        $dir = WEAREDIT_SITE_ENGINE_PATH . self::DOCS_DIR;
        if ( ! is_dir( $dir ) ) return [];
        $files = glob( $dir . '*.html' );
        if ( ! $files ) return [];
        $docs = [];
        foreach ( $files as $f ) {
            $slug   = basename( $f, '.html' );
            $title  = self::extract_title( $f );
            $mtime  = filemtime( $f );
            $docs[] = [
                'slug'  => $slug,
                'title' => $title ?: ucwords( str_replace( [ '-', '_' ], ' ', $slug ) ),
                'date'  => $mtime ? date_i18n( 'd M Y', $mtime ) : '',
            ];
        }
        // Newest first.
        usort( $docs, function ( $a, $b ) { return strcmp( $b['date'], $a['date'] ); } );
        return $docs;
    }

    private static function extract_title( $path ) {
        $head = @file_get_contents( $path, false, null, 0, 2048 );
        if ( ! $head ) return '';
        if ( preg_match( '/<title>([^<]+)<\/title>/i', $head, $m ) ) {
            return trim( strip_tags( $m[1] ) );
        }
        return '';
    }

    private static function render_index() {
        $docs = self::discover_docs();

        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow', true );
        ?><!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>EDIT. · Internal Marketing Documents</title>
<style>
  :root { --ink:#0a0a0a; --pink:#f92869; --yellow:#ffdd06; --grey:#666; --soft:#fafafa; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; background:var(--soft); color:var(--ink); padding:48px 24px; line-height:1.5; }
  .wrap { max-width:880px; margin:0 auto; }
  .eyebrow { font-size:11px; font-weight:700; color:var(--pink); letter-spacing:0.22em; text-transform:uppercase; margin-bottom:12px; }
  h1 { font-size:38px; font-weight:800; letter-spacing:-0.02em; margin-bottom:8px; }
  .sub { color:var(--grey); font-size:14px; margin-bottom:36px; }
  .docs { background:#fff; border:1px solid #e5e5e5; border-radius:8px; overflow:hidden; }
  .doc { padding:20px 24px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; gap:16px; transition:background 0.15s; }
  .doc:last-child { border-bottom:none; }
  .doc:hover { background:#fafafa; }
  .doc .title { font-size:16px; font-weight:700; color:var(--ink); text-decoration:none; }
  .doc .title:hover { color:var(--pink); }
  .doc .date { font-size:12px; color:var(--grey); font-variant-numeric:tabular-nums; }
  .empty { background:#fff; padding:48px 24px; text-align:center; color:var(--grey); border:1px dashed #ddd; border-radius:8px; font-size:14px; }
  .empty code { background:#f0f0f0; padding:2px 8px; border-radius:3px; font-size:12px; }
  footer { margin-top:36px; padding-top:20px; border-top:1px solid #e5e5e5; color:var(--grey); font-size:12px; text-align:center; }
</style>
</head>
<body>
<div class="wrap">
  <p class="eyebrow">EDIT. Internal</p>
  <h1>Marketing Documents</h1>
  <p class="sub">Playbooks, relatórios e guias operacionais para o team de marketing da EDIT. Páginas marcadas <code>noindex</code> — só acessíveis via link directo.</p>

  <?php if ( empty( $docs ) ): ?>
    <div class="empty">
      Sem documentos disponíveis ainda.<br>
      Adicionar ficheiros HTML em <code><?php echo esc_html( self::DOCS_DIR ); ?></code> via release do plugin.
    </div>
  <?php else: ?>
    <div class="docs">
      <?php foreach ( $docs as $doc ): ?>
        <div class="doc">
          <a class="title" href="<?php echo esc_url( home_url( '/' . self::SLUG_BASE . '/' . $doc['slug'] . '/' ) ); ?>">
            <?php echo esc_html( $doc['title'] ); ?>
          </a>
          <span class="date"><?php echo esc_html( $doc['date'] ); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <footer>
    EDIT. Site Engine v<?php echo esc_html( WEAREDIT_SITE_ENGINE_VERSION ); ?> · noindex/nofollow
  </footer>
</div>
</body>
</html><?php
    }

    private static function render_404( $slug ) {
        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow', true );
        ?><!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title>EDIT. · Documento não encontrado</title>
<style>
  body { font-family:-apple-system,sans-serif; background:#fafafa; color:#0a0a0a; padding:60px 24px; text-align:center; }
  h1 { font-size:32px; margin-bottom:12px; }
  p { color:#666; max-width:500px; margin:0 auto 20px; }
  a { color:#f92869; text-decoration:underline; font-weight:600; }
</style>
</head>
<body>
<h1>📂 Documento não encontrado</h1>
<p>Não existe nenhum documento com o slug <code><?php echo esc_html( $slug ); ?></code>.</p>
<p><a href="<?php echo esc_url( home_url( '/' . self::SLUG_BASE . '/' ) ); ?>">← Voltar ao índice</a></p>
</body>
</html><?php
    }
}
