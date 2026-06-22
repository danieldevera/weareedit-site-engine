<?php
/**
 * GAMA Bootcamp (Google Ads & Meta Ads) — redesign preview surface.
 * ─────────────────────────────────────────────────────────────────────────────
 * A disposable, token-gated working duplicate of the Google Ads & Meta Ads
 * bootcamp page, used to iterate the new on-page conversion sections LIVE
 * without touching the real Formação page. Clone of the AAI preview surface.
 *
 * Rendering: proxies the REAL course page server-side (so we inherit the live
 * theme chrome — head/CSS, nav header, hero, footer) and SPLICES our new
 * sections into the body, dropping the old course body:
 *
 *     [ real page: top … through the hero ]   (up to <section class="ceb-mini">)
 *     [ our scoped #aai-rd sections fragment ]
 *     [ real page: <footer> … </html> ]
 *
 * Our sections are scoped under #aai-rd so the theme CSS and ours don't clash.
 * Path-matched at /aai-preview/, token-gated like the Augment page: admins or
 * ?preview=<TOKEN> see it; everyone else (and every crawler) gets a noindex
 * 404. No WP post is created. If the live fetch/splice fails, falls back to a
 * self-contained standalone template.
 *
 * @since 1.5.605
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_GAMA_Redesign_Preview {

    const STATUS        = 'live';
    const PATH          = '/gama-preview';
    const PREVIEW_TOKEN = 'gama-2026-preview';

    /** Real course page we proxy + splice into. */
    const LIVE_URL = 'https://weareedit.io/formacao/bootcamp-online-google-ads-meta-ads/';

    /** Splice markers in the live markup. */
    const HERO_BODY_MARK = 'banner-image"';       // start of the course hero <section> — cut here to drop the real hero + body (we render our own Augment-adapted hero instead)
    const FOOTER_MARK    = '<footer';             // site footer start (no trailing space: raw output emits "<footer\n", WP-Rocket-optimized emits "<footer ")

    const SECTIONS  = 'includes/templates/gama-redesign-sections.html';  // scoped fragment
    const TEMPLATE  = 'includes/templates/gama-redesign-preview.html';   // standalone fallback

    public static function init(): void {
        if ( self::STATUS === 'off' ) return;
        if ( self::is_target_request() ) {
            add_action( 'template_redirect', [ __CLASS__, 'render_page' ], 0 );
        } elseif ( self::STATUS === 'live' && self::is_live_target() ) {
            // Go-live: same approved redesign on the real GAMA URL. Priority -1
            // so we exit before the output buffer starts (keeps formacao
            // post-processors off our markup). Defensive fallback on any error.
            add_action( 'template_redirect', [ __CLASS__, 'render_live' ], -1 );
        }
    }

    private static function is_target_request(): bool {
        $path = (string) parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
        return rtrim( $path, '/' ) === self::PATH;
    }

    private static function should_render_publicly(): bool {
        if ( current_user_can( 'manage_options' ) ) return true;
        $token = isset( $_GET['preview'] ) ? (string) wp_unslash( $_GET['preview'] ) : '';
        return ! empty( self::PREVIEW_TOKEN ) && hash_equals( self::PREVIEW_TOKEN, $token );
    }

    /** Live-mode target: the real GAMA URL — not our own internal raw self-fetch. */
    private static function is_live_target(): bool {
        if ( isset( $_GET['edit_src'] ) ) return false;
        $ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
        if ( strpos( $ua, 'edit-aai-preview' ) !== false ) return false;
        $path = (string) parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
        return rtrim( $path, '/' ) === '/formacao/bootcamp-online-google-ads-meta-ads';
    }

    /** Go-live renderer: fetch raw page, splice, emit index,follow. Fallback = untouched page. */
    public static function render_live(): void {
        $live = self::live_html();
        if ( $live === '' ) return;
        $spliced = self::splice( $live, self::sections_fragment(), true );
        if ( $spliced === '' ) return;
        self::emit( $spliced, false );
    }

    public static function render_page(): void {
        // This is a live working preview that changes every release — never let
        // WP Rocket (or other page caches) cache it, or edits won't show.
        if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );

        if ( ! self::should_render_publicly() ) {
            status_header( 404 );
            nocache_headers();
            header( 'X-Robots-Tag: noindex, nofollow', true );
            echo '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><title>Em breve</title></head><body><p style="font-family:system-ui;text-align:center;padding:80px 20px;color:#666">Página em construção.</p></body></html>';
            exit;
        }

        $fragment = self::sections_fragment();

        // Preferred: splice our sections into the real (proxied) course page.
        $live = self::live_html();
        if ( $live !== '' ) {
            $spliced = self::splice( $live, $fragment );
            if ( $spliced !== '' ) { self::emit( $spliced ); return; }
        }

        // Fallback: self-contained standalone template.
        $file = WEAREDIT_SITE_ENGINE_PATH . self::TEMPLATE;
        if ( file_exists( $file ) ) {
            $html = (string) file_get_contents( $file );
            $html = str_replace( '<!--EDIT-HEAD-->', '<meta name="robots" content="noindex,nofollow">', $html );
            $html = str_replace( '<!--CERT-URL-->', esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/aai/cert-sample.jpg' ), $html );
            self::emit( $html );
            return;
        }

        status_header( 500 );
        nocache_headers();
        echo 'GAMA preview unavailable.';
        exit;
    }

    /** Scoped #aai-rd sections fragment, with the certificate URL resolved. */
    private static function sections_fragment(): string {
        $f = WEAREDIT_SITE_ENGINE_PATH . self::SECTIONS;
        $html = file_exists( $f ) ? (string) file_get_contents( $f ) : '';
        return str_replace( '<!--CERT-URL-->', esc_url( WEAREDIT_SITE_ENGINE_URL . 'assets/aai/cert-sample.jpg' ), $html );
    }

    /** Fetch the live course page (5-min transient cache; ?nocache to bypass). */
    private static function live_html(): string {
        $key = 'edit_gama_live_html';
        if ( empty( $_GET['nocache'] ) ) {
            $cached = get_transient( $key );
            if ( is_string( $cached ) && $cached !== '' ) return $cached;
        }
        $resp = wp_remote_get( add_query_arg( 'edit_src', 'raw', self::LIVE_URL ), [
            'timeout'     => 22,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => [ 'User-Agent' => 'edit-aai-preview/1.0 (+weareedit.io)' ],
        ] );
        if ( is_wp_error( $resp ) ) return '';
        if ( (int) wp_remote_retrieve_response_code( $resp ) !== 200 ) return '';
        $body = (string) wp_remote_retrieve_body( $resp );
        if ( $body === '' ) return '';
        set_transient( $key, $body, 300 );
        return $body;
    }

    /** Splice: [top..hero] + our sections + [footer..end]. '' on failure. */
    private static function splice( string $live, string $fragment, bool $live_mode = false ): string {
        $mark = strpos( $live, self::HERO_BODY_MARK );
        if ( $mark === false ) return '';
        $cut = strrpos( substr( $live, 0, $mark ), '<section' );
        if ( $cut === false ) return '';

        $foot = strpos( $live, self::FOOTER_MARK );
        if ( $foot === false || $foot <= $cut ) return '';

        $top    = substr( $live, 0, $cut );
        $footer = substr( $live, $foot );

        // Force noindex on the proxied <head> for the PREVIEW route only. On the
        // live URL we keep the real page's index,follow + all its JSON-LD.
        if ( ! $live_mode ) {
            $top = preg_replace( '/<head[^>]*>/i', '$0<meta name="robots" content="noindex,nofollow">', $top, 1 );
        }

        // Hard-strip the EARLY15 promo bar/overlay (JS-built — sets inline
        // styles a CSS rule can't beat) and the breadcrumbs nav, so they're
        // gone on the preview rather than merely hidden.
        $kill_scripts = '#<(script|style)\b[^>]*\bid="edit-(promo|early15)[^"]*"[^>]*>.*?</\1>#is';
        $kill_bcrumb  = '#<nav\b[^>]*class="edit-breadcrumbs"[^>]*>.*?</nav>#is';
        foreach ( array( 'top', 'footer' ) as $part ) {
            $$part = preg_replace( $kill_scripts, '', $$part );
            $$part = preg_replace( $kill_bcrumb, '', $$part );
        }

        // Remove the old course H1 (e.g. "Bootcamp / Advanced Artificial
        // Intelligence", class color-<Type>) — we render our own hero title.
        $top = preg_replace( '#<h1\b[^>]*class="[^"]*\bcolor-[^"]*"[^>]*>.*?</h1>#is', '', $top );

        // Extract the real date/info bar (dates row .formacao-info + action bar
        // #info_bar) and re-place it under our hero, overlapping the video.
        // It runs from .formacao-info to the end of $top (already trimmed at the
        // hero start). The <!--INFO-BAR--> marker in the fragment is the target.
        $info_bar = '';
        $fi = strpos( $top, 'class="formacao-info ' );
        if ( $fi !== false ) {
            $start = strrpos( substr( $top, 0, $fi ), '<div' );
            if ( $start !== false ) {
                $chunk = substr( $top, $start );   // .formacao-info … then #info_bar …
                $top   = substr( $top, 0, $start );
                // Split: the dates (.formacao-info) go inside the boxed card; the
                // action bar (#info_bar) stays full-width OUTSIDE the box, so the
                // box hugs the dates (no empty reserved height / dark band) and
                // the action bar can carry its own drop shadow.
                $ib = strpos( $chunk, 'id="info_bar"' );
                if ( $ib !== false ) {
                    $ibs    = strrpos( substr( $chunk, 0, $ib ), '<div' );
                    $dates  = $ibs !== false ? substr( $chunk, 0, $ibs ) : $chunk;
                    $action = $ibs !== false ? substr( $chunk, $ibs ) : '';
                    // The dates chunk carries the theme's ancestor </div>s (it
                    // over-closes), which would pop #info_bar several levels up
                    // the DOM — so it stops being a sibling of the dates box and
                    // no margin can pull the action bar flush. Trim those stray
                    // trailing closes so #aai-infobar wraps exactly the dates and
                    // #info_bar lands right after it as a true sibling.
                    $dates  = self::balance_chunk( $dates );
                    $action = self::balance_chunk( $action );
                    // GAMA's live info bar already carries Início + Duração +
                    // Investimento, so no extra column injection is needed here.
                    $info_bar = '<div id="aai-infobar">' . $dates . '</div>' . $action;
                } else {
                    $info_bar = '<div id="aai-infobar">' . self::balance_chunk( $chunk ) . '</div>';
                }
            }
        }
        // Self-balance $top: close any wrappers left open by dropping the old
        // hero + extracting the info bar (the theme's offset grid column), so
        // our hero/sections inject at full body width — not inside a padded,
        // offset column. This is what lets the hero sit edge-to-edge.
        $open = substr_count( $top, '<div' ) - substr_count( $top, '</div>' );
        if ( $open > 0 ) { $top .= str_repeat( '</div>', $open ); }

        // Extract the real course program/curriculum section (.programa) for the
        // <!--PROGRAMA--> slot. It lives in the dropped body, so search $live.
        $programa = '';
        $pp = strpos( $live, 'class="programa ' );
        if ( $pp !== false ) {
            $pstart = strrpos( substr( $live, 0, $pp ), '<section' );
            $pend   = strpos( $live, '<section', $pp + 16 );
            if ( $pstart !== false && $pend !== false && $pend > $pstart ) {
                $programa = '<div class="aai-programa">' . substr( $live, $pstart, $pend - $pstart ) . '</div>';
            }
        }

        // Extract the full course-details "education" block verbatim: the
        // .visao-geral section (Sobre / Visão Geral / O que inclui / DGERT /
        // Mensalidades) through the end of .programa, up to the next section
        // (.ceb-mini). Injected as-is (outside #aai-rd) so it renders exactly
        // like the live page.
        $education = '';
        $ve = strpos( $live, 'class="visao-geral' );
        $cm = strpos( $live, 'class="ceb-mini' );
        if ( $ve !== false && $cm !== false && $cm > $ve ) {
            $estart = strrpos( substr( $live, 0, $ve ), '<section' );
            $eend   = strrpos( substr( $live, 0, $cm ), '<section' );
            if ( $estart !== false && $eend !== false && $eend > $estart ) {
                $education = '<div class="aai-education">' . substr( $live, $estart, $eend - $estart ) . '</div>';
            }
        }

        $fragment = str_replace( '<!--INFO-BAR-->', $info_bar, $fragment );
        $fragment = str_replace( '<!--PROGRAMA-->', $programa, $fragment );
        $fragment = str_replace( '<!--EDUCATION-->', $education, $fragment );

        // The footer's "Mantém-te a par das novidades / Subscrever Newsletter"
        // strip is obsolete (superseded by the in-page pink newsletter) — strip
        // it from the proxied footer, from its <div> up to the copyright bar.
        $fn = strpos( $footer, 'class="footer-newsletter"' );
        if ( $fn !== false ) {
            $fnd = strrpos( substr( $footer, 0, $fn ), '<div' );
            $cp  = strpos( $footer, 'class="copyright"', $fn );
            if ( $fnd !== false && $cp !== false ) {
                $cpd = strrpos( substr( $footer, 0, $cp ), '<div' );
                if ( $cpd !== false && $cpd > $fnd ) {
                    $footer = substr( $footer, 0, $fnd ) . substr( $footer, $cpd );
                }
            }
        }

        // TEST: Alumni "colocados em" logo wall, placed directly ABOVE the pink
        // newsletter section. The newsletter strip is JS-injected just before the
        // <footer>; by sitting the wall immediately before <footer> too, the JS
        // strip inserts AFTER the wall, so the wall lands over the newsletter.
        if ( class_exists( 'EDIT_Alumni_Employers' ) ) {
            $wall = EDIT_Alumni_Employers::render( 'wall' );
            if ( $wall !== '' ) {
                $band = '<div class="aai-alumni-wall" style="background:#f6f4ef;padding:72px 24px;">'
                      . '<div style="max-width:1240px;margin:0 auto;">' . $wall . '</div></div>';
                $footer = $band . $footer;
            }
        }

        return $top . "\n<!-- GAMA redesign sections -->\n" . $fragment . "\n" . $footer;
    }

    /**
     * Make an extracted HTML chunk self-balanced: trim stray trailing </div>s
     * if it over-closes (carried ancestor closes), or append </div>s if it
     * leaves wrappers open. Lets the dates box + action bar sit as siblings.
     */
    private static function balance_chunk( string $html ): string {
        $diff = substr_count( $html, '<div' ) - substr_count( $html, '</div>' );
        if ( $diff < 0 ) {
            // Over-closed: drop the spurious trailing closes one at a time.
            while ( $diff < 0 && preg_match( '#</div>\s*$#', $html ) ) {
                $html = preg_replace( '#</div>\s*$#', '', $html, 1 );
                $diff++;
            }
        } elseif ( $diff > 0 ) {
            $html .= str_repeat( '</div>', $diff );
        }
        return $html;
    }

    /**
     * Re-inject the real Inscrição form (CF7 295986) as a side panel — the
     * splice drops the theme's native one. Reuses the .side-filter-container
     * open mechanism. Returns '' on failure so CTAs fall back to #falaConnosco.
     */
    private static function inscription_panel( string $live ): string {
        // Re-place the REAL native inscrição panel (#pedirInfo, form 295986)
        // that the splice would otherwise drop — extracted verbatim, NOT rebuilt.
        $i = strpos( $live, 'id="pedirInfo"' );
        if ( $i === false ) return '';
        $start = strrpos( substr( $live, 0, $i ), '<div' );
        if ( $start === false ) return '';
        $depth = 0; $p = $start; $len = strlen( $live );
        while ( $p < $len ) {
            $od = strpos( $live, '<div', $p );
            $cd = strpos( $live, '</div>', $p );
            if ( $cd === false ) break;
            if ( $od !== false && $od < $cd ) { $depth++; $p = $od + 4; }
            else { $depth--; $p = $cd + 6; if ( $depth === 0 ) return substr( $live, $start, $p - $start ); }
        }
        return '';
    }

    private static function emit( string $html, bool $noindex = true ): void {
        status_header( 200 );
        if ( $noindex ) {
            nocache_headers();
            header( 'X-Robots-Tag: noindex, nofollow', true );
        }
        header( 'Content-Type: text/html; charset=UTF-8' );
        echo $html;
        exit;
    }
}
