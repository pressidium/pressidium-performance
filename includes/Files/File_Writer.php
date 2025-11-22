<?php
/**
 * File writer.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Files;

use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * File_Writer class.
 *
 * @since 1.0.0
 */
final class File_Writer {

    /**
     * File_Writer constructor.
     *
     * @param Filesystem $filesystem
     */
    public function __construct( private readonly Filesystem $filesystem ) {}

    /**
     * Write the given file to the filesystem.
     *
     * @throws Filesystem_Exception If the file could not be written.
     *
     * @param File   $file    `File` object to write.
     * @param string $sub_dir Subdirectory to write the file to.
     *
     * @return void
     */
    public function write( File $file, string $sub_dir = '' ): void {
        if ( $file->is_empty() ) {
            // File is empty, bail early
            return;
        }

        // Prepend the root destination directory to the relative file path
        $file_path = $this->filesystem->build_path( $sub_dir, $file->get_path() );

        $did_write = $this->filesystem->create_file( $file_path, $file->get_contents() );

        if ( ! $did_write ) {
            throw new Filesystem_Exception( 'Could not write the file' );
        }
    }

}
