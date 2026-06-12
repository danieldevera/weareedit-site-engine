<?php
/**
 * Past-date display interceptor for formacao pages.
 *
 * Replaces past dates on course displays with editorial placeholders:
 *   • Start dates (preceded by "Início" / inside .course-date) → "Brevemente"
 *   • End dates (preceded by "Fim")                            → "A definir"
 *
 * Scope: output buffer on /formacao/ archive + /formacao/<slug>/ singles +
 * pillar pages. Does NOT touch <script> blocks → JSON-LD schema keeps
 * valid ISO 8601 dates (Google doesn't downrank past startDate; replacing
 * inside schema would break structured data validation).
 *
 * Why output buffer (not ACF filter): the theme reads ACF and bakes dates
 * into multiple template parts (card label, sidebar widget, course header).
 * A single output-buffer pass catches every render surface in one place
 * without depending on knowing the ACF field names.
 *
 * Non-destructive: data on disk is untouched. When a past date later gets
 * re-scheduled to a future cohort, the ACF field is updated normally and
 * this interceptor stops swapping it (date is no longer past).
 *
 * Shipped v1.5.422.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Past_Date_Interceptor {

    const START_REPLACEMENT = 'Brevemente';
    const END_REPLACEMENT   = 'A definir';

    /** PT month names → numeric (lowercase, no accents stripped beyond unicode-aware match). */
    const PT_MONTHS = [
        'janeiro'   => 1,
        'fevereiro' => 2,
        'março'     => 3,
        'marco'     => 3,
        'abril'     => 4,
        'maio'      => 5,
        'junho'     => 6,
        'julho'     => 7,
        'agosto'    => 8,
        'setembro'  => 9,
        'outubro'   => 10,
        'novembro'  => 11,
        'dezembro'  => 12,
    ];

    public static function init() {
        // Hook late so theme has already rendered its template parts.
        add_action( 'template_redirect', [ __CLASS__, 'maybe_start_buffer' ], 999 );
    }

    /**
     * Only buffer formacao surfaces — avoids touching unrelated pages.
     * Pillar pages aren't in `formacao` post type but they list course cards,
     * so we include them via their known slugs.
     */
    public static function maybe_start_buffer() {
        if ( is_admin() || wp_doing_ajax() ) return;
        if ( ! self::is_target_surface() ) return;
        ob_start( [ __CLASS__, 'rewrite' ] );
    }

    private static function is_target_surface(): bool {
        // ONLY /formacao/<slug>/ single course pages. Pillar pages were
        // included until v1.5.428 but caused layout regressions on certain
        // theme/cache combinations — pillar dates are short-form and rare,
        // not worth the blast radius.
        return is_singular( 'formacao' );
    }

    /**
     * Main rewrite pass. Operates in three phases to handle context-aware
     * replacement (Início vs Fim vs unlabelled card date).
     */
    public static function rewrite( string $html ): string {
        if ( ! $html ) return $html;

        // Carve out <script>…</script> so JSON-LD schema dates stay untouched.
        $scripts = [];
        $html = preg_replace_callback(
            '#<script\b[^>]*>.*?</script>#is',
            function ( $m ) use ( &$scripts ) {
                $token = '<<<EDIT_SCRIPT_' . count( $scripts ) . '>>>';
                $scripts[] = $m[0];
                return $token;
            },
            $html
        );

        // Element-level approach: target each `<label>X</label> ... <div class=value>CONTENT</div>`
        // pair, strip CONTENT to plain text, parse the date, and replace the
        // ENTIRE CONTENT atomically. Survives:
        //   - Inner <span class="desktoponly">2026</span>/<span class="mobileonly">'26</span>
        //     wrappers that split month and year across text nodes.
        //   - Single or double quotes on the value div class attribute.
        //   - Trailing whitespace inside the value div.

        // PHASE 1 — Início label → past date inside .value div → "Brevemente"
        $html = preg_replace_callback(
            '#(<label[^>]*>\s*Início\s*</label>.{0,400}?<div\s+class=["\']value["\'][^>]*>)(.*?)(</div>)#siu',
            function ( $m ) {
                if ( self::value_div_holds_past_date( $m[2] ) ) {
                    return $m[1] . self::START_REPLACEMENT . $m[3];
                }
                return $m[0];
            },
            $html
        );

        // PHASE 2 — Fim label → past date inside .value div → "A definir"
        $html = preg_replace_callback(
            '#(<label[^>]*>\s*Fim\s*</label>.{0,400}?<div\s+class=["\']value["\'][^>]*>)(.*?)(</div>)#siu',
            function ( $m ) {
                if ( self::value_div_holds_past_date( $m[2] ) ) {
                    return $m[1] . self::END_REPLACEMENT . $m[3];
                }
                return $m[0];
            },
            $html
        );

        // PHASE 3 — `.course-date` archive cards. Inline text node, simpler.
        $CARD_DATE = '(\d{1,2})(?:\s+de)?\s+([A-Za-zçÇãéíóÁÉÍÓ]+)(?:\s*,)?(?:\s+de)?\s+(\d{4})';
        $html = preg_replace_callback(
            '/(class="[^"]*course-date[^"]*"[^>]*>)([^<]*?)' . $CARD_DATE . '([^<]*)/iu',
            function ( $m ) {
                if ( self::is_pt_date_past( $m[3], $m[4], $m[5] ) ) {
                    return $m[1] . $m[2] . self::START_REPLACEMENT . $m[6];
                }
                return $m[0];
            },
            $html
        );

        // Restore scripts.
        if ( $scripts ) {
            $html = preg_replace_callback(
                '/<<<EDIT_SCRIPT_(\d+)>>>/',
                function ( $m ) use ( $scripts ) {
                    return $scripts[ (int) $m[1] ] ?? '';
                },
                $html
            );
        }

        return $html;
    }

    /**
     * Plain-text-strip the .value div content, parse for a PT date,
     * return true if it's strictly before today.
     *
     * Handles markup like:
     *   14 Fevereiro, <span class="desktoponly">2026</span><span class="mobileonly">'26</span>
     * by reducing to: "14 Fevereiro, 2026 '26" → first matching PT date wins.
     */
    private static function value_div_holds_past_date( string $inner ): bool {
        // Strip tags + decode entities, collapse whitespace.
        $text = preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( $inner ) ) );
        if ( $text === '' ) return false;
        if ( ! preg_match(
            '/(\d{1,2})(?:\s+de)?\s+([A-Za-zçÇãéíóÁÉÍÓ]+)(?:\s*,)?(?:\s+de)?\s+(\d{4})/u',
            $text,
            $m
        ) ) {
            return false;
        }
        return self::is_pt_date_past( $m[1], $m[2], $m[3] );
    }

    /**
     * True if (day, PT month name, year) is strictly before today.
     */
    private static function is_pt_date_past( $day, $month_name, $year ): bool {
        $key = mb_strtolower( trim( $month_name ), 'UTF-8' );
        if ( ! isset( self::PT_MONTHS[ $key ] ) ) return false; // not a real PT month → bail
        $month = self::PT_MONTHS[ $key ];
        $day   = (int) $day;
        $year  = (int) $year;
        if ( $year < 2000 || $year > 2100 ) return false; // sanity guard
        if ( $day < 1 || $day > 31 )         return false;
        $ts    = mktime( 0, 0, 0, $month, $day, $year );
        $today = strtotime( 'today' );
        return $ts && $ts < $today;
    }
}
EDIT_Past_Date_Interceptor::init();
