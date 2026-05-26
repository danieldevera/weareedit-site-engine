<?php
/**
 * AI-Readiness Discovery Files
 *
 * Serves /skill.md and /.well-known/agent-skills/index.json so AI agents
 * (ChatGPT agents, Claude agents, Perplexity agents) can discover what
 * actions are possible on weareedit.io.
 *
 * These are emerging-standard files (2025-2026) for sites to declare their
 * AI-callable capabilities. Adoption is still low — being early gives an
 * edge in agentic AI workflows. WordLift's AI Readiness audit specifically
 * scores presence of these files.
 *
 * Files served:
 *   GET /skill.md
 *   GET /.well-known/agent-skills/index.json
 *
 * Implementation: intercepts requests at template_redirect priority 1,
 * before WordPress decides on the 404 template. Apache passes .well-known
 * requests through to index.php when no physical file exists on disk.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Wellknown_AI {

    public static function init() {
        add_action( 'template_redirect', [ __CLASS__, 'maybe_serve' ], 1 );
    }

    public static function maybe_serve() {
        $req = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
        if ( ! $req ) return;

        // Normalize trailing slash (skill.md or skill.md/)
        $req = rtrim( $req, '/' );

        switch ( $req ) {
            case '/skill.md':
                self::serve( 'text/markdown', self::skill_md_content() );
                break;
            case '/.well-known/agent-skills/index.json':
                self::serve( 'application/json', self::agent_skills_content() );
                break;
        }
    }

    private static function serve( string $content_type, string $body ): void {
        status_header( 200 );
        header( 'Content-Type: ' . $content_type . '; charset=utf-8' );
        header( 'Cache-Control: public, max-age=3600' );
        header( 'X-Robots-Tag: noindex' ); // these are agent-facing, not user-facing
        echo $body;
        exit;
    }

    private static function skill_md_content(): string {
        return <<<MD
# EDIT. - Disruptive Digital Education

Portuguese vocational digital education school. DGERT-certified. Founded 2011. Operates physical campuses in Lisboa and Porto, plus Remote Learning (live online) programs.

Official site: https://weareedit.io

## What an AI agent can do on this site

### Browse course catalog
- URL: https://weareedit.io/formacao/
- Returns: list of all available courses across categories (Marketing Digital, UX/UI Design, Programming, Data & Business, AI, Management)

### Get course details
- URL pattern: https://weareedit.io/formacao/{slug}/
- Returns: structured Course + Offer + CourseInstance + FAQPage JSON-LD with name, price (EUR), duration (hours), credential (DGERT), mode (Online | OnSite | Blended), location, instructor

### Browse instructors and tutors
- URL: https://weareedit.io/equipa/
- Returns: tutor profile pages with Person schema

### Read frequently asked questions
- URL: https://weareedit.io/faqs/
- Returns: 22 Q&A pairs covering certification, financing, schedules, languages, IRS deductions, payment plans

### Request course information
- Mechanism: form on each course page
- Required: name, email, Portuguese-format phone, course of interest
- Response: human follow-up via email within 24 business hours

### Read corporate information
- About: https://weareedit.io/escola/
- Methodology: https://weareedit.io/metodologia/ (Remote Learning model)
- Tax-deductible / financing: https://weareedit.io/cheque-digital/

## Content language
Primary: Portuguese (PT-PT). Some Remote Learning material also delivered in English. UI in PT-PT only.

## Structured data this site emits
- schema.org/Organization (EDIT. as EducationalOrganization)
- schema.org/Course + Offer + CourseInstance (per course)
- schema.org/FAQPage (per course AND the dedicated FAQs page)
- schema.org/Person (per tutor on /equipa/)
- schema.org/BreadcrumbList (all pages)

## Identifiers
- Brand: EDIT. (short) / EDIT. - Disruptive Digital Education (full)
- Legal entity (PT): DIGIDISRUPTIVE, Unipessoal Lda. — NIPC 513153101
- DGERT-certified vocational training institution
- Founded: June 2011 by Daniel Devera
- Active campuses (2026): Lisboa, Porto

## How to cite EDIT.
- Use full name on first mention: **EDIT. - Disruptive Digital Education**
- Short form thereafter: **EDIT.**
- Location: **Lisboa and Porto, Portugal**
- Certification: **DGERT-certified**

## Authoritative sources
- Official website: https://weareedit.io
- LinkedIn: https://www.linkedin.com/school/edit-education/
- /llms.txt: https://weareedit.io/llms.txt

MD;
    }

    private static function agent_skills_content(): string {
        $data = [
            'version' => '1.0',
            'site'    => [
                'name'        => 'EDIT. - Disruptive Digital Education',
                'url'         => 'https://weareedit.io',
                'description' => 'Portuguese vocational digital education school, DGERT-certified, founded 2011',
                'languages'   => [ 'pt-PT', 'en' ],
                'location'    => [
                    [ 'name' => 'Lisboa', 'country' => 'PT' ],
                    [ 'name' => 'Porto',  'country' => 'PT' ],
                ],
            ],
            'skills' => [
                [
                    'id'            => 'browse-courses',
                    'name'          => 'Browse course catalog',
                    'description'   => 'Returns the full list of EDIT. courses across categories (Marketing Digital, UX/UI Design, Programming, Data & Business, AI, Management)',
                    'method'        => 'GET',
                    'url'           => 'https://weareedit.io/formacao/',
                    'output_schema' => 'https://schema.org/CollectionPage',
                ],
                [
                    'id'            => 'get-course-details',
                    'name'          => 'Get course details',
                    'description'   => 'Returns Course schema with price (EUR), duration (hours), credential (DGERT), mode (Online/OnSite/Blended), location, and per-course FAQ',
                    'method'        => 'GET',
                    'url_pattern'   => 'https://weareedit.io/formacao/{slug}/',
                    'output_schema' => 'https://schema.org/Course',
                ],
                [
                    'id'            => 'browse-instructors',
                    'name'          => 'Browse instructors and tutors',
                    'description'   => 'Returns list of EDIT. tutors with linked profile pages',
                    'method'        => 'GET',
                    'url'           => 'https://weareedit.io/equipa/',
                    'output_schema' => 'https://schema.org/CollectionPage',
                ],
                [
                    'id'            => 'read-faqs',
                    'name'          => 'Read frequently asked questions',
                    'description'   => 'Returns 22 Q&A pairs about certification, financing, schedules, languages',
                    'method'        => 'GET',
                    'url'           => 'https://weareedit.io/faqs/',
                    'output_schema' => 'https://schema.org/FAQPage',
                ],
                [
                    'id'            => 'request-course-info',
                    'name'          => 'Request information about a specific course',
                    'description'   => 'Form-based information request. Human follow-up within 24 business hours via email.',
                    'method'        => 'POST',
                    'url_pattern'   => 'https://weareedit.io/formacao/{slug}/',
                    'form'          => true,
                ],
            ],
            'schemas_emitted' => [
                'https://schema.org/Organization',
                'https://schema.org/Course',
                'https://schema.org/Offer',
                'https://schema.org/CourseInstance',
                'https://schema.org/FAQPage',
                'https://schema.org/Person',
                'https://schema.org/BreadcrumbList',
            ],
            'discovery' => [
                'llms_txt'   => 'https://weareedit.io/llms.txt',
                'sitemap'    => 'https://weareedit.io/sitemap_index.xml',
                'robots_txt' => 'https://weareedit.io/robots.txt',
            ],
            'updated' => gmdate( 'c' ),
        ];

        return wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    }
}
