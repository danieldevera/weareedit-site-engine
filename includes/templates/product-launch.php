<?php
/**
 * Generic product-launch landing template (rich, AAI-parity, config-driven).
 * Expects $C (product config) in scope with $C['_mode'] = 'preview' | 'live'.
 *
 * Renders a full self-contained page. The body sections are wrapped in
 * <!--PRD:SECTIONS-START--> / -END--> markers so EDIT_Product_Launch can splice
 * just the sections into a real proxied header/footer (AAI/GAMA mechanism). If
 * the proxy fails, this whole page is served as-is (the safe fallback) — it can
 * never white-screen. SEO/GEO head comes from EDIT_Product_Launch::seo_head().
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$mode    = $C['_mode'] ?? 'preview';
$is_live = ( $mode === 'live' );
$accentMap = [ 'pink' => '#f92869', 'teal' => '#60c5b3', 'blue' => '#0090eb', 'yellow' => '#ffdd06' ];
$accent  = $accentMap[ $C['format_color'] ?? 'pink' ] ?? '#f92869';
$has_instructor = ! empty( $C['instructor']['name'] ) && strpos( $C['instructor']['name'], '[A CONFIRMAR]' ) === false;
$is_tba = function ( $v ) { return strpos( (string) $v, '[A CONFIRMAR]' ) !== false || strpos( (string) $v, 'confirmar' ) !== false; };
$tba = '<span class="tba">a confirmar</span>';
$assets      = 'https://weareedit.io/wp-content/plugins/weareedit-site-engine/assets/';
$fonts       = $assets . 'fonts/';
$hero_vid    = $C['hero']['video']  ?? $assets . 'augment/augment-hero-loop-v5.mp4';
$hero_poster = $C['hero']['poster'] ?? '';
$dgert_img   = $assets . 'augment/dgert-branco.png';
?><!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php echo EDIT_Product_Launch::seo_head( $C, $is_live ); ?>
<style>
  :root{--ink:#111;--grey:#5b5b5b;--line:#e8e8e8;--soft:#fafafa;--yellow:#ffdd06;--pink:#f92869;--teal:#60c5b3;--purple:#7a38b4;--accent:<?php echo $accent; ?>;}
  *{box-sizing:border-box;margin:0;padding:0;}
  .prd body,body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;background:#fff;color:var(--ink);line-height:1.55;-webkit-font-smoothing:antialiased;}
  #prd .sec{max-width:1080px;margin:0 auto;padding:64px 28px;}
  #prd .eyebrow{font-size:12px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:var(--accent);margin-bottom:12px;}
  #prd h2{font-size:34px;font-weight:800;letter-spacing:-1px;line-height:1.12;margin-bottom:10px;}
  #prd h2 .hl{background:linear-gradient(180deg,transparent 62%,var(--yellow) 62% 92%,transparent 92%);}
  #prd .lead{font-size:16.5px;color:var(--grey);max-width:680px;margin-bottom:34px;}
  #prd .btn{background:var(--ink);color:var(--yellow);font-weight:800;font-size:15px;border:none;border-radius:6px;padding:15px 28px;text-decoration:none;display:inline-block;cursor:pointer;}
  #prd .alt{background:var(--soft);}
  #prd .tba{background:var(--yellow);color:var(--ink);font-weight:800;font-size:11px;letter-spacing:.03em;border-radius:4px;padding:2px 7px;text-transform:uppercase;white-space:nowrap;}
  .prd-pvbar{background:var(--ink);color:#fff;font-size:12.5px;text-align:center;padding:9px 16px;}.prd-pvbar b{color:var(--yellow);}
  .prd-fhdr{display:flex;align-items:center;justify-content:space-between;padding:16px 28px;border-bottom:1px solid var(--line);}
  .prd-fhdr .lg{font-weight:800;letter-spacing:.18em;font-size:18px;}.prd-fhdr .lg span{color:var(--accent);}
  /* hero — AAI-parity: neon-waves video, full-bleed, floating info card */
  @font-face{font-family:'SctoGroteskA';font-weight:500;font-style:normal;font-display:swap;src:url('<?php echo $fonts; ?>SctoGroteskA-Medium.woff2') format('woff2');}
  @font-face{font-family:'SctoGroteskA';font-weight:700;font-style:normal;font-display:swap;src:url('<?php echo $fonts; ?>SctoGroteskA-Bold.woff2') format('woff2');}
  #prd .hero{position:relative;overflow:hidden;background:#0a0a0a;color:#fff;width:100vw;max-width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);margin-top:-110px;padding:168px 0 256px;min-height:78vh;display:flex;align-items:flex-start;font-family:'SctoGroteskA',-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;}
  #prd .hero .vid{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;transform:scale(1.22);z-index:0;pointer-events:none;filter:hue-rotate(55deg) saturate(1.15) brightness(.82);}
  #prd .hero .veil{position:absolute;inset:0;z-index:1;background:linear-gradient(180deg,rgba(10,10,10,.55) 0%,rgba(10,10,10,.42) 45%,rgba(10,10,10,.34) 100%);}
  #prd .hero-in{position:relative;z-index:2;max-width:1080px;margin:0 auto;padding:0 28px;width:100%;}
  #prd .hero .dgert{display:inline-flex;align-items:center;gap:13px;text-decoration:none;color:#fff;margin-bottom:26px;transition:opacity .2s;}
  #prd .hero .dgert:hover{opacity:.85;}
  #prd .hero .dgert img{height:40px;width:auto;display:block;}
  #prd .hero .dgert .t{font-size:15px;font-weight:600;letter-spacing:-.01em;}
  #prd .hero .dgert .ar{font-size:13px;opacity:.7;}
  #prd .hero .eyebrow{font-size:12px;letter-spacing:.2em;color:var(--yellow);font-weight:700;text-transform:uppercase;margin-bottom:20px;}
  #prd .hero h1{font-size:clamp(48px,7.6vw,98px);font-weight:500;letter-spacing:-.02em;line-height:1.01;margin-bottom:24px;}
  #prd .hero h1 .dp{color:var(--accent);}
  #prd .hero h1 .dt{color:var(--teal);}
  #prd .hero p{font-size:clamp(18px,1.5vw,23px);line-height:1.4;color:var(--yellow);font-weight:500;max-width:780px;padding-bottom:0;}
  /* info-card — floats up over the lower neon waves (AAI treatment) */
  #prd .infocard{max-width:1024px;margin:-200px auto 0;position:relative;z-index:5;border-radius:8px;overflow:hidden;box-shadow:0 40px 80px -30px rgba(0,0,0,.7);}
  #prd .ic-dates{display:grid;grid-template-columns:repeat(5,1fr);background:#1c1c1c;color:#fff;}
  #prd .ic-col{padding:20px 22px;border-right:1px solid rgba(255,255,255,.08);}#prd .ic-col:last-child{border-right:none;}
  #prd .ic-col label{display:block;font-size:10.5px;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.55);margin-bottom:7px;}
  #prd .ic-col .v{font-size:15px;font-weight:700;}
  #prd .ic-bar{display:flex;align-items:stretch;background:#fff;}
  #prd .ic-tag{flex:1;display:flex;align-items:center;justify-content:center;font-size:13.5px;font-weight:700;color:var(--ink);border-right:1px solid var(--line);}
  #prd .ic-cta{flex:1;display:flex;align-items:center;justify-content:center;background:var(--accent);color:#fff;font-weight:800;font-size:14.5px;text-decoration:none;padding:18px;}
  /* persona cards */
  #prd .pz-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-bottom:18px;}
  #prd .pz{background:#fff;border:1px solid var(--line);border-top:4px solid var(--accent);border-radius:6px;padding:22px 24px;}
  #prd .pz:nth-child(2){border-top-color:var(--teal);}#prd .pz:nth-child(3){border-top-color:var(--yellow);}
  #prd .pz .h{font-size:15.5px;font-weight:800;margin-bottom:7px;}#prd .pz .s{font-size:14px;color:var(--grey);}
  #prd .pz-foot{font-size:14px;font-weight:700;}
  /* instructor */
  #prd .nq{display:grid;grid-template-columns:auto 1fr;gap:40px;align-items:center;background:#fff;border:1px solid var(--line);border-radius:8px;padding:42px 46px;}
  #prd .nq-ph{position:relative;width:180px;height:180px;border-radius:8px;background:var(--soft);border:1px solid var(--line);display:flex;align-items:center;justify-content:center;color:#bbb;font-weight:800;font-size:12px;text-align:center;flex-shrink:0;}
  #prd .nq-ph .tag{position:absolute;left:50%;bottom:-12px;transform:translateX(-50%);background:var(--yellow);color:var(--ink);font-weight:800;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;padding:6px 13px;border-radius:4px;white-space:nowrap;}
  #prd .nq-q{font-family:Georgia,serif;font-size:23px;line-height:1.45;color:#1a1a1a;margin-bottom:20px;}#prd .nq-q .mk{color:var(--accent);font-weight:800;}
  #prd .nq-sign{display:flex;align-items:center;gap:13px;}#prd .nq-sign .bar{width:30px;height:3px;background:var(--accent);}
  #prd .nq-sign .nm{font-size:15.5px;font-weight:800;}#prd .nq-sign .rl{font-size:13px;color:var(--grey);}
  /* arsenal */
  #prd .ar-grid{display:grid;grid-template-columns:1fr auto 1fr auto 1fr;gap:14px;align-items:stretch;}
  #prd .ar-col{background:#fff;border:1px solid var(--line);border-radius:8px;padding:20px;}
  #prd .ar-col .lab{font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);margin-bottom:14px;}
  #prd .ar-tools{display:flex;flex-direction:column;gap:10px;}
  #prd .ar-tool{background:var(--soft);border:1px solid var(--line);border-radius:6px;padding:12px 14px;font-weight:800;font-size:14px;text-align:center;}
  #prd .ar-arrow{display:flex;align-items:center;color:var(--line);font-size:24px;font-weight:800;}
  /* modules */
  #prd .mod{background:#fff;border:1px solid var(--line);border-radius:8px;padding:24px 26px;margin-bottom:12px;}
  #prd .mod.flag{border-color:var(--ink);border-left:5px solid var(--accent);}
  #prd .mod-top{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:14px;}
  #prd .mod-ti{display:flex;align-items:baseline;gap:16px;}
  #prd .mod-ti .num{font-size:26px;font-weight:800;color:var(--accent);line-height:1;}
  #prd .mod-ti .t{font-size:17.5px;font-weight:800;}
  #prd .mod-lvl{font-size:10.5px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#fff;background:var(--accent);border-radius:4px;padding:4px 10px;white-space:nowrap;}
  #prd .mod-b{display:grid;grid-template-columns:1fr 1fr;gap:7px 28px;}
  #prd .mod-b div{display:flex;gap:9px;font-size:14px;color:var(--grey);}#prd .mod-b div::before{content:"›";color:var(--accent);font-weight:800;}
  #prd .mod-proj{margin-top:12px;font-size:13.5px;font-weight:800;color:var(--ink);}#prd .mod-proj span{color:var(--accent);}
  #prd .oc-band{background:var(--ink);color:#fff;border-radius:8px;padding:34px;margin-top:24px;}
  #prd .oc-band .lab{font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--yellow);font-weight:800;margin-bottom:18px;}
  #prd .oc-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 32px;}
  #prd .oc{display:flex;gap:11px;font-size:14.5px;}#prd .oc .ck{width:21px;height:21px;border-radius:4px;background:var(--teal);color:var(--ink);font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;}
  /* outcome cards */
  #prd .rc{background:#fff;border:1px solid var(--line);border-radius:8px;padding:24px 26px;margin-bottom:12px;position:relative;}
  #prd .rc .corner{position:absolute;top:0;right:24px;transform:translateY(-50%);background:var(--accent);color:#fff;font-weight:800;font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;padding:5px 11px;border-radius:4px;}
  #prd .rc.post .corner{background:var(--ink);color:var(--yellow);}
  #prd .rc .h{font-size:16.5px;font-weight:800;margin-bottom:6px;}#prd .rc .s{font-size:14px;color:var(--grey);}
  /* empresas banner */
  #prd .emp{background:var(--ink);color:#fff;border-radius:8px;padding:26px 32px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;}
  #prd .emp .t{font-size:17px;font-weight:800;}#prd .emp .t em{font-style:italic;color:var(--yellow);}
  #prd .emp .btn{background:var(--yellow);color:var(--ink);}
  /* investment */
  #prd .inv{background:#2e2e2e;color:#fff;border-radius:10px;padding:38px 42px;display:grid;grid-template-columns:1.25fr 1fr;gap:38px;align-items:center;}
  #prd .inv .lab{font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--yellow);font-weight:800;margin-bottom:14px;}
  #prd .inv ul{list-style:none;}#prd .inv li{display:flex;gap:11px;font-size:14.5px;margin-bottom:11px;color:rgba(255,255,255,.9);}#prd .inv li::before{content:"\2713";color:var(--teal);font-weight:800;}
  #prd .inv-p{background:#fff;color:var(--ink);border-radius:8px;padding:28px;text-align:center;}
  #prd .inv-p .big{font-size:38px;font-weight:800;line-height:1;}#prd .inv-p .sub{font-size:13px;color:var(--grey);margin:8px 0 16px;}#prd .inv-p .btn{background:var(--yellow);color:var(--ink);}
  /* faq */
  #prd .faq{background:#fff;border:1px solid var(--line);border-radius:6px;margin-bottom:10px;}
  #prd .faq summary{cursor:pointer;list-style:none;padding:18px 24px;font-size:15.5px;font-weight:800;display:flex;justify-content:space-between;gap:14px;}
  #prd .faq summary::-webkit-details-marker{display:none;}
  #prd .faq summary::after{content:"+";color:var(--accent);font-weight:800;}#prd .faq[open] summary::after{content:"\2013";}
  #prd .faq .a{padding:0 24px 18px;font-size:14.5px;color:var(--grey);}
  /* francisco */
  #prd .fc{display:grid;grid-template-columns:auto 1fr;gap:34px;align-items:center;background:#fff;border:1px solid var(--line);border-radius:8px;padding:32px 36px;}
  #prd .fc-ph{position:relative;width:170px;height:170px;border-radius:8px;background:#1c1c1c;color:#888;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;}
  #prd .fc-ph .tag{position:absolute;left:50%;bottom:-12px;transform:translateX(-50%);background:var(--yellow);color:var(--ink);font-weight:800;font-size:10px;letter-spacing:.06em;text-transform:uppercase;padding:5px 12px;border-radius:4px;white-space:nowrap;}
  #prd .fc h2{margin-bottom:8px;}#prd .fc .who{font-weight:800;font-size:15px;margin-top:8px;}#prd .fc .role{font-size:13px;color:var(--grey);}
  #prd .fc-actions{display:flex;gap:10px;margin-top:16px;}#prd .fc-actions .wa{background:#25d366;color:#fff;border-radius:6px;padding:13px 16px;font-weight:800;text-decoration:none;}
  /* final CTA */
  #prd .fin{background:var(--ink);color:#fff;text-align:center;}
  #prd .fin h2{font-size:33px;margin-bottom:10px;}#prd .fin h2 .mp{background:linear-gradient(180deg,transparent 60%,var(--accent) 60% 92%,transparent 92%);}#prd .fin h2 .hl{color:var(--yellow);background:none;}
  #prd .fin p{color:#bdbdbd;font-size:16px;margin-bottom:24px;}#prd .fin .btn{background:var(--yellow);color:var(--ink);}
  .prd-ffoot{padding:40px 28px;border-top:1px solid var(--line);text-align:center;font-size:13px;color:var(--grey);}
  @media(max-width:860px){
    #prd .pz-grid,#prd .oc-grid,#prd .nq,#prd .inv,#prd .mod-b,#prd .fc{grid-template-columns:1fr;}
    #prd .ar-grid{grid-template-columns:1fr;}#prd .ar-arrow{display:none;}
    #prd .ic-dates{grid-template-columns:1fr 1fr;}#prd .hero h1{font-size:42px;}#prd h2{font-size:27px;}
    #prd .hero{padding:128px 0 168px;margin-top:-90px;min-height:0;}#prd .infocard{margin:-130px auto 0;}
  }
