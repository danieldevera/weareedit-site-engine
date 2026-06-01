<?php
/**
 * Email Marketing Engines — dedicated admin menu.
 *
 * Lifts the Brevo Newsletter Integration + Welcome Email Autonomous
 * Picks controls out from under "Definições → EDIT. SEO Fix" into a
 * focused top-level menu shared by the marketing/sales team.
 *
 * Each engine gets its own section under one form. The form persists
 * to the same `edit_seo_fix_settings` option key as before, so no
 * data migration is needed — only the UI location moved.
 *
 * Future engines (one-click tagging, footer popup, weekly picks, etc.)
 * land here as new sections.
 *
 * @package WeareEditSiteEngine
 * @since   1.5.190
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Email_Engines_Panel {

    const MENU_SLUG = 'edit-email-engines';

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 20 );
    }

    public static function register_menu(): void {
        add_menu_page(
            'Email Marketing Engines',  // page title
            'Email Marketing',          // sidebar label
            'manage_options',           // capability (TODO: custom cap so sales team gets in without full admin)
            self::MENU_SLUG,
            [ __CLASS__, 'render_page' ],
            'dashicons-email-alt',      // sidebar icon
            30                          // position
        );
    }

    public static function render_page(): void {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        $synced_flash = isset( $_GET['picks_synced'] );
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:12px;">
                <span class="dashicons dashicons-email-alt" style="font-size:28px;width:28px;height:28px;color:#f92869;"></span>
                Email Marketing Engines
            </h1>
            <p style="max-width:780px;font-size:14px;color:#444;">
                One panel for every email automation EDIT. runs. Each engine renders, syncs, and reports here.
                Future engines (one-click tagging, footer popup capture, weekly picks) will appear as new sections below.
            </p>

            <form method="post" action="options.php">
                <?php settings_fields( 'edit_seo_fix_group' ); ?>

                <h2>Brevo connection</h2>
                <p class="description">Shared by every engine on this page. Brevo v3 API key + the master newsletter list ID.</p>
                <table class="form-table">
                    <tr>
                        <th><label for="brevo_api_key">Brevo API Key (v3)</label></th>
                        <td>
                            <input type="password" id="brevo_api_key" name="edit_seo_fix_settings[brevo_api_key]" value="<?php echo esc_attr( $settings['brevo_api_key'] ?? '' ); ?>" class="regular-text" placeholder="xkeysib-..." autocomplete="off">
                            <p class="description">Generate at <a href="https://app.brevo.com/settings/keys/api" target="_blank">Brevo → SMTP &amp; API → Chaves API</a>. Treat as a secret — never paste it in chat or screenshots.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="brevo_newsletter_list_id">Newsletter list ID</label></th>
                        <td>
                            <input type="number" id="brevo_newsletter_list_id" name="edit_seo_fix_settings[brevo_newsletter_list_id]" value="<?php echo esc_attr( $settings['brevo_newsletter_list_id'] ?? 4 ); ?>" class="small-text" min="1" placeholder="4">
                            <p class="description">Default list for new homepage-strip subscribers (<code>4</code> = <code>Newsletter · Site organic (2026+)</code>). Find at <a href="https://app.brevo.com/contact/list-listing" target="_blank">Brevo → Contatos → Listas</a>.</p>
                        </td>
                    </tr>
                </table>

                <hr style="margin:32px 0 24px;border:0;border-top:1px solid #e5e5e5;">

                <h2>Engine #1 · Welcome Email (direct send · Path B)</h2>
                <p class="description">
                    Every new subscriber via the homepage strip triggers an immediate welcome email — WP renders the locked
                    template with today's freshest picks and sends it through Brevo's transactional API. No Brevo automation
                    needed. Test deliverability with the button below before flipping the homepage strip on.
                </p>
                <table class="form-table">
                    <tr>
                        <th><label for="welcome_sender_email">Sender email</label></th>
                        <td>
                            <input type="email" id="welcome_sender_email" name="edit_seo_fix_settings[welcome_sender_email]" value="<?php echo esc_attr( $settings['welcome_sender_email'] ?? 'daniel.devera@weareedit.io' ); ?>" class="regular-text">
                            <p class="description">Must be Brevo-verified. Check at <a href="https://app.brevo.com/senders" target="_blank">Brevo → Senders, Domains & IPs</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="welcome_sender_name">Sender name</label></th>
                        <td>
                            <input type="text" id="welcome_sender_name" name="edit_seo_fix_settings[welcome_sender_name]" value="<?php echo esc_attr( $settings['welcome_sender_name'] ?? 'Daniel Devera from EDIT.' ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="welcome_subject">Subject line</label></th>
                        <td>
                            <input type="text" id="welcome_subject" name="edit_seo_fix_settings[welcome_subject]" value="<?php echo esc_attr( $settings['welcome_subject'] ?? 'Que bom ter-te por aqui na EDIT.' ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="welcome_test_recipient">Test recipient</label></th>
                        <td>
                            <input type="email" id="welcome_test_recipient" name="edit_seo_fix_settings[welcome_test_recipient]" value="<?php echo esc_attr( $settings['welcome_test_recipient'] ?? 'daniel.devera@weareedit.io' ); ?>" class="regular-text">
                            <p class="description">Where the "Send test welcome" button below delivers. Defaults to the sender so the round-trip is in one inbox.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Last send</th>
                        <td>
                            <?php
                            if ( class_exists( 'EDIT_Newsletter_Picks' ) ) {
                                $st = EDIT_Newsletter_Picks::get_last_sync_status();
                                if ( ! empty( $st['time'] ) ) {
                                    $when  = wp_date( 'Y-m-d H:i:s', $st['time'] );
                                    $ok    = ( $st['status'] ?? '' ) === 'ok';
                                    $color = $ok ? '#1e8a4f' : '#b62929';
                                    $label = $ok ? 'OK' : 'ERROR';
                                    echo '<code>' . esc_html( $when ) . '</code> &middot; '
                                        . '<strong style="color:' . $color . ';">' . $label . '</strong> &middot; '
                                        . esc_html( $st['message'] ?? '' );
                                } else {
                                    echo '<em>Never run yet — save your settings (below), then click "Send test welcome".</em>';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:24px;">Welcome email copy</h3>
                <p class="description">Override the text inside the welcome email. Leave a field blank to keep the locked default. Tutor cards (below the body) are auto-pulled from the closest upcoming editions; only the framing copy is editable here.</p>
                <table class="form-table">
                    <tr>
                        <th><label for="welcome_eyebrow">Eyebrow</label></th>
                        <td>
                            <input type="text" id="welcome_eyebrow" name="edit_seo_fix_settings[welcome_eyebrow]" value="<?php echo esc_attr( $settings['welcome_eyebrow'] ?? '' ); ?>" class="regular-text" placeholder="Bem-vindo à EDIT.">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="welcome_headline">Headline (H1)</label></th>
                        <td>
                            <input type="text" id="welcome_headline" name="edit_seo_fix_settings[welcome_headline]" value="<?php echo esc_attr( $settings['welcome_headline'] ?? '' ); ?>" class="regular-text" placeholder="Que bom ter-te por aqui.">
                            <p class="description">Trailing <code>.</code> automatically gets the pink accent.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="welcome_body">Body</label></th>
                        <td>
                            <?php
                            $default_body = "Sou o Daniel, fundador da EDIT.\n\nEsta newsletter é onde partilho, em primeira mão, as ideias que estamos a explorar, os tutores que estamos a entrevistar, e os cursos que estão a nascer aqui dentro.\n\nUma edição por semana. Sem fluff. Quando responderes a este email, sou eu que leio.\n\nDeixo-te aqui o que está em destaque agora.";
                            $current_body = isset( $settings['welcome_body'] ) && trim( (string) $settings['welcome_body'] ) !== ''
                                ? (string) $settings['welcome_body']
                                : $default_body;
                            ?>
                            <textarea id="welcome_body" name="edit_seo_fix_settings[welcome_body]" rows="10" class="large-text"><?php echo esc_textarea( $current_body ); ?></textarea>
                            <p class="description">Separate paragraphs with a blank line. Each paragraph renders as a <code>&lt;p&gt;</code> in the email.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="welcome_section_eyebrow">Section eyebrow</label></th>
                        <td>
                            <input type="text" id="welcome_section_eyebrow" name="edit_seo_fix_settings[welcome_section_eyebrow]" value="<?php echo esc_attr( $settings['welcome_section_eyebrow'] ?? '' ); ?>" class="regular-text" placeholder="Em destaque agora">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="welcome_section_headline">Section headline (H2)</label></th>
                        <td>
                            <input type="text" id="welcome_section_headline" name="edit_seo_fix_settings[welcome_section_headline]" value="<?php echo esc_attr( $settings['welcome_section_headline'] ?? '' ); ?>" class="regular-text" placeholder="As próximas edições.">
                            <p class="description">Trailing <code>.</code> automatically gets the pink accent.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="welcome_signature_url">Signature image URL</label></th>
                        <td>
                            <input type="url" id="welcome_signature_url" name="edit_seo_fix_settings[welcome_signature_url]" value="<?php echo esc_attr( $settings['welcome_signature_url'] ?? '' ); ?>" class="large-text" placeholder="https://weareedit.io/wp-content/uploads/2026/05/DANIEL-DEVERA-ASSINATURA.png">
                            <p class="description">PNG of Daniel's handwritten signature. Upload a <strong>tightly-cropped</strong> version (no top/bottom whitespace) to <a href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>" target="_blank">Media → Add New</a>, copy the URL, and paste here. Leave blank to use the default <code>DANIEL-DEVERA-ASSINATURA.png</code>.</p>
                        </td>
                    </tr>
                </table>

                <p class="description" style="padding:10px 14px;background:#f5f5f5;border-left:3px solid #f92869;margin:8px 0 0 0;font-size:13px;">
                    <strong>Note:</strong> Path B replaces the Brevo "Mensagem de boas-vindas" automation entirely. Leave that
                    automation paused (or delete it) — WP handles welcome sends directly now, with today's freshest picks every time.
                </p>

                <hr style="margin:32px 0 24px;border:0;border-top:1px solid #e5e5e5;">

                <h2>Engine #2 · Homepage Newsletter Strip</h2>
                <p class="description">The yellow signup strip on the homepage + workshop / bootcamp / curso product pages. Writes new subscribers to the newsletter list configured at the top.</p>
                <table class="form-table">
                    <tr>
                        <th>Status</th>
                        <td>
                            <span style="color:#1e8a4f;font-weight:700;">● LIVE</span>
                            &middot; Locked at v1.5.182 (homepage layout) + v1.5.186 (per-typology colours)
                            <p class="description">Edit the strip via the plugin source — visual structure is locked, but copy + list ID stay configurable here.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save changes', 'primary', 'submit', true ); ?>
            </form>

            <!-- Separate form for the "Send test welcome" action — must
                 NOT be nested inside the settings form above (HTML doesn't
                 allow nested forms; the browser collapses them and the
                 outer form's action steals the submission). -->
            <hr style="margin:32px 0 24px;border:0;border-top:1px solid #e5e5e5;">
            <h2 style="margin:0 0 6px 0;">Send test welcome</h2>
            <p class="description" style="margin:0 0 16px 0;">
                After saving the settings above, click below to render the locked welcome template with today's picks and send a single email to <code><?php echo esc_html( $settings['welcome_test_recipient'] ?? 'daniel.devera@weareedit.io' ); ?></code> via Brevo's transactional API.
            </p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'edit_newsletter_picks_force_sync' ); ?>
                <input type="hidden" name="action" value="edit_newsletter_picks_force_sync">
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">Send test welcome &rarr;</button>
                    <?php
                    if ( $synced_flash && class_exists( 'EDIT_Newsletter_Picks' ) ) {
                        $st = EDIT_Newsletter_Picks::get_last_sync_status();
                        $ok = ( $st['status'] ?? '' ) === 'ok';
                        echo '<span style="margin-left:14px;font-weight:600;color:' . ( $ok ? '#1e8a4f' : '#b62929' ) . ';">'
                            . ( $ok ? '✓ ' : '✗ ' ) . esc_html( $st['message'] ?? '' ) . '</span>';
                    }
                    ?>
                </p>
            </form>
        </div>
        <?php
    }
}
