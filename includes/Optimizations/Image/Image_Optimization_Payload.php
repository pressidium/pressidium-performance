<?php
/**
 * Payload for the image optimization background process.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Image_Optimization_Payload class.
 *
 * @since 1.0.0
 */
class Image_Optimization_Payload {

    /**
     * Image_Optimization_Payload constructor.
     *
     * @param int    $attachment_id     Attachment ID.
     * @param string $size_variant_name Size variant name.
     * @param Image  $image             Image.
     */
    public function __construct(
        private readonly int $attachment_id,
        private readonly string $size_variant_name,
        private readonly Image $image
    ) {}

    /**
     * Return the attachment ID.
     *
     * @return int
     */
    public function get_attachment_id(): int {
        return $this->attachment_id;
    }

    /**
     * Return the size variant name.
     *
     * @return string
     */
    public function get_size_variant_name(): string {
        return $this->size_variant_name;
    }

    /**
     * Return the image.
     *
     * @return Image
     */
    public function get_image(): Image {
        return $this->image;
    }

}
