<?php
/**
 * Filesystem.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Files;

use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;
use Pressidium\WP\Performance\Enumerations\Output_Directory;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Filesystem class.
 *
 * @since 1.0.0
 */
final class Filesystem {

    /**
     * @var string Path to the `wp-content/uploads` directory.
     */
    private string $upload_dir;

    /**
     * @var string Path to the `wp-content/uploads/pressidium-performance` directory.
     */
    private string $root_dest_dir;

    /**
     * Filesystem constructor.
     *
     * @throws Filesystem_Exception If the filesystem could not be initialized.
     * @throws Filesystem_Exception If the `pressidium-performance` directory could not be created.
     * @throws Filesystem_Exception If the `pressidium-performance` directory is not writable.
     */
    public function __construct() {
        $this->upload_dir    = wp_upload_dir()['basedir'];
        $this->root_dest_dir = $this->join( $this->upload_dir, 'pressidium-performance' );

        $this->init();
    }

    /**
     * Initialize the WordPress filesystem.
     *
     * @throws Filesystem_Exception If the filesystem could not be initialized.
     *
     * @throws Filesystem_Exception If the `pressidium-performance` directory could not be created.
     * @throws Filesystem_Exception If the `pressidium-performance/minified` directory could not be created.
     * @throws Filesystem_Exception If the `pressidium-performance/concatenated` directory could not be created.
     *
     * @throws Filesystem_Exception If the `pressidium-performance` directory is not writable.
     * @throws Filesystem_Exception If the `pressidium-performance/minified` directory is not writable.
     * @throws Filesystem_Exception If the `pressidium-performance/concatenated` directory is not writable.
     *
     * @return void
     */
    private function init(): void {
        global $wp_filesystem;

        require_once ABSPATH . 'wp-admin/includes/file.php';

        WP_Filesystem();

        if ( ! $wp_filesystem ) {
            throw new Filesystem_Exception( 'Failed to initialize the WordPress filesystem.' );
        }

        $this->create_dir( $this->root_dest_dir );
        $this->create_dir( $this->join( $this->root_dest_dir, Output_Directory::MINIFIED->value ) );
        $this->create_dir( $this->join( $this->root_dest_dir, Output_Directory::CONCATENATED->value ) );
    }

    /**
     * Return a list of files in the given directory.
     *
     * @param string $dir_path Path to the directory to list files from.
     *
     * @return array Associative array of file information.
     */
    public function list_files( string $dir_path ): array {
        global $wp_filesystem;

        $files = $wp_filesystem->dirlist( $dir_path );

        if ( $files === false ) {
            return array();
        }

        return array_filter(
            $files,
            function ( $file_info ) {
                return $file_info['type'] === 'f';
            }
        );
    }

    /**
     * Read the entire file at the given path.
     *
     * @throws Filesystem_Exception If the file could not be read.
     *
     * @param string $file_path
     *
     * @return string The contents of the file.
     */
    public function read( string $file_path ): string {
        global $wp_filesystem;

        $contents = $wp_filesystem->get_contents( $file_path );

        if ( $contents === false ) {
            throw new Filesystem_Exception( 'Failed to read file at path: ' . esc_html( $file_path ) );
        }

        return $contents;
    }

    /**
     * Write the given data to the file at the given path.
     *
     * This method will create the directory if it does not exist.
     *
     * @param string $file_path     The path to the file where to write the data.
     * @param string $file_contents The data to write.
     *
     * @return bool Whether the data was written to the file.
     */
    public function write( string $file_path, string $file_contents ): bool {
        global $wp_filesystem;

        $dir_path = dirname( $file_path );

        try {
            // Ensure the directory exists before writing the file
            $this->create_dir( $dir_path );
        } catch ( Filesystem_Exception $exception ) {
            // The directory does not exist and could not be created
            return false;
        }

        return $wp_filesystem->put_contents(
            $file_path,
            $file_contents,
            FS_CHMOD_FILE // `0644` - read/write for the owner, read for everybody else
        );
    }

