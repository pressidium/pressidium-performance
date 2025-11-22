<?php
/**
 * Image optimization manager.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Hooks\Filters;
use Pressidium\WP\Performance\Background_Process;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Settings;
use Pressidium\WP\Performance\Optimizations\Image\Converters\Converter_Manager;
use Pressidium\WP\Performance\Utils\Array_Utils;
use Pressidium\WP\Performance\Utils\Numeric_Utils;
use Pressidium\WP\Performance\Enumerations\Image_Retention_Policy;
use Pressidium\WP\Performance\Enumerations\Image_Editor;
use Pressidium\WP\Performance\Exceptions\Image_Conversion_Exception;

use InvalidArgumentException;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Image_Optimization_Manager class.
 *
 * @since 1.0.0
 */
final class Image_Optimization_Manager implements Filters {

    /**
     * @var Image_Optimization_Process Background image optimization process.
     */
    private Image_Optimization_Process $image_optimization_process;

    /**
     * @var array Settings.
     */
    private array $settings;

    /**
     * Image_Optimization_Manager constructor.
     *
     * @param Logger                   $logger                   Logger.
     * @param Settings                 $settings_object          Settings object.
     * @param Filesystem               $filesystem               Filesystem.
     * @param Image_Attachment_Factory $image_attachment_factory Image attachment factory.
     * @param Converter_Manager        $converter_manager        Converter manager.
     * @param Image_Metadata_Manager   $image_metadata_manager   Image metadata manager.
     * @param Posts_Updater            $posts_updater            Posts updater.
     */
    public function __construct(
        private readonly Logger $logger,
        Settings $settings_object,
        Filesystem $filesystem,
        private readonly Image_Attachment_Factory $image_attachment_factory,
        Converter_Manager $converter_manager,
        Image_Metadata_Manager $image_metadata_manager,
        Posts_Updater $posts_updater
    ) {
        $this->settings = $settings_object->get();

        $this->image_optimization_process = new Image_Optimization_Process(
            $logger,
            $filesystem,
            $converter_manager,
            $image_metadata_manager,
            $posts_updater,
            $this->get_image_retention_policy()
        );
    }

    /**
     * Return the image retention policy.
     *
     * @return Image_Retention_Policy
     */
    private function get_image_retention_policy(): Image_Retention_Policy {
        $keep_original_files = $this->settings['imageOptimization']['keepOriginalFiles'] ?? false;

        return $keep_original_files
            ? Image_Retention_Policy::RETAIN_ORIGINAL
            : Image_Retention_Policy::DELETE_ORIGINAL;
    }

    /**
     * Whether we should auto-optimize images on upload.
     *
     * @return bool
     */
    private function should_auto_optimize(): bool {
        return $this->settings['imageOptimization']['autoOptimize'] ?? false;
    }

    /**
     * Whether we should optimize images with the given MIME type.
     *
     * @param string $mime_type MIME type to check.
     *
     * @return bool
     */
    private function should_optimize_mime_type( string $mime_type ): bool {
        $formats = $this->settings['imageOptimization']['formats'] ?? array();

        if ( ! array_key_exists( $mime_type, $formats ) ) {
            // No settings for this mime type, bail early
            return false;
        }

        $mime_type_settings = $formats[ $mime_type ];

        return array_key_exists( 'shouldOptimize', $mime_type_settings ) && $mime_type_settings['shouldOptimize'];
    }

