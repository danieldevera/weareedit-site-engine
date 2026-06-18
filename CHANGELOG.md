# Changelog
## v1.5.593 — 2026-06-18 (Editorial #3 hero — just place the image, no CSS hacks)
Removed all hero aspect/cover/inline-style adjustment code. The theme's background-cover .about-image div (which kept cropping the banner) is now replaced with a plain <img> at natural aspect (width:100%, max-width:1000px, height:auto, centered) — the full 2000x840 art shows uncropped, no cover, no aspect-ratio overrides. og:image still pointed at the full 1200x630 share image.

## v1.5.592 — 2026-06-18 (HOTFIX — hero aspect: force inline so the banner stops cropping)
The per-post 2.381 hero aspect was set via a same-specificity head rule, which lost the cascade to the global single-blog .about-image rule (2.23) / WP Rocket used-CSS — so the box rendered taller than the art and background-size:cover vertical-centre-cropped it (DISRUPTIVE BLOG tag + "Por Daniel Devera" byline cut off, zoomed title). Now forced INLINE on the element (inline !important beats every stylesheet; Rocket lazyload only appends background-image so it survives) plus a bumped-specificity [data-bg] head rule as backup.

## v1.5.591 — 2026-06-18 (HOTFIX — black hero: doubled URL in data-bg/og)
v1.5.590 rewrote the hero data-bg + og:image to an ABSOLUTE plugin URL, but the targets already carried the https://weareedit.io prefix — producing a doubled domain (https://weareedit.iohttps://weareedit.io/...), a broken URL, and a black hero (no background image loaded). Fixed by using wp_make_link_relative() so the replacement is a relative /wp-content/plugins/... path.

