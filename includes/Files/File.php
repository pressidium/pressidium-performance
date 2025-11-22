<?php
/**
 * File.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Files;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Utils\URL_Utils;
use Pressidium\WP\Performance\Utils\WP_Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * File class.
 *
 * @since 1.0.0
 */
final class File {

    /**
     * @var string File hash.
     */
    private string $hash;

    /**
     * @var string URL of the file.
     */
    private string $url;

    /**
     * @var ?string Path of the file on the filesystem.
     */
    private ?string $path;

    /**
     * File constructor.
     *
     * @param Logger $logger   An instance of `Logger`.
     * @param string $location File location (URL or path).
     * @param string $contents File contents.
     */
    public function __construct(
        protected readonly Logger $logger,
        string $location,
        private readonly string $contents,
    ) {
        $this->populate_path_and_url( $location );
    }

    /**
     * Return the URL of this file.
     *
     * @return string
     */
    public function get_url(): string {
        return $this->url;
    }

    /**
     * Return the filesystem path of this file.
     *
     * @return ?string
     */
    public function get_path(): ?string {
        return $this->path;
    }

    /**
     * Populate the path and URL properties based on the given location.
     *
     * @param string $location File location (URL or path).
     *
     * @return void
     */
    private function populate_path_and_url( string $location ): void {
        if ( URL_Utils::is_url( $location ) ) {
            $this->url  = $location;
            $this->path = URL_Utils::get_path_from_url( $location );

            return;
        }

        $this->url  = URL_Utils::get_url_from_path( $location );
        $this->path = $location;
    }

    /**
     * Return the file contents.
     *
     * @return string
     */
    public function get_contents(): string {
        return $this->contents;
    }

    /**
     * Return the filename of this file.
     *
     * @return string
     */
    public function get_filename(): string {
        return strtok( basename( $this->url ), '?' );
    }

    /**
     * Return the file type.
     *
     * @return string
     */
    public function get_file_type(): string {
        return pathinfo( $this->get_filename(), PATHINFO_EXTENSION );
    }

    /**
     * Return the size of this file in bytes.
     *
     * @return int
     */
    public function get_size_in_bytes(): int {
        return strlen( $this->contents );
    }

    /**
     * Whether this file is empty.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return empty( $this->contents );
    }

    /**
     * Compute and return the hash of this file.
     *
     * @return string
     */
    private function compute_hash(): string {
        $location = ! empty( $this->path ) ? $this->path : $this->url;

        if ( WP_Utils::is_local_or_development_env() ) {
            // Workaround to work in local or development environments without verifying the SSL certificate
            stream_context_set_default(
                array(
                    'ssl' => array(
                        'verify_peer'      => false,
                        'verify_peer_name' => false,
                    ),
                )
            );

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $contents = file_get_contents( $location );

            return md5( $contents );
        }

        return md5_file( $location );
    }

    /**
     * Return the hash of this file.
     *
     * @return string
     */
    public function get_hash(): string {
        if ( ! isset( $this->hash ) ) {
            $this->hash = $this->compute_hash();
        }

        return $this->hash;
    }

}
