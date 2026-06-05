<?php
/**
 * EDIT. CF7 BCC Archive
 * ─────────────────────────────────────────────────────────────────────────────
 * Appends `Bcc: geral@weareedit.io` to every CF7 admin-notification email so
 * that ALL form submissions across the site are archived to the shared inbox,
 * regardless of per-form recipient configuration.
 *
 * Scope:
 *   - Only Mail 1 (admin notification — the message that goes TO us)
 *   - SKIPS Mail 2 (auto-reply — that one goes TO the form submitter; copying
 *     ourselves on it would be noise + would BCC the submitter's address)
 *
 * Filter:
 *   - wpcf7_mail_components @ priority 1000
 *     (runs after EDIT_CF7_Mail_Tags::replace_curly_tokens at 999, so curly-brace
 *     tokens are fully resolved before we touch the headers)
 *
 * Logging:
 *   - Writes a one-line entry to the CF7 Debug Log every time it injects the
 *     Bcc, so we can audit which forms triggered an archive copy.
 *
 * @since 1.5.281
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_CF7_BCC_Archive {

    const ARCHIVE_ADDRESS = 'geral@weareedit.io';

    public static function init(): void {
        add_filter( 'wpcf7_mail_components', [ __CLASS__, 'inject_bcc' ], 1000, 3 );
    }

    /**
     * Append Bcc: geral@weareedit.io to Mail 1 (admin notification) headers.
     *
     * @param array  $components    CF7 mail components (subject, body, recipient, additional_headers, ...).
     * @param object $contact_form  WPCF7_ContactForm instance.
     * @param object $mail          WPCF7_Mail instance.
     * @return array
     */
    public static function inject_bcc( $components, $contact_form, $mail ) {
        // Skip Mail 2 (auto-reply). Mail name 'mail_2' goes TO the form submitter.
        $mail_name = '';
        if ( is_object( $mail ) ) {
            if ( method_exists( $mail, 'name' ) ) {
                $mail_name = (string) $mail->name();
            } elseif ( method_exists( $mail, 'template_name' ) ) {
                $mail_name = (string) $mail->template_name();
            } elseif ( property_exists( $mail, 'name' ) ) {
                $mail_name = (string) $mail->name;
            }
        }
        if ( $mail_name === 'mail_2' ) {
            return $components;
        }

        // Skip if the archive address is already the recipient (don't BCC ourselves
        // if we're already the To: target).
        $recipient = isset( $components['recipient'] ) ? (string) $components['recipient'] : '';
        if ( stripos( $recipient, self::ARCHIVE_ADDRESS ) !== false ) {
            return $components;
        }

        $headers = isset( $components['additional_headers'] ) ? (string) $components['additional_headers'] : '';

        // Skip if the archive address is already in any Bcc: line (avoid dupes
        // when a form maintainer has already wired it manually).
        if ( preg_match( '/^Bcc:.*' . preg_quote( self::ARCHIVE_ADDRESS, '/' ) . '/im', $headers ) ) {
            return $components;
        }

        $bcc_line = 'Bcc: ' . self::ARCHIVE_ADDRESS;

        // Append on a new line, preserving any existing headers.
        if ( $headers === '' ) {
            $components['additional_headers'] = $bcc_line;
        } else {
            // Ensure trailing newline before appending.
            $components['additional_headers'] = rtrim( $headers, "\r\n" ) . "\r\n" . $bcc_line;
        }

        // Audit log entry (visible in Tools → CF7 Debug Log).
        if ( class_exists( 'EDIT_CF7_Debug' ) ) {
            EDIT_CF7_Debug::write( [
                'event'      => 'bcc_archive_injected',
                'form_id'    => $contact_form && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0,
                'form_title' => $contact_form && method_exists( $contact_form, 'title' ) ? (string) $contact_form->title() : '',
                'mail_name'  => $mail_name,
                'recipient'  => $recipient,
                'bcc'        => self::ARCHIVE_ADDRESS,
            ] );
        }

        return $components;
    }
}
