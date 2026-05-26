<?php
/**
 * Insert a word boundary into decorative post titles.
 *
 * Many weareedit.io course/bootcamp titles store a styled prefix in post_title,
 * e.g. <font color="#ffffff">Curso Online</font></br>Data Science & AI Analytics.
 * When a title is hand-entered without a space after the </br> break, text
 * extractors (Google NLP, InLinks) read the styled prefix and the course name
 * as a single token — "OnlineData Science" — losing the real entity.
 *
 * This filter inserts one space before the </br> break: an invisible trailing
 * space at the end of line 1, zero visual change, so the_title()/get_the_title()
 * always emit a clean word boundary. Idempotent — the rewritten "</font> </br>"
 * no longer contains the matched "</font></br>", so it is never re-matched.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EDIT_Title_Spacing {

    public static function init() {
        add_filter( 'the_title', [ __CLASS__, 'add_word_boundary' ], 10, 1 );
    }

    public static function add_word_boundary( $title ) {
        if ( ! is_string( $title ) || false === strpos( $title, '</font></br>' ) ) {
            return $title;
        }

        return str_replace( '</font></br>', '</font> </br>', $title );
    }
}
