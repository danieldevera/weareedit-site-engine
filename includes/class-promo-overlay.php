<?php
/**
 * Promo overlay — site-wide single-shot modal banner.
 *
 * Used for time-boxed CEO campaigns. v1: "Early 15% — Edições Setembro 2026".
 * Centered modal lightbox over a black 60% backdrop. Cookie/localStorage
 * gate so it shows once per browser. ESC / X / backdrop-click dismisses.
 *
 * Toggle the campaign on/off via the ACTIVE constant. Copy + CTA URL live
 * inline so a marketer can edit a single file to change the offer (no
 * settings UI yet — can be promoted later if multiple campaigns concurrent).
 *
 * @package WeareEditSiteEngine
 * @since   1.5.236
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Promo_Overlay {

    /** Flip to false to disable the overlay without removing the file. */
    const ACTIVE        = true;

    /** localStorage key — bump to force the overlay to show again to all users. */
    const STORAGE_KEY   = 'edit_promo_early15_seen';

    /** Delay before the modal fades in, milliseconds. */
    const SHOW_DELAY_MS = 10000;

    /** Auto-mark as seen after this many ms of being visible. */
    const AUTO_MARK_MS  = 30000;

    /** Hard kill date — modal hides itself after this regardless of cookie. */
    const KILL_DATE     = '2026-09-30 23:59:59';

    public static function init(): void {
        if ( ! self::ACTIVE ) return;
        if ( strtotime( self::KILL_DATE ) < time() ) return;
        add_action( 'wp_footer', [ __CLASS__, 'render' ], 50 );
    }

    public static function render(): void {
        if ( is_admin() ) return;
        if ( is_feed() ) return;
        ?>
<style id="edit-promo-overlay-css">
#edit-promo-overlay { position: fixed; inset: 0; z-index: 99999; display: none; opacity: 0; transition: opacity 280ms ease; }
#edit-promo-overlay.is-open { display: flex; align-items: center; justify-content: center; opacity: 1; }
#edit-promo-overlay__backdrop { position: absolute; inset: 0; background: rgba(10,10,10,0.65); backdrop-filter: blur(2px); -webkit-backdrop-filter: blur(2px); }
#edit-promo-overlay__card {
    position: relative; z-index: 1; width: min(560px, calc(100vw - 32px)); max-height: calc(100vh - 32px);
    background: #ffdd06; padding: 56px 48px 48px; box-sizing: border-box;
    font-family: 'SctoGroteskA', 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #0a0a0a;
    border: 3px solid #0a0a0a; overflow-y: auto;
    transform: translateY(16px) scale(0.96); transition: transform 320ms cubic-bezier(0.16,1,0.30,1);
}
#edit-promo-overlay.is-open #edit-promo-overlay__card { transform: translateY(0) scale(1); }
#edit-promo-overlay__close {
    position: absolute; top: 14px; right: 14px; width: 36px; height: 36px;
    border: 0; background: transparent; cursor: pointer; padding: 0;
    font-size: 26px; line-height: 36px; color: #0a0a0a; font-family: inherit;
    border-radius: 50%; transition: background 0.15s ease;
}
#edit-promo-overlay__close:hover { background: rgba(10,10,10,0.08); }
#edit-promo-overlay__close:focus-visible { outline: 2px solid #0a0a0a; outline-offset: 2px; }
#edit-promo-overlay__logo { display: block; height: 22px; width: auto; margin-bottom: 28px; }
#edit-promo-overlay__eyebrow { margin: 0 0 14px; font-size: 11px; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; color: #0a0a0a; }
#edit-promo-overlay__headline {
    margin: 0 0 18px; font-size: 64px; line-height: 0.95; font-weight: 800;
    letter-spacing: -0.03em; color: #0a0a0a;
}
#edit-promo-overlay__headline .pct { color: #f92869; }
#edit-promo-overlay__sub { margin: 0 0 28px; font-size: 18px; line-height: 1.35; color: #0a0a0a; max-width: 380px; }
#edit-promo-overlay__sub strong { font-weight: 700; }
#edit-promo-overlay__cta {
    display: inline-block; padding: 18px 28px; font-size: 14px; font-weight: 700;
    letter-spacing: 0.06em; text-transform: uppercase; color: #0a0a0a;
    background: transparent; text-decoration: none; border: 0; cursor: pointer;
    font-family: inherit;
}
#edit-promo-overlay__card .swipe-cta { background: #0a0a0a; }
#edit-promo-overlay__card .swipe-cta .swipe-label { color: #ffdd06; padding: 18px 28px; display: inline-flex; }
#edit-promo-overlay__card .swipe-cta:hover .swipe-label { color: #ffdd06; }
@media (max-width: 640px) {
    #edit-promo-overlay__card { width: calc(100vw - 24px); padding: 48px 28px 32px; }
    #edit-promo-overlay__headline { font-size: 48px; }
    #edit-promo-overlay__sub { font-size: 16px; }
}
@media (prefers-reduced-motion: reduce) {
    #edit-promo-overlay,
    #edit-promo-overlay__card { transition: none !important; transform: none !important; }
}
</style>

