<?php
/**
 * Converter interface.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image\Converters;

use Pressidium\WP\Performance\Exceptions\Image_Conversion_Exception;
use Pressidium\WP\Performance\Optimizations\Image\Image;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Converter interface.
 *
 * @since 1.0.0
 */
interface Converter {

    /**
     * Load the specified image.
     *
     * @throws Image_Conversion_Exception If an error occurs during image loading.
     *
     * @param Image $image Image to load.
     *
     * @return Converter
     */
    public function load( Image $image ): Converter;

    /**
     * Convert the loaded image.
     *
     * @throws Image_Conversion_Exception If no image is loaded.
     * @throws Image_Conversion_Exception If an error occurs during image saving.
     *
     * @return Image Converted image.
     */
    public function convert(): Image;

}
