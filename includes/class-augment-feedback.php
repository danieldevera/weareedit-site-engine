<?php
/**
 * Augment page — async team-feedback layer.
 * ─────────────────────────────────────────────────────────────────────────────
 * Daniel shares the preview link with the team; each person drops pinned notes
 * directly on the page. Notes are stored server-side (shared + persistent) so
 * all feedback collects in one place for review. No logins — write is gated by
 * the same preview token the page uses, so only people with the link can post.
 *
 * Storage: a single WP option (array of note objects) — fine for a feedback
 * round's volume. REST under /wp-json/edit-augment/v1/notes (same-origin on the
 * subdomain → no CORS). The widget HTML/CSS/JS is injected into the standalone
 * Augment document by EDIT_Augment_Page::render_page() when ENABLED is true.
 *
 * Toggle off (FEEDBACK_ENABLED=false) before public launch to remove the layer.
 *
 * @since 1.5.577
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Augment_Feedback {

	/** Master switch — flip false to remove the feedback layer for launch. */
	const ENABLED = true;

	/** Write secret = the Augment preview token (people with the link can post). */
	const SECRET = 'augment-2026-preview';

	const OPTION = 'edit_augment_notes';
	const NS     = 'edit-augment/v1';

	public static function init(): void {
		if ( ! self::ENABLED ) return;
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
	}

	public static function routes(): void {
		register_rest_route( self::NS, '/notes', [
			[ 'methods' => 'GET',  'callback' => [ __CLASS__, 'list_notes' ], 'permission_callback' => '__return_true' ],
			[ 'methods' => 'POST', 'callback' => [ __CLASS__, 'add_note' ],   'permission_callback' => '__return_true' ],
		] );
		register_rest_route( self::NS, '/notes/(?P<id>[a-z0-9]+)', [
			[ 'methods' => 'DELETE', 'callback' => [ __CLASS__, 'delete_note' ], 'permission_callback' => '__return_true' ],
		] );
	}

	private static function notes(): array {
		$n = get_option( self::OPTION, [] );
		return is_array( $n ) ? $n : [];
	}

	public static function list_notes() {
		return new WP_REST_Response( array_values( self::notes() ), 200 );
	}

	public static function add_note( WP_REST_Request $req ) {
		if ( ! hash_equals( self::SECRET, (string) $req->get_param( 'secret' ) ) ) {
			return new WP_REST_Response( [ 'error' => 'forbidden' ], 403 );
		}
		$text = trim( wp_strip_all_tags( (string) $req->get_param( 'text' ) ) );
		if ( $text === '' ) return new WP_REST_Response( [ 'error' => 'empty' ], 400 );

		$note = [
			'id'     => substr( md5( uniqid( '', true ) ), 0, 10 ),
			'text'   => mb_substr( $text, 0, 1000 ),
			'author' => mb_substr( trim( wp_strip_all_tags( (string) $req->get_param( 'author' ) ) ), 0, 60 ) ?: 'Anónimo',
			'xpct'   => max( 0, min( 100, (float) $req->get_param( 'xpct' ) ) ),
			'y'      => max( 0, (int) $req->get_param( 'y' ) ),
			'vw'     => (int) $req->get_param( 'vw' ),
			'ts'     => time(),
		];
		$notes = self::notes();
		if ( count( $notes ) >= 500 ) array_shift( $notes ); // hard cap
		$notes[] = $note;
		update_option( self::OPTION, $notes, false );
		return new WP_REST_Response( $note, 201 );
	}

	public static function delete_note( WP_REST_Request $req ) {
		if ( ! hash_equals( self::SECRET, (string) $req->get_param( 'secret' ) ) ) {
			return new WP_REST_Response( [ 'error' => 'forbidden' ], 403 );
		}
		$id    = (string) $req->get_param( 'id' );
		$notes = array_values( array_filter( self::notes(), fn( $n ) => ( $n['id'] ?? '' ) !== $id ) );
		update_option( self::OPTION, $notes, false );
		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}

	/** Inject the widget just before </body> of the standalone Augment doc. */
	public static function inject_widget( string $html ): string {
		if ( ! self::ENABLED ) return $html;
		return str_replace( '</body>', self::widget() . '</body>', $html );
	}

	private static function widget(): string {
		$secret = esc_js( self::SECRET );
		$base   = '/wp-json/' . self::NS . '/notes';
		return <<<HTML
<style id="aug-fb-css">
  #augfb-bar{position:fixed;right:18px;bottom:18px;z-index:2147483000;display:flex;gap:10px;font-family:'SctoGroteskA','Inter',-apple-system,Helvetica,Arial,sans-serif;}
  #augfb-bar button{border:0;border-radius:8px;padding:11px 16px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 6px 20px rgba(0,0,0,.18);transition:.15s;}
  #augfb-add{background:#7a38b4;color:#fff;}
  #augfb-add.on{background:#0a0a0a;}
  #augfb-list{background:#fff;color:#0a0a0a;border:1px solid #e6e6e3;}
  body.augfb-adding{cursor:crosshair;}
  body.augfb-adding *{cursor:crosshair !important;}
  .augfb-pin{position:absolute;z-index:2147482000;width:28px;height:28px;border-radius:50% 50% 50% 2px;background:#7a38b4;color:#fff;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 10px rgba(122,56,180,.45);cursor:pointer;transform:translate(-50%,-100%);border:2px solid #fff;}
  .augfb-pin:hover{background:#DF086F;}
  .augfb-pop{position:absolute;z-index:2147483100;width:300px;max-width:88vw;background:#fff;border:1px solid #e6e6e3;border-radius:12px;box-shadow:0 16px 44px rgba(0,0,0,.22);padding:14px;font-family:'SctoGroteskA','Inter',-apple-system,Helvetica,Arial,sans-serif;transform:translate(-50%,12px);}
  .augfb-pop textarea{width:100%;box-sizing:border-box;border:1px solid #e6e6e3;border-radius:8px;padding:9px;font:inherit;font-size:14px;resize:vertical;min-height:64px;}
  .augfb-pop input{width:100%;box-sizing:border-box;border:1px solid #e6e6e3;border-radius:8px;padding:8px 9px;font:inherit;font-size:13px;margin-top:8px;}
  .augfb-pop .row{display:flex;gap:8px;justify-content:flex-end;margin-top:10px;}
  .augfb-pop .row button{border:0;border-radius:8px;padding:9px 14px;font-size:13px;font-weight:700;cursor:pointer;}
  .augfb-save{background:#7a38b4;color:#fff;} .augfb-cancel{background:#f1eef6;color:#0a0a0a;}
  .augfb-read{font-size:14px;line-height:1.5;color:#0a0a0a;white-space:pre-wrap;}
  .augfb-meta{font-size:12px;color:#8a8a8a;margin:8px 0 0;display:flex;justify-content:space-between;align-items:center;}
  .augfb-del{background:none;border:0;color:#DF086F;font-size:12px;font-weight:700;cursor:pointer;padding:0;}
  #augfb-panel{position:fixed;right:18px;bottom:74px;z-index:2147483050;width:330px;max-width:92vw;max-height:62vh;overflow:auto;background:#fff;border:1px solid #e6e6e3;border-radius:14px;box-shadow:0 20px 50px rgba(0,0,0,.22);padding:16px;display:none;font-family:'SctoGroteskA','Inter',-apple-system,Helvetica,Arial,sans-serif;}
  #augfb-panel.open{display:block;}
  #augfb-panel h4{margin:0 0 12px;font-size:14px;}
  .augfb-item{border-top:1px solid #efefef;padding:10px 0;font-size:13px;cursor:pointer;}
  .augfb-item b{font-weight:700;} .augfb-item .t{color:#444;line-height:1.45;margin-top:2px;}
</style>
<div id="augfb-bar">
  <button id="augfb-list" type="button">Notas (0)</button>
  <button id="augfb-add" type="button">+ Deixar nota</button>
</div>
<div id="augfb-panel"><h4>Feedback da equipa</h4><div id="augfb-items"></div></div>
<script>
(function(){
  var BASE="$base", SECRET="$secret";
  var adding=false, notes=[];
  var bar=document.getElementById('augfb-bar');
  var addBtn=document.getElementById('augfb-add'), listBtn=document.getElementById('augfb-list');
  var panel=document.getElementById('augfb-panel'), items=document.getElementById('augfb-items');
  function author(){ var a=localStorage.getItem('augfb_author'); if(!a){ a=(prompt('O teu nome (para as notas):')||'').trim(); if(a) localStorage.setItem('augfb_author',a); } return a||'Anónimo'; }
  function count(){ listBtn.textContent='Notas ('+notes.length+')'; }
  function docW(){ return document.documentElement.scrollWidth; }
  function clear(){ [].forEach.call(document.querySelectorAll('.augfb-pin,.augfb-pop'),function(e){e.remove();}); }
  function render(){
    clear();
    notes.forEach(function(n,i){
      var p=document.createElement('div'); p.className='augfb-pin'; p.textContent=i+1;
      p.style.left=n.xpct+'%'; p.style.top=n.y+'px';
      p.onclick=function(ev){ ev.stopPropagation(); openRead(n,p); };
      document.body.appendChild(p);
    });
    items.innerHTML='';
    notes.forEach(function(n,i){
      var d=document.createElement('div'); d.className='augfb-item';
      d.innerHTML='<b>'+(i+1)+'. '+escapeHtml(n.author)+'</b><div class="t">'+escapeHtml(n.text)+'</div>';
      d.onclick=function(){ window.scrollTo({top:Math.max(0,n.y-160),behavior:'smooth'}); };
      items.appendChild(d);
    });
    count();
  }
  function escapeHtml(s){ return (s||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function openRead(n,pin){
    closePops();
    var pop=document.createElement('div'); pop.className='augfb-pop';
    pop.style.left=n.xpct+'%'; pop.style.top=(n.y)+'px';
    var when=new Date(n.ts*1000).toLocaleString('pt-PT');
    pop.innerHTML='<div class="augfb-read">'+escapeHtml(n.text)+'</div><div class="augfb-meta"><span>'+escapeHtml(n.author)+' · '+when+'</span><button class="augfb-del">Apagar</button></div>';
    pop.querySelector('.augfb-del').onclick=function(){ del(n.id); pop.remove(); };
    pop.onclick=function(e){e.stopPropagation();};
    document.body.appendChild(pop);
  }
  function closePops(){ [].forEach.call(document.querySelectorAll('.augfb-pop'),function(e){e.remove();}); }
  function openCompose(x,y){
    closePops();
    var pop=document.createElement('div'); pop.className='augfb-pop';
    pop.style.left=x+'%'; pop.style.top=y+'px';
    pop.innerHTML='<textarea placeholder="A tua nota..."></textarea><input type="text" placeholder="O teu nome" /><div class="row"><button class="augfb-cancel">Cancelar</button><button class="augfb-save">Guardar</button></div>';
    var ta=pop.querySelector('textarea'), nm=pop.querySelector('input');
    nm.value=author();
    pop.querySelector('.augfb-cancel').onclick=function(){ pop.remove(); };
    pop.querySelector('.augfb-save').onclick=function(){
      var t=ta.value.trim(); if(!t) return;
      var a=(nm.value||'').trim()||'Anónimo'; localStorage.setItem('augfb_author',a);
      save({text:t,author:a,xpct:x,y:Math.round(y),vw:docW()}); pop.remove();
    };
    pop.onclick=function(e){e.stopPropagation();};
    document.body.appendChild(pop); ta.focus();
  }
  function load(){ fetch(BASE).then(function(r){return r.json();}).then(function(d){ notes=Array.isArray(d)?d:[]; render(); }).catch(function(){}); }
  function save(n){
    n.secret=SECRET;
    fetch(BASE,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(n)})
      .then(function(r){return r.json();}).then(function(saved){ if(saved&&saved.id){ notes.push(saved); render(); } });
  }
  function del(id){ fetch(BASE+'/'+id+'?secret='+encodeURIComponent(SECRET),{method:'DELETE'}).then(function(){ notes=notes.filter(function(n){return n.id!==id;}); render(); }); }
  addBtn.onclick=function(){ adding=!adding; addBtn.classList.toggle('on',adding); addBtn.textContent=adding?'Clica na página…':'+ Deixar nota'; document.body.classList.toggle('augfb-adding',adding); };
  listBtn.onclick=function(){ panel.classList.toggle('open'); };
  document.addEventListener('click',function(e){
    if(!adding){ if(!e.target.closest('.augfb-pop')&&!e.target.closest('.augfb-pin')) closePops(); return; }
    if(e.target.closest('#augfb-bar')||e.target.closest('.augfb-pop')) return;
    e.preventDefault();
    var x=((e.pageX)/docW())*100, y=e.pageY;
    adding=false; addBtn.classList.remove('on'); addBtn.textContent='+ Deixar nota'; document.body.classList.remove('augfb-adding');
    openCompose(x,y);
  },true);
  load();
})();
</script>
HTML;
	}
}
