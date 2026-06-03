<?php
/**
 * Formação archive — campaign URL banner.
 *
 * Server-side render: when ?campanha=early15 is on the URL and we're on a
 * /formacao/ path, the banner gets inserted directly into the page HTML
 * via the same output-buffer filter the breadcrumbs use. No JS, no
 * deferred-script timing issues, no cache surprises.
 *
 * Earlier attempts (v1.5.242–v1.5.252) used JS to either filter the grid
 * or inject the banner. The filter blanked the page; the JS injection
 * never fired in production (likely WP Rocket / Cloudflare interference
 * with the inline script).
 *
 * @package WeareEditSiteEngine
 * @since   1.5.254
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Formacao_Archive_Filter {

    const QUERY_PARAM   = 'campanha';
    const EARLY15_VALUE = 'early15';
    /** Campaign hard kill date — banner auto-hides itself after this. */
    const KILL_DATE     = '2026-06-30 23:59:59';
    /** A/B test cookie name. Holds 'A' (countdown) or 'B' (static deadline). */
    const VARIANT_COOKIE = 'edit_promo_variant';
    /** Dismiss cookie name (set when user clicks the × on the bar). */
    const DISMISS_COOKIE = 'edit_promo_bar_dismissed';

    public static function init(): void {
        // Shortlink redirect — runs early in the request lifecycle. The
        // class's own init() is itself called from `init` priority 10, so
        // we attach to `parse_request` (which fires after init) and ALSO
        // do an inline check in case parse_request was already past.
        self::maybe_shortlink_redirect();
        add_action( 'parse_request', [ __CLASS__, 'maybe_shortlink_redirect' ], 1 );
        // CSS still goes via wp_head — keeps the inline style cacheable.
        add_action( 'wp_head', [ __CLASS__, 'maybe_print_css' ], 7 );
        // Banner HTML injected into the buffered response.
        add_filter( 'weareedit_site_engine_output_buffer', [ __CLASS__, 'maybe_inject_html' ], 7 );
        // Card filter — hides non-EARLY15 course-boxes server-side.
        add_filter( 'weareedit_site_engine_output_buffer', [ __CLASS__, 'maybe_filter_cards' ], 8 );
    }

    /**
     * Short-link redirect: /early15 → /formacao/?campanha=early15.
     *
     * Lets us print "weareedit.io/early15" on reels / posters / paid ads
     * and have visitors land on the filtered archive without typing the
     * full query string. 301 so search engines collapse the duplicate.
     */
    public static function maybe_shortlink_redirect(): void {
        if ( is_admin() ) return;
        $path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( (string) $_SERVER['REQUEST_URI'], '?' ) : '';
        $normalised = '/' . trim( (string) $path, '/' );
        if ( $normalised !== '/early15' ) return;
        wp_safe_redirect( home_url( '/formacao/?campanha=early15' ), 301 );
        exit;
    }

    /**
     * Banner is scoped to /formacao/ pages during the campaign window.
     * Other pages stay clean. Banner text still links to the filtered
     * archive so clicks inside the formacao surface land on the curated
     * /formacao/?campanha=early15 view.
     */
    private static function is_banner_active(): bool {
        if ( is_admin() || is_feed() ) return false;
        if ( strtotime( self::KILL_DATE ) < time() ) return false;
        $path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( (string) $_SERVER['REQUEST_URI'], '?' ) : '';
        if ( strpos( (string) $path, '/formacao' ) !== 0 ) return false;
        return true;
    }

    /**
     * Card filter is scoped: only fires on /formacao/ when ?campanha=early15
     * is explicitly set. Visitors landing there via the banner link or any
     * other channel see the curated 7 courses; other surfaces stay normal.
     */
    private static function is_filter_active(): bool {
        if ( is_admin() || is_feed() ) return false;
        if ( empty( $_GET[ self::QUERY_PARAM ] ) ) return false;
        $campaign = sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_PARAM ] ) );
        if ( $campaign !== self::EARLY15_VALUE ) return false;
        $path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( (string) $_SERVER['REQUEST_URI'], '?' ) : '';
        if ( strpos( (string) $path, '/formacao' ) !== 0 ) return false;
        return true;
    }

    // Back-compat shim — older code paths checked is_active().
    private static function is_active(): bool { return self::is_banner_active(); }

    /**
     * Assign or return the visitor's A/B variant.
     * 'A' = countdown bar (BD-4a), 'B' = static deadline (BD-4e).
     * Cookie persists 30 days so visitors see the same variant on return.
     */
    private static function get_variant(): string {
        $existing = isset( $_COOKIE[ self::VARIANT_COOKIE ] ) ? (string) $_COOKIE[ self::VARIANT_COOKIE ] : '';
        if ( $existing === 'A' || $existing === 'B' ) {
            return $existing;
        }
        // QA override: ?variant=A or ?variant=B on the URL forces a side
        // without setting the cookie. Useful for screenshots + debug.
        if ( isset( $_GET['variant'] ) ) {
            $forced = strtoupper( sanitize_text_field( wp_unslash( (string) $_GET['variant'] ) ) );
            if ( $forced === 'A' || $forced === 'B' ) return $forced;
        }
        $variant = ( random_int( 0, 1 ) === 0 ) ? 'A' : 'B';
        if ( ! headers_sent() ) {
            setcookie(
                self::VARIANT_COOKIE,
                $variant,
                time() + 30 * DAY_IN_SECONDS,
                defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
                defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
                is_ssl(),
                false
            );
            // Reflect in $_COOKIE so the same request sees the assignment too.
            $_COOKIE[ self::VARIANT_COOKIE ] = $variant;
        }
        return $variant;
    }

    private static function is_dismissed(): bool {
        return ! empty( $_COOKIE[ self::DISMISS_COOKIE ] );
    }

    public static function maybe_print_css(): void {
        if ( ! self::is_banner_active() ) return;
        ?>
<style id="edit-early15-banner-css">
/* Minimal promo bar — sits above the breadcrumb nav, dark with a yellow
   hairline border. Two variants (A countdown / B static) share most styles. */
#edit-promo-bar {
    position: relative !important; z-index: 5 !important;
    display: flex !important; visibility: visible !important; opacity: 1 !important;
    align-items: center !important; justify-content: center !important; gap: 16px !important;
    background: #0a0a0a !important; color: rgba(255,255,255,0.92) !important;
    border-bottom: 1px solid #ffdd06 !important;
    font-family: 'SctoGroteskA', 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
    font-weight: 400 !important; font-size: 14px !important; line-height: 1.3 !important;
    /* Top margin clears the theme's fixed header so the bar sits right
       under it instead of being hidden behind it. The theme header is
       taller than the visible nav row — uses ~110px clearance in practice. */
    margin: 110px 0 0 0 !important; padding: 11px 40px !important;
    text-decoration: none !important;
}
/* When the bar sits above the breadcrumbs, zero out the breadcrumbs'
   own 140px top-padding so they butt cleanly under the bar. */
