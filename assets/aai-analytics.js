/* EDIT — first-party engagement tracker for bootcamp product pages.
   No cookies, no PII. Batches events to /wp-json/edit-analytics/v1/track via
   sendBeacon. Page key comes from the <script data-page="..."> attribute. */
(function () {
  'use strict';
  try {
    var ORIGIN = location.origin;
    if (ORIGIN.indexOf('weareedit.io') === -1) return;            // live host only
    var EP = ORIGIN + '/wp-json/edit-analytics/v1/track';
    var self = document.currentScript;
    var PAGE = (self && self.getAttribute('data-page')) || 'aai';

    // session id (per tab) — sessionStorage, no persistent cookie
    var SID = '';
    try { SID = sessionStorage.getItem('ed_sid') || ''; } catch (e) {}
    if (!SID) { SID = (Date.now().toString(36) + Math.abs((Math.random() * 1e9) | 0).toString(36)).slice(0, 20);
      try { sessionStorage.setItem('ed_sid', SID); } catch (e) {} }

    var w = innerWidth;
    var DEVICE = w < 700 ? 'mobile' : (w < 1024 ? 'tablet' : 'desktop');

    var queue = [];
    function push(event, target, section, val, meta) {
      queue.push({ event: event, target: (target || '').slice(0, 180), section: (section || '').slice(0, 60),
        val: val | 0, meta: (meta || '').slice(0, 240) });
      if (queue.length >= 12) flush();
    }
    function flush() {
      if (!queue.length) return;
      var payload = JSON.stringify({ page: PAGE, session: SID, device: DEVICE, events: queue });
      queue = [];
      try {
        if (navigator.sendBeacon) navigator.sendBeacon(EP, new Blob([payload], { type: 'application/json' }));
        else fetch(EP, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: payload, keepalive: true });
      } catch (e) {}
    }
    setInterval(flush, 6000);
    addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') flush(); });
    addEventListener('pagehide', flush);

    // 1. page_view
    var ref = '';
    try { ref = document.referrer ? new URL(document.referrer).hostname : 'direct'; } catch (e) {}
    push('page_view', '', '', 0, ref + (location.search ? ' ' + location.search.slice(0, 80) : ''));

    // 2. time on page
    var t0 = Date.now();
    addEventListener('pagehide', function () { push('time_on_page', '', '', Math.round((Date.now() - t0) / 1000), ''); flush(); });

    // 3. scroll depth (once per milestone)
    var hitDepth = {};
    function onScroll() {
      var de = document.documentElement, sc = (de.scrollTop || document.body.scrollTop);
      var h = de.scrollHeight - de.clientHeight;
      if (h <= 0) return;
      var p = Math.round(sc / h * 100);
      [25, 50, 75, 100].forEach(function (m) { if (p >= m && !hitDepth[m]) { hitDepth[m] = 1; push('scroll_depth', '', '', m, ''); } });
    }
    addEventListener('scroll', throttle(onScroll, 400), { passive: true });

    // 4. section_view (area views) — name from each section's eyebrow
    function sectionName(sec) {
      if (sec.id === 'aai-hero' || sec.className.indexOf('hero') > -1) return 'Hero (topo)';
      var eb = sec.querySelector('.eyebrow');
      if (eb) return eb.textContent.replace(/^\s*\d+\s*/, '').trim().slice(0, 60);
      var h = sec.querySelector('h2, h1, h3');
      return h ? h.textContent.trim().slice(0, 60) : '';
    }
    if ('IntersectionObserver' in window) {
      var seen = {};
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (!en.isIntersecting) return;
          var nm = sectionName(en.target);
          if (nm && !seen[nm]) { seen[nm] = 1; push('section_view', '', nm, 0, ''); }
        });
      }, { threshold: 0.4 });
      // observe AAI content sections (those with an eyebrow) + hero
      var secs = document.querySelectorAll('#aai-hero, .aai-rd section, section.ap2-band, .aai-rd .sec');
      if (!secs.length) secs = document.querySelectorAll('section');
      [].forEach.call(secs, function (s) { io.observe(s); });
    }

    // 5. click classifier
    function txt(el) { return (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 90); }
    function slug(a) { var m = (a.getAttribute('href') || '').match(/\/formacao\/([a-z0-9-]+)/i) || (a.getAttribute('href') || '').match(/\/blog\/([a-z0-9-]+)/i); return m ? m[1] : ''; }
    function nearestSection(el) { var s = el.closest('#aai-hero, .aai-rd section, section.ap2-band, .aai-rd .sec, section'); return s ? sectionName(s) : ''; }

    function classify(el) {
      var a = el.closest('a, button, summary, .ap2-vid, .wf-tool, .btn');
      if (!a) return null;
      var href = (a.getAttribute && a.getAttribute('href')) || '';
      var sec = nearestSection(a);

      if (/wa\.me|api\.whatsapp|whatsapp/i.test(href) || a.classList.contains('fr-wa'))
        return { e: 'whatsapp_click', t: 'whatsapp', s: sec };

      if (a.classList.contains('ap2-vid')) {
        var nm = a.querySelector('.ap2-vnm'); return { e: 'video_play', t: nm ? txt(nm) : (a.getAttribute('data-vimeo') || 'video'), s: sec };
      }
      if (a.classList.contains('wf-tool')) return { e: 'tool_click', t: txt(a), s: sec };

      if (a.closest('.aai-alumni-wall')) { var im = a.querySelector('img'); return { e: 'logo_click', t: (im && (im.alt || im.title)) || 'alumni-employer', s: 'alumni-employers' }; }
      if (a.closest('.other-projects')) { var im2 = a.querySelector('img'); return { e: 'logo_click', t: (im2 && (im2.alt || im2.title)) || (href || 'partner'), s: 'footer-partners' }; }

      if (a.closest('.course-box') || a.classList.contains('course-box')) return { e: 'content_click', t: 'curso:' + (slug(a) || txt(a)), s: sec || 'cursos-relacionados' };
      if (a.classList.contains('art-card') || a.closest('.art-card')) { var ac = (a.closest('.art-card') || a); var ti = ac.querySelector('.art-title'); return { e: 'content_click', t: 'artigo:' + (ti ? txt(ti) : slug(a)), s: sec || 'artigos' }; }
      if (a.tagName === 'SUMMARY' || a.closest('summary')) { var sm = a.tagName === 'SUMMARY' ? a : a.closest('summary'); return { e: 'faq_toggle', t: txt(sm).slice(0, 80), s: sec }; }

      // enrolment / primary CTAs
      if (/#?falaconnosco|#inscrever/i.test(href) || a.classList.contains('swipe-cta') || a.classList.contains('btn'))
        return { e: 'cta_click', t: txt(a) || 'cta', s: sec };

      // generic outbound
      if (/^https?:/i.test(href) && href.indexOf('weareedit.io') === -1)
        return { e: 'outbound_click', t: (function(){try{return new URL(href).hostname;}catch(e){return href.slice(0,80);}})(), s: sec };

      // internal links to other course/site pages → content
      if (href && href.indexOf('#') !== 0) return { e: 'content_click', t: (slug(a) || txt(a) || href).slice(0, 120), s: sec };
      return null;
    }
    document.addEventListener('click', function (ev) {
      var r = classify(ev.target);
      if (r) push(r.e, r.t, r.s, 0, '');
    }, true);

    // 6. Naiara studio video play
    var nv = document.querySelector('.nq2-video video');
    if (nv) nv.addEventListener('play', function () { push('video_play', 'naiara-studio', 'quem-te-vai-formar', 0, ''); }, { once: true });

    function throttle(fn, ms) { var last = 0, tmr; return function () { var now = Date.now(); if (now - last >= ms) { last = now; fn(); } else { clearTimeout(tmr); tmr = setTimeout(function () { last = Date.now(); fn(); }, ms); } }; }
  } catch (e) { /* never break the page */ }
})();
