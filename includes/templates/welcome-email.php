<?php
/**
 * Welcome email template — rendered daily by EDIT_Newsletter_Picks and
 * PUT to Brevo via the v3 API. Mirrors the locked source HTML at
 * /Users/danieldevera/Downloads/edit-newsletter-2026/_welcome-email-locked.html
 *
 * Variables injected by the renderer:
 *   $picks_data  array  — 3 entries (workshop / bootcamp / curso) from
 *                         EDIT_Newsletter_Picks::get_current_picks()
 *   $issue_date  string — Today in PT-PT (e.g. "1 Junho 2026")
 *
 * Layout rule: cards alternate photo-LEFT (even idx) → photo-RIGHT (odd
 * idx) → photo-LEFT. Typology drives the accent colour, not position.
 *
 * @since 1.5.189
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$picks_data = isset( $picks_data ) && is_array( $picks_data ) ? $picks_data : [];
$issue_date = isset( $issue_date ) ? (string) $issue_date : '';
$copy       = isset( $copy ) && is_array( $copy ) ? $copy : [];

// Apply the pink-dot accent to a string that ends in ".".
$accent_trailing_dot = static function ( string $text ): string {
    if ( $text === '' ) return $text;
    if ( substr( $text, -1 ) === '.' ) {
        $base = substr( $text, 0, -1 );
        return esc_html( $base ) . '<span style="color:#f92869;">.</span>';
    }
    return esc_html( $text );
};

/**
 * Render one product card. Position 0/2 = photo-LEFT, 1 = photo-RIGHT.
 * Returns HTML string for the card's wrapping <table>.
 */
