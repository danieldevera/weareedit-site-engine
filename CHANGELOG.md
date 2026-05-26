# Changelog

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
