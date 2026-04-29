<?php
/**
 * File Minification Evaluator.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Database\Tables\Optimizations_Table;
use Pressidium\WP\Performance\Optimizations\Optimization_Record;
use Pressidium\WP\Performance\Settings;
use Pressidium\WP\Performance\Code_Pruner;
use Pressidium\WP\Performance\Files\File;
use Pressidium\WP\Performance\Storage\Storage;
use Pressidium\WP\Performance\Utils\Array_Utils;
use Pressidium\WP\Performance\Utils\Date_Utils;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * File_Minification_Evaluator class.
 *
 * @since 1.0.0
 */
final class File_Minification_Evaluator {

    /**
     * @var array Settings.
     */
    private array $settings;

    /**
     * @var array Memoization of whether files should be minified.
     */
    private array $memo_should_minify;

    /**
     * @var string Cache key prefix for files that should be skipped from minification.
     */
    private const SKIP_CACHE_PREFIX = 'pp_skip_minify_';

    /**
     * File_Minification_Evaluator constructor.
     *
     * @param Logger              $logger              An instance of `Logger`.
     * @param Optimizations_Table $optimizations_table An instance of `Optimizations_Table`.
     * @param Settings            $settings_object     An instance of `Settings`.
     * @param Storage             $cache               A PSR-16 cache storage instance for skip records.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly Optimizations_Table $optimizations_table,
        Settings $settings_object,
        private readonly Storage $cache,
    ) {
        $this->settings = $settings_object->get();
    }

    /**
     * Whether the specified URL matches the file location (or a regex pattern of it).
     *
     * @param string $url_or_pattern URL or regex pattern to match.
     * @param bool   $is_regex       Whether we are matching a regex pattern.
     * @param string $file_url       URL of the file to match against.
     *
     * @return bool `true` if the URL matches, `false` otherwise.
     */
    private function matches_exclusion( string $url_or_pattern, bool $is_regex, string $file_url ): bool {
        if ( $is_regex ) {
            return preg_match( '#' . $url_or_pattern . '#', $file_url );
        }

        return $url_or_pattern === $file_url;
    }

    /**
     * Return the exclusions for the type of the given file.
     *
     * @param string $file_type Type of the file (e.g. `js`, `css`).
     *
     * @return array
     */
    private function get_minification_exclusions( string $file_type ): array {
        if ( ! isset( $this->settings['minification']['exclusions'] ) ) {
            return array();
        }

        if ( ! isset( $this->settings['minification']['exclusions'][ $file_type ] ) ) {
            return array();
        }

        return $this->settings['minification']['exclusions'][ $file_type ];
    }

    /**
     * Return the minification time to live (TTL) in days.
     *
     * @return int
     */
    private function get_minification_ttl_in_days(): int {
        /**
         * Filters the minification time-to-live (TTL) in days.
         *
         * @param int $ttl_in_days Minification TTL in days.
         */
        return apply_filters( 'pressidium_performance_minification_ttl_in_days', 7 );
    }

    /**
     * Whether the given optimization record is expired based on its last update time and the minification TTL.
     *
     * @param Optimization_Record $optimization_record Optimization record to evaluate.
     *
     * @return bool `true` if the optimization record is expired, `false` otherwise.
     */
    private function is_minified_record_expired( Optimization_Record $optimization_record ): bool {
        return Date_Utils::is_older_than(
            $optimization_record->get_updated_at(),
            $this->get_minification_ttl_in_days()
        );
    }

    /**
     * Whether the minified record is valid.
     *
     * Note that this method has a side effect of updating the optimization record if the hash matches.
     *
     * @param Optimization_Record $optimization_record Optimization record to evaluate.
     * @param File                $file                The file to evaluate.
     *
     * @return bool `true` if the minified record is valid, `false` otherwise.
     */
    private function is_minified_record_valid( Optimization_Record $optimization_record, File $file ): bool {
        $is_expired = $this->is_minified_record_expired( $optimization_record );

        if ( ! $is_expired ) {
            // Optimization record exists, and it's still valid
            return true;
        }

        // Optimization record is expired, we have to compare the hashes
        $hashes_match = hash_equals( $optimization_record->get_hash(), $file->get_hash() );

        if ( $hashes_match ) {
            try {
                $this->optimizations_table->touch_optimization_record( $optimization_record->get_original_uri() );
            } catch ( Exception $exception ) {
                $this->logger->error(
                    sprintf( 'Could not update optimization record: %s', esc_html( $exception->getMessage() ) )
                );
            }

            // Hashes match, the minified version of the file is still valid
            return true;
        }

        // Hashes do not match, the minified version of the file is expired
        return false;
    }

