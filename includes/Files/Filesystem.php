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
     * Flatten the given recursive `dirlist()` result into a map of relative paths.
     *
     * @param array  $files  Associative array of file information, as returned by `dirlist()`.
     * @param string $prefix Path of the directory holding these entries, relative to the root of the walk.
     *
     * @return array<string, int> Map of relative file paths to their last modified timestamps.
     */
    private function flatten_dirlist( array $files, string $prefix = '' ): array {
        $flattened = array();

        foreach ( $files as $file_info ) {
            $name          = $file_info['name'];
            $relative_path = $prefix === '' ? $name : $prefix . '/' . $name;

            if ( $file_info['type'] === 'd' ) {
                if ( is_array( $file_info['files'] ?? null ) ) {
                    $flattened = array_merge(
                        $flattened,
                        $this->flatten_dirlist( $file_info['files'], $relative_path )
                    );
                }

                continue;
            }

            $flattened[ $relative_path ] = (int) ( $file_info['lastmodunix'] ?? 0 );
        }

        return $flattened;
    }

    /**
     * Return every file in the given directory and all its subdirectories.
     *
     * @param string $dir_path Path to the directory to walk.
     *
     * @return array<string, int> Map of relative file paths to their last modified timestamps.
     */
    public function list_files_recursive( string $dir_path ): array {
        global $wp_filesystem;

        $files = $wp_filesystem->dirlist( $dir_path, false, true );

        if ( ! is_array( $files ) ) {
            return array();
        }

        return $this->flatten_dirlist( $files );
    }

    /**
     * Delete the empty subdirectories of the given directory.
     *
     * @param string $dir_path      Path to the directory to prune.
     * @param int    $deleted_count Running count of the directories deleted so far.
     *
     * @return bool Whether the given directory is now empty.
     */
    private function prune_empty_dirs_within( string $dir_path, int &$deleted_count ): bool {
        global $wp_filesystem;

        $entries = $wp_filesystem->dirlist( $dir_path );

        if ( ! is_array( $entries ) ) {
            return false;
        }

        $is_empty = true;

        foreach ( $entries as $entry ) {
            if ( $entry['type'] !== 'd' ) {
                $is_empty = false;
                continue;
            }

            $child_path = $this->join( $dir_path, $entry['name'] );

            // Pass the deleted count by reference so it can be updated in the recursive calls
            if ( ! $this->prune_empty_dirs_within( $child_path, $deleted_count ) ) {
                $is_empty = false;
                continue;
            }

            if ( ! $wp_filesystem->rmdir( $child_path ) ) {
                $is_empty = false;
                continue;
            }

            $deleted_count += 1;
        }

        return $is_empty;
    }

    /**
     * Delete the empty subdirectories of the given directory.
     *
     * @param string $dir_path Path to the directory to prune.
     *
     * @return int Number of directories deleted.
     */
    public function prune_empty_dirs( string $dir_path ): int {
        $deleted_count = 0;

        // Pass the deleted count by reference so it can be updated in the recursive calls
        $this->prune_empty_dirs_within( $dir_path, $deleted_count );

        return $deleted_count;
    }

    /**
     * Return the number of files in the given recursive `dirlist()` result.
     *
     * Directory entries hold their own contents under a `files` key,
     * so we have to walk into them to reach every file.
     *
     * @param array $files Associative array of file information, as returned by `dirlist()`.
     *
     * @return int Number of files.
     */
    private function count_files_in_dirlist( array $files ): int {
        $count = 0;

        foreach ( $files as $file_info ) {
            if ( $file_info['type'] === 'd' ) {
                $count += is_array( $file_info['files'] ?? null )
                    ? $this->count_files_in_dirlist( $file_info['files'] )
                    : 0;

                continue;
            }

            $count += 1;
        }

        return $count;
    }

    /**
     * Return the number of files in the given directory and all its subdirectories.
     *
     * @param string $dir_path Path to the directory to count files in.
     *
     * @return int Number of files. `0` if the directory does not exist or could not be listed.
     */
    public function count_files( string $dir_path ): int {
        global $wp_filesystem;

        $files = $wp_filesystem->dirlist( $dir_path, false, true );

        if ( ! is_array( $files ) ) {
            return 0;
        }

        return $this->count_files_in_dirlist( $files );
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
     * Move the file at the given path to the given destination, replacing it if it exists.
     *
     * This method uses the built-in `rename()` function, which is atomic, while
     * `WP_Filesystem::move()` is not (it deletes the destination before moving
     * the source).
     *
     * @param string $source_path      Absolute path to the file to move.
     * @param string $destination_path Absolute path to move the file to.
     *
     * @return bool Whether the file was moved.
     */
    public function move( string $source_path, string $destination_path ): bool {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
        return rename( $source_path, $destination_path );
    }

    /**
     * Read the last N lines of a file using a stream-based approach.
     *
     * Opens the file and reads backwards from the end in chunks,
     * so the entire file is never loaded into memory at once.
     *
     * @throws Filesystem_Exception If the file could not be opened.
     *
     * @param string $file_path  Path to the file to read.
     * @param int    $lines      Number of lines to read from the end.
     * @param int    $chunk_size Size of each read chunk in bytes.
     *
     * @return string The last N lines of the file.
     */
    public function read_last_lines( string $file_path, int $lines, int $chunk_size = 4096 ): string {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen( $file_path, 'r' );

        if ( $handle === false ) {
            throw new Filesystem_Exception( 'Failed to open file at path: ' . esc_html( $file_path ) );
        }

        fseek( $handle, 0, SEEK_END );

        $pos        = ftell( $handle );
        $buffer     = '';
        $line_count = 0;

        while ( $pos > 0 && $line_count <= $lines ) {
            $read_size = min( $chunk_size, $pos );
            $pos      -= $read_size;

            fseek( $handle, $pos, SEEK_SET );

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
            $chunk = fread( $handle, $read_size );

            $buffer      = $chunk . $buffer;
            $line_count += substr_count( $chunk, "\n" );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $handle );

        // We may have read more lines than needed, so trim from the top
        $all_lines = explode( "\n", $buffer );

        // Take only the last $lines lines (ignoring a potential trailing empty element)
        $last_lines = array_slice( $all_lines, -$lines );

        return implode( "\n", $last_lines );
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
