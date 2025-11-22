<?php
/**
 * Image attachment factory.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

use Pressidium\WP\Performance\Exceptions\Image_Conversion_Exception;

use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Image_Attachment_Factory class.
 *
 * @since 1.0.0
 */
class Image_Attachment_Factory {

    /**
     * @var string Current upload directory's base URL.
     */
    private string $base_url;

    /**
     * @var string Current upload directory's path.
     */
    private string $base_dir;

    /**
     * Image_Attachment_Factory constructor.
     *
     * @param Image_Factory $image_factory Image factory.
     */
    public function __construct(
        private readonly Image_Factory $image_factory,
    ) {
        $upload_dir = wp_upload_dir();

        $this->base_url = $upload_dir['baseurl'];
        $this->base_dir = $upload_dir['basedir'];
    }

    /**
     * Create a new `Image_Attachment` instance.
     *
     * @throws InvalidArgumentException   If the attachment is not an image.
     * @throws InvalidArgumentException   If the attachment metadata is invalid.
     * @throws Image_Conversion_Exception If an error occurs during image loading.
     *
     * @param int                   $attachment_id Attachment ID.
     * @param ?array<string, mixed> $metadata      Optional. Attachment metadata.
     *
     * @return Image_Attachment Image attachment instance.
     */
    public function create( int $attachment_id, ?array $metadata = array() ): Image_Attachment {
        return new Image_Attachment(
            $this->image_factory,
            $this->base_url,
            $this->base_dir,
            $attachment_id,
            $metadata
        );
    }

}
