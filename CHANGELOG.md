# Changelog
## v1.5.160 — 2026-05-31 (Animations: more perceivable + smoother fade)
- **Entrance fade-up:** larger displacement (60px translateY + 0.985 scale) and longer duration (1100ms) so it's clearly perceived during scroll. Threshold raised to fire when strip is genuinely on-screen (15% visibility + 10% bottom rootMargin).
- **Dot pulse:** more visible heartbeat — scale 1 → 1.38 (was 1.18), faster 1.8s cycle (was 2.4s), with a slight upward bob.
- **Confetti fade:** pieces hold opacity 1 through 92% of duration, then snap to 0 in the final 8%. Eliminates the lingering "hang" at landing. Adds two-phase physics easing (ease-out climb + ease-in fall) plus rotateX waypoints for paper-flutter feel.

## v1.5.159 — 2026-05-31 (Confetti: up-then-down arc)
- Confetti now POPS UP first (180-460px above origin at 35% of animation) then falls to its landing position. New CSS var `--peak` carries the apex height per piece. Animation timing-function switched to `linear` so the keyframe waypoints carry the parabolic arc themselves (ease-out flattened it).

## v1.5.158 — 2026-05-31 (Confetti: slower, fuller-screen, less rigid)
- Confetti now fixed to viewport (not clipped to the yellow strip) so pieces spill across the full screen.
- 110 pieces (was 64). Per-piece duration 3.6-5.0s (was 2.2s). Larger horizontal spread (~55% of viewport width). Less aggressive rotation. Origin jitter so the burst doesn't look like a single point.
- Keyframes interpolated through 40% + 70% waypoints so pieces sway organically rather than tracing a rigid straight line.
- Cleanup timer extended to 5.6s.

## v1.5.157 — 2026-05-31 (Newsletter pitch: hard line break between sentences)
- Inserted \n between the two pitch sentences + added `white-space: pre-line` CSS to the pitch element so the second sentence renders on a new line. Tighter visual hierarchy.

## v1.5.156 — 2026-05-31 (Newsletter pitch: two-sentence rewrite)
- Pitch reworked to two sentences: `Notas semanais, entrevistas inéditas com tutores, cursos em pré-lançamento. Conteúdos de especialidade com curadoria do nosso Fundador.` — first sentence lists the three deliverables, second sentence elevates the founder-curated promise.

## v1.5.155 — 2026-05-31 (Newsletter strip: animations)
- **Entrance fade-up.** Strip starts at `opacity:0; translateY(28px)` — fades in + rises when it enters viewport (IntersectionObserver, 25% threshold). 700ms ease. Matches the site's existing `.wow animate__fadeInUp` pattern.
- **Pulsing pink dot.** The `.` after "edição" pulses gently (scale 1 → 1.18, 2.4s loop). Subtle attention-draw on the brand mark.
- **Confetti on success.** ~64 small coloured rectangles erupt from the centre of the strip when subscription succeeds. Brand palette only (yellow / pink / teal / coral / black). 2.2s fall with random rotation. Skipped on duplicate-subscribe (less surprising for returning users) and respects `prefers-reduced-motion`.

## v1.5.154 — 2026-05-31 (Newsletter: send WP nonce so admin bypass works)
- The frontend fetch wasn't sending `X-WP-Nonce`, so the REST handler never saw the logged-in admin session even with valid cookies. `is_user_logged_in()` returned false → admin rate-limit bypass (v1.5.153) didn't fire.
- Fix: pass nonce via `wp_localize_script`, attach as `X-WP-Nonce` header in fetch. Admin testing now bypasses rate limit as intended.

## v1.5.153 — 2026-05-31 (Newsletter form: admins bypass rate limit)
- Logged-in WP admins (`manage_options` capability) now bypass the 5-per-hour-per-IP rate limit on the newsletter signup endpoint. Lets us test the form repeatedly from the same office IP without locking ourselves out. Public visitors still rate-limited.

## v1.5.152 — 2026-05-31 (Homepage About: strip auto-injected links)
- The about-section paragraphs (`Desde 2011 que a EDIT.…` block on the homepage) were getting their brand keywords (`DGERT`, `UX/UI Design`, `Data Science`, `Inteligência Artificial`, `Marketing Digital`, `Programação`) wrapped in auto-injected `<a>` tags — most likely by InLinks (live since v1.5.20). Daniel asked the paragraph kept as plain prose.
- New `EDIT_Output_Buffer::strip_about_links()` — inline JS in `<footer>` that strips `<a>` children from `section.about .col-sm-6 p` on the homepage. Runs at DOMContentLoaded + 4 staggered timers (0.5s / 1.5s / 3s / 6s) to catch lazy injection.

