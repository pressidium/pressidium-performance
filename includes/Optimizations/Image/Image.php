<?php
/**
 * Image.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

use Pressidium\WP\Performance\Settings;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Utils\Array_Utils;
use Pressidium\WP\Performance\Enumerations\Image_Mime_Type;
use Pressidium\WP\Performance\Exceptions\Image_Conversion_Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Image class.
 *
 * @since 1.0.0
 */
class Image {

    /**
     * @var array Settings.
     */
    private array $settings;

    /**
     * @var int Compression quality.
     */
    private int $compression_quality = 75;

    /**
     * @var ?int Image type as an `IMAGETYPE_` constant.
     *
     * @link https://www.php.net/manual/en/image.constants.php
     * @link https://www.php.net/manual/en/function.exif-imagetype.php
     */
    private ?int $image_type = null;

    /**
     * Image constructor.
     *
     * @throws Image_Conversion_Exception If the image file does not exist at the specified path.
     *
     * @param Settings   $settings_object Settings.
     * @param Filesystem $filesystem      Filesystem.
     * @param string     $url             Image URL.
     * @param string     $path            Image path.
     * @param int        $attachment_id   Attachment ID.
     * @param ?int       $file_size       Optional. File size in bytes.
     *                                    If not provided, it will be
     *                                    calculated using `filesize()`.
     * @param ?string    $mime_type       Optional. Mime type of the image.
     *                                    If not provided, it will be
     *                                    determined using `exif_imagetype()`
     *                                    and `image_type_to_mime_type()`.
     */
    public function __construct(
        Settings $settings_object,
        private readonly Filesystem $filesystem,
        private readonly string $url,
        private readonly string $path,
        private readonly int $attachment_id,
        private ?int $file_size = null,
        private ?string $mime_type = null,
    ) {
        $this->settings = $settings_object->get();

        if ( ! file_exists( $path ) ) {
            throw new Image_Conversion_Exception(
                sprintf( 'Image file does not exist at path: %s', esc_html( $path ) )
            );
        }
    }

    /**
     * Return image URL.
     *
     * @return string
     */
    public function get_url(): string {
        return $this->url;
    }

    /**
     * Return image path.
     *
     * @return string
     */
    public function get_path(): string {
        return $this->path;
    }

    /**
     * Return attachment ID.
     *
     * @return int
     */
    public function get_attachment_id(): int {
        return $this->attachment_id;
    }

    /**
     * Set the compression quality of the image.
     *
     * @param int $compression_quality Compression quality.
     *
     * @return void
     */
    public function set_compression_quality( int $compression_quality ): void {
        $this->compression_quality = $compression_quality;
    }

    /**
     * Return the compression quality of the image.
     *
     * @return int
     */
    public function get_compression_quality(): int {
        return $this->compression_quality;
    }

    /**
     * Return the file extension of the image.
     *
     * @return string
     */
    public function get_file_extension(): string {
        return pathinfo( $this->path, PATHINFO_EXTENSION );
    }

    /**
     * Calculate the size of this image in bytes.
     *
     * @return int
     */
    private function calculate_size_in_bytes(): int {
        return filesize( $this->path );
    }

    /**
     * Return the size of this image in bytes.
     *
     * @return int
     */
    public function get_size_in_bytes(): int {
        if ( empty( $this->file_size ) ) {
            $this->file_size = $this->calculate_size_in_bytes();
        }

        return $this->file_size;
    }

    /**
     * Return the image exclusions as an associative array with keys `url` and `is_regex`.
     *
     * @return array
     */
    private function get_exclusions(): array {
        return $this->settings['imageOptimization']['exclusions'] ?? array();
    }

    /**
     * Whether the specified URL matches the image URL (or a regex pattern of it).
     *
     * @param string $url_or_pattern URL or regex pattern to match.
     * @param bool   $is_regex       Whether we are matching a regex pattern.
     *
     * @return bool
     */
    private function matches_exclusion( string $url_or_pattern, bool $is_regex ): bool {
        if ( $is_regex ) {
            return preg_match( '#' . $url_or_pattern . '#', $this->url );
        }

        return $url_or_pattern === $this->url;
    }

    /**
     * Whether the image is excluded from optimization.
     *
     * @return bool
     */
    public function is_excluded(): bool {
        /**
         * Filters the image exclusions.
         *
         * @param array $exclusions Exclusions as an array of associative arrays with keys `url` and `is_regex`.
         */
        $exclusions = apply_filters(
            'pressidium_performance_image_exclusions',
            $this->get_exclusions()
        );

        return Array_Utils::some(
            $exclusions,
            function ( $exclusion ) {
                return $this->matches_exclusion( $exclusion['url'], $exclusion['is_regex'] );
            }
        );
    }

    /**
     * Return the type of the image as an `IMAGETYPE_` constant.
     *
     * @throws Image_Conversion_Exception If the image type could not be determined.
     *
     * @link https://www.php.net/manual/en/image.constants.php
     * @link https://www.php.net/manual/en/function.exif-imagetype.php
     *
     * Uses the `exif_imagetype()` function to determine the image type
     * by reading the first bytes of the file and checking its signature.
     *
     * Memoize the result to avoid multiple calls to `exif_imagetype()`.
     *
     * @return int
     */
    public function get_image_type(): int {
        if ( ! empty( $this->image_type ) ) {
            return $this->image_type;
        }

        $image_type = exif_imagetype( $this->path );

        if ( $image_type === false ) {
            throw new Image_Conversion_Exception( 'Could not determine image type' );
        }

        $this->image_type = $image_type;

        return $this->image_type;
    }

    /**
     * Return the mime-type of the image.
     *
     * @throws Image_Conversion_Exception If the image type could not be determined.
     *
     * @return string
     */
    public function get_mime_type(): string {
        if ( empty( $this->mime_type ) ) {
            $this->mime_type = image_type_to_mime_type( $this->get_image_type() );
        }

        return $this->mime_type;
    }

    /**
     * Return the supported image mime types.
     *
     * @return string[]
     */
    private function get_supported_mime_types(): array {
        return array(
            Image_Mime_Type::JPEG->value,
            Image_Mime_Type::PNG->value,
            Image_Mime_Type::WEBP->value,
            Image_Mime_Type::GIF->value,
            Image_Mime_Type::AVIF->value,
        );
    }

    /**
     * Whether the image mime type is supported.
     *
     * @return bool
     */
    public function is_supported_mime_type(): bool {
        return in_array( $this->get_mime_type(), $this->get_supported_mime_types(), true );
    }

}