$render_card = static function ( array $pick, int $idx ) {
    $is_last       = $idx === 2;
    $photo_on_left = ( $idx % 2 === 0 );
    $color         = $pick['color'] ?? '#0a0a0a';
    $course_url    = esc_url( $pick['url'] ?? '#' );
    $tutor_url     = esc_url( $pick['tutor_url'] ?? '#' );
    $tutor_photo   = esc_url( $pick['tutor_photo'] ?? '' );
    $tutor_name    = esc_html( $pick['tutor_name'] ?? '' );
    $tutor_role    = esc_html( $pick['tutor_role'] ?? '' );
    $typo_label    = esc_html( $pick['typology_label'] ?? '' );
    $date_label    = esc_html( $pick['date_label'] ?? '' );
    $title         = esc_html( $pick['title'] ?? '' );
    $description   = esc_html( $pick['description'] ?? '' );
    $tutor_eyebrow = stripos( ( $pick['tutor_role'] ?? '' ), 'tutora' ) !== false ? 'Tutora' : 'Tutor';

    $bottom_padding = $is_last ? '56px' : '32px';

    $img_td = '<td valign="top" class="row-cell img-cell" width="48%" style="width:48%;padding-right:24px;">'
        . '<a href="' . $course_url . '" style="display:block;text-decoration:none;border:0;">'
        . '<img class="row-img-md" src="' . esc_url( $tutor_photo ) . '" alt="' . $tutor_name . '" width="280" style="display:block;width:100%;max-width:280px;height:280px;border-radius:4px;object-fit:cover;border:0;">'
        . '</a></td>';
    $img_td_right = '<td valign="top" class="row-cell img-cell" width="48%" style="width:48%;padding:0;">'
        . '<a href="' . $course_url . '" style="display:block;text-decoration:none;border:0;">'
        . '<img class="row-img-md" src="' . esc_url( $tutor_photo ) . '" alt="' . $tutor_name . '" width="280" style="display:block;width:100%;max-width:280px;height:280px;border-radius:4px;object-fit:cover;border:0;">'
        . '</a></td>';

    $text_td_left = '<td valign="middle" class="row-cell text-cell" width="52%" style="width:52%;padding:0;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;color:#0a0a0a;">'
        . '<div style="font-size:11px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:' . esc_attr( $color ) . ';margin-bottom:10px;">' . $typo_label . ( $date_label !== '' ? ' &middot; ' . $date_label : '' ) . '</div>'
        . '<h3 class="title-md" style="margin:0 0 12px 0;font-size:24px;line-height:1.18;font-weight:700;letter-spacing:-0.015em;color:#0a0a0a;"><a href="' . $course_url . '" style="color:#0a0a0a;text-decoration:none;">' . $title . '</a></h3>'
        . '<p style="margin:0 0 16px 0;font-size:14px;line-height:1.45;color:#444444;">' . $description . '</p>'
        . ( $tutor_name !== '' ? '<div style="font-size:10px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:' . esc_attr( $color ) . ';margin-bottom:4px;">' . $tutor_eyebrow . '</div>'
            . '<div style="font-size:14px;font-weight:700;color:#0a0a0a;line-height:1.2;"><a href="' . $tutor_url . '" style="color:#0a0a0a;text-decoration:underline;text-decoration-color:' . esc_attr( $color ) . ';text-underline-offset:2px;">' . $tutor_name . '</a></div>'
            . '<div style="font-size:12px;color:#717171;line-height:1.3;margin-bottom:20px;">' . $tutor_role . '</div>' : '' )
        . '<a href="' . $course_url . '" style="display:inline-block;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:' . esc_attr( $color ) . ';text-decoration:underline;text-decoration-color:' . esc_attr( $color ) . ';text-underline-offset:4px;text-decoration-thickness:2px;">Ver programa &rsaquo;</a>'
        . '</td>';

    $text_td_right = '<td valign="middle" class="row-cell text-cell" width="52%" style="width:52%;padding-right:24px;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;color:#0a0a0a;">'
        . '<div style="font-size:11px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:' . esc_attr( $color ) . ';margin-bottom:10px;">' . $typo_label . ( $date_label !== '' ? ' &middot; ' . $date_label : '' ) . '</div>'
        . '<h3 class="title-md" style="margin:0 0 12px 0;font-size:24px;line-height:1.18;font-weight:700;letter-spacing:-0.015em;color:#0a0a0a;"><a href="' . $course_url . '" style="color:#0a0a0a;text-decoration:none;">' . $title . '</a></h3>'
        . '<p style="margin:0 0 16px 0;font-size:14px;line-height:1.45;color:#444444;">' . $description . '</p>'
        . ( $tutor_name !== '' ? '<div style="font-size:10px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:' . esc_attr( $color ) . ';margin-bottom:4px;">' . $tutor_eyebrow . '</div>'
            . '<div style="font-size:14px;font-weight:700;color:#0a0a0a;line-height:1.2;"><a href="' . $tutor_url . '" style="color:#0a0a0a;text-decoration:underline;text-decoration-color:' . esc_attr( $color ) . ';text-underline-offset:2px;">' . $tutor_name . '</a></div>'
            . '<div style="font-size:12px;color:#717171;line-height:1.3;margin-bottom:20px;">' . $tutor_role . '</div>' : '' )
        . '<a href="' . $course_url . '" style="display:inline-block;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:' . esc_attr( $color ) . ';text-decoration:underline;text-decoration-color:' . esc_attr( $color ) . ';text-underline-offset:4px;text-decoration-thickness:2px;">Ver programa &rsaquo;</a>'
        . '</td>';

    $row = $photo_on_left ? ( $img_td . $text_td_left ) : ( $text_td_right . $img_td_right );

    return '<table role="presentation" class="container" width="680" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:680px;max-width:680px;background-color:#ffffff;">'
        . '<tr><td class="px-md" style="padding:8px 40px ' . $bottom_padding . ' 40px;">'
        . '<table role="presentation" class="row-table" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' . $row . '</tr></table>'
        . '</td></tr></table>';
};