</style>
</head>
<body>
<div id="prd">
<?php if ( ! $is_live ) : ?><div class="prd-pvbar">🔒 <b>PREVIEW</b> · <?php echo esc_html( $C['name'] ); ?> · não pública · <b>/<?php echo esc_html( $C['slug'] ); ?>-preview/</b></div><?php endif; ?>
<header class="prd-fhdr"><div class="lg">EDIT<span>.</span></div><a class="btn" href="#inscrever">Fala connosco</a></header>

<!--PRD:SECTIONS-START-->
<section class="hero">
  <video class="vid" autoplay muted loop playsinline preload="auto"<?php echo $hero_poster ? ' poster="' . esc_url( $hero_poster ) . '"' : ''; ?>><source src="<?php echo esc_url( $hero_vid ); ?>" type="video/mp4"></video>
  <div class="veil"></div>
  <div class="hero-in">
    <a class="dgert" href="https://www.dgert.gov.pt/entidades-formadoras-certificadas" target="_blank" rel="noopener"><img src="<?php echo esc_url( $dgert_img ); ?>" alt="DGERT — Entidade Formadora Certificada"><span class="t"><?php echo esc_html( $C['hero']['badge'] ?? 'Entidade Formadora Certificada' ); ?></span><span class="ar">&#8599;</span></a>
    <div class="eyebrow"><?php echo esc_html( $C['hero']['eyebrow'] ); ?></div>
    <h1><?php echo wp_kses_post( $C['hero']['h1_html'] ); ?></h1>
    <p><?php echo esc_html( $C['hero']['sub'] ); ?></p>
  </div>
