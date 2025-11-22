<?php
/**
 * CSS concatenator.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\CSS;

use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenator as Concatenator_Interface;

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
     * Merge the given files into a single file.
     *
     * @param string $contents        File contents.
     * @param string $type            File type.
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return string Concatenated file location.
     */
    public function concatenate( string $contents, string $type, string $aggregated_hash ): string {
        $dest_filename = $aggregated_hash . '.css';
        $sub_dir       = Output_Directory::CONCATENATED->value;
        $dest_path     = $this->filesystem->build_path( $sub_dir, $dest_filename );

        if ( ! $this->filesystem->exists( $dest_path ) ) {
            $this->logger->debug( "[CSS_Concatenator] Creating concatenated file at {$dest_path}" );
            $this->filesystem->create_file( $dest_path, $contents );

            return $dest_path;
        }

        $this->logger->debug( "[CSS_Concatenator] Appending to concatenated file at {$dest_path}" );
        $this->filesystem->append( $dest_path, "\n" . $contents );

        return $dest_path;
    }

}