## v1.5.151 — 2026-05-31 (Pitch line full PT translation)
- Translated mixed-language pitch to full Portuguese: `Notas semanais. Entrevistas inéditas com tutores. Cursos em pré-lançamento. Curadoria do nosso Fundador.` — preserves the founder-curated promise but reads native to PT-PT audience.

## v1.5.150 — 2026-05-31 (Newsletter strip: copy + spacing + font)
- **Copy update.** Pitch line now reads `Notas semanais. Entrevistas inéditas com tutores. Cursos em pré-lançamento, Curated content by our Founder.` — adds the founder-curated promise to differentiate from generic newsletters.
- **Spacing.** Added 64-112px vertical margin above and below the strip (responsive — 48-72px mobile, 80-112px desktop). Strip no longer feels glued to the hero or the courses grid.
- **Typography.** Font swapped from `Helvetica Neue` to `SctoGroteskA` (weareedit.io theme font, loaded from `/wp-content/themes/weareedit/css/fonts/`). Inner elements use `inherit` so they pick up the same font automatically. Strip now visually matches the rest of the site.

## v1.5.149 — 2026-05-31 (Newsletter strip placement fix)
- **Fix:** newsletter signup strip was injecting at the bottom of the homepage instead of immediately after the hero. Root cause: the JS selector `.btn-yellow.swipe-cta` matched the footer "Subscrever Newsletter" CTA as well (it carries the same site-wide class), and the walk-up logic landed in the wrong section.
- New anchor: `section.hero` directly (verified live on weareedit.io — the locked v1.5.54 hero is a `<section class="hero">` block followed by `.courses-boxes-home`). Strip now appears between hero and the courses grid.
- Fallback chain preserved: main-content first-section → any first-section.

## v1.5.148 — 2026-05-31
- **Newsletter signup CTA — Brevo integration.** New homepage strip captures email subscriptions and posts them to Brevo list `Newsletter · Site organic (2026+)` (ID 4 by default) via the v3 REST API. Single-field form (email only), reuses the locked site-wide `.swipe-cta` button standard (v1.5.112) for the submit button.
- New `class-newsletter-signup.php`:
  - REST endpoint `POST /wp-json/edit/v1/newsletter-signup`. Public, protected by honeypot + email format + per-IP rate limit (5/hour, Cloudflare-aware via `HTTP_CF_CONNECTING_IP`).
  - Brevo client calls `POST /v3/contacts` with `updateEnabled:true`. Duplicate response (`duplicate_parameter`) is downgraded to soft-success (`Já estás subscrito`) so returning visitors aren't shamed. Existing duplicates get their list-membership refreshed via PUT.
  - Adds custom attributes `SIGNUP_IP`, `SIGNUP_PLACEMENT`, `SIGNUP_SOURCE` on each contact for analytics segmentation later.
  - Logs every submission (success or failure) to the existing CF7 Debug Log (Tools → CF7 Debug Log) for single-pane visibility.
- New `assets/newsletter-signup.js` (~7KB):
  - Injects `<section id="edit-newsletter-strip">` on `body.home` only, right after the locked hero (anchored to the `.btn-yellow.swipe-cta` "Ver todos os Cursos" CTA). No theme template edits required.
  - 5 dataLayer events + direct gtag calls: `newsletter_view` (IntersectionObserver at 50%), `newsletter_focus`, `newsletter_submit`, `newsletter_success` (or `newsletter_duplicate`), `newsletter_error`. Belt-and-suspenders for GA4 — works even if GTM `GTM-TSP85L` is not configured to listen.
  - Vanilla ES5-safe, no dependencies. Skips re-injection on bfcache restore.
- New `assets/newsletter-signup.css`: yellow-gradient hero-adjacent strip with pink-dot accent on the headline, 52px tall email + submit, swipe-CTA submit reuses site-wide animation, mobile stack at 560px.
- Admin Panel: new "Brevo Newsletter Integration" section in **Settings → EDIT. SEO Fix** with two fields:
  - `brevo_api_key` (password input, masked)
  - `brevo_newsletter_list_id` (number, default 4)
- Copy locked at: eyebrow `Newsletter EDIT.` + headline `Recebe a próxima edição.` + pitch `Notas semanais. Entrevistas inéditas com tutores. Cursos antes do site público.` + social proof `Junta-te a +11.000 profissionais digitais portugueses.` Tied to the locked weekly+bi-weekly content engine — every promise is deliverable.

