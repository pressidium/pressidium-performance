<?php
/**
 * WordPress Utilities.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Utils;

use WP_Image_Editor_Imagick;
use WP_Image_Editor_GD;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * WP_Utils class.
 *
 * @since 1.0.0
 */
final class WP_Utils {

    /**
     * Return the domain of this WordPress website.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function get_domain(): string {
        $domain = wp_parse_url( get_site_url(), PHP_URL_HOST );

        if ( ! $domain ) {
            return wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['HTTP_HOST'] ) ), PHP_URL_HOST );
        }

        return $domain;
    }

    /**
     * Return the request URI of the current request.
     *
     * @return string
     */
    public static function get_request_uri(): string {
        return esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
    }

    /**
     * Return the environment type of this WordPress website.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function get_environment_type(): string {
        return wp_get_environment_type();
    }

    /**
     * Return whether the current environment is a local environment.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function is_local_env(): bool {
        return self::get_environment_type() === 'local';
    }

    /**
     * Return whether the current environment is a development environment.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function is_development_env(): bool {
        return self::get_environment_type() === 'development';
    }

    /**
     * Return whether the current environment is a staging environment.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function is_staging_env(): bool {
        return self::get_environment_type() === 'staging';
    }

    /**
     * Return whether the current environment is the production environment.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function is_production_env(): bool {
        return self::get_environment_type() === 'production';
    }

    /**
     * Return whether the current environment is a local or development environment.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function is_local_or_development_env(): bool {
        return self::is_local_env() || self::is_development_env();
    }

    /**
     * Return the memory limit in bytes.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public static function get_memory_limit(): int {
        // Sensible default.
        $memory_limit = '128M';

        if ( function_exists( 'ini_get' ) ) {
            $memory_limit = ini_get( 'memory_limit' );
        }

        if ( ! $memory_limit || intval( $memory_limit ) === -1 ) {
            // Unlimited, set to 32GB.
            $memory_limit = '32000M';
        }

        return wp_convert_hr_to_bytes( $memory_limit );
    }

    /**
     * Return the memory limit in a human-readable format.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function get_human_readable_memory_limit(): string {
        return Numeric_Utils::format_bytes_to_decimal( self::get_memory_limit() );
    }

    /**
     * Return whether Imagick is available.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function is_imagick_available(): bool {
        require_once ABSPATH . 'wp-includes/class-wp-image-editor.php';
        require_once ABSPATH . 'wp-includes/class-wp-image-editor-imagick.php';

        return WP_Image_Editor_Imagick::test();
    }

    /**
     * Return whether GD is available.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function is_gd_available(): bool {
        require_once ABSPATH . 'wp-includes/class-wp-image-editor.php';
        require_once ABSPATH . 'wp-includes/class-wp-image-editor-gd.php';

        return WP_Image_Editor_GD::test();
    }

    /**
     * Return the Imagick version.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function get_imagick_version(): string {
        if ( ! self::is_imagick_available() ) {
            return 'N/A';
        }

        return 'v' . phpversion( 'imagick' );
    }

    /**
     * Return the GD version.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function get_gd_version(): string {
        if ( ! self::is_gd_available() ) {
            return 'N/A';
        }

        return 'v' . phpversion( 'gd' );
    }

    /**
     * Return a unique hash for the current page based on its path.
     *
     * @return string
     */
    public static function get_unique_page_hash(): string {
        $path = trailingslashit( wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
        return md5( $path );
    }

}
