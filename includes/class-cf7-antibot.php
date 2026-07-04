<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CF7 Anti-Bot — silent honeypot for the "Fale Conosco" contact forms.
 *
 * Background (2026-07-04): automated spam escalated on the WordPress CF7
 * contact form — mail.ru / inbox.ru / list.ru senders, gibberish names, and
 * the literal CF7 "[your-subject]" placeholder left unfilled. These bots POST
 * every field, so a hidden honeypot field they can't help filling drops them
 * with ZERO user friction and ZERO captcha — and zero false positives, since
 * a real person never sees (let alone fills) a visually + a11y hidden field.
 *
 * Defence in depth: this runs UNDER the Cloudflare edge rate-limit rule on the
 * CF7 endpoint and (recommended) Cloudflare Turnstile on the form itself.
 */
class EDIT_CF7_AntiBot {

	/** Honeypot field name — looks like a real field so bots fill it; humans never see it. */
	const HP_FIELD = 'edit-website-url';

	public function __construct() {
		add_filter( 'wpcf7_form_elements', array( $this, 'inject_honeypot' ) );
		// Priority 9 so it runs before other spam filters and short-circuits cheaply.
		add_filter( 'wpcf7_spam', array( $this, 'detect_bot' ), 9, 2 );
	}

	/**
	 * Append a visually + screen-reader hidden honeypot input to every CF7 form.
	 * Cache-safe: static markup, so WP Rocket / page cache serve it unchanged.
	 */
	public function inject_honeypot( $content ) {
		$hp  = '<div aria-hidden="true" style="position:absolute!important;left:-9999px!important;top:-9999px!important;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;">';
		$hp .= '<label>Não preencher <input type="text" name="' . esc_attr( self::HP_FIELD ) . '" tabindex="-1" autocomplete="off" value=""></label>';
		$hp .= '</div>';
		return $content . $hp;
	}

	/**
	 * Flag the submission as spam if the honeypot was filled. CF7 then blocks the
	 * mail and records it under Flamingo/spam rather than delivering it.
	 *
	 * @param bool  $spam       Current spam verdict from earlier filters.
	 * @param mixed $submission WPCF7_Submission (optional; not present on older CF7).
	 */
	public function detect_bot( $spam, $submission = null ) {
		if ( $spam ) {
			return $spam;
		}
		$val = isset( $_POST[ self::HP_FIELD ] ) ? trim( (string) wp_unslash( $_POST[ self::HP_FIELD ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $val ) {
			if ( $submission && method_exists( $submission, 'add_spam_log' ) ) {
				$submission->add_spam_log(
					array(
						'agent'  => 'edit_cf7_antibot',
						'reason' => 'honeypot field filled (' . self::HP_FIELD . ')',
					)
				);
			}
			return true;
		}
		return $spam;
	}
}

new EDIT_CF7_AntiBot();