    /**
     * Whether the given image size variant should be optimized.
     *
     * @param Image_Attachment_Size $size_variant Size variant of the image to check.
     *
     * @return bool
     */
    private function should_optimize_image( Image_Attachment_Size $size_variant ): bool {
        $is_optimized = $size_variant->is_optimized();

        if ( $is_optimized ) {
            // Image is already optimized, bail early
            $this->logger->debug(
                sprintf( 'Image \'%s\' is already optimized', esc_html( $size_variant->get_path() ) )
            );
            return false;
        }

        $image = $size_variant->get_image();

        if ( $image->is_excluded() ) {
            // Image is excluded from optimization, bail early
            $this->logger->debug(
                sprintf( 'Image \'%s\' is excluded from optimization', esc_html( $size_variant->get_path() ) )
            );
            return false;
        }

        if ( ! $image->is_supported_mime_type() ) {
            // Image mime type is not supported, bail early
            $this->logger->debug(
                sprintf( 'Image \'%s\' mime type is not supported', esc_html( $size_variant->get_path() ) )
            );
            return false;
        }

        if ( ! $this->should_optimize_mime_type( $image->get_mime_type() ) ) {
            // Image optimization is disabled for this mime type, bail early
            $this->logger->debug(
                sprintf( 'Image \'%s\' mime type should not be optimized', esc_html( $size_variant->get_path() ) )
            );
            return false;
        }

        return true;
    }

    /**
     * Push the images of all size variants of the given image attachment to the queue of images to optimize.
     *
     * @param Image_Attachment $image_attachment Image attachment to push its size variants' images to the queue.
     *
     * @return void
     */
    public function push_to_queue( Image_Attachment $image_attachment ): void {
        foreach ( $image_attachment->get_sizes() as $size_variant_name => $size_variant ) {
            if ( ! $this->should_optimize_image( $size_variant ) ) {
                // Image should not be optimized, bail early
                continue;
            }

            // Push the image to the optimization queue for background processing
            $this->image_optimization_process->push_to_queue(
                new Image_Optimization_Payload(
                    $image_attachment->get_attachment_id(),
                    $size_variant_name,
                    $size_variant->get_image()
                )
            );
        }
    }

    /**
     * Start optimizing images in the background.
     *
     * @return void
     */
    public function process_queue(): void {
        $this->image_optimization_process->save()->dispatch();
    }

    /**
     * Optimize the generate attachment.
     *
     * @link https://developer.wordpress.org/reference/hooks/wp_generate_attachment_metadata/
     *
     * @param array  $metadata      An array of attachment meta data.
     * @param int    $attachment_id Current attachment ID.
     * @param string $context       Additional context. Can be 'create' when metadata was initially created
     *                              for new attachment or 'update' when the metadata was updated.
     *
     * @return array
     */
    public function optimize_image( array $metadata, int $attachment_id, string $context ): array {
        if ( ! $this->should_auto_optimize() ) {
            // Auto-optimization is disabled, bail early
            return $metadata;
        }

        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            // Attachment is not an image, bail early
            return $metadata;
        }

        try {
            $attachment = $this->image_attachment_factory->create( $attachment_id );
        } catch ( InvalidArgumentException | Image_Conversion_Exception $exception ) {
            // Invalid attachment, bail early
            $this->logger->error( sprintf( 'Could not optimize image (%s)', $exception->getMessage() ) );
            return $metadata;
        }

        $this->push_to_queue( $attachment );
        $this->process_queue();

