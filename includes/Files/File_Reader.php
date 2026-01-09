<?php
/**
 * File reader.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Files;

use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Utils\WP_Utils;
use Pressidium\WP\Performance\Utils\URL_Utils;

use const Pressidium\WP\Performance\VERSION;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * File_Reader class.
 *
 * @since 1.0.0
 */
final class File_Reader {

    /**
     * @var string User agent for remote requests.
     */
    const USER_AGENT = 'Pressidium Performance Plugin';

    /**
     * File_Reader constructor.
     *
     * @param Logger     $logger
     * @param Filesystem $filesystem
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly Filesystem $filesystem
    ) {}

    /**
     * Read the contents of the file at the given path.
     *
     * @throws Filesystem_Exception If the file could not be retrieved.
     *
     * @param string $file_path Path to the file to read.
     *
     * @return string Contents of the file.
     */
    private function maybe_read_local( string $file_path ): string {
        return $this->filesystem->read( $file_path );
    }

    /**
     * Determine if the given URL is protocol-relative.
     *
     * Protocol-relative URLs start with '//' and inherit
     * the protocol (http or https) from the current context.
     *
     * @param string $url URL to check.
     *
     * @return bool `true` if the URL is protocol-relative, `false` otherwise.
     */
    private function is_protocol_relative_url( string $url ): bool {
        return str_starts_with( $url, '//' );
    }

    /**
     * Fetch and return the contents of the file at the given URI.
     *
     * @throws Filesystem_Exception If the file could not be retrieved.
     *
     * @param string $file_uri URI of the file to retrieve.
     *
     * @return string Contents of the file.
     */
    private function maybe_fetch_remote( string $file_uri ): string {
        $file_uri = URL_Utils::normalize_url( $file_uri );

        $response = wp_safe_remote_request(
            $file_uri,
            array(
                'timeout'    => 10, // seconds
                'user-agent' => sprintf( '%s/%s', self::USER_AGENT, VERSION ),
                'sslverify'  => ! WP_Utils::is_local_or_development_env(), // do not verify SSL in local/dev env
            ),
        );

        if ( ! is_wp_error( $response ) ) {
            return $response['body'];
        }

        throw new Filesystem_Exception(
            sprintf( 'Could not retrieve file: %s', esc_html( $response->get_error_message() ) )
        );
    }

    /**
     * Read the file at the given path.
     *
     * @throws Filesystem_Exception If the file could not be retrieved.
     *
     * @param string $file_path Path to the file to read.
     *
     * @return File A `File` object representing the file.
     */
    public function read_local( string $file_path ): File {
        $contents = $this->maybe_read_local( $file_path );

        return new File( $this->logger, $file_path, $contents );
    }

    /**
     * Read the file at the given URI.
     *
     * @throws Filesystem_Exception If the file could not be retrieved.
     *
     * @param string $file_uri URI of the file to read.
     *
     * @return File A `File` object representing the file.
     */
    public function read_remote( string $file_uri ): File {
        $contents = $this->maybe_fetch_remote( $file_uri );

        return new File( $this->logger, $file_uri, $contents );
    }

}
