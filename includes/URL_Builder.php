<?php
/**
 * URL builder.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * URL_Builder class.
 *
 * @since 1.0.0
 */
final class URL_Builder {

    /**
     * @var string URL to the `wp-content/uploads` directory.
     */
    private string $upload_url;

    /**
     * @var string Base URL to the `wp-content/uploads/pressidium-performance` directory.
     */
    private string $base_url;

    /**
     * URL_Builder constructor.
     */
    public function __construct() {
        $this->upload_url = wp_upload_dir()['baseurl'];
        $this->base_url   = $this->join( $this->upload_url, 'pressidium-performance' );
    }

    /**
     * Join one or more URL segments.
     *
     * @param string $url      Base URL to join with.
     * @param string ...$paths URL segments to join.
     *
     * @return string Concatenation of URL and all members of paths with exactly one
     *                forward slash following each non-empty part except the last.
     */
    public function join( string $url, string ...$paths ): string {
        // Filter out empty paths
        $paths = array_filter( $paths );

        // Trim slashes from paths
        $paths = array_map(
            function ( string $path ) {
                return trim( $path, '/\\' );
            },
            $paths
        );

        // Join paths with forward slash
        return $url . '/' . implode( '/', $paths );
    }

    /**
     * Build a URL from the base URL and the given paths.
     *
     * @param string ...$paths URL segments to join.
     *
     * @return string Built URL.
     */
    public function build_url( string ...$paths ): string {
        return $this->join( $this->base_url, ...$paths );
    }

}
