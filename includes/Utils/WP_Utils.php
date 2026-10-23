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
     * Return the protocol of this WordPress website.
     *
     * @return string
     */
    public static function get_site_protocol(): string {
        $parsed_url = wp_parse_url( get_home_url() );
        $scheme     = $parsed_url['scheme'] ?? 'https';

        if ( $scheme === 'http' ) {
            return 'http';
        }

        return 'https';
    }

    /**
     * Return the blog ID of the current site.
     *
     * For single sites, this is always `1`.
     *
     * For multisites, this is the current blog ID.
     * On the main site of a multisite, this will be `1`.
     * On a subsite of a multisite, this will be the blog ID.
     *
     * @return int
     */
    public static function get_blog_id(): int {
        return get_current_blog_id();
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
        $path = trailingslashit( wp_parse_url( self::get_request_uri(), PHP_URL_PATH ) );
        return md5( $path );
    }

    /**
     * Return the WordPress core version.
     *
     * @since 1.2.0
     *
     * @return string
     */
    public static function get_wp_version(): string {
        return get_bloginfo( 'version' );
    }

    /**
     * Return the database version.
     *
     * @since 1.2.0
     *
     * @return string
     */
    public static function get_db_version(): string {
        global $wpdb;

        return $wpdb->db_version();
    }

    /**
     * Return the site locale (language).
     *
     * @since 1.2.0
     *
     * @return string
     */
    public static function get_locale(): string {
        return get_locale();
    }

    /**
     * Return the site timezone string.
     *
     * @since 1.2.0
     *
     * @return string
     */
    public static function get_timezone(): string {
        return wp_timezone_string();
    }

    /**
     * Return whether this is a WordPress multisite installation.
     *
     * @since 1.2.0
     *
     * @return bool
     */
    public static function is_multisite(): bool {
        return is_multisite();
    }

    /**
     * Return the multisite type.
     *
     * Returns 'subdomain' for subdomain-based multisites,
     * 'subdirectory' for path-based multisites, or `null`
     * if this is not a multisite installation.
     *
     * @since 1.2.0
     *
     * @return ?string
     */
    public static function get_multisite_type(): ?string {
        if ( ! is_multisite() ) {
            return null;
        }

        return defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ? 'subdomain' : 'subdirectory';
    }

    /**
     * Return the total number of users.
     *
     * @since 1.2.0
     *
     * @return int
     */
    public static function get_user_count(): int {
        $user_count = count_users();

        return $user_count['total_users'] ?? 0;
    }

    /**
     * Return the PHP version.
     *
     * @since 1.2.0
     *
     * @return string
     */
    public static function get_php_version(): string {
        return phpversion();
    }


}
