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
            // Older browsers — just fire view immediately.
            track( 'newsletter_view', { placement: 'hero' } );
            return;
        }
        var fired = false;
        var io = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( entry.isIntersecting && ! fired ) {
                    fired = true;
                    track( 'newsletter_view', { placement: 'hero' } );
                    io.disconnect();
                }
            } );
        }, { threshold: 0.5 } );
        io.observe( strip );
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