</section>
<?php if ( ! empty( $C['info_card'] ) ) : $ic = $C['info_card']; ?>
<div class="infocard">
  <div class="ic-dates"><?php foreach ( $ic['cols'] as $col ) : ?><div class="ic-col"><label><?php echo esc_html( $col[0] ); ?></label><div class="v"><?php echo $is_tba( $col[1] ) ? $tba : esc_html( $col[1] ); ?></div></div><?php endforeach; ?></div>
  <div class="ic-bar"><?php foreach ( $ic['tags'] as $t ) : ?><span class="ic-tag"><?php echo esc_html( $t ); ?></span><?php endforeach; ?><a class="ic-cta" href="#inscrever"><?php echo esc_html( $ic['cta'] ); ?></a></div>
</div>
<?php endif; ?>

<section class="sec">
  <div class="eyebrow"><?php echo esc_html( $C['whatis']['eyebrow'] ); ?></div>
  <h2><?php echo wp_kses_post( $C['whatis']['h2_html'] ); ?></h2>
  <?php foreach ( $C['whatis']['paras'] as $p ) : ?><p class="lead"><?php echo esc_html( $p ); ?></p><?php endforeach; ?>
  <?php if ( ! empty( $C['personas'] ) ) : ?>
  <div class="pz-grid"><?php foreach ( $C['personas'] as $pz ) : ?><div class="pz"><div class="h"><?php echo esc_html( $pz[0] ); ?></div><div class="s"><?php echo esc_html( $pz[1] ); ?></div></div><?php endforeach; ?></div>
  <?php if ( ! empty( $C['persona_foot'] ) ) : ?><p class="pz-foot"><?php echo esc_html( $C['persona_foot'] ); ?></p><?php endif; ?>
  <?php endif; ?>
