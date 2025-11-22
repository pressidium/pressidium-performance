<?php
/**
 * Payload for the minification background process.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Minification_Payload class.
 *
 * @since 1.0.0
 */
class Minification_Payload {

    /**
     * Minification_Payload constructor.
     *
     * @param string    $file_uri URI of the file to be minified.
     * @param int|false $post_id  Post ID to which the file to minify belong.
     */
    public function __construct(
        private readonly string $file_uri,
        private readonly int|false $post_id
    ) {}

    /**
     * Return the URI of the file to be minified.
     *
     * @return string File URI.
     */
    public function get_file_uri(): string {
        return $this->file_uri;
    }

    /**
     * Return the ID of the post to which the file to minify belong.
     *
     * @return int|false
     */
    public function get_post_id(): int|false {
        return $this->post_id;
    }

}
