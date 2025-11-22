<?php
/**
 * Image attachment.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

use Pressidium\WP\Performance\Utils\Array_Utils;
use Pressidium\WP\Performance\Exceptions\Image_Conversion_Exception;

use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Image_Attachment class.
 *
 * @since 1.0.0
 */
class Image_Attachment {

    /**
     * @var array<string, mixed> Original metadata.
     */
    private array $original_metadata;

    /**
     * @var array<string, Image_Attachment_Size> Image sizes.
     */
    private array $sizes;

    /**
     * Image_Attachment constructor.
     *
     * @throws InvalidArgumentException   If the attachment is not an image.
     * @throws InvalidArgumentException   If the attachment metadata is invalid.
     * @throws Image_Conversion_Exception If an error occurs during image loading.
     *
     * @param Image_Factory         $image_factory Image factory.
     * @param string                $base_url      Current upload directory's URL.
     * @param string                $base_dir      Current upload directory's path.
     * @param int                   $attachment_id Attachment ID.
     * @param ?array<string, mixed> $metadata      Optional. Attachment metadata.
     *                                             If the metadata is not provided, it
     *                                             will be automatically fetched using
     *                                             `wp_get_attachment_metadata()`.
     */
    public function __construct(
        private readonly Image_Factory $image_factory,
        private readonly string $base_url,
        private readonly string $base_dir,
        private readonly int $attachment_id,
        ?array $metadata = array()
    ) {
        if ( ! wp_attachment_is_image( $this->attachment_id ) ) {
            throw new InvalidArgumentException( 'Attachment is not an image' );
        }

        if ( empty( $metadata ) || ! is_array( $metadata ) ) {
            $metadata = wp_get_attachment_metadata( $this->attachment_id );
        }

        if ( ! is_array( $metadata ) ) {
            throw new InvalidArgumentException( 'Invalid attachment metadata' );
        }

        // Check if all required keys are present
        if ( ! Array_Utils::array_keys_exist( array( 'width', 'height', 'file', 'sizes', 'filesize' ), $metadata ) ) {
            throw new InvalidArgumentException( 'Invalid attachment metadata' );
        }

        $this->original_metadata = $metadata;

        $this->populate_sizes();
    }

    /**
     * Populate the image sizes.
     *
     * @throws InvalidArgumentException   If the metadata of a particular size is invalid.
     * @throws Image_Conversion_Exception If an error occurs during image loading.
     *
     * @return void
     */
    private function populate_sizes(): void {
        $url          = sprintf( '%s/%s', $this->base_url, $this->original_metadata['file'] );
        $path         = sprintf( '%s/%s', $this->base_dir, $this->original_metadata['file'] );
        $image        = $this->image_factory->create( $url, $path, $this->attachment_id );
        $is_optimized = $this->original_metadata['is_optimized'] ?? false;

        $this->sizes['full'] = new Image_Attachment_Size(
            $image,
            $this->original_metadata['width'],
            $this->original_metadata['height'],
            $path,
            $url,
            $this->original_metadata['filesize'],
            $is_optimized
        );

        $sub_dir = dirname( $this->original_metadata['file'] );

        $this->sizes = array_merge(
            $this->sizes,
            array_map(
                function ( $size ) use ( $sub_dir ) {
                    $url          = sprintf( '%s/%s/%s', $this->base_url, $sub_dir, $size['file'] );
                    $path         = sprintf( '%s/%s/%s', $this->base_dir, $sub_dir, $size['file'] );
                    $image        = $this->image_factory->create( $url, $path, $this->attachment_id );
                    $is_optimized = $size['is_optimized'] ?? false;

                    if ( ! Array_Utils::array_keys_exist( array( 'width', 'height', 'file', 'filesize' ), $size ) ) {
                        throw new InvalidArgumentException( 'Invalid attachment size' );
                    }

                    return new Image_Attachment_Size(
                        $image,
                        $size['width'],
                        $size['height'],
                        $path,
                        $url,
                        $size['filesize'],
                        $is_optimized
                    );
                },
                $this->original_metadata['sizes']
            )
        );
    }

    /**
     * Return the attachment ID.
     *
     * @return int
     */
    public function get_attachment_id(): int {
        return $this->attachment_id;
    }

    /**
     * Return the `Image_Attachment_Size` objects.
     *
     * @return array<string, Image_Attachment_Size>
     */
    public function get_sizes(): array {
        return $this->sizes;
    }

}