#edit-promo-bar ~ .edit-breadcrumbs { padding-top: 14px !important; }
#edit-promo-bar .dot {
    width: 8px; height: 8px; background: #ffdd06; border-radius: 50%; flex-shrink: 0;
}
#edit-promo-bar .copy { color: rgba(255,255,255,0.92); }
#edit-promo-bar .copy .y { color: #ffdd06; }
#edit-promo-bar .copy .p { color: #f92869; }
#edit-promo-bar .copy .sep { color: rgba(255,255,255,0.3); margin: 0 10px; }
#edit-promo-bar .copy .hl { color: #ffdd06; }
#edit-promo-bar .countdown {
    display: inline-flex; align-items: center; gap: 0;
    font-family: 'SctoGroteskA', monospace; font-variant-numeric: tabular-nums;
}
#edit-promo-bar .countdown .n {
    color: #fff; background: rgba(255,221,6,0.12); padding: 3px 7px; margin-right: 4px;
    border-radius: 3px; min-width: 28px; text-align: center; display: inline-block;
}
#edit-promo-bar .countdown .lbl { color: rgba(255,255,255,0.65); margin-right: 8px; }
#edit-promo-bar .cta {
    color: #ffdd06; background: transparent; text-decoration: none; padding: 0;
}
#edit-promo-bar .cta:hover { color: #fff; }
#edit-promo-bar .close {
    position: absolute; right: 18px; top: 50%; transform: translateY(-50%);
    font-size: 16px; color: rgba(255,255,255,0.55); cursor: pointer; line-height: 1;
    background: transparent; border: 0; padding: 0;
}
#edit-promo-bar .close:hover { color: #fff; }
@media (max-width: 720px) {
    #edit-promo-bar { padding: 10px 16px 10px 16px !important; font-size: 13px !important; flex-wrap: wrap; gap: 8px !important; }
    #edit-promo-bar .close { right: 8px; }
    #edit-promo-bar .copy .sep { margin: 0 6px; }
}

