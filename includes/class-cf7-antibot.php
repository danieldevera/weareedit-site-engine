<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CF7 Anti-Bot — content heuristics + honeypot for the "Fale Conosco" forms.
 *
 * Background (2026-07-04): automated spam escalated on the WordPress CF7 contact form
 * (mail.ru / bk.ru / list.ru senders, camelCase/gibberish names, several per hour). The bots
 * POST a hardcoded payload straight to origin.weareedit.io/wp-json/contact-form-7/…/feedback,
 * bypassing the Cloudflare edge, and never load the form — so the honeypot stays empty.
 *
 * IMPORTANT (verified by live test): in CF7 6.1.6 the `wpcf7_spam` filter returning true DOES
 * mark the message as spam (Flamingo) but NO LONGER prevents the mail from being sent. The mail
 * is only reliably stopped by setting $abort in `wpcf7_before_send_mail`. So this class does BOTH:
 *   - wpcf7_spam           -> mark as spam (record-keeping / Flamingo)
 *   - wpcf7_before_send_mail -> $abort = true  (the actual, effective mail block)
 *
 * Detection (tuned for near-zero false positives on a PT-language DGERT audience — real names
 * carry a space + normal vowels, PT/EU emails, no Cyrillic/links): Cyrillic, links in fields,
 * .ru/free-mail domains, mostly-numeric email local part, camelCase/low-vowel single-token names,
 * and the honeypot. Permanent belt-and-suspenders remains a CAPTCHA (Turnstile/reCAPTCHA).
 */
class EDIT_CF7_AntiBot {

	/** Honeypot field name — looks like a real field so scraper-bots fill it; humans never see it. */
	const HP_FIELD = 'edit-website-url';

	/** Russian / free-mail spam domains (plus any *.ru). */
	const BAD_DOMAINS = array( 'mail.ru', 'bk.ru', 'list.ru', 'inbox.ru', 'internet.ru', 'rambler.ru', 'yandex.ru', 'ya.ru', 'mail.ua' );

	/** Per-submission memo so is_bot() runs once even though two hooks consult it. */
	private $memo_key = null;
	private $memo_reason = '';

	public function __construct() {
		add_filter( 'wpcf7_form_elements', array( $this, 'inject_honeypot' ) );
		// Mark as spam (Flamingo log). Does NOT block mail on CF7 6.1.6 — see class docblock.
		add_filter( 'wpcf7_spam', array( $this, 'detect_bot' ), 9, 2 );
		// The effective block: abort the mail before it is sent.
		add_action( 'wpcf7_before_send_mail', array( $this, 'maybe_abort' ), 9, 3 );
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

	/** wpcf7_spam: flag as spam for the record (Flamingo). Not the mail block. */
	public function detect_bot( $spam, $submission = null ) {
		if ( $spam ) {
			return $spam;
		}
		if ( '' !== $this->is_bot( $submission ) ) {
			return true;
		}
		return $spam;
	}

	/** wpcf7_before_send_mail: the reliable block — abort the mail for a detected bot. */
	public function maybe_abort( $contact_form, &$abort, $submission ) {
		$reason = $this->is_bot( $submission );
		if ( '' !== $reason ) {
			$abort = true;
			if ( is_object( $submission ) && method_exists( $submission, 'add_spam_log' ) ) {
				$submission->add_spam_log( array( 'agent' => 'edit_cf7_antibot', 'reason' => $reason ) );
			}
		}
	}

	/** Memoised per submission. Returns the trip reason, or '' if the submission looks human. */
	private function is_bot( $submission ) {
		$key = is_object( $submission ) ? spl_object_id( $submission ) : 0;
		if ( $this->memo_key === $key ) {
			return $this->memo_reason;
		}
		$this->memo_key    = $key;
		$this->memo_reason = $this->evaluate( $submission );
		return $this->memo_reason;
	}

	private function evaluate( $submission ) {
		// The injected honeypot is not a registered field, so it is ABSENT from get_posted_data();
		// merge raw $_POST so the honeypot (and any raw field) is always visible.
		$data = array();
		if ( is_object( $submission ) && method_exists( $submission, 'get_posted_data' ) ) {
			$data = (array) $submission->get_posted_data();
		}
		$data = array_merge( (array) wp_unslash( $_POST ), $data ); // phpcs:ignore WordPress.Security.NonceVerification

		// Honeypot filled -> scraper bot that fills every field.
		$hp = isset( $data[ self::HP_FIELD ] ) ? trim( (string) $data[ self::HP_FIELD ] ) : '';
		if ( '' !== $hp ) {
			return 'honeypot';
		}

		// Flatten visible field values (skip the honeypot + CF7 internals `_wpcf7*`).
		$values = array();
		foreach ( $data as $k => $v ) {
			if ( self::HP_FIELD === $k || ( is_string( $k ) && '_' === substr( $k, 0, 1 ) ) ) {
				continue;
			}
			if ( is_array( $v ) ) {
				$v = implode( ' ', array_map( 'strval', $v ) );
			}
			$values[ $k ] = is_string( $v ) ? $v : '';
		}
		$blob = implode( "\n", $values );

		if ( preg_match( '/\p{Cyrillic}/u', $blob ) ) {
			return 'cyrillic';
		}
		if ( preg_match( '#https?://|www\.[a-z0-9-]|\[url|<a\s#i', $blob ) ) {
			return 'link';
		}

		// Email domain / local-part checks.
		if ( preg_match_all( '/([a-z0-9.+_-]+)@([a-z0-9.-]+\.[a-z]{2,})/i', $blob, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$local  = $match[1];
				$domain = strtolower( $match[2] );
				if ( '.ru' === substr( $domain, -3 ) || in_array( $domain, self::BAD_DOMAINS, true ) ) {
					return 'spam-email-domain';
				}
				$len = strlen( $local );
				if ( $len >= 7 && ( preg_match_all( '/[0-9]/', $local ) / $len ) > 0.6 ) {
					return 'numeric-email';
				}
			}
		}

		// Gibberish / camelCase single-token name (RobertDyela, ThomasPelia, phuktjpa, Lloydcax).
		$name = '';
		foreach ( array( 'your-name', 'nome', 'name', 'nome-completo', 'fullname' ) as $nk ) {
			if ( ! empty( $values[ $nk ] ) ) {
				$name = trim( $values[ $nk ] );
				break;
			}
		}
		// Real full names carry a space; only inspect single tokens to avoid false positives.
		if ( '' !== $name && false === strpbrk( $name, " \t" ) ) {
			if ( preg_match( '/\p{Ll}\p{Lu}/u', $name ) ) {
				return 'camelcase-name';
			}
			$len = function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name );
			if ( $len >= 7 ) {
				$vowels = preg_match_all( '/[aeiouáàâãéêíóôõúü]/iu', $name );
				if ( ( $vowels / $len ) < 0.32 ) {
					return 'gibberish-name';
				}
			}
		}

		return '';
	}
}

new EDIT_CF7_AntiBot();
