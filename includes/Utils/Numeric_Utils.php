<?php
/**
 * Numeric Utilities.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Numeric_Utils class.
 *
 * @since 1.0.0
 */
final class Numeric_Utils {

    /**
     * Calculate the percentage difference between two values.
     *
     * @param float $old_value Old value.
     * @param float $new_value New value.
     *
     * @return float Percentage difference.
     */
    public static function calc_percent_diff( float $old_value, float $new_value ): float {
        return ( ( $new_value - $old_value ) / $old_value ) * 100;
    }

    /**
     * Return the specified size in a human-readable format using decimal system.
     *
     * This method uses a multiplication factor of 1000.
     * 1 kilobyte (kB) is equal to 1000 bytes.
     *
     * @param int $size Size in bytes.
     * @param int $precision Precision.
     *
     * @return string
     */
    public static function format_bytes_to_decimal( int $size, int $precision = 2 ): string {
        $base     = log( $size, 1000 );
        $suffixes = array(
            'B',  // byte
            'kB', // kilobyte
            'MB', // megabyte
            'GB', // gigabyte
            'TB', // terabyte
        );

        return round( pow( 1000, $base - floor( $base ) ), $precision ) . ' ' . $suffixes[ floor( $base ) ];
    }

    /**
     * Return the specified size in a human-readable format using binary system.
     *
     * This method uses a multiplication factor of 1024.
     * 1 kibibyte (KiB) is equal to 1024 bytes.
     *
     * @param int $size Size in bytes.
     * @param int $precision Precision.
     *
     * @return string
     */
    public static function format_bytes_to_binary( int $size, int $precision = 2 ): string {
        $base     = log( $size, 1024 );
        $suffixes = array(
            '',
            'KiB', // kibibyte
            'MiB', // mebibyte
            'GiB', // gibibyte
            'TiB', // tebibyte
        );

        return round( pow( 1024, $base - floor( $base ) ), $precision ) . ' ' . $suffixes[ floor( $base ) ];
    }

}
