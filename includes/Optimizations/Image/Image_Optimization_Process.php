<?php
/**
 * Image optimization process.
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
     * @param Logger                 $logger                 An instance of `Logger`.
     * @param Filesystem             $filesystem             An instance of `Filesystem`.
     * @param Converter_Manager      $converter_manager      An instance of `Converter_Manager`.
     * @param Image_Metadata_Manager $image_metadata_manager An instance of `Image_Metadata_Manager`.
     * @param Posts_Updater          $posts_updater          An instance of `Posts_Updater`.
     * @param Image_Retention_Policy $image_retention_policy An instance of the `Image_Retention_Policy` enumeration.
     */
    public function __construct(
        protected readonly Logger $logger,
        protected readonly Filesystem $filesystem,
        protected readonly Converter_Manager $converter_manager,
        protected readonly Image_Metadata_Manager $image_metadata_manager,
        protected readonly Posts_Updater $posts_updater,
        protected readonly Image_Retention_Policy $image_retention_policy
    ) {
        parent::__construct();
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
            // We should retain the originally uploaded image, bail early
            return;
        }

        $deleted_successfully = $this->filesystem->delete_file( $image->get_path() );

        if ( ! $deleted_successfully ) {
            throw new Image_Filesystem_Exception( 'Failed to delete the original image.' );
        }
    }

    /**
     * Perform task with queued item.
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param mixed $item Queue item to iterate over.
     *
     * @return mixed
     */
    protected function task( $item ) {
        $this->logger->debug( 'Processing image optimization task in the background...' );

        if ( ! ( $item instanceof Image_Optimization_Payload ) ) {
            // Queued item is not an instance of `Image_Optimization_Payload`, remote it from the queue
            $this->logger->warning(
                sprintf( '[%s] Queued item is not a valid payload, removing it from queue.', esc_html( $this->action ) )
            );

            return false;
        }

        $attachment_id     = $item->get_attachment_id();
        $size_variant_name = $item->get_size_variant_name();
        $image             = $item->get_image();

        $image_url = $image->get_url();

        $this->logger->debug(
            sprintf( '[%s] Optimizing {%s} in the background...', esc_html( $this->action ), esc_url( $image_url ) )
        );

        try {
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
        }

        $this->logger->debug( sprintf( '[%s] Image processed, removing from queue.', esc_html( $this->action ) ) );

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
     * @return array
     */
    public function get_items(): array {
        $batches = $this->get_batches( 1 );

        if ( empty( $batches ) ) {
            return array();
        }

        $next_batch = $batches[0];
        $items      = array();

        foreach ( $next_batch->data as $item ) {
            if ( ! ( $item instanceof Image_Optimization_Payload ) ) {
                continue;
            }

            $image = $item->get_image();

            $items[] = array(
                'location' => $image->get_path(),
                'type'     => $image->get_file_extension(),
                'size'     => Numeric_Utils::format_bytes_to_decimal( $image->get_size_in_bytes() ),
            );
        }

        return $items;
    }

}
