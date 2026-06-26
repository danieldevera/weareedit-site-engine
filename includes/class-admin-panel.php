<?php
/**
 * Admin Panel — EDIT. SEO Fix Settings
 *
 * Central settings page where all plugin options can be configured.
 * Located under: Settings → EDIT. SEO Fix
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Admin_Panel {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_notices', [ __CLASS__, 'show_audit_summary' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
    }

    public static function register_menu() {
        add_options_page(
            'EDIT. SEO Fix',
            'EDIT. SEO Fix',
            'manage_options',
            'edit-seo-fix',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    public static function register_settings() {
        register_setting( 'edit_seo_fix_group', 'edit_seo_fix_settings', [
            'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
        ] );
    }

    public static function sanitize_settings( array $input ): array {
        // Preserve keys handled by other panels (e.g. the Email Marketing
        // form has no fix_output_buffer checkbox — without this, every save
        // on that form would silently disable the homepage hero rewrites).
        $existing = (array) get_option( 'edit_seo_fix_settings', [] );
        if ( class_exists( 'EDIT_CF7_Debug' ) ) {
            EDIT_CF7_Debug::write( [
                'event'      => 'settings_sanitize',
                'form_title' => 'Settings sanitize · welcome_single_*',
                'mode_in'        => var_export( $input['welcome_single_mode']        ?? '<MISSING>', true ),
                'title_in'       => var_export( $input['welcome_single_title']       ?? '<MISSING>', true ),
                'url_in'         => var_export( $input['welcome_single_url']         ?? '<MISSING>', true ),
                'tutor_name_in'  => var_export( $input['welcome_single_tutor_name']  ?? '<MISSING>', true ),
                'tutor_photo_in' => var_export( $input['welcome_single_tutor_photo'] ?? '<MISSING>', true ),
                'input_keys'     => implode( ', ', array_keys( $input ) ),
            ] );
        }
        $sanitized = [
            'default_description'   => sanitize_text_field( $input['default_description'] ?? '' ),
            'og_site_name'          => sanitize_text_field( $input['og_site_name'] ?? '' ),
            'twitter_handle'        => sanitize_text_field( $input['twitter_handle'] ?? '' ),
            'primary_language'      => sanitize_text_field( $input['primary_language'] ?? 'pt-PT' ),
            'defer_scripts'         => ! empty( $input['defer_scripts'] ),
            'preload_fonts'         => ! empty( $input['preload_fonts'] ),
            'fix_image_alts'        => ! empty( $input['fix_image_alts'] ),
            'fix_duplicate_ga'      => ! empty( $input['fix_duplicate_ga'] ),
            'primary_ga_id'         => sanitize_text_field( $input['primary_ga_id'] ?? '' ),
            'sitemap_entries'       => absint( $input['sitemap_entries'] ?? 1000 ),
            'default_og_image_id'   => absint( $input['default_og_image_id'] ?? 0 ),
            'force_meta_output'     => ! empty( $input['force_meta_output'] ),
            'lcp_image_url'         => esc_url_raw( $input['lcp_image_url'] ?? '' ),
            'fix_output_buffer'     => ! empty( $input['fix_output_buffer'] ),
            // Tracking pixels
            'fb_pixel_id'           => sanitize_text_field( $input['fb_pixel_id'] ?? '' ),
            'hotjar_id'             => sanitize_text_field( $input['hotjar_id'] ?? '' ),
            'linkedin_partner_id'   => sanitize_text_field( $input['linkedin_partner_id'] ?? '' ),
            'tiktok_pixel_id'       => sanitize_text_field( $input['tiktok_pixel_id'] ?? '' ),
            'gads_conversion_id'    => sanitize_text_field( $input['gads_conversion_id'] ?? '' ),
            // Course pages
            'course_post_types'     => sanitize_text_field( $input['course_post_types'] ?? 'formacao,curso,course' ),
            'course_schema_enabled' => isset( $input['course_schema_enabled'] ) ? ! empty( $input['course_schema_enabled'] ) : true,
            'course_credential'     => sanitize_text_field( $input['course_credential'] ?? 'Certificado DGERT' ),
            // Cheque-Formação
            'cheque_formacao_enabled' => ! empty( $input['cheque_formacao_enabled'] ),
            'cheque_formacao_text'    => wp_kses_post( $input['cheque_formacao_text'] ?? '' ),
            'cheque_formacao_link'    => esc_url_raw( $input['cheque_formacao_link'] ?? '' ),
            // Archive descriptions (keyed by post type slug)
            'archive_descriptions'    => array_map(
                'sanitize_text_field',
                (array) ( $input['archive_descriptions'] ?? [] )
            ),
            // Brevo newsletter integration (v1.5.148+)
            'brevo_api_key'             => sanitize_text_field( $input['brevo_api_key'] ?? '' ),
            'brevo_newsletter_list_id'  => absint( $input['brevo_newsletter_list_id'] ?? 4 ),
            // Welcome email — Path B (direct send on signup)
            'welcome_sender_email'      => sanitize_email( $input['welcome_sender_email'] ?? 'daniel.devera@weareedit.io' ),
            'welcome_sender_name'       => sanitize_text_field( $input['welcome_sender_name'] ?? 'Daniel Devera da EDIT.' ),
            'welcome_subject'           => sanitize_text_field( $input['welcome_subject'] ?? 'Que bom ter-te por aqui na EDIT.' ),
            'welcome_test_recipient'    => sanitize_email( $input['welcome_test_recipient'] ?? 'daniel.devera@weareedit.io' ),
            'welcome_eyebrow'           => sanitize_text_field( $input['welcome_eyebrow'] ?? '' ),
            'welcome_headline'          => sanitize_text_field( $input['welcome_headline'] ?? '' ),
            'welcome_body'              => sanitize_textarea_field( $input['welcome_body'] ?? '' ),
            'welcome_section_eyebrow'   => sanitize_text_field( $input['welcome_section_eyebrow'] ?? '' ),
            'welcome_section_headline'  => sanitize_text_field( $input['welcome_section_headline'] ?? '' ),
            'welcome_signature_url'     => esc_url_raw( $input['welcome_signature_url'] ?? '' ),
            'welcome_pinned_picks'      => sanitize_textarea_field( $input['welcome_pinned_picks'] ?? '' ),
            // Single-product manual mode (bypasses WP lookups entirely)
            'welcome_single_mode'             => ! empty( $input['welcome_single_mode'] ),
            'welcome_single_typology'         => sanitize_text_field( $input['welcome_single_typology'] ?? 'bootcamp' ),
            'welcome_single_date_label'       => sanitize_text_field( $input['welcome_single_date_label'] ?? '' ),
            'welcome_single_title'            => sanitize_text_field( $input['welcome_single_title'] ?? '' ),
            'welcome_single_url'              => esc_url_raw( $input['welcome_single_url'] ?? '' ),
            'welcome_single_description'      => sanitize_text_field( $input['welcome_single_description'] ?? '' ),
            'welcome_single_tutor_name'       => sanitize_text_field( $input['welcome_single_tutor_name'] ?? '' ),
            'welcome_single_tutor_role'       => sanitize_text_field( $input['welcome_single_tutor_role'] ?? 'Tutor · EDIT.' ),
            'welcome_single_tutor_url'        => esc_url_raw( $input['welcome_single_tutor_url'] ?? '' ),
            'welcome_single_tutor_photo'      => esc_url_raw( $input['welcome_single_tutor_photo'] ?? '' ),
            // Card 2 (optional — rendered only if title is filled)
            'welcome_card2_typology'    => sanitize_text_field( $input['welcome_card2_typology'] ?? 'bootcamp' ),
            'welcome_card2_date_label'  => sanitize_text_field( $input['welcome_card2_date_label'] ?? '' ),
            'welcome_card2_title'       => sanitize_text_field( $input['welcome_card2_title'] ?? '' ),
            'welcome_card2_url'         => esc_url_raw( $input['welcome_card2_url'] ?? '' ),
            'welcome_card2_description' => sanitize_text_field( $input['welcome_card2_description'] ?? '' ),
            'welcome_card2_tutor_name'  => sanitize_text_field( $input['welcome_card2_tutor_name'] ?? '' ),
            'welcome_card2_tutor_role'  => sanitize_text_field( $input['welcome_card2_tutor_role'] ?? 'Tutor · EDIT.' ),
            'welcome_card2_tutor_url'   => esc_url_raw( $input['welcome_card2_tutor_url'] ?? '' ),
            'welcome_card2_tutor_photo' => esc_url_raw( $input['welcome_card2_tutor_photo'] ?? '' ),
            // Card 3 (optional)
            'welcome_card3_typology'    => sanitize_text_field( $input['welcome_card3_typology'] ?? 'workshop' ),
            'welcome_card3_date_label'  => sanitize_text_field( $input['welcome_card3_date_label'] ?? '' ),
            'welcome_card3_title'       => sanitize_text_field( $input['welcome_card3_title'] ?? '' ),
            'welcome_card3_url'         => esc_url_raw( $input['welcome_card3_url'] ?? '' ),
            'welcome_card3_description' => sanitize_text_field( $input['welcome_card3_description'] ?? '' ),
            'welcome_card3_tutor_name'  => sanitize_text_field( $input['welcome_card3_tutor_name'] ?? '' ),
            'welcome_card3_tutor_role'  => sanitize_text_field( $input['welcome_card3_tutor_role'] ?? 'Tutor · EDIT.' ),
            'welcome_card3_tutor_url'   => esc_url_raw( $input['welcome_card3_tutor_url'] ?? '' ),
            'welcome_card3_tutor_photo' => esc_url_raw( $input['welcome_card3_tutor_photo'] ?? '' ),
            'picks_cron_enabled'        => ! empty( $input['picks_cron_enabled'] ),
        ];

        // Restore any key the submitted form didn't include — protects
        // fields owned by other settings panels from being wiped to their
        // ?? defaults on a partial-form save.
        foreach ( $sanitized as $k => $_ ) {
            if ( ! array_key_exists( $k, $input ) && array_key_exists( $k, $existing ) ) {
                $sanitized[ $k ] = $existing[ $k ];
            }
        }
        return $sanitized;
    }

    public static function render_settings_page() {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        $issues   = self::get_current_issues();
        ?>
        <div class="wrap">
            <h1>EDIT. SEO Fix — Settings & Audit</h1>

            <div class="edit-seo-audit-summary">
                <h2>Issues Found (<?php echo count( $issues ); ?>)</h2>
                <table class="widefat striped">
                    <thead><tr>
                        <th>#</th><th>Issue</th><th>Severity</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $issues as $i => $issue ) : ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo esc_html( $issue['issue'] ); ?></td>
                            <td><span class="edit-seo-badge edit-seo-badge--<?php echo esc_attr( strtolower( $issue['severity'] ) ); ?>"><?php echo esc_html( $issue['severity'] ); ?></span></td>
                            <td><span class="edit-seo-badge edit-seo-badge--<?php echo esc_attr( $issue['fixed'] ? 'fixed' : ( isset( $issue['manual'] ) ? 'manual' : 'needs-config' ) ); ?>"><?php echo $issue['fixed'] ? 'Auto-Fixed' : ( isset( $issue['manual'] ) ? 'Manual Fix' : 'Needs Config' ); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <hr>

            <form method="post" action="options.php">
                <?php settings_fields( 'edit_seo_fix_group' ); ?>

                <h2>General</h2>
                <table class="form-table">
                    <?php if ( defined( 'RANK_MATH_VERSION' ) ) : ?>
                    <tr>
                        <th>Rank Math Detected</th>
                        <td>
                            <label>
                                <input type="checkbox" name="edit_seo_fix_settings[force_meta_output]" value="1" <?php checked( $settings['force_meta_output'] ?? false ); ?>>
                                <strong>Force plugin meta output</strong> — override Rank Math and output meta description, canonical, Open Graph, and Twitter Card tags from this plugin
                            </label>
                            <p class="description" style="color:#b32d2e;">⚠️ Enable this only if Rank Math is <strong>not</strong> outputting these tags. Running both simultaneously will create duplicates.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><label for="default_description">Default Meta Description</label></th>
                        <td>
                            <textarea id="default_description" name="edit_seo_fix_settings[default_description]" rows="3" class="large-text"><?php echo esc_textarea( $settings['default_description'] ?? '' ); ?></textarea>
                            <p class="description">Used on pages without a specific description. Aim for 120–160 characters.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="og_site_name">Site Name (for OG/Twitter)</label></th>
                        <td><input type="text" id="og_site_name" name="edit_seo_fix_settings[og_site_name]" value="<?php echo esc_attr( $settings['og_site_name'] ?? '' ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="twitter_handle">Twitter Handle</label></th>
                        <td><input type="text" id="twitter_handle" name="edit_seo_fix_settings[twitter_handle]" value="<?php echo esc_attr( $settings['twitter_handle'] ?? '' ); ?>" class="regular-text" placeholder="@weareedit"></td>
                    </tr>
                    <tr>
                        <th><label for="default_og_image_id">Default OG / Share Image</label></th>
                        <td>
                            <input type="hidden" id="default_og_image_id" name="edit_seo_fix_settings[default_og_image_id]" value="<?php echo esc_attr( $settings['default_og_image_id'] ?? '' ); ?>">
                            <button type="button" class="button" id="edit-seo-pick-image">Select Image</button>
                            <?php if ( ! empty( $settings['default_og_image_id'] ) ) : ?>
                                <p><?php echo wp_get_attachment_image( $settings['default_og_image_id'], [120, 63] ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="lcp_image_url">LCP Hero Image URL</label></th>
                        <td>
                            <input type="url" id="lcp_image_url" name="edit_seo_fix_settings[lcp_image_url]" value="<?php echo esc_attr( $settings['lcp_image_url'] ?? '' ); ?>" class="large-text" placeholder="https://weareedit.io/wp-content/uploads/...">
                            <p class="description">URL of the above-the-fold hero image. The plugin will add a <code>&lt;link rel="preload"&gt;</code> for it to improve LCP. Find it in View Page Source — look for the largest image in the hero section.</p>
                        </td>
                    </tr>
                </table>

                <h2>Optimizations</h2>
                <table class="form-table">
                    <tr>
                        <th>Defer Non-Critical Scripts</th>
                        <td><label><input type="checkbox" name="edit_seo_fix_settings[defer_scripts]" value="1" <?php checked( $settings['defer_scripts'] ?? true ); ?>> Add <code>defer</code> to analytics, GTM, and tracking scripts</label></td>
                    </tr>
                    <tr>
                        <th>Preload Fonts / Resource Hints</th>
                        <td><label><input type="checkbox" name="edit_seo_fix_settings[preload_fonts]" value="1" <?php checked( $settings['preload_fonts'] ?? true ); ?>> Add preconnect/dns-prefetch hints for Google Fonts and analytics</label></td>
                    </tr>
                    <tr>
                        <th>Auto-Fix Image Alt Text</th>
                        <td><label><input type="checkbox" name="edit_seo_fix_settings[fix_image_alts]" value="1" <?php checked( $settings['fix_image_alts'] ?? true ); ?>> Auto-generate alt text from image title if missing</label></td>
                    </tr>
                    <tr>
                        <th>Full-Page Image Fix (Output Buffer)</th>
                        <td>
                            <label><input type="checkbox" name="edit_seo_fix_settings[fix_output_buffer]" value="1" <?php checked( $settings['fix_output_buffer'] ?? true ); ?>> Fix <strong>all</strong> images site-wide — adds missing alt text and <code>loading="lazy"</code> to theme templates, widgets, and plugin output (not just post content)</label>
                            <p class="description">Recommended. Uses PHP output buffering to intercept the full HTML. Also fixes staging-URL leaks and relative image paths.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Remove Duplicate Analytics</th>
                        <td><label><input type="checkbox" name="edit_seo_fix_settings[fix_duplicate_ga]" value="1" <?php checked( $settings['fix_duplicate_ga'] ?? true ); ?>> Remove legacy UA tracking and deduplicate GA4 vs GTM</label></td>
                    </tr>
                    <tr>
                        <th><label for="primary_ga_id">Primary GA4 Measurement ID</label></th>
                        <td><input type="text" id="primary_ga_id" name="edit_seo_fix_settings[primary_ga_id]" value="<?php echo esc_attr( $settings['primary_ga_id'] ?? 'G-R11CP4ELEH' ); ?>" class="regular-text" placeholder="G-XXXXXXXXXX"></td>
                    </tr>
                </table>

                <h2>Sitemap</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="sitemap_entries">Max URLs per Sitemap</label></th>
                        <td>
                            <input type="number" id="sitemap_entries" name="edit_seo_fix_settings[sitemap_entries]" value="<?php echo esc_attr( $settings['sitemap_entries'] ?? 1000 ); ?>" class="small-text" min="100" max="50000">
                            <p class="description">
                                Sitemap index: <a href="<?php echo esc_url( home_url( '/sitemap_index.xml' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/sitemap_index.xml' ) ); ?></a>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2>Tracking Pixels</h2>
                <p class="description">Enter IDs to activate each tracking service. Leave blank to disable. Scripts are deferred automatically and respect Moove GDPR consent.</p>
                <table class="form-table">
                    <tr>
                        <th><label for="fb_pixel_id">Facebook Pixel ID</label></th>
                        <td>
                            <input type="text" id="fb_pixel_id" name="edit_seo_fix_settings[fb_pixel_id]" value="<?php echo esc_attr( $settings['fb_pixel_id'] ?? '' ); ?>" class="regular-text" placeholder="e.g. 2551453898325061">
                            <p class="description">Get from <a href="https://business.facebook.com/events_manager" target="_blank">Meta Events Manager</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="hotjar_id">Hotjar Site ID</label></th>
                        <td>
                            <input type="text" id="hotjar_id" name="edit_seo_fix_settings[hotjar_id]" value="<?php echo esc_attr( $settings['hotjar_id'] ?? '' ); ?>" class="regular-text" placeholder="e.g. 3389236">
                            <p class="description">Get from <a href="https://insights.hotjar.com/site/list" target="_blank">Hotjar → Sites</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="linkedin_partner_id">LinkedIn Partner ID</label></th>
                        <td>
                            <input type="text" id="linkedin_partner_id" name="edit_seo_fix_settings[linkedin_partner_id]" value="<?php echo esc_attr( $settings['linkedin_partner_id'] ?? '' ); ?>" class="regular-text" placeholder="e.g. 7491708">
                            <p class="description">Get from <a href="https://www.linkedin.com/campaignmanager/" target="_blank">LinkedIn Campaign Manager → Insight Tag</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tiktok_pixel_id">TikTok Pixel ID</label></th>
                        <td>
                            <input type="text" id="tiktok_pixel_id" name="edit_seo_fix_settings[tiktok_pixel_id]" value="<?php echo esc_attr( $settings['tiktok_pixel_id'] ?? '' ); ?>" class="regular-text" placeholder="e.g. D1OIV83C77U5IDGMJ2I0">
                            <p class="description">Get from <a href="https://ads.tiktok.com/i18n/events_manager" target="_blank">TikTok Ads → Events Manager</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gads_conversion_id">Google Ads Conversion ID</label></th>
                        <td>
                            <input type="text" id="gads_conversion_id" name="edit_seo_fix_settings[gads_conversion_id]" value="<?php echo esc_attr( $settings['gads_conversion_id'] ?? '' ); ?>" class="regular-text" placeholder="AW-XXXXXXXXX">
                            <p class="description">Get from <a href="https://ads.google.com/aw/conversions" target="_blank">Google Ads → Tools → Conversions</a>.</p>
                        </td>
                    </tr>
                </table>

                <h2>Email Marketing Engines</h2>
                <p class="description" style="padding:12px 16px;background:#fff8d6;border-left:4px solid #f92869;margin:0 0 16px 0;">
                    Brevo Newsletter Integration + Welcome Email controls moved to a dedicated top-level menu — open
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . EDIT_Email_Engines_Panel::MENU_SLUG ) ); ?>" style="font-weight:700;">Email Marketing →</a>
                </p>

                <h2>Course Pages</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="course_post_types">Course Post Types</label></th>
                        <td>
                            <input type="text" id="course_post_types" name="edit_seo_fix_settings[course_post_types]" value="<?php echo esc_attr( $settings['course_post_types'] ?? 'formacao,curso,course' ); ?>" class="regular-text">
                            <p class="description">Comma-separated WordPress post type slugs for course pages. Course schema and Cheque-Formação notices apply to these types.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Course Schema (JSON-LD)</th>
                        <td>
                            <label>
                                <input type="checkbox" name="edit_seo_fix_settings[course_schema_enabled]" value="1" <?php checked( $settings['course_schema_enabled'] ?? true ); ?>>
                                Output <code>schema.org/Course</code> JSON-LD on course pages — enables Google rich results (price, duration, provider)
                            </label>
                            <p class="description">Enabled by default. Disable only if another plugin outputs Course schema.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="course_credential">Default Credential Awarded</label></th>
                        <td>
                            <input type="text" id="course_credential" name="edit_seo_fix_settings[course_credential]" value="<?php echo esc_attr( $settings['course_credential'] ?? 'Certificado DGERT' ); ?>" class="regular-text">
                            <p class="description">Shown in Course schema as <code>educationalCredentialAwarded</code>. Override per course with the <code>_course_credential</code> post meta field.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Per-Course Meta Fields</th>
                        <td>
                            <p class="description">Set these on individual course posts (Custom Fields or ACF) to unlock Google rich results:</p>
                            <ul style="list-style:disc;margin-left:16px;line-height:2">
                                <li><code>_course_workload</code> — expected effort, e.g. <em>"4 horas por semana"</em> <strong style="color:#b32d2e">(required for enrollment rich result)</strong></li>
                                <li><code>_course_price</code> — numeric price in EUR, e.g. <em>1500</em></li>
                                <li><code>_course_duration_hours</code> — total hours, e.g. <em>200</em></li>
                                <li><code>_course_duration_weeks</code> — duration in weeks, e.g. <em>10</em></li>
                                <li><code>_course_start_date</code> — ISO date, e.g. <em>2026-09-15</em></li>
                                <li><code>_course_end_date</code> — ISO date, e.g. <em>2026-12-20</em></li>
                                <li><code>_course_mode</code> — comma-separated: <em>OnSite, Online, Blended</em></li>
                                <li><code>_course_location</code> — city, e.g. <em>Lisboa</em></li>
                                <li><code>_course_instructor</code> — instructor name</li>
                                <li><code>_course_credential</code> — overrides the default credential above</li>
                            </ul>
                        </td>
                    </tr>
                </table>

                <h2>Cheque-Formação + Digital</h2>
                <table class="form-table">
                    <tr>
                        <th>Enable Notice</th>
                        <td>
                            <label>
                                <input type="checkbox" name="edit_seo_fix_settings[cheque_formacao_enabled]" value="1" <?php checked( $settings['cheque_formacao_enabled'] ?? false ); ?>>
                                Auto-inject Cheque-Formação + Digital eligibility notice at the bottom of course pages
                            </label>
                            <p class="description">Displays a styled notice informing students they may receive up to €750 in government subsidy. Proven conversion driver — TheStarter and LDS both feature this prominently.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cheque_formacao_text">Notice Text</label></th>
                        <td>
                            <textarea id="cheque_formacao_text" name="edit_seo_fix_settings[cheque_formacao_text]" rows="3" class="large-text"><?php echo esc_textarea( $settings['cheque_formacao_text'] ?? '' ); ?></textarea>
                            <p class="description">Supports basic HTML (strong, em, a). Leave blank to use the default text.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cheque_formacao_link">Application Link URL</label></th>
                        <td>
                            <input type="url" id="cheque_formacao_link" name="edit_seo_fix_settings[cheque_formacao_link]" value="<?php echo esc_attr( $settings['cheque_formacao_link'] ?? '' ); ?>" class="large-text" placeholder="https://www.iefp.pt/cheque-formacao">
                            <p class="description">URL of the official Cheque-Formação application page. Defaults to IEFP if left blank.</p>
                        </td>
                    </tr>
                </table>

                <h2>Archive Descriptions</h2>
                <p class="description">Meta descriptions for post type archive pages (e.g. <code>/formacao/</code>). These apply when the archive is not a WordPress page. For WordPress pages, set the description directly in the page editor using the <strong>SEO Description</strong> meta box.</p>
                <table class="form-table">
                    <?php
                    $archive_descs = $settings['archive_descriptions'] ?? [];
                    $archives = [
                        'formacao'       => 'Formação (/formacao/)',
                        'curso'          => 'Curso (/curso/)',
                        'course'         => 'Course (/course/)',
                        'escola'         => 'Escola (/escola/)',
                        'eventos'        => 'Eventos (/eventos/)',
                    ];
                    foreach ( $archives as $slug => $label ) :
                    ?>
                    <tr>
                        <th><label for="archive_desc_<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></label></th>
                        <td>
                            <textarea
                                id="archive_desc_<?php echo esc_attr( $slug ); ?>"
                                name="edit_seo_fix_settings[archive_descriptions][<?php echo esc_attr( $slug ); ?>]"
                                rows="2"
                                maxlength="160"
                                class="large-text"
                            ><?php echo esc_textarea( $archive_descs[ $slug ] ?? '' ); ?></textarea>
                            <p class="description"><?php echo esc_html( mb_strlen( $archive_descs[ $slug ] ?? '' ) ); ?> / 160 characters</p>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button( 'Save Settings' ); ?>
            </form>

            <hr>
            <h2>Dashboards</h2>
            <p class="description">First-party analytics — no cookies, admin-only. Opens directly while you're logged in.</p>
            <table class="form-table">
                <tr>
                    <th>Search Insights</th>
                    <td>
                        <a href="<?php echo esc_url( home_url( '/search-insights/' ) ); ?>" target="_blank" class="button button-primary">Open Search Insights →</a>
                        <p class="description">What visitors search for on-site. Leads with <strong>zero-result queries</strong> — the demand gaps: courses and content people look for but can't find.</p>
                    </td>
                </tr>
                <tr>
                    <th>Bootcamp Analytics</th>
                    <td>
                        <a href="<?php echo esc_url( home_url( '/bootcamp-analytics/?page=aai' ) ); ?>" target="_blank" class="button">Open Bootcamp Analytics →</a>
                        <p class="description">Engagement on bootcamp product pages — section reach, scroll depth, video, CTA clicks.</p>
                    </td>
                </tr>
            </table>

            <hr>
            <h2>Tools</h2>
            <p class="description">One-time actions to fix server-side files.</p>
            <table class="form-table">
                <tr>
                    <th>robots.txt</th>
                    <td>
                        <button id="edit-seo-fix-robots" class="button button-secondary">Fix robots.txt (remove duplicate)</button>
                        <span id="edit-seo-fix-robots-result" style="margin-left:10px;"></span>
                        <p class="description">Rewrites the physical robots.txt file on the server with a clean, deduplicated version.</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * List all identified SEO issues and whether they're auto-fixed.
     * Dynamically reflects active modules and runtime conditions.
     */
    private static function get_current_issues(): array {
        $settings    = get_option( 'edit_seo_fix_settings', [] );
        $rank_math        = defined( 'RANK_MATH_VERSION' );
        $wpml             = defined( 'ICL_SITEPRESS_VERSION' );
        $polylang         = function_exists( 'pll_the_languages' );
        $fix_alts         = ! empty( $settings['fix_image_alts'] );
        $fix_ob           = ! empty( $settings['fix_output_buffer'] );
        $fix_ga           = ! empty( $settings['fix_duplicate_ga'] );
        $fix_fonts        = ! empty( $settings['preload_fonts'] );
        $fix_scripts      = ! empty( $settings['defer_scripts'] );
        $has_ga_id        = ! empty( $settings['primary_ga_id'] );
        $force_meta       = ! empty( $settings['force_meta_output'] );
        $hreflang_ok      = $wpml || $polylang || true; // plugin always outputs pt-PT (Rank Math Free excluded)

        // Meta/canonical/OG/Twitter: fixed by Rank Math when active, or by this plugin via force_meta
        $meta_fixed = $rank_math || $force_meta;

        return [
            [ 'issue' => 'Missing meta description on all pages',                              'severity' => 'Critical', 'fixed' => $meta_fixed ],
            [ 'issue' => 'Missing H1 tag on homepage and inner pages',                         'severity' => 'Critical', 'fixed' => false, 'manual' => true ],
            [ 'issue' => 'No canonical link tag on any page',                                  'severity' => 'Critical', 'fixed' => $meta_fixed ],
            [ 'issue' => 'Missing og:title, og:description, og:type Open Graph tags',          'severity' => 'High',     'fixed' => $meta_fixed ],
            [ 'issue' => 'og:image:width and og:image:height missing',                         'severity' => 'Medium',   'fixed' => $meta_fixed ],
            [ 'issue' => 'og:locale missing (site is pt-PT)',                                  'severity' => 'Medium',   'fixed' => $meta_fixed ],
            [ 'issue' => 'Twitter/X card meta tags completely missing',                        'severity' => 'High',     'fixed' => $meta_fixed ],
            [ 'issue' => 'sitemap_index.xml returns 404 (referenced in robots.txt)',           'severity' => 'Critical', 'fixed' => true ],
            [ 'issue' => 'sitemap.xml returns 404',                                            'severity' => 'Critical', 'fixed' => true ],
            [ 'issue' => 'robots.txt generated by smallseotools.com (third-party branding)',   'severity' => 'Low',      'fixed' => true ],
            [ 'issue' => 'robots.txt references wrong sitemap URL',                            'severity' => 'High',     'fixed' => true ],
            [ 'issue' => 'Images missing alt text (course images, icons)',                     'severity' => 'High',     'fixed' => $fix_alts ],
            [ 'issue' => 'Theme/widget images missing alt text and lazy loading (87% of imgs)', 'severity' => 'High',     'fixed' => $fix_ob ],
            [ 'issue' => 'Render-blocking scripts: GA, GTM, Moove GDPR loading sync',         'severity' => 'High',     'fixed' => $fix_scripts ],
            [ 'issue' => 'No preconnect/dns-prefetch for Google Fonts & analytics',            'severity' => 'Medium',   'fixed' => $fix_fonts ],
            [ 'issue' => 'Google Fonts missing font-display: swap (FOIT risk)',                'severity' => 'Medium',   'fixed' => $fix_fonts ],
            [ 'issue' => 'Duplicate analytics: UA + GA4 + GTM firing simultaneously',         'severity' => 'High',     'fixed' => $fix_ga && $has_ga_id ],
            [ 'issue' => 'Legacy UA tracking active (UA sunset July 2024)',                    'severity' => 'Critical', 'fixed' => $fix_ga ],
            [ 'issue' => 'No hreflang tags (site serves Portuguese speakers)',                 'severity' => 'Medium',   'fixed' => $hreflang_ok ],
            [ 'issue' => 'No robots meta tag on pages',                                        'severity' => 'Medium',   'fixed' => $meta_fixed ],
            [ 'issue' => 'Pagination pages not noindexed (thin/duplicate content risk)',       'severity' => 'Medium',   'fixed' => $meta_fixed ],
            [ 'issue' => 'Search results pages not noindexed',                                 'severity' => 'Medium',   'fixed' => $meta_fixed ],
            [ 'issue' => 'Schema Organization lacks telephone, address fields',                'severity' => 'Medium',   'fixed' => true ],
            [ 'issue' => 'WebPage schema missing @id and inLanguage fields',                   'severity' => 'Low',      'fixed' => $rank_math ],
            [ 'issue' => 'No Article schema on blog posts',                                    'severity' => 'Medium',   'fixed' => $rank_math ],
            [ 'issue' => 'No BreadcrumbList schema on inner pages',                            'severity' => 'Medium',   'fixed' => $rank_math ],
            [ 'issue' => 'No CSS preload link hint for main stylesheet',                       'severity' => 'Low',      'fixed' => $fix_fonts ],
            [ 'issue' => 'Heading hierarchy unclear (H2/H3 without H1 visible)',               'severity' => 'High',     'fixed' => false, 'manual' => true ],
        ];
    }

    public static function show_audit_summary() {
        $screen = get_current_screen();
        if ( $screen && $screen->id !== 'settings_page_edit-seo-fix' ) return;
        // Only show on plugin settings page — rendered inline
    }

    public static function enqueue_admin_assets( string $hook ) {
        if ( $hook !== 'settings_page_edit-seo-fix' ) return;

        wp_enqueue_media();
        wp_enqueue_style(
            'edit-seo-fix-admin',
            WEAREDIT_SITE_ENGINE_URL . 'assets/admin.css',
            [],
            WEAREDIT_SITE_ENGINE_VERSION
        );
        wp_enqueue_script(
            'edit-seo-fix-admin',
            WEAREDIT_SITE_ENGINE_URL . 'assets/admin.js',
            [ 'jquery', 'media-upload' ],
            WEAREDIT_SITE_ENGINE_VERSION,
            true
        );
    }
}
