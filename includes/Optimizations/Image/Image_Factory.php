<?php
/**
 * Factory for creating image objects.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Settings;
use Pressidium\WP\Performance\Exceptions\Image_Conversion_Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Image_Factory class.
 *
 * @since 1.0.0
 */
final class Image_Factory {

    /**
     * Image_Factory constructor.
     *
     * @param Settings   $settings_object Settings object.
     * @param Filesystem $filesystem      Filesystem.
     */
    public function __construct(
        private readonly Settings $settings_object,
        private readonly Filesystem $filesystem
    ) {}

    /**
     * Create and return an image object.
     *
     * @throws Image_Conversion_Exception If the image file does not exist at the specified path.
     *
     * @param string  $url           Image URL.
     * @param string  $path          Image path.
     * @param int     $attachment_id Attachment ID.
     * @param ?int    $file_size     Optional. File size in bytes.
     * @param ?string $mime_type     Optional. Mime type of the image.
     *
     * @return Image
     */
    public function create(
        string $url,
        string $path,
        int $attachment_id,
        ?int $file_size = null,
        ?string $mime_type = null
    ): Image {
        return new Image(
            $this->settings_object,
            $this->filesystem,
            $url,
            $path,
            $attachment_id,
            $file_size,
            $mime_type
        );
    }

}
