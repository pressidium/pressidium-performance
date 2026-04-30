<?php
/**
 * Posts updater.
 *
 * Updates the content of all posts with the new image URLs.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

use WP_Query;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Posts_Updater class.
 *
 * @since 1.0.0
 */
class Posts_Updater {

    /**
     * Fetch and return all posts that contain the given original URL.
     *
     * @param string $original_url Original URL to search for.
     *
     * @return WP_Post[]
     */
    private function fetch_posts( string $original_url ): array {
        $query = new WP_Query(
            array(
                'post_type'      => 'any',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'nopaging'       => true,
                's'              => $original_url,
                'search_columns' => array( 'post_content' ),
            )
        );

        if ( $query->post_count === 0 ) {
            return array();
        }

        return $query->posts;
    }

    /**
     * Update all posts that contain the given original URL with the new optimized URL.
     *
     * @param string $original_url  Original URL to search for.
     * @param string $optimized_url Optimized URL to replace with.
     *
     * @return void
     */
    public function update_posts( string $original_url, string $optimized_url ): void {
        $posts = $this->fetch_posts( $original_url );

        foreach ( $posts as $post ) {
            wp_update_post(
                array(
                    'ID'           => $post->ID,
                    'post_content' => str_replace( $original_url, $optimized_url, $post->post_content ),
                )
            );
        }
    }

    /**
     * Update all posts that contain the given optimized URL with the original URL.
     *
     * @param string $optimized_url Optimized URL to search for.
     * @param string $original_url  Original URL to replace with.
     *
     * @return void
     */
    public function revert_posts( string $optimized_url, string $original_url ): void {
        $posts = $this->fetch_posts( $optimized_url );

        foreach ( $posts as $post ) {
            wp_update_post(
                array(
                    'ID'           => $post->ID,
                    'post_content' => str_replace( $optimized_url, $original_url, $post->post_content ),
                )
            );
        }
    }

    /**
     * Replace multiple optimized URLs with their original URLs in all posts using a single query.
     *
     * Uses a direct SQL `UPDATE` with chained `REPLACE()` calls to avoid
     * loading post objects into memory. This is significantly more
     * memory-efficient than running separate `WP_Query` and `wp_update_post()`
     * calls for each URL pair.
     *
     * @param array<string, string> $replacements Associative array mapping optimized URLs to original URLs.
     *
     * @return void
     */
    public function batch_revert_posts( array $replacements ): void {
        global $wpdb;

        if ( empty( $replacements ) ) {
            return;
        }

        $set_expression = 'post_content';
        $set_params     = array();
        $where_clauses  = array();
        $where_params   = array();

        foreach ( $replacements as $optimized_url => $original_url ) {
            $set_expression  = "REPLACE({$set_expression}, %s, %s)";
            $set_params[]    = $optimized_url;
            $set_params[]    = $original_url;

            $where_clauses[] = 'post_content LIKE %s';
            $where_params[]  = '%' . $wpdb->esc_like( $optimized_url ) . '%';
        }

        $where = implode( ' OR ', $where_clauses );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL built with $wpdb->prepare() placeholders.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->posts} SET post_content = {$set_expression} WHERE {$where}",
                array_merge( $set_params, $where_params )
            )
        );
    }

}
