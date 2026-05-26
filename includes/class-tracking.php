<?php
/**
 * Tracking Pixels — Facebook, Hotjar, LinkedIn, TikTok, Google Ads
 *
 * Each service is independently toggled and configured via the admin panel.
 * Scripts are output via wp_head so the existing script optimizer can defer
 * them and Moove GDPR can manage consent at the browser level.
 *
 * Settings keys (in edit_seo_fix_settings):
 *   fb_pixel_id          — Facebook Pixel ID (numeric string)
 *   hotjar_id            — Hotjar Site ID (numeric string)
 *   linkedin_partner_id  — LinkedIn Insight Tag Partner ID (numeric string)
 *   tiktok_pixel_id      — TikTok Pixel ID (alphanumeric string)
 *   gads_conversion_id   — Google Ads conversion ID (AW-XXXXXXXXX)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Tracking {

    public static function init() {
        add_action( 'wp_head',   [ __CLASS__, 'output_facebook_pixel' ], 10 );
        add_action( 'wp_head',   [ __CLASS__, 'output_hotjar' ],         10 );
        add_action( 'wp_head',   [ __CLASS__, 'output_linkedin' ],       10 );
        add_action( 'wp_head',   [ __CLASS__, 'output_tiktok' ],         10 );
        add_action( 'wp_head',   [ __CLASS__, 'output_gads' ],           10 );
        add_action( 'wp_footer', [ __CLASS__, 'output_cf7_redirect' ],   20 );
        add_action( 'wp_footer', [ __CLASS__, 'output_inlinks' ],        20 );

        // Add resource hints for new tracking origins
        add_action( 'wp_head', [ __CLASS__, 'output_resource_hints' ], 1 );
    }

    // -------------------------------------------------------------------------
    // Facebook Pixel
    // -------------------------------------------------------------------------

    public static function output_facebook_pixel() {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        $pixel_id = trim( $settings['fb_pixel_id'] ?? '' );
        if ( ! $pixel_id ) return;

        $pixel_id_escaped = esc_js( $pixel_id );

        // Fire Lead event on the thank-you page (form submission confirmation).
        // This is what Meta Ads tracks as a conversion for enrollment campaigns.
        $is_thank_you = ( strpos( $_SERVER['REQUEST_URI'] ?? '', '/thank-you' ) !== false );
        ?>
<!-- EDIT. SEO Fix: Facebook Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init','<?php echo $pixel_id_escaped; ?>');
fbq('track','PageView');
<?php if ( $is_thank_you ) : ?>
fbq('track','Lead');
<?php endif; ?>
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo esc_attr( $pixel_id ); ?>&ev=PageView&noscript=1" alt=""></noscript>
<!-- /EDIT. SEO Fix: Facebook Pixel -->
        <?php
    }

    // -------------------------------------------------------------------------
    // Hotjar
    // -------------------------------------------------------------------------

    public static function output_hotjar() {
        $settings   = get_option( 'edit_seo_fix_settings', [] );
        $hotjar_id  = trim( $settings['hotjar_id'] ?? '' );
        if ( ! $hotjar_id ) return;

        $hotjar_id_escaped = esc_js( $hotjar_id );
        ?>
<!-- EDIT. SEO Fix: Hotjar -->
<script>
(function(h,o,t,j,a,r){
    h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
    h._hjSettings={hjid:<?php echo intval( $hotjar_id ); ?>,hjsv:6};
    a=o.getElementsByTagName('head')[0];
    r=o.createElement('script');r.async=1;
    r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
    a.appendChild(r);
})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
</script>
<!-- /EDIT. SEO Fix: Hotjar -->
        <?php
    }

    // -------------------------------------------------------------------------
    // LinkedIn Insight Tag
    // -------------------------------------------------------------------------

    public static function output_linkedin() {
        $settings    = get_option( 'edit_seo_fix_settings', [] );
        $partner_id  = trim( $settings['linkedin_partner_id'] ?? '' );
        if ( ! $partner_id ) return;
        ?>
<!-- EDIT. SEO Fix: LinkedIn Insight Tag -->
<script type="text/javascript">
_linkedin_partner_id = "<?php echo esc_js( $partner_id ); ?>";
window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
window._linkedin_data_partner_ids.push(_linkedin_partner_id);
</script>
<script type="text/javascript">
(function(l) {
if (!l){window.lintrk = function(a,b){window.lintrk.q.push([a,b])};
window.lintrk.q=[]}
var s = document.getElementsByTagName("script")[0];
var b = document.createElement("script");
b.type = "text/javascript";b.async = true;
b.src = "https://snap.licdn.com/li.lms-analytics/insight.min.js";
s.parentNode.insertBefore(b, s);})(window.lintrk);
</script>
<noscript><img height="1" width="1" style="display:none;" alt="" src="https://px.ads.linkedin.com/collect/?pid=<?php echo esc_attr( $partner_id ); ?>&fmt=gif"></noscript>
<!-- /EDIT. SEO Fix: LinkedIn Insight Tag -->
        <?php
    }

    // -------------------------------------------------------------------------
    // TikTok Pixel
    // -------------------------------------------------------------------------

    public static function output_tiktok() {
        $settings        = get_option( 'edit_seo_fix_settings', [] );
        $tiktok_pixel_id = trim( $settings['tiktok_pixel_id'] ?? '' );
        if ( ! $tiktok_pixel_id ) return;
        ?>
<!-- EDIT. SEO Fix: TikTok Pixel -->
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track",
  "identify","instances","debug","on","off","once","ready","alias","group","enableCookie",
  "disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};
  for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);
  ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},
  ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";
  ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,
  ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script");
  n.type="text/javascript",n.async=!0,n.src=i+"?sdkid="+e+"&lib="+t;
  e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};
  ttq.load('<?php echo esc_js( $tiktok_pixel_id ); ?>');
  ttq.page();
}(window, document, 'ttq');
</script>
<!-- /EDIT. SEO Fix: TikTok Pixel -->
        <?php
    }

    // -------------------------------------------------------------------------
    // Google Ads Conversion Linker
    // -------------------------------------------------------------------------

    public static function output_gads() {
        $settings       = get_option( 'edit_seo_fix_settings', [] );
        $gads_id        = trim( $settings['gads_conversion_id'] ?? '' );
        if ( ! $gads_id ) return;
        // Only add the conversion linker if GA4 is already loaded via GTM
        // The conversion ID is referenced in GTM conversion tags; this just
        // ensures the global gtag function exists as a fallback.
        ?>
<!-- EDIT. SEO Fix: Google Ads Conversion Linker -->
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('config','<?php echo esc_js( $gads_id ); ?>');
</script>
<!-- /EDIT. SEO Fix: Google Ads Conversion Linker -->
        <?php
    }

    // -------------------------------------------------------------------------
    // Resource hints for new tracking origins
    // -------------------------------------------------------------------------

    public static function output_resource_hints() {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        $hints    = [];

        if ( ! empty( $settings['hotjar_id'] ) ) {
            $hints[] = 'https://static.hotjar.com';
            $hints[] = 'https://script.hotjar.com';
        }
        if ( ! empty( $settings['linkedin_partner_id'] ) ) {
            $hints[] = 'https://snap.licdn.com';
            $hints[] = 'https://px.ads.linkedin.com';
        }
        if ( ! empty( $settings['tiktok_pixel_id'] ) ) {
            $hints[] = 'https://analytics.tiktok.com';
        }

        if ( empty( $hints ) ) return;

        echo "\n<!-- EDIT. SEO Fix: Tracking Resource Hints -->\n";
        foreach ( $hints as $origin ) {
            echo '<link rel="dns-prefetch" href="' . esc_url( $origin ) . '">' . "\n";
        }
        echo "<!-- /EDIT. SEO Fix: Tracking Resource Hints -->\n";
    }

    // -------------------------------------------------------------------------
    // Contact Form 7 — redirect to /thank-you/ on successful submission
    // -------------------------------------------------------------------------

    /**
     * After CF7 sends mail successfully, redirect to the thank-you page.
     * CF7 v5.1+ removed the on_sent_ok setting — the modern approach is
     * listening for the wpcf7mailsent DOM event via JavaScript.
     */
    public static function output_cf7_redirect() {
        // Only output when CF7 is active on the site
        if ( ! function_exists( 'wpcf7' ) && ! class_exists( 'WPCF7' ) ) return;

        $thank_you = esc_js( home_url( '/thank-you/' ) );
        ?>
<!-- EDIT. SEO Fix: CF7 Thank-You Redirect -->
<script>
document.addEventListener( 'wpcf7mailsent', function() {
    window.location = '<?php echo $thank_you; ?>';
}, false );
</script>
<!-- /EDIT. SEO Fix: CF7 Thank-You Redirect -->
        <?php
    }

    // -------------------------------------------------------------------------
    // InLinks — entity SEO / internal linking / schema markup automation
    // -------------------------------------------------------------------------

    /**
     * Inject InLinks JS in the footer. Site ID configured via
     * edit_seo_fix_settings['inlinks_site_id'] (default: EDIT.'s site 50168).
     * Loaded with `defer` so it doesn't block render. Skip in admin/AJAX/feeds.
     */
    public static function output_inlinks() {
        if ( is_admin() ) return;
        if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) return;
        if ( function_exists( 'is_feed' ) && is_feed() ) return;
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;

        $settings = get_option( 'edit_seo_fix_settings', [] );
        $site_id  = trim( (string) ( $settings['inlinks_site_id'] ?? '50168' ) );

        // Allow disabling explicitly by setting to empty/false
        if ( ! $site_id || $site_id === '0' ) return;

        // Numeric guard — InLinks site IDs are numeric
        if ( ! ctype_digit( $site_id ) ) return;

        // Tell WP Rocket / Cloudflare Rocket Loader to leave this script alone.
        // InLinks crawler validates by detecting jscloud.net URL on the page — if
        // WP Rocket rewrites it to a local cached copy, InLinks marks JS INACTIVE.
        ?>
<!-- EDIT. SEO Fix: InLinks -->
<script defer data-no-minify="1" data-no-optimize="1" data-cfasync="false" src="https://jscloud.net/x/<?php echo esc_attr( $site_id ); ?>/inlinks.js"></script>
<!-- /EDIT. SEO Fix: InLinks -->
        <?php
    }
}
