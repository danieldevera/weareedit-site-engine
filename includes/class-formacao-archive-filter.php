<?php
/**
 * Formação archive — campaign URL banner.
 *
 * When visitors land on /formacao/?campanha=early15 (from the promo overlay
 * CTA, the newsletter sales section, or a paid ad), we show a tasteful
 * yellow banner at the top of the archive explaining the Early 15% offer.
 *
 * Earlier versions (v1.5.242–v1.5.250) tried to client-side filter the
 * grid down to September 2026 starts. That repeatedly broke because the
 * theme's card markup couldn't be targeted without false-positives
 * (closest() walks resolved to the grid wrapper and hid everything).
 *
 * Dedicated Task #27 landing page will replace this with a curated
 * /campanhas/early15/ route showing only the eligible courses.
 *
 * @package WeareEditSiteEngine
 * @since   1.5.242
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Formacao_Archive_Filter {

    const QUERY_PARAM   = 'campanha';
    const EARLY15_VALUE = 'early15';

    public static function init(): void {
        add_action( 'wp_footer', [ __CLASS__, 'maybe_print_banner' ], 60 );
    }

    public static function maybe_print_banner(): void {
        if ( is_admin() ) return;
        if ( empty( $_GET[ self::QUERY_PARAM ] ) ) return;
        $campaign = sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_PARAM ] ) );
        if ( $campaign !== self::EARLY15_VALUE ) return;
        $path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( (string) $_SERVER['REQUEST_URI'], '?' ) : '';
        if ( strpos( (string) $path, '/formacao' ) !== 0 ) return;
        ?>
<style id="edit-early15-banner-css">
#edit-early15-banner {
    position: relative; z-index: 5;
    background: #ffdd06; color: #0a0a0a; border-bottom: 4px solid #0a0a0a;
    font-family: 'SctoGroteskA', 'Helvetica Neue', Helvetica, Arial, sans-serif;
}
#edit-early15-banner .wrap {
    max-width: 1280px; margin: 0 auto; padding: 28px 40px;
    display: flex; flex-wrap: wrap; align-items: center; gap: 24px;
}
#edit-early15-banner .eyebrow {
    font-size: 12px; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase;
}
#edit-early15-banner .eyebrow .promo { color: #0090eb; }
#edit-early15-banner .headline {
    font-size: 30px; font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; margin: 0;
}
#edit-early15-banner .headline .pct { color: #f92869; }
#edit-early15-banner .sub {
    font-size: 15px; line-height: 1.45; margin: 0; max-width: 540px;
}
#edit-early15-banner .sub strong { font-weight: 700; }
#edit-early15-banner .meta {
    flex: 1 1 auto; display: flex; flex-direction: column; gap: 8px;
}
#edit-early15-banner .hint {
    font-size: 12px; letter-spacing: 0.06em; text-transform: uppercase; font-weight: 700;
    color: rgba(10,10,10,0.7);
}
@media (max-width: 720px) {
    #edit-early15-banner .wrap { padding: 22px 20px; gap: 14px; }
    #edit-early15-banner .headline { font-size: 24px; }
}
</style>
<script id="edit-early15-banner-js">
(function () {
    'use strict';
    if ( document.getElementById( 'edit-early15-banner' ) ) return;
    var banner = document.createElement( 'aside' );
    banner.id = 'edit-early15-banner';
    banner.setAttribute( 'role', 'region' );
    banner.setAttribute( 'aria-label', 'Promoção Early 15' );
    banner.innerHTML = ''
        + '<div class="wrap">'
        +   '<div class="meta">'
        +     '<p class="eyebrow"><span class="promo">PROMO</span> &middot; Setembro 2026</p>'
        +     '<h2 class="headline">Early <span class="pct">15%</span> &mdash; nas edi&ccedil;&otilde;es de Setembro.</h2>'
        +     '<p class="sub">Inscreve-te at&eacute; <strong>30 Junho</strong> e fica com 15% de desconto. Procura pelos cursos com o selo <strong>EARLY15</strong> em baixo.</p>'
        +   '</div>'
        +   '<p class="hint">A campanha aplica-se aos cursos com selo EARLY15</p>'
        + '</div>';
    // Insert the banner immediately after the theme's hero header. Try a few
    // common anchors; fall back to prepending to <main>.
    var anchor = document.querySelector( '#masthead' )
              || document.querySelector( 'header[role="banner"]' )
              || document.querySelector( 'header.site-header' );
    if ( anchor && anchor.parentNode ) {
        anchor.parentNode.insertBefore( banner, anchor.nextSibling );
    } else {
        var main = document.querySelector( 'main' ) || document.body;
        main.insertBefore( banner, main.firstChild );
    }
})();
</script>
        <?php
    }

}
