<?php
/**
 * WordPress mail sender override.
 *
 * WordPress defaults wp_mail() to From: wordpress@<host>, which fails
 * deliverability checks on every modern inbox (no DKIM/SPF alignment).
 * Force every site-side email to ship from a Brevo-verified sender so
 * CF7 confirmations, comment notifications, password resets, etc. all
 * land in inboxes instead of spam.
 *
 * Sender locked to geral@weareedit.io / "EDIT." 2026-06-01 per Daniel.
 *
 * @package WeareEditSiteEngine
 * @since   1.5.193
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_WP_Mail_Sender {

    const SENDER_EMAIL = 'geral@weareedit.io';
    const SENDER_NAME  = 'EDIT.';

    public static function init(): void {
        // Priority 99 so we win over plugins setting their own From at default 10.
        add_filter( 'wp_mail_from',      [ __CLASS__, 'filter_from' ],      99 );
        add_filter( 'wp_mail_from_name', [ __CLASS__, 'filter_from_name' ], 99 );
    }

    public static function filter_from( string $from ): string {
        return self::SENDER_EMAIL;
    }

    public static function filter_from_name( string $name ): string {
        return self::SENDER_NAME;
    }
}