?><!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="color-scheme" content="light only">
  <meta name="supported-color-schemes" content="light only">
  <title>Bem-vindo à EDIT.</title>
  <style>
    body { margin:0; padding:0; background:#f5f5f5; -webkit-text-size-adjust:100%; }
    .container { width:680px; max-width:680px; }
    .px-md { padding-left:40px; padding-right:40px; }
    a { color:inherit; }
    @media only screen and (max-width: 680px) {
      .container { width:100% !important; max-width:100% !important; }
      .px-md { padding-left:24px !important; padding-right:24px !important; }
      .row-table { display: block !important; width: 100% !important; }
      .row-cell  { display: block !important; width: 100% !important; padding: 0 !important; }
      .row-cell.img-cell { padding: 0 !important; }
      .row-img-md { width: 100% !important; max-width: none !important; height: auto !important; }
      .text-cell  { padding: 18px 24px 28px !important; }
      .title-md   { font-size: 22px !important; line-height: 1.18 !important; }
      .h2-em      { font-size: 28px !important; }
      .stack-md   { display:block !important; width:100% !important; padding-right:0 !important; padding-bottom:24px !important; }
    }
  </style>
</head>
<body bgcolor="#f5f5f5" style="margin:0;padding:0;background:#f5f5f5;">
  <div style="display:none;font-size:1px;color:#f5f5f5;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">
    Bem-vindo à comunidade EDIT. — uma nota pessoal do fundador + o que está em destaque agora.
  </div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f5f5f5">
<tr><td align="center" style="padding:0;">

<!-- BRAND HEADER + ISSUE DATE -->
<table role="presentation" class="container" width="680" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:680px;max-width:680px;background-color:#ffffff;">
  <tr>
    <td class="px-md" style="padding:32px 40px 0 40px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td valign="middle" align="left">
            <a href="https://weareedit.io" style="display:inline-block;text-decoration:none;"><img src="https://weareedit.io/wp-content/uploads/2026/05/edit-extended-logo.png" alt="EDIT." width="160" height="auto" style="display:block;width:160px;height:auto;max-width:160px;border:0;"></a>
          </td>
          <td valign="middle" align="right">
            <p style="margin:0;font-size:11px;font-weight:700;color:#9a9a9a;letter-spacing:0.22em;text-transform:uppercase;font-family:'Helvetica Neue',Arial,sans-serif;"><?php echo esc_html( $issue_date ); ?></p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<!-- GREETING -->
<table role="presentation" class="container" width="680" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:680px;max-width:680px;background-color:#ffffff;">
  <tr><td class="px-md" style="padding:40px 40px 8px 40px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#0a0a0a;">
    <p style="margin:0;font-size:11px;font-weight:700;color:#f92869;letter-spacing:0.22em;text-transform:uppercase;"><?php echo esc_html( $copy['eyebrow'] ?? 'Bem-vindo à EDIT.' ); ?></p>
  </td></tr>
  <tr><td class="px-md" style="padding:14px 40px 24px 40px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#0a0a0a;">
    <h1 style="margin:0;font-size:36px;line-height:1.1;font-weight:700;letter-spacing:-0.025em;color:#0a0a0a;"><?php echo $accent_trailing_dot( $copy['headline'] ?? 'Que bom ter-te por aqui.' ); ?></h1>
  </td></tr>
  <tr><td class="px-md" style="padding:0 40px 24px 40px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#0a0a0a;">
    <?php foreach ( [ 'body_p1', 'body_p2', 'body_p3', 'body_p4' ] as $k ) : ?>
      <?php $txt = trim( (string) ( $copy[ $k ] ?? '' ) ); if ( $txt === '' ) continue; ?>
      <p style="margin:0 0 16px 0;font-size:16px;line-height:1.55;color:#222222;"><?php echo nl2br( esc_html( $txt ) ); ?></p>
    <?php endforeach; ?>
  </td></tr>
  <tr><td class="px-md" style="padding:8px 40px 48px 40px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
      <tr>
        <td valign="top" width="84" style="padding-right:18px;">
          <a href="https://weareedit.io/equipa/daniel-devera/" style="display:inline-block;text-decoration:none;border:0;"><img src="https://weareedit.io/wp-content/uploads/2026/05/daniel-devera-sign-off.png" alt="Daniel Devera" width="84" height="84" style="display:block;width:84px;height:84px;border-radius:50%;border:3px solid #f92869;box-sizing:border-box;"></a>
        </td>
        <td valign="top" style="padding-top:0;">
          <a href="https://weareedit.io/equipa/daniel-devera/" style="display:inline-block;text-decoration:none;border:0;"><img src="<?php echo esc_url( $copy['signature_url'] ?? 'https://weareedit.io/wp-content/uploads/2026/05/DANIEL-DEVERA-ASSINATURA.png' ); ?>" alt="Daniel Devera" width="180" height="auto" style="display:block;width:180px;height:auto;border:0;"></a>
          <p style="margin:8px 0 0 4px;font-size:11px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#0a0a0a;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://weareedit.io/equipa/daniel-devera/" style="color:#0a0a0a;text-decoration:none;">Founder</a></p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>

<!-- DGERT AUTHORITY BAND -->
<table role="presentation" class="container" width="680" cellpadding="0" cellspacing="0" border="0" bgcolor="#0a0a0a" style="width:680px;max-width:680px;background-color:#0a0a0a;">
  <tr><td class="px-md" style="padding:32px 40px;">
    <a href="https://www.dgert.gov.pt/entidades-formadoras-certificadas" style="text-decoration:none;display:block;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td valign="middle" align="left" width="120" style="padding-right:24px;">
            <img src="https://weareedit.io/wp-content/plugins/weareedit-site-engine/assets/dgert-entidade-formadora-branco.png" alt="DGERT — Entidade Formadora Certificada" width="84" height="auto" style="display:block;width:84px;height:auto;">
          </td>
          <td valign="middle" align="left">
            <p style="margin:0;font-size:18px;font-weight:700;color:#ffffff;line-height:1.3;font-family:'Helvetica Neue',Arial,sans-serif;letter-spacing:-0.01em;">Entidade Formadora Certificada</p>
            <p style="margin:4px 0 0 0;font-size:12px;color:rgba(255,255,255,0.6);font-family:'Helvetica Neue',Arial,sans-serif;letter-spacing:0.02em;">DGERT n&ordm; 18391</p>
          </td>
          <td valign="middle" align="right" width="40">
            <span style="display:inline-block;color:#ffffff;font-size:22px;line-height:1;font-family:'Helvetica Neue',Arial,sans-serif;">&#8599;</span>
          </td>
        </tr>
      </table>
    </a>
  </td></tr>
</table>

<!-- SECTION HEADING -->
<table role="presentation" class="container" width="680" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:680px;max-width:680px;background-color:#ffffff;">
  <tr><td align="left" class="px-md" style="padding:64px 40px 32px 40px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">
    <p style="margin:0 0 8px 0;font-size:11px;font-weight:700;color:#f92869;letter-spacing:0.22em;text-transform:uppercase;"><?php echo esc_html( $copy['section_eyebrow'] ?? 'Em destaque agora' ); ?></p>
    <h2 class="h2-em" style="margin:0;font-size:30px;line-height:1.1;font-weight:700;letter-spacing:-0.025em;color:#0a0a0a;"><?php echo $accent_trailing_dot( $copy['section_headline'] ?? 'As próximas edições.' ); ?></h2>
  </td></tr>
</table>

<?php
// Render up to 3 cards
foreach ( $picks_data as $idx => $pick ) {
    if ( $idx >= 3 ) break;
    echo $render_card( $pick, $idx );
}
?>

<!-- LOCKED WHITE FOOTER (default) -->
<table role="presentation" class="container" width="680" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:680px;max-width:680px;background-color:#ffffff;">
  <tr><td align="center" class="px-md" style="padding:56px 40px 32px 40px;">
    <img src="https://weareedit.io/wp-content/uploads/2026/05/edit-extended-logo.png" alt="EDIT." width="200" height="auto" style="display:block;margin:0 auto 20px auto;width:200px;max-width:200px;height:auto;border:0;">
    <p style="margin:0 0 28px 0;font-size:12px;color:#9a9a9a;font-family:'Helvetica Neue',Arial,sans-serif;letter-spacing:0.08em;text-transform:uppercase;">Disruptive Digital Education &middot; Desde 2011</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
      <tr>
        <td style="padding:0 10px;"><a href="https://www.instagram.com/weareedit/" style="display:inline-block;text-decoration:none;"><img src="https://img.icons8.com/ios-filled/100/0a0a0a/instagram-new.png" width="28" height="28" alt="Instagram" style="display:block;width:28px;height:28px;border:0;"></a></td>
        <td style="padding:0 10px;"><a href="https://www.facebook.com/weareedit" style="display:inline-block;text-decoration:none;"><img src="https://img.icons8.com/ios-filled/100/0a0a0a/facebook-new.png" width="28" height="28" alt="Facebook" style="display:block;width:28px;height:28px;border:0;"></a></td>
        <td style="padding:0 10px;"><a href="https://www.linkedin.com/school/edit-disruptive-digital-education/" style="display:inline-block;text-decoration:none;"><img src="https://img.icons8.com/ios-filled/100/0a0a0a/linkedin.png" width="28" height="28" alt="LinkedIn" style="display:block;width:28px;height:28px;border:0;"></a></td>
        <td style="padding:0 10px;"><a href="https://www.youtube.com/@weareeditpt" style="display:inline-block;text-decoration:none;"><img src="https://img.icons8.com/ios-filled/100/0a0a0a/youtube-play.png" width="28" height="28" alt="YouTube" style="display:block;width:28px;height:28px;border:0;"></a></td>
        <td style="padding:0 10px;"><a href="https://www.tiktok.com/@weareedit" style="display:inline-block;text-decoration:none;"><img src="https://img.icons8.com/ios-filled/100/0a0a0a/tiktok.png" width="28" height="28" alt="TikTok" style="display:block;width:28px;height:28px;border:0;"></a></td>
      </tr>
    </table>
  </td></tr>
  <tr><td class="px-md" align="left" style="padding:0 40px 12px 40px;">
    <p style="margin:0;font-size:11px;font-weight:700;color:#25D366;letter-spacing:0.22em;text-transform:uppercase;font-family:'Helvetica Neue',Arial,sans-serif;">Fala connosco</p>
  </td></tr>
  <tr><td class="px-md" align="left" style="padding:0 40px 14px 40px;">
    <p style="margin:0;font-size:24px;line-height:1.2;font-weight:700;color:#0a0a0a;letter-spacing:-0.02em;font-family:'Helvetica Neue',Arial,sans-serif;">Conversa direta pelo WhatsApp<span style="color:#25D366;">.</span></p>
  </td></tr>
  <tr><td class="px-md" align="left" style="padding:0 40px 40px 40px;">
    <a href="https://wa.me/351936508449" style="display:inline-block;font-family:'Helvetica Neue',Arial,sans-serif;font-size:18px;font-weight:600;color:#0a0a0a;text-decoration:underline;text-decoration-color:#25D366;text-underline-offset:5px;text-decoration-thickness:2px;letter-spacing:-0.005em;"><img src="https://img.icons8.com/ios-filled/100/0a0a0a/whatsapp.png" width="18" height="18" alt="" style="display:inline-block;vertical-align:middle;border:0;margin-right:8px;">+351 936 508 449 &rarr;</a>
  </td></tr>
  <tr><td class="px-md" style="padding:0 40px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top:1px solid #e5e5e5;font-size:0;line-height:0;height:1px;">&nbsp;</td></tr></table></td></tr>
  <tr><td class="px-md" style="padding:40px 40px 32px 40px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
      <tr>
        <td valign="top" width="25%" class="stack-md" style="padding-right:16px;">
          <p style="margin:0 0 16px 0;font-size:10px;font-weight:700;color:#f92869;letter-spacing:0.2em;text-transform:uppercase;font-family:'Helvetica Neue',Arial,sans-serif;">Cursos</p>
          <p style="margin:0 0 10px 0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://weareedit.io/formacao/" style="color:#0a0a0a;text-decoration:none;font-weight:600;">Todos &rsaquo;</a></p>
          <p style="margin:0 0 10px 0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://weareedit.io/marketing-digital/" style="color:#717171;text-decoration:none;">Marketing Digital</a></p>
          <p style="margin:0 0 10px 0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://weareedit.io/data-science/" style="color:#717171;text-decoration:none;">Data Science</a></p>
          <p style="margin:0 0 10px 0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://weareedit.io/curso-uxui-design/" style="color:#717171;text-decoration:none;">UX/UI Design</a></p>
          <p style="margin:0 0 10px 0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://weareedit.io/curso-inteligencia-artificial/" style="color:#717171;text-decoration:none;">Intelig&ecirc;ncia Artificial</a></p>
          <p style="margin:0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://weareedit.io/curso-programacao/" style="color:#717171;text-decoration:none;">Programa&ccedil;&atilde;o</a></p>
        </td>
        <td valign="top" width="25%" class="stack-md" style="padding-right:16px;">
          <p style="margin:0 0 16px 0;font-size:10px;font-weight:700;color:#f92869;letter-spacing:0.2em;text-transform:uppercase;font-family:'Helvetica Neue',Arial,sans-serif;">EDIT.</p>
          <p style="margin:0 0 10px 0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://weareedit.io/equipa/" style="color:#717171;text-decoration:none;">Equipa</a></p>
          <p style="margin:0 0 10px 0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://weareedit.io/formacao-digital-para-empresas/" style="color:#717171;text-decoration:none;">Para Empresas</a></p>
          <p style="margin:0 0 10px 0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://weareedit.io/avaliacoes-google/" style="color:#717171;text-decoration:none;">Avalia&ccedil;&otilde;es</a></p>
          <p style="margin:0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://weareedit.io/cheque-digital/" style="color:#717171;text-decoration:none;">Cheque Forma&ccedil;&atilde;o</a></p>
        </td>
        <td valign="top" width="25%" class="stack-md" style="padding-right:16px;">
          <p style="margin:0 0 16px 0;font-size:10px;font-weight:700;color:#f92869;letter-spacing:0.2em;text-transform:uppercase;font-family:'Helvetica Neue',Arial,sans-serif;">Recursos</p>
          <p style="margin:0 0 10px 0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://weareedit.io/criticas-google/" style="color:#717171;text-decoration:none;">Cr&iacute;ticas Google</a></p>
          <p style="margin:0 0 10px 0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://www.dgert.gov.pt/entidades-formadoras-certificadas" style="color:#717171;text-decoration:none;">DGERT</a></p>
          <p style="margin:0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="https://wa.me/351936508449" style="color:#717171;text-decoration:none;">WhatsApp</a></p>
        </td>
        <td valign="top" width="25%" class="stack-md">
          <p style="margin:0 0 16px 0;font-size:10px;font-weight:700;color:#f92869;letter-spacing:0.2em;text-transform:uppercase;font-family:'Helvetica Neue',Arial,sans-serif;">Contacto</p>
          <p style="margin:0 0 10px 0;font-size:13px;line-height:1.5;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="mailto:geral@weareedit.io" style="color:#717171;text-decoration:none;">geral@weareedit.io</a></p>
        </td>
      </tr>
    </table>
  </td></tr>
  <tr><td class="px-md" style="padding:0 40px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top:1px solid #e5e5e5;font-size:0;line-height:0;height:1px;">&nbsp;</td></tr></table></td></tr>
  <tr><td class="px-md" style="padding:32px 40px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
      <tr>
        <td valign="top" width="50%" class="stack-md" style="padding-right:24px;">
          <p style="margin:0 0 8px 0;font-size:10px;font-weight:700;color:#60c5b3;letter-spacing:0.2em;text-transform:uppercase;font-family:'Helvetica Neue',Arial,sans-serif;">Campus Lisboa</p>
          <p style="margin:0;font-size:13px;line-height:1.6;color:#717171;font-family:'Helvetica Neue',Arial,sans-serif;">Av. Aquilino Ribeiro Machado, N&ordm; 8<br>1600-001 Lisboa</p>
          <p style="margin:8px 0 0 0;font-size:13px;line-height:1.6;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="tel:+351210182455" style="color:#0a0a0a;text-decoration:none;font-weight:600;">+351 210 182 455</a></p>
        </td>
        <td valign="top" width="50%" class="stack-md">
          <p style="margin:0 0 8px 0;font-size:10px;font-weight:700;color:#60c5b3;letter-spacing:0.2em;text-transform:uppercase;font-family:'Helvetica Neue',Arial,sans-serif;">Campus Porto</p>
          <p style="margin:0;font-size:13px;line-height:1.6;color:#717171;font-family:'Helvetica Neue',Arial,sans-serif;">R. Alferes Malheiro, N&ordm; 226<br>4000-008 Porto</p>
          <p style="margin:8px 0 0 0;font-size:13px;line-height:1.6;font-family:'Helvetica Neue',Arial,sans-serif;"><a href="tel:+351224960345" style="color:#0a0a0a;text-decoration:none;font-weight:600;">+351 224 960 345</a></p>
        </td>
      </tr>
    </table>
  </td></tr>
  <tr><td class="px-md" style="padding:0 40px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top:1px solid #e5e5e5;font-size:0;line-height:0;height:1px;">&nbsp;</td></tr></table></td></tr>
  <tr><td class="px-md" style="padding:32px 40px;">
    <a href="https://weareedit.io/avaliacoes-google/" style="text-decoration:none;display:block;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td valign="middle" align="left" width="120" style="padding-right:24px;">
            <img src="https://img.icons8.com/color/100/google-logo.png" alt="Google" width="64" height="64" style="display:block;width:64px;height:64px;border:0;">
          </td>
          <td valign="middle" align="left">
            <p style="margin:0 0 4px 0;font-size:15px;font-weight:700;color:#0a0a0a;line-height:1.3;font-family:'Helvetica Neue',Arial,sans-serif;letter-spacing:-0.01em;">Avalia&ccedil;&otilde;es Google</p>
            <p style="margin:0;font-size:18px;line-height:1;font-family:'Helvetica Neue',Arial,sans-serif;color:#0a0a0a;">
              <span style="color:#FBBC05;letter-spacing:2px;font-size:16px;">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
              &nbsp;<span style="font-weight:700;color:#0a0a0a;letter-spacing:-0.01em;">4.1</span><span style="color:#9a9a9a;font-weight:500;font-size:14px;">&nbsp;/ 5</span>
            </p>
            <p style="margin:6px 0 0 0;font-size:12px;color:#717171;font-family:'Helvetica Neue',Arial,sans-serif;letter-spacing:0.02em;">67 avalia&ccedil;&otilde;es reais &middot; Lisboa + Porto</p>
          </td>
          <td valign="middle" align="right" width="40">
            <span style="display:inline-block;color:#9a9a9a;font-size:22px;line-height:1;font-family:'Helvetica Neue',Arial,sans-serif;">&#8599;</span>
          </td>
        </tr>
      </table>
    </a>
  </td></tr>
  <tr><td class="px-md" style="padding:0 40px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top:1px solid #e5e5e5;font-size:0;line-height:0;height:1px;">&nbsp;</td></tr></table></td></tr>
  <tr><td align="center" class="px-md" style="padding:24px 40px 40px 40px;">
    <p style="margin:0 0 10px 0;font-size:11px;line-height:1.6;color:#9a9a9a;font-family:'Helvetica Neue',Arial,sans-serif;text-align:center;">&copy; 2026 EDIT. &mdash; Disruptive Digital Education &middot; Todos os direitos reservados.</p>
    <p style="margin:0;font-size:11px;line-height:1.6;color:#717171;font-family:'Helvetica Neue',Arial,sans-serif;text-align:center;"><a href="https://weareedit.io/politica-de-privacidade/" style="color:#717171;text-decoration:underline;">Privacy</a> &nbsp;&middot;&nbsp; <a href="https://weareedit.io/termos-condicoes/" style="color:#717171;text-decoration:underline;">Termos</a> &nbsp;&middot;&nbsp; <a href="https://weareedit.io/cookies/" style="color:#717171;text-decoration:underline;">Cookies</a> &nbsp;&middot;&nbsp; <a href="mailto:geral@weareedit.io" style="color:#717171;text-decoration:underline;">Suporte</a> &nbsp;&middot;&nbsp; <a href="{{unsubscribe_url}}" style="color:#717171;text-decoration:underline;">Unsubscribe</a></p>
  </td></tr>
</table>

</td></tr>
</table>
</body>
</html>
