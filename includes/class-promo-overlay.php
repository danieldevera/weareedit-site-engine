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
    const ACTIVE        = true; // v2 campaign: Early Bird 10% ate 31 Julho (Daniel, 2026-07-02)

    /** Test mode: restrict rendering to the path below. Set to '' (empty
     *  string) to render site-wide. Useful for staging a campaign before
     *  letting it loose on every visitor. */
    const TEST_PATH     = '';

    /** localStorage key — bump to force the overlay to show again to all users. */
    const STORAGE_KEY   = 'edit_promo_early10_seen';

    /** Delay before the modal fades in, milliseconds.
     *  Lowered to 2s during QA — bump back to 10000 before site-wide rollout. */
    const SHOW_DELAY_MS = 2000;

    /** Auto-mark as seen after this many ms of being visible. */
    const AUTO_MARK_MS  = 30000;

    /** Hard kill date — modal hides itself after this regardless of cookie. */
    const KILL_DATE     = '2026-07-31 23:59:59';

    public static function init(): void {
        if ( ! self::ACTIVE ) return;
        if ( strtotime( self::KILL_DATE ) < time() ) return;
        add_action( 'wp_footer', [ __CLASS__, 'render' ], 50 );
    }

    public static function render(): void {
        if ( is_admin() ) return;
        if ( is_feed() ) return;
        // Path gate — only render on the configured test path when set.
        if ( self::TEST_PATH !== '' ) {
            $request_path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( (string) $_SERVER['REQUEST_URI'], '?' ) : '';
            $request_path = '/' . trim( (string) $request_path, '/' ) . '/';
            $test_path    = '/' . trim( self::TEST_PATH, '/' ) . '/';
            if ( $request_path !== $test_path ) return;
        }
        ?>
<style id="edit-promo-overlay-css">
/* Floating bottom-left card (NOT a modal) — site stays fully interactive.
   Sits above the reCAPTCHA badge via z-index and bottom offset.

   Why visibility (not display): swapping display:none → block in the same
   frame as opacity 0 → 1 makes the browser skip the transition entirely
   (the element was unrendered, then suddenly painted). Using
   visibility:hidden + pointer-events:none keeps the element in the box
   model the whole time so the opacity/transform tween actually plays. */
#edit-promo-overlay {
    position: fixed; left: 20px; bottom: 90px; z-index: 99999;
    width: 320px; height: 320px;
    visibility: hidden; opacity: 0;
    transform: translate3d(0, 24px, 0);
    /* ease-out-sine — the gentlest standard easing. Decelerates softly
       across the whole motion rather than slamming to a stop. */
    transition: opacity 1100ms cubic-bezier(0.39, 0.575, 0.565, 1),
                transform 1100ms cubic-bezier(0.39, 0.575, 0.565, 1),
                visibility 0ms linear 1100ms;
    pointer-events: none;
    will-change: opacity, transform;
}
#edit-promo-overlay.is-open {
    visibility: visible; opacity: 1;
    transform: translate3d(0, 0, 0);
    pointer-events: auto;
    transition: opacity 1100ms cubic-bezier(0.39, 0.575, 0.565, 1),
                transform 1100ms cubic-bezier(0.39, 0.575, 0.565, 1),
                visibility 0ms linear 0ms;
}
#edit-promo-overlay__card {
    position: relative; width: 100%; height: 100%;
    background: #ffdd06; padding: 28px 24px 22px; box-sizing: border-box;
    font-family: 'SctoGroteskA', 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #0a0a0a;
    border: 3px solid #0a0a0a; box-shadow: 0 14px 32px rgba(0,0,0,0.35);
    display: flex; flex-direction: column;
}
#edit-promo-overlay__close {
    position: absolute; top: 8px; right: 8px; width: 30px; height: 30px;
    border: 0; background: transparent; cursor: pointer; padding: 0;
    font-size: 22px; line-height: 30px; color: #0a0a0a; font-family: inherit;
    border-radius: 50%; transition: background 0.15s ease;
}
#edit-promo-overlay__close:hover { background: rgba(10,10,10,0.10); }
#edit-promo-overlay__close:focus-visible { outline: 2px solid #0a0a0a; outline-offset: 2px; }
#edit-promo-overlay__eyebrow { margin: 0 0 14px; font-size: 11px; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; color: #0a0a0a; }
#edit-promo-overlay__eyebrow .promo { color: #0090eb; }
#edit-promo-overlay__headline {
    margin: 0 0 16px; font-size: 56px; line-height: 0.95; font-weight: 800;
    letter-spacing: -0.03em; color: #0a0a0a;
}
#edit-promo-overlay__headline .pct { color: #f92869; }
#edit-promo-overlay__sub { margin: 0 0 20px; font-size: 16px; line-height: 1.4; color: #0a0a0a; }
#edit-promo-overlay__sub strong { font-weight: 700; }
#edit-promo-overlay__card .swipe-cta {
    background: #0a0a0a; margin-top: auto; text-align: center; align-self: stretch;
}
#edit-promo-overlay__card .swipe-cta .swipe-label {
    color: #ffdd06; padding: 12px 18px; display: inline-flex; font-size: 11px;
    letter-spacing: 0.06em; text-transform: uppercase; font-weight: 700;
}
#edit-promo-overlay__card .swipe-cta:hover .swipe-label { color: #ffdd06; }

