<?php
/**
 * Image revert process.
 *
 * Background process that receives attachment IDs and reverts
 * each optimized attachment back to its original state: restoring
 * the original metadata, replacing optimized URLs in post content,
 * and deleting the optimized files from the filesystem.
 *
 * Each task handles a single attachment (with a bounded number
 * of size variants, typically fewer than 10), so memory usage
 * stays low and the risk of exceeding PHP time limits within
 * a single task is minimal. The background process framework
 * checks time and memory limits between tasks.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

use Pressidium\WP\Performance\Background_Process;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Utils\Numeric_Utils;

use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Image_Revert_Process class.
 *
 * @since 1.0.2
 */
class Image_Revert_Process extends Background_Process {

    /**
     * @var string The unique prefix to use for the queue.
     */
    protected string $prefix = 'pressidium_performance';

    /**
     * @var string The action to perform.
     */
    protected string $action = 'revert_images';

    /**
     * Image_Revert_Process constructor.
     *
     * @param Logger        $logger         An instance of `Logger`.
     * @param Filesystem    $filesystem     An instance of `Filesystem`.
     * @param Posts_Updater $posts_updater  An instance of `Posts_Updater`.
     */
    public function __construct(
        protected readonly Logger $logger,
        protected readonly Filesystem $filesystem,
        protected readonly Posts_Updater $posts_updater,
    ) {
        parent::__construct();
    }

    /**
     * Return the logger instance.
     *
     * @return Logger
     */
    protected function get_logger(): Logger {
        return $this->logger;
    }

    /**
     * Return the upload base directory.
     *
     * @return string
     */
    private function get_upload_base_dir(): string {
        return wp_upload_dir()['basedir'];
    }


    /**
     * Whether the attachment with the given ID exists.
     *
     * @param int $attachment_id Attachment ID.
     *
     * @return bool
     */
    private function attachment_exists( int $attachment_id ): bool {
        return get_post_type( $attachment_id ) === 'attachment';
    }

    /**
     * Whether the given metadata indicates an optimized attachment.
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
     * Revert a single attachment back to its original state.
     *
     * Collects all URL replacement pairs (full-size and every size
     * variant), performs a single batched SQL update to replace
     * optimized URLs with original URLs in post content, deletes
     * the optimized files from the filesystem, and restores the
     * original metadata.
     *
     * @param int   $attachment_id Attachment ID.
     * @param array $metadata      Current attachment metadata.
     *
     * @return void
     */
    private function revert_attachment( int $attachment_id, array $metadata ): void {
        $original_metadata = $metadata['original'];

        $upload_dir = wp_upload_dir();
        $base_dir   = $upload_dir['basedir'];
        $base_url   = $upload_dir['baseurl'];

        // Collect all URL replacements (optimized → original) for a single batched query
        $replacements = array();

        // Full-size image
        $optimized_url = sprintf( '%s/%s', $base_url, $metadata['file'] );
        $original_url  = sprintf( '%s/%s', $base_url, $original_metadata['file'] );

        $replacements[ $optimized_url ] = $original_url;

        // Size variants
        $image_sub_dir = dirname( $metadata['file'] );

        if ( isset( $original_metadata['sizes'] ) && is_array( $original_metadata['sizes'] ) ) {
            foreach ( $original_metadata['sizes'] as $size => $original_size ) {
                if ( ! isset( $metadata['sizes'][ $size ]['file'] ) ) {
                    continue;
                }

                $optimized_size_url = sprintf( '%s/%s/%s', $base_url, $image_sub_dir, $metadata['sizes'][ $size ]['file'] );
                $original_size_url  = sprintf( '%s/%s/%s', $base_url, $image_sub_dir, $original_size['file'] );

                $replacements[ $optimized_size_url ] = $original_size_url;
            }
        }

        // Replace all optimized URLs with original URLs in post content in a single query
        $this->posts_updater->batch_revert_posts( $replacements );

        // Delete optimized files from the filesystem
        $optimized_image_path = $base_dir . DIRECTORY_SEPARATOR . $metadata['file'];

        if ( is_file( $optimized_image_path ) ) {
            $this->filesystem->delete_file( $optimized_image_path );
        }

        if ( isset( $original_metadata['sizes'] ) && is_array( $original_metadata['sizes'] ) ) {
            $image_dir = dirname( $optimized_image_path );

            foreach ( $original_metadata['sizes'] as $size => $original_size ) {
                if ( ! isset( $metadata['sizes'][ $size ]['file'] ) ) {
                    continue;
                }

                $optimized_size_path = $image_dir . DIRECTORY_SEPARATOR . $metadata['sizes'][ $size ]['file'];

                if ( is_file( $optimized_size_path ) ) {
                    $this->filesystem->delete_file( $optimized_size_path );
                }
            }
        }

        // Revert the attached file metadata
        update_attached_file( $attachment_id, $original_metadata['file'] );

        // Restore the original attachment metadata
        wp_update_attachment_metadata( $attachment_id, $original_metadata );
    }