</section>

<?php if ( ! empty( $C['arsenal'] ) ) : $ar = $C['arsenal']; ?>
<section class="sec alt">
  <div class="eyebrow"><?php echo esc_html( $ar['eyebrow'] ); ?></div>
  <h2><?php echo wp_kses_post( $ar['h2_html'] ); ?></h2>
  <p class="lead"><?php echo esc_html( $ar['lead'] ); ?></p>
  <div class="ar-grid"><?php $gi = 0; $gn = count( $ar['groups'] ); foreach ( $ar['groups'] as $g ) : $gi++; ?>
    <div class="ar-col"><div class="lab"><?php echo esc_html( $g[0] ); ?></div><div class="ar-tools"><?php foreach ( $g[1] as $tool ) : ?><div class="ar-tool"><?php echo esc_html( $tool ); ?></div><?php endforeach; ?></div></div>
    <?php if ( $gi < $gn ) : ?><div class="ar-arrow">→</div><?php endif; ?>
  <?php endforeach; ?></div>
</section>
<?php endif; ?>

<section class="sec">
  <div class="eyebrow"><?php echo esc_html( $C['curriculum']['eyebrow'] ); ?></div>
  <h2><?php echo wp_kses_post( $C['curriculum']['h2_html'] ); ?></h2>
  <p class="lead"><?php echo esc_html( $C['curriculum']['lead'] ); ?></p>
  <?php foreach ( $C['curriculum']['modules'] as $m ) : ?>
  <div class="mod<?php echo ! empty( $m['flag'] ) ? ' flag' : ''; ?>">
    <div class="mod-top"><div class="mod-ti"><span class="num"><?php echo esc_html( $m['num'] ); ?></span><span class="t"><?php echo esc_html( $m['title'] ); ?></span></div><?php if ( ! empty( $m['level'] ) ) : ?><span class="mod-lvl"><?php echo esc_html( $m['level'] ); ?></span><?php endif; ?></div>
    <div class="mod-b"><?php foreach ( $m['bullets'] as $b ) : ?><div><?php echo esc_html( $b ); ?></div><?php endforeach; ?></div>
    <?php if ( ! empty( $m['project'] ) ) : ?><div class="mod-proj"><span>Projeto:</span> <?php echo esc_html( $m['project'] ); ?></div><?php endif; ?>
  </div>
  <?php endforeach; ?>
  <div class="oc-band"><div class="lab">No fim do bootcamp, vais conseguir</div><div class="oc-grid"><?php foreach ( $C['curriculum']['outcomes'] as $o ) : ?><div class="oc"><div class="ck">✓</div><div><?php echo esc_html( $o ); ?></div></div><?php endforeach; ?></div></div>
