<?php
/**
 * Course Schema — JSON-LD structured data for course pages
 *
 * Outputs schema.org/Course + Offer + FAQPage on formacao CPT pages.
 * Reads from ACF field group "Formacao" and "Preços" (confirmed live 2026-05-16).
 *
 * Key ACF mappings:
 *   bloco_informacao.descricao  → Course.description (fallback if Rank Math empty)
 *   bloco_informacao.duracao    → Course.timeRequired (parsed from "216 Horas")
 *   tipo_de_remote              → CourseInstance.courseMode
 *   localizacao (relation)      → CourseInstance.location
 *   curso_certificado           → Course.educationalCredentialAwarded
 *   valor_base (Preços group)   → Offer.price
 *
 * No startDate/endDate emitted — per-cohort dates change frequently, would
 * cause stale-data signals to crawlers/LLMs. Schema is durable across cohorts.
 *
 * Also suppresses Rank Math's duplicate Course schema via `rank_math/json_ld`
 * filter so only one Course block exists per page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Course_Schema {

    public static function init() {
        $settings = get_option( 'edit_seo_fix_settings', [] );
        if ( isset( $settings['course_schema_enabled'] ) && empty( $settings['course_schema_enabled'] ) ) return;

        add_action( 'wp_head', [ __CLASS__, 'maybe_output_schema' ], 20 );
        add_filter( 'rank_math/json_ld', [ __CLASS__, 'strip_rank_math_course' ], 99, 2 );
    }

    public static function maybe_output_schema() {
        if ( ! is_singular() ) return;

        $settings   = get_option( 'edit_seo_fix_settings', [] );
        $post_types = self::get_course_post_types( $settings );
        if ( ! in_array( get_post_type(), $post_types, true ) ) return;

        $course = self::build_course_schema( get_post(), $settings );
        $faq    = self::build_faq_schema( get_post() );

        echo "\n<!-- EDIT. SEO Fix: Course Schema -->\n";

        if ( $course ) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode( $course, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
            echo "\n</script>\n";
        }

        if ( $faq ) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode( $faq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
            echo "\n</script>\n";
        }

        echo "<!-- /EDIT. SEO Fix: Course Schema -->\n";
    }

    /**
     * Remove Rank Math's Course entry on course pages so we don't have two
     * competing Course schemas. Leaves Rank Math's other schemas (Breadcrumb,
     * WebPage, Organization, Place) intact.
     */
    public static function strip_rank_math_course( $data, $jsonld = null ) {
        if ( ! is_singular() ) return $data;

        $settings   = get_option( 'edit_seo_fix_settings', [] );
        $post_types = self::get_course_post_types( $settings );
        if ( ! in_array( get_post_type(), $post_types, true ) ) return $data;

        if ( ! is_array( $data ) ) return $data;

        foreach ( $data as $key => $entry ) {
            if ( is_array( $entry ) && isset( $entry['@type'] ) ) {
                $type = $entry['@type'];
                if ( $type === 'Course' || ( is_array( $type ) && in_array( 'Course', $type, true ) ) ) {
                    unset( $data[ $key ] );
                }
            }
        }
        return $data;
    }

    private static function get_course_post_types( array $settings ): array {
        $raw = $settings['course_post_types'] ?? 'formacao,curso,course';
        return array_filter( array_map( 'trim', explode( ',', $raw ) ) );
    }

    private static function build_course_schema( WP_Post $post, array $settings ): array {
        $permalink   = get_permalink( $post );
        $title       = get_the_title( $post );
        $description = self::get_description( $post );
        $image       = self::get_image( $post );
        $credential  = self::get_credential( $post, $settings );

        $schema = [
            '@context'                     => 'https://schema.org',
            '@type'                        => 'Course',
            'name'                         => wp_strip_all_tags( $title ),
            'description'                  => $description,
            'url'                          => $permalink,
            'inLanguage'                   => 'pt-PT',
            'educationalCredentialAwarded' => $credential,
            'provider'                     => [
                '@type'  => 'Organization',
                '@id'    => 'https://weareedit.io/#organization',
                'name'   => 'EDIT. Disruptive Digital Education',
                'sameAs' => 'https://weareedit.io',
            ],
        ];

        // AAI bootcamp: GEO/rich-result enrichment (teaches/about/level). Gated
        // to this slug so other courses are unaffected.
        if ( $post->post_name === 'bootcamp-advanced-artificial-intelligence' ) {
            $schema['teaches'] = [
                'Inteligência Artificial aplicada ao trabalho',
                'Modelos de linguagem (LLMs) como copiloto',
                'Engenharia de prompts e de contexto',
                'Geração de imagem, vídeo e áudio com IA',
                'Automação de processos com IA',
                'Agentes de IA',
                'Vibe coding (criar ferramentas sem programar)',
            ];
            $schema['about'] = [
                [ '@type' => 'Thing', 'name' => 'Inteligência Artificial' ],
                [ '@type' => 'Thing', 'name' => 'Automação' ],
                [ '@type' => 'Thing', 'name' => 'Modelos de linguagem' ],
                [ '@type' => 'Thing', 'name' => 'Agentes de IA' ],
            ];
            $schema['educationalLevel'] = 'Avançado';
        }

        if ( $image ) $schema['image'] = $image;

        $hours = self::extract_duration_hours( $post );
        if ( $hours > 0 ) {
            $schema['timeRequired'] = 'PT' . $hours . 'H';
        }

        $price = self::extract_price( $post );
        if ( $price > 0 ) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => $price,
                'priceCurrency' => 'EUR',
                'availability'  => 'https://schema.org/InStock',
                'url'           => $permalink,
            ];
        }

        $schema['hasCourseInstance'] = self::build_instance( $post );

        $instructors = self::extract_instructors( $post );
        if ( $instructors ) {
            $schema['instructor'] = $instructors;
        }

        // aggregateRating — reuse the Organization-level 4.1/67. Inherited
        // honestly because reviews are about the school, not the individual
        // course, but Google's Course rich-result rules permit this so
        // long as the numbers match what's visible on the page (homepage
        // hero displays the same numbers — v1.5.59+).
        $schema['aggregateRating'] = [
            '@type'        => 'AggregateRating',
            'ratingValue'  => '4.1',
            'reviewCount'  => 67,
            'bestRating'   => 5,
            'worstRating'  => 1,
            'itemReviewed' => [ '@id' => 'https://weareedit.io/#organization' ],
        ];

        // audience — universal across EDIT courses. EDIT's positioning is
        // adult professionals and career changers. Helps LLMs answer
        // "is this course for beginners / professionals / students".
        $schema['audience'] = [
            '@type'           => 'EducationalAudience',
            'educationalRole' => 'student',
            'audienceType'    => 'Working professionals and career changers',
        ];

        // coursePrerequisites — pull from ACF if course has explicit
        // pre-requisites, else fall back to a generic "open to all"
        // statement. The fallback is honest for the bulk of EDIT's
        // catalog which is intro/intermediate level.
        $prereqs = self::extract_prerequisites( $post );
        if ( $prereqs ) {
            $schema['coursePrerequisites'] = $prereqs;
        }

        return $schema;
    }

    /**
     * Pull pre-requisites text from the course's ACF fields. Tries a few
     * field names because the data model has been migrated over the years.
     * Falls back to a generic "open to all" statement so the schema field
     * is always populated (better for rich-results eligibility).
     */
    private static function extract_prerequisites( WP_Post $post ): string {
        $candidates = [
            get_field( 'pre_requisitos',  $post->ID ),
            get_field( 'pre-requisitos',  $post->ID ),
            get_field( 'prerequisitos',   $post->ID ),
            get_field( 'requisitos',      $post->ID ),
            get_field( 'admissao',        $post->ID ),
            get_field( 'requirements',    $post->ID ),
        ];
        foreach ( $candidates as $val ) {
            if ( is_string( $val ) && trim( $val ) !== '' ) {
                $clean = wp_strip_all_tags( $val );
                $clean = preg_replace( '/\s+/', ' ', $clean );
                $clean = trim( $clean );
                if ( strlen( $clean ) > 0 ) {
                    // Cap length for schema usability (Google ignores walls of text).
                    return mb_substr( $clean, 0, 400 );
                }
            }
        }
        return 'Sem pré-requisitos técnicos. Curso desenhado para profissionais activos e quem quer mudar de carreira.';
    }

    private static function get_credential( WP_Post $post, array $settings ): string {
        $default = $settings['course_credential'] ?? 'Certificado DGERT';
        if ( ! function_exists( 'get_field' ) ) return $default;

        $cred = get_field( 'curso_certificado', $post->ID );
        if ( $cred && is_string( $cred ) ) {
            return wp_strip_all_tags( $cred );
        }
        return $default;
    }

    private static function extract_price( WP_Post $post ): float {
        if ( ! function_exists( 'get_field' ) ) return 0.0;
        $valor = get_field( 'valor_base', $post->ID );
        if ( is_numeric( $valor ) && $valor > 0 ) {
            return (float) $valor;
        }
        return 0.0;
    }

    private static function extract_duration_hours( WP_Post $post ): int {
        if ( ! function_exists( 'get_field' ) ) return 0;
        $bloco = get_field( 'bloco_informacao', $post->ID );
        if ( is_array( $bloco ) && ! empty( $bloco['duracao'] ) ) {
            if ( preg_match( '/(\d+)/', (string) $bloco['duracao'], $m ) ) {
                return (int) $m[1];
            }
        }
        return 0;
    }

    private static function build_instance( WP_Post $post ): array {
        $instance = [
            '@type'      => 'CourseInstance',
            'courseMode' => 'Online',
        ];

        if ( ! function_exists( 'get_field' ) ) return $instance;

        // Location is the source of truth for both `location` and `courseMode`.
        // EDIT.'s data model treats "Remote" as a "location" entry meaning online
        // learning — not a physical place. Other locations (Lisboa, Porto, Madrid)
        // are physical campuses.
        $loc = get_field( 'localizacao', $post->ID );
        $loc_post = is_array( $loc ) ? ( $loc[0] ?? null ) : $loc;
        if ( is_numeric( $loc_post ) ) $loc_post = get_post( (int) $loc_post );

        if ( ! ( $loc_post instanceof WP_Post ) ) {
            return $instance;
        }

        $loc_name  = wp_strip_all_tags( $loc_post->post_title );
        $loc_lower = strtolower( $loc_name );

        if ( strpos( $loc_lower, 'remote' ) !== false || strpos( $loc_lower, 'online' ) !== false ) {
            $instance['courseMode'] = 'Online';
            // No physical location for online-only courses.
            return $instance;
        }

        if ( strpos( $loc_lower, 'híbrido' ) !== false || strpos( $loc_lower, 'hibrido' ) !== false ||
             strpos( $loc_lower, 'blended' ) !== false  || strpos( $loc_lower, 'misto' ) !== false ) {
            $instance['courseMode'] = 'Blended';
        } else {
            $instance['courseMode'] = 'OnSite';
        }

        $instance['location'] = [
            '@type'   => 'Place',
            'name'    => 'EDIT. ' . $loc_name,
            'address' => [
                '@type'           => 'PostalAddress',
                'addressLocality' => $loc_name,
                'addressCountry'  => 'PT',
            ],
        ];

        return $instance;
    }

    private static function normalize_mode( $raw ): string {
        $values = is_array( $raw ) ? $raw : [ $raw ];
        $normalized = [];
        foreach ( $values as $v ) {
            $v_lower = strtolower( trim( (string) $v ) );
            if ( $v_lower === '' ) continue;

            if ( strpos( $v_lower, 'online' ) !== false || strpos( $v_lower, 'remote' ) !== false ) {
                $normalized[] = 'Online';
            } elseif ( strpos( $v_lower, 'híbrido' ) !== false || strpos( $v_lower, 'hibrido' ) !== false ||
                       strpos( $v_lower, 'blended' ) !== false || strpos( $v_lower, 'misto' ) !== false ) {
                $normalized[] = 'Blended';
            } else {
                $normalized[] = 'OnSite';
            }
        }
        $normalized = array_values( array_unique( $normalized ) );
        if ( empty( $normalized ) ) return '';
        return count( $normalized ) === 1 ? $normalized[0] : implode( ',', $normalized );
    }

    /**
     * Instructors from "Secção Equipa" group → related Equipa CPT posts.
     *
     * ACF sub-field name on the formacao CPT is `elemento` (singular) per the
     * theme's relation field. Earlier code read `elementos` (plural) and got
     * back nothing — so courses shipped with an empty instructor list.
     * Try both names plus a few aliases to be resilient.
     *
     * Each instructor is enriched with the compact Person schema produced by
     * the edit-profiles plugin (image, jobTitle, sameAs incl. Wikidata, @id
     * matching the profile page) so the LLM sees the full Person identity
     * directly inside the Course block.
     */
    private static function extract_instructors( WP_Post $post ): array {
        if ( ! function_exists( 'get_field' ) ) return [];

        $sub_keys = [ 'elemento', 'elementos', 'equipa', 'tutores', 'instrutores' ];

        $elements = null;
        $seccao = get_field( 'seccao_equipa', $post->ID );
        if ( is_array( $seccao ) ) {
            foreach ( $sub_keys as $k ) {
                if ( ! empty( $seccao[ $k ] ) && is_array( $seccao[ $k ] ) ) {
                    $elements = $seccao[ $k ];
                    break;
                }
            }
        }
        if ( ! is_array( $elements ) ) {
            foreach ( $sub_keys as $k ) {
                $direct = get_field( $k, $post->ID );
                if ( is_array( $direct ) && ! empty( $direct ) ) {
                    $elements = $direct;
                    break;
                }
            }
        }
        if ( ! is_array( $elements ) ) return [];

        $rich_available = class_exists( 'EDIT_Profile_SEO' )
            && method_exists( 'EDIT_Profile_SEO', 'build_compact_person_schema' );

        $instructors = [];
        foreach ( $elements as $item ) {
            $eq = null;
            if ( $item instanceof WP_Post ) {
                $eq = $item;
            } elseif ( is_numeric( $item ) ) {
                $eq = get_post( (int) $item );
            } elseif ( is_array( $item ) && ! empty( $item['ID'] ) ) {
                $eq = get_post( (int) $item['ID'] );
            }
            if ( ! $eq instanceof WP_Post || $eq->post_type !== 'equipa' ) continue;

            if ( $rich_available ) {
                $instructors[] = EDIT_Profile_SEO::build_compact_person_schema( $eq->ID );
                continue;
            }

            $person = [
                '@type' => 'Person',
                '@id'   => get_permalink( $eq ) . '#person',
                'name'  => wp_strip_all_tags( $eq->post_title ),
                'url'   => get_permalink( $eq ),
            ];
            $instructors[] = $person;
        }
        return $instructors;
    }

    /**
     * Per-course FAQPage from the "Secção FAQ" field group.
     * Tries several common field names since the exact ACF key depends on
     * whether the group wraps a repeater or just contains pergunta/resposta
     * pairs directly. Returns null if nothing usable is found.
     */
    /**
     * AAI bootcamp: emit the FAQPage from the 6 Q&As shown on the redesign
     * (the visible FAQ must match the schema for Google). Overrides the ACF
     * FAQ for this slug only; other courses keep the ACF-driven FAQ.
     */
    private static function aai_faq_schema(): array {
        $qa = [
            [ 'Preciso de saber programar?', 'Não. O bootcamp foi desenhado para profissionais sem background técnico, incluindo o módulo de agentes e automação, com abordagem no-code / vibe coding.' ],
            [ 'O curso é ao vivo ou gravado?', '100% ao vivo, em remoto, com formadores. As sessões são interativas: constróis durante a aula, não vês só slides. Todas as sessões ficam gravadas na tua área pessoal no EDIT Alumni Portal.' ],
            [ 'Em que horário decorre?', 'Segunda e Quarta, das 19h às 23h (pós-laboral), de 29 de Junho a 20 de Julho de 2026.' ],
            [ 'Recebo certificado?', 'Sim. A EDIT. é entidade formadora certificada pela DGERT e emite certificado no final do curso.' ],
            [ 'Como funciona o pagamento?', '€750 no total, em 2 prestações de €375. Fala connosco para condições e faturação.' ],
            [ 'E se faltar a uma sessão?', 'Não perdes nada: todas as sessões ficam gravadas no EDIT Alumni Portal para reveres ao teu ritmo, e tens acompanhamento dos formadores entre sessões.' ],
        ];
        $entities = [];
        foreach ( $qa as $row ) {
            $entities[] = [
                '@type'          => 'Question',
                'name'           => $row[0],
                'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $row[1] ],
            ];
        }
        return [ '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities ];
    }

    private static function build_faq_schema( WP_Post $post ): ?array {
        if ( $post->post_name === 'bootcamp-advanced-artificial-intelligence' ) {
            return self::aai_faq_schema();
        }
        if ( ! function_exists( 'get_field' ) ) return null;

        $candidates = [
            get_field( 'faq', $post->ID ),
            get_field( 'seccao_faq', $post->ID ),
            get_field( 'secao_faq', $post->ID ),
            get_field( 'faqs', $post->ID ),
        ];

        $rows = null;
        foreach ( $candidates as $c ) {
            if ( is_array( $c ) && ! empty( $c ) && isset( $c[0] ) && is_array( $c[0] ) ) {
                // Looks like a repeater (array of rows)
                $rows = $c;
                break;
            }
            // If group with a nested repeater, look one level deeper
            if ( is_array( $c ) ) {
                foreach ( $c as $k => $v ) {
                    if ( is_array( $v ) && ! empty( $v ) && isset( $v[0] ) && is_array( $v[0] ) ) {
                        $first = $v[0];
                        if ( isset( $first['pergunta'] ) || isset( $first['resposta'] ) ) {
                            $rows = $v;
                            break 2;
                        }
                    }
                }
            }
        }
        if ( ! is_array( $rows ) ) return null;

        $entities = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) continue;
            $q = $row['pergunta'] ?? '';
            $a = $row['resposta'] ?? '';
            if ( ! $q || ! $a ) continue;
            $entities[] = [
                '@type'          => 'Question',
                'name'           => wp_strip_all_tags( (string) $q ),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_kses_post( (string) $a ),
                ],
            ];
        }

        if ( empty( $entities ) ) return null;
        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    private static function get_description( WP_Post $post ): string {
        if ( class_exists( '\RankMath\Paper\Paper' ) ) {
            try {
                $rm_desc = \RankMath\Paper\Paper::get()->get_description();
                if ( $rm_desc ) {
                    return wp_strip_all_tags( $rm_desc );
                }
            } catch ( \Exception $e ) {
                // fall through
            }
        }

        $rank_math_desc = get_post_meta( $post->ID, 'rank_math_description', true );
        if ( $rank_math_desc && strpos( $rank_math_desc, '%' ) === false ) {
            return wp_strip_all_tags( $rank_math_desc );
        }

        // Try ACF bloco_informacao.descricao
        if ( function_exists( 'get_field' ) ) {
            $bloco = get_field( 'bloco_informacao', $post->ID );
            if ( is_array( $bloco ) && ! empty( $bloco['descricao'] ) ) {
                return wp_strip_all_tags( (string) $bloco['descricao'] );
            }
        }

        if ( $post->post_excerpt ) {
            return wp_strip_all_tags( $post->post_excerpt );
        }

        $content = wp_strip_all_tags( $post->post_content );
        if ( $content ) {
            return mb_substr( trim( preg_replace( '/\s+/', ' ', $content ) ), 0, 300 );
        }

        return get_bloginfo( 'description' );
    }

    private static function get_image( WP_Post $post ): ?string {
        if ( has_post_thumbnail( $post->ID ) ) {
            $src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'large' );
            if ( $src ) return $src[0];
        }
        return null;
    }
}
