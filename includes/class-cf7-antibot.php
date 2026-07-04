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

		// Prefer the parsed CF7 submission fields; fall back to raw $_POST.
		$data = array();
		if ( $submission && method_exists( $submission, 'get_posted_data' ) ) {
			$data = (array) $submission->get_posted_data();
		}
		if ( empty( $data ) ) {
			$data = (array) wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
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

		$reason = '';

		// 1. Honeypot filled — catches bots that scrape the form and fill every field.
		$hp = isset( $data[ self::HP_FIELD ] ) ? trim( (string) $data[ self::HP_FIELD ] ) : '';
		if ( '' !== $hp ) {
			$reason = 'honeypot field filled (' . self::HP_FIELD . ')';
		}

		// 2. Cyrillic anywhere — a PT-language DGERT audience never submits Cyrillic.
		if ( '' === $reason && preg_match( '/\p{Cyrillic}/u', $blob ) ) {
			$reason = 'cyrillic';
		}

		// 3. Links in any field — real contact leads almost never paste URLs.
		if ( '' === $reason && preg_match( '#https?://|www\.[a-z0-9-]|\[url|<a\s#i', $blob ) ) {
			$reason = 'link';
		}

		// 4. Russian / free-mail spam domains (.ru & co.) — zero legit for this audience.
		if ( '' === $reason ) {
			$bad = array( 'mail.ru', 'bk.ru', 'list.ru', 'inbox.ru', 'internet.ru', 'rambler.ru', 'yandex.ru', 'ya.ru', 'mail.ua' );
			if ( preg_match_all( '/[\w.+-]+@([a-z0-9.-]+\.[a-z]{2,})/i', $blob, $m ) ) {
				foreach ( $m[1] as $dom ) {
					$dom = strtolower( $dom );
					if ( '.ru' === substr( $dom, -3 ) || in_array( $dom, $bad, true ) ) {
						$reason = 'spam-email-domain';
						break;
					}
				}
			}
		}

		// 5. Mostly-numeric email local part (au8834386@gmail.com) — bot mailbox pattern.
		if ( '' === $reason && preg_match_all( '/([a-z0-9.+_-]+)@[a-z0-9.-]+\.[a-z]{2,}/i', $blob, $m ) ) {
			foreach ( $m[1] as $local ) {
				$len = strlen( $local );
				if ( $len >= 7 ) {
					$digits = preg_match_all( '/[0-9]/', $local );
					if ( ( $digits / $len ) > 0.6 ) {
						$reason = 'numeric-email';
						break;
					}
				}
			}
		}

		// 6. Gibberish / camelCase single-token name (RobertDyela, ThomasPelia, phuktjpa, Lloydcax).
		if ( '' === $reason ) {
			$name = '';
			foreach ( array( 'nome', 'your-name', 'name', 'nome-completo', 'fullname', 'nome-completo-2' ) as $nk ) {
				if ( ! empty( $values[ $nk ] ) ) {
					$name = trim( $values[ $nk ] );
					break;
				}
			}
			// Real full names carry a space; only inspect single tokens to avoid false positives.
			if ( '' !== $name && false === strpbrk( $name, " \t" ) ) {
				if ( preg_match( '/\p{Ll}\p{Lu}/u', $name ) ) {
					$reason = 'camelcase-name';
				} else {
					$len = function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name );
					if ( $len >= 7 ) {
						$vowels = preg_match_all( '/[aeiouáàâãéêíóôõúü]/iu', $name );
						if ( ( $vowels / $len ) < 0.32 ) {
							$reason = 'gibberish-name';
						}
					}
				}
			}
		}

		if ( '' !== $reason ) {
			if ( $submission && method_exists( $submission, 'add_spam_log' ) ) {
				$submission->add_spam_log(
					array(
						'agent'  => 'edit_cf7_antibot',
						'reason' => $reason,
					)
				);
			}
			return true;
		}
		return $spam;
	}
}

new EDIT_CF7_AntiBot();