</section>

<?php if ( ! empty( $C['outcomes_cards'] ) ) : ?>
<section class="sec alt">
  <div class="eyebrow">O que levas contigo</div>
  <h2>Não sais com apontamentos. <span class="hl">Sais com algo construído.</span></h2>
  <div style="margin-top:30px;"><?php foreach ( $C['outcomes_cards'] as $rc ) : $post = ( $rc[0] !== 'RESULTADO' ); ?><div class="rc<?php echo $post ? ' post' : ''; ?>"><div class="corner"><?php echo esc_html( $rc[0] ); ?></div><div class="h"><?php echo esc_html( $rc[1] ); ?></div><div class="s"><?php echo esc_html( $rc[2] ); ?></div></div><?php endforeach; ?></div>
</section>
<?php endif; ?>

<section class="sec">
  <div class="eyebrow" style="text-align:center;">Quem te vai formar</div>
  <div class="nq">
    <div class="nq-ph"><?php echo $has_instructor ? esc_html( $C['instructor']['name'] ) : 'FOTO<br>FORMADOR'; ?><div class="tag">O teu formador</div></div>
    <div><p class="nq-q"><span class="mk">&ldquo;</span><?php echo esc_html( $C['instructor']['quote'] ); ?><span class="mk">&rdquo;</span></p>
      <div class="nq-sign"><div class="bar"></div><div><div class="nm"><?php echo $has_instructor ? esc_html( $C['instructor']['name'] ) : '[A CONFIRMAR] <span class="tba">nome do formador</span>'; ?></div><div class="rl"><?php echo esc_html( $C['instructor']['role'] ); ?></div></div></div>
    </div>
  </div>
