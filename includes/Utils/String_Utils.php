<?php
/**
 * String Utilities.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * String_Utils class.
 *
 * @since 1.0.0
 */
final class String_Utils {

    /**
     * Return the given string with the given prefix removed.
     *
     * @since 1.0.0
     *
     * @param string $prefix Prefix to remove from the string.
     * @param string $str    String to remove the prefix from.
     *
     * @return string
     */
    public static function strip_prefix( string $prefix, string $str ): string {
        if ( str_starts_with( $str, $prefix ) ) {
            return substr( $str, strlen( $prefix ) );
        }

        return $str;
    }

    /**
     * Return the given string with the given suffix removed.
     *
     * @since 1.0.0
     *
     * @param string $suffix Suffix to remove from the string.
     * @param string $str    String to remove the suffix from.
     *
     * @return string
     */
    public static function strip_suffix( string $suffix, string $str ): string {
        if ( str_ends_with( $str, $suffix ) && ! empty( $suffix ) ) {
            return substr( $str, 0, -strlen( $suffix ) );
        }

        return $str;
    }

    /**
     * Prepend the given prefix.
     *
     * Will only prepend the given prefix if it doesn't already exist,
     * preventing double prefixing the given string.
     *
     * @since 1.1.0
     *
     * @param string $string What to prepend the given prefix to.
     * @param string $prefix Prefix to prepend.
     *
     * @return string String with prefix prepended.
     */
    public static function prefix_it( string $string, string $prefix ): string {
        return str_starts_with( $string, $prefix ) ? $string : $prefix . $string;
    }

    /**
     * Append the given suffix.
     *
     * Will only append the given suffix if it doesn't already exist,
     * preventing double suffixing the given string.
     *
     * @since 1.1.0
     *
     * @param string $string What to append the given suffix to.
     * @param string $suffix Suffix to append.
     *
     * @return string String with suffix appended.
     */
    public static function suffix_it( string $string, string $suffix ): string {
        return str_ends_with( $string, $suffix ) ? $string : $string . $suffix;
    }

    /**
     * Prepend a leading slash.
     *
     * Will only prepend a leading slash if it doesn't already exist,
     * preventing double slashing the given string.
     *
     * @since 1.1.0
     *
     * @param string $string What to add the leading slash to.
     *
     * @return string String with leading slash added.
     */
    public static function leading_slash_it( string $string ): string {
        return self::prefix_it( $string, '/' );
    }


    /**
     * Return the given string with the leading slash removed (if any).
     *
     * @since 1.0.0
     *
     * @param string $value String to remove the leading slash from.
     *
     * @return string String with leading slash removed.
     */
    public static function unleading_slash_it( string $value ): string {
        return self::strip_prefix( '/', $value );
    }

    /**
     * Append a trailing slash.
     *
     * Will only append a trailing slash if it doesn't already exist,
     * preventing double slashing the given string.
     *
     * @since 1.1.0
     *
     * @param string $string What to add the trailing slash to.
     *
     * @return string String with trailing slash added
     */
    public static function trailing_slash_it( string $string ): string {
        return self::suffix_it( $string, '/' );
    }

    /**
     * Return the given string with the trailing slash removed (if any).
     *
     * @since 1.0.0
     *
     * @param string $value String to remove the trailing slash from.
     *
     * @return string String with trailing slash removed
     */
    public static function untrailing_slash_it( string $value ): string {
        return self::strip_suffix( '/', $value );
    }

    /**
     * Truncate a string to the given length.
     *
     * @param ?string $string String to truncate.
     * @param int     $length Length to truncate the string to.
     *
     * @return ?string Truncated string.
     */
    public static function truncate( ?string $string, int $length ): ?string {
        if ( is_null( $string ) ) {
            return null;
        }

        return substr( $string, 0, $length );
    }

    /**
     * Whether the given string is a URL.
     *
     * @since 1.0.0
     *
     * @param string $value String to check.
     *
     * @return bool Whether the given string is a URL.
     */
    public static function is_url( string $value ): bool {
        return filter_var( $value, FILTER_VALIDATE_URL ) !== false;
    }

}