## v1.5.590 — 2026-06-18 (Editorial #3 — fix hero: crop dead black top)
The full-size hero led with a large black band (the artwork's black top half). Re-cropped to lead with the gradient — keeps DISRUPTIVE BLOG + title + "Por Daniel Devera" byline + full pencil, drops the dead black top. Bundled asset now 2000x840 (ratio 2.381); per-post aspect-ratio updated to match.

## v1.5.589 — 2026-06-18 (Editorial #3 article — full-size hero, OG image, color avatar)
More per-post enrichment for "Do prompt ao produto". (1) Hero now uses the FULL-size artwork (byline + full pencil) instead of the trimmed/cropped banner: data-bg rewritten to bundled assets/img/editorial-3-hero.png (2000x1292, ratio 1.548) + per-post aspect-ratio override. (2) og:image rewritten from the small trimmed 768x345 thumb to assets/img/editorial-3-og.png (1200x630, full composition). (3) Author block headshot swapped to Daniel's color chalkboard avatar (WP media URL) and dropped the CSS pink border since the ring is baked into the image.

## v1.5.588 — 2026-06-18 (Editorial #3 article — authority links + author block)
Per-article enrichment for "Do prompt ao produto" that the WYSIWYG body editor strips on save, injected via the output buffer (scoped to the exact post slug). (1) External authority links woven onto the named tools: Framer Agents, Figma Make, Figma Sites, MCP, Claude Code, Cursor, Framer, Webflow, Framer 3.0. (2) The locked "Sobre o autor" dark-bleed block for Daniel (148px circle photo + pink ring, role, bio, Perfil/LinkedIn/Wikidata CTAs) injected just above the "Partilhar:" share row. TODO: generalise into the Blog Uploader / a reusable author field so this isn't post-specific.

## v1.5.587 — 2026-06-18 (Blog post — fix hero banner aspect + lighter body grey)
Two single-blog fixes. (1) The hero banner (.about-image data-bg div) was rendering as a giant near-square block because the theme gives the div a large height and background-size:cover cropped the wide 2.23:1 banner into a tall sliver. Pinned the div to the banner's own aspect-ratio (2549/1144) with height:auto so the whole image shows as a clean letterbox, still capped to the 1000px content width. (2) Softened blog body copy from near-black to a lighter editorial grey (#6f6f6f) on .text-block-container.

## v1.5.586 — 2026-06-18 (Blog post hero — dual-colour H1)
Blog post hero H1 is now dual-colour: the part after the first ": " is wrapped (output buffer, scoped to single blog posts) and rendered in brand pink (#f92869) — e.g. "Do prompt ao produto: [pink]o design já não termina num ficheiro[/pink]". No-op for titles without a colon.

## v1.5.585 — 2026-06-18 (Blog post hero — cap the full-bleed banner)
Single blog post hero (.about-image) was full-bleed (~850px tall on wide screens) and bled past the article column. The editorial banner carries title/author top-to-bottom so it can't be cropped — capped it by constraining width to the content layout (max-width 1000px, centered), dropping height to ~450px while keeping the whole image. Scoped to body.single-blog.

## v1.5.584 — 2026-06-17 (Course content — darker grey body text + bold links)
Curriculum/content body text was a faint theme grey; darkened to #333 (scoped to .programa/.text-block-container, with :not([color="#0090eb"]) protecting the authored-blue description list). Course-content links now darker grey + bold so they stand out from the body text; blue hover on Remote courses retained.

## v1.5.583 — 2026-06-17 (Course content links — fix white-on-white + blue hover)
Inline links inside the curriculum/description cards were painted white by the theme (designed for dark sections), so on the white cards they were invisible and hovered yellow. Course-content links (.sobre-curso/.programa/.text-block-container, incl. font-wrapped) now use a readable darker grey (#555) by default and the page main colour on hover — Remote courses → blue (#0090eb), scoped via :has(.class-remote-learning).

## v1.5.582 — 2026-06-17 (Course pages — hide legacy empresas banner + new banner follows typology colour)
(1) The legacy theme in-company banner ("Gostavas de realizar este curso na tua empresa…") was still showing on courses whose variant is `section.banner.bg-black` (the old hide rule only caught `.split-black-grey`). Broadened the hide to `section.banner:has(.info-banner)` so every legacy variant is suppressed — the new `.ceb-mini` banner replaces it. (2) The new Empresas banner (`.ceb-mini`) was hard-yellow; now its accents (eyebrow, italic, CTA) follow the page's typology colour — Remote Learning courses → blue (#0090eb), white CTA label; presencial keeps yellow. Scoped via `:has(.class-remote-learning)`.

## v1.5.581 — 2026-06-17 (Single course page — hide redundant hero/CTA promo badges)
On single course (product) pages the theme echoes the promo badge slot (AI UPGRADE / EARLY15) into the hero + sticky-CTA chrome, where it renders dark-on-dark and is redundant (Daniel: "not supposed to render"). Hidden via `body.single-formacao .special-labels-container{display:none}`, with `body.single-formacao .course-box .special-labels-container{display:flex}` re-showing the badges on the "outras formações" related-courses grid cards. Grid/archive/pillar card badges untouched.

## v1.5.580 — 2026-06-17 (Remote courses — keep authored blue accent, not pink)
The course-description highlight on Remote Learning courses (e.g. /curso-marketing-digital-online/) rendered PINK even though the content is authored blue (#0090eb) and Remote's typology colour IS blue. Cause: our own rule in class-pillar-cross-links.php force-recoloured `.sobre-curso font[color="#0090eb"]` to #f92869 site-wide. Scoped it to EXCLUDE remote courses via `body:not(:has(.class-remote-learning))`, so Remote courses keep their authored blue (title mention + the AI/Social/SEO/… bullet list) and other course types are unchanged. Degrades safely to authored blue if :has() is unsupported.

## v1.5.579 — 2026-06-17 (Augment feedback — edit notes + visual add-mode cursor)
(1) Edit existing notes: added a PUT /notes/{id} route (update_note, token-gated) + an "Editar" button in each note bubble that swaps to an editable textarea (Guardar/Cancelar); shows "· editado" after. (2) More visual add-mode: replaced the plain crosshair with a custom purple ring+plus SVG cursor, plus a fixed top hint banner ("Clica em qualquer ponto para deixar a tua nota · ESC para cancelar"; squared 8px, not a pill) shown only while adding, and ESC now cancels add-mode. JS syntax-checked. (Existing notes flushed on request.)

## v1.5.578 — 2026-06-17 (Augment feedback — fix save for logged-in users)
Notes failed to save for logged-in admins: the widget's fetch sent the weareedit.io auth cookie to the subdomain, so WP REST demanded a nonce and 403'd the POST (anonymous requests already worked). Added credentials:'omit' to all three fetches (GET/POST/DELETE) so every request is treated as anonymous and authed by our token param instead — works for logged-in and anonymous alike. Backend was fine; this was purely the WP-REST cookie/nonce trap.

## v1.5.577 — 2026-06-17 (Augment — async team-feedback layer)
New on-page feedback widget for the team-presentation round (class-augment-feedback.php). Daniel sends the preview link; team members click "+ Deixar nota" and drop pinned, author-attributed notes anywhere on the page. Notes are stored server-side (WP option) via REST under /wp-json/edit-augment/v1/notes — same-origin on the subdomain, write-gated by the preview token — so all feedback collects in one shared place and persists. "Notas (N)" opens a review panel listing everything with click-to-scroll. No logins for the team. Injected into the standalone Augment doc by render_page(); JS syntax-checked + bar render verified. Toggle off via EDIT_Augment_Feedback::ENABLED=false before public launch.

## v1.5.576 — 2026-06-17 (Augment — partnerships moved + Seegno removed)
Moved the Parcerias Corporativas section to sit right after "O que vais construir" (before Programa). Removed the Seegno card (Logo.dev returned a blank logo) — 6 partners now: Feedzai, Unbabel, Defined.ai, BySix, NILG.AI, Altar.io.

## v1.5.575 — 2026-06-17 (Augment — corporate partnerships, DGERT logo, polish)
(1) New "Parcerias Corporativas" section: 7 PT AI companies (Feedzai, Unbabel, Defined.ai, BySix, NILG.AI, Altar.io, Seegno) as logo cards (Logo.dev, larger 64px logos) + name + PT tagline; placed before the empresas/formats section. (2) Added the black DGERT badge logo to the Certificação section row. (3) Polish: the EDIT swipe-CTA (pink->teal->black sweep, label flips yellow) applied to the primary purple CTAs via JS layer injection; lift+purple-shadow hover on all card types; load animation on the hero + IntersectionObserver scroll-reveal for section content (tags only below-fold elements so above-fold never flashes; no-JS = everything visible). Verified render via headless Chrome. Note: Seegno logo returns blank from Logo.dev — needs a real asset.

## v1.5.574 — 2026-06-17 (Augment — Daniel mentor card to pravatar placeholder)
Swapped Daniel's real photo (daniel-devera-sign-off.png) for a pravatar placeholder on his Lead Mentors card, so all four cards are uniform placeholders during the design phase. Name/role/tag unchanged. All four photos to be replaced with real headshots before public launch.

## v1.5.573 — 2026-06-17 (Augment — Lead Mentors: purple-tinted cards + add Daniel)
Ported the Lead Mentors section from the local mockup (~/Downloads/augment-mockup.html) into the live template: switched the four B&W .tcard mentors to the purple-tinted .gcard treatment (same duotone the Convidados section uses) and made Daniel Devera the lead card (IA Aplicada). Remaining 3 are role-based generic mentors (IA no Marketing / Dados & Análise / Design & UX) with pravatar placeholder photos — swap for real headshots when the lineup is confirmed. Verified render via headless Chrome.

## v1.5.572 — 2026-06-17 (Augment footer — use the REAL pillar CSS, verified render)
Replaced the hand-written ("replicated") footer CSS with the ACTUAL rules pulled verbatim from the live pillar stylesheets: bootstrap grid (`.container`/`.row`/`.col-*`) from bootstrap.min.css, the real EDIT site-footer rules from the theme style.css (`footer{}`, `.footer-menu`, `.locations`, `.other-projects`, `.copyright`, `.social-footer`, visibility utils), and the real light override from `assets/marketing-digital.css` (`#f7f5f0` canvas, ink text, pink hovers, locked yellow Subscrever CTA, white theme logos flipped black via `brightness(0)`). Extracted with a CSS parser (handles @media blocks; bootstrap junk like blockquote/panel footer rules filtered out). Small augment-only supplement: inline-SVG social icons (the theme icon font is cross-origin-blocked on the subdomain — confirmed no ACAO header), and a mobile fix to restore the nav columns (theme `.hide-mobile` hides them ≤768) + keep the bottom bar light/static. Fixes the duplicate-columns + broken-icon render from v1.5.571. Verified via headless Chrome at 1440px (pixel-matches the /marketing-digital/ pillar footer) and 560px.

## v1.5.571 — 2026-06-17 (Augment — embed the actual pillar footer markup)
Replaced Augment's hand-built replica footer with the REAL theme footer lifted verbatim from /marketing-digital/ (the exact pillar markup: `.container.footer-menu`, the 4 col-md-3 columns, locations, Outros projetos logos-grid, footer-newsletter strip, copyright + social-footer, white DGERT badge). Lazy-load placeholders were resolved to real `src` URLs so the standalone doc renders them. Because the theme's 224KB stylesheet would clobber Augment's own design, the footer-relevant theme CSS is reproduced inline (real values: container 1230px, h3 22px/500, links 15px, footer-logo 151px, other-projects lead 22px + hairline, copyright/social-footer) scoped under `footer`, plus the pillar light override (#f7f5f0 canvas, ink text, pink hovers, white theme logos flipped black via brightness(0), yellow Subscrever CTA preserved). The theme social-icon font (social-icons.css) is linked in <head> for the footer glyphs. Net: Augment now carries the pillar footer's exact structure/content, not an approximation.

## v1.5.570 — 2026-06-17 (Empresas + Augment — adopt the canonical pillar light footer)
Aligned both footers to the agreed reference: the `/marketing-digital/` (et al) pillar footer, whose light treatment lives in `assets/marketing-digital.css` (`body:has(.md-pillar--light) footer …`). (1) EMPRESAS: replaced the v1.5.568 custom recolor (which chipped the partner logos on dark) with an exact mirror of the pillar CSS, re-scoped to `body.empresas-page footer` — #f7f5f0 canvas, ink text, pink hovers, locked yellow Subscrever CTA, hairlines softened, and the white theme logos (EDIT wordmark, DGERT, the four 'Outros projetos' marks) flipped black via `brightness(0)` instead of chipped. Since empresas renders inside the theme, this is now pixel-identical to the pillar footer. (2) AUGMENT (standalone doc, can't inherit theme chrome): retuned its self-contained footer to the same pillar spec — #f7f5f0, ink text, partner marks `brightness(0)` black with inter-logo hairlines (no chips), 22px 'Outros projetos' lead with trailing rule, and restored the 'Mantém-te a par' + yellow Subscrever strip for parity. Social glyphs simplified to plain ink→pink.

## v1.5.569 — 2026-06-17 (Augment — full EDIT footer, light, in the standalone doc)
Second half of the empresas+augment light-chrome pass. The Augment subdomain page is a standalone document (bypasses theme chrome), so it carried a minimal 3-column placeholder footer. Replaced it with the full real EDIT footer — matching empresas/main-site — light variant: EDIT logo + Lisboa/Porto locations + black DGERT badge in the brand column; Formação/Eventos, Remote Learning/Outros, and Escola link columns (real weareedit.io URLs); an "Outros projetos" row (EDIT Work / Disruptive Jobs / The Transformation / IO) on dark 6px chips so the white/mono marks survive on light; and a bottom bar with inline-SVG social icons (squared chips, hover-fill ink) + copyright + Política de Privacidade. Self-contained CSS using the doc's existing tokens (`--soft`/`--ink`/`--grey`/`--line`/`--pink`); no theme dependency. The page's own newsletter section already sits above the footer, so the footer newsletter strip was intentionally omitted to avoid a duplicate CTA.

## v1.5.568 — 2026-06-17 (Empresas — light footer to match the white top bar)
The empresas page already forces a white top bar with a black EDIT lockup, but the theme footer below it stayed dark — visually inconsistent. Added a scoped LIGHT FOOTER block (`body.empresas-page footer`): soft off-white background (#f6f5f1), the white EDIT wordmark flipped dark via `filter:brightness(0)` (same approach the header uses), headings/location labels in ink, links grey→pink on hover, hairlines lightened, and the white/mono "Outros projetos" partner marks seated on dark 6px chips so they survive on light. Social glyphs darken (hover pink); the yellow "Subscrever Newsletter" swipe-CTA is untouched. CSS-only, scoped to the empresas footer — dark content sections above are unaffected. First half of the empresas+augment light-chrome pass.

## v1.5.567 — 2026-06-17 (Empresas — fix black-on-black header logo)
The empresas page renders on the home template, whose header puts the logo in a DARK `.headerDesktop__content` panel (white logo over the dark hero). On empresas the bar is forced white, but the parent `header{background:#fff}` didn't reach the dark child panel — so the black-on-transparent EDIT lockup landed black-on-black (invisible). Added a closed-state rule whitening the `.headerDesktop` wrapper children (container/row/content/middle); the menuOpen dark-overlay state is excluded; the yellow "Fala connosco" is untouched. CSS-only.

## v1.5.566 — 2026-06-17 (Internal docs — card-grid index + per-page quick-nav bar)
Internal Marketing Documents UX: (1) index redesigned from a flat list into a responsive CARD GRID (auto-fill minmax 280px, hover lift + pink accent). (2) Every doc page now gets a sticky QUICK-NAV bar injected after <body> via new serve_doc() — back-to-index link + a "jump to another doc" dropdown (current doc preselected) + EDIT. Internal tag. Both render paths route through serve_doc() (replaces raw readfile); injection uses substr_replace to avoid regex backreference issues; bar is fully inline-styled so it never clashes with doc CSS. No content changes to the docs themselves.

## v1.5.565 — 2026-06-16 (Internal docs — publish AAI + DataOps traffic audits)
Published two page-level traffic audits to the marketing repo (GA4 + GSC via Supermetrics, 90d). AAI Bootcamp: 1,000 sessions, 89%% engagement, 22 conversions — Paid Search converts 8.6x better than Organic (14 of 22 conversions). DataOps Bootcamp: 100 sessions, 94%% engagement, 0 conversions, ZERO Paid Search — demand+distribution problem, niche term (mirrors the SEO/Claude-Code failure pattern). Drop-in noindex HTML; no code changes.

## v1.5.564 — 2026-06-16 (Revalidate Education Foresight EN version + 301)
Same revalidation applied to the ENGLISH Education Foresight doc: renamed education-foresight-strategy-2026-06-10.html to -2026-06-16.html, prepended the Revalidation 2026-06-16 layer (fresh GT data, rising-rank-not-volume correction, failed-course field test, re-ranked bets), and added the EN slug to the REDIRECTS map (301 old to new). Both PT + EN foresight docs now revalidated + redirected.

## v1.5.563 — 2026-06-16 (Internal docs — revalidate Education Foresight strategy + 301 old→new)
Revalidated the Education Foresight & Growth Strategy (PT) doc against a fresh 12-month Google Trends pull (to 2026-06-16, via Supermetrics): chatgpt still dominates PT (67–100, not declining), "claude code" still negligible in PT (~1, faded after a Mar–Apr blip), PT-vs-GB specialist-tooling gap persists. Renamed `education-foresight-strategy-2026-06-10-pt.html` → `-2026-06-16-pt.html` and prepended a "Revalidação 2026-06-16" layer: data re-check table, a methodology correction (the "claude code = 100" figure is a Rising-Queries RANK, not absolute volume — it's tiny everywhere), a field-test reality check (the specialist Claude-Code course got ~0 PT intent), and a re-ranking (Part A mainstream anchoring = high confidence; Part B Claude-Code/agents cohorts = DOWNGRADED). Added a slug REDIRECTS map to EDIT_Internal_Marketing_Docs (301 old slug → new) wired into both render paths.

## v1.5.562 — 2026-06-16 (Pillar pages — scrub SIFIDE / "até 35%" / Cheque-Formação site-wide)
Per Daniel, removed every public SIFIDE claim (and the bundled "crédito fiscal até 35%" + dead "Cheque Formação + Digital" line) from all 6 pillar pages (Marketing Digital, Marketing Digital IA, Data Science, UX/UI, IA, Programação). Touched: hero lede ("Programas SIFIDE-elegíveis." dropped), section-header lead, empresas-CTA lede, cta_lead trailing claim, dgert-band sub (rewritten to DGERT certification + certificados individuais), FAQ financing answer (SIFIDE + Cheque-Formação removed, kept DGERT + pagamento faseado), and Rank Math meta descriptions. Funding language now matches the empresas legal review: DGERT certification + individual certificates only. The lone remaining "SIFIDE" string is an internal PHP comment in class-empresas-page.php (not emitted to HTML).

## v1.5.561 — 2026-06-16 (Empresas mixed-content fix + pillar "Porquê a EDIT." section removal)
**Empresas (urgent):** the cached empresas.weareedit.io render was emitting `http://weareedit.io/...` stylesheet URLs (the render's SSL context produced http). Served on the https page, browsers BLOCKED those stylesheets as mixed content → the theme header rendered completely unstyled. Fixed by forcing all first-party asset URLs to https in render_page() before the HTML is cached (str_replace http://→https:// for weareedit.io / www / empresas). Plugin update busts the poisoned transient so it re-renders clean.
**Pillar pages:** removed the "Porquê a EDIT." section from all 6 pillar classes (Marketing Digital, Marketing Digital IA, Data Science, UX/UI, IA, Programação) per Daniel — section-only. NOTE: SIFIDE / "até 35%" / Cheque-Formação claims still remain in ~6 other spots per pillar (hero lede, cta_lead, FAQ, section headers, DGERT band, Rank Math meta) — left in place per the section-only scope; full scrub pending a separate decision.

## v1.5.560 — 2026-06-16 (Course cards — badge priority rule: order + cap)
Implemented Daniel's badge precedence rule. (1) Server-side reorder: every .special-labels-container's stacked tags are sorted to a fixed priority — early15 (Setembro Early-Bird promo) > AI UPGRADE > NOVO PROGRAMA (empty/other last). (2) Grid cards (.course) cap at the top 2 tags via CSS (3rd+ hidden) — tight corner; the product-page hero badge (the lone container NOT inside a .course card) keeps all 3. Empty badge boxes hidden everywhere. early15 text preserved verbatim + now sorts first, so the EARLY15 ?campanha= filter still matches and the cap never drops it.

## v1.5.559 — 2026-06-16 (Course cards — render stacked badge labels cleanly)
After v1.5.558 un-hid the badge slot, cards carrying MULTIPLE tags (NOVO PROGRAMA + AI UPGRADE + "early15" — the Setembro Early-Bird promo code) rendered the 2nd/3rd badge as an empty bordered box: the theme styles a single badge, so stacked ones clipped their label to blank. Forced .special-labels-container to a clean vertical flex stack (height:auto, overflow:visible) and .course-promo-code to visible, uppercase, auto-sized labels — so every tag shows (early15 → "EARLY15"). CSS-only override in inject_global_overrides; no markup change.

## v1.5.558 — 2026-06-16 (Course cards — restore badge labels, hide only the IEFP "100% Reembolso" badge)
The blanket `.special-labels-container{display:none !important;}` (added 2026-06-12 to kill the obsolete IEFP "100% Reembolso" badge) was wrongly hiding the ENTIRE top-right badge slot on course cards — suppressing the real "NOVO PROGRAMA" (66 cards), "AI UPGRADE" (48) and "early15" labels too. Replaced with a surgical rule that hides ONLY the IEFP badge by its image (img[src*="Group-1265"]): :has() hides the whole badge container, plus a bare-img fallback for browsers without :has(). All other special-labels tags display again. No server-side/markup change; the EARLY15 campanha filter is untouched.

## v1.5.557 — 2026-06-16 (Augment — purple duotone tint on Convidados photos)
Guest portraits now carry a purple duotone tint (CSS filter: grayscale+sepia+hue-rotate 218deg) to match the Augment signature purple and unify the placeholder portraits; softens slightly on hover. Template regenerated from locked mockup.

## v1.5.556 — 2026-06-16 (Augment — add 3rd convidado Alexandre Messina)
Added a third guest to the Convidados (1-hour guest talks) section: **Alexandre Messina** (AI Creator & Educator · Lovable), talk "Do zero ao produto: construir apps reais com IA, sem saber programar." Now 3 named guests + the "Mais a confirmar" card. Placeholder portrait (pravatar) — swap real headshot on confirmation. Template `includes/templates/augment-page.html` regenerated from the locked mockup (single source of truth in ~/Downloads/augment-mockup.html). STATUS still 'preview'.

## v1.5.555 — 2026-06-16 (Augment — subdomain page scaffold, same integration as empresas)
Ported the locked Augment landing page (v7 mockup) into the plugin as a subdomain surface at **augment.weareedit.io**, mirroring the EDIT_Empresas_Page pattern. New `EDIT_Augment_Page` (`includes/class-augment-page.php`): detects `HTTP_HOST = augment.weareedit.io` and short-circuits at `template_redirect` priority 0 to emit our own standalone HTML document (the page is fully self-contained — own head/fonts/dark hero video — so we readfile() the template and exit, bypassing the formacao theme entirely, rather than wrapping it like empresas does). `STATUS = 'preview'` (admins or `?preview=augment-2026-preview` token see it; everyone else gets a clean noindex "em breve" 404). Template at `includes/templates/augment-page.html`; the `<!--EDIT-HEAD-->` marker is replaced at render with status-appropriate robots/canonical meta (noindex while preview). Assets (hero video, EDIT logo, DGERT logos) added at `assets/augment/`, referenced by absolute weareedit.io plugin URLs so they resolve from the subdomain. Required + init'd alongside the empresas classes.
**NOT live:** STATUS=preview + needs the `augment.weareedit.io` DNS CNAME → same WP install (+ host added to the server/Cloudflare) before it resolves. No public link from weareedit.io. Flip STATUS→'live' only on go-live greenlight. Signature colour purple #7a38b4.

## v1.5.554 — 2026-06-16 (Internal Marketing Docs — add Google-reviews counter-push playbook)
Published a new internal marketing document at `/internal-marketing-documents/counter-push-avaliacoes-google-2026-06-16/` (noindex/nofollow, link-only). PT playbook for the alumni honest-review counter-push to dilute the active coordinated fake-review attack on the Lisboa GBP while the removals sit pending with Google: segmentation (who to ask / exclude), Brevo email + WhatsApp copy (founder voice, "tu"), the official 1-tap review-link instruction (placeholder — link must be pulled from the GBP "Obtenha mais avaliações" share, not fabricated), do/don't rules (no incentives, phase in 2–3 waves), and success metrics. Drop-in HTML only — no code/route changes. Related: Nuno Costa attack incident.

## v1.5.553 — 2026-06-15 (Empresas — deactivate Brevo booking embed, email-first)
Per Daniel + the review doc (email-first, minimise meetings), deactivated the "Discovery 30 min" Brevo Meetings booking flow — kept in code for near-future use behind a single flag `BOOKING_ENABLED = false`. Gates 3 touchpoints: (1) the booking iframe + "Escolha o seu horário para a chamada de 30 min" block on the form-confirmation screen, (2) the "Se quiser adiantar…" intro line + (3) the "Reservar 30 min" button in the auto-reply email. When off, the confirmation shows only "Pedido recebido" and the email stays purely email-first. To restore: flip the flag — AND first fix the Brevo meeting description (still carries the Cheque-Formação / SIFIDE claim) in the Brevo Meetings UI (UI-only, not in our code/API).

## v1.5.552 — 2026-06-15 (Empresas process — pink title highlight on Diagnóstico)
After removing the "48 horas" time tag from the Diagnóstico step (v1.5.549), it sat bare next to the timed steps. Added a `pw-step--notime` modifier (auto-applied to any step with an empty `time`) that gives the title an accent-coloured background pill — Diagnóstico's accent is pink (#f92869), so its title now shows on pink, matching the visual weight of the other steps' time tags.

## v1.5.551 — 2026-06-15 (Empresas — scrub funding terms from HTML/CSS comments in page source)
Post-deploy `curl` of the live page (1.5.550) showed the visible copy was clean, but 4 mentions of the old terms (Cheque-Formação / SIFIDE / POCH / "até 100%") still sat inside **code comments** that get emitted into the HTML source. Harmless to humans, but this site is optimised for LLM/AI citation, so the source itself must be clean. Reworded the financing HTML comment + two inline-CSS comments to drop the term names. No functional/visual change.

## v1.5.550 — 2026-06-15 (Empresas — hero primary CTA "Discovery Call" → "Pedir proposta")
Per Daniel, aligning the hero with the review doc's email-preference / minimise-meetings theme. Primary CTA label "Discovery Call" → "Pedir proposta" (still → #contacto). Decision logged: sitewide org schema Cheque-Formação/IRS line (`class-structured-data.php:74`) stays as-is (B2C/individual context, valid).

## v1.5.549 — 2026-06-15 (Empresas — apply team legal review doc "Alterações na página")
Implemented the full review document. Funding messaging now reflects reality: only the **Fundo de Compensação do Trabalho** is mentioned (singular, "do Trabalho"); all Cheque-Formação / SIFIDE / POCH / POPH / "até 100%" / "reembolso" claims gone.
- **Hero lede:** "Elegíveis para Fundos de Compensação" → "Formação certificada pela DGERT, com emissão de certificados individuais." Hero CTA label → "Fundo de Compensação do Trabalho".
- **Intro lede:** deleted the trailing "Como entidade formadora certificada DGERT, … Fundos de Compensação" sentence entirely.
- **Financing banner:** removed the false-claims body sentence; kept eyebrow + title + "Conhecer os apoios" link (reactivated).
- **Financing section (rebuilt minimal, reactivated):** dropped the "até 100%" stat + all 3 cards; now a single mention of the Fundo de Compensação do Trabalho + DGERT badge ("certificados individuais") + CTA.
- **Process (shared const):** Diagnóstico step — removed the "48 horas" time tag + "de 60 minutos" from the body (empresas page hides empty time pill).
- **FAQ (shared const):** cost answer no longer cites €8.000 / nº formandos / customização — now "depende essencialmente do número de horas de formação"; **deleted** "Quanto tempo demora…"; min/max group answer → "Não temos limites fixos…"; customization answer → "sobretudo por email", no 60-min session.
- **Lead form bullets:** "chamada de 30 minutos" → "Diagnóstico inicial das vossas necessidades, por email"; removed the "Apoio na elegibilidade" bullet.
- **Founder reply email:** dropped "agendar uma conversa de 30 minutos".
- **Global:** every "Fundos de Compensação" → "Fundo de Compensação do Trabalho" across empresas page, homepage band + eehe feat (`class-empresas-links.php`), SEO meta, and the draft corporativa own-render copy.
- **WhatsApp FAB icon:** painted the glyph from an inline `data:image/svg+xml;base64` (server-read of `assets/whatsapp.svg`) instead of an external URL — immune to path/cache/404 (the doc flagged a broken "?" icon, likely from the pre-fix version).
- **Left for confirmation:** hero primary CTA still "Discovery Call" (doc didn't list it, but its email-preference theme may apply); sitewide org schema `class-structured-data.php:74` Cheque-Formação/IRS (B2C context).

## v1.5.548 — 2026-06-15 (Empresas — scrub remaining wrong funding claims from shared constants + homepage)
v1.5.547 deactivated the FINANCIAMENTO *section*, but the live empresas.weareedit.io page still rendered the false claims because it pulls **shared constants** (STATS / VALUE_PROPS / FAQ) from `EDIT_Formacao_Corporativa_Page`, plus the homepage/area-page sections in `class-empresas-links.php` had their own copy. Found via a live `curl` scan (SIFIDE ×7, Cheque-Formação ×9, POCH, "até 100%" still present). Scrubbed all of it to the team-confirmed "Fundo(s) de Compensação".
- **Shared constants (`class-formacao-corporativa-page.php`, feed the live empresas page):**
  - STATS: dropped the false `100% · SIFIDE + Cheque · Elegível para subsídios` stat → `4.1★ · 67 reviews Google` (defensible, matches site AggregateRating).
  - VALUE_PROPS: `DGERT-certificada · SIFIDE elegível` + "reembolso via SIFIDE, Cheque Formação + Digital, e POPH" → `Entidade Formadora Certificada DGERT` + "pode ser enquadrada no Fundo de Compensação do Trabalho".
  - FAQ: removed the SIFIDE/Cheque clause from the cost answer; **deleted** the entire "Posso usar SIFIDE, Cheque Formação ou POPH?" Q&A.
- **Homepage + area-page (`class-empresas-links.php`):** `ee-empresas-band` body and the homepage `eehe__feat` ("SIFIDE & Fundos de Compensação") → "Fundos de Compensação".
- **Draft `/formacao-digital-para-empresas/` own-render copy** (not live, STATUS=draft): scrubbed the 6 SIFIDE/Cheque mentions (auto-reply email, trust line, rank_math + schema descriptions, lead bullets) so a future republish can't re-expose them.
- **Left as-is (flag for review):** the sitewide org schema in `class-structured-data.php:74` still says "elegível para Cheque-Formação e dedutível no IRS" — that's B2C/individual context (Cheque-Formação can apply to individuals), outside the team's empresas-specific correction. Confirm in the legal review doc.

## v1.5.547 — 2026-06-15 (Empresas FINANCIAMENTO section deactivated — wrong funding claims)
Team flagged the entire financing messaging as factually wrong. Frozen (not rewritten) pending a legal review document. Corrections per team: **Cheque-Formação** não se aplica a empresas; **SIFIDE** é crédito fiscal de IRC (responsabilidade contabilística), não aplicável a formação profissional; **POCH** terminou em 2013. Único apoio atual válido = **Fundo de Compensação do Trabalho** (+ PESSOAS 2030, mas ainda não utilizável).
- **Deactivated both financing blocks** behind `<?php if ( false ) : ?>` flags (instantly reversible): the teaser `financing-banner` (~L3646) and the full `financing` section with the 3 cards + "até 100% reembolsável" stat (~L3996).
- **Repointed dangling CTA**: hero secondary CTA `Fundos de Compensação` `href="#financiamento"` → `#contacto` (anchor target no longer renders).
- **Scrubbed inline false claims** (kept only the team-confirmed "Fundos de Compensação"): hero lede, intro lede, lead-form bullet, and the SEO meta description — all dropped Cheque-Formação / SIFIDE references.
- **TODO**: restore + rewrite once the legal review document lands (flip the two `if ( false )` → `if ( true )` and replace card copy).

## v1.5.546 — 2026-06-15 (Empresas A11y + Best-Practices — Lighthouse audit fixes)
Pulled the exact failing audits via a local Lighthouse run on the live subdomain (mobile): A11y **88**, the dominant Best-Practices fail = `errors-in-console` (CORS). Fixed everything that lives in our code; the rest (`.htaccess` CORS header, WP Rocket toggles) is parked for off-hours, and the LinkedIn Insight tag stays (kept by decision — it caps BP under 100 via `deprecations` / `third-party-cookies` / `inspector-issues`).
- **Contrast (a11y)**: `--grey-3` `#888` → `#767676` (3.54:1 → 4.54:1 on white, passes WCAG AA). One variable clears **11 of the 17** flagged `color-contrast` nodes (`.section-eyebrow`, `.add-tab`, `.add-quote-attr`, `.add-section-title`, `.form-help`, `.privacy-note` — all share `--grey-3`). The remaining 6 are `.add-subs li .n` brand-accent step numbers (`var(--accent)`) — left as-is to preserve the brand colour; revisit as a separate design call.
- **CORS fonts (best-practices `errors-in-console`)**: our 6 SctoGroteskA `@font-face` used absolute apex URLs (`WEAREDIT_SITE_ENGINE_URL`), which CORS-block on `empresas.weareedit.io` (fonts always fetch in CORS mode; the apex sends no `Access-Control-Allow-Origin`). They now use **root-relative** paths via `wp_make_link_relative()` → resolve same-origin. Side benefit: the subdomain was silently rendering in *fallback fonts*; this restores the real SctoGroteskA (matches the locked spec).
- **`<main>` landmark (a11y `landmark-one-main`)**: the theme chrome exposes no `<main>`; `emit_body()` now wraps the content sections in `<main id="conteudo">`.
- **alt / accessible names (a11y `image-alt` + `link-name`)**: new `a11y_chrome_fixes()` post-process adds `alt` to the visible theme chrome images (EDIT. logos, search icon, the 4 footer brand-grid logos) and `aria-label` to the logo + brand-grid links that contained only an image. Attribute-add only (never restructures); scoped to flagged-visible chrome, leaving hidden mega-menu icons untouched.

## v1.5.545 — 2026-06-15 (Empresas WhatsApp icon — fix dead plugin-path 404)
- **WhatsApp icon was a blank green circle** (glyph missing). Root cause: the theme markup hardcodes the OLD plugin path `…/plugins/edit-seo-fix/assets/whatsapp.svg` which 404s since the v1.5.55 folder rename to `weareedit-site-engine`. On normal pages the output-buffer rewrites that path; the empresas custom render bypasses it → broken `<img>`, hidden by the v1.5.544 `font-size:0`. Fix: paint the glyph via `#whatsapp-button { background-image: url(<correct path>) }` (30px, centered, on the green circle) and `#whatsapp-button img { display:none }`. Path-rename-proof (uses WEAREDIT_SITE_ENGINE_URL).

# Changelog
## v1.5.544 — 2026-06-15 (Fix empresas WhatsApp FAB + WebP rollout to banners)
- **WhatsApp floating button fixed on empresas** (Daniel: "URGENT: WhatsApp UI is broken" — fine on other pages). The theme `<a id="whatsapp-button">` (white-glyph svg) lost its styling under the empresas scoped CSS resets, rendering as an unstyled/broken icon. Restored the green circular FAB via `body.empresas-page #whatsapp-button{...}` (fixed bottom-right, 56px, #25D366, white 30px glyph).
- **WebP rollout**: the mid-page "EDIT para Empresas" banner on all 6 pillar variants + the homepage empresas section (`class-empresas-links.php`) now use `empresas-hero.webp` (57KB) instead of the 263KB jpg. Empresas hero already on WebP (v1.5.543); OG image kept as jpg for social compatibility.

# Changelog
## v1.5.543 — 2026-06-15 (Empresas perf — LCP hero: WebP + preload, Track B)
- **Hero image → WebP** (was the LCP element + the PSI "image delivery 287 KiB" flag). Generated `empresas-hero.webp` (1600×900, q72) — **57 KB vs the 263 KB jpg (−78%)**. The hero `background` now uses `image-set(webp type('image/webp'), jpg type('image/jpeg'))` with the bare jpg `background` as the fallback layer for browsers without `image-set`.
- **LCP hero preload** — the hero is a CSS `background-image`, invisible to the preload scanner, so it was discovered late (LCP ~6.0s). Added `<link rel="preload" as="image" type="image/webp" fetchpriority="high">` for the WebP at `wp_head` priority 1 (near the top of `<head>`), so the browser fetches it immediately.
- NOTE: the dominant Performance lever (render-blocking 2.87s from 33 JS + 13 CSS) is WP Rocket config (Delay JS + Remove Unused CSS) — Track A, enabled in the Rocket admin, not code.

## v1.5.542 — 2026-06-15 (Empresas perf — mu-plugin pre-plugin cache serve)
- **Auto-installed mu-plugin** (`mu-plugins/edit-empresas-early-cache.php`) that serves the cached empresas home **before regular plugins load** — the last mile past the `plugins_loaded` serve (which still pays plugin-loading cost), toward WP-Rocket-class TTFB. The main plugin writes/updates the mu-plugin (marker `EDIT-MU-v2`) on `admin_init`, activation, and plugin update; if the mu-plugins dir isn't writable it silently no-ops and the `plugins_loaded` serve remains the fallback. Marks its responses `X-EDIT-Empresas-Cache: HIT-MU`.
- **Cache key switched to a fixed `edit_empresas_html`** (was version-keyed) so the mu-plugin — which runs before this plugin's version constant is defined — can read the same transient. Busting is now explicit: `delete_transient('edit_empresas_html')` on plugin update (in the upgrader hook, alongside the mu-plugin refresh + warm), with the 12h TTL + `?nocache=1` as backstops.

## v1.5.541 — 2026-06-15 (Empresas perf — serve cache at plugins_loaded)
- **Early cache serve** for the empresas output cache. v1.5.539 cached the render but served it from `render_page()` — late in the request, after WP had loaded the theme, run the main query, and entered the template machinery (~2.8s of bootstrap even on a HIT, vs the pillars' 0.24s via WP Rocket's pre-bootstrap file cache). Now a tightly-scoped `plugins_loaded` (priority 0) handler in the main plugin file serves the cached transient and exits **before** the theme/query/template phase — for anonymous GET hits to the empresas home only (logged-in sniffed via the `wordpress_logged_in_` cookie since auth isn't loaded yet; preview/nocache excluded; non-`/` paths like `/wp-json/` skipped). Falls through to the normal render (which still populates + serves the transient) on a miss/admin/preview. Response header `X-EDIT-Empresas-Cache: HIT-EARLY` marks the fast path. NOTE: plugins still load — going fully pre-WP (~0.2s) would need a mu-plugin / advanced-cache drop-in; this is the in-plugin ceiling.

## v1.5.540 — 2026-06-15 (Empresas mobile header — fix white-on-white logo)
- **Mobile EDIT logo was white-on-white** on the empresas white top bar (Daniel: "Logo white on white" — only the yellow dot showed). Cause: the JS lockup swap only replaces the first/desktop header, so the **mobile** header keeps the theme's white `logo-edit.svg`, and the white-bar blacken rule explicitly excludes `:not([src*="logo-edit"])` — leaving it white on white. Fix: targeted rule `body.empresas-page .headerMobile:not([class*="menuOpen"]) .headerMobile__logo img { filter: brightness(0) !important }` blackens the mobile logo in the closed state; the existing open-state rule reverts it to white on the dark menu overlay. Desktop lockup (locked v1.5.361) untouched.

## v1.5.539 — 2026-06-15 (Performance — empresas output cache + cache pre-warm)
- **Empresas page output cache** (Daniel: external audit found ~3.7s TTFB / never cached). `render_page()` called `nocache_headers()` and re-ran the full theme + body render on every hit. The render is byte-identical for all anonymous visitors (the lead form's REST endpoint is `permission_callback => __return_true` — no per-request nonce), so we now cache the whole render in a transient keyed by plugin version (`edit_empresas_html_{VERSION}` — auto-invalidates on every deploy, 12h TTL) and serve it without rebuilding. Cache HIT path skips `get_header()`/`emit_body()`/`get_footer()` entirely (~3.7s → tens of ms). `Cache-Control: public, max-age=600` now sent (was no-store) so browser/CDN cache too. Logged-in users, `?nocache=1`, `?preview`, and POSTs always get a fresh build. Refactored the canonical-dedupe from an ob_start callback to a direct `dedupe_canonical()` call on the captured buffer so it can be stashed. Verify via the `X-EDIT-Empresas-Cache: HIT|MISS` response header.
- **Cache pre-warm** (covers empresas + pillar cold renders). New `weareedit_warm_caches()` fires non-blocking self-requests to the empresas home + 5 pillar URLs, so the first visitor after a cache-bust doesn't eat the cold render (pillars were 6–17s cold: the /formacao/ scrape + heavy build; warm now pays that instead of a real user/Googlebot). Runs **daily on cron** + **~30s after our plugin updates** (when caches were just invalidated). Pillars stay Rocket-cached (warm TTFB ~0.25s) + keep the 14-day scrape transient.

## v1.5.538 — 2026-06-15 (Course-single alumni spacing + empresas "24h" bold)
- **Alumni section content pushed down off the top edge** (Daniel: "move down the alumni content"). The theme's dark `section.alumni` had its eyebrow/title cramped against the band's top edge. Added `body.single-formacao section.alumni{padding-top:120px !important}` to the course-singles CSS block (`class-pillar-cross-links.php`) — scoped to course singles only, so the homepage alumni slider is untouched.
- **Bolded "24 horas úteis"** in the empresas "Vamos conversar" contact section lead (Daniel: "to bold 24h"). `Em 24 horas úteis voltamos…` → `Em <strong>24 horas úteis</strong> voltamos…` on `class-empresas-page.php` line ~3907.

## v1.5.537 — 2026-06-15 (Related-areas banner — more breathing room + IA always present)
- **More space around the "Esta formação faz parte de" banner** (Daniel: "add more space between the sections"). `.epb-banner` vertical padding 64px → 104px, so it no longer sits tight against the tutors block above or the Alumni section below. Shared component → applies on every product page.
- **Inteligência Artificial is now a constant card** in the related-areas banner (Daniel: "AI should be constant"). It's the transversal layer of 2026, so `inject_pillar_banner()` appends the IA pillar to the card list whenever the banner renders and the reverse-lookup didn't already include it (deduped — never doubles on IA's own courses). A Data Science course now shows 01 Data Science + 02 Inteligência Artificial; IA always lands as the trailing card for a consistent position. Courses in no pillar still self-skip (the banner isn't forced just for IA).

## v1.5.536 — 2026-06-15 (Newsletter strip — remove top/bottom dead space site-wide)
- **Zeroed the newsletter strip's vertical margins** (Daniel: "remove top and bottom dead spaces for the newsletter banner site-wide"). `#edit-newsletter-strip` margin `56px 0 80px` → `0` (base), `48px 0 72px` → `0` (≤767), `64px 0 88px` → `0` (≥768) in `assets/newsletter-signup.css`. The margins were revealing the dark page/footer background as black "dead space" bands around the pink strip on non-home pages; the strip now sits flush. Internal padding (40/48px) unchanged. NOTE: on the homepage the strip is now flush with the hero/course grid too (was intentional breathing room) — revisit if it reads tight.

## v1.5.535 — 2026-06-15 (Course singles — roll out Empresas + related-areas banners site-wide)
- **Both course-single banners rolled out from the single-slug live test to every `formacao` single** (Daniel: "these 2 sections were implemented on [the test bootcamp] — make the same alterations to all product pages"). In `class-pillar-cross-links.php` the three `BANNER_TEST_SLUG` gates were removed:
  - **Mini "EDIT. para Empresas" banner** ("Faça esta formação com a *sua equipa*" + DGERT lockup + "Conhecer a EDIT. para Empresas →" swipe-cta) — now on all product pages; hides the old theme in-company banner (`section.banner.split-black-grey`).
  - **"Esta formação faz parte de" related-areas color-block banner** — renders the pillar(s) a course belongs to (reverse-lookup against pillar CATALOG constants); self-skips courses not listed in any pillar (empty `$hits`).
  - `emit_styles()` now emits the `epb-*` / `ceb-mini` banner CSS on every formacao single (was gated to the test slug). The `BANNER_TEST_SLUG` constant removed; injection still scoped to `is_singular('formacao')`, idempotency via markup markers.

## v1.5.534 — 2026-06-15 (Pillars — unified catalog header + dated cards sorted first)
- **Catalog header unified to the Marketing Digital layout across all pillars** (Daniel: "the headers for this section do not match, implement the digital marketing layout in all pillar pages"). Data Science, Inteligência Artificial, Programação and UX/UI Design used a plain centered `.md-section-title`; swapped to the `.md-section-header` block (eyebrow "N programas DGERT-certificados" + left title + right `__lead` description) — identical structure to Marketing Digital (+ its IA variant, which already had it). The `.md-section-header` CSS already ships in the shared `assets/marketing-digital.css` every pillar enqueues, so no CSS change needed. Per-pillar eyebrow counts (DS 11 · IA 11 · Programação 10 · UX/UI 12) and a domain-specific lead clause each.
- **Course cards now sort dated-first within each group** (Daniel: "sort the course cards, cards with dates list on top"). New `EDIT_Pillar_Courses::sort_slugs_dated_first()` stable-partitions a group's slugs so cards carrying a start date (detected via the scraped `course-date` Portuguese month+year) render before undated cards; relative order preserved otherwise. Wired into all six pillar catalog loops (MD, DS, IA, Programação, UX/UI, MD-IA). Schema ItemList order left as authored.

## v1.5.533 — 2026-06-15 (Alumni/client wall — remove Logo.dev attribution)
- **Removed the "Logos servidos via Logo.dev" attribution line** (`.ae-attribution`) from `EDIT_Alumni_Employers::render()` (Daniel). Affects every surface the wall renders — the empresas in-company client wall + the pillar alumni walls. NOTE: Logo.dev's free tier requires attribution for commercial use; dropping it may need a paid Logo.dev plan to stay compliant. CSS rules left in place (unused, harmless).

## v1.5.532 — 2026-06-15 (Empresas — Financiamento heading/CTA copy + Process overlap fix)
- **Financiamento heading forced to 2 lines** (Daniel). `Não é o motivo. É o desbloqueio.` was wrapping to 3 lines ("É o" / "desbloqueio." split). Added an explicit `<br>` after "motivo." and widened `.financing .section-title` max-width 16ch → 20ch so line 2 ("É o desbloqueio.") holds together.
- **Primary CTA + card link copy → "Fale connosco"** (Daniel). Big swipe-cta button "Verificar a vossa elegibilidade" → "Fale connosco"; the card micro-link "Falar connosco →" aligned to "Fale connosco →" for consistency. (Locked header CTA "Fala connosco" left untouched.)
- **Process timeline overlap fixed on intermediate widths** (Daniel: "content overlap bad formatting in smaller screens"). The wave SVG is 1400×400 (3.5:1) so its height shrinks with viewport width, but the absolutely-positioned "above" step blocks (48 HORAS / 1–2 SEMANAS badges + titles) are fixed-height and overflowed upward into the section title — only ~3px clearance even at full width. Fix: `.process-wave-v2` top margin 128px → 200px (slack across the wave-active range), and raised the stack breakpoint 880px → 1000px so tablets/smaller laptops get the clean vertical numbered stack instead of the compressed wave.

## v1.5.531 — 2026-06-14 (Empresas — in-company client logo wall, pillar design)
- **Replaced the empresas client logo grid with the pillar-page wall design** (Daniel: "Create a logo wall… use the pillar page design"). The old `.logo-wall` → `.logo-grid` of bundled greyscale PNGs (capped at 12, from `self::clients()`) is swapped for `EDIT_Alumni_Employers::render()` — the same colour-logo + name-caption card wall used on the pillar pages, now driven by a curated list of **24 in-company clients** (won corporate-training deals, selected from Pipedrive). Heading eyebrow "Clientes Empresas", no LinkedIn badge (these are clients, not alumni employers), plain (non-linked) eyebrow.
- **Generalised `EDIT_Alumni_Employers::render()`** to accept two optional params: `?array $employers` (custom list overriding the default alumni roster — each item needs `name` + `domain` + optional `logo_domain`) and `?string $eyebrow_url` (`null` = default alumni LinkedIn link for back-compat; `''` = render the eyebrow as plain text, used by the client wall). Pillar/homepage alumni calls are unchanged.
- Clients (recognizability order): Worten · Sonae MC · Galp · EDP · Goldenergy · TAP · Altice · Nestlé · Adidas · Yves Rocher · Pfizer · AGEAS · Cetelem · Natixis · BIAL · Leya · Cofina · Media Capital · WPP Portugal · Farfetch · Neutroplast · Infraestruturas de Portugal · CM Porto · Grupo Dia. All logos verified non-blank via Logo.dev; per-domain fixes: Farfetch→`aboutfarfetch.com`, Media Capital→`mediacapital.pt`, Goldenergy→`goldenergy.pt` (the bare `grupomediacapital`/`goldenergy.com` domains returned generic monograms).

## v1.5.530 — 2026-06-14 (Empresas form — submit button → standard swipe-cta)
- **"Pedir Proposta" submit button now uses the site-standard swipe-cta** (Daniel: "use standard button in form"). Replaced the plain `background→pink` hover with the swipe markup (3 `swipe-layer` spans pink/teal/black + `swipe-label`) on the `<button type=submit>`; base stays the full-width ink pill, the global `.swipe-cta` CSS supplies the sweep + yellow text flip (same treatment as the áreas + Financiamento CTAs already on the page). Added `.btn-submit .swipe-label{justify-content:center;width:100%}` to keep the label centered full-width.
- **Form JS retargeted to the label span.** The submit handler set `submitBtn.textContent` for the "A enviar…" / "Pedir Proposta" states, which would have wiped the swipe spans. Now caches `submitLabel = submitBtn.querySelector('.swipe-label')` and updates that instead, so the swipe markup survives the loading/error state changes.

## v1.5.529 — 2026-06-14 (Alumni Employers wall — colour logos + website links + name captions + badge link)
- **Logos now render in full colour** (Daniel: "use color versions"). Removed the `filter:grayscale(1) opacity(0.65)` treatment + the grayscale hover transition from `.ae-logo`. Logos served by Logo.dev display in their native brand colours on all surfaces (pillar pages, Empresas, etc.).
- **Each logo card links to the company's institutional website** (Daniel: "add the companys institutional websites as links"). Card `href` changed from the per-company LinkedIn alumni search to `https://{domain}` via new `company_url()`. Opens in a new tab. The site-wide alumni/LinkedIn function now lives on the badge (below).
- **Company name shown as an always-visible text caption** under each logo (Daniel: "add a text item for company names"). New `.ae-name` element + `.ae-logo-wrap` (fixed 46px logo zone so captions baseline-align across logos of different aspect ratios). Card markup is now a vertical stack: logo zone + name. The old display:none `.ae-fallback-name` is replaced — the name is always present, so a broken/blank Logo.dev image (`.ae-item-link--fallback` hides the empty logo zone) still leaves a labelled card instead of a blank cell.
- **"LinkedIn Verified" badge is now a link** to the EDIT. alumni list (Daniel: "add a link to EDIT Business Page Alumni to the Linkedin verified"). `<p class="ae-badge">` → `<a>` pointing at `linkedin.com/school/edit-education/people/` (the school People tab, where alumni live) with an `↗` affordance + hover state.
- **Farfetch logo fix.** Logo.dev returns a blank/transparent image for `farfetch.com` (the cause of the empty top-left cell on the pillar walls). Added an optional per-employer `logo_domain` override (used by `logo_url()` only — the website link still uses `domain`); Farfetch now resolves its mark via `aboutfarfetch.com`, which serves the official Farfetch "FF" logo. No hosted asset needed.
- Adjusted variant sizing for the taller captioned cards: wall/grid `aspect-ratio` 5/3 → 5/3.4; strip item 104×60 → 118×84.

## v1.5.528 — 2026-06-14 (Empresas subdomain: footer social icons + form checkboxes)
- **Footer social icons fixed** (Daniel: "social media icons are missing from the footer"). They rendered as bare letters `f i v y l` because the theme's `social-icons.css` declares the `icomoon` `@font-face` with a **relative** src — on `empresas.weareedit.io` that resolves against weareedit.io, i.e. a cross-origin font fetch with no `Access-Control-Allow-Origin` header → browser blocks it → the `.icon-*:before` codepoints (`\66`=f, `\69`=i, `\76`=v, `\79`=y, `\6c`=l) fall back to literal letters. Fix: re-declared the `icomoon` `@font-face` in the page's inline CSS (prints at `PHP_INT_MAX`, after theme CSS) with **root-relative** srcs (`/wp-content/themes/weareedit/css/fonts/social_icons.ttf|woff`) so the font loads same-origin on the subdomain. The subdomain serves the same docroot, so the file resolves (verified 200).
- **Form "Áreas de interesse" checkboxes fixed** (Daniel: "this form is missing the area selections"). The 5 checkboxes were invisible because the theme `style.css` hides ALL native checkboxes site-wide (`input[type=checkbox]{display:none;position:absolute}`) — it draws custom boxes on a sibling `<span>` our form doesn't use; bootstrap also adds `clip:rect(0,0,0,0);pointer-events:none`. Fix: scoped `!important` override on `.lead-form .checkbox-group input[type=checkbox]` forcing the native control back (`display:inline-block;position:static;appearance:checkbox;opacity:1;clip:auto;pointer-events:auto;16×16;accent-color:ink`). Tightly scoped so nothing else on the page is affected.

## v1.5.527 — 2026-06-14 (Financiamento — standard button + link mouse-overs)
- **Primary CTA** "Verificar a vossa elegibilidade" now uses the site-standard **swipe-cta** hover (pink→teal→black sweep + yellow text flip). Added swipe-cta markup (3 layers + swipe-label) + class; removed the custom translateY hover. Base = solid ink pill; global `.swipe-cta` CSS supplies the animation.
- **Card micro-CTAs** (`.fin-card__lnk`: Verificar elegibilidade / Saber mais / Falar connosco) got a standard link mouse-over: text+arrow → brand pink + arrow slides right on hover.

## v1.5.526 — 2026-06-14 (Empresas FAQ → pillar centered column + section spacing)
- **FAQ matched to pillar** (Daniel: "match Empresas to Pillar"). Reverted the v1.5.524 full-width — set `.md-faq` section-title/q/a back to `max-width:1280px` + `60px` gutters (pillar exact), `.md-faq__list`/`.md-faq__item` stay full-width so dividers/hover span edge-to-edge with content centered. Now identical layout to the pillar FAQ.
- **Section spacing** (Daniel): `.empresas-intro` bottom padding 16px → 96px (lede was crammed against the yellow financing banner); `.process` bottom 100px → 140px (cards were tight against the grey founder-quote section).

## v1.5.525 — 2026-06-14 (Fix Financiamento trust-badge — theme .badge collision)
- The Financiamento trust line rendered with a grey rounded pill behind part of the DGERT text + forced onto one line crashing into the CTA. Cause: generic class `.badge` collided with the theme's `.badge` component (grey bg + nowrap); our rule didn't override `background`. Fix: renamed `.badge` → `.fin-trust-badge` (markup + CSS) and added explicit `background:none;padding:0;border-radius:0;white-space:normal`. Same class-collision pattern as the v1.5.523 `.faq`→`.emp-faq` rename.

## v1.5.524 — 2026-06-14 (Course video swap → YouTube + empresas FAQ full-width)
- **Course promo video replaced** (Daniel: "replace, do not fix; previous was Vimeo"). Shared Vimeo `927121971` (removed/private → "vídeo não disponível" on curso-uxui-porto, curso-marketing-digital-online, curso-uxui-online…) swapped to YouTube `Q8qqSbHz8uM` via output-buffer `str_replace` (`player.vimeo.com/video/927121971` → `www.youtube.com/embed/Q8qqSbHz8uM`), covering both the WP-Rocket `data-lazy-src` and `<noscript>` fallback. Targets the specific id, so other course videos untouched.
- **Empresas FAQ → full-page width** (Daniel). Removed the 1180px max-width cap on `.md-faq` section-title/list/q/a so the FAQ spans the full viewport (48px edge gutter retained).

## v1.5.523 — 2026-06-14 (Empresas Financiamento light-premium + FAQ matches pillar md-faq)
- **Financiamento section → light premium** (Daniel: "dark color does not help; most important section to convince the lead"). Dark `.financing` → cream paper (#f7f6f2) editorial: white cards w/ hover-lift, surfaced **"até 100%"** stat (own FAQ claim), yellow-underline accent on "desbloqueio", refined lede ("tratamos da candidatura convosco"), per-card micro-CTAs, sharper primary CTA "Verificar a vossa elegibilidade". Approved mockup `financiamento-section-light-premium.html`.
- **Empresas FAQ → matches pillar `md-faq` design** (Daniel: "match the faqs design from the pillar pages"). Plain `.faq` + `+/−` accordion → `md-faq` markup: numbered items (counter), → arrow rotating 90° on open, pink active state, light variant. CSS copied + flattened from `assets/marketing-digital.css` (`.md-faq` + `.md-pillar--light` overrides) into the empresas scoped block since that asset isn't enqueued on the subdomain. Renamed section class `.faq` → `.emp-faq` to kill the theme `.faq` dark-component collision; retired the v1.5.498 `!important` neutralizers. Headline kept with pink accent on "fazem".

## v1.5.522 — 2026-06-14 (Empresas Process step 04 time + FAQ timeline aligned)
- `PROCESS` step 04 "Avaliação de Impacto" `time` `3 + 6 meses` → `3–6 meses` (en-dash to match step 03 badge style) (Daniel).
- FAQ "Quanto tempo demora…" aligned to the new Process timeline: `48 horas` diagnóstico, `1 semana` desenho, `1-2 semanas` entrega, avaliação `3-6 meses` (was the stale 1-2 sem / 4-12 sem / 1 sem). No more contradiction between Process badges and FAQ.

## v1.5.521 — 2026-06-14 (Empresas Process step 03 — time 4–12→1–2 semanas + body)
- `PROCESS` step 03 "Entrega": `time` `4–12 semanas` → `1–2 semanas`; body opener `Sessões in-house` → `Sessões nas vossas instalações` (fixed `na vossas` → `nas vossas`) (Daniel). FAQ "Quanto tempo demora…" now diverges further (still says 1-2 sem diagnóstico + 4-12 sem entrega) — pending Daniel's call to align.

## v1.5.520 — 2026-06-14 (Empresas Process step 02 body — add equipa pedagógica)
- `PROCESS` step 02 "Desenho do Programa" body now opens "A nossa equipa pedagógica em conjunto com o lead instructor desenha um syllabus…" (Daniel). Fixed typo `e conjunto` → `em conjunto`.

## v1.5.519 — 2026-06-14 (Empresas Process step 01 timeframe: 1–2 semanas → 48 horas)
- `EDIT_Formacao_Corporativa_Page::PROCESS` step 01 "Diagnóstico" `time` changed `1–2 semanas` → `48 horas` (Daniel). Surfaces on the empresas Process section. NOTE: the on-page FAQ ("Quanto tempo demora…") still says "1-2 semanas de diagnóstico" — left as-is pending Daniel's call on whether to align.

## v1.5.518 — 2026-06-14 (Empresas duplicate-canonical fix — launch-critical SEO)
- **Bug:** `empresas.weareedit.io` shipped TWO `<link rel="canonical">` tags — ours (correct, → empresas) plus a stray one from Rank Math / WP-core rel_canonical pointing at `https://weareedit.io/` (the front-page context). Google reads the homepage canonical and treats empresas as a duplicate → it would never index on its own. Live-verified via curl (2 canonicals) and `site:` search (empresas not indexed).
- **Fix:** wrapped `render_page()` output in `ob_start([__CLASS__,'dedupe_canonical'])`. New `dedupe_canonical()` strips EVERY canonical tag (both ` />` and `>` styles, any attribute order) from the final HTML and re-inserts exactly one self-referencing empresas canonical at the top of `<head>`. Bulletproof regardless of whether the `rank_math/frontend/canonical` filter fires. Pillars were already clean (single self-canonical) — untouched.

## v1.5.412 — 2026-06-12 (Overlay comparison fix: DGERT size + hide breadcrumbs to match homepage)
- DGERT lockup overshot in v1.5.411. Logo 80px → 56px (matches homepage actual height). Text 22px → 18px. Gap 22px → 18px. Now matches homepage prominence exactly.
- **Hide theme breadcrumbs on pillar pages** — homepage hero has no breadcrumb strip above it; the pillar's breadcrumbs were pushing the hero down. CSS rule hides `.breadcrumb`, `.breadcrumbs`, `#breadcrumbs`, `.yoast-breadcrumb`, `.rank-math-breadcrumb` site-wide on any page containing `.md-pillar`.

## v1.5.411 — 2026-06-12 (Pillar hero: DGERT lockup at homepage size + move content up)
- **DGERT lockup enlarged to homepage spec:** logo 38px → 80px height, text 14px → 22px, gap 14px → 22px between logo and text. Now matches the homepage prominence.
- **Hero content moved up:** changed flex alignment from `align-items:center` (vertically centered in viewport) to `align-items:flex-start` (anchored to top). Padding-top bumped 64px → 96px. Min-height 100vh → 90vh. Hero content now sits near the top of the viewport — content pushed down look is gone.

## v1.5.410 — 2026-06-12 (URL-pattern hijack — bypasses rewrite rules entirely)
- Added `parse_request` hook (priority 1) that matches `/internal-marketing-documents/` and `/internal-marketing-documents/{slug}/` directly from `$_SERVER['REQUEST_URI']`. Runs before WP's 404 routing kicks in. Renders the doc and exits — no rewrite rule + .htaccess flush dependency at all.
- The original rewrite-rule path is kept as the "clean" route, but the URL-hijack is now the resilient fallback that works regardless of opcache state, .htaccess permissions, or WP rewrite cache.

## v1.5.409 — 2026-06-12 (Version-keyed rewrite flush flag — kills the /internal-marketing-documents/ 404 for good)
- The flush flag is now version-keyed: `edit_imd_rewrites_flushed_{version}`. Every plugin release triggers a one-time flush automatically. Removes the need to manually bump the flag suffix every time WP's rewrite rules get reset (e.g., on plugin deactivate/reactivate, opcache shenanigans, or theme switches).
- Daniel manual fix that always works as a backup: WP Admin → Definições → Hiperligações permanentes → Guardar alterações.

## v1.5.408 — 2026-06-12 (Pillar hero: left-aligned + lighter H1 weight + smaller size)
- Hero alignment switched from `text-align:center` to `text-align:left`, `align-items:center` to `flex-start`. Daniel preferred the left-aligned editorial feel over the centered homepage layout.
- H1 weight reduced from 600 → 500 to match the homepage's lighter display feel (was too heavy).
- H1 size reduced from clamp(64px, 11vw, 148px) → clamp(56px, 8.5vw, 120px). Less crowding on longer lines like "Inteligência Artificial".
- Letter-spacing relaxed from -.04em → -.02em. Wider, more readable.
- Line-height bumped from .95 → 1.0 for breathing room.

## v1.5.407 — 2026-06-12 (Fix: /internal-marketing-documents/ 404 — bump rewrite flush flag)
- `/internal-marketing-documents/` started returning 404 after the v1.5.402 deactivate/reactivate cycle reset WordPress's rewrite rules. Our flush flag (`edit_imd_rewrites_flushed_v1`) was still set, so `EDIT_Internal_Marketing_Docs::maybe_flush_rewrites()` thought it had already done the work and skipped re-registration.
- Bumped flag to `_v2` so the rules get re-flushed on next admin page load. Routes will resolve again immediately after this update.

## v1.5.406 — 2026-06-12 (Pillar H1: "na EDIT" → "Inteligência Artificial" — signals IA as transversal layer)
- H1 line 2 changed from "na EDIT." to "Inteligência Artificial." — every EDIT. pillar now signals the pillar topic + IA transversal positioning. Marketing Digital + IA. Data Science + IA. UX/UI + IA. The IA layer is the through-line across all 5 verticals.
- Pattern to apply to the other 4 pillars when they get the homepage-style hero treatment.

## v1.5.405 — 2026-06-12 (Pillar hero: match homepage spec — centered, pink/teal dots, no DGERT box)
- **Centered the hero content** to match homepage layout (`text-align:center` + flex column with align-items center). Was left-aligned, now content stack centers like the homepage Future-Proof. Transformation. block.
- **Fixed dot colors** — `.md-dot--pink` and `.md-dot--teal` were being overridden to yellow by the broad `.md-hero__title span` rule. Scoped that rule to `.md-hero__title:not(.md-hero__title--xl)` so the XL homepage-spec hero gets its real pink (#f92869) and teal (#60c5b3) dots.
- **DGERT lockup styling** — removed border + background on the hero-top variant with `!important` overrides. Now inline like the homepage (no pill box). Smaller logo (38px), lighter text weight.
- H1 weight reduced from 700 → 600 to match the homepage's lighter display weight.

## v1.5.404 — 2026-06-12 (Pillar hero replicates homepage structure + video bg, drops Cheque Formação)
- **Hero restructured to mirror homepage hero** (per `project_hp_hero_locked`):
  - Video background: `/wp-content/uploads/2026/03/waves-sequence-compressed.mp4` (autoplay, muted, loop, playsinline, 0.7 opacity over a dark veil for legibility).
  - DGERT lockup moved to TOP of the hero (subtle no-border variant — homepage style).
  - Eyebrow dropped (header sits inside the video).
  - H1 enlarged to clamp(64px, 11vw, 148px) with -.04em tracking, pink dot after "Marketing Digital", teal dot after "EDIT". Matches the homepage Future-Proof. Transformation. dot pattern.
  - Lede colour changed to brand yellow (clamp 20-26px). Tighter line-height. Up to 980px width.
  - CTA simplified: single yellow primary button + inline `★ 4.1 / 67 reviews no Google` rating link (replaces the 4-pill bar + ghost button).
  - Removed the pill bar (DGERT pill + reviews pill + 600+ alumni pill + Lisboa Porto Online pill) — info redistributed into the lede + the rating link + the DGERT lockup.
- **Cheque Formação removed** from pillar body copy (per the program sunset Jun 30, 2026):
  - Hero lede: `...Cheque Formação + Digital elegível.` → `...Programas SIFIDE-elegíveis.`
  - "Porquê EDIT." bullet: `SIFIDE, POPH, e Cheque Formação + Digital` → `SIFIDE (crédito fiscal até 35% sobre o investimento em formação)`
  - Intro CTA lead: `...Todos elegíveis para Cheque Formação + Digital.` → `...Todos elegíveis para SIFIDE.`
  - FAQ #3 reframed: `Posso usar o Cheque Formação + Digital?` → `Que opções de financiamento existem para a formação?` with SIFIDE-led answer.
  - Rank Math description updated: SIFIDE-elegíveis (not Cheque Formação) + adds employer-name social proof (Farfetch, Sonae, NOS, EDP).

## v1.5.403 — 2026-06-11 (DGERT lockup in hero + mid-page banner)
- Hero: small DGERT lockup added below the CTAs — logo + "Entidade Formadora Certificada" label + arrow link to the DGERT entidades certificadas portal.
- Mid-page band: full-width DGERT banner inserted between the catalog and the tutors section. Logo + "DGERT nº 18391" eyebrow + "Entidade Formadora Certificada" title + SIFIDE-elegível sub-copy + arrow link to DGERT portal.
- Whole band is clickable (link wrapper) with hover state (background lifts, arrow turns yellow + slides up-right). Mobile collapses to single-column.
- DGERT logo asset reused: `/assets/dgert-entidade-formadora-branco.png` (already shipped site-wide).

## v1.5.402 — 2026-06-11 (Hero typography matches homepage spec + opcache activation hook)
- `.md-hero__title` typography matched to the homepage hero locked spec (per `project_hp_hero_locked` v1.5.54): `clamp(56px, 9.5vw, 100px)` size + `-.0313em` letter-spacing (was `clamp(48px, 8vw, 96px)` and `-.02em`). Tighter, larger, more editorial — consistent across the site.
- **`register_activation_hook` calls `opcache_reset()`** — defensive fix for the opcache-stuck-on-old-bytecode loop where the `upgrader_process_complete` hook in v1.5.398+ didn't fire because the OLD plugin code (loaded from stale opcache) didn't have the hook. Now a manual deactivate + reactivate cycle in WP Admin guarantees a fresh bytecode load. Required to recover card-color fixes from v1.5.393 if they didn't take effect.

## v1.5.401 — 2026-06-11 (Group titles: match standard eyebrow style)
- `.md-group__title` (BOOTCAMPS, CURSOS, WORKSHOPS, CROSSOVER IA labels) was 22px yellow with .1em letter-spacing — too dominant. Now matches the standard eyebrow style established by `.md-section-header__eyebrow`: 13px, grey at 55% opacity, .18em letter-spacing, uppercase, 600 weight. Creates consistent visual hierarchy.

## v1.5.400 — 2026-06-11 (Catalog header: applied 2-col section-header pattern + dropped Cheque Formação)
- "Catálogo de Formação em Marketing Digital" header now uses the reusable `.md-section-header` 2-col layout (eyebrow + h2 title LEFT, lead body RIGHT). Matches the brand reference + the intro section pattern shipped in v1.5.398.
- Eyebrow: "16 programas DGERT-certificados" — adds quantifier + credibility signal.
- Lead body copy mentions SIFIDE (not Cheque Formação — program sunsetting per v1.5.399 brief update).
- Generalised the CSS pattern as `.md-section-header` (was `.md-intro__header`) so it's reusable on tutors header, FAQ header, final CTA, and on the other 4 pillars when their V2 intros land.

## v1.5.399 — 2026-06-11 (Empresas LinkedIn brief: drop Cheque Formação — program sunsets Jun 30)
- Cheque Formação + Digital program sunsets Jun 30, 2026 (~19 days). Brief was over-weighting it — the 100 Sep peak won't repeat in 2026 once the program is gone. Removed from H1, findings table flagged with sunset warning, Angle B and Angle C ad copy rewritten to drop the program mention, B2C long-tail keyword swapped from "academia digital cheque formação" to DGERT/SIFIDE variants, September seasonality narrative reframed as "uncertain post-sunset".
- **New optimal H1:** `"Formação ChatGPT para Empresas. DGERT-certificada. SIFIDE-elegível."` (was: "...Cheque Formação elegível."). SIFIDE replaces Cheque Formação as the surviving financial-trigger anchor.
- Added a candid note inside the strategic implication block explaining the "formação digital para empresas" zero-volume reading: Google Trends has a detection threshold (~1-5 searches/week minimum) below which terms show null even when there ARE real searches. The verbatim phrase isn't dominant, but the underlying intent was being carried in the data by adjacent terms (Cheque Formação as program brand, formação ChatGPT, DGERT). With Cheque Formação sunsetting, that proxy signal disappears — confirming why Cheque Formação shouldn't anchor the campaign.
- Chart label updated: "cheque formação (sunset Jun 30 2026)" so anyone reading the chart understands the trend won't repeat.

## v1.5.398 — 2026-06-11 (Pillar intro: editorial 2-col header — eyebrow + h2 left / lead body right)
- Refactored `.md-intro` section header to use a 2-column editorial layout: eyebrow + H2 title in the left column, lead paragraph in the right column. Matches the canonical EDIT. section-header pattern (per Daniel's brand reference screenshot 2026-06-11).
- CSS: 1fr / 1.4fr grid, 80px gap, 1280px container. Mobile falls back to single column with 32px gap.
- Typography: eyebrow grey at 55% opacity (was bright yellow — softer, more editorial). H2 lifted to clamp(40px, 4.5vw, 56px). Lead at 75% opacity, max-width 640px.
- PHP: `class-marketing-digital-page.php` wraps the eyebrow + title in `.md-intro__header-left` and the lead paragraph beside it inside `.md-intro__header` grid.
- Currently only Marketing Digital has the V2 long-form intro (per `task_pillar_pages_v2.md`); the same `.md-intro__header` pattern is ready to be applied to Data Science, UX/UI Design, IA, Programação when their V2 intros are written.

## v1.5.397 — 2026-06-11 (Empresas LinkedIn brief: corrected vocabulary + B2C "academia digital" play)
- **New PT vocabulary findings (Google Trends, same-query comparison):**
  - **ChatGPT empresas** — avg ~65, peaks 100, year-round. PT buyers anchor on the TOOL NAME, not concepts.
  - **formação ChatGPT** — avg ~55-65, peaks 100 (Feb 16) · 84 (Jul 6) · 72 (Sep 14). Strong training-buyer intent.
  - **formação IA** — sporadic but real (73 in Jul, 57 in Oct).
  - **academia digital** — high (avg ~55, peaks 100) BUT brand-fragmented across Santander · BPI · NOS · EDP · IEFP/Câmara programs · minor Google variants. Bad B2B anchor (competing with banks); GOOD B2C opportunity.
  - **Zero-volume confirmed:** IA para empresas · curso IA empresas · agentes IA empresas · automação IA empresas · Copilot empresas · upskilling IA. PT corporate buyers search the tool, not the abstract concept.
- **New optimal H1** for empresas.weareedit.io: **"Formação ChatGPT para Empresas. DGERT-certificada. Cheque Formação elegível."** — compounds the 3 anchors EDIT. can actually win (high volume + clear intent + uncontested by big PT brands).
- **Added "Bonus play — B2C tap into Academia Digital sphere"** section: 7 tactics ranked by leverage for EDIT.'s B2C funnel to capture the academia digital intent without head-term competition with bank academies. Includes SEO pillar at /academia-digital/, long-tail comparison content, tagline adoption, long-tail PPC, alumni reviews boost, comparison landing page, competitor-brand keyword bids.
- **Reframed Angle A** in the brief from generic "AI changed your team" → specific "ChatGPT changed how your team works" — matches the dominant PT search query.

## v1.5.396 — 2026-06-11 (Empresas LinkedIn brief: Google Trends data + minimal-investment Phase 0)
- Added **Section 0 "Search demand reality check"** to the LinkedIn brief — Google Trends data for B2B Portuguese vocabulary over the last 12 months. Pulled live via Supermetrics MCP.
- **Major strategic finding:** "formação corporativa", "upskilling", "reskilling", "formação para empresas", "formação à medida" all have **zero PT search volume**. Real high-volume PT B2B search vocabulary is **DGERT (25 avg, peak 62) + Cheque Formação (peak 100 in Sep 2025) + SIFIDE (11 avg, niche but steady)**. Both DGERT and Cheque Formação spike sharply in W37/2025 (Sep 14) — annual buying window.
- **Implication for the campaign:** doubles the strength of Angle C (Financial/CFO hook). Landing page H1 + ad headlines should anchor on DGERT + Cheque Formação + SIFIDE.
- **Pivoted budget approach to minimal-investment Phase 0:** Days 1-30 = €0 cash (max €200 for boost-post tests if an organic post overperforms) · 100% content-led · grow EDIT. Business Page followers (target +500-1,000) · all 3 angles tested in organic posts. Paid 3-campaign structure deferred to Phase 1 (Days 31-90) only if Day-30 follower / inbound DM signals validate. Worst-case 30-day exposure: €0-200.
- Chart.js time-series visualisation embedded showing the 12-month trends for DGERT, Cheque Formação, SIFIDE.

## v1.5.395 — 2026-06-11 (Pillar brand font + Empresas LinkedIn brief)
- **Pillar brand font fix:** `.md-pillar` headings + body text now explicitly set `font-family:'SctoGroteskA','Helvetica Neue',Helvetica,Arial,sans-serif`. Without this rule the shortcode-rendered headings were falling back to the system font, breaking visual consistency vs the rest of the site. Affects all 5 pillars.
- **New internal doc:** `empresas-linkedin-acquisition-2026-06-11.html` — full strategy brief for the EDIT. Empresas LinkedIn acquisition push. 3-campaign architecture (Awareness + Lead Gen + Discovery Calls), audience definition, 3 creative angles, Lead Gen form → Brevo mapping, content-production plan, 4-week launch calendar, Day-1 launch checklist, risks. English. Available at `/internal-marketing-documents/empresas-linkedin-acquisition-2026-06-11/`.

## v1.5.394 — 2026-06-11 (Marketing Digital CATALOG: move Google Analytics 4 to Workshops)
- `curso-google-analytics-4` slug renders as "Workshop Remote Learning Google Analytics 4" — was misplaced in the Cursos array, which with v1.5.393's group-based override would have coloured it yellow. Moved to Workshops so it renders teal.

## v1.5.393 — 2026-06-11 (Pillar cards: group-driven SVG bg + opcache reset on update)
- Replaced fragile URL-pattern regex (v1.5.391) with authoritative group-based override. The pillar PHP files already know which group each card belongs to ("Bootcamps", "Workshops", "Cursos", "Crossover IA"); now they pass that hint to `EDIT_Pillar_Courses::render_card($slug, $group)`. Override is deterministic, no regex gymnastics, catches all mis-tagged cards regardless of slug shape.
- `GROUP_BG` map is the source of truth: Bootcamps + Crossover IA → bootcamp-bg.svg (pink), Workshops → workshop-bg-1.svg (teal), Cursos → bg-curso.svg (yellow) with `-online` / `-remote` / `remote-learning-` variants overridden to bg-remote.svg (blue).
- All 5 pillar PHP files (Marketing Digital, Data Science, UX/UI Design, Inteligência Artificial, Programação) updated to pass `$group` as 2nd arg. Backwards-compatible: `render_card($slug)` still works (empty hint → no override).
- **`opcache_reset()` hook on `upgrader_process_complete`** — when this plugin is updated via WP one-click update, PHP opcache is flushed so new class bytecode loads immediately. Diagnosis from v1.5.391/392: new code on disk wasn't being executed because opcache served stale v1.5.390 bytecode for ~10-30 min after update.

## v1.5.392 — 2026-06-11 (Force opcache + transient cache refresh)
- v1.5.391's URL override didn't take effect on live — PHP opcache was almost certainly serving the old v1.5.390 bytecode despite the new file being on disk. Bumped `CACHE_KEY` to `v4` so the transient is freshly populated, and version bump forces WP to re-extract files (which should trigger opcache invalidation via mtime change).
- No functional code changes vs v1.5.391; same override logic. If override still doesn't fire after this deploy, opcache needs manual flush (host panel or `opcache_reset()` via WP-CLI).

## v1.5.391 — 2026-06-11 (Pillar cards: defensive SVG override for mis-tagged workshops/bootcamps)
- 5+ workshop posts AND `digital-marketing-foundations-bootcamp-remote` have the wrong `formacao_tipo` taxonomy / Tipo Destaque in WP. Result: their `/formacao/` cards carry `data-bg="bg-curso.svg"` (yellow) instead of their correct typology bg.
- `EDIT_Pillar_Courses::rewrite_for_pillar()` now detects URL pattern and forces the right SVG:
  - `/formacao/workshop-*` or `/formacao/remote-learning-workshop-*` → workshop-bg-1.svg (TEAL)
  - `/formacao/bootcamp-*` or `digital-marketing-foundations-bootcamp` → bootcamp-bg.svg (PINK)
- This is a defensive override that masks the upstream WP-data bug on the 5 pillar pages. The /formacao/ archive itself still renders the mis-tagged cards yellow — that needs per-post fixes in WP Admin (Tipo Destaque field).
- TODO for content team: fix the Tipo Destaque field on these posts so /formacao/ archive also renders correctly:
  - workshop-paid-media-performance
  - workshop-data-analytics-with-ai-2
  - workshop-loyalty-marketing (slug may differ — check)
  - workshop-influencer-marketing (slug may differ — check)
  - workshop-professional-growth-success
  - digital-marketing-foundations-bootcamp-remote

## v1.5.390 — 2026-06-11 (Pillar cards: bake SVG bg inline + drop rocket-lazyload — applies to all 5 pillars)
- **Root cause for the v1.5.389 mixed-colour state:** scraped cards arrive from /formacao/ with `class="course-box text-black rocket-lazyload"` and `style=""` and `data-bg="..."`. WP Rocket's lazy-load JS converts `data-bg` → `style.background-image` when the card scrolls into view — but that JS doesn't reliably fire for HTML injected by the pillar shortcode. Result: SOME cards rendered with their SVG bg (cached/eager-loaded), OTHERS rendered with no bg at all (yellow fallback).
- **Fix:** `EDIT_Pillar_Courses::rewrite_for_pillar()` now strips `rocket-lazyload` class + inlines the background-image directly from `data-bg`. SVG renders on first paint, no JS dependency. Idempotent: cards without `data-bg` pass through unchanged.
- **Reverted v1.5.389's CSS [data-bg*=] color overrides** — fragile pattern matching that missed any card whose `data-bg` had already been consumed by lazy-load. Native SVG colors (bootcamp PINK / workshop TEAL / remote BLUE / curso YELLOW) are correct typology per locked standard.
- Kept v1.5.389's ghost-CTA fix, hidden-edit-link rule, and Marketing Digital catalog reorder (Google Ads bootcamp leads).
- Applies to all 5 pillar pages (Marketing Digital, Data Science, UX/UI Design, Inteligência Artificial, Programação) — they all share the same `EDIT_Pillar_Courses::render_card()` pipeline.

## v1.5.389 — 2026-06-11 (Marketing Digital pillar: typology colours, contrast, ghost CTA, reorder, hide stray Edit link)
- **Typology-coloured catalog cards** per locked standard (see `project_newsletter_strip_locked.md` v1.5.186). Cards now render in their brand-typology colour: Bootcamp PINK (`#f92869`), Workshop TEAL (`#60c5b3`), Remote BLUE (`#0090eb`), Presencial YELLOW (`#ffdd06`). SVG backgrounds suppressed in favour of solid brand colours so the pillar matches the rest of the site's visual identity.
- **Card title contrast fix** — force white text on bootcamp / workshop / remote cards (was unreadable dark text on dark backgrounds in the previous build). Presencial yellow keeps black text.
- **Hero ghost CTA** — `Falar com um consultor` button now has solid 2px white border (was rgba `.3` invisible against black). Hover inverts to white-fill + black text.
- **Catalog reorder** — Google Ads bootcamp leads the Bootcamps row (was position #2). Driven by Education Foresight & Growth Strategy 2026-06-10 finding that "google ads" is the dominant PT search term (44 avg interest, highest in Marketing Digital vertical) + CEO push 2026-06-11 to make this the funnel anchor.
- **Hidden stray "Edit" admin link** — WP theme's `edit_post_link()` output was bleeding into the page for logged-in editors. Now hidden via CSS on `.md-pillar` scope.

## v1.5.388 — 2026-06-11 (Internal Marketing Documents: add PT translation of Education Foresight strategy)
- Added `education-foresight-strategy-2026-06-10-pt.html` — full PT-PT translation of the strategy doc. Same data, same charts, prose translated.
- Updated EN file title + H1 to include `· EN ·` marker so the two versions are distinguishable in the index. Both now show as paired siblings.
- Translation: 60,081 chars (3% longer than EN, expected drift). All numeric data + Chart.js code preserved verbatim.
- Library now contains 3 docs (IG playbook + EN strategy + PT strategy).

## v1.5.387 — 2026-06-11 (Internal Marketing Documents: add Education Foresight & Growth Strategy doc)
- Added `includes/templates/internal-docs/education-foresight-strategy-2026-06-10.html` — the EDIT. Education Foresight & Growth Strategy report built 2026-06-10. Appears in the index at `/internal-marketing-documents/` automatically.
- Doc covers: course-topic trends, funnel data, LLM citation baseline + dominance roadmap. Strategic snapshot for the team to align on the "why" behind editorial + Google Ads investment.
- Library now contains 2 docs (IG playbook + this strategy report).

## v1.5.386 — 2026-06-11 (Internal Marketing Documents: drop login gate, link-only access)
- Removed `is_user_logged_in()` gate from `EDIT_Internal_Marketing_Docs::maybe_render()`. Some team members (Carla, Naiara, contributors) don't have WP credentials and were getting bounced to wp-login.
- Documents now accessible via direct link without authentication.
- Discoverability remains low: `X-Robots-Tag: noindex, nofollow` + `<meta robots>` block search-engine indexing; no public page on the site links to `/internal-marketing-documents/`; only people you DM the URL to can find it.
- Dropped the `.userbar` block from the index page (no session to show).
- Updated index sub-copy to PT + explicit "noindex — só acessíveis via link directo" disclosure.

## v1.5.385 — 2026-06-11 (Internal Marketing Documents: login-gated static HTML library)
- New module `EDIT_Internal_Marketing_Docs` (`includes/class-internal-marketing-docs.php`) serves login-gated static HTML files under `/internal-marketing-documents/`.
- Routes: `/internal-marketing-documents/` shows an auto-generated index of all docs in `includes/templates/internal-docs/`; `/internal-marketing-documents/{slug}/` streams the matching `{slug}.html` file directly.
- Login gate: `is_user_logged_in()` check; logged-out visitors redirected to `wp-login.php` with `redirect_to` preserving the requested doc URL.
- `X-Robots-Tag: noindex, nofollow` + `<meta robots>` on the index — internal docs stay out of Google.
- One-shot rewrite flush via `edit_imd_rewrites_flushed_v1` option flag so routes resolve immediately after deploy (no manual permalink-save).
- Index auto-discovers `.html` files, reads `<title>` for label, sorts newest-first by mtime.
- First doc shipped: `instagram-playbook-2026-06-11.html` — PT translation of the IG growth-sprint playbook with today's Ed#2 distribution schedule at the top.
- Future docs: drop new `.html` into `includes/templates/internal-docs/`, release plugin, doc appears in the index automatically.

## v1.5.162 — 2026-05-31 (Success state: left-to-right box reveal + staggered text + delayed confetti)
- Success box now reveals with a left-to-right wipe (clip-path inset, 680ms easeInOutExpo).
- Strong title slides up + fades in at 480ms delay (620ms ease).
- Subscription message slides up + fades in at 660ms delay (staggered ~180ms from title).
- Confetti now fires at 700ms — AFTER the box reveal completes — so it lands as a payoff to the reveal sequence rather than competing with it.
- prefers-reduced-motion respected (animations disabled, content visible immediately).

## v1.5.161 — 2026-05-31 (Success copy: don't promise confirmation email)
- Success message no longer says "verifica o teu email para confirmar" — welcome automation isn't built yet (Task #16). New copy: `Subscrito. Obrigado — vemo-nos em breve.` Honest and friendly.

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
