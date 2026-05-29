<?php
/**
 * CF7 Mail Tag Resolver — replaces curly-brace `{tokens}` in CF7 email
 * templates with live data from the formacao post the form was submitted
 * from.
 *
 * Background (incident 2026-05-29): the Pedido de Informação confirmation
 * email shipped with literal placeholders for Horário and Local/Formato
 * because CF7 only natively resolves square-bracket `[tags]` that map to
 * form fields. Curly-brace `{horario_workshop}` and `{local_workshop}`
 * passed through untouched.
 *
 * This class intercepts wpcf7_mail_components (Subject, Body, Recipient,
 * Headers) and runs a str_replace pass for the supported tokens. The
 * resolved post is determined from the submission URL (Referrer-equivalent)
 * because the floating popup forms ship with `_wpcf7_container_post=0`.
 *
 * Also registers the same tokens as CF7-native `[token]` special mail tags
 * for forward-compat, so future templates can use either syntax.
 *
 * Supported tokens (auto-resolved from the containing formacao post):
 *   {horario_workshop} — ACF bloco_informacao.horarios → fallback
 *                        horario_formacao taxonomy term name
 *   {local_workshop}   — localizacao taxonomy term name(s), comma-joined
 *   {nome_workshop}    — post_title (stripped of HTML)
 *   {data_workshop}    — ACF home_data → fallback horario_formacao.data
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_CF7_Mail_Tags {

    public static function init(): void {
        add_filter( 'wpcf7_mail_components',  [ __CLASS__, 'replace_curly_tokens' ], 10, 3 );
        add_filter( 'wpcf7_special_mail_tags', [ __CLASS__, 'resolve_special_tag' ], 10, 4 );
    }

    /**
     * Replace curly-brace tokens in all mail components at send time.
     */
    public static function replace_curly_tokens( $components, $contact_form, $mail ) {
        $tokens = self::resolve_workshop_tokens();
        if ( empty( $tokens ) ) return $components;

        foreach ( [ 'subject', 'body', 'recipient', 'additional_headers' ] as $key ) {
            if ( ! isset( $components[ $key ] ) || ! is_string( $components[ $key ] ) ) continue;
            foreach ( $tokens as $token => $value ) {
                if ( $value === '' ) continue;
                $components[ $key ] = str_replace( '{' . $token . '}', $value, $components[ $key ] );
            }
        }
        return $components;
    }

    /**
     * Also expose the same tokens via CF7 native square-bracket syntax so
     * templates can opt in to `[horario_workshop]` if preferred. Note: CF7
     * strips leading underscores for special mail tags — handle both forms.
     */
    public static function resolve_special_tag( $output, $name, $html = false, $mail_tag = null ) {
        $name   = is_object( $mail_tag ) && method_exists( $mail_tag, 'field_name' ) ? $mail_tag->field_name() : $name;
        $name   = ltrim( (string) $name, '_' );
        $tokens = self::resolve_workshop_tokens();
        if ( isset( $tokens[ $name ] ) && $tokens[ $name ] !== '' ) {
            return $tokens[ $name ];
        }
        return $output;
    }

    /**
     * Build the token → value map from the formacao post the form was
     * submitted from.
     */
    private static function resolve_workshop_tokens(): array {
        $post_id = self::get_form_post_id();
        if ( ! $post_id ) return [];

        $tokens = [];

        // ── Horário ────────────────────────────────────────────────────
        $horario = '';
        if ( function_exists( 'get_field' ) ) {
            $bloco = get_field( 'bloco_informacao', $post_id );
            if ( is_array( $bloco ) && ! empty( $bloco['horarios'] ) ) {
                $horario = trim( wp_strip_all_tags( $bloco['horarios'] ) );
            }
        }
        if ( ! $horario ) {
            $terms = get_the_terms( $post_id, 'horario_formacao' );
            if ( is_array( $terms ) && ! empty( $terms ) ) {
                $horario = trim( wp_strip_all_tags( $terms[0]->name ) );
            }
        }
        $tokens['horario_workshop'] = $horario;

        // ── Local / Formato ────────────────────────────────────────────
        $local = '';
        $loc_terms = get_the_terms( $post_id, 'localizacao' );
        if ( is_array( $loc_terms ) && ! empty( $loc_terms ) ) {
            $names = array_filter( array_map( function ( $t ) {
                return isset( $t->name ) ? trim( wp_strip_all_tags( $t->name ) ) : '';
            }, $loc_terms ) );
            $local = implode( ', ', $names );
        }
        $tokens['local_workshop'] = $local;

        // ── Nome do workshop (post title) ──────────────────────────────
        $title = get_the_title( $post_id );
        if ( $title ) {
            $tokens['nome_workshop'] = trim( wp_strip_all_tags( $title ) );
        }

        // ── Data (defensive — if the existing mechanism stops working) ──
        $data = '';
        if ( function_exists( 'get_field' ) ) {
            $data = (string) get_field( 'home_data', $post_id );
            if ( ! $data ) {
                $h = get_field( 'horario_formacao', $post_id );
                if ( is_array( $h ) && ! empty( $h['data'] ) ) $data = (string) $h['data'];
            }
        }
        if ( $data ) {
            $tokens['data_workshop'] = $data;
        }

        return $tokens;
    }

    /**
     * Resolve the formacao post ID the submission came from.
     *
     * Tries (in order):
     *   1. CF7's container_post_id meta — works when the form is rendered
     *      inline in the post template.
     *   2. The submission URL meta (CF7 stores the originating URL) — works
     *      for floating popup forms whose container_post_id is 0.
     *   3. HTTP_REFERER fallback — final safety net for edge cases.
     *
     * Only accepts post IDs of type `formacao`.
     */
    private static function get_form_post_id(): int {
        if ( ! class_exists( 'WPCF7_Submission' ) ) return 0;
        $sub = WPCF7_Submission::get_instance();
        if ( ! $sub ) return 0;

        $candidates = [];

        $meta_id = (int) $sub->get_meta( 'container_post_id' );
        if ( $meta_id ) $candidates[] = $meta_id;

        $url = (string) $sub->get_meta( 'url' );
        if ( $url ) {
            $id = url_to_postid( $url );
            if ( $id ) $candidates[] = $id;
        }

        if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
            $ref = wp_unslash( $_SERVER['HTTP_REFERER'] );
            $id  = url_to_postid( $ref );
            if ( $id ) $candidates[] = $id;
        }

        foreach ( $candidates as $id ) {
            if ( $id && get_post_type( $id ) === 'formacao' ) return (int) $id;
        }
        return 0;
    }
}