    /**
     * Whether the file with the given URL and type is excluded from minification.
     *
     * @param string $file_url  URL of the file to evaluate.
     * @param string $file_type Type of the file (e.g. `js`, `css`).
     *
     * @return bool `true` if the file is excluded from minification, `false` otherwise.
     */
    private function is_excluded_from_minification( string $file_url, string $file_type ): bool {
        /**
         * Filters the minification exclusions for the specified file type.
         *
         * @param array $exclusions Exclusions as an array of associative arrays with keys `url` and `is_regex`.
         */
        $exclusions = apply_filters(
            "pressidium_performance_minification_exclusions_{$file_type}",
            $this->get_minification_exclusions( $file_type )
        );

        return Array_Utils::some(
            $exclusions,
            function ( $exclusion ) use ( $file_url ) {
                return $this->matches_exclusion( $exclusion['url'], $exclusion['is_regex'], $file_url );
            }
        );
    }

    /**
     * Whether this file is already minified.
     *
     * @param File $file The file to evaluate.
     *
     * @return bool `true` if the file is already minified, `false` otherwise.
     */
    private function is_minified( File $file ): bool {
        if ( preg_match( '/([.-])min\.(js|css)$/', $file->get_filename() ) ) {
            return true;
        }

        $pruner = new Code_Pruner( $file->get_contents() );

        $pruned_content = $pruner
            ->prune_block_comments()
            ->prune_inline_comments()
            ->prune_empty_lines()
            ->get_pruned_code();

        return ! str_contains( trim( $pruned_content ), "\n" );
    }

    /**
     * Whether this file has been minified by this plugin before.
     *
     * @param File $file The file to evaluate.
     *
     * @return bool `true` if the file has been minified before, `false` otherwise.
     */
    private function has_been_minified_before( File $file ): bool {
        $optimization_record = $this->optimizations_table->get_optimization_record( $file->get_url() );

        if ( ! $optimization_record ) {
            // No optimization record found, the file has not been minified before
            return false;
        }

        return $this->is_minified_record_valid( $optimization_record, $file );
    }

    /**
     * Whether this file is of an unsupported type.
     *
     * @param File $file The file to evaluate.
     *
     * @return bool
     */
    private function is_unsupported_file_type( File $file ): bool {
        return ! in_array( strtolower( $file->get_file_type() ), array( 'js', 'css' ), true );
    }

    /**
     * Return a cache key for the given file URL.
     *
     * The URL is hashed to ensure the key stays within the transient
     * name length limit.
     *
     * @param string $file_url URL of the file.
     *
     * @return string Cache key.
     */
    private function get_skip_cache_key( string $file_url ): string {
        return self::SKIP_CACHE_PREFIX . md5( $file_url );
    }

