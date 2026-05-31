/* ============================================================================
   EDIT. — Newsletter Signup Strip (frontend)
   Injects a hero-adjacent newsletter capture on the homepage only, handles
   submit + dataLayer/gtag event tracking, and renders success/error states.

   No build step: vanilla ES5-safe JS, no dependencies, no module loading.
   ========================================================================== */

(function () {
    'use strict';

    if ( typeof window.editNewsletter !== 'object' || ! window.editNewsletter ) return;
    var CFG = window.editNewsletter;

    // Only inject on the homepage. The PHP enqueue already gates by is_front_page(),
    // but belt-and-suspenders against caching mishaps that ship JS to other pages.
    if ( ! /(^|\s)(home|page-template-page-home)(\s|$)/.test( document.body.className || '' ) ) return;

    // Avoid double-injection on bfcache restore or SPA-like navigations.
    if ( document.getElementById( 'edit-newsletter-strip' ) ) return;

    // ---------------------------------------------------------------- helpers

    function track( event, payload ) {
        try {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push( Object.assign( { event: event }, payload || {} ) );
        } catch ( _ ) {}
        try {
            if ( typeof window.gtag === 'function' ) {
                window.gtag( 'event', event, payload || {} );
            }
        } catch ( _ ) {}
    }

    function emailDomain( email ) {
        var at = String( email || '' ).lastIndexOf( '@' );
        return at >= 0 ? String( email ).slice( at + 1 ).toLowerCase() : '';
    }

    function isEmailLike( s ) {
        // Lightweight format check; server enforces is_email() canonically.
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( String( s || '' ).trim() );
    }

    // ----------------------------------------------------------------- render

    var copy = CFG.copy || {};
    var strip = document.createElement( 'section' );
    strip.id = 'edit-newsletter-strip';
    strip.setAttribute( 'aria-labelledby', 'edit-newsletter-strip__headline' );
    strip.innerHTML = [
        '<div id="edit-newsletter-strip__inner">',
        '  <div id="edit-newsletter-strip__copy">',
        '    <p id="edit-newsletter-strip__eyebrow">' + escapeHTML( copy.eyebrow || 'Newsletter EDIT.' ) + '</p>',
        '    <h2 id="edit-newsletter-strip__headline">' + escapeHTML( copy.headline || 'Recebe a próxima edição.' ).replace( /\.$/, '<span class="dot">.</span>' ) + '</h2>',
        '    <p id="edit-newsletter-strip__pitch">' + escapeHTML( copy.pitch || '' ) + '</p>',
        '    <p id="edit-newsletter-strip__social">' + escapeHTML( copy.social || '' ) + '</p>',
        '  </div>',
        '  <form id="edit-newsletter-strip__form" novalidate>',
        '    <div id="edit-newsletter-strip__field">',
        '      <label class="screen-reader-text" for="edit-newsletter-strip__email">Email</label>',
        '      <input id="edit-newsletter-strip__email" type="email" name="email" autocomplete="email" inputmode="email" required',
        '             placeholder="' + escapeAttr( copy.placeholder || 'o.teu.email@dominio.pt' ) + '">',
        '      <button id="edit-newsletter-strip__submit" type="submit" class="swipe-cta">',
        '        <span class="swipe-layer swipe-pink"></span>',
        '        <span class="swipe-layer swipe-teal"></span>',
        '        <span class="swipe-layer swipe-black"></span>',
        '        <span class="swipe-label">' + escapeHTML( copy.submit || 'Subscrever' ) + ' &rarr;</span>',
        '      </button>',
        '    </div>',
        '    <p id="edit-newsletter-strip__status" data-state="" aria-live="polite"></p>',
        '    <p id="edit-newsletter-strip__disclaimer">' + escapeHTML( copy.disclaimer || '' ) + '</p>',
        '    <div id="edit-newsletter-strip__honeypot" aria-hidden="true">',
        '      <label for="edit-newsletter-strip__hp">Website</label>',
        '      <input id="edit-newsletter-strip__hp" type="text" name="website" tabindex="-1" autocomplete="off">',
        '    </div>',
        '  </form>',
        '  <div id="edit-newsletter-strip__success" role="status">',
        '    <strong>' + escapeHTML( copy.success_title || 'Obrigado.' ) + '</strong>',
        '    <span id="edit-newsletter-strip__success-msg">' + escapeHTML( copy.success || '' ) + '</span>',
        '  </div>',
        '</div>',
    ].join( '' );

    function escapeHTML( s ) {
        return String( s == null ? '' : s )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' );
    }
    function escapeAttr( s ) {
        return escapeHTML( s ).replace( /"/g, '&quot;' );
    }

    // ----------------------------------------------------------------- inject

    function findInjectionPoint() {
        // 1) The locked homepage hero is <section class="hero">. Inject right
        //    after it so the strip sits between the hero and `.courses-boxes-home`.
        //    Verified via the live HTML on 2026-05-31.
        var hero = document.querySelector( 'section.hero' );
        if ( hero ) return hero;

        // 2) Fallback: find the first <section> inside the main content area.
        var first = document.querySelector( 'main section, #main section, .site-main section, .content section' );
        if ( first ) return first;

        // 3) Last resort: first <section> on the page.
        var anySection = document.querySelector( 'section' );
        return anySection || null;
    }

    function injectStrip() {
        var anchor = findInjectionPoint();
        if ( anchor && anchor.parentNode ) {
            anchor.parentNode.insertBefore( strip, anchor.nextSibling );
        } else {
            // Hard fallback: append to body so the strip exists somewhere.
            document.body.appendChild( strip );
        }
        wireForm();
        observeImpression();
    }

    // --------------------------------------------------------------- impressions

    function observeImpression() {
        if ( ! ( 'IntersectionObserver' in window ) ) {
            // Older browsers — fire view immediately + show.
            strip.classList.add( 'is-in-view' );
            track( 'newsletter_view', { placement: 'hero' } );
            return;
        }
        var fired = false;
        var io = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( entry.isIntersecting && ! fired ) {
                    fired = true;
                    // Trigger entrance fade-up + fire view event.
                    strip.classList.add( 'is-in-view' );
                    track( 'newsletter_view', { placement: 'hero' } );
                    io.disconnect();
                }
            } );
        }, { threshold: 0.15, rootMargin: '0px 0px -10% 0px' } );
        io.observe( strip );
    }

    /* ------------------------------------------------------------- confetti
       Fires on successful subscription. Layer is FIXED to the viewport so
       pieces can spill across the full screen (not clipped to the strip).
       Origin = strip centre projected to viewport coords. ~100 pieces, 4-5s
       fall with sway, brand palette only. */
    function fireConfetti() {
        if ( window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ) return;

        var layer = document.createElement( 'div' );
        layer.id = 'edit-newsletter-strip__confetti';
        layer.setAttribute( 'aria-hidden', 'true' );
        document.body.appendChild( layer );

        var colors = [ '#ffdd06', '#f92869', '#60c5b3', '#0a0a0a', '#ec8172' ];

        // Compute strip centre in viewport coordinates (layer is position:fixed,
        // so children use viewport-relative left/top).
        var rect    = strip.getBoundingClientRect();
        var originX = rect.left + rect.width  * 0.5;
        var originY = rect.top  + rect.height * 0.35;

        var vw = Math.max( document.documentElement.clientWidth,  window.innerWidth  || 0 );
        var vh = Math.max( document.documentElement.clientHeight, window.innerHeight || 0 );

        var pieces = 110;
        for ( var i = 0; i < pieces; i++ ) {
            var p = document.createElement( 'span' );
            p.className = 'edit-confetti-piece';
            // Small random origin offset so the burst doesn't look like a single point.
            var jitter = 40;
            p.style.left = ( originX + ( Math.random() * jitter * 2 - jitter ) ) + 'px';
            p.style.top  = ( originY + ( Math.random() * jitter - jitter / 2 ) ) + 'px';
            p.style.backgroundColor = colors[ Math.floor( Math.random() * colors.length ) ];

            // Horizontal travel: full viewport width random
            var dx = ( Math.random() * 1.6 - 0.8 ) * vw * 0.55;
            // Vertical travel — pieces shoot UP first then fall.
            //   --peak  (negative): how high above origin each piece rises
            //   --dy    (positive): final landing position below origin
            var peak    = -( 180 + Math.random() * 280 );  // -180 to -460px above origin
            var maxDown = vh - originY + 80;
            var dy      = ( 0.55 + Math.random() * 0.55 ) * maxDown;

            var rot   = ( Math.random() * 4 + 1.5 ) * 180; // 270-990deg, less spinny
            var delay = Math.random() * 420;               // 0-420ms stagger
            var dur   = 3600 + Math.random() * 1400;       // 3.6-5.0s per piece

            p.style.setProperty( '--dx',   dx   + 'px' );
            p.style.setProperty( '--dy',   dy   + 'px' );
            p.style.setProperty( '--peak', peak + 'px' );
            p.style.setProperty( '--rot',  rot  + 'deg' );
            p.style.setProperty( '--dur',  dur  + 'ms' );
            p.style.animationDelay = delay + 'ms';

            // Vary shape — paper rectangles, squares, circles, thin strips.
            var shape = Math.random();
            if ( shape < 0.25 ) {
                p.style.width  = '6px';
                p.style.height = '18px';
            } else if ( shape < 0.5 ) {
                p.style.width  = '12px';
                p.style.height = '8px';
            } else if ( shape < 0.7 ) {
                p.style.width  = '9px';
                p.style.height = '9px';
                p.style.borderRadius = '50%';
            }
            // else default 10x16

            layer.appendChild( p );
        }

        // Cleanup after the longest piece finishes (delay 420ms + 5000ms anim).
        setTimeout( function () { if ( layer.parentNode ) layer.parentNode.removeChild( layer ); }, 5600 );
    }

    // -------------------------------------------------------------- form wiring

    function wireForm() {
        var form    = document.getElementById( 'edit-newsletter-strip__form' );
        var email   = document.getElementById( 'edit-newsletter-strip__email' );
        var submit  = document.getElementById( 'edit-newsletter-strip__submit' );
        var status  = document.getElementById( 'edit-newsletter-strip__status' );
        var success = document.getElementById( 'edit-newsletter-strip__success-msg' );
        var hp      = document.getElementById( 'edit-newsletter-strip__hp' );
        var focused = false;

        email.addEventListener( 'focus', function () {
            if ( ! focused ) {
                focused = true;
                track( 'newsletter_focus', { placement: 'hero' } );
            }
        } );

        function setStatus( state, msg ) {
            status.setAttribute( 'data-state', state );
            status.textContent = msg || '';
        }
        function setInvalid( yes ) {
            email.setAttribute( 'aria-invalid', yes ? 'true' : 'false' );
        }

        form.addEventListener( 'submit', function ( e ) {
            e.preventDefault();
            setStatus( '', '' );
            setInvalid( false );

            var value = ( email.value || '' ).trim();
            if ( ! isEmailLike( value ) ) {
                setInvalid( true );
                setStatus( 'error', copy.invalid || 'Email inválido.' );
                track( 'newsletter_error', { placement: 'hero', error_type: 'invalid_email' } );
                email.focus();
                return;
            }

            track( 'newsletter_submit', { placement: 'hero', email_domain: emailDomain( value ) } );

            submit.disabled = true;
            var labelEl = submit.querySelector( '.swipe-label' );
            var origLabel = labelEl ? labelEl.textContent : '';
            if ( labelEl ) labelEl.textContent = 'A enviar…';

            var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
            // Send the WP nonce when present — required for is_user_logged_in()
            // to return true in the REST handler (so the admin bypass kicks in).
            if ( CFG.nonce ) headers[ 'X-WP-Nonce' ] = CFG.nonce;

            fetch( CFG.restUrl, {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
                body: JSON.stringify( {
                    email:      value,
                    website:    hp.value || '',
                    placement:  'hero',
                    source_url: location.href,
                } ),
            } )
            .then( function ( r ) {
                return r.json().then( function ( body ) { return { code: r.status, body: body }; } );
            } )
            .then( function ( res ) {
                submit.disabled = false;
                if ( labelEl ) labelEl.textContent = origLabel;

                if ( res.body && res.body.status === 'ok' ) {
                    var isDup = /já estás subscrito|já estás na lista/i.test( res.body.message || '' );
                    track( isDup ? 'newsletter_duplicate' : 'newsletter_success', {
                        placement: 'hero',
                        email_domain: emailDomain( value ),
                    } );
                    success.textContent = res.body.message || copy.success || '';
                    strip.setAttribute( 'data-state', 'success' );
                    // Celebrate AFTER the box reveal completes (box wipe is 680ms,
                    // first text appears at ~480ms). Fire confetti at 700ms so the
                    // burst lands during/just after text settles — feels like a
                    // payoff to the reveal sequence rather than competing with it.
                    if ( ! isDup ) setTimeout( fireConfetti, 700 );
                    return;
                }

                var msg = ( res.body && res.body.message ) || copy.error || 'Erro.';
                setStatus( 'error', msg );
                track( 'newsletter_error', {
                    placement: 'hero',
                    error_type: res.code === 429 ? 'rate_limit' : ( res.code >= 500 ? 'server_error' : 'validation' ),
                } );
            } )
            .catch( function () {
                submit.disabled = false;
                if ( labelEl ) labelEl.textContent = origLabel;
                setStatus( 'error', copy.error || 'Erro.' );
                track( 'newsletter_error', { placement: 'hero', error_type: 'network' } );
            } );
        } );
    }

    // -------------------------------------------------------------- bootstrap

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', injectStrip );
    } else {
        injectStrip();
    }
})();
