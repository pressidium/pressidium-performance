<?php
/**
 * Exclusion matcher.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance;

use Pressidium\WP\Performance\Utils\Array_Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Exclusion_Matcher class.
 *
 * Matches asset URLs against the exclusions of the settings, which
 * are either exact URLs or regular expressions (without delimiters).
 *
 * @since 1.2.0
 */
final class Exclusion_Matcher {

    /**
     * Whether the given URL matches the given exclusion.
     *
     * An invalid regular expression never matches.
     *
     * @param string $url            URL to check.
     * @param string $url_or_pattern Exact URL, or a regular expression without delimiters.
     * @param bool   $is_regex       Whether we are matching a regex pattern.
     *
     * @return bool
     */
    public static function matches( string $url, string $url_or_pattern, bool $is_regex ): bool {
        if ( ! $is_regex ) {
            return $url_or_pattern === $url;
        }

        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an invalid pattern must not match, nor warn
        return @preg_match( '#' . $url_or_pattern . '#', $url ) === 1;
    }

    /**
     * Whether the given URL matches any of the given exclusions.
     *
     * @param string                      $url        URL to check.
     * @param array<array<string, mixed>> $exclusions Exclusions, each with a `url` and an `is_regex` key.
     *
     * @return bool
     */
    public static function matches_any( string $url, array $exclusions ): bool {
        return Array_Utils::some(
            $exclusions,
            function ( $exclusion ) use ( $url ) {
                $url_or_pattern = (string) ( $exclusion['url'] ?? '' );
                $is_regex       = (bool) ( $exclusion['is_regex'] ?? false );

                return self::matches( $url, $url_or_pattern, $is_regex );
            }
        );
    }

}