## v1.5.90 — 2026-05-27
- **Course schema expansion** (audit Tier 1 GEO item). Every `formacao` page now emits three additional schema.org fields beyond what was there before:
  - **`aggregateRating`** — 4.1 / 67 (reuses the Organization rating, with `itemReviewed` `@id` pointing at the org node). Matches what's visible on the homepage hero and `/avaliacoes-google/`. Unlocks Course rich-result eligibility.
  - **`audience`** (`EducationalAudience`) — "Working professionals and career changers". Helps LLMs answer "is this course for beginners / professionals / students" correctly.
  - **`coursePrerequisites`** — pulled from ACF (`pre_requisitos`, `prerequisitos`, `requisitos`, `admissao`, etc.) if any course explicitly defines them; otherwise falls back to a generic "open to all" statement. The fallback is honest for the bulk of EDIT's catalog (intro/intermediate level).
- Single plugin release applies to all ~70 course pages — no per-course manual editing required.
- Skipped `teaches` / `about` / `occupationalCategory` for this release — those need per-course mapping (e.g. UX course → ISCO code for UX designers) and are better added in a follow-up when we have the data structure mapped.

## v1.5.89 — 2026-05-27
- Search minimum query length 3 → **2 chars**. Common course-name abbreviations (AI, UX, ML, DS, IA) now trigger results. Lowered in both client-side `fetch2()` and the server-side admin-ajax fallback.

## v1.5.88 — 2026-05-27 (Search Index — the deeper fix)
- **Search now sub-50ms regardless of WP boot overhead.** Architecture: instead of hitting `admin-ajax.php` per keystroke (which paid 4-5s of WordPress plugin init cost), the plugin generates a **static JSON index** of all searchable posts (id, title, URL, type label) at `/wp-content/uploads/edit-search-index.json`. Webserver (nginx/apache) serves it directly with zero PHP. Client fetches it ONCE per page load (deferred to `requestIdleCallback`), then all keystroke filtering is client-side (`Array.filter` on title substring match — sub-millisecond).
- New `class-search-index.php`:
  - Builds the JSON via single direct `$wpdb->get_results()` query (no per-post `WP_Query` loop).
  - Regenerates on `save_post`, `deleted_post`, `transition_post_status` hooks — index stays fresh automatically when content changes.
  - Builds lazily on first page load if file missing (`init` priority 999).
- Client-side `fetch2()` rewritten:
  - Loads index from static URL once, caches the Promise.
  - Preloads on `requestIdleCallback` so it's ready before the user types.
  - Debounce dropped to 80ms (was 220ms) — no longer compensating for slow server.
  - HTML-escapes titles + URLs (the old `data_fetch` returned pre-rendered HTML; we render client-side now, so escaping matters).
- Backwards-compat: the v1.5.84 admin-ajax `data_fetch` handler stays in place as a fallback — anything else on the site still calling `data_fetch` keeps working. The 5-min server transient cache also stays as a defense-in-depth layer.

