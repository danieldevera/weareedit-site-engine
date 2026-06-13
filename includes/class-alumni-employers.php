<?php
/**
 * Alumni Employers — modular logo wall component.
 *
 * Single source of truth for the LinkedIn-verified list of companies that
 * have hired EDIT. alumni. Used as recruitment-validation social proof on
 * the pillar pages, homepage, course/product pages, Empresas page, and
 * future LinkedIn ad landing pages.
 *
 * Logo strategy (Hybrid C): primary fetch via Clearbit's free Logo API
 * (https://logo.clearbit.com/{domain}). For employers Clearbit doesn't
 * cover well, the markup gracefully falls back to a text-only chip via
 * onerror handling.
 *
 * Variants:
 *   wall   — full grid, larger logos, used on pillar pages
 *   strip  — single horizontal row, smaller logos, used on course pages
 *   grid   — compact 3-4 col grid, medium logos, used on sidebars
 *
 * Usage:
 *   PHP        — echo EDIT_Alumni_Employers::render( 'wall' );
 *   Shortcode  — [edit_alumni_employers variant="wall" limit="0"]
 *
 * Added v1.5.473.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Alumni_Employers {

    /**
     * Ordered list of alumni employers. Curated 2026-06-13 — 30 companies
     * grouped by tier, most-recognized first. Order matters for `strip`
     * + `limit` variants. Tiers:
     *   global-tech   — global unicorns/scale-ups with strong PT presence
     *   global        — multinationals with PT tech hubs
     *   pt-corp       — Portuguese corporate brands
     *   consultancy   — global consulting firms
     *   pt-studio     — PT digital/creative leaders
     */
    const EMPLOYERS = [
        // Tier A — Global unicorns + scale-ups with PT presence (12)
        [ 'name' => 'Farfetch',           'domain' => 'farfetch.com',         'tier' => 'global-tech' ],
        [ 'name' => 'OutSystems',         'domain' => 'outsystems.com',       'tier' => 'global-tech' ],
        [ 'name' => 'Talkdesk',           'domain' => 'talkdesk.com',         'tier' => 'global-tech' ],
        [ 'name' => 'Feedzai',            'domain' => 'feedzai.com',          'tier' => 'global-tech' ],
        [ 'name' => 'Unbabel',            'domain' => 'unbabel.com',          'tier' => 'global-tech' ],
        [ 'name' => 'Sword Health',       'domain' => 'swordhealth.com',      'tier' => 'global-tech' ],
        [ 'name' => 'Cleverly',           'domain' => 'cleverly.com',         'tier' => 'global-tech' ],
        [ 'name' => 'Bolt',               'domain' => 'bolt.eu',              'tier' => 'global-tech' ],
        [ 'name' => 'Defined.ai',         'domain' => 'defined.ai',           'tier' => 'global-tech' ],
        [ 'name' => 'Coverflex',          'domain' => 'coverflex.com',        'tier' => 'global-tech' ],
        [ 'name' => 'Mercedes-Benz.io',   'domain' => 'mercedes-benz.io',     'tier' => 'global-tech' ],
        [ 'name' => 'Critical TechWorks', 'domain' => 'criticaltechworks.com','tier' => 'global-tech' ],
        // Tier B — MNCs with PT tech hubs (5)
        [ 'name' => 'Bosch',              'domain' => 'bosch.com',            'tier' => 'global' ],
        [ 'name' => 'Siemens',            'domain' => 'siemens.com',          'tier' => 'global' ],
        [ 'name' => 'Microsoft',          'domain' => 'microsoft.com',        'tier' => 'global' ],
        [ 'name' => 'Google',             'domain' => 'google.com',           'tier' => 'global' ],
        [ 'name' => 'Natixis',            'domain' => 'natixis.com',          'tier' => 'global' ],
        // Tier C — Portuguese corporate brands (8)
        [ 'name' => 'NOS',                'domain' => 'nos.pt',               'tier' => 'pt-corp' ],
        [ 'name' => 'MEO',                'domain' => 'meo.pt',               'tier' => 'pt-corp' ],
        [ 'name' => 'EDP',                'domain' => 'edp.com',              'tier' => 'pt-corp' ],
        [ 'name' => 'Galp',               'domain' => 'galp.com',             'tier' => 'pt-corp' ],
        [ 'name' => 'Worten',             'domain' => 'worten.pt',            'tier' => 'pt-corp' ],
        [ 'name' => 'Sonae MC',           'domain' => 'sonaemc.com',          'tier' => 'pt-corp' ],
        [ 'name' => 'CTT',                'domain' => 'ctt.pt',               'tier' => 'pt-corp' ],
        [ 'name' => 'Millennium BCP',     'domain' => 'millenniumbcp.pt',     'tier' => 'pt-corp' ],
        // Tier D — Global consultancies (3)
        [ 'name' => 'Accenture',          'domain' => 'accenture.com',        'tier' => 'consultancy' ],
        [ 'name' => 'Deloitte Digital',   'domain' => 'deloittedigital.com',  'tier' => 'consultancy' ],
        [ 'name' => 'McKinsey & Company', 'domain' => 'mckinsey.com',         'tier' => 'consultancy' ],
        // Tier E — PT digital/creative leaders (2)
        [ 'name' => 'Bliss Applications', 'domain' => 'blissapplications.com','tier' => 'pt-studio' ],
        [ 'name' => 'Bürocratik',         'domain' => 'burocratik.com',       'tier' => 'pt-studio' ],
    ];

    private static $assets_emitted = false;

    public static function init(): void {
        add_shortcode( 'edit_alumni_employers', [ __CLASS__, 'shortcode_render' ] );
    }

    public static function shortcode_render( $atts ): string {
        $atts = shortcode_atts( [
            'variant' => 'wall',
            'limit'   => 0,
            'heading' => 'Alumni colocados em',
            'badge'   => 'verificado via LinkedIn',
        ], $atts );

        return self::render(
            (string) $atts['variant'],
            (int)    $atts['limit'],
            (string) $atts['heading'],
            (string) $atts['badge']
        );
    }

    /**
     * Render the logo wall. `$variant` controls visual treatment (wall|
     * strip|grid). `$limit` truncates to the top-N most-recognized
     * employers (0 = show all). `$heading` and `$badge` are the eyebrow +
     * trust microcopy.
     */
    public static function render(
        string $variant = 'wall',
        int    $limit   = 0,
        string $heading = 'Alumni colocados em',
        string $badge   = 'verificado via LinkedIn'
    ): string {
        $variant = in_array( $variant, [ 'wall', 'strip', 'grid' ], true ) ? $variant : 'wall';

        $list = self::EMPLOYERS;
        if ( $limit > 0 ) {
            $list = array_slice( $list, 0, $limit );
        }

        ob_start();
        self::maybe_emit_assets();
        ?>
        <?php $alumni_root_url = 'https://www.linkedin.com/school/edit-education/people/'; ?>
        <section class="ae-section ae-section--<?php echo esc_attr( $variant ); ?>" aria-label="<?php echo esc_attr( $heading ); ?>">
            <div class="ae-heading">
                <p class="ae-eyebrow">
                    <a class="ae-eyebrow-link"
                       href="<?php echo esc_url( $alumni_root_url ); ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       title="Ver todos os alumni EDIT. no LinkedIn (abre em nova janela)">
                        <?php echo esc_html( $heading ); ?>
                    </a>
                </p>
                <?php if ( $badge ) : ?>
                    <p class="ae-badge">
                        <svg class="ae-badge-icon" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M20.5 2h-17A1.5 1.5 0 0 0 2 3.5v17A1.5 1.5 0 0 0 3.5 22h17a1.5 1.5 0 0 0 1.5-1.5v-17A1.5 1.5 0 0 0 20.5 2zM8 19H5v-9h3zM6.5 8.25A1.75 1.75 0 1 1 8.25 6.5 1.75 1.75 0 0 1 6.5 8.25zM19 19h-3v-4.74c0-1.42-.6-1.93-1.38-1.93A1.74 1.74 0 0 0 13 14.19a.66.66 0 0 0 0 .14V19h-3v-9h2.9v1.3a3.11 3.11 0 0 1 2.7-1.4c1.55 0 3.36.86 3.36 3.66z"/></svg>
                        <?php echo esc_html( $badge ); ?>
                    </p>
                <?php endif; ?>
            </div>
            <ul class="ae-list">
                <?php foreach ( $list as $emp ) :
                    $logo_url     = 'https://logo.clearbit.com/' . $emp['domain'];
                    $name         = $emp['name'];
                    $linkedin_url = self::linkedin_alumni_url( $name );
                    $aria_label   = sprintf( 'Ver alumni EDIT. com experiência em %s no LinkedIn (abre em nova janela)', $name );
                ?>
                    <li class="ae-item">
                        <a
                            class="ae-item-link"
                            href="<?php echo esc_url( $linkedin_url ); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            title="Alumni EDIT. com experiência em <?php echo esc_attr( $name ); ?> · LinkedIn"
                            aria-label="<?php echo esc_attr( $aria_label ); ?>"
                        >
                            <img
                                class="ae-logo"
                                src="<?php echo esc_url( $logo_url ); ?>"
                                alt="<?php echo esc_attr( 'Logo ' . $name ); ?>"
                                loading="lazy"
                                decoding="async"
                                onerror="this.parentNode.classList.add('ae-item-link--fallback');this.style.display='none';"
                            >
                            <span class="ae-fallback-name"><?php echo esc_html( $name ); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Build a LinkedIn URL that opens the EDIT. school alumni page
     * filtered by a keyword match against the employer name. Logged-in
     * users see the People tab filtered to alumni who currently or
     * previously worked at the named company.
     *
     * Pattern: https://www.linkedin.com/school/edit-education/people/?keywords={company}
     */
    public static function linkedin_alumni_url( string $company_name ): string {
        $base = 'https://www.linkedin.com/school/edit-education/people/';
        return $base . '?keywords=' . rawurlencode( $company_name );
    }

    /**
     * Emit the scoped CSS once per page load. Inline so it works whether
     * the component is rendered via PHP call, shortcode, or future block.
     */
    private static function maybe_emit_assets(): void {
        if ( self::$assets_emitted ) return;
        self::$assets_emitted = true;
        ?>
        <style id="edit-ae-css">
        .ae-section{margin:0;padding:0;color:#0a0a0a;font-family:inherit;}
        .ae-heading{display:flex;flex-direction:column;gap:6px;align-items:center;margin:0 0 28px;text-align:center;}
        .ae-eyebrow{font-size:13px;font-weight:500;letter-spacing:0.30em;text-transform:uppercase;color:#0a0a0a;margin:0;}
        .ae-eyebrow-link{color:inherit;text-decoration:none;transition:color 180ms;}
        .ae-eyebrow-link:hover,.ae-eyebrow-link:focus-visible{color:#0a66c2;text-decoration:underline;text-decoration-thickness:1.5px;text-underline-offset:4px;}
        .ae-eyebrow-link:focus-visible{outline:2px solid #0a66c2;outline-offset:3px;border-radius:2px;}
        .ae-badge{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:500;letter-spacing:0.08em;color:#666;margin:0;font-style:italic;}
        .ae-badge-icon{width:12px;height:12px;color:#0a66c2;}
        .ae-list{list-style:none;padding:0;margin:0;}
        .ae-item{position:relative;}
        .ae-item-link{display:flex;align-items:center;justify-content:center;width:100%;height:100%;background:#fff;border:1px solid #e8e6df;border-radius:6px;text-decoration:none;color:inherit;transition:border-color 180ms,transform 180ms,box-shadow 180ms;}
        .ae-item-link:hover,
        .ae-item-link:focus-visible{border-color:#0a66c2;transform:translateY(-2px);box-shadow:0 4px 14px rgba(10,102,194,0.10);}
        .ae-item-link:focus-visible{outline:2px solid #0a66c2;outline-offset:2px;}
        .ae-logo{max-width:80%;max-height:60%;width:auto;height:auto;object-fit:contain;filter:grayscale(1) opacity(0.65);transition:filter 200ms;}
        .ae-item-link:hover .ae-logo,
        .ae-item-link:focus-visible .ae-logo{filter:grayscale(0) opacity(1);}
        .ae-fallback-name{display:none;font-size:13px;font-weight:600;color:#0a0a0a;letter-spacing:-0.01em;text-align:center;padding:0 8px;}
        .ae-item-link--fallback .ae-fallback-name{display:block;}
        .ae-item-link--fallback:hover .ae-fallback-name,
        .ae-item-link--fallback:focus-visible .ae-fallback-name{color:#0a66c2;}

        /* WALL — full grid, large logos. Used on pillar pages, post-hero
           on Empresas, anywhere with horizontal room. */
        .ae-section--wall .ae-list{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px;}
        .ae-section--wall .ae-item{aspect-ratio:5/3;}
        @media (max-width:1100px){.ae-section--wall .ae-list{grid-template-columns:repeat(4,1fr);}}
        @media (max-width:680px) {.ae-section--wall .ae-list{grid-template-columns:repeat(3,1fr);gap:10px;}}
        @media (max-width:420px) {.ae-section--wall .ae-list{grid-template-columns:repeat(2,1fr);}}

        /* STRIP — single horizontal row. Used in course pages sidebar /
           Empresas inline / blog post conversion blocks. */
        .ae-section--strip .ae-list{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;}
        .ae-section--strip .ae-item{flex:0 0 auto;width:104px;height:60px;padding:8px;}

        /* GRID — compact 3-4 cols, medium. Used for tighter sidebars or
           small homepage cards. */
        .ae-section--grid .ae-list{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;}
        .ae-section--grid .ae-item{aspect-ratio:5/3;}
        @media (max-width:640px){.ae-section--grid .ae-list{grid-template-columns:repeat(3,1fr);}}
        </style>
        <?php
    }
}
EDIT_Alumni_Employers::init();