</section>

<section class="sec alt">
  <div class="emp"><div class="t">Faz esta formação com a <em>tua equipa</em>.</div><a class="btn" href="https://weareedit.io/formacao-digital-para-empresas/">Conhecer a EDIT. para Empresas →</a></div>
</section>

<section class="sec" id="inscrever">
  <div class="eyebrow">Investimento</div>
  <h2>27 horas de implementação, projeto final e <span class="hl">certificação DGERT.</span></h2>
  <div class="inv" style="margin-top:24px;">
    <div><div class="lab">Incluído na inscrição</div><ul><?php foreach ( $C['investment']['included'] as $i ) : ?><li><?php echo esc_html( $i ); ?></li><?php endforeach; ?></ul></div>
    <div class="inv-p"><div class="big"><?php echo $is_tba( $C['investment']['price'] ) ? $tba : esc_html( $C['investment']['price'] ); ?></div><div class="sub"><?php echo esc_html( $C['investment']['price_sub'] ); ?></div><a href="#francisco" class="btn">Quero inscrever-me →</a></div>
  </div>
</section>

<?php if ( ! empty( $C['faq'] ) ) : ?>
<section class="sec alt">
  <div class="eyebrow">Perguntas frequentes</div>
  <h2>Ainda com <span class="hl">dúvidas?</span></h2>
  <div style="margin-top:24px;"><?php foreach ( $C['faq'] as $f ) : ?><details class="faq"><summary><?php echo esc_html( $f[0] ); ?></summary><div class="a"><?php echo esc_html( $f[1] ); ?></div></details><?php endforeach; ?></div>
