<?php
/**
 * Image attachment size.
 *
 * Represents an image attachment size variant.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Image_Attachment_Size class.
 *
 * @since 1.0.0
 */
class Image_Attachment_Size {

    /**
     * Image_Attachment_Size constructor.
     */
    public function __construct(
        private readonly Image $image,
        private readonly int $width,
        private readonly int $height,
        private readonly string $path,
        private readonly string $url,
        private readonly int $size,
        private readonly bool $is_optimized
    ) {}

    /**
     * Return the image object.
     *
     * @return Image
     */
    public function get_image(): Image {
        return $this->image;
    }

    /**
     * Return the image width.
     *
     * @return int
     */
    public function get_width(): int {
        return $this->width;
    }

    /**
     * Return the image height.
     *
     * @return int
     */
    public function get_height(): int {
        return $this->height;
    }

    /**
     * Return the absolute file path to the image.
     *
     * @return string
     */
    public function get_path(): string {
        return $this->path;
    }

    /**
     * Return the absolute URL to the image.
     *
     * @return string
     */
    public function get_url(): string {
        return $this->url;
    }

    /**
     * Return the file size in bytes.
     *
     * @return int
     */
    public function get_size(): int {
        return $this->size;
    }

    /**
     * Whether the image is optimized.
     *
     * @return bool
     */
    public function is_optimized(): bool {
        return $this->is_optimized;
    }

}