## v1.5.87 — 2026-05-27
- **Server-side transient cache on search.** First query for a keyword still pays the full WordPress admin-ajax.php boot cost (~4-5s of plugin init overhead we can't easily eliminate). But once cached for 5 minutes, every repeat query (different users, repeat visits, session restarts) is **instant** — skips WP_Query entirely. Cache key is `md5(strtolower(keyword))` so case variants share the cache. Combined with the v1.5.79 client-side cache, the experience for everyone except the very first visitor of a keyword improves dramatically.

## v1.5.86 — 2026-05-27
- **Fix /avaliacoes-google/ rendering broken** after v1.5.85 rename. Three causes:
  1. **CSS file URL mangled** by another plugin (`drag-and-drop-multiple-file-upload-contact-form-7`)'s buggy URL filter — it intercepted `wp_enqueue_style` calls and prepended its own plugin path + the FS path, turning the URL into garbage. Fix: build the URL via `site_url() + basename(WEAREDIT_SITE_ENGINE_PATH)` instead of `WEAREDIT_SITE_ENGINE_URL` — bypasses the broken filter.
  2. **Page title + Rank Math meta** still said "Críticas Google" — v1.5.85's migration only renamed `post_name`. Expanded to also update `post_title` (if still references "Crítica") and refresh `rank_math_title` + `rank_math_description` post meta.
  3. **Stale WP Rocket cache** — migration now calls `rocket_clean_domain()` + `rocket_clean_minify()` at the end so old cached HTML doesn't keep serving the old URL/title references.

## v1.5.85 — 2026-05-27
- **URL rename: `/criticas-google/` → `/avaliacoes-google/`** to match the terminology shipped in v1.5.81. Three-part migration:
  1. **SLUG constant** in `class-criticas-page.php` updated to `'avaliacoes-google'`. Kept old slug as `OLD_SLUG` constant for migration + redirect lookup.
  2. **One-time DB migration** runs on admin_init priority 5 (before ensure_page_exists). Finds the existing WP page at the old slug, renames its `post_name`, updates the tracked OPTION_KEY, flushes rewrite rules. Idempotent via `edit_seo_fix_avaliacoes_slug_migrated` option flag.
  3. **301 redirect** on template_redirect hook catches `/criticas-google/*` (incl. trailing slash + suffixes) and 301s to `/avaliacoes-google/*`. Preserves inbound SEO links from Google search results, social shares, and existing schema URL references in third-party indexes.
- All hardcoded `'/criticas-google/'` references in `class-output-buffer.php` refactored to use `EDIT_Criticas_Page::SLUG` so future renames are clean.
- Shortcode name (`edit_criticas_google_reviews`), CSS class names (cg-*), file paths (assets/criticas-google.css), and internal option keys preserved — those are not user-facing and changing them would break existing pages/cache.

## v1.5.84 — 2026-05-27
- **Search speed: 9.2s → ~250ms (real fix).** v1.5.82 replaced the handler but kept WP_Query's default `'s'` behaviour which does `LIKE %term%` against `post_title`, `post_content`, AND `post_excerpt` for every post type. The post_content scan is what was taking 9.2 s. Added a `posts_search` filter that overrides the SQL to LIKE on `post_title` only when our internal `weareedit_title_only` query arg is set. Other searches on the site (Google, other plugins) unaffected — the filter no-ops unless our arg is present.
- Also disabled `update_post_meta_cache` + `update_post_term_cache` on the query (search results only need title + permalink, no need to hydrate every post's meta).

## v1.5.83 — 2026-05-27
- **Homepage "Formação" carousel aligned to viewport left edge.** Cards were inheriting bootstrap container padding so they started indented from the screen edge. Now zero left padding/margin on `.courses-boxes-home .courses-container` → `.row` → `.col-md-12` → `.swiper-boxes`. Cards scroll flush against the left.

## v1.5.82 — 2026-05-27
- **Search backend rebuilt — fast + comprehensive.** Audit caught the real cause of slow search: the theme's `data_fetch` AJAX handler took **9.4 SECONDS per request** and didn't include the team profile (`equipa`) post type, so "Daniel Devera" returned zero results. Front-end debouncing couldn't compensate for that backend slowness.
- New `class-search-ajax.php` removes all existing handlers for `wp_ajax_data_fetch` / `wp_ajax_nopriv_data_fetch` at `init` priority 999 and registers a clean replacement. Uses one `WP_Query` with `'s'` across 8 relevant post types (post, page, formacao, eventos, noticias, equipa, entrevistas, profissoes), limits to 8 results, output markup matches the theme's existing CSS selectors. Expected response time: 100–400 ms instead of 9.4 s.

## v1.5.81 — 2026-05-27
- **/criticas-google/ terminology — "crítica/críticas" → "avaliação/avaliações"** throughout the page (PT-PT natural term for reviews). All user-visible labels swapped: hero source pill, CTA buttons, attribution lines, aria-labels, campus card counts, alumni banner footnote. URL slug + CSS class names + file paths preserved (no SEO/code impact). 13 string replacements total.

## v1.5.80 — 2026-05-27
- **Homepage Google Reviews badge removed.** The white "G Google ★★★★★ 4.1/5 · 67 avaliações verificadas" pill below the stats row was redundant — the hero already carries the reviews score (with the multi-color Google wordmark) from v1.5.59+. Course-page badge injection stays (those pages don't have the hero reviews block).

## v1.5.79 — 2026-05-27
- **Search faster — debounce + cancel-stale + cache.** The v1.5.77 `fetch2()` fired an AJAX request on EVERY keyup, which caused results to lag behind typing (wp-admin-ajax.php is ~300-500ms per call). Now:
  - **Debounced 220ms** — no AJAX fires until typing pauses.
  - **Cancels stale in-flight requests** when a new one starts → no out-of-order results.
  - **Per-session cache** keyed by query string — re-typing the same query renders instantly.

## v1.5.78 — 2026-05-27
- Plugin name `★` → `*` (Unicode star U+2605 sorted BELOW letters in WP's plugin list, ASCII asterisk U+002A sorts BEFORE letters). Bubbles to the top now.

## v1.5.77 — 2026-05-27
- **Header search FULLY rescued** — two theme bugs bypassed via plugin:
  1. **CSS:** `.headerDesktop__search` had `width:0` + a `.sticky___w5zBW` ancestor selector to expand. But the theme has THREE `<header>` tags and `$('header').addClass('sticky___w5zBW')` lands the class on a header that isn't an ancestor of the desktop search container. Fix: `:has(.autocomplete__inputWrapperEnabled)` CSS rule forces the container open regardless of which header got the class. Fallback for older browsers via `.autocomplete.searchOpen .autocomplete__inputWrapper{opacity:1}`.
  2. **JS:** the theme's inline `fetch2()` (called by `onkeyup` on the search input) was defined inside a broken IIFE failing with "$ is not a function" — so typing threw `fetch2 is not defined`. Replaced with a clean working version registered globally in `<head>` before the input renders.
- RUCSS toggle can now be re-enabled safely — search has been fixed at the source, not via RUCSS-safelisting symptoms.

## v1.5.76 — 2026-05-27
- **Search fix — proper RUCSS filter.** v1.5.74 used `rocket_rucss_excluded_inline_css` which only protects INLINE `<style>` blocks. The `.autocomplete__inputWrapperEnabled` rule lives in an EXTERNAL stylesheet, so RUCSS was still stripping it. Added `rocket_rucss_safelist` filter — covers external stylesheets. Listed all reveal-on-click selectors (autocomplete*, search*, headerDesktop__search, hero classes as defensive safelist).
- **After update:** clear WP Rocket cache + "Limpar CSS usado" (Clear Used CSS) — RUCSS needs to re-scan with the new safelist or it'll keep serving the stripped CSS from its cache.

## v1.5.75 — 2026-05-27
- Plugin name → `★ weareedit.io Site Engine` (added Unicode star prefix). Pushes the plugin to the top of the WP Admin → Plugins list so it's easy to spot. WP plugin file path / slug / option keys unchanged — purely cosmetic header rename.

## v1.5.74 — 2026-05-27
- **Header search broken after enabling "Remover CSS não usado"** — RUCSS stripped the `.autocomplete__inputWrapperEnabled` rule (only visible AFTER user clicks the magnifying glass, which RUCSS can't observe on first scan). Click handler was binding correctly + adding the class, but no visual reveal because the rule was gone. Extended the Shield's `rocket_rucss_excluded_inline_css` list with 17 search-related class names (autocomplete*, searchOpen, closeSearch, searchButton, headerDesktop__search, ais-InstantSearch__root, resultadosPesquisa, keywordSearch, hasFocus, sticky___w5zBW, postTypeSearch).
- Pre-existing `$ is not a function` errors (theme code outside IIFE jQuery wrapper) noted in audit — separate issue, will address in a follow-up if it impacts other features.

## v1.5.73 — 2026-05-26
- **Mobile hero visibility — brute-force JS safety net.** Despite v1.5.69/72's `visibility:visible !important` + `opacity:1 !important` in CSS, iOS Safari was still honouring WOW.js's inline `style.visibility="hidden"` per-element. CSS spec says `!important` in stylesheet wins over plain inline style — but observed behaviour says otherwise on mobile WebKit. Added an inline `<script>` in `<head>` that strips `style.visibility/opacity/transform` on `.hero h1, .hero h2, .hero .dgert-hero-pill, .hero .hero-corporate-row, .hero .hero-reviews, .hero .swipe-cta`. Runs immediately + on DOMContentLoaded + at 200ms + at 1000ms — to catch WOW.js whenever it fires.
- Also extended the CSS override to target elements directly (not via `.wow` class) on all viewports, in case WOW.js is hiding things on desktop too in some edge case.

## v1.5.72 — 2026-05-26
- **Mobile: H1 + DGERT pill STILL invisible after v1.5.69's visibility override.** Root cause #2: animate.css's `@keyframes fadeInUp` starts at `opacity: 0` + `transform: translate3d(0, 100%, 0)`. Disabling animation-name alone doesn't reset opacity. Now forcing ALL four hide-states off: `visibility:visible`, `opacity:1`, `transform:none`, `animation:none`. Targets `.wow`, `h1.wow`, `h2.wow`, and `.dgert-hero-pill.wow` explicitly.

## v1.5.71 — 2026-05-26
- **Mobile: corporate logos were getting cut off** because the theme's `display:flex` on `.logos-flex-container` was paired with an unreachable rule that kept `flex-wrap:nowrap`. The v1.5.69 wrap override didn't have enough specificity. Hardened with `!important` on every flex property + explicit `display:flex`. Logos now wrap into 2-3 rows on mobile, each capped at 32px height × 90px width.

## v1.5.70 — 2026-05-26
- **Mobile H1 overflow fixed.** "Transformation." was extending past the right edge on iPhone 14/15 widths. Scaled H1 from `clamp(40px,11vw,72px)` → `clamp(32px,9vw,64px)` and added `word-break:break-word` as a safety net. Hero side padding tightened to 20px (was theme default ~40px) for more usable text width on narrow viewports.

## v1.5.69 — 2026-05-26
- **Homepage hero — mobile fixes (audit screenshot 2026-05-26):**
  - **H1/H2/DGERT pill invisible on mobile** — root cause: theme's WOW.js sets `.wow { visibility: hidden; }` until animation fires, and on short mobile viewports the trigger sometimes misses. Force `visibility: visible !important` on hero `.wow` elements as a safety net so they always land visible (animation still plays via animate.css but never blocks render).
  - **H1** scaled down on mobile: `clamp(40px, 11vw, 72px)` (was clamp(64px, 9.5vw, 152px) which forced 64px min and crowded the viewport).
  - **H2** sub-text: 18px on mobile (was 32px desktop) so it fits ~3 lines without overwhelming the CTA below.
  - **DGERT pill** logo bumped down to 28px (from 40px), tighter gap, smaller text.
  - **Corporate-clients divider** — "CLIENTES CORPORATIVOS · FORMAÇÃO À MEDIDA" was truncated on mobile. Now wraps; horizontal flanking lines are hidden on mobile (re-enabled at desktop).
  - **CTA** padding + font reduced on mobile (14px 24px / 14px) so it doesn't stretch full-width.
  - **Reviews score** wraps if needed; slightly smaller font.
  - **Corporate logos** drop from 72px max-height to 36px on mobile, wrap into multi-row, gap 20px.
  - **Hero padding** reduced 144px → 80px top, 96px → 48px bottom on mobile.
- All changes scoped to `@media (max-width:768px)` — desktop locked hero remains unchanged.

## v1.5.68 — 2026-05-26
- **WP Rocket Shield** — new defensive integration (`class-wp-rocket-shield.php`). Registers safe exclusions BEFORE the user toggles "Atrasar JavaScript" or "Remover CSS não usado", so the hero animations, the swipe-cta CTA hover, the reviews block, and the third-party stack (jQuery, GTM, Cookiebot, FB Pixel, LinkedIn Insight, Hotjar, Clarity, InLinks, WOW.js, WhatsApp button) don't break when those toggles flip. Four WP Rocket filters wired:
  - `rocket_rucss_excluded_inline_css` — protects our injected inline `<style>` from Remove-Unused-CSS analysis (otherwise hover rules get stripped).
  - `rocket_delay_js_exclusions` — adds the standard "must run before user interaction" allow-list.
  - `rocket_excluded_inline_js_content` — protects inline `dataLayer`/`gtag`/Cookiebot bootstraps.
  - `rocket_exclude_js` — skips this plugin's own JS from any optimisation pass.
- Auto-detects WP Rocket; no-op if not active. Zero impact on sites without WP Rocket.

## v1.5.67 — 2026-05-26
- **Schema bundle (3 wins from the audit roadmap):**
  1. **Homepage Organization schema enriched.** Added `founder` (Daniel Devera as `Person` with Wikidata Q139907903 in `sameAs`), appended Wikidata Q139907765 to the Organization's `sameAs`, added `alternateName` aliases (`EDIT.`, `weareedit`, `Devera Co. Lda`). Closes the homepage → Wikidata → Daniel LLM-traversal chain — single highest-impact GEO fix per the audit.
  2. **Fix duplicate `og:type` on non-post pages.** Rank Math emits `og:type=article` for every CPT (courses, in-company, criticas); the plugin used to *append* a second `og:type=website` tag (no-op — FB/LinkedIn use first-seen). Now replaces in-place via output buffer on any non-`post` singular. Real blog posts keep `og:type=article`.
  3. **AggregateRating + Review[] JSON-LD on `/criticas-google/`.** Page previously shipped only BreadcrumbList. Now emits a full graph: Organization with 4.1/67 AggregateRating, 4 Review nodes for the visible quotes (publisher=Google for policy compliance), plus two EducationalOrganization sub-nodes for the Lisboa (4.2/36) + Porto (4.0/31) campus split with their addresses.
  4. **/criticas-google/ og:image added** (page previously had none — social shares looked broken). Uses the brand SHARE-EDIT.jpg at 1200×630.

## v1.5.66 — 2026-05-26
- **`/criticas-google/` hero — Iter 2 (Quote-led) shipped.** Replaces the old centered "★ Críticas verificadas no Google" eyebrow + "O que dizem os nossos alunos." H1 + grey subtitle. New structure mirrors the locked homepage hero:
  - Google source pill (G logo + "Críticas verificadas no Google" + ↗) — matches homepage DGERT pill placement
  - Big yellow opening quote mark (Georgia serif, brand yellow `#f5d100`)
  - Italic H1 quote (`Mudei de carreira em 6 meses.`) with brand-pink dot accent matching homepage H1 dot pattern
  - Attribution: student name (white bold) + course + campus + employer
  - Dual CTA: yellow primary "Ler todas as 67 críticas" (anchors to `#criticas` section) + ghost secondary "Deixar uma crítica" (opens Lisboa Google reviews in new tab)
  - Reviews score below CTAs: `★ 4.1 / 67 reviews no Google` (multi-color wordmark) — same component as homepage
- All hero typography left-aligned in 1600px container, matching the locked homepage hero. Mobile breakpoint: CTAs stack vertically, quote scales down to 38px, quote-mark to 96px.
- The cg-statbar section directly below the hero (added in v1.5.63) provides the secondary numeric anchor.

## v1.5.65 — 2026-05-26
- **Homepage hero CTA + reviews block: left-aligned** to match the approved design (iter 3). Theme defaults centered them via `.hero-btn-container`; overridden with `text-align:left` + `display:block`. Reviews block margin changed from `14px auto 28px` to `14px 0 28px 0`. Corporate-row + logo strip remain centered (unchanged).

## v1.5.64 — 2026-05-26
- **Fix: reviews score block was rendering INSIDE the CTA's yellow box.** Root cause: injection landed it as a sibling of the swipe-cta anchor, but BOTH live inside the theme's `<div class="btn btn-slide ...">` wrapper. That wrapper was containing the reviews visually inside the button bounds. Changed injection target from `<span class="swipe-label">…</span></a>` (inside the wrapper) to `<div class="hero-corporate-row` (outside the wrapper). Reviews block now sits between the CTA container and the corporate-row, as a true sibling.
- CSS updated: `display: flex` + `width: fit-content` + `margin: 14px auto 28px` → reviews block centers horizontally to match the CTA above. Dropped the inert `align-self: flex-start` (parent wasn't a flex container, was a no-op).
- Idempotency guard via `class="hero-reviews ` check.

## v1.5.63 — 2026-05-26
- **Homepage reviews score: hover underline made visible.** Bumped from 1px rgba(255,255,255,0.55) → 2px solid #fff at 5px offset. Applied to all descendants so the multi-color Google wordmark also underlines together.
- **/criticas-google/ campus cards: removed both "Ver todas as críticas →" CTAs** (Lisboa + Porto). Cards now stop at the address line — cleaner, no double click-out.
- **/criticas-google/ statbar: Google brand row added above the 4.1.** Multi-color G logo + Google wordmark (per-letter spans, brand-exact hex codes). Establishes that the 4.1 rating + 67 reviews come from Google. Visually-hidden "Google Reviews" label for screen readers.

## v1.5.62 — 2026-05-26
- Reviews score hover state → simple underline (1px, rgba white 0.55, 4px offset) instead of opacity dim. Lighter, more "this is a link" affordance.

## v1.5.61 — 2026-05-26
- **Fix: reviews score block was silently dropped.** The str_replace target (`<span class="swipe-label">Ver todos os Cursos</span></a>`) was being matched against HTML that didn't yet have the swipe-label span — the swipe-cta rewrite ran *after* the reviews injection in process(). Reordered: swipe-cta rewrite first, reviews block injection second.
- **Fix: WhatsApp floating button SVG was 404.** The button HTML (from theme/Customizer) hardcoded `/wp-content/plugins/edit-seo-fix/...` — the old plugin slug pre-rename (v1.5.55). Added a universal output-buffer rewrite that redirects any `edit-seo-fix/` URL to the new `weareedit-site-engine/` path. Catches the WhatsApp SVG plus any other stale references that might be lurking in theme/widget/Customizer code.

## v1.5.60 — 2026-05-26
- Reviews score block: "Google" → multi-color Google wordmark (G blue, o red, o yellow, g blue, l green, e red). Per-letter `<span>` markup with brand-exact hex codes. Replaces the plain word "Google" at the end of `★ 4.1 / 67 reviews no Google`.

## v1.5.59 — 2026-05-26
- **Hero bottom section — Iter 3 (Magazine Editorial) shipped.** Three coordinated changes:
  1. **Corporate-clients pill** → section-divider register (Option B + iter 3): uppercase, tracked, separator `·` instead of `|`, flanked by thin horizontal lines that act as a section heading for the logo strip below.
  2. **Reviews score** injected directly below the "Ver todos os Cursos" CTA — `★ 4.1 / 67 reviews no Google`, linked to `/criticas-google/`. Anchors real-number social proof close to the conversion point (NN/g principle).
  3. **Corporate logos bumped to 72px** (from theme-default ~40-50px) — reads at a distance, balances the new section-divider treatment above.
- Top bar (WP theme header), H1 + dots, H2 yellow sub-text, DGERT pill — all untouched. Pure additions to the bottom half of the hero.

## v1.5.58 — 2026-05-26
- **Corporate-clients tagline → trust pill linked to `/formacao-in-company/`.** "Clientes Corporativos | Formação à medida para Empresas" is now a clickable trust pill in the same format as the DGERT pill (smaller 16px text, ↗ arrow at the end, hover opacity 0.78, fadeInUp animation). Wrapping element changed from `<p>` to `<a class="hero-corporate">`. Closes the **link-the-tagline** half of Phase A of the In-Company SEO upgrade (the logo-wrapping half remains the open decision).

## v1.5.57 — 2026-05-26
- DGERT pill now animates in with the rest of the hero — added `wow animate__fadeInUp` + `data-wow-duration="1s"` to match the H1's existing animation params. No standalone CSS animation; reuses the theme's WOW.js library that's already loaded.

## v1.5.56 — 2026-05-26
- **Homepage hero: new DGERT trust pill above the H1.** Mini white DGERT badge (40px) + "Entidade Formadora Certificada" text + ↗ external-link glyph, clickable to https://www.dgert.gov.pt/entidades-formadoras-certificadas. Reuses the existing white DGERT badge asset (zero new assets). Hover transitions opacity to 0.78. Scoped to `body.page-template-page-home` only — doesn't affect other pages. Does not modify any locked hero element (H1, dots, H2, "Other" tagline, CTA) — pure addition.
- HTML injection lives in `class-output-buffer.php` `process()` inside the `is_front_page()` block; CSS lives in `inject_global_overrides()`.
- First release shipped through the one-click GitHub update flow.

## v1.5.55 — 2026-05-26
- **Renamed plugin: EDIT. SEO Fix → weareedit.io Site Engine.** Reflects the actual scope (SEO + GEO + brand + design + ops). Folder and main PHP file renamed to `weareedit-site-engine`. WP options + AJAX actions + .htaccess markers keep the `edit_seo_fix_*` prefix for live-site backward compat.
- **One-click updates from GitHub** — bundled Plugin Update Checker v5.5 (MIT). After this version is installed manually, future releases show up as one-click updates in WP Admin.
- **Auto cache clear on update** — `upgrader_process_complete` hook flushes WP Rocket page cache + minified assets after the plugin updates, so CSS/HTML rewrites take effect without a manual purge.
- **GitHub Action `release.yml`** — auto-builds the release zip on every `v*` tag push and attaches it to the GitHub release.
- Constants `EDIT_SEO_FIX_*` → `WEAREDIT_SITE_ENGINE_*`. Activation/init/deactivation function names renamed accordingly.
- Plugin URI now points at `github.com/danieldevera/weareedit-site-engine`.

## v1.5.54 — 2026-05-26 (homepage hero LOCKED)
- Softer easing per CTA swipe layer (pink/teal/black). Total ~0.65s. No overshoot.

## v1.5.53 — 2026-05-26
- Distinct easing per CTA swipe layer — pink snap, teal glide, black stamp.

## v1.5.52 — 2026-05-26
- CTA hover easing → `cubic-bezier(0.22, 1, 0.36, 1)` (decisive ease-out).

## v1.5.51 — 2026-05-26
- Fix: HTML rewrite for CTA was being silently skipped because the idempotency guard `strpos($html, 'swipe-cta')` matched the CSS rules string. Guard removed; preg_replace is naturally idempotent here.

## v1.5.50 — 2026-05-26
- CTA hover: sequenced 3-layer swipe (pink L→R, teal R→L, black L→R) via injected `<span class="swipe-layer">` panels.

## v1.5.46 — 2026-05-26
- "Other" tagline `Clientes Corporativos` — weight 700 → 400 (no longer bold).

## v1.5.45 — 2026-05-26
- "Other" tagline unified to 20px + re-centered.

## v1.5.43 — 2026-05-26
- "Other" tagline → light grey `#c8c8c8`, scaled down to sit below the H2 (main 28 / sep 22 / sub 18).

## v1.5.42 — 2026-05-26
- DGERT cert link repointed to verified-live `https://www.dgert.gov.pt/entidades-formadoras-certificadas` (prior `certifica.dgert.gov.pt/Entidade/Detalhe/18391` was a fabricated URL — DGERT's portal uses ASP.NET URLs with no GET-by-ID).
- H2 sub-text: forced `<br>` after "Data Science," removed; wraps naturally into ~2 lines.
- "Other" tagline scaled to match Figma mockup.

## v1.5.40 — 2026-05-26
- H2 sub-text dropped 42px → 32px.

## v1.5.39 — 2026-05-26
- Homepage tagline `<p><b>Clientes Corporativos | …</b></p>` rebuilt as `<p class="hero-corporate">` with `.hc-main` / `.hc-sep` / `.hc-sub` spans.

## v1.5.23–26 — 2026-05-25
- Homepage hero H1 restored to Figma copy "Future Proof. Transformation." (white) via output-buffer rewrite.
- DGERT badge becomes clickable (initially pointing at the fabricated URL — fixed in v1.5.41).
- /criticas-google/ hero gets the `waves-sequence-compressed.mp4` video background.

Earlier versions: see git history (will be reconstructed retroactively if needed).
