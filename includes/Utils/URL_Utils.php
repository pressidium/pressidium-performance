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
     * Normalize the given URL.
     *
     * @param string $url URL to normalize.
     *
     * @return string
     */
    public static function normalize_url( string $url ): string {
        // Handle protocol-relative URLs
        $is_protocol_relative = str_starts_with( $url, '//' );

        if ( $is_protocol_relative ) {
            $protocol = WP_Utils::get_site_protocol();
            $url      = $protocol . ':' . $url;
        }

        return $url;
    }

    /**
     * Whether the given value is a valid URL.
     *
     * @param string $value
     *
     * @return bool
     */
    public static function is_url( string $value ): bool {
        $normalized_value = self::normalize_url( $value );

        return (bool) filter_var( $normalized_value, FILTER_VALIDATE_URL );
    }

    /**
     * Return the host of the given URL.
     *
     * @param string $url URL to extract the host from.
     *
     * @return ?string Lowercase host, or `null` if the URL does not have one
     *                 (e.g. because it's a relative URL).
     */
    public static function get_host( string $url ): ?string {
        $host = wp_parse_url( self::normalize_url( $url ), PHP_URL_HOST );

        if ( ! is_string( $host ) || $host === '' ) {
            return null;
        }

        return strtolower( $host );
    }

    /**
     * Normalize the given value to a hostname.
     *
     * @param mixed $host Hostname, or a URL to extract the hostname from.
     *
     * @return ?string Lowercase hostname, or `null` if we could not determine one.
     */
    public static function normalize_host( $host ): ?string {
        if ( ! is_string( $host ) ) {
            // Not a string, cannot normalize
            return null;
        }

        $host = trim( $host );

        if ( str_contains( $host, '/' ) ) {
            // Be forgiving and accept a full URL in place of a hostname
            return self::get_host( $host );
        }

        if ( empty( $host ) ) {
            return null;
        }

        return strtolower( $host );
    }

    /**
     * Return the hosts this site serves its own assets from.
     *
     * A site does not necessarily serve everything from a single host. WordPress may live
     * under a different host than the front-end (e.g. when it's installed in its own
     * directory), and `wp-content` may be served from somewhere else entirely.
     *
     * @return string[] Lowercase hostnames.
     */
    public static function get_site_hosts(): array {
        $hosts = array_map(
            array( self::class, 'normalize_host' ),
            array( home_url(), site_url(), content_url() )
        );

        return Array_Utils::dedupe_values( array_filter( $hosts ) );
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
