<?php
/**
 * EDIT. Thank-You Confetti
 *
 * Fires a brand-coloured confetti burst on the CF7 success page (/thank-you/),
 * where the inscrição form (CF7 295986) redirects after a successful submit.
 * Self-contained vanilla canvas, no external dependency. Respects reduced-motion.
 *
 * @package weareedit-site-engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EDIT_ThankYou_Confetti {

	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 99 );
	}

	private static function is_target() {
		if ( is_admin() ) {
			return false;
		}
		if ( function_exists( 'is_page' ) && is_page( 'thank-you' ) ) {
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
		return is_string( $uri ) && rtrim( $uri, '/' ) === '/thank-you';
	}

	public static function render() {
		if ( ! self::is_target() ) {
			return;
		}
		?>
<script data-no-minify="1" data-no-optimize="1" data-cfasync="false">
(function(){
  if (window.__editConfettiDone) return; window.__editConfettiDone = true;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var COLORS = ['#ffdd06','#f92869','#60c5b3','#ec8172','#DF086F','#ffffff'];
  function go(){
    var c = document.createElement('canvas');
    c.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:99999';
    document.body.appendChild(c);
    var ctx = c.getContext('2d'), dpr = window.devicePixelRatio || 1;
    function resize(){ c.width = innerWidth*dpr; c.height = innerHeight*dpr; ctx.setTransform(dpr,0,0,dpr,0,0); }
    resize(); addEventListener('resize', resize);
    var W = function(){ return innerWidth; }, H = function(){ return innerHeight; };
    var P = [], N = Math.min(180, Math.round(innerWidth/8));
    function spawn(ox){
      for (var i=0;i<N;i++){
        var a = Math.random()*Math.PI*2, sp = 6 + Math.random()*9;
        P.push({
          x: ox, y: H()*0.30,
          vx: Math.cos(a)*sp, vy: Math.sin(a)*sp - 6,
          w: 6 + Math.random()*7, h: 8 + Math.random()*9,
          rot: Math.random()*Math.PI, vr: (Math.random()-0.5)*0.4,
          col: COLORS[(Math.random()*COLORS.length)|0],
          life: 0, ttl: 140 + Math.random()*70
        });
      }
    }
    spawn(W()*0.5); setTimeout(function(){ spawn(W()*0.28); }, 140); setTimeout(function(){ spawn(W()*0.72); }, 280);
    var raf;
    function frame(){
      ctx.clearRect(0,0,W(),H());
      var alive = false;
      for (var i=0;i<P.length;i++){
        var p = P[i]; if (p.life > p.ttl) continue; alive = true;
        p.life++; p.vy += 0.22; p.vx *= 0.99; p.x += p.vx; p.y += p.vy; p.rot += p.vr;
        var fade = p.life > p.ttl-40 ? Math.max(0,(p.ttl-p.life)/40) : 1;
        ctx.save(); ctx.globalAlpha = fade; ctx.translate(p.x,p.y); ctx.rotate(p.rot);
        ctx.fillStyle = p.col; ctx.fillRect(-p.w/2,-p.h/2,p.w,p.h); ctx.restore();
      }
      if (alive) { raf = requestAnimationFrame(frame); }
      else { cancelAnimationFrame(raf); removeEventListener('resize', resize); c.remove(); }
    }
    frame();
  }
  if (document.readyState === 'complete' || document.readyState === 'interactive') { setTimeout(go, 120); }
  else { addEventListener('DOMContentLoaded', function(){ setTimeout(go, 120); }); }
})();
</script>
		<?php
	}
}
