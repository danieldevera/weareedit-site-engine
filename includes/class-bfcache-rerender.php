<?php
/**
 * BFCache re-render fix.
 *
 * WP Rocket adds `data-wpr-lazyrender="1"` to below-the-fold sections to defer
 * their render. When a user navigates away and presses the browser back button,
 * the browser restores the page from the back-forward cache (bfcache) WITHOUT
 * re-firing the JS that triggers WP Rocket's lazy render — so the deferred
 * sections stay hidden and the user sees a near-blank page.
 *
 * This module injects a tiny snippet that listens for the `pageshow` event
 * with `event.persisted === true` (bfcache restore) and, if any
 * `[data-wpr-lazyrender]` elements are present on the page, performs a single
 * reload to force a fresh render.
 *
 * Scope: only fires on bfcache restore AND only when the page actually contains
 * lazy-render elements (course pages). Other pages are untouched.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EDIT_BFCache_Rerender {

    public static function init() {
        add_action( 'wp_footer', [ __CLASS__, 'inject_script' ], 99 );
    }

    public static function inject_script() {
        // Minified inline — runs after page parse, listens for bfcache restore.
        ?>
<script id="edit-seo-bfcache-rerender">
(function(){window.addEventListener('pageshow',function(e){if(e.persisted&&document.querySelector('[data-wpr-lazyrender]')){window.location.reload()}})})();
</script>
        <?php
    }
}
