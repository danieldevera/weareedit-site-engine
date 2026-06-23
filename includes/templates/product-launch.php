<?php
/**
 * Generic product-launch landing template.
 * Expects $C (product config array, see includes/data/products/*.php) in scope,
 * with $C['_mode'] = 'preview' | 'live'. Emits a full HTML document including a
 * complete SEO/GEO head (Course + FAQPage + BreadcrumbList JSON-LD, meta, OG).
 *
 * SEO posture: preview => noindex,nofollow ; live => index,follow + full schema.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$mode    = $C['_mode'] ?? 'preview';
$is_live = ( $mode === 'live' );
$seo     = $C['seo'] ?? [];
$canon   = $seo['canonical'] ?? home_url( '/' . $C['slug'] . '/' );
$accentMap = [ 'pink' => '#f92869', 'teal' => '#60c5b3', 'blue' => '#0090eb', 'yellow' => '#ffdd06' ];
$accent  = $accentMap[ $C['format_color'] ?? 'pink' ] ?? '#f92869';
$has_instructor = ! empty( $C['instructor']['name'] ) && strpos( $C['instructor']['name'], '[A CONFIRMAR]' ) === false;

/* ---------------- JSON-LD (only emitted live; keeps preview clean) ---------------- */
$jsonld = [];
if ( $is_live ) {
	$instance = [
		'@type'        => 'CourseInstance',
		'courseMode'   => 'online',
		'courseWorkload' => $seo['time_required'] ?? null,
		'location'     => [ '@type' => 'VirtualLocation', 'url' => $canon ],
	];
	if ( ! empty( $seo['start_date'] ) ) $instance['startDate'] = $seo['start_date'];

	$course = array_filter( [
		'@context'                     => 'https://schema.org',
		'@type'                        => 'Course',
		'name'                         => $C['name'],
		'description'                  => $seo['meta'] ?? '',
		'url'                          => $canon,
		'inLanguage'                   => 'pt-PT',
		'provider'                     => [
			'@type'  => 'Organization',
			'name'   => 'EDIT. - Disruptive Digital Education',
			'url'    => 'https://weareedit.io',
			'sameAs' => [ 'https://www.wikidata.org/wiki/Q139907765' ],
		],
		'educationalLevel'             => $seo['educational_level'] ?? null,
		'timeRequired'                 => $seo['time_required'] ?? null,
		'teaches'                      => $seo['teaches'] ?? null,
		'about'                        => $seo['about'] ?? null,
		'educationalCredentialAwarded' => $seo['credential'] ?? null,
		'courseMode'                   => 'online',
		'hasCourseInstance'            => array_filter( $instance ),
	] );
	if ( $has_instructor ) {
		$course['instructor'] = [ '@type' => 'Person', 'name' => $C['instructor']['name'], 'jobTitle' => $C['instructor']['role'] ?? '' ];
	}
	if ( ! empty( $seo['price'] ) ) {
		$course['offers'] = [
			'@type'         => 'Offer',
			'category'      => 'Bootcamp',
			'price'         => (string) $seo['price'],
			'priceCurrency' => $seo['currency'] ?? 'EUR',
			'availability'  => 'https://schema.org/InStock',
			'url'           => $canon,
		];
	}
	$jsonld[] = $course;

	if ( ! empty( $C['faq'] ) ) {
		$jsonld[] = [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array_map( function ( $f ) {
				return [ '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $f[1] ] ];
			}, $C['faq'] ),
		];
	}
	$jsonld[] = [
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => [
			[ '@type' => 'ListItem', 'position' => 1, 'name' => 'EDIT.', 'item' => 'https://weareedit.io/' ],
			[ '@type' => 'ListItem', 'position' => 2, 'name' => 'Formação', 'item' => 'https://weareedit.io/formacao/' ],
			[ '@type' => 'ListItem', 'position' => 3, 'name' => $C['name'], 'item' => $canon ],
		],
	];
}
$enc = function ( $a ) { return wp_json_encode( $a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); };
?><!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $seo['title'] ?? $C['name'] ); ?></title>
<meta name="description" content="<?php echo esc_attr( $seo['meta'] ?? '' ); ?>">
<meta name="robots" content="<?php echo $is_live ? 'index,follow,max-image-preview:large' : 'noindex,nofollow'; ?>">
<link rel="canonical" href="<?php echo esc_url( $canon ); ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_PT">
<meta property="og:site_name" content="EDIT. - Disruptive Digital Education">
<meta property="og:title" content="<?php echo esc_attr( $seo['title'] ?? $C['name'] ); ?>">
<meta property="og:description" content="<?php echo esc_attr( $seo['meta'] ?? '' ); ?>">
<meta property="og:url" content="<?php echo esc_url( $canon ); ?>">
<?php if ( ! empty( $seo['og_image'] ) ) : ?><meta property="og:image" content="<?php echo esc_url( $seo['og_image'] ); ?>">
<meta name="twitter:card" content="summary_large_image"><?php endif; ?>
<?php foreach ( $jsonld as $block ) : ?>
<script type="application/ld+json"><?php echo $enc( $block ); ?></script>
<?php endforeach; ?>
<style>
  :root{--ink:#111;--grey:#5b5b5b;--line:#e8e8e8;--soft:#fafafa;--yellow:#ffdd06;--pink:#f92869;--teal:#60c5b3;--purple:#7a38b4;--accent:<?php echo $accent; ?>;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;background:var(--soft);color:var(--ink);line-height:1.55;-webkit-font-smoothing:antialiased;}
  .sec{max-width:1080px;margin:0 auto;padding:62px 28px;}
  .eyebrow{font-size:13px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:var(--accent);margin-bottom:12px;}
  h2{font-size:36px;font-weight:800;letter-spacing:-1px;line-height:1.1;margin-bottom:10px;}
  h2 .hl{background:linear-gradient(180deg,transparent 62%,var(--yellow) 62% 92%,transparent 92%);}
  .lead{font-size:17px;color:var(--grey);max-width:660px;margin-bottom:36px;}
  .btn{background:var(--ink);color:var(--yellow);font-weight:800;font-size:15px;border:none;border-radius:6px;padding:15px 30px;text-decoration:none;display:inline-block;cursor:pointer;}
  .alt{background:#fff;}
  .tba{background:var(--yellow);color:var(--ink);font-weight:800;font-size:11px;letter-spacing:.04em;border-radius:4px;padding:2px 7px;text-transform:uppercase;}
  .pvbar{background:var(--ink);color:#fff;font-size:12.5px;text-align:center;padding:9px 16px;}.pvbar b{color:var(--yellow);}
  .hero{position:relative;overflow:hidden;background:#0a0613;color:#fff;background-image:radial-gradient(120% 120% at 12% 8%,rgba(122,56,180,.55),transparent 42%),radial-gradient(120% 120% at 88% 18%,rgba(249,40,105,.45),transparent 46%),radial-gradient(140% 120% at 70% 100%,rgba(96,197,179,.35),transparent 50%);}
  .hero-in{max-width:1080px;margin:0 auto;padding:84px 28px 70px;}
  .hero .eyebrow{color:var(--teal);}
  .hero h1{font-size:50px;font-weight:800;letter-spacing:-1.6px;line-height:1.05;margin-bottom:16px;max-width:880px;}
  .hero h1 .hl{background:linear-gradient(180deg,transparent 60%,var(--accent) 60% 92%,transparent 92%);padding:0 2px;}
  .hero p{font-size:18px;color:rgba(255,255,255,.82);max-width:640px;margin-bottom:26px;}
  .hero-facts{display:flex;flex-wrap:wrap;gap:10px;}
  .hf{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);border-radius:6px;padding:9px 14px;font-size:13.5px;font-weight:700;}.hf b{color:var(--yellow);}
  .iq-grid{display:grid;grid-template-columns:1.3fr 1fr;gap:40px;align-items:start;}
  .iq-card{background:#fff;border:1px solid var(--line);border-radius:8px;padding:26px 28px;}
  .iq-card .h{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--accent);margin-bottom:12px;}
  .iq-card ul{list-style:none;}.iq-card li{display:flex;gap:11px;align-items:flex-start;font-size:14.5px;color:var(--grey);margin-bottom:11px;}
  .iq-card li::before{content:"";width:8px;height:8px;border-radius:2px;background:var(--teal);margin-top:6px;flex-shrink:0;}
  .ap-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:0;border:1px solid var(--line);border-radius:6px;background:#fff;overflow:hidden;}
  .ap-stat{padding:22px 20px;border-right:1px solid var(--line);}.ap-stat:last-child{border-right:none;}
  .ap-stat .n{font-size:30px;font-weight:800;line-height:1;}.ap-stat .n .u{font-size:16px;color:var(--grey);}
  .ap-stat .n.pink{color:var(--accent);}.ap-stat .n.teal{color:var(--teal);}
  .ap-stat .l{font-size:12.5px;color:var(--grey);margin-top:6px;}
  .cu-mods{display:flex;flex-direction:column;gap:14px;margin-bottom:36px;}
  .cu-mod{display:grid;grid-template-columns:54px 1fr;gap:22px;align-items:start;background:#fff;border:1px solid var(--line);border-radius:6px;padding:24px 26px;}
  .cu-mod .num{font-size:30px;font-weight:800;color:var(--line);line-height:1;}
  .cu-mod.flag{border-color:var(--ink);border-left:5px solid var(--accent);}.cu-mod.flag .num{color:var(--accent);}
  .cu-mod .ti{font-size:18px;font-weight:800;margin-bottom:7px;}.cu-mod .out{font-size:14.5px;color:var(--grey);}
  .cu-band{background:var(--ink);color:#fff;border-radius:6px;padding:34px;}
  .cu-band .lab{font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:var(--yellow);font-weight:800;margin-bottom:18px;}
  .cu-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px 34px;}
  .cu-oc{display:flex;gap:12px;align-items:flex-start;font-size:15px;}
  .cu-oc .ck{width:22px;height:22px;border-radius:4px;background:var(--teal);color:var(--ink);font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px;}
  .tl-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;}
  .tl-logo{background:#fff;border:1px solid var(--line);border-radius:6px;height:84px;display:flex;align-items:center;justify-content:center;padding:18px;font-size:16px;font-weight:800;color:#444;text-align:center;}
  .adm-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
  .adm{background:#fff;border:1px solid var(--line);border-top:4px solid var(--accent);border-radius:6px;padding:20px 22px;}
  .adm:nth-child(2){border-top-color:var(--teal);}.adm:nth-child(3){border-top-color:var(--yellow);}
  .adm .h{font-size:14px;font-weight:800;margin-bottom:6px;}.adm .s{font-size:13.5px;color:var(--grey);}
  .nq-block{display:grid;grid-template-columns:auto 1fr;gap:40px;align-items:center;background:#fff;border:1px solid var(--line);border-radius:8px;padding:44px 48px;}
  .nq-photo{position:relative;width:170px;flex-shrink:0;}
  .nq-photo .ph{width:170px;height:170px;border-radius:8px;background:var(--soft);border:1px solid var(--line);display:flex;align-items:center;justify-content:center;color:#bbb;font-weight:800;font-size:13px;text-align:center;}
  .nq-photo .tag{position:absolute;left:50%;bottom:-13px;transform:translateX(-50%);background:var(--yellow);color:var(--ink);font-weight:800;font-size:11px;letter-spacing:.1em;text-transform:uppercase;padding:6px 14px;border-radius:4px;white-space:nowrap;}
  .nq-q{font-family:Georgia,serif;font-size:23px;line-height:1.45;color:#1a1a1a;margin-bottom:22px;}.nq-q .mark{color:var(--accent);font-weight:800;}
  .nq-sign{display:flex;align-items:center;gap:14px;}.nq-sign .bar{width:32px;height:3px;background:var(--accent);}
  .nq-sign .nm{font-size:16px;font-weight:800;}.nq-sign .rl{font-size:13px;color:var(--grey);margin-top:2px;}
  .inv{background:#2e2e2e;color:#fff;border-radius:10px;padding:40px 44px;display:grid;grid-template-columns:1.2fr 1fr;gap:40px;align-items:center;}
  .inv .lab{font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:var(--yellow);font-weight:800;margin-bottom:14px;}
  .inv ul{list-style:none;}.inv li{display:flex;gap:11px;align-items:flex-start;font-size:15px;margin-bottom:11px;color:rgba(255,255,255,.9);}
  .inv li::before{content:"\2713";color:var(--teal);font-weight:800;flex-shrink:0;}
  .inv-price{background:#fff;color:var(--ink);border-radius:8px;padding:30px;text-align:center;}
  .inv-price .big{font-size:40px;font-weight:800;line-height:1;}.inv-price .sub{font-size:13.5px;color:var(--grey);margin:8px 0 18px;}
  .faq-item{background:#fff;border:1px solid var(--line);border-radius:6px;padding:22px 24px;margin-bottom:12px;}
  .faq-item .q{font-size:16px;font-weight:800;margin-bottom:7px;}.faq-item .a{font-size:14.5px;color:var(--grey);}
  .fin{background:var(--ink);color:#fff;text-align:center;}
  .fin h2{font-size:34px;margin-bottom:10px;}.fin h2 .hl{color:var(--yellow);background:none;}
  .fin p{color:#bdbdbd;font-size:16px;margin-bottom:24px;}.fin .btn{background:var(--yellow);color:var(--ink);}
  @media(max-width:820px){.iq-grid,.cu-grid,.nq-block,.tl-grid,.inv{grid-template-columns:1fr;}.ap-stats,.adm-grid{grid-template-columns:1fr 1fr;}.hero h1{font-size:34px;}h2{font-size:28px;}}
</style>
</head>
<body>
<?php if ( ! $is_live ) : ?>
<div class="pvbar">🔒 <b>PREVIEW</b> · <?php echo esc_html( $C['name'] ); ?> · página de trabalho (não pública) · <b>/<?php echo esc_html( $C['slug'] ); ?>-preview/</b></div>
<?php endif; ?>

<div class="hero"><div class="hero-in">
  <div class="eyebrow"><?php echo esc_html( $C['hero']['eyebrow'] ); ?></div>
  <h1><?php echo wp_kses_post( $C['hero']['h1_html'] ); ?></h1>
  <p><?php echo esc_html( $C['hero']['sub'] ); ?></p>
  <div class="hero-facts">
    <?php foreach ( $C['hero']['facts'] as $f ) : ?><span class="hf"><b><?php echo esc_html( $f[0] ); ?></b> <?php echo esc_html( $f[1] ); ?></span><?php endforeach; ?>
  </div>
</div></div>

<section class="sec">
  <div class="eyebrow"><?php echo esc_html( $C['whatis']['eyebrow'] ); ?></div>
  <h2><?php echo wp_kses_post( $C['whatis']['h2_html'] ); ?></h2>
  <div class="iq-grid">
    <div><?php foreach ( $C['whatis']['paras'] as $p ) : ?><p class="lead" style="margin-bottom:18px;"><?php echo esc_html( $p ); ?></p><?php endforeach; ?></div>
    <div class="iq-card"><div class="h">Para quem é</div><ul><?php foreach ( $C['whatis']['para_quem'] as $li ) : ?><li><?php echo esc_html( $li ); ?></li><?php endforeach; ?></ul></div>
  </div>
</section>

<section class="sec alt">
  <div class="eyebrow">Em números</div>
  <h2>Formação aplicada, <span class="hl">orientada para produção.</span></h2>
  <div class="ap-stats" style="margin-top:24px;">
    <?php foreach ( $C['stats'] as $s ) : ?><div class="ap-stat"><div class="n <?php echo esc_attr( $s[3] ); ?>"><?php echo esc_html( $s[0] ); ?><?php echo $s[1] ? '<span class="u">' . esc_html( $s[1] ) . '</span>' : ''; ?></div><div class="l"><?php echo esc_html( $s[2] ); ?></div></div><?php endforeach; ?>
  </div>
</section>

<section class="sec">
  <div class="eyebrow"><?php echo esc_html( $C['curriculum']['eyebrow'] ); ?></div>
  <h2><?php echo wp_kses_post( $C['curriculum']['h2_html'] ); ?></h2>
  <p class="lead"><?php echo esc_html( $C['curriculum']['lead'] ); ?></p>
  <div class="cu-mods">
    <?php foreach ( $C['curriculum']['modules'] as $m ) : ?><div class="cu-mod<?php echo $m[3] ? ' flag' : ''; ?>"><div class="num"><?php echo esc_html( $m[0] ); ?></div><div><div class="ti"><?php echo esc_html( $m[1] ); ?></div><div class="out"><?php echo esc_html( $m[2] ); ?></div></div></div><?php endforeach; ?>
  </div>
  <div class="cu-band">
    <div class="lab">No fim do bootcamp, vais conseguir</div>
    <div class="cu-grid"><?php foreach ( $C['curriculum']['outcomes'] as $o ) : ?><div class="cu-oc"><div class="ck">✓</div><div><?php echo esc_html( $o ); ?></div></div><?php endforeach; ?></div>
  </div>
</section>

<?php if ( ! empty( $C['stack'] ) ) : ?>
<section class="sec alt">
  <div class="eyebrow">O stack</div>
  <h2>As ferramentas que vais usar para <span class="hl">construir.</span></h2>
  <p class="lead">Um stack técnico orientado para a implementação real.</p>
  <div class="tl-grid"><?php foreach ( $C['stack'] as $t ) : ?><div class="tl-logo"><?php echo esc_html( $t ); ?></div><?php endforeach; ?></div>
</section>
<?php endif; ?>

<section class="sec">
  <div class="eyebrow">Admissão</div>
  <h2>Para quem já tem <span class="hl">base técnica.</span></h2>
  <p class="lead">É um bootcamp avançado. Para tirares o máximo partido, precisas de cumprir os seguintes requisitos.</p>
  <div class="adm-grid"><?php foreach ( $C['admission'] as $a ) : ?><div class="adm"><div class="h"><?php echo esc_html( $a[0] ); ?></div><div class="s"><?php echo esc_html( $a[1] ); ?></div></div><?php endforeach; ?></div>
</section>

<section class="sec alt">
  <div class="eyebrow" style="text-align:center;">Quem te vai formar</div>
  <div class="nq-block">
    <div class="nq-photo"><div class="ph"><?php echo $has_instructor ? esc_html( $C['instructor']['name'] ) : 'FOTO<br>FORMADOR'; ?></div><div class="tag">O teu formador</div></div>
    <div>
      <p class="nq-q"><span class="mark">&ldquo;</span><?php echo esc_html( $C['instructor']['quote'] ); ?><span class="mark">&rdquo;</span></p>
      <div class="nq-sign"><div class="bar"></div><div><div class="nm"><?php echo $has_instructor ? esc_html( $C['instructor']['name'] ) : '[A CONFIRMAR] <span class="tba">nome do formador</span>'; ?></div><div class="rl"><?php echo esc_html( $C['instructor']['role'] ); ?></div></div></div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="eyebrow">Investimento</div>
  <h2><?php echo esc_html( $C['hero']['facts'][0][0] ); ?> de formação prática, projeto final e <span class="hl">certificação DGERT.</span></h2>
  <div class="inv" style="margin-top:24px;">
    <div><div class="lab">O que está incluído</div><ul><?php foreach ( $C['investment']['included'] as $i ) : ?><li><?php echo esc_html( $i ); ?></li><?php endforeach; ?></ul></div>
    <div class="inv-price"><div class="big"><?php echo strpos( $C['investment']['price'], 'confirmar' ) !== false ? '<span class="tba">A confirmar</span>' : esc_html( $C['investment']['price'] ); ?></div><div class="sub"><?php echo esc_html( $C['investment']['price_sub'] ); ?></div><a href="#falaConnosco" class="btn">Falar com a EDIT.</a></div>
  </div>
</section>

<?php if ( ! empty( $C['faq'] ) ) : ?>
<section class="sec alt">
  <div class="eyebrow">Perguntas frequentes</div>
  <h2>Ainda com <span class="hl">dúvidas?</span></h2>
  <div style="margin-top:24px;"><?php foreach ( $C['faq'] as $f ) : ?><div class="faq-item"><div class="q"><?php echo esc_html( $f[0] ); ?></div><div class="a"><?php echo esc_html( $f[1] ); ?></div></div><?php endforeach; ?></div>
</section>
<?php endif; ?>

<section class="sec fin" id="falaConnosco">
  <h2>A próxima turma arranca em <span class="hl"><?php echo esc_html( $C['cta']['date'] ); ?>.</span></h2>
  <p><?php echo esc_html( $C['cta']['line'] ); ?></p>
  <a href="#" class="btn">Quero o meu lugar →</a>
</section>

</body>
</html>