    /**
     * Whether the given file URL has been cached as one that should be skipped from minification.
     *
     * @param string $file_url URL of the file.
     *
     * @return bool `true` if the file is cached as skippable, `false` otherwise.
     */
    private function is_cached_as_skippable( string $file_url ): bool {
        try {
            return $this->cache->has( $this->get_skip_cache_key( $file_url ) );
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
     * Uses the minification TTL so the decision is periodically re-evaluated.
     *
     * @param string $file_url URL of the file.
     *
     * @return void
     */
    private function cache_as_skippable( string $file_url ): void {
        try {
            $ttl_in_seconds = $this->get_minification_ttl_in_days() * DAY_IN_SECONDS;

            $did_store_cache_record = $this->cache->set(
                $this->get_skip_cache_key( $file_url ),
                true,
                $ttl_in_seconds
            );

            if ( ! $did_store_cache_record ) {
                $this->logger->warning(
                    sprintf(
                        'Failed to cache skip record for file: %s. ',
                        esc_html( $file_url )
                    )
                );
            }
        } catch ( Exception $exception ) {
            $this->logger->debug(
                sprintf( 'Could not cache skip record for file: %s', esc_html( $file_url ) )
            );
        }
    }

    /**
     * Return the type of the file based on its URL.
     *
     * @param string $file_url URL of the file to evaluate.
     *
     * @return string Type of the file (e.g. `js`, `css`).
     *                Empty string if the type could not be determined (e.g. no extension).
     */
    private function get_type( string $file_url ): string {
        $extension = pathinfo( wp_parse_url( $file_url, PHP_URL_PATH ), PATHINFO_EXTENSION );
        $extension = strtolower( $extension );

        if ( empty( $extension ) ) {
            return '';
        }

        return $extension;
    }

    /**
     * Whether a file should be minified based on its URL alone.
     *
     * A preliminary, lightweight check that avoids fetching the file contents.
     * Returns `false` if we can safely determine that the file should not be minified
     * based on its URL alone (e.g. because it's excluded or because we have a valid
     * optimization record), `true` if it should be minified or if we cannot determine
     * whether it should be minified without further checks.
     *
     * @param string $file_url URL of the file to evaluate.
     *
     * @return bool
     */
    public function should_be_minified_by_url( string $file_url ): bool {
        $file_type = $this->get_type( $file_url );

        if ( isset( $this->memo_should_minify[ $file_url ] ) ) {
            // We have already evaluated whether this file should be minified, return the memoized result
            $this->logger->debug( "File '{$file_url}' has already been evaluated, returning memoized result" );
            return $this->memo_should_minify[ $file_url ];
        }

        if ( $this->is_excluded_from_minification( $file_url, $file_type ) ) {
            // File is excluded from minification, memoize and bail early
            $this->logger->debug( "File '{$file_url}' is excluded from minification" );
            $this->memo_should_minify[ $file_url ] = false;
            return false;
        }

        $optimization_record = $this->optimizations_table->get_optimization_record( $file_url );

        if ( $optimization_record && ! $this->is_minified_record_expired( $optimization_record ) ) {
            // Optimization record exists and is not expired, memoize and bail early
            $this->logger->debug( "Found an optimization record for file '{$file_url}'" );
            $this->memo_should_minify[ $file_url ] = false;
            return false;
        }

        if ( $this->is_cached_as_skippable( $file_url ) ) {
            // A previous evaluation determined this file does not need minification
            $this->logger->debug( "File '{$file_url}' is cached as skippable, skipping..." );
            $this->memo_should_minify[ $file_url ] = false;
            return false;
        }

        $this->logger->debug( "Could not determine whether the file '${file_url}' should be minified based on its URL alone" );
        return true;
    }

    /**
     * Whether this file should be minified based on its URL and contents.
     *
     * A full evaluation of the file. Prefer calling {@see self::should_be_minified_by_url()}
     * first to avoid unnecessary evaluations for files that should not be minified based on
     * their URL alone.
     *
     * @param File $file File to evaluate.
     *
     * @return bool `true` if the file should be minified, `false` otherwise.
     */
    public function should_be_minified( File $file ): bool {
        $file_url = $file->get_url();

        if ( isset( $this->memo_should_minify[ $file_url ] ) ) {
            // We have already evaluated whether this file should be minified, return the memoized result
            return $this->memo_should_minify[ $file_url ];
        }

        $this->memo_should_minify[ $file_url ] = ! Array_Utils::is_any_callable_truthy(
            array(
                function () use ( $file ) {
                    return $this->has_been_minified_before( $file );
                },
                function () use ( $file ) {
                    return $file->is_empty();
                },
                function () use ( $file ) {
                    return $this->is_minified( $file );
                },
                function () use ( $file ) {
                    return $this->is_unsupported_file_type( $file );
                },
            )
        );

        if ( ! $this->memo_should_minify[ $file_url ] ) {
            /*
             * The file does not need minification (e.g. it's already minified by a third party,
             * empty, or an unsupported type). Cache this decision so that subsequent requests
             * can skip fetching the file contents entirely.
             */
            $this->cache_as_skippable( $file_url );
        }

        return $this->memo_should_minify[ $file_url ];
    }

}
