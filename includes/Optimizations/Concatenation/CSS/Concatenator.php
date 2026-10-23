<?php
/**
 * CSS concatenator.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\CSS;

use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenator as Concatenator_Interface;

use Pressidium\WP\Performance\Exceptions\Concatenation_Exception;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Files\File;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Table;
use Pressidium\WP\Performance\Settings;

use Pressidium\WP\Performance\Enumerations\Output_Directory;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenator class.
 *
 * @since 1.0.0
 */
final class Concatenator implements Concatenator_Interface {

    /**
     * Concatenator constructor.
     *
     * @param Logger               $logger
     * @param Filesystem           $filesystem
     * @param Concatenations_Table $concatenations_table
     * @param Settings             $settings_object
     */
    public function __construct(
        protected readonly Logger $logger,
        protected readonly Filesystem $filesystem,
        protected readonly Concatenations_Table $concatenations_table,
        protected readonly Settings $settings_object,
    ) {}

    /**
     * Return the key of the given segment of the bundle with the given aggregated hash.
     *
     * A bundle is split into segments at the stylesheets that could not be included in it,
     * so the order of the cascade is preserved. Each segment is concatenated to its own file,
     * so its key can be passed wherever the key of a bundle file is expected.
     *
     * @param string $aggregated_hash Aggregated hash of the bundle.
     * @param int    $segment         Segment index.
     *
     * @return string
     */
    public static function get_segment_key( string $aggregated_hash, int $segment ): string {
        return "{$aggregated_hash}-{$segment}";
    }

    /**
     * Return the directory that holds the concatenated files.
     *
     * All files of a bundle (i.e. all of its segments) live in this directory.
     *
     * @return string
     */
    public function get_bundle_directory(): string {
        return $this->filesystem->build_path( Output_Directory::CONCATENATED->value );
    }

    /**
     * Return the path of the concatenated file with the given key.
     *
     * @param string $key Key of the bundle file.
     *
     * @return string
     */
    private function get_bundle_path( string $key ): string {
        return $this->filesystem->build_path(
            Output_Directory::CONCATENATED->value,
            $key . '.css'
        );
    }

    /**
     * Return the path of the partial file with the given key.
     *
     * @param string $key Key of the bundle file.
     *
     * @return string
     */
    private function get_partial_path( string $key ): string {
        return $this->filesystem->build_path(
            Output_Directory::CONCATENATED->value,
            $key . self::PARTIAL_SUFFIX . '.css'
        );
    }

    /**
     * Append the given contents to the partial file of the bundle.
     *
     * @throws Concatenation_Exception If the contents could not be written.
     *
     * @param string $contents File contents.
     * @param string $type     File type.
     * @param string $key      Key of the bundle file.
     *
     * @return string Partial file location.
     */
    public function concatenate( string $contents, string $type, string $key ): string {
        $partial_path = $this->get_partial_path( $key );

        if ( ! $this->filesystem->exists( $partial_path ) ) {
            $this->logger->debug( "[CSS_Concatenator] Creating partial concatenated file at {$partial_path}" );

            if ( ! $this->filesystem->create_file( $partial_path, $contents ) ) {
                throw new Concatenation_Exception(
                    esc_html( "Could not create partial concatenated file at {$partial_path}" )
                );
            }

            return $partial_path;
        }

        $this->logger->debug( "[CSS_Concatenator] Appending to partial concatenated file at {$partial_path}" );

        if ( ! $this->filesystem->append( $partial_path, "\n" . $contents ) ) {
            throw new Concatenation_Exception(
                esc_html( "Could not append to partial concatenated file at {$partial_path}" )
            );
        }

        return $partial_path;
    }

    /**
     * Move the partial file of the bundle into place.
     *
     * @param string $key Key of the bundle file.
     *
     * @return ?string Concatenated file location, or `null` if the bundle could not be completed.
     */
    public function complete( string $key ): ?string {
        $partial_path = $this->get_partial_path( $key );
        $dest_path    = $this->get_bundle_path( $key );

        if ( ! $this->filesystem->exists( $partial_path ) ) {
            $this->logger->error( "[CSS_Concatenator] Could not find partial concatenated file at {$partial_path}" );
            return null;
        }

        if ( ! $this->filesystem->move( $partial_path, $dest_path ) ) {
            $this->logger->error( "[CSS_Concatenator] Could not move {$partial_path} to {$dest_path}" );
            return null;
        }

        $this->logger->info( "[CSS_Concatenator] Completed concatenated file at {$dest_path}" );

        return $dest_path;
    }

    /**
     * Delete the concatenated file of the bundle, if any.
     *
     * @param string $key Key of the bundle file.
     *
     * @return void
     */
    public function delete( string $key ): void {
        $dest_path = $this->get_bundle_path( $key );

        if ( $this->filesystem->exists( $dest_path ) ) {
            $this->logger->debug( "[CSS_Concatenator] Deleting concatenated file at {$dest_path}" );
            $this->filesystem->delete_file( $dest_path );
        }
    }

    /**
     * Delete the partial file of the bundle, if any.
     *
     * @param string $key Key of the bundle file.
     *
     * @return void
     */
    public function discard( string $key ): void {
        $partial_path = $this->get_partial_path( $key );

        if ( $this->filesystem->exists( $partial_path ) ) {
            $this->logger->debug( "[CSS_Concatenator] Discarding partial concatenated file at {$partial_path}" );
            $this->filesystem->delete_file( $partial_path );
        }
    }

}