<div id="edit-promo-overlay" role="dialog" aria-modal="true" aria-labelledby="edit-promo-overlay__headline" aria-hidden="true">
    <div id="edit-promo-overlay__backdrop" data-edit-promo-dismiss></div>
    <div id="edit-promo-overlay__card">
        <button type="button" id="edit-promo-overlay__close" data-edit-promo-dismiss aria-label="Fechar promoção">&times;</button>
        <img id="edit-promo-overlay__logo" src="https://weareedit.io/wp-content/uploads/2026/05/edit-extended-logo.png" alt="EDIT.">
        <p id="edit-promo-overlay__eyebrow">Edições Setembro 2026</p>
        <h2 id="edit-promo-overlay__headline">Early <span class="pct">15%</span></h2>
        <p id="edit-promo-overlay__sub">Garante o teu lugar com <strong>-15%</strong> nas edições de Setembro.<br>Inscrições até <strong>30 de Junho</strong>.</p>
        <a href="<?php echo esc_url( home_url( '/formacao/?campanha=early15' ) ); ?>" id="edit-promo-overlay__cta" class="swipe-cta" data-edit-promo-cta>
            <span class="swipe-layer swipe-pink"></span>
            <span class="swipe-layer swipe-teal"></span>
            <span class="swipe-layer swipe-black"></span>
            <span class="swipe-label">Ver cursos elegíveis &rsaquo;</span>
        </a>
    </div>
</div>

<script id="edit-promo-overlay-js">
(function () {
    'use strict';
    var STORAGE_KEY = '<?php echo esc_js( self::STORAGE_KEY ); ?>';
    var SHOW_DELAY  = <?php echo (int) self::SHOW_DELAY_MS; ?>;
    var AUTO_MARK   = <?php echo (int) self::AUTO_MARK_MS; ?>;

    function seen() {
        try { return window.localStorage.getItem( STORAGE_KEY ) === '1'; }
        catch ( _ ) { return document.cookie.indexOf( STORAGE_KEY + '=1' ) !== -1; }
    }
    function markSeen() {
        try { window.localStorage.setItem( STORAGE_KEY, '1' ); }
        catch ( _ ) {
            // localStorage blocked (e.g. Safari private mode) — fall back to cookie
            var d = new Date(); d.setTime( d.getTime() + 1000*60*60*24*120 );
            document.cookie = STORAGE_KEY + '=1; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
        }
    }

    var overlay = document.getElementById( 'edit-promo-overlay' );
    if ( ! overlay ) return;
    if ( seen() ) return;

    var prevFocus = null;
    var firstFocusable = null;
    var lastFocusable  = null;

    function open() {
        prevFocus = document.activeElement;
        overlay.classList.add( 'is-open' );
        overlay.setAttribute( 'aria-hidden', 'false' );
        document.documentElement.style.overflow = 'hidden';
        var focusables = overlay.querySelectorAll( 'button, a[href], [tabindex]:not([tabindex="-1"])' );
        if ( focusables.length ) {
            firstFocusable = focusables[0];
            lastFocusable  = focusables[ focusables.length - 1 ];
            // Land focus on the CTA, not the close button, so keyboard users see the offer
            var cta = overlay.querySelector( '[data-edit-promo-cta]' );
            ( cta || firstFocusable ).focus();
        }
        // Mark seen immediately on first view — Daniel's directive.
        // Cookie ALSO reinforced on dismiss + CTA click. One-shot guaranteed.
        markSeen();
        try {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ event: 'promo_overlay_view', campaign: 'early15_set2026' });
        } catch ( _ ) {}
    }
    function close() {
        if ( ! overlay.classList.contains( 'is-open' ) ) return;
        overlay.classList.remove( 'is-open' );
        overlay.setAttribute( 'aria-hidden', 'true' );
        document.documentElement.style.overflow = '';
        markSeen();
        if ( prevFocus && typeof prevFocus.focus === 'function' ) {
            try { prevFocus.focus(); } catch ( _ ) {}
        }
        try {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ event: 'promo_overlay_dismiss', campaign: 'early15_set2026' });
        } catch ( _ ) {}
    }

    // Dismiss handlers
    overlay.addEventListener( 'click', function ( e ) {
        var dismiss = e.target.closest ? e.target.closest( '[data-edit-promo-dismiss]' ) : null;
        if ( dismiss ) { e.preventDefault(); close(); }
    } );
    document.addEventListener( 'keydown', function ( e ) {
        if ( ! overlay.classList.contains( 'is-open' ) ) return;
        if ( e.key === 'Escape' || e.keyCode === 27 ) { close(); return; }
        // Focus trap
        if ( e.key === 'Tab' && firstFocusable && lastFocusable ) {
            if ( e.shiftKey && document.activeElement === firstFocusable ) {
                e.preventDefault(); lastFocusable.focus();
            } else if ( ! e.shiftKey && document.activeElement === lastFocusable ) {
                e.preventDefault(); firstFocusable.focus();
            }
        }
    } );
    // CTA click — count as engagement, mark seen, let navigation proceed
    var cta = overlay.querySelector( '[data-edit-promo-cta]' );
    if ( cta ) {
        cta.addEventListener( 'click', function () {
            markSeen();
            try {
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({ event: 'promo_overlay_cta', campaign: 'early15_set2026' });
            } catch ( _ ) {}
        } );
    }

    // Fire after dwell delay
    setTimeout( open, SHOW_DELAY );
})();
</script>
        <?php
    }
}
