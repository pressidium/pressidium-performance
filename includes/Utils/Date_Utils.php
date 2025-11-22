<?php
/**
 * Date Utilities.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Utils;

use DateInterval;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Date_Utils class.
 *
 * @since 1.0.0
 */
final class Date_Utils {

    /**
     * Return the duration of the given `DateInterval` object in seconds.
     *
     * Please note that these values are approximate. For example, we (wronly) assume
     * that every month has 30 days, and we do not take leap years into account.
     *
     * @param DateInterval $interval The `DateInterval` object.
     *
     * @return string
     */
    public static function date_interval_to_seconds( DateInterval $interval ): string {
        $seconds = 0;

        $seconds += $interval->y * YEAR_IN_SECONDS;
        $seconds += $interval->m * MONTH_IN_SECONDS;
        $seconds += $interval->d * DAY_IN_SECONDS;
        $seconds += $interval->h * HOUR_IN_SECONDS;
        $seconds += $interval->i * MINUTE_IN_SECONDS;
        $seconds += $interval->s;

        return $seconds;
    }

    /**
     * Whether the given timestamp is older than the given number of days.
     *
     * @param string $timestamp Timestamp to check.
     * @param int    $days      Number of days.
     *
     * @return bool
     */
    public static function is_older_than( string $timestamp, int $days ): bool {
        $now  = time();
        $diff = $now - strtotime( $timestamp );

        $days_in_seconds = $days * DAY_IN_SECONDS;

        return $diff > $days_in_seconds;
    }

}
