<?php
/**
 * Auto-create the /criticas-google/ page on plugin activation/admin load.
 *
 * Page content is just a shortcode token: [edit_criticas_google_reviews].
 * The actual HTML is emitted by the shortcode handler at render time,
 * which bypasses the theme's content filters that were stripping <style>
 * and <svg> from raw saved content.
 *
 * Idempotent + self-healing:
 *  - Adopts an existing page at the slug if found (manual creation case)
 *  - Force-updates the page content to the shortcode if it doesn't already
 *    contain the shortcode (upgrade path from v1.5.5 raw-HTML page)
 *
 * CSS lives in assets/criticas-google.css and is enqueued only on this page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Criticas_Page {

    const SLUG       = 'criticas-google';
    const TITLE      = 'Críticas no Google';
    const OPTION_KEY = 'edit_seo_fix_criticas_page_id';
    const SHORTCODE  = 'edit_criticas_google_reviews';

    public static function init() {
        add_shortcode( self::SHORTCODE, [ __CLASS__, 'render_shortcode' ] );
        add_action( 'admin_init',         [ __CLASS__, 'ensure_page_exists' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function enqueue_assets() {
        if ( ! is_page( self::SLUG ) ) return;
        wp_enqueue_style(
            'edit-seo-fix-criticas-google',
            WEAREDIT_SITE_ENGINE_URL . 'assets/criticas-google.css',
            [],
            WEAREDIT_SITE_ENGINE_VERSION
        );
    }

    public static function ensure_page_exists() {
        $shortcode_token = '[' . self::SHORTCODE . ']';

        // Fast path: tracked ID still resolves to a published page.
        $stored_id = (int) get_option( self::OPTION_KEY );
        if ( $stored_id ) {
            $post = get_post( $stored_id );
            if ( $post && $post->post_status === 'publish' ) {
                // Self-heal: upgrade old raw-HTML content to shortcode form.
                if ( strpos( $post->post_content, $shortcode_token ) === false ) {
                    self::force_update_to_shortcode( $stored_id );
                }
                return $stored_id;
            }
        }

        // Migration path: page was manually created with our slug — adopt it.
        $existing = get_page_by_path( self::SLUG );
        if ( $existing ) {
            update_option( self::OPTION_KEY, $existing->ID );
            if ( strpos( $existing->post_content, $shortcode_token ) === false ) {
                self::force_update_to_shortcode( $existing->ID );
            }
            return $existing->ID;
        }

        // Create from scratch.
        $page_id = wp_insert_post( [
            'post_title'     => self::TITLE,
            'post_name'      => self::SLUG,
            'post_content'   => $shortcode_token,
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => get_current_user_id() ?: 1,
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ], true );

        if ( is_wp_error( $page_id ) ) {
            error_log( 'EDIT_Criticas_Page: page creation failed — ' . $page_id->get_error_message() );
            return 0;
        }

        update_option( self::OPTION_KEY, $page_id );
        self::set_rank_math_meta( $page_id );

        return $page_id;
    }

    private static function force_update_to_shortcode( int $page_id ): void {
        wp_update_post( [
            'ID'           => $page_id,
            'post_content' => '[' . self::SHORTCODE . ']',
        ] );
        self::set_rank_math_meta( $page_id );
    }

    private static function set_rank_math_meta( int $page_id ): void {
        update_post_meta( $page_id, 'rank_math_title',       'Críticas Google — EDIT. Disruptive Digital Education' );
        update_post_meta( $page_id, 'rank_math_description', '67 avaliações verificadas dos alunos da EDIT. nos campus de Lisboa (4.2/5) e Porto (4.0/5). Avaliação média 4.1 no Google.' );
        update_post_meta( $page_id, 'rank_math_robots',      [ 'index', 'follow' ] );
    }

    /**
     * Render the page body. URLs are the real per-campus Google Knowledge
     * Panel links provided 2026-05-25. SVG is inlined for portability.
     */
    public static function render_shortcode(): string {
        $url_lisboa = 'https://www.google.com/search?sca_esv=317ac58a646953f7&rlz=1C5CHFA_enPT1181PT1181&sxsrf=ANbL-n5NF7a2F1kZGKzRLy3GXf-jy-pEUw:1779709662668&q=EDIT.+-+Disruptive+Digital+Education&si=AL3DRZELanY_V96Y0q8q1FLKlfOvH_NlT58VxGyuYFaMYKzFM6Mokt4Y4Ruu3i3UpzrqwRdAaPatY6Oux-gO2Ij1kqRIsIG59t1flkb_z6Movmvw1G1zLw-iWIUQli-YxjJav5kjPh8yvOLhRv_b_1MqLPi6AVmokg%3D%3D&sa=X&sqi=2';
        $url_porto  = 'https://www.google.com/search?sca_esv=317ac58a646953f7&rlz=1C5CHFA_enPT1181PT1181&sxsrf=ANbL-n6tyRhxoueDGu-vnmhSg1OLMev8eA:1779709695595&q=EDIT.+-+Disruptive+Digital+Education&si=AL3DRZGNtcdgKOqVhotcr-UG2kkYpwR2WO4qu3O00NmpwBmLnZwdr0by9-lDw02A3djoI5bg0JGVJxifhYhygsgoppU6tYtIi856LbYV6Y7Yzk1xPZ6RO1SHu_4iNcggVODR0dSUaDVhWrBQhfcCzyXcqGnuzpc7mA%3D%3D&sa=X';

        $url_lisboa = esc_url( $url_lisboa );
        $url_porto  = esc_url( $url_porto );

        $g_logo = '<svg class="cg-campus__logo" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/><path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/><path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/><path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/></svg>';

        return <<<HTML
<div class="cg-page">
  <section class="cg-hero">
    <video class="cg-hero__video" autoplay muted loop playsinline preload="auto" aria-hidden="true">
      <source src="/wp-content/uploads/2026/03/waves-sequence-compressed.mp4" type="video/mp4">
    </video>
    <div class="cg-hero__content">
      <a class="cg-hero__source" href="{$url_lisboa}" target="_blank" rel="noopener">
        {$g_logo}
        <span class="cg-hero__source-text">Críticas verificadas no Google</span>
        <span class="cg-hero__source-arrow" aria-hidden="true">&#x2197;</span>
      </a>
      <div class="cg-hero__quote-mark" aria-hidden="true">&ldquo;</div>
      <h1 class="cg-hero__quote">Mudei de carreira em 6 meses<span class="cg-hero__quote-dot">.</span></h1>
      <p class="cg-hero__attribution">
        <span class="cg-hero__author">Inês Bernardino</span>
        <span class="cg-hero__author-meta">UX/UI Design Bootcamp · EDIT. Lisboa · Product Designer @ Farfetch</span>
      </p>
      <div class="cg-hero__cta-row">
        <a class="cg-hero__cta-primary" href="#criticas">Ler todas as 67 críticas</a>
        <a class="cg-hero__cta-secondary" href="{$url_lisboa}" target="_blank" rel="noopener">Deixar uma crítica</a>
      </div>
      <a class="cg-hero__reviews" href="{$url_lisboa}" target="_blank" rel="noopener" aria-label="Avaliações Google — 4.1 de 5 baseado em 67 reviews">
        <span class="cg-hero__reviews-star">&#x2605;</span>
        <span class="cg-hero__reviews-rating">4.1</span>
        <span class="cg-hero__reviews-sep">/</span>
        <span class="cg-hero__reviews-count">67 reviews no</span>
        <span class="cg-hero__reviews-gw" aria-hidden="true"><span class="g-G">G</span><span class="g-o1">o</span><span class="g-o2">o</span><span class="g-g">g</span><span class="g-l">l</span><span class="g-e">e</span></span>
      </a>
    </div>
  </section>

  <section class="cg-statbar" aria-label="Resumo das avaliações no Google">
    <div class="cg-statbar__brand">
      {$g_logo}
      <span class="cg-statbar__brand-wordmark" aria-hidden="true"><span class="g-G">G</span><span class="g-o1">o</span><span class="g-o2">o</span><span class="g-g">g</span><span class="g-l">l</span><span class="g-e">e</span></span>
      <span class="cg-statbar__brand-sr-only">Google Reviews</span>
    </div>
    <div class="cg-statbar__main">
      <div class="cg-statbar__num">4.1</div>
      <div class="cg-statbar__stars" aria-hidden="true">★★★★<span style="opacity:0.45;">★</span></div>
    </div>
    <div class="cg-statbar__meta">
      Em <strong>67 avaliações</strong><br>
      Em <strong>2 campus</strong> · Lisboa + Porto
    </div>
  </section>

  <section class="cg-quotes" id="criticas" aria-label="Críticas em destaque">
    <div class="cg-quotes__inner">
      <div class="cg-quotes__head">
        <div class="cg-quotes__eyebrow">&starf; <span>Críticas verificadas no Google</span></div>
        <h2 class="cg-quotes__title">A história, em primeira pessoa.</h2>
      </div>
      <div class="swiper cg-quotes__swiper">
        <div class="swiper-wrapper">
          <div class="swiper-slide">
            <article class="cg-quote">
              <div class="cg-quote__num">01 &mdash; Bootcamp Advanced AI</div>
              <p class="cg-quote__text">Concluí recentemente o Bootcamp online &ldquo;Advanced Artificial Inteligence&rdquo; com a professora Naiara Back, recomendo!</p>
              <div class="cg-quote__attrib">
                <div class="cg-quote__stars" aria-label="5 de 5 estrelas">★★★★★</div>
                <div class="cg-quote__attrib-inner">
                  <div class="cg-quote__name">Santiago Santos</div>
                  <div class="cg-quote__role">Crítica verificada &middot; Google</div>
                </div>
              </div>
            </article>
          </div>
          <div class="swiper-slide">
            <article class="cg-quote">
              <div class="cg-quote__num">02 &mdash; Local Guide</div>
              <p class="cg-quote__text">Óptima referência no mercado de trabalho, fazendo a diferença num CV! Super recomendo.</p>
              <div class="cg-quote__attrib">
                <div class="cg-quote__stars" aria-label="5 de 5 estrelas">★★★★★</div>
                <div class="cg-quote__attrib-inner">
                  <div class="cg-quote__name">Cristiana Vilaça Ferreira</div>
                  <div class="cg-quote__role">Local Guide &middot; 14 críticas</div>
                </div>
              </div>
            </article>
          </div>
          <div class="swiper-slide">
            <article class="cg-quote">
              <div class="cg-quote__num">03 &mdash; Alumni UXUI</div>
              <p class="cg-quote__text">Escola com boas condições e equipa administrativa muito simpática e prestável. Recomendo a EDIT., principalmente pelos tutores &mdash; profissionais bastante experientes com verdadeiro gosto em ensinar.</p>
              <div class="cg-quote__attrib">
                <div class="cg-quote__stars" aria-label="5 de 5 estrelas">★★★★★</div>
                <div class="cg-quote__attrib-inner">
                  <div class="cg-quote__name">Inês Ávila Rebelo</div>
                  <div class="cg-quote__role">Crítica verificada &middot; Google</div>
                </div>
              </div>
            </article>
          </div>
          <div class="swiper-slide">
            <article class="cg-quote">
              <div class="cg-quote__num">04 &mdash; Aluna</div>
              <p class="cg-quote__text">Muito bons tutores, sempre prestáveis e atenciosos. Escola bem preparada e com boas condições. Gostei da experiência e recomendo.</p>
              <div class="cg-quote__attrib">
                <div class="cg-quote__stars" aria-label="5 de 5 estrelas">★★★★★</div>
                <div class="cg-quote__attrib-inner">
                  <div class="cg-quote__name">Ana Vitorino</div>
                  <div class="cg-quote__role">Crítica verificada &middot; Google</div>
                </div>
              </div>
            </article>
          </div>
        </div>
      </div>
      <div class="cg-quotes__nav">
        <button class="cg-quotes__arrow cg-quotes__arrow--prev" aria-label="Crítica anterior">&larr;</button>
        <div class="cg-quotes__counter"><span class="cg-quotes__current">01</span>&nbsp;/&nbsp;04</div>
        <button class="cg-quotes__arrow cg-quotes__arrow--next" aria-label="Próxima crítica">&rarr;</button>
      </div>
    </div>
  </section>
  <script>
  (function(){var t=0;function init(){if(typeof Swiper==='undefined'){if(t++<50){setTimeout(init,100);}return;}var sw=new Swiper('.cg-quotes__swiper',{slidesPerView:1.05,spaceBetween:16,loop:true,autoplay:{delay:7000,disableOnInteraction:false,pauseOnMouseEnter:true},navigation:{nextEl:'.cg-quotes__arrow--next',prevEl:'.cg-quotes__arrow--prev'},breakpoints:{720:{slidesPerView:2.4,spaceBetween:48}},on:{slideChange:function(){var c=document.querySelector('.cg-quotes__current');if(c){c.textContent=(this.realIndex+1).toString().padStart(2,'0');}}}});}document.readyState==='loading'?document.addEventListener('DOMContentLoaded',init):init();})();
  </script>

  <section class="cg-alumni-videos" aria-label="Histórias de Alumni">
    <div class="cg-alumni-videos__inner">
      <div class="cg-alumni-videos__head">
        <div class="cg-alumni-videos__eyebrow">Inspire others</div>
        <h2 class="cg-alumni-videos__title">Histórias de quem passou por aqui.</h2>
        <p class="cg-alumni-videos__sub">3 alunos partilham o que mudou depois da EDIT.</p>
      </div>
      <div class="cg-alumni-videos__grid">
        <button class="cg-alumni-video" data-vimeo-id="503548420" aria-label="Reproduzir vídeo 1">
          <img src="https://vumbnail.com/503548420.jpg" alt="História de Alumni — vídeo 1" loading="lazy">
          <span class="cg-alumni-video__play" aria-hidden="true">&#9654;</span>
        </button>
        <button class="cg-alumni-video" data-vimeo-id="361118949" aria-label="Reproduzir vídeo 2">
          <img src="https://vumbnail.com/361118949.jpg" alt="História de Alumni — vídeo 2" loading="lazy">
          <span class="cg-alumni-video__play" aria-hidden="true">&#9654;</span>
        </button>
        <button class="cg-alumni-video" data-vimeo-id="354168570" aria-label="Reproduzir vídeo 3">
          <img src="https://vumbnail.com/354168570.jpg" alt="História de Alumni — vídeo 3" loading="lazy">
          <span class="cg-alumni-video__play" aria-hidden="true">&#9654;</span>
        </button>
      </div>
    </div>
  </section>
  <div class="cg-alumni-modal" id="cgAlumniModal" hidden role="dialog" aria-modal="true" aria-label="Vídeo">
    <button class="cg-alumni-modal__close" aria-label="Fechar vídeo">&times;</button>
    <div class="cg-alumni-modal__inner">
      <div class="cg-alumni-modal__player"></div>
    </div>
  </div>
  <script>
  (function(){function init(){var modal=document.getElementById('cgAlumniModal');if(!modal)return;var player=modal.querySelector('.cg-alumni-modal__player');var thumbs=document.querySelectorAll('.cg-alumni-video');function open(id){player.innerHTML='<iframe src="https://player.vimeo.com/video/'+id+'?autoplay=1" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';modal.hidden=false;requestAnimationFrame(function(){modal.classList.add('is-open');});document.body.style.overflow='hidden';}function close(){modal.classList.remove('is-open');setTimeout(function(){modal.hidden=true;player.innerHTML='';document.body.style.overflow='';},250);}thumbs.forEach(function(t){t.addEventListener('click',function(){open(t.getAttribute('data-vimeo-id'));});});modal.querySelector('.cg-alumni-modal__close').addEventListener('click',close);modal.addEventListener('click',function(e){if(e.target===modal)close();});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!modal.hidden)close();});}document.readyState==='loading'?document.addEventListener('DOMContentLoaded',init):init();})();
  </script>

  <section class="cg-alumni-banner" aria-label="Avaliar a EDIT.">
    <div class="cg-alumni-banner__card">
      <div class="cg-alumni-banner__quote-mark" aria-hidden="true">&ldquo;</div>
      <div class="cg-alumni-banner__sticker">60 SEG</div>
      <div class="cg-alumni-banner__inner">
        <div class="cg-alumni-banner__eyebrow">
          <svg width="16" height="16" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/><path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/><path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/><path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/></svg>
          <span>Já és aluno EDIT.? &nbsp;·&nbsp; Crítica no Google</span>
        </div>
        <h2 class="cg-alumni-banner__title">Conta-nos a tua <strong>história</strong>.</h2>
        <p class="cg-alumni-banner__sub">Demora menos de um minuto e a tua opinião fica para sempre.</p>
        <div class="cg-alumni-banner__buttons">
          <a class="cg-alumni-banner__btn" href="{$url_lisboa}" target="_blank" rel="noopener">Avaliar Lisboa &rarr;</a>
          <a class="cg-alumni-banner__btn" href="{$url_porto}" target="_blank" rel="noopener">Avaliar Porto &rarr;</a>
        </div>
        <div class="cg-alumni-banner__footnote">Críticas verificadas no Google &middot; 100% honestas</div>
      </div>
    </div>
  </section>

  <section class="cg-campuses">
    <article class="cg-campus">
      <div class="cg-campus__brand">{$g_logo}<span class="cg-campus__brand-text">Google · Lisboa</span></div>
      <h2 class="cg-campus__name">EDIT. Lisboa</h2>
      <div class="cg-campus__rating-row">
        <span class="cg-campus__stars" aria-hidden="true">★★★★<span style="opacity:0.45;">★</span></span>
        <span class="cg-campus__rating-num">4.2</span>
        <span class="cg-campus__count">· 36 críticas</span>
      </div>
      <p class="cg-campus__address">Av. Aquilino Ribeiro Machado, Nº 8<br>1800-399 Lisboa</p>
    </article>
    <article class="cg-campus">
      <div class="cg-campus__brand">{$g_logo}<span class="cg-campus__brand-text">Google · Porto</span></div>
      <h2 class="cg-campus__name">EDIT. Porto</h2>
      <div class="cg-campus__rating-row">
        <span class="cg-campus__stars" aria-hidden="true">★★★★<span style="opacity:0.45;">★</span></span>
        <span class="cg-campus__rating-num">4.0</span>
        <span class="cg-campus__count">· 31 críticas</span>
      </div>
      <p class="cg-campus__address">R. do Alferes Malheiro, Nº 226<br>4000-057 Porto</p>
    </article>
  </section>

  <p class="cg-future">Em breve, também no Trustpilot, Course Report e SwitchUp.</p>
</div>
HTML;
    }
}
