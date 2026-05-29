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

        // Defence in depth — also catch wp_mail() in case any sibling system
        // (e-goi addon, Pipedrive bridge) ships emails outside CF7's flow
        // with the same {token} placeholders. Late priority so other filters
        // run first.
        add_filter( 'wp_mail',                 [ __CLASS__, 'wp_mail_token_pass' ], 99, 1 );
    }

    /**
     * Final-stage wp_mail interception. If any other system sends a mail
     * with curly-brace tokens in subject/body/headers, we still substitute.
     */
    public static function wp_mail_token_pass( $args ) {
        if ( ! is_array( $args ) ) return $args;
        $tokens = self::resolve_workshop_tokens();
        if ( empty( $tokens ) ) return $args;

        $changed = false;
        foreach ( [ 'subject', 'message', 'headers' ] as $key ) {
            if ( ! isset( $args[ $key ] ) ) continue;
            $original = $args[ $key ];
            $value    = is_array( $original ) ? implode( "\n", $original ) : (string) $original;
            foreach ( $tokens as $token => $resolved ) {
                if ( $resolved === '' ) continue;
                foreach ( self::token_patterns( $token ) as $regex ) {
                    $value = preg_replace( $regex, $resolved, $value );
                }
            }
            if ( $value !== ( is_array( $original ) ? implode( "\n", $original ) : (string) $original ) ) {
                $args[ $key ] = $value;
                $changed = true;
            }
        }
        if ( $changed ) {
            self::debug_log( [
                'event'   => 'wp_mail_tokens_replaced',
                'subject' => isset( $args['subject'] ) ? mb_substr( (string) $args['subject'], 0, 200 ) : '',
                'to'      => isset( $args['to'] ) ? ( is_array( $args['to'] ) ? implode( ',', $args['to'] ) : (string) $args['to'] ) : '',
            ] );
        }
        return $args;
    }

    /**
     * Append a row to the CF7 debug log so we can trace resolver behavior.
     */
    private static function debug_log( array $row ): void {
        if ( class_exists( 'EDIT_CF7_Debug' ) ) {
            EDIT_CF7_Debug::write( $row );
        }
    }

    /**
     * Replace curly-brace tokens in all mail components at send time.
     */
    public static function replace_curly_tokens( $components, $contact_form, $mail ) {
        $post_id = self::get_form_post_id();
        $tokens  = self::resolve_workshop_tokens();

        // Per-token occurrence count BEFORE replacement, across all common
        // syntaxes (literal {token}, HTML entity encoded variants, with
        // optional whitespace). This is what tells us *why* a token might
        // not be substituting.
        $body         = isset( $components['body'] ) ? (string) $components['body'] : '';
        $body_length  = strlen( $body );
        $occurrences  = [];
        $snippets     = [];
        foreach ( array_keys( $tokens ) as $token ) {
            $patterns = self::token_patterns( $token );
            $occurrences[ $token ] = [];
            foreach ( $patterns as $label => $regex ) {
                $count = preg_match_all( $regex, $body, $m );
                $occurrences[ $token ][ $label ] = (int) $count;
                if ( $count > 0 && empty( $snippets[ $token ] ) ) {
                    // Capture 80 chars around the first match for context
                    $pos = strpos( $body, $m[0][0] );
                    if ( $pos !== false ) {
                        $start = max( 0, $pos - 40 );
                        $snippets[ $token ] = mb_substr( $body, $start, 160 );
                    }
                }
            }
        }

        // Try to identify which CF7 mail this is (Mail 1 = admin notification,
        // Mail 2 = auto-reply to submitter). $mail is a WPCF7_Mail instance.
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

        self::debug_log( [
            'event'        => 'mailtags_resolver',
            'form_id'      => $contact_form && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0,
            'form_title'   => $contact_form && method_exists( $contact_form, 'title' ) ? (string) $contact_form->title() : '',
            'mail_name'    => $mail_name,
            'recipient'    => isset( $components['recipient'] ) ? (string) $components['recipient'] : '',
            'subject'      => isset( $components['subject'] ) ? (string) $components['subject'] : '',
            'resolved_post'=> $post_id,
            'tokens'       => $tokens,
            'body_length'  => $body_length,
            'occurrences'  => $occurrences,
            'snippets'     => $snippets,
            'body_sample'  => mb_substr( $body, 0, 4000 ),
        ] );

        if ( empty( $tokens ) ) return $components;

        // Defensive replacement — matches literal curly braces AND HTML
        // entity-encoded variants (&#123;…&#125; / &lcub;…&rcub; / hex),
        // with optional whitespace inside the braces.
        foreach ( [ 'subject', 'body', 'recipient', 'additional_headers' ] as $key ) {
            if ( ! isset( $components[ $key ] ) || ! is_string( $components[ $key ] ) ) continue;
            foreach ( $tokens as $token => $value ) {
                if ( $value === '' ) continue;
                $patterns = self::token_patterns( $token );
                foreach ( $patterns as $regex ) {
                    $components[ $key ] = preg_replace( $regex, $value, $components[ $key ] );
                }
            }
        }
        return $components;
    }

    /**
     * Build the regex patterns we'll search for, per token. Covers literal
     * curly braces and the four common HTML-encoded variants WordPress can
     * produce when a WYSIWYG editor touches the email template.
     */
    private static function token_patterns( string $token ): array {
        $t = preg_quote( $token, '#' );
        return [
            'literal'    => '#\\{\\s*' . $t . '\\s*\\}#u',
            'entity_dec' => '#&\\#123;\\s*' . $t . '\\s*&\\#125;#u',
            'entity_hex' => '#&\\#x7[bB];\\s*' . $t . '\\s*&\\#x7[dD];#u',
            'entity_nam' => '#&lcub;\\s*' . $t . '\\s*&rcub;#u',
        ];
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
        // Theme stores localização as an ACF relation field → localizacoes CPT
        // (per project_formacao_acf_map.md), NOT a regular taxonomy. Try the
        // ACF relation first; fall back to taxonomy in case some legacy posts
        // use it that way.
        $local = '';
        if ( function_exists( 'get_field' ) ) {
            $loc_rel = get_field( 'localizacao', $post_id );
            if ( $loc_rel ) {
                $names = [];
                $list  = is_array( $loc_rel ) ? $loc_rel : [ $loc_rel ];
                foreach ( $list as $item ) {
                    if ( is_object( $item ) && isset( $item->post_title ) ) {
                        $names[] = $item->post_title;
                    } elseif ( is_numeric( $item ) ) {
                        $t = get_the_title( (int) $item );
                        if ( $t ) $names[] = $t;
                    } elseif ( is_string( $item ) && $item !== '' ) {
                        $names[] = $item;
                    }
                }
                $names = array_filter( array_map( function ( $n ) {
                    return trim( wp_strip_all_tags( (string) $n ) );
                }, $names ) );
                if ( ! empty( $names ) ) $local = implode( ', ', $names );
            }
        }
        if ( ! $local ) {
            $loc_terms = get_the_terms( $post_id, 'localizacao' );
            if ( is_array( $loc_terms ) && ! empty( $loc_terms ) ) {
                $names = array_filter( array_map( function ( $t ) {
                    return isset( $t->name ) ? trim( wp_strip_all_tags( $t->name ) ) : '';
                }, $loc_terms ) );
                $local = implode( ', ', $names );
            }
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

            // Fallback: parse the slug out of /formacao/<slug>/ and resolve
            // via get_page_by_path against the formacao CPT. Handles cases
            // where url_to_postid doesn't match due to query-var quirks.
            if ( preg_match( '#/formacao/([^/?#]+)#', $url, $m ) ) {
                $post = get_page_by_path( $m[1], OBJECT, 'formacao' );
                if ( $post instanceof WP_Post ) $candidates[] = (int) $post->ID;
            }
        }

        if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
            $ref = wp_unslash( $_SERVER['HTTP_REFERER'] );
            $id  = url_to_postid( $ref );
            if ( $id ) $candidates[] = $id;
            if ( preg_match( '#/formacao/([^/?#]+)#', $ref, $m ) ) {
                $post = get_page_by_path( $m[1], OBJECT, 'formacao' );
                if ( $post instanceof WP_Post ) $candidates[] = (int) $post->ID;
            }
        }

        foreach ( $candidates as $id ) {
            if ( $id && get_post_type( $id ) === 'formacao' ) return (int) $id;
        }
        // Last resort — return the first candidate regardless of CPT so the
        // resolver still surfaces something (debug log shows what was used).
        foreach ( $candidates as $id ) {
            if ( $id ) return (int) $id;
        }
        return 0;
    }
}
