<?php
/**
 * Structured Data — only outputs what Rank Math doesn't already provide.
 * Rank Math handles: WebSite, WebPage, BreadcrumbList, Article.
 * We only add: enhanced Organization schema with address/social profiles.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EDIT_Structured_Data {

    public static function init() {
        // Rank Math Pro outputs Organization schema — skip to avoid duplicates
        if ( defined( 'RANK_MATH_VERSION' ) ) {
            // Filter Rank Math's schema: remove Brazil from areaServed
            add_filter( 'rank_math/json_ld', [ __CLASS__, 'remove_brazil_from_area_served' ], 99 );
            // Enhance Organization entity with high-value fields for LLM citations
            add_filter( 'rank_math/json_ld', [ __CLASS__, 'enhance_organization_schema' ], 100 );
            return;
        }
        add_action( 'wp_head', [ __CLASS__, 'output_schema' ], 99 );
    }

    /**
     * Enhance the Organization / EducationalOrganization entity in Rank Math's
     * JSON-LD with fields it does not provide out of the box.
     *
     * Adds (only if not already set): foundingDate, slogan, description,
     * currenciesAccepted, knowsAbout (topic expertise — strong LLM signal),
     * hasCredential (DGERT certification — trust signal). Also defensively
     * splits any sameAs entry that contains whitespace, repairing data-entry
     * errors where multiple URLs were typed into a single field.
     *
     * @param array $data The full JSON-LD graph array from Rank Math.
     * @return array
     */
    public static function enhance_organization_schema( array $data ): array {
        foreach ( $data as $key => &$entity ) {
            $type    = $entity['@type'] ?? '';
            $is_org  = false;

            if ( is_string( $type ) ) {
                $is_org = in_array( $type, [ 'Organization', 'EducationalOrganization' ], true );
            } elseif ( is_array( $type ) ) {
                $is_org = (bool) array_intersect( [ 'Organization', 'EducationalOrganization' ], $type );
            }

            if ( ! $is_org ) {
                continue;
            }

            // Defensive sameAs cleanup — split on whitespace, keep valid URLs only.
            if ( isset( $entity['sameAs'] ) && is_array( $entity['sameAs'] ) ) {
                $cleaned = [];
                foreach ( $entity['sameAs'] as $url ) {
                    if ( ! is_string( $url ) ) {
                        continue;
                    }
                    foreach ( preg_split( '/\s+/', trim( $url ) ) as $part ) {
                        if ( filter_var( $part, FILTER_VALIDATE_URL ) ) {
                            $cleaned[] = $part;
                        }
                    }
                }
                if ( ! empty( $cleaned ) ) {
                    $entity['sameAs'] = array_values( array_unique( $cleaned ) );
                }
            }

            // Additive fields — preserve any value Rank Math has already set.
            $entity['foundingDate']        = $entity['foundingDate']        ?? '2013';
            $entity['slogan']              = $entity['slogan']              ?? 'Future Proof Education';
            $entity['currenciesAccepted']  = $entity['currenciesAccepted']  ?? 'EUR';
            $entity['description']         = $entity['description']         ?? 'EDIT. é uma escola de educação digital disruptiva em Portugal, certificada DGERT, com formação em UX/UI Design, Inteligência Artificial, Data Science, Marketing Digital, Programação e Gestão de Negócios. Mais de 1000 alunos por ano. Campus em Lisboa e Porto. Toda a formação é elegível para Cheque-Formação e dedutível no IRS.';

            $entity['knowsAbout'] = $entity['knowsAbout'] ?? [
                'Marketing Digital',
                'User Experience Design',
                'User Interface Design',
                'Data Science',
                'Business Analytics',
                'Inteligência Artificial',
                'Generative AI',
                'Programação',
                'Front-End Development',
                'Product Management',
                'Project Management',
                'SEO',
                'Web Analytics',
            ];

            if ( ! isset( $entity['hasCredential'] ) ) {
                $entity['hasCredential'] = [
                    '@type'              => 'EducationalOccupationalCredential',
                    'credentialCategory' => 'certificate',
                    'name'               => 'DGERT — Direção-Geral do Emprego e das Relações de Trabalho',
                    'url'                => 'https://www.dgert.gov.pt/',
                ];
            }

            // Weighted aggregate of Google Business reviews across both campuses.
            // Lisboa: 4.2 × 36 reviews + Porto: 4.0 × 31 reviews → 4.1 over 67 total.
            // Update these numbers when re-syncing from the live Google Business
            // profiles. Numbers must always reflect the real Google data — fake
            // ratings are a structured-data policy penalty.
            if ( ! isset( $entity['aggregateRating'] ) ) {
                $entity['aggregateRating'] = [
                    '@type'       => 'AggregateRating',
                    'ratingValue' => '4.1',
                    'reviewCount' => 67,
                    'bestRating'  => 5,
                    'worstRating' => 1,
                ];
            }

            // Append the EDIT. Wikidata entity (Q139907765) to sameAs so LLMs
            // and Google KG can resolve the homepage → Wikidata → Daniel chain.
            $wikidata_org = 'https://www.wikidata.org/wiki/Q139907765';
            if ( ! isset( $entity['sameAs'] ) || ! is_array( $entity['sameAs'] ) ) {
                $entity['sameAs'] = [];
            }
            if ( ! in_array( $wikidata_org, $entity['sameAs'], true ) ) {
                $entity['sameAs'][] = $wikidata_org;
            }

            // Founder — closes the Person link chain. Carries Daniel's
            // Wikidata Q139907903 in his sameAs so the graph is self-contained
            // (Google KG / LLMs can lift the founder relationship directly
            // from the homepage without crawling /equipa/daniel-devera/).
            if ( ! isset( $entity['founder'] ) ) {
                $entity['founder'] = [
                    '@type'    => 'Person',
                    '@id'      => 'https://weareedit.io/#daniel-devera',
                    'name'     => 'Daniel Devera',
                    'givenName'  => 'Daniel',
                    'familyName' => 'Devera',
                    'jobTitle' => 'Founder',
                    'worksFor' => [ '@id' => $entity['@id'] ?? 'https://weareedit.io/#organization' ],
                    'sameAs'   => [
                        'https://www.wikidata.org/wiki/Q139907903',
                        'https://www.linkedin.com/in/danieldevera/',
                    ],
                ];
            }

            // alternateName aliases that mirror Wikidata. Helps LLMs resolve
            // the legal entity name ("Devera Co. Lda") and brand short-form ("EDIT.").
            if ( ! isset( $entity['alternateName'] ) ) {
                $entity['alternateName'] = [ 'EDIT.', 'weareedit', 'Devera Co. Lda' ];
            } elseif ( is_string( $entity['alternateName'] ) ) {
                $entity['alternateName'] = array_unique( [ $entity['alternateName'], 'EDIT.', 'Devera Co. Lda' ] );
            }

            break; // Only one Organization entity expected per page.
        }
        unset( $entity );

        return $data;
    }

    /**
     * Remove Brazil (and any non-PT country) from Rank Math's areaServed.
     * Rank Math stores this in its Organization/LocalBusiness schema node.
     *
     * @param array $data The full JSON-LD graph array from Rank Math.
     * @return array
     */
    public static function remove_brazil_from_area_served( array $data ): array {
        foreach ( $data as $key => &$entity ) {
            if ( ! isset( $entity['areaServed'] ) ) continue;

            $area = $entity['areaServed'];

            // Normalise to array for uniform handling
            if ( ! is_array( $area ) || isset( $area['@type'] ) ) {
                $area = [ $area ];
            }

            // Keep only Portugal and Online — strip Brazil and any other country
            $filtered = array_values( array_filter( $area, function( $item ) {
                if ( ! is_array( $item ) ) return false;
                $name = $item['name'] ?? '';
                // Allow Portugal and Online; block everything else
                return in_array( $name, [ 'Portugal', 'Online' ], true );
            } ) );

            if ( empty( $filtered ) ) {
                unset( $entity['areaServed'] );
            } elseif ( count( $filtered ) === 1 ) {
                $entity['areaServed'] = $filtered[0];
            } else {
                $entity['areaServed'] = $filtered;
            }
        }
        unset( $entity );

        return $data;
    }

    public static function output_schema() {
        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'EducationalOrganization',
            '@id'             => 'https://weareedit.io/#organization',
            'name'            => 'EDIT. - Disruptive Digital Education',
            'alternateName'   => 'EDIT.',
            'url'             => 'https://weareedit.io',
            'logo'            => [
                '@type'   => 'ImageObject',
                'url'     => 'https://weareedit.io/wp-content/uploads/2018/12/SHARE-EDIT.jpg',
                'width'   => 1200,
                'height'  => 630,
            ],
            'description'     => 'Escola de educação digital disruptiva em Portugal, com formação em UX/UI Design, Inteligência Artificial, Data Science, Marketing Digital, Programação e Gestão de Negócios.',
            'foundingDate'    => '2013',
            'address'         => [
                [
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => 'Rua de Santa Marta, 56',
                    'addressLocality' => 'Lisboa',
                    'postalCode'      => '1150-296',
                    'addressCountry'  => 'PT',
                ],
                [
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => 'Rua de Cedofeita, 609',
                    'addressLocality' => 'Porto',
                    'postalCode'      => '4050-179',
                    'addressCountry'  => 'PT',
                ],
            ],
            'sameAs' => [
                'https://www.facebook.com/weareedit',
                'https://www.instagram.com/weareedit',
                'https://www.linkedin.com/school/weareedit',
                'https://twitter.com/weareedit',
                'https://www.youtube.com/weareedit',
            ],
        ];

        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        echo "\n</script>\n";
    }
}