</section>
<?php endif; ?>

<section class="sec" id="francisco">
  <div class="eyebrow">Fala connosco</div>
  <div class="fc">
    <div class="fc-ph">FOTO<div class="tag">Head of Admissions</div></div>
    <div><h2>Ajudamos-te a escolher o <span class="hl">curso certo.</span></h2><p class="lead" style="margin-bottom:0;">Falas diretamente com quem trata das inscrições. Ajudamos-te a perceber se este bootcamp é o caminho certo para ti, sem compromisso.</p><div class="who">Francisco Freitas</div><div class="role">Head of Admissions</div><div class="fc-actions"><a class="btn" href="#">Enviar mensagem</a><a class="wa" href="https://wa.me/351936508449">WhatsApp</a></div></div>
  </div>
</section>

<section class="sec fin">
  <h2><span class="mp">A próxima turma</span> <span class="hl">arranca em <?php echo esc_html( $C['cta']['date'] ); ?>.</span></h2>
  <p><?php echo esc_html( $C['cta']['line'] ); ?></p>
  <a href="#inscrever" class="btn">Quero o meu lugar →</a>
</section>
<!--PRD:SECTIONS-END-->

<footer class="prd-ffoot">EDIT. — Disruptive Digital Education · Entidade Formadora Certificada DGERT</footer>
</div>
</body>
</html>