/* Card filter — hide the wrapper div that owns the grid slot. */
.course-box.early15-hidden,
.course:has(.course-box.early15-hidden) { display: none !important; }
</style>
        <?php
    }

    /**
     * Hide every `<a class="course-box">…</a>` that does NOT contain a
     * `<div class="course-promo-code">early15</div>` badge.
     *
     * The promo code appears inside `<div class="special-labels-container">`
     * at the top-left of each card; we just check for the text node inside
     * `course-promo-code` so case + whitespace are tolerant.
     */
    public static function maybe_filter_cards( string $html ): string {
        if ( ! self::is_filter_active() ) return $html;
        if ( strpos( $html, 'course-box' ) === false ) return $html;

        return preg_replace_callback(
            '#<a([^>]*?)class="([^"]*?course-box[^"]*?)"([^>]*?)>(.*?)</a>#s',
            function ( array $m ): string {
                if ( preg_match( '#course-promo-code"[^>]*>\s*early15\s*<#i', $m[4] ) ) {
                    return $m[0]; // keep — has the badge
                }
                return '<a' . $m[1] . 'class="' . $m[2] . ' early15-hidden"' . $m[3] . '>' . $m[4] . '</a>';
            },
            $html
        );
    }

    public static function maybe_inject_html( string $html ): string {
        if ( ! self::is_banner_active() ) return $html;
        if ( self::is_dismissed() ) return $html;
        // Avoid double-injection if the filter runs twice for any reason.
        if ( strpos( $html, 'id="edit-promo-bar"' ) !== false ) return $html;

        $variant   = self::get_variant();
        $cta_url   = esc_url( home_url( '/formacao/?campanha=early15' ) );
        $aria      = 'Promoção Early Bird 15 — variante ' . $variant;

        // ---- Variant A : live countdown ----
        if ( $variant === 'A' ) {
            $inner = '<span class="dot"></span>'
                   . '<span class="copy">'
                   .   '<span class="y">Promo</span>'
                   .   '<span class="sep">·</span>'
                   .   'Early Bird <span class="p">15%</span> termina em'
                   . '</span>'
                   . '<span class="countdown">'
                   .   '<span class="n" id="edit-promo-cd-d">--</span><span class="lbl">dias</span>'
                   .   '<span class="n" id="edit-promo-cd-h">--</span><span class="lbl">horas</span>'
                   .   '<span class="n" id="edit-promo-cd-m">--</span><span class="lbl">min</span>'
                   . '</span>'
                   . '<a class="cta" href="' . $cta_url . '" data-edit-promo-cta>Ver cursos →</a>'
                   . '<button type="button" class="close" data-edit-promo-dismiss aria-label="Fechar">&times;</button>';
        } else {
            // ---- Variant B : static deadline ----
            $inner = '<span class="dot"></span>'
                   . '<span class="copy">'
                   .   '<span class="y">Promo</span>'
                   .   '<span class="sep">·</span>'
                   .   'Early Bird <span class="p">15%</span> nos cursos de Setembro'
                   .   '<span class="sep">·</span>'
                   .   'Termina <span class="hl">30 Junho</span>'
                   . '</span>'
                   . '<a class="cta" href="' . $cta_url . '" data-edit-promo-cta>Ver cursos →</a>'
                   . '<button type="button" class="close" data-edit-promo-dismiss aria-label="Fechar">&times;</button>';
        }

        $bar = '<div id="edit-promo-bar" data-variant="' . esc_attr( $variant ) . '" role="region" aria-label="' . esc_attr( $aria ) . '">'
             . $inner
             . '</div>'
             . self::tracking_script( $variant );

        // Insertion: RIGHT BEFORE our breadcrumb nav so the bar sits between
        // the site header and the breadcrumb trail.
        $patterns = [
            '#(<nav[^>]*class="edit-breadcrumbs[^"]*"[^>]*>)#i',
            '#(<!--\s*#masthead\s*-->)#i',
            '#(<main[^>]*>)#i',
        ];
        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $html ) ) {
                // For #masthead comment, insert AFTER. For nav + main, insert BEFORE.
                if ( strpos( $pattern, 'masthead' ) !== false ) {
                    return preg_replace( $pattern, '$1' . $bar, $html, 1 );
                }
                return preg_replace( $pattern, $bar . '$1', $html, 1 );
            }
        }
        return preg_replace( '#(<body[^>]*>)#i', '$1' . $bar, $html, 1 );
    }

    /**
     * Inline JS — fires GA4 dataLayer events (view + click), runs the
     * countdown for variant A, and handles dismissal.
     */
    private static function tracking_script( string $variant ): string {
        $kill_iso = gmdate( 'c', strtotime( self::KILL_DATE ) );
        ob_start();
        ?>
<script id="edit-promo-bar-js">
(function () {
    'use strict';
    var bar = document.getElementById('edit-promo-bar');
    if (!bar) return;
    var variant = bar.getAttribute('data-variant') || 'A';
    var killIso = <?php echo wp_json_encode( $kill_iso ); ?>;

    // ---- GA4 / dataLayer view event ----
    try {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'promo_bar_view',
            promo_variant: variant,
            promo_campaign: 'early15_set2026'
        });
    } catch (e) {}

    // ---- CTA click event ----
    var cta = bar.querySelector('[data-edit-promo-cta]');
    if (cta) {
        cta.addEventListener('click', function () {
            try {
                window.dataLayer.push({
                    event: 'promo_bar_click',
                    promo_variant: variant,
                    promo_campaign: 'early15_set2026'
                });
            } catch (e) {}
        });
    }

    // ---- Dismiss handler — sets cookie for 7 days ----
    var close = bar.querySelector('[data-edit-promo-dismiss]');
    if (close) {
        close.addEventListener('click', function () {
            var d = new Date();
            d.setTime(d.getTime() + 7 * 24 * 60 * 60 * 1000);
            document.cookie = '<?php echo esc_js( self::DISMISS_COOKIE ); ?>=1; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
            bar.style.display = 'none';
            try {
                window.dataLayer.push({
                    event: 'promo_bar_dismiss',
                    promo_variant: variant,
                    promo_campaign: 'early15_set2026'
                });
            } catch (e) {}
        });
    }

    // ---- Countdown — variant A only ----
    if (variant === 'A') {
        var killDate = new Date(killIso).getTime();
        function pad(n) { return n < 10 ? '0' + n : '' + n; }
        function tick() {
            var diff = killDate - Date.now();
            if (diff <= 0) { bar.style.display = 'none'; return; }
            var d = Math.floor(diff / 86400000);
            var h = Math.floor((diff % 86400000) / 3600000);
            var m = Math.floor((diff % 3600000) / 60000);
            var dEl = document.getElementById('edit-promo-cd-d');
            var hEl = document.getElementById('edit-promo-cd-h');
            var mEl = document.getElementById('edit-promo-cd-m');
            if (dEl) dEl.textContent = pad(d);
            if (hEl) hEl.textContent = pad(h);
            if (mEl) mEl.textContent = pad(m);
        }
        tick();
        setInterval(tick, 60000);
    }
})();
</script>
        <?php
        return (string) ob_get_clean();
    }
}
