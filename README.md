# weareedit.io Site Engine

Custom WordPress plugin powering [weareedit.io](https://weareedit.io) — SEO + GEO + brand + design + ops, all in one place.

## What it does

**Search-engine optimisation (SEO)**
- Schema.org JSON-LD (Organization, EducationalOrganization, Course, Person, AggregateRating, BreadcrumbList)
- Meta tags, Open Graph, Twitter Cards, canonical URLs, hreflang
- Sitemap generation
- Image lazy-loading + alt text fallbacks
- Analytics deduplication, script deferral

**Generative-engine optimisation (GEO / LLMs)**
- `/llms.txt` generation
- AI crawler `robots.txt` rules (GPTBot, ClaudeBot, PerplexityBot, etc.)
- Wikidata-linked Person + Organization schema (Daniel Devera Q139907903, EDIT. Q139907765)

**Brand & design**
- Homepage hero typography rewrite (H1 + pink/teal accent dots, H2 sub-text, "Other" tagline)
- CTA hover animations (3-layer sequenced sweep)
- DGERT badge integration (clickable, links to verified .gov.pt registry)
- Site-wide CSS overrides via output buffer

**Content & integrations**
- Google Reviews → visible badge + AggregateRating schema
- `/criticas-google/` virtual page rendered from review data
- HTML rewrites via output buffer (DGERT badge, course pages, course schema enrichment)
- WP Rocket cache integration (auto-flush on plugin update)

**One-time data fixes**
- Rank Math noindex cleanup (Videos page, FAQs page)
- robots.txt regeneration with AI-crawler rules

## Installation (manual, one-time)

1. WP Admin → `Plugins → Adicionar novo → Carregar plugin`
2. Upload the latest `weareedit-site-engine-X.Y.Z.zip` from [Releases](https://github.com/danieldevera/weareedit-site-engine/releases)
3. Activate

## Auto-updates (from v1.5.55 onwards)

This plugin polls its GitHub repository for new releases via [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker). After the initial manual install of v1.5.55+, every future release shows up as a one-click update in WP Admin.

After the update applies, WP Rocket page cache + minified assets are flushed automatically — no manual cache clear needed.

## Release ritual

1. Bump `Version:` in `weareedit-site-engine.php` and the `WEAREDIT_SITE_ENGINE_VERSION` constant
2. Append a section to `CHANGELOG.md`
3. Commit + tag + push:
   ```sh
   git add -A
   git commit -m "v1.5.XX — short summary"
   git tag v1.5.XX
   git push origin main --tags
   ```
4. The [`release.yml`](.github/workflows/release.yml) GitHub Action builds the zip and attaches it to the GitHub release automatically
5. WP Admin shows "Atualização disponível" within ~12h (or click "Verificar atualizações" on the plugin row)

## Repository layout

```
weareedit-site-engine/
├── weareedit-site-engine.php  # Bootstrap, PUC registration, cache-clear hook
├── includes/                  # All modules (one class per file)
│   ├── class-meta-tags.php
│   ├── class-output-buffer.php
│   ├── class-structured-data.php
│   └── ... (~25 classes)
├── assets/                    # DGERT badge images, admin CSS/JS, etc.
├── vendor/
│   └── plugin-update-checker/ # YahnisElsts/plugin-update-checker v5.5 (MIT)
├── .github/workflows/
│   └── release.yml            # Auto-build zip on `v*` tag push
├── README.md
├── CHANGELOG.md
└── .gitignore
```

## Backward compatibility

The plugin was previously called **EDIT. SEO Fix**. Renamed to **weareedit.io Site Engine** at v1.5.55 to better reflect its scope. WP options, AJAX action names, .htaccess markers, and JS asset handles still use the `edit_seo_fix_*` prefix to preserve existing live-site state. Internal PHP function names, constants, and the plugin folder use `weareedit_site_engine_*` / `WEAREDIT_SITE_ENGINE_*`.

## License

GPL-2.0+ (matches WordPress core). PUC library bundled under its MIT license — see `vendor/plugin-update-checker/license.txt`.