    /**
     * Perform task with queued item.
     *
     * Receives an attachment ID, loads its metadata, checks if
     * it has been optimized, and reverts it to its original state.
     *
     * The entire body is wrapped in a top-level `Throwable` catch
     * to guarantee the item is always removed from the queue.
     *
     * @param mixed $item Queue item (attachment ID).
     *
     * @return mixed
     */
    protected function task( $item ) {
        if ( ! is_scalar( $item ) ) {
            $this->logger->warning(
                sprintf( '[%s] Skipping non-scalar queue item, removing from queue.', esc_html( $this->action ) )
            );
            return false;
        }

        $attachment_id = (int) $item;

        try {
            $this->logger->debug(
                sprintf( '[%s] Processing attachment %d for revert...', esc_html( $this->action ), $attachment_id )
            );

            if ( ! $this->attachment_exists( $attachment_id ) ) {
                $this->logger->warning(
                    sprintf(
                        '[%s] Attachment %d does not exist, skipping.',
                        esc_html( $this->action ),
                        $attachment_id
                    )
                );
                return false;
            }

            $metadata = wp_get_attachment_metadata( $attachment_id );

            if ( ! is_array( $metadata ) ) {
                $this->logger->debug(
                    sprintf(
                        '[%s] Attachment %d has no metadata, skipping.',
                        esc_html( $this->action ),
                        $attachment_id
                    )
                );
                return false;
            }

            if ( ! $this->is_optimized( $metadata ) ) {
                $this->logger->debug(
                    sprintf(
                        '[%s] Attachment %d is not optimized, skipping.',
                        esc_html( $this->action ),
                        $attachment_id
                    )
                );
                return false;
            }

            if ( ! isset( $metadata['original'] ) || ! is_array( $metadata['original'] ) ) {
                $this->logger->warning(
                    sprintf(
                        '[%s] Attachment %d is marked as optimized but has no original metadata, skipping.',
                        esc_html( $this->action ),
                        $attachment_id
                    )
                );
                return false;
            }

            $original_file = $metadata['original']['file'] ?? null;

            if ( empty( $original_file ) ) {
                $this->logger->warning(
                    sprintf(
                        '[%s] Attachment %d has no original file path in metadata, skipping.',
                        esc_html( $this->action ),
                        $attachment_id
                    )
                );
                return false;
            }

            $original_file_path = $this->get_upload_base_dir() . DIRECTORY_SEPARATOR . $original_file;

            if ( ! is_file( $original_file_path ) ) {
                $this->logger->warning(
                    sprintf(
                        '[%s] Original file for attachment %d does not exist at \'%s\', skipping.',
                        esc_html( $this->action ),
                        $attachment_id,
                        esc_html( $original_file_path )
                    )
                );
                return false;
            }

            $this->revert_attachment( $attachment_id, $metadata );

            $this->logger->debug(
                sprintf(
                    '[%s] Attachment %d reverted successfully, removing from queue.',
                    esc_html( $this->action ),
                    $attachment_id
                )
            );
        } catch ( Throwable $throwable ) {
            $this->logger->error(
                sprintf(
                    '[%s] Unrecoverable error reverting attachment %d, removing from queue: %s in %s:%d',
                    esc_html( $this->action ),
                    $attachment_id,
                    esc_html( $throwable->getMessage() ),
                    esc_html( $throwable->getFile() ),
                    $throwable->getLine()
                )
            );
        }

        return false;
    }

    /**
     * Complete processing.
     *
     * @return void
     */
    protected function complete(): void {
        parent::complete();

        $this->logger->info( sprintf( '[%s] All image reverts were complete.', esc_html( $this->action ) ) );
    }

    /**
     * Return the items to process.
     *
     * Limited to the items of the next batch.
     *
     * Primes the post and meta object caches for the entire batch
     * in bulk before iterating, so that the per-item calls to
     * `wp_get_attachment_url()`, `get_post_mime_type()`, and
     * `wp_get_attachment_metadata()` all read from cache instead
     * of issuing individual database queries.
     *
     * @return array<int, array{location: string, type: string, size: string}>
     */
    public function get_items(): array {
        $batches = $this->get_batches( 1 );

        if ( empty( $batches ) ) {
            return array();
        }

        $next_batch     = $batches[0];
        $attachment_ids = array();

        foreach ( $next_batch->data as $item ) {
            if ( ! is_scalar( $item ) ) {
                continue;
            }

            $attachment_ids[] = (int) $item;
        }

        if ( empty( $attachment_ids ) ) {
            return array();
        }

        // Warm the post and meta caches for the entire batch in bulk
        _prime_post_caches( $attachment_ids, false, true );

        $items = array();

        foreach ( $attachment_ids as $attachment_id ) {
            $url = wp_get_attachment_url( $attachment_id );

            if ( ! $url ) {
                continue;
            }

            $mime_type = get_post_mime_type( $attachment_id );
            $metadata  = wp_get_attachment_metadata( $attachment_id );
            $filesize  = is_array( $metadata ) && isset( $metadata['filesize'] )
                ? (int) $metadata['filesize']
                : 0;

            $items[] = array(
                'location' => $url,
                'type'     => $mime_type ? substr( $mime_type, strpos( $mime_type, '/' ) + 1 ) : '',
                'size'     => Numeric_Utils::format_bytes_to_decimal( $filesize ),
            );
        }

        return $items;
    }

}


