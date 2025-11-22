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

}
