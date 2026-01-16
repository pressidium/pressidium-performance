<?php
/**
 * Original files deletion manager.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Hooks\Actions;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Utils\String_Utils;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Original_Files_Deletion_Manager class.
 *
 * @since 1.0.0
 */
class Original_Files_Deletion_Manager implements Actions {

    /**
     * Original_Files_Deletion_Manager constructor.
     *
     * @param Logger     $logger     Logger.
     * @param Filesystem $filesystem Filesystem.
     */
    public function __construct( private readonly Logger $logger, private readonly Filesystem $filesystem ) {}

    /**
     * Return metadata for the attachment with the given attachment ID.
     *
     * @param int $post_id Attachment ID.
     *
     * @return array{
     *    width?: int,
     *    height?: int,
     *    file?: string,
     *    sizes?: array<string, array{file?: string, width?: int, height?: int, "mime-type"?: string, filesize?: int}>,
     *    image_meta?: array,
     *    filesize?: int,
     *    is_optimized?: bool,
     *    original?: array
     *  }|false
     */
    private function get_attachment_metadata( int $post_id ): array|false {
        return wp_get_attachment_metadata( $post_id );
    }

    /**
     * Whether the attachment has been optimized.
     *
     * @param array $metadata Attachment metadata.
     *
     * @return bool
     */
    private function is_optimized( array $metadata ): bool {
        if ( ! isset( $metadata['is_optimized'] ) ) {
            return false;
        }

        $flag = $metadata['is_optimized'];

        return $flag === true || $flag === 1 || $flag === '1' || $flag === 'true';
    }

    /**
     * Delete a file from the `uploads` directory.
     *
     * @param string $uploads_base_dir  Base `uploads` directory.
     * @param string $original_base_dir Base directory of the original file.
     * @param string $filename          Filename to delete.
     *
     * @return void
     */
    private function delete_file( string $uploads_base_dir, string $original_base_dir, string $filename ): void {
        $file_path = sprintf(
            '%s/%s/%s',
            $uploads_base_dir,
            $original_base_dir,
            basename( $filename )
        );

        $this->logger->debug( sprintf( 'Deleting original file %s', esc_html( $file_path ) ) );

        $this->filesystem->delete_file( $file_path );
    }

    /**
     * Delete size variations of the original image.
     *
     * @param string $uploads_base_dir  Base `uploads` directory.
     * @param string $original_base_dir Base directory of the original file.
     * @param array<string, array{
     *     file?: string,
     *     width?: int,
     *     height?: int,
     *     "mime-type"?: string,
     *     filesize?: int
     * }> $original_sizes Size variations of the original image.
     *                    Keys are size slugs, values are arrays
     *                    which may contain:
     *                    - `file`: Filename of the size variation.
     *                    - `width`: Width of the size variation.
     *                    - `height`: Height of the size variation.
     *                    - `mime-type`: Mime type of the size variation.
     *                    - `filesize`: File size of the size variation.
     *
     * @return void
     */
    private function delete_size_variations(
        string $uploads_base_dir,
        string $original_base_dir,
        array $original_sizes
    ): void {
        foreach ( $original_sizes as $original_size ) {
            $variant_filename = $original_size['file'] ?? null;

            if ( empty( $variant_filename ) ) {
                // No filename for this size variant, skip
                continue;
            }

            $this->delete_file( $uploads_base_dir, $original_base_dir, $variant_filename );
        }
    }

    /**
     * Delete the original files when an optimized attachment is deleted.
     *
     * @link https://developer.wordpress.org/reference/hooks/delete_attachment/
     *
     * This method is called before the attachment is deleted from the database.
     *
     * @param int     $post_id Attachment ID.
     * @param WP_Post $post    Attachment post object.
     *
     * @return void
     */
    public function delete_original_files( int $post_id, WP_Post $post ): void {
        $metadata = $this->get_attachment_metadata( $post_id );

        if ( ! $metadata ) {
            return;
        }

        if ( ! $this->is_optimized( $metadata ) ) {
            return;
        }

        $uploads_base_dir = String_Utils::untrailing_slash_it( wp_get_upload_dir()['basedir'] );

        $original_file     = $metadata['original']['file'] ?? null;
        $original_base_dir = String_Utils::unleading_slash_it( dirname( $original_file ?? '' ) );

        $this->logger->debug( sprintf( 'Deleting original files for attachment ID %d', esc_html( $post_id ) ) );

        if ( ! empty( $original_file ) ) {
            $this->delete_file( $uploads_base_dir, $original_base_dir, $original_file );
        }

        $original_sizes = $metadata['original']['sizes'] ?? array();

        $this->delete_size_variations( $uploads_base_dir, $original_base_dir, $original_sizes );
    }

    /**
     * Return the actions to register.
     *
     * @return array<string, array{0: string, 1?: int, 2?: int}>
     */
    public function get_actions(): array {
        return array(
            'delete_attachment' => array( 'delete_original_files', 10, 2 ),
        );
    }

}
