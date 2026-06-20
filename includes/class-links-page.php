<?php
/**
 * Link-in-bio page at /links — a standalone, full-bleed "Linktree" replacement
 * served entirely by the plugin (no theme header/footer), pixel-matched to the
 * Figma design with the real SctoGroteskA font.
 *
 * TEAM-MANAGED (v1.5.663):
 *  - AUTO-DYNAMIC content (zero maintenance):
 *      • Articles  → latest 3 published `blog` CPT posts (title, URL, author
 *        from post_author), refreshed automatically as new posts ship.
 *      • Area counts → read live from each pillar's CATALOG constant.
 *  - WP ADMIN PANEL (Settings → EDIT. Link-in-bio): bio, CTA URL, reviews
 *    rating/count, Early Bird promo (on/off + copy + URL), social URLs, and an
 *    author-name → photo map. Everything falls back to sane defaults, so the
 *    page renders correctly even before the team touches anything.
 *
 * Routing: template_redirect (prio 1, before redirect_canonical); no rewrite
 * flush. Full GA4 (G-R11CP4ELEH): page_view + per-link `link_click` events.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Links_Page {

	const SLUG   = 'links';
	const GA4    = 'G-R11CP4ELEH';
	const OPTION = 'edit_links_settings';

	public static function init() {
		add_action( 'template_redirect', [ __CLASS__, 'maybe_render' ], 1 );
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
	}

	private static function asset( $file ) {
		return WEAREDIT_SITE_ENGINE_URL . 'assets/' . $file;
	}

	/* ───────────────────────────── Settings ───────────────────────────── */

	public static function defaults() {
		$base = WEAREDIT_SITE_ENGINE_URL . 'assets/';
		return [
			'bio'            => 'Escola especializada em UX/UI, Marketing Digital, Data Science, Inteligência Artificial e Front-End.',
			'cta_url'        => 'https://weareedit.io/formacao/',
			'reviews_rating' => '4,1',
			'reviews_count'  => '67',
			'reviews_url'    => 'https://weareedit.io/avaliacoes-google/',
			'eb_enabled'     => '1',
			'eb_eyebrow'     => 'Inscrições Setembro 2026',
			'eb_headline'    => 'Early Bird · -15% de desconto',
			'eb_url'         => 'https://weareedit.io/formacao/',
			'social_instagram' => 'https://www.instagram.com/edit.education/',
			'social_whatsapp'  => 'https://wa.me/351936508449',
			'social_site'      => 'https://weareedit.io/',
			'social_linkedin'  => 'https://www.linkedin.com/school/edit-education/',
			'author_photos'  => "Daniel Devera | {$base}links-author-daniel.png\nCarla Geraldes | {$base}links-author-carla.png",
		];
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::OPTION, [] ), self::defaults() );
	}

	private static function author_photo_map( $raw ) {
		$map = [];
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			if ( strpos( $line, '|' ) === false ) continue;
			list( $name, $url ) = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( $name !== '' && $url !== '' ) $map[ $name ] = $url;
		}
		return $map;
	}

	/* ─────────────────────── Dynamic content ──────────────────────────── */

	/** Latest 3 published blog posts; falls back to the locked editorial trio. */
	private static function latest_articles() {
		$out = [];
		if ( post_type_exists( 'blog' ) ) {
			$q = new WP_Query( [
				'post_type'           => 'blog',
				'post_status'         => 'publish',
				'posts_per_page'      => 3,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			] );
			foreach ( $q->posts as $p ) {
				$out[] = [
					'title'  => get_the_title( $p ),
					'url'    => get_permalink( $p ),
					'author' => get_the_author_meta( 'display_name', $p->post_author ) ?: 'EDIT.',
				];
			}
			wp_reset_postdata();
		}
		if ( empty( $out ) ) {
			$out = [
				[ 'title' => 'Do prompt ao produto: o design na era da IA', 'author' => 'Daniel Devera', 'url' => 'https://weareedit.io/blog/do-prompt-ao-produto/' ],
				[ 'title' => 'O paradoxo da criatividade aumentada',         'author' => 'Carla Geraldes', 'url' => 'https://weareedit.io/blog/vinte-e-cinco-cabecas-dois-pensamentos-o-paradoxo-da-criatividade-aumentada/' ],
				[ 'title' => 'Branding na era da IA',                        'author' => 'Daniel Devera', 'url' => 'https://weareedit.io/blog/os-agentes-nao-tiveram-infancia-branding-na-era-da-ia/' ],
			];
		}
		return $out;
	}

	/** Live program count from a pillar class's CATALOG constant. */
	private static function area_count( $class, $fallback ) {
		if ( class_exists( $class ) && defined( "$class::CATALOG" ) ) {
			$n = 0;
			foreach ( (array) constant( "$class::CATALOG" ) as $group ) {
				$n += is_array( $group ) ? count( $group ) : 1;
			}
			if ( $n > 0 ) return $n;
		}
		return $fallback;
	}

	/* ───────────────────────────── Front ──────────────────────────────── */

	public static function maybe_render() {
		if ( is_admin() ) return;
		$path = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		if ( strtolower( $path ) !== self::SLUG ) return;
		self::render();
		exit;
	}

	public static function render() {
		$s         = self::settings();
		$base      = WEAREDIT_SITE_ENGINE_URL . 'assets/';
		$font_base = wp_make_link_relative( WEAREDIT_SITE_ENGINE_URL ) . 'assets/fonts/';
		$home      = 'https://weareedit.io';
		$photos    = self::author_photo_map( $s['author_photos'] );

		$areas = [
			[ 'label' => 'Marketing Digital',       'url' => $home . '/marketing-digital/',             'bg' => '#ffdd06', 'tx' => '#0a0a0a', 'class' => 'EDIT_Marketing_Digital_Page',     'fb' => 16 ],
			[ 'label' => 'Data Science',            'url' => $home . '/data-science/',                  'bg' => '#f92869', 'tx' => '#ffffff', 'class' => 'EDIT_Data_Science_Page',          'fb' => 11 ],
			[ 'label' => 'UX/UI Design',            'url' => $home . '/curso-uxui-design/',             'bg' => '#60c5b3', 'tx' => '#0a0a0a', 'class' => 'EDIT_UX_UI_Design_Page',          'fb' => 12 ],
			[ 'label' => 'Inteligência Artificial', 'url' => $home . '/curso-inteligencia-artificial/', 'bg' => '#ec8172', 'tx' => '#0a0a0a', 'class' => 'EDIT_Inteligencia_Artificial_Page','fb' => 11 ],
			[ 'label' => 'Programação',             'url' => $home . '/curso-programacao/',             'bg' => '#0090eb', 'tx' => '#ffffff', 'class' => 'EDIT_Programacao_Page',           'fb' => 8 ],
		];

		$ig = '<svg viewBox="0 0 24 24"><path fill="#fff" d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.43.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.43.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.43-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.43-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16M12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.34 4.14.63c-.79.3-1.46.71-2.13 1.38C1.34 2.68.93 3.35.63 4.14.34 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.27 2.15.56 2.91.3.79.71 1.46 1.38 2.13.67.67 1.34 1.08 2.13 1.38.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.27-.06 2.15-.27 2.91-.56a5.9 5.9 0 0 0 2.13-1.38 5.9 5.9 0 0 0 1.38-2.13c.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.27-2.15-.56-2.91a5.9 5.9 0 0 0-1.38-2.13A5.9 5.9 0 0 0 19.86.63c-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0z"/><path fill="#fff" d="M12 5.84a6.16 6.16 0 1 0 0 12.32 6.16 6.16 0 0 0 0-12.32zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.41-10.4a1.44 1.44 0 1 0 0 2.88 1.44 1.44 0 0 0 0-2.88z"/></svg>';
		$wa = '<svg viewBox="0 0 24 24"><path fill="#fff" d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.47-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.62.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35M12.05 21.79h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.89-9.88 2.64 0 5.12 1.03 6.99 2.9a9.83 9.83 0 0 1 2.89 6.99c0 5.45-4.44 9.88-9.89 9.88m8.41-18.3A11.82 11.82 0 0 0 12.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 0 0 5.69 1.45h.01c6.55 0 11.89-5.34 11.89-11.89a11.82 11.82 0 0 0-3.48-8.42"/></svg>';
		$gl = '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="M2.5 9h19M2.5 15h19M12 2.5c2.5 2.6 2.5 16.4 0 19M12 2.5c-2.5 2.6-2.5 16.4 0 19"/></svg>';
		$li = '<svg viewBox="0 0 24 24"><path fill="#fff" d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13zm1.78 13.02H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z"/></svg>';
		$socials = [
			[ 'label' => 'Instagram', 'url' => $s['social_instagram'], 'svg' => $ig ],
			[ 'label' => 'WhatsApp',  'url' => $s['social_whatsapp'],  'svg' => $wa ],
			[ 'label' => 'Site',      'url' => $s['social_site'],      'svg' => $gl ],
			[ 'label' => 'LinkedIn',  'url' => $s['social_linkedin'],  'svg' => $li ],
		];

		$tag_svg    = '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>';
		$google_svg = '<svg viewBox="0 0 24 24"><path fill="#4285F4" d="M23.5 12.27c0-.79-.07-1.54-.2-2.27H12v4.51h6.47a5.53 5.53 0 0 1-2.4 3.63v3h3.87c2.26-2.09 3.56-5.17 3.56-8.87z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.94-2.91l-3.87-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.27v3.09A12 12 0 0 0 12 24z"/><path fill="#FBBC05" d="M5.27 14.29A7.21 7.21 0 0 1 4.89 12c0-.8.14-1.57.38-2.29V6.62H1.27A12 12 0 0 0 0 12c0 1.94.46 3.77 1.27 5.38l4-3.09z"/><path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42A11.98 11.98 0 0 0 12 0 12 12 0 0 0 1.27 6.62l4 3.09C6.22 6.86 8.87 4.75 12 4.75z"/></svg>';

		header( 'Content-Type: text/html; charset=utf-8' );
		status_header( 200 );
		?><!doctype html>
<html lang="pt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>EDIT. — Links | Disruptive Digital Education</title>
<meta name="description" content="Todos os links da EDIT.: cursos, áreas de formação, redes sociais e artigos. Lisboa · Porto · Remote.">
<meta name="robots" content="noindex, follow">
<link rel="canonical" href="<?php echo esc_url( $home . '/links/' ); ?>">
<meta property="og:title" content="EDIT. — Links">
<meta property="og:description" content="Cursos, áreas, redes e artigos da EDIT. num só sítio.">
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo esc_url( $home . '/links/' ); ?>">
<link rel="icon" href="https://weareedit.io/wp-content/uploads/2021/07/cropped-weareedit_Favicon-32x32.png" sizes="32x32">
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( self::GA4 ); ?>"></script>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('js',new Date());
gtag('config','<?php echo esc_js( self::GA4 ); ?>',{page_title:'Link-in-bio',page_path:'/links'});
</script>
<style>
@font-face{font-family:'SctoGroteskA';font-weight:300;font-style:normal;font-display:swap;src:url('<?php echo esc_url( $font_base . 'SctoGroteskA-Light.woff2' ); ?>') format('woff2');}
@font-face{font-family:'SctoGroteskA';font-weight:400;font-style:normal;font-display:swap;src:url('<?php echo esc_url( $font_base . 'SctoGroteskA-Regular.woff2' ); ?>') format('woff2');}
@font-face{font-family:'SctoGroteskA';font-weight:500;font-style:normal;font-display:swap;src:url('<?php echo esc_url( $font_base . 'SctoGroteskA-Medium.woff2' ); ?>') format('woff2');}
@font-face{font-family:'SctoGroteskA';font-weight:700;font-style:normal;font-display:swap;src:url('<?php echo esc_url( $font_base . 'SctoGroteskA-Bold.woff2' ); ?>') format('woff2');}
@font-face{font-family:'SctoGroteskA';font-weight:900;font-style:normal;font-display:swap;src:url('<?php echo esc_url( $font_base . 'SctoGroteskA-Black.woff2' ); ?>') format('woff2');}
:root{--ink:#0a0a0f;--yellow:#ffdd06;--pink:#f92869;--grey:#9a9aa6;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{-webkit-text-size-adjust:100%;}
body{font-family:'SctoGroteskA',-apple-system,BlinkMacSystemFont,'Helvetica Neue',Arial,sans-serif;background:var(--ink);color:#fff;line-height:1.5;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;}
a{color:inherit;text-decoration:none;}
.wrap{max-width:520px;margin:0 auto;padding-bottom:56px;}
.hero{position:relative;text-align:center;padding:56px 24px 44px;background:
 radial-gradient(120% 90% at 0% 0%, #f92869 0%, transparent 55%),
 radial-gradient(120% 90% at 100% 0%, #ffdd06 0%, transparent 55%),
 radial-gradient(130% 100% at 0% 100%, #60c5b3 0%, transparent 55%),
 radial-gradient(130% 100% at 100% 100%, #0090eb 0%, transparent 55%),
 var(--ink);}
.hero::after{content:'';position:absolute;inset:0;background:linear-gradient(to bottom,rgba(10,10,15,.15) 0%,rgba(10,10,15,.55) 55%,var(--ink) 100%);pointer-events:none;}
.hero>*{position:relative;z-index:1;}
.logo{width:150px;height:auto;display:block;margin:0 auto 16px;}
.eyebrow{font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:var(--yellow);font-weight:700;margin-bottom:14px;}
.bio{font-size:15px;font-weight:500;max-width:380px;margin:0 auto 20px;color:#fff;}
.chip{display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border:1px solid rgba(255,255,255,.18);border-radius:10px;background:rgba(255,255,255,.08);font-size:14px;font-weight:500;margin-bottom:18px;}
.chip .g{width:18px;height:18px;flex:none;}
.chip .stars{color:var(--yellow);letter-spacing:1px;font-size:15px;}
.chip .stars .e{color:#5a5a63;}
.cta{display:block;background:var(--yellow);color:#0a0a0a;font-weight:700;font-size:16px;padding:17px 20px;border-radius:12px;text-align:center;}
.sec{padding:0 24px;}
.label{font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--grey);font-weight:700;margin:34px 0 14px;}
.eb{display:flex;align-items:center;gap:16px;background:var(--pink);border-radius:14px;padding:20px 22px;}
.eb .ic{width:30px;height:30px;flex:none;}
.eb .t{flex:1;}
.eb .k{font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;opacity:.9;}
.eb .h{font-size:21px;font-weight:900;letter-spacing:-.01em;margin-top:2px;}
.eb .arr{font-size:24px;font-weight:700;}
.area{display:flex;align-items:center;gap:14px;border-radius:14px;padding:20px 24px;margin-bottom:12px;}
.area .nm{flex:1;}
.area .nm b{display:block;font-size:22px;font-weight:900;letter-spacing:-.02em;}
.area .nm span{font-size:13px;font-weight:500;opacity:.72;}
.area .arr{font-size:24px;font-weight:700;}
.socials{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;}
.soc{display:flex;flex-direction:column;align-items:center;gap:10px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.13);border-radius:12px;padding:18px 6px 14px;}
.soc svg{width:26px;height:26px;}
.soc span{font-size:12px;font-weight:600;}
.art{display:flex;align-items:center;gap:16px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:14px 18px;margin-bottom:14px;}
.art .ph{width:56px;height:56px;border-radius:50%;object-fit:cover;flex:none;border:2px solid var(--pink);background:#161620;}
.art .ph.init{display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:900;color:#fff;background:#23232e;}
.art .meta{flex:1;min-width:0;}
.art .ti{font-size:16px;font-weight:700;line-height:1.25;letter-spacing:-.01em;}
.art .by{font-size:13px;font-weight:500;color:#cfcfd8;margin-top:5px;}
.art .by .ler{color:var(--grey);}
.art .arr{font-size:22px;color:var(--grey);font-weight:700;}
.allart{display:block;text-align:center;border:1px solid rgba(255,255,255,.2);border-radius:12px;padding:15px;color:var(--yellow);font-weight:700;font-size:15px;margin-top:4px;}
.foot{text-align:center;padding:40px 24px 0;}
.foot img{width:150px;height:auto;opacity:.95;margin-bottom:18px;}
.foot p{font-size:14px;color:var(--grey);font-weight:500;}
@media(max-width:380px){.area .nm b{font-size:20px;}.soc span{font-size:11px;}}
</style>
</head>
<body>
<main class="wrap">

  <header class="hero">
    <img class="logo" src="<?php echo esc_url( $base . 'edit-extended-white.png' ); ?>" alt="EDIT. — Disruptive Digital Education">
    <p class="eyebrow">Disruptive Digital Education</p>
    <p class="bio"><?php echo esc_html( $s['bio'] ); ?></p>
    <a class="chip" href="<?php echo esc_url( $s['reviews_url'] ); ?>" data-evt="Reviews Google" data-section="hero">
      <span class="g"><?php echo $google_svg; ?></span>
      <span class="stars">&#9733;&#9733;&#9733;&#9733;<span class="e">&#9733;</span></span>
      <span><?php echo esc_html( $s['reviews_rating'] ); ?> · <?php echo esc_html( $s['reviews_count'] ); ?> avaliações</span>
    </a>
    <a class="cta" href="<?php echo esc_url( $s['cta_url'] ); ?>" data-evt="Ver todos os cursos" data-section="hero">Ver todos os cursos &rarr;</a>
  </header>

  <?php if ( $s['eb_enabled'] === '1' && $s['eb_headline'] !== '' ) : ?>
  <section class="sec">
    <p class="label">Em destaque</p>
    <a class="eb" href="<?php echo esc_url( $s['eb_url'] ); ?>" data-evt="Early Bird" data-section="destaque">
      <span class="ic"><?php echo $tag_svg; ?></span>
      <span class="t"><span class="k"><?php echo esc_html( $s['eb_eyebrow'] ); ?></span><span class="h"><?php echo esc_html( $s['eb_headline'] ); ?></span></span>
      <span class="arr">&rarr;</span>
    </a>
  </section>
  <?php endif; ?>

  <section class="sec">
    <p class="label">Áreas de formação</p>
    <?php foreach ( $areas as $a ) : $cnt = self::area_count( $a['class'], $a['fb'] ); ?>
    <a class="area" style="background:<?php echo esc_attr( $a['bg'] ); ?>;color:<?php echo esc_attr( $a['tx'] ); ?>;" href="<?php echo esc_url( $a['url'] ); ?>" data-evt="Área: <?php echo esc_attr( $a['label'] ); ?>" data-section="areas">
      <span class="nm"><b><?php echo esc_html( $a['label'] ); ?></b><span><?php echo (int) $cnt; ?> programas · Ver área</span></span>
      <span class="arr">&rarr;</span>
    </a>
    <?php endforeach; ?>
  </section>

  <section class="sec">
    <p class="label">Ligações</p>
    <div class="socials">
      <?php foreach ( $socials as $sc ) : ?>
      <a class="soc" href="<?php echo esc_url( $sc['url'] ); ?>" target="_blank" rel="noopener" data-evt="<?php echo esc_attr( $sc['label'] ); ?>" data-section="ligacoes">
        <?php echo $sc['svg']; ?><span><?php echo esc_html( $sc['label'] ); ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="sec">
    <p class="label">Artigos</p>
    <?php foreach ( self::latest_articles() as $art ) :
        $photo = $photos[ $art['author'] ] ?? '';
        $initial = mb_strtoupper( mb_substr( $art['author'], 0, 1 ) );
    ?>
    <a class="art" href="<?php echo esc_url( $art['url'] ); ?>" data-evt="Artigo: <?php echo esc_attr( $art['title'] ); ?>" data-section="artigos">
      <?php if ( $photo ) : ?>
        <img class="ph" src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $art['author'] ); ?>">
      <?php else : ?>
        <span class="ph init"><?php echo esc_html( $initial ); ?></span>
      <?php endif; ?>
      <span class="meta">
        <span class="ti"><?php echo esc_html( $art['title'] ); ?></span>
        <span class="by"><?php echo esc_html( $art['author'] ); ?> <span class="ler">· Ler artigo</span></span>
      </span>
      <span class="arr">&rarr;</span>
    </a>
    <?php endforeach; ?>
    <a class="allart" href="<?php echo esc_url( $home . '/blog/' ); ?>" data-evt="Ver todos os artigos" data-section="artigos">Ver todos os artigos &rarr;</a>
  </section>

  <footer class="foot">
    <img src="<?php echo esc_url( $base . 'dgert-entidade-formadora-branco.png' ); ?>" alt="Entidade Formadora Certificada DGERT">
    <p>weareedit.io · Lisboa · Porto · Remote</p>
  </footer>

</main>
<script>
document.querySelectorAll('a[data-evt]').forEach(function(a){
  a.addEventListener('click',function(){
    if(typeof gtag==='function'){
      gtag('event','link_click',{link_label:a.getAttribute('data-evt'),link_section:a.getAttribute('data-section')||'',link_url:a.href});
    }
  });
});
</script>
</body>
</html><?php
	}

	/* ───────────────────────────── Admin ──────────────────────────────── */

	public static function register_menu() {
		add_options_page(
			'EDIT. Link-in-bio',
			'EDIT. Link-in-bio',
			'manage_options',
			'edit-links-page',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	public static function register_settings() {
		register_setting( 'edit_links_group', self::OPTION, [
			'sanitize_callback' => [ __CLASS__, 'sanitize' ],
		] );
	}

	public static function sanitize( $input ) {
		$input = (array) $input;
		$url   = function ( $k ) use ( $input ) { return esc_url_raw( trim( $input[ $k ] ?? '' ) ); };
		$txt   = function ( $k ) use ( $input ) { return sanitize_text_field( $input[ $k ] ?? '' ); };
		return [
			'bio'              => sanitize_textarea_field( $input['bio'] ?? '' ),
			'cta_url'          => $url( 'cta_url' ),
			'reviews_rating'   => $txt( 'reviews_rating' ),
			'reviews_count'    => $txt( 'reviews_count' ),
			'reviews_url'      => $url( 'reviews_url' ),
			'eb_enabled'       => ( ( $input['eb_enabled'] ?? '' ) === '1' ) ? '1' : '0',
			'eb_eyebrow'       => $txt( 'eb_eyebrow' ),
			'eb_headline'      => $txt( 'eb_headline' ),
			'eb_url'           => $url( 'eb_url' ),
			'social_instagram' => $url( 'social_instagram' ),
			'social_whatsapp'  => $url( 'social_whatsapp' ),
			'social_site'      => $url( 'social_site' ),
			'social_linkedin'  => $url( 'social_linkedin' ),
			'author_photos'    => sanitize_textarea_field( $input['author_photos'] ?? '' ),
		];
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s = self::settings();
		$f = function ( $k ) use ( $s ) { return esc_attr( $s[ $k ] ?? '' ); };
		?>
		<div class="wrap">
			<h1>EDIT. Link-in-bio <span style="font-size:13px;color:#666;">— página <a href="https://weareedit.io/links" target="_blank">weareedit.io/links</a></span></h1>
			<p style="max-width:720px;color:#444;">Os <strong>artigos</strong> (últimos 3 do blog) e o <strong>nº de programas</strong> por área atualizam-se automaticamente. Use este painel para o resto. Depois de guardar, limpe a cache (WP Rocket) se necessário.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'edit_links_group' ); ?>
				<table class="form-table" role="presentation">
					<tr><th colspan="2"><h2 style="margin:0;">Topo</h2></th></tr>
					<tr><th scope="row">Bio</th><td><textarea name="<?php echo self::OPTION; ?>[bio]" rows="2" class="large-text"><?php echo esc_textarea( $s['bio'] ); ?></textarea></td></tr>
					<tr><th scope="row">CTA "Ver todos os cursos" → URL</th><td><input type="url" name="<?php echo self::OPTION; ?>[cta_url]" value="<?php echo $f('cta_url'); ?>" class="regular-text"></td></tr>
					<tr><th scope="row">Avaliações Google</th><td>
						Nota <input type="text" name="<?php echo self::OPTION; ?>[reviews_rating]" value="<?php echo $f('reviews_rating'); ?>" size="4">
						· Nº <input type="text" name="<?php echo self::OPTION; ?>[reviews_count]" value="<?php echo $f('reviews_count'); ?>" size="5">
						· URL <input type="url" name="<?php echo self::OPTION; ?>[reviews_url]" value="<?php echo $f('reviews_url'); ?>" class="regular-text">
					</td></tr>

					<tr><th colspan="2"><h2 style="margin:18px 0 0;">Em destaque (Early Bird)</h2></th></tr>
					<tr><th scope="row">Mostrar destaque</th><td><label><input type="checkbox" name="<?php echo self::OPTION; ?>[eb_enabled]" value="1" <?php checked( $s['eb_enabled'], '1' ); ?>> Ativo</label></td></tr>
					<tr><th scope="row">Eyebrow</th><td><input type="text" name="<?php echo self::OPTION; ?>[eb_eyebrow]" value="<?php echo $f('eb_eyebrow'); ?>" class="regular-text"></td></tr>
					<tr><th scope="row">Headline</th><td><input type="text" name="<?php echo self::OPTION; ?>[eb_headline]" value="<?php echo $f('eb_headline'); ?>" class="large-text"></td></tr>
					<tr><th scope="row">URL</th><td><input type="url" name="<?php echo self::OPTION; ?>[eb_url]" value="<?php echo $f('eb_url'); ?>" class="regular-text"></td></tr>

					<tr><th colspan="2"><h2 style="margin:18px 0 0;">Ligações (redes)</h2></th></tr>
					<tr><th scope="row">Instagram</th><td><input type="url" name="<?php echo self::OPTION; ?>[social_instagram]" value="<?php echo $f('social_instagram'); ?>" class="regular-text"></td></tr>
					<tr><th scope="row">WhatsApp</th><td><input type="url" name="<?php echo self::OPTION; ?>[social_whatsapp]" value="<?php echo $f('social_whatsapp'); ?>" class="regular-text"></td></tr>
					<tr><th scope="row">Site</th><td><input type="url" name="<?php echo self::OPTION; ?>[social_site]" value="<?php echo $f('social_site'); ?>" class="regular-text"></td></tr>
					<tr><th scope="row">LinkedIn</th><td><input type="url" name="<?php echo self::OPTION; ?>[social_linkedin]" value="<?php echo $f('social_linkedin'); ?>" class="regular-text"></td></tr>

					<tr><th colspan="2"><h2 style="margin:18px 0 0;">Fotos de autor</h2></th></tr>
					<tr><th scope="row">Mapa Nome | URL da foto</th><td>
						<textarea name="<?php echo self::OPTION; ?>[author_photos]" rows="4" class="large-text" style="font-family:monospace;"><?php echo esc_textarea( $s['author_photos'] ); ?></textarea>
						<p class="description">Uma linha por autor: <code>Nome do Autor | https://.../foto.png</code>. O nome tem de coincidir com o autor do post no WordPress. Sem foto, mostra a inicial.</p>
					</td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