        return $metadata;
    }

    /**
     * Return the human-readable file size difference.
     *
     * @param float $original_size  Original file size in bytes.
     * @param float $optimized_size Optimized file size in bytes.
     *
     * @return string Human-readable file size difference.
     */
    private function get_human_readable_file_size( float $original_size = 0, float $optimized_size = 0 ): string {
        if ( $original_size <= 0 && $optimized_size > 0 ) {
            // Original file size is unknown, return the optimized file size
            return Numeric_Utils::format_bytes_to_decimal( $optimized_size );
        }

        if ( $optimized_size <= 0 && $original_size > 0 ) {
            // Optimized file size is unknown, return the original file size
            return Numeric_Utils::format_bytes_to_decimal( $original_size );
        }

        if ( $optimized_size >= $original_size ) {
            // Optimized file size is larger than the original file size, do not show the saved bytes
            return Numeric_Utils::format_bytes_to_decimal( $optimized_size );
        }

        return sprintf(
            '%s → %s (saved %s, %s)',
            Numeric_Utils::format_bytes_to_decimal( $original_size ),
            Numeric_Utils::format_bytes_to_decimal( $optimized_size ),
            Numeric_Utils::format_bytes_to_decimal( $original_size - $optimized_size ),
            number_format( Numeric_Utils::calc_percent_diff( $original_size, $optimized_size ), 2 ) . '%'
        );
    }

    /**
     * Filter the attachment data prepared for JavaScript to update the data displayed in the Media Library.
     *
     * @param array<string, mixed>       $response   Array of prepared attachment data.
     * @param WP_Post                    $attachment Attachment object.
     * @param array<string, mixed>|false $meta       Array of attachment metadata, or `false` if there is none.
     *
     * @return array<string, mixed> Array of prepared attachment data.
     */
    public function update_media_library_data( array $response, WP_Post $attachment, array|false $meta ): array {
        if ( ! $meta ) {
            return $response;
        }

        $is_optimized = array_key_exists( 'is_optimized', $meta ) && $meta['is_optimized'];

        if ( ! $is_optimized ) {
            return $response;
        }

        $mime_type = $meta['mime-type'];
        $type      = substr( $mime_type, 0, strpos( $mime_type, '/' ) );
        $sub_type  = substr( $mime_type, strpos( $mime_type, '/' ) + 1 );

        $response['mime']    = $mime_type;
        $response['type']    = $type;
        $response['subtype'] = $sub_type;

        $response['filesizeHumanReadable'] = $this->get_human_readable_file_size(
            $meta['original']['filesize'] ?? $response['filesizeInBytes'],
            $response['filesizeInBytes'] ?? 0
        );

        return $response;
    }

    /**
     * Filter the list of image editing library classes to prioritize the preferred image editor.
     *
     * @link https://developer.wordpress.org/reference/hooks/wp_image_editors/
     *
     * @param string[] $image_editors List of image editing library classes.
     *
     * @return string[]
     */
    public function prioritize_image_editor( array $image_editors ): array {
        $image_editor = $this->settings['imageOptimization']['preferredImageEditor'] ?? 'auto';

        if ( $image_editor === 'auto' ) {
            // No need to change the order of the image editors
            return $image_editors;
        }

        $image_editor_mapping = array(
            'imagick' => Image_Editor::IMAGICK->value,
            'gd'      => Image_Editor::GD->value,
        );

        $image_editor_class = $image_editor_mapping[ $image_editor ] ?? null;

        if ( $image_editor_class === null ) {
            // Invalid preferred image editor, return the image editors as is
            return $image_editors;
        }

        // Move the preferred image editor to the beginning of the list
        return Array_Utils::move_value_to_beginning( $image_editors, $image_editor_class );
    }

    /**
     * Filter the image editor output format mapping to add or remove supported output formats.
     *
     * @link https://developer.wordpress.org/reference/hooks/image_editor_output_format/
     *
     * @param string[] $output_format Image editor output format mapping.
     *
     * @return string[]
     */
    public function image_editor_output_format( array $output_format ): array {
        return $output_format;
    }

    /**
     * Return the image optimization background processes.
     *
     * @return Background_Process[]
     */
    public function get_background_processes(): array {
        return array(
            $this->image_optimization_process,
        );
    }

    /**
     * Return the filters to register.
     *
     * @return array<string, array{0: string, 1?: int, 2?: int}>
     */
    public function get_filters(): array {
        return array(
            'wp_generate_attachment_metadata' => array( 'optimize_image', 10, 3 ),
            'wp_prepare_attachment_for_js'    => array( 'update_media_library_data', 10, 3 ),
            'wp_image_editors'                => array( 'prioritize_image_editor' ),
            'image_editor_output_format'      => array( 'image_editor_output_format' ),
        );
    }

}
