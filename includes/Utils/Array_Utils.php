<?php
/**
 * Array Utilities.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Array_Utils class.
 *
 * @since 1.0.0
 */
final class Array_Utils {

    /**
     * Test whether all elements in the given array pass the test implemented by the provided function.
     *
     * @param array    $arr
     * @param callable $callback
     *
     * @return bool `true` if the callback function returns a truthy
     *              value for every array element. Otherwise, `false`.
     */
    public static function every( array $arr, callable $callback ): bool {
        foreach ( $arr as $element ) {
            if ( ! call_user_func( $callback, $element ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Test whether at least one element in the given array passes the test implemented by the provided function.
     *
     * This method short circuits the execution of the callback function as soon as it finds an element for which
     * the callback function returns a truthy value.
     *
     * @param array    $arr
     * @param callable $callback
     *
     * @return bool `true` if the callback function returns a truthy value
     *              for at least one element in the array. Otherwise, `false`.
     */
    public static function some( array $arr, callable $callback ): bool {
        foreach ( $arr as $element ) {
            if ( call_user_func( $callback, $element ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flatten array, converting a multi-dimensional array to a single-dimensional array.
     *
     * @param array $arr The multi-dimensional array to flatten.
     *
     * @return array The single-dimensional array.
     */
    public static function flatten( array $arr ): array {
        $flat_array = array();

        foreach ( $arr as $key => $value ) {
            if ( is_array( $value ) ) {
                $flat_array = array_merge( $flat_array, self::flatten( $value ) );
                continue;
            }

            $flat_array = array_merge( $flat_array, array( $key => $value ) );
        }

        return $flat_array;
    }

    /**
     * Test whether at least one callable in the given array returns a truthy value.
     *
     * @param callable[] $rules Array of callables to check.
     *
     * @return bool `true` if at least one callable returns a truthy value. Otherwise, `false`.
     */
    public static function is_any_callable_truthy( array $rules ): bool {
        return self::some(
            $rules,
            function ( callable $rule ) {
                return call_user_func( $rule );
            }
        );
    }

    /**
     * Move the given value to the beginning of the given array.
     *
     * @param array $arr   Array to move the value in.
     * @param mixed $value Value to move to the beginning of the array.
     *
     * @return array Array with the value moved to the beginning.
     */
    public static function move_value_to_beginning( array $arr, $value ): array {
        $index = array_search( $value, $arr, true );

        if ( $index === false ) {
            return $arr;
        }

        unset( $arr[ $index ] );
        array_unshift( $arr, $value );

        return $arr;
    }

    /**
     * Sort the given array.
     *
     * @param array $arr Array to sort.
     *
     * @return array Sorted array.
     */
    public static function sort( array $arr ): array {
        sort( $arr );
        return $arr;
    }

    /**
     * Whether all the given keys exist in the given array.
     *
     * @param string[]             $keys Keys to check.
     * @param array<string, mixed> $arr  Array to check in.
     *
     * @return bool
     */
    public static function array_keys_exist( array $keys, array $arr ): bool {
        return empty( array_diff( $keys, array_keys( $arr ) ) );
    }

}
