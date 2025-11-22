<?php
/**
 * AVIF converter.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image\Converters;

use Pressidium\WP\Performance\Optimizations\Image\Image;
use Pressidium\WP\Performance\Optimizations\Image\Image_Factory;
use Pressidium\WP\Performance\Exceptions\Image_Conversion_Exception;

use WP_Image_Editor;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Avif_Converter class.
 *
 * @since 1.0.0
 */
class Avif_Converter implements Converter {

    /**
     * @var string MIME type.
     */
    const MIME_TYPE = 'image/avif';

    /**
     * @var string File extension.
     */
    const EXTENSION = 'avif';

    /**
     * @var ?Image Image to convert.
     */
    private ?Image $image = null;

    /**
     * @var ?WP_Image_Editor Image editor.
     */
    private ?WP_Image_Editor $image_editor = null;

    /**
     * Avif_Converter constructor.
     *
     * @param Image_Factory $image_factory Image factory.
     */
    public function __construct( private readonly Image_Factory $image_factory ) {}

    /**
     * Load the image.
     *
     * @throws Image_Conversion_Exception If an error occurs during image loading.
     *
     * @param Image $image Image to load.
     *
     * @return Avif_Converter
     */
    public function load( Image $image ): Avif_Converter {
        $this->image = $image;

        $image_editor = wp_get_image_editor( $this->image->get_path() );

        if ( is_wp_error( $image_editor ) ) {
            throw new Image_Conversion_Exception(
                sprintf( 'Could not get image editor: %s', esc_html( $image_editor->get_error_message() ) )
            );
        }

        $this->image_editor = $image_editor;

        $result = $this->image_editor->load();

        if ( is_wp_error( $result ) ) {
            throw new Image_Conversion_Exception(
                sprintf( 'Could not load image: %s', esc_html( $result->get_error_message() ) )
            );
        }

        return $this; // chainable
    }

    /**
     * Whether the image is loaded.
     *
     * @return bool
     */
    private function is_loaded(): bool {
        return ! is_null( $this->image ) && ! is_null( $this->image_editor );
    }

    /**
     * Set the image compression quality on a 1-100% scale.
     *
     * @throws Image_Conversion_Exception If an error occurs during quality setting.
     *
     * @param int $quality
     *
     * @return Avif_Converter
     */
    public function set_quality( int $quality ): Avif_Converter {
        $result = $this->image_editor->set_quality( $quality );

        if ( is_wp_error( $result ) ) {
            throw new Image_Conversion_Exception(
                sprintf( 'Could not set image quality: %s', esc_html( $result->get_error_message() ) )
            );
        }

        return $this; // chainable
    }

    /**
     * Convert the specified image.
     *
     * @throws Image_Conversion_Exception If no image is loaded.
     * @throws Image_Conversion_Exception If an error occurs during image saving.
     *
     * @return Image
     */
    public function convert(): Image {
        if ( ! $this->is_loaded() ) {
            throw new Image_Conversion_Exception( 'Image is not loaded' );
        }

        $destination_url = sprintf(
            '%s.%s',
            preg_replace( '/\.[^.]+$/', '', $this->image->get_url() ),
            self::EXTENSION
        );

        $path_info        = pathinfo( $this->image->get_path() );
        $destination_path = sprintf(
            '%s/%s.%s',
            $path_info['dirname'],
            $path_info['filename'],
            self::EXTENSION
        );

        $result = $this->image_editor->save( $destination_path, self::MIME_TYPE );

        if ( is_wp_error( $result ) ) {
            throw new Image_Conversion_Exception(
                sprintf( 'Could not save image: %s', esc_html( $result->get_error_message() ) )
            );
        }

        $converted_image = $this->image_factory->create(
            $destination_url,
            $destination_path,
            $this->image->get_attachment_id(),
            $result['filesize'],
            $result['mime-type']
        );

        $converted_image->set_compression_quality( $this->image->get_compression_quality() );

        return $converted_image;
    }

}
