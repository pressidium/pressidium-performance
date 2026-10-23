<?php
/**
 * Taxonomy-related helpers.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Caching;

use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Taxonomies class.
 *
 * @since 1.1.0
 */
class Taxonomies {

    /**
     * Return all taxonomy names (except `nav_menu` and `link_category`).
     *
     * @return string[]
     */
    private static function get_all_taxonomies(): array {
        $taxonomy_names = get_taxonomies();

        unset( $taxonomy_names['nav_menu'] );
        unset( $taxonomy_names['link_category'] );

        return array_values( $taxonomy_names );
    }

    /**
     * Whether the given taxonomy term is a top-level term (i.e. doesn't have any parents).
     *
     * @param WP_Term $term
     *
     * @return bool
     */
    private static function is_top_level_term( WP_Term $term ): bool {
        return $term->parent === 0;
    }

    /**
     * Return all taxonomy term IDs of the post with the given ID.
     *
     * @param int $post_id ID of the post.
     *
     * @return int[]
     */
    public static function get_taxonomy_terms( int $post_id ): array {
        $terms = wp_get_object_terms( $post_id, self::get_all_taxonomies() );

        if ( is_wp_error( $terms ) ) {
            return array();
        }

        $term_ids = array();

        foreach ( $terms as $term ) {
            $term_ids[] = $term->term_id;

            if ( ! self::is_top_level_term( $term ) ) {
                $ancestor_term_ids = get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' );
                $term_ids          = array_merge( $term_ids, $ancestor_term_ids );
            }
        }

        return array_values( array_unique( $term_ids ) );
    }

}