/* Items: same gentle ease-out-sine, longer duration, ~120ms apart so
   the cascade unfolds at a leisurely pace alongside the pop-up motion. */
#edit-promo-overlay .edit-promo-stagger {
    opacity: 0;
    transition: opacity 700ms cubic-bezier(0.39, 0.575, 0.565, 1);
    will-change: opacity;
}
#edit-promo-overlay.is-open .edit-promo-stagger { opacity: 1; }
#edit-promo-overlay.is-open .edit-promo-stagger[data-stagger="1"] { transition-delay: 250ms; }
#edit-promo-overlay.is-open .edit-promo-stagger[data-stagger="2"] { transition-delay: 370ms; }
#edit-promo-overlay.is-open .edit-promo-stagger[data-stagger="3"] { transition-delay: 490ms; }
#edit-promo-overlay.is-open .edit-promo-stagger[data-stagger="4"] { transition-delay: 610ms; }

@media (max-width: 640px) {
    #edit-promo-overlay { left: 12px; bottom: 78px; width: calc(100vw - 24px); max-width: 320px; height: auto; min-height: 260px; }
}
@media (prefers-reduced-motion: reduce) {
    #edit-promo-overlay,
    #edit-promo-overlay .edit-promo-stagger { transition: none !important; }
}
</style>

<div id="edit-promo-overlay" role="region" aria-labelledby="edit-promo-overlay__headline" aria-hidden="true">
    <div id="edit-promo-overlay__card">
        <button type="button" id="edit-promo-overlay__close" data-edit-promo-dismiss aria-label="Fechar promoção">&times;</button>
        <p id="edit-promo-overlay__eyebrow" class="edit-promo-stagger" data-stagger="1"><span class="promo">PROMO</span> &middot; Setembro 2026</p>
        <h2 id="edit-promo-overlay__headline" class="edit-promo-stagger" data-stagger="2">Early Bird <span class="pct">10%</span></h2>
        <p id="edit-promo-overlay__sub" class="edit-promo-stagger" data-stagger="3">-10% nos cursos de Setembro.<br>Inscrições até <strong>31 Julho</strong>.</p>
        <a href="<?php echo esc_url( home_url( '/formacao/?campanha=early10' ) ); ?>" id="edit-promo-overlay__cta" class="swipe-cta edit-promo-stagger" data-stagger="4" data-edit-promo-cta>
            <span class="swipe-layer swipe-pink"></span>
            <span class="swipe-layer swipe-teal"></span>
            <span class="swipe-layer swipe-black"></span>
            <span class="swipe-label">Ver cursos &rsaquo;</span>
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
    // QA override: ?promo=force re-fires the popup even after it's been
    // marked seen (handy for screenshots, screen recordings, sales demos).
    var force = /[?&]promo=force\b/.test( window.location.search );
    if ( ! force && seen() ) return;

    function open() {
        overlay.classList.add( 'is-open' );
        overlay.setAttribute( 'aria-hidden', 'false' );
        // NOT a modal — site stays active. No focus trap, no body scroll lock.
        // Mark seen immediately on first view (Daniel's directive). Reinforced
        // on dismiss + CTA click.
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
        markSeen();
        try {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ event: 'promo_overlay_dismiss', campaign: 'early15_set2026' });
        } catch ( _ ) {}
    }

    // Dismiss via the X button only (no backdrop in this floating variant).
    overlay.addEventListener( 'click', function ( e ) {
        var dismiss = e.target.closest ? e.target.closest( '[data-edit-promo-dismiss]' ) : null;
        if ( dismiss ) { e.preventDefault(); close(); }
    } );
    // ESC also closes — works even though the popup isn't a modal, since
    // keyboard users may instinctively reach for it.
    document.addEventListener( 'keydown', function ( e ) {
        if ( ! overlay.classList.contains( 'is-open' ) ) return;
        if ( e.key === 'Escape' || e.keyCode === 27 ) close();
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
