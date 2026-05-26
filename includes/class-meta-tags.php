<?php
/**
 * Meta Description + Robots Meta Tag
 * Safe: only outputs if Rank Math has not already done so.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Meta_Tags {

    public static function init() {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        // Rank Math handles these — skip unless admin has forced output
        if ( defined( 'RANK_MATH_VERSION' ) && empty( $settings['force_meta_output'] ) ) {
            return;
        }

        remove_action( 'wp_head', 'wp_generator' );
        add_action( 'wp_head',      [ __CLASS__, 'output_meta_tags' ], 1 );
        add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_box' ] );
        add_action( 'save_post',    [ __CLASS__, 'save_meta_box' ] );
    }

    public static function output_meta_tags() {
        $description = self::get_description();
        $robots      = self::get_robots_content();

        if ( $description ) {
            echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
        }
        echo '<meta name="robots" content="' . esc_attr( $robots ) . '">' . "\n";
    }

    public static function get_description(): string {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        $default  = $settings['default_description'] ?? 'EDIT. é uma escola de educação digital disruptiva em Portugal.';

        if ( is_singular() ) {
            global $post;
            $custom = get_post_meta( $post->ID, '_edit_seo_description', true );
            if ( $custom ) return self::truncate( $custom, 160 );
            if ( has_excerpt( $post->ID ) ) return self::truncate( get_the_excerpt(), 160 );
            $content = wp_strip_all_tags( get_post_field( 'post_content', $post->ID ) );
            if ( $content ) return self::truncate( $content, 160 );
        }

        if ( is_post_type_archive() ) {
            $post_type = get_query_var( 'post_type' );
            $archive_descs = $settings['archive_descriptions'] ?? [];
            if ( ! empty( $archive_descs[ $post_type ] ) ) {
                return self::truncate( $archive_descs[ $post_type ], 160 );
            }
        }

        if ( is_category() || is_tag() || is_tax() ) {
            $desc = wp_strip_all_tags( term_description() );
            if ( $desc ) return self::truncate( $desc, 160 );
        }

        if ( is_search() ) {
            return 'Resultados de pesquisa para "' . esc_html( get_search_query() ) . '" na EDIT.';
        }

        return $default;
    }

    public static function register_meta_box() {
        $post_types = get_post_types( [ 'public' => true ] );
        add_meta_box(
            'edit-seo-description',
            'SEO Description',
            [ __CLASS__, 'render_meta_box' ],
            array_values( $post_types ),
            'normal',
            'high'
        );
    }

    public static function render_meta_box( WP_Post $post ) {
        $description = get_post_meta( $post->ID, '_edit_seo_description', true );
        wp_nonce_field( 'edit_seo_meta_box', 'edit_seo_meta_box_nonce' );
        $len = mb_strlen( $description );
        $color = $len >= 120 && $len <= 160 ? '#00a32a' : ( $len > 0 ? '#dba617' : '#666' );
        ?>
        <p style="margin-bottom:6px">
            <label for="edit_seo_description" style="font-weight:600">Meta Description</label>
        </p>
        <textarea
            id="edit_seo_description"
            name="edit_seo_description"
            rows="3"
            maxlength="160"
            style="width:100%;box-sizing:border-box"
        ><?php echo esc_textarea( $description ); ?></textarea>
        <p style="margin-top:4px;color:<?php echo esc_attr( $color ); ?>" id="edit-seo-desc-hint">
            <span id="edit-seo-char-count"><?php echo $len; ?></span> / 160 characters — ideal: 120–160
        </p>
        <script>
        (function(){
            var ta = document.getElementById('edit_seo_description');
            var count = document.getElementById('edit-seo-char-count');
            var hint  = document.getElementById('edit-seo-desc-hint');
            ta.addEventListener('input', function(){
                var n = ta.value.length;
                count.textContent = n;
                hint.style.color = (n >= 120 && n <= 160) ? '#00a32a' : (n > 0 ? '#dba617' : '#666');
            });
        })();
        </script>
        <?php
    }

    public static function save_meta_box( int $post_id ) {
        if ( ! isset( $_POST['edit_seo_meta_box_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['edit_seo_meta_box_nonce'], 'edit_seo_meta_box' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset( $_POST['edit_seo_description'] ) ) {
            $value = sanitize_text_field( wp_unslash( $_POST['edit_seo_description'] ) );
            if ( $value === '' ) {
                delete_post_meta( $post_id, '_edit_seo_description' );
            } else {
                update_post_meta( $post_id, '_edit_seo_description', $value );
            }
        }
    }

    public static function get_robots_content(): string {
        if ( is_paged() && get_query_var( 'paged' ) > 1 ) return 'noindex, follow';
        if ( is_search() ) return 'noindex, follow';
        if ( is_preview() ) return 'noindex, nofollow';
        return 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1';
    }

    private static function truncate( string $text, int $max ): string {
        $text = trim( preg_replace( '/\s+/', ' ', $text ) );
        if ( mb_strlen( $text ) <= $max ) return $text;
        return mb_substr( $text, 0, mb_strrpos( mb_substr( $text, 0, $max - 3 ), ' ' ) ) . '...';
    }
}
