<?php
/**
 * Converter manager.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image\Converters;

use InvalidArgumentException;
use Pressidium\WP\Performance\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Converter_Manager class.
 *
 * @since 1.0.0
 */
class Converter_Manager {

    /**
     * @var array Settings.
     */
    private array $settings;

    /**
     * Converter_Manager constructor.
     *
     * @param Settings                 $settings_object Settings object.
     * @param array<string, Converter> $converters      Associative array of image converters.
     */
    public function __construct( Settings $settings_object, private readonly array $converters ) {
        $this->settings = $settings_object->get();
    }

    /**
     * Return the mime type of the destination image based on the given mime type of the source image.
     *
     * @throws InvalidArgumentException If the mime type is not supported.
     *
     * @param string $source_mime_type MIME type of the source image.
     *
     * @return string MIME type of the destination image.
     */
    private function get_destination_mime_type( string $source_mime_type ): string {
        if ( ! array_key_exists( $source_mime_type, $this->settings['imageOptimization']['formats'] ) ) {
            throw new InvalidArgumentException( 'Mime type not supported' );
        }

        $format_settings = $this->settings['imageOptimization']['formats'][ $source_mime_type ];

        if ( ! array_key_exists( 'convertTo', $format_settings ) ) {
            throw new InvalidArgumentException( 'No destination mime type defined' );
        }

        return $format_settings['convertTo'];
    }

    /**
     * Return the `Converter` instance for the given mime type.
     *
     * @throws InvalidArgumentException If the mime type is not supported.
     *
     * @param string $source_mime_type MIME type of the source image.
     *
     * @return Converter
     */
    public function get_converter( string $source_mime_type ): Converter {
        $dest_mime_type = $this->get_destination_mime_type( $source_mime_type );

        if ( ! array_key_exists( $dest_mime_type, $this->converters ) ) {
            throw new InvalidArgumentException( 'No converter defined for the destination mime type' );
        }

        return $this->converters[ $dest_mime_type ];
    }

}
