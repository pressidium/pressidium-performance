<?php
/**
 * URL Utilities.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * URL_Utils class.
 *
 * @since 1.0.0
 */
final class URL_Utils {

    /**
     * Whether the given value is a valid URL.
     *
     * @param string $value
     *
     * @return bool
     */
    public static function is_url( string $value ): bool {
        return (bool) filter_var( $value, FILTER_VALIDATE_URL );
    }

    /**
     * Whether the given value is a valid URL of this site.
     *
     * @param string $value
     *
     * @return bool
     */
    public static function is_own_url( string $value ): bool {
        if ( ! self::is_url( $value ) ) {
            return false;
        }

        return (bool) preg_match( '/^' . preg_quote( home_url(), '/' ) . '/', $value );
    }

    /**
     * Return a filesystem path based on the given URL.
     *
     * @param string $url URL to convert to a filesystem path.
     *
     * @return ?string Filesystem path, or `null` if the URL is not valid or not of this site.
     */
    public static function get_path_from_url( string $url ): ?string {
        if ( ! self::is_own_url( $url ) ) {
            return null;
        }

        $path = wp_parse_url( $url, PHP_URL_PATH );

        if ( ! is_string( $path ) ) {
            return null;
        }

        return ABSPATH . str_replace( '/', DIRECTORY_SEPARATOR, $path );
    }

    /**
     * Return the URL based on the given filesystem path.
     *
     * @param string $path Filesystem path to convert to a URL.
     *
     * @return string URL for the file at the given path.
     */
    public static function get_url_from_path( string $path ): string {
        $url_path = realpath( $path );

        if ( str_starts_with( $url_path, ABSPATH ) ) {
            $url_path = substr( $url_path, strlen( ABSPATH ) );
        }

        $url_path = ltrim( str_replace( array( '\\', DIRECTORY_SEPARATOR ), '/', $url_path ), '/' );

        return home_url( $url_path );
    }

}
