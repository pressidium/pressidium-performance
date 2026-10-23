<?php
/**
 * Minification skip cache.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Options\Options;
use Pressidium\WP\Performance\Storage\Storage;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Skip_Cache class.
 *
 * Remembers files that should not be minified, so that subsequent requests
 * can skip fetching their contents entirely.
 *
 * Keys are namespaced by a generation counter that is bumped whenever the cache
 * is invalidated. Bumping the counter makes every previously stored key
 * unreachable, which is the only way to invalidate the entire cache on sites
 * running a persistent object cache (Redis), where transients never reach the
 * options table and so cannot be swept from it.
 *
 * @since 1.0.0
 */
final class Skip_Cache {

    /**
     * @var string Cache key prefix for files that should be skipped from minification.
     */
    private const KEY_PREFIX = 'pp_skip_minify_';

    /**
     * @var string Name of the option holding the generation counter.
     */
    private const GENERATION_COUNTER_OPTION = 'pressidium_performance_minification_skip_cache_generation_counter';

    /**
     * Skip_Cache constructor.
     *
     * @param Logger  $logger  An instance of `Logger`.
     * @param Storage $cache   A PSR-16 cache storage instance for skip records.
     * @param Options $options An instance of `Options`, holding the generation counter.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly Storage $cache,
        private readonly Options $options,
    ) {}

    /**
     * Return the current value of the generation counter.
     *
     * @return int
     */
    private function get_generation_counter(): int {
        return (int) $this->options->get( self::GENERATION_COUNTER_OPTION );
    }

    /**
     * Return the cache key for the given file URL.
     *
     * The URL is hashed to ensure the key stays within the transient
     * name length limit.
     *
     * @param string $file_url URL of the file.
     *
     * @return string Cache key.
     */
    private function get_key( string $file_url ): string {
        return self::KEY_PREFIX . $this->get_generation_counter() . '_' . md5( $file_url );
    }

    /**
     * Whether the given file URL has been cached as one that should be skipped from minification.
     *
     * @param string $file_url URL of the file.
     *
     * @return bool `true` if the file is cached as skippable, `false` otherwise.
     */
    public function has( string $file_url ): bool {
        try {
            return $this->cache->has( $this->get_key( $file_url ) );
        } catch ( Exception $exception ) {
            $this->logger->debug(
                sprintf(
                    'Could not check if the file has been cached as skippable: %s',
                    esc_html( $exception->getMessage() )
                )
            );

            return false;
        }
    }

    /**
     * Cache the given file URL as one that should be skipped from minification.
     *
     * @param string $file_url       URL of the file.
     * @param int    $ttl_in_seconds How long to remember the decision for, in seconds.
     *
     * @return void
     */
    public function remember( string $file_url, int $ttl_in_seconds ): void {
        try {
            $did_store_cache_record = $this->cache->set(
                $this->get_key( $file_url ),
                true,
                $ttl_in_seconds
            );

            if ( ! $did_store_cache_record ) {
                $this->logger->warning(
                    sprintf( 'Failed to cache skip record for file: %s', esc_html( $file_url ) )
                );
            }
        } catch ( Exception $exception ) {
            $this->logger->debug(
                sprintf( 'Could not cache skip record for file: %s', esc_html( $file_url ) )
            );
        }
    }

    /**
     * Return the names of every skip transient currently stored in the options table.
     *
     * Sites running a persistent object cache (Redis) store transients
     * outside the options table, so this returns an empty array there.
     * Bumping the generation counter is what invalidates the cache on those sites.
     *
     * @return string[] Transient names, without the `_transient_` or `_transient_timeout_` prefixes.
     */
    private function get_stored_transient_names(): array {
        global $wpdb;

        $value_prefix   = '_transient_';
        $timeout_prefix = '_transient_timeout_';

        $value_like   = $wpdb->esc_like( $value_prefix . self::KEY_PREFIX ) . '%';
        $timeout_like = $wpdb->esc_like( $timeout_prefix . self::KEY_PREFIX ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $option_names = (array) $wpdb->get_col(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $value_like,
                $timeout_like
            )
        );

        $transient_names = array();

        foreach ( $option_names as $option_name ) {
            $prefix = str_starts_with( $option_name, $timeout_prefix ) ? $timeout_prefix : $value_prefix;

            $transient_names[] = substr( $option_name, strlen( $prefix ) );
        }

        return array_values( array_unique( $transient_names ) );
    }

    /**
     * Invalidate every cached skip decision.
     *
     * Bumps the generation counter so that previously stored keys can no longer
     * be reached, then deletes the transients left behind in the options table
     * so they don't linger there until their TTL expires.
     *
     * @return int Number of skip transients deleted from the options table.
     */
    public function invalidate(): int {
        $generation_counter = $this->get_generation_counter() + 1;

        $did_bump_counter = $this->options->set( self::GENERATION_COUNTER_OPTION, $generation_counter );

        if ( ! $did_bump_counter ) {
            $this->logger->error( 'Could not bump the minification skip cache generation counter' );
        }

        $transient_names = $this->get_stored_transient_names();
        $deleted_count   = 0;

        foreach ( $transient_names as $transient_name ) {
            if ( delete_transient( $transient_name ) ) {
                $deleted_count += 1;
            }
        }

        $this->logger->info(
            sprintf(
                'Invalidated the minification skip cache (generation counter %d, %d transient(s) deleted)',
                $generation_counter,
                $deleted_count
            )
        );

        return $deleted_count;
    }

}