    /**
     * Delete the file at the given path.
     *
     * @param string $file_path The path to the file to delete.
     *
     * @return bool Whether the file was deleted.
     */
    public function delete_file( string $file_path ): bool {
        global $wp_filesystem;

        return $wp_filesystem->delete( $file_path );
    }

    /**
     * Delete the directory at the given path and all its contents recursively.
     *
     * @param string $dir_path The path to the directory to delete.
     *
     * @return bool Whether the directory was deleted.
     */
    public function delete_dir( string $dir_path ): bool {
        global $wp_filesystem;

        return $wp_filesystem->rmdir( $dir_path, true );
    }

    /**
     * Append the given data to the file at the given path.
     *
     * @param string $file_path     Absolute path to the file where to append the data.
     * @param string $file_contents Data to append.
     *
     * @return bool Whether the data was appended to the file.
     */
    public function append( string $file_path, string $file_contents ): bool {
        /*
         * We have to use the built-in `file_put_contents()` instead of the
         * `WP_Filesystem` abstraction because it doesn't support appending
         * to files.
         */

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return file_put_contents( $file_path, $file_contents, FILE_APPEND );
    }

    /**
     * Whether the file or directory at the given path exists.
     *
     * @param string $path Path to the file or directory to check.
     *
     * @return bool
     */
    public function exists( string $path ): bool {
        global $wp_filesystem;

        return $wp_filesystem->exists( $path );
    }

    /**
     * Create the directory at the given path, if it doesn't exist.
     *
     * Ensure that the directory is writable.
     *
     * @throws Filesystem_Exception If the directory could not be created.
     * @throws Filesystem_Exception If the directory is not writable.
     *
     * @param string $dir_path Path to the directory to create.
     *
     * @return void
     */
    public function create_dir( string $dir_path ): void {
        global $wp_filesystem;

        if ( $this->exists( $dir_path ) ) {
            return;
        }

        /*
         * Create the directory recursively if it doesn't exist.
         * We are using `wp_mkdir_p()` here because it will create
         * any intermediate directories that do not exist.
         */
        $did_create_dir = wp_mkdir_p( $dir_path );

        /*
         * Update the permissions of the directory to ensure it is writable.
         * We are using `FS_CHMOD_DIR` permissions
         * `0755` - read/write/execute for the owner, read/execute for everybody else.
         */
        $did_update_permissions = $wp_filesystem->chmod( $dir_path, FS_CHMOD_DIR );

        if ( ! $did_create_dir || ! $did_update_permissions ) {
            throw new Filesystem_Exception(
                sprintf( 'Failed to create the `%s` directory.', esc_html( $dir_path ) )
            );
        }

        if ( ! $wp_filesystem->is_writable( $dir_path ) ) {
            throw new Filesystem_Exception(
                sprintf( 'The `%s` directory is not writable.', esc_html( $dir_path ) )
            );
        }
    }

    /**
     * Create a file at the given path with the given data.
     *
     * @param string $file_path     Absolute path to the file to create.
     * @param string $file_contents Data to write.
     *
     * @return bool Whether the file was created and the data was written to it.
     */
    public function create_file( string $file_path, string $file_contents ): bool {
        return $this->write( $file_path, $file_contents );
    }

    /**
     * Join one or more path segments.
     *
     * @param string $path     The path to join with.
     * @param string ...$paths The paths to join.
     *
     * @return string Concatenation of path and all member of paths with exactly one
     *                directory separator following each non-empty part except the last.
     */
    public function join( string $path, string ...$paths ): string {
        // Filter out empty paths
        $paths = array_filter( $paths );

        // Trim slashes from paths
        $paths = array_map(
            function ( string $path ) {
                return trim( $path, '/\\' );
            },
            $paths
        );

        // Join paths
        return $path . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $paths );
    }

    /**
     * Build a path from the root destination directory and the given paths.
     *
     * @param string ...$paths Paths to join.
     *
     * @return string Built path.
     */
    public function build_path( string ...$paths ): string {
        return $this->join( $this->root_dest_dir, ...$paths );
    }

}
