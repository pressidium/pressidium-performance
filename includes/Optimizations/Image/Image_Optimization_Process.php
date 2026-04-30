<?php
/**
 * Image optimization process.
 *
 * Background process that receives attachment IDs, creates
 * `Image_Attachment` objects, filters their size variants,
 * and optimizes each valid variant — all in a single process.
 *
 * Each task handles a single attachment (with a bounded number
 * of size variants, typically fewer than 10), so memory usage
 * stays low and the risk of exceeding PHP time limits within
 * a single task is minimal. The background process framework
 * checks time and memory limits between tasks.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

use Pressidium\WP\Performance\Exceptions\Image_Conversion_Exception;
use Pressidium\WP\Performance\Exceptions\Image_Filesystem_Exception;

use Pressidium\WP\Performance\Enumerations\Image_Retention_Policy;

use Pressidium\WP\Performance\Background_Process;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Optimizations\Image\Converters\Converter_Manager;
use Pressidium\WP\Performance\Utils\Numeric_Utils;

use InvalidArgumentException;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

class Image_Optimization_Process extends Background_Process {

    /**
     * @var string The unique prefix to use for the queue.
     */
    protected string $prefix = 'pressidium_performance';

    /**
     * @var string The action to perform.
     */
    protected string $action = 'optimize_images';

    /**
     * Image_Optimization_Process constructor.
     *
     * @param Logger                   $logger                   An instance of `Logger`.
     * @param Filesystem               $filesystem               An instance of `Filesystem`.
     * @param Converter_Manager        $converter_manager        An instance of `Converter_Manager`.
     * @param Image_Metadata_Manager   $image_metadata_manager   An instance of `Image_Metadata_Manager`.
     * @param Posts_Updater            $posts_updater            An instance of `Posts_Updater`.
     * @param Image_Attachment_Factory $image_attachment_factory An instance of `Image_Attachment_Factory`.
     * @param Image_Retention_Policy   $image_retention_policy   Image retention policy.
     * @param array<string, mixed>     $format_settings          Formats settings (mime-type → shouldOptimize, etc.).
     */
    public function __construct(
        protected readonly Logger $logger,
        protected readonly Filesystem $filesystem,
        protected readonly Converter_Manager $converter_manager,
        protected readonly Image_Metadata_Manager $image_metadata_manager,
        protected readonly Posts_Updater $posts_updater,
        private readonly Image_Attachment_Factory $image_attachment_factory,
        protected readonly Image_Retention_Policy $image_retention_policy,
        private readonly array $format_settings = array(),
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
     * Whether we should optimize images with the given MIME type.
     *
     * @param string $mime_type MIME type to check.
     *
     * @return bool
     */
    private function should_optimize_mime_type( string $mime_type ): bool {
        if ( ! array_key_exists( $mime_type, $this->format_settings ) ) {
            return false;
        }

        $mime_type_settings = $this->format_settings[ $mime_type ];

        return array_key_exists( 'shouldOptimize', $mime_type_settings ) && $mime_type_settings['shouldOptimize'];
    }

    /**
     * Whether the given image size variant should be optimized.
     *
     * @param Image_Attachment_Size $size_variant Size variant of the image to check.
     *
     * @return bool
     */
    private function should_optimize_size_variant( Image_Attachment_Size $size_variant ): bool {
        if ( $size_variant->is_optimized() ) {
            $this->logger->debug(
                sprintf( 'Image \'%s\' is already optimized', esc_html( $size_variant->get_path() ) )
            );
            return false;
        }

        $image = $size_variant->get_image();

        if ( $image->is_excluded() ) {
            $this->logger->debug(
                sprintf( 'Image \'%s\' is excluded from optimization', esc_html( $size_variant->get_path() ) )
            );
            return false;
        }

        if ( ! $image->is_supported_mime_type() ) {
            $this->logger->debug(
                sprintf( 'Image \'%s\' mime type is not supported', esc_html( $size_variant->get_path() ) )
            );
            return false;
        }

        if ( ! $this->should_optimize_mime_type( $image->get_mime_type() ) ) {
            $this->logger->debug(
                sprintf( 'Image \'%s\' mime type should not be optimized', esc_html( $size_variant->get_path() ) )
            );
            return false;
        }

        return true;
    }

    /**
     * Optimize the given image.
     *
     * @throws Image_Conversion_Exception If no converter is found for the given image's mime-type.
     * @throws Image_Conversion_Exception If an error occurs during image loading.
     * @throws Image_Conversion_Exception If no image is loaded.
     * @throws Image_Conversion_Exception If an error occurs during quality setting.
     * @throws Image_Conversion_Exception If an error occurs during image saving.
     *
     * @param Image $image Image to optimize.
     *
     * @return Image Optimized image.
     */
    private function optimize_image( Image $image ): Image {
        $converter = $this->converter_manager->get_converter( $image->get_mime_type() );

        return $converter
            ->load( $image )
            ->set_quality( $image->get_compression_quality() )
            ->convert();
    }

    /**
     * Whether to delete the original image.
     *
     * @return bool
     */
    private function should_delete_original_image(): bool {
        return $this->image_retention_policy === Image_Retention_Policy::DELETE_ORIGINAL;
    }

    /**
     * Delete the original image if set to do so.
     *
     * @throws Image_Filesystem_Exception If the original image could not be deleted.
     *
     * @param Image $image The image to delete.
     *
     * @return void
     */
    private function maybe_delete_original_image( Image $image ): void {
        if ( ! $this->should_delete_original_image() ) {
            return;
        }

        $deleted_successfully = $this->filesystem->delete_file( $image->get_path() );

        if ( ! $deleted_successfully ) {
            throw new Image_Filesystem_Exception( 'Failed to delete the original image.' );
        }
    }

    /**
     * Whether an attachment with the given ID exists in the Media Library.
     *
     * @param int $attachment_id Attachment ID.
     *
     * @return bool
     */
    private function attachment_exists( int $attachment_id ): bool {
        return get_post_type( $attachment_id ) === 'attachment';
    }

    /**
     * Optimize a single size variant of an attachment.
     *
     * @param int                   $attachment_id     Attachment ID.
     * @param string                $size_variant_name Size variant name.
     * @param Image_Attachment_Size $size_variant      Size variant.
     *
     * @return void
     */
    private function optimize_size_variant(
        int $attachment_id,
        string $size_variant_name,
        Image_Attachment_Size $size_variant
    ): void {
        $image     = $size_variant->get_image();
        $image_url = $image->get_url();

        $this->logger->debug(
            sprintf( '[%s] Optimizing {%s} in the background...', esc_html( $this->action ), esc_url( $image_url ) )
        );

        try {
            if ( ! $this->attachment_exists( $attachment_id ) ) {
                // Original attachment does not exist, bail early
                $this->logger->warning(
                    sprintf(
                        '[%s] Could not optimize size variant, original attachment (ID %s) does not exist.',
                        esc_html( $this->action ),
                        $attachment_id
                    )
                );
                return;
            }

            $optimized_image = $this->optimize_image( $image );

            $this->maybe_delete_original_image( $image );

            $this->image_metadata_manager->update( $attachment_id, $size_variant_name, $optimized_image );
            $this->posts_updater->update_posts( $image->get_url(), $optimized_image->get_url() );
        } catch ( Image_Conversion_Exception $exception ) {
            $this->logger->error( sprintf( '[%s] Could not optimize image.', esc_html( $this->action ) ) );
            $this->logger->log_exception( $exception );
        } catch ( Image_Filesystem_Exception $exception ) {
            $this->logger->error( sprintf( '[%s] Could not delete original image.', esc_html( $this->action ) ) );
            $this->logger->log_exception( $exception );
        } catch ( Throwable $throwable ) {
            $this->logger->error(
                sprintf(
                    '[%s] Unrecoverable error while processing size variant: %s in %s:%d',
                    esc_html( $this->action ),
                    esc_html( $throwable->getMessage() ),
                    esc_html( $throwable->getFile() ),
                    $throwable->getLine()
                )
            );
        }
    }

    /**
     * Perform task with queued item.
     *
     * Receives an attachment ID, creates an `Image_Attachment`,
     * iterates over its size variants, and optimizes each valid one.
     *
     * Each attachment typically has fewer than 10 size variants,
     * so processing all variants of a single attachment within one
     * task is safe for both memory and time limits.
     *
     * The entire body is wrapped in a top-level `Throwable` catch
     * to guarantee the item is always removed from the queue.
     * Without this, a single fatal error (e.g. out-of-memory from
     * loading a huge image) would permanently block the queue on
     * that one item, `task()` would never return, so the item
     * would never be removed, and every retry from the cron
     * healthcheck would hit the same fatal on the same item.
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
                sprintf( '[%s] Processing attachment %d...', esc_html( $this->action ), $attachment_id )
            );

            $attachment = $this->image_attachment_factory->create( $attachment_id );

            foreach ( $attachment->get_sizes() as $size_variant_name => $size_variant ) {
                if ( ! $this->should_optimize_size_variant( $size_variant ) ) {
                    continue;
                }

                $this->optimize_size_variant( $attachment_id, $size_variant_name, $size_variant );
            }

            $this->logger->debug(
                sprintf(
                    '[%s] Attachment %d processed, removing from queue.',
                    esc_html( $this->action ),
                    $attachment_id
                )
            );
        } catch ( InvalidArgumentException | Image_Conversion_Exception $exception ) {
            $this->logger->debug(
                sprintf(
                    '[%s] Skipping attachment %d: %s',
                    esc_html( $this->action ),
                    $attachment_id,
                    esc_html( $exception->getMessage() )
                )
            );
        } catch ( Throwable $throwable ) {
            $this->logger->error(
                sprintf(
                    '[%s] Unrecoverable error processing attachment %d, removing from queue: %s in %s:%d',
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

        $this->logger->info( sprintf( '[%s] All image optimizations were complete.', esc_html( $this->action ) ) );
    }

    /**
     * Return the items to process.
     *
     * Limited to the items of the next batch.
     *
     * @return array<int, array{location: string, type: string, size: string}>
     */
    public function get_items(): array {
        $batches = $this->get_batches( 1 );

        if ( empty( $batches ) ) {
            return array();
        }

        $next_batch = $batches[0];
        $items      = array();

        foreach ( $next_batch->data as $item ) {
            if ( ! is_scalar( $item ) ) {
                continue;
            }

            $attachment_id = (int) $item;
            $url           = wp_get_attachment_url( $attachment_id );

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
