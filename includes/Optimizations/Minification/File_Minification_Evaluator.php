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
     * File_Minification_Evaluator constructor.
     *
     * @param Logger              $logger              An instance of `Logger`.
     * @param Optimizations_Table $optimizations_table An instance of `Optimizations_Table`.
     * @param Settings            $settings_object     An instance of `Settings`.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly Optimizations_Table $optimizations_table,
        Settings $settings_object
    ) {
        $this->settings = $settings_object->get();
    }

    /**
     * Whether the specified URL matches the file location (or a regex pattern of it).
     *
     * @param string $url_or_pattern URL or regex pattern to match.
     * @param bool   $is_regex       Whether we are matching a regex pattern.
     * @param File   $file           The file to evaluate.
     *
     * @return bool `true` if the URL matches, `false` otherwise.
     */
    private function matches_exclusion( string $url_or_pattern, bool $is_regex, File $file ): bool {
        if ( $is_regex ) {
            return preg_match( '#' . $url_or_pattern . '#', $file->get_url() );
        }

        return $url_or_pattern === $file->get_url();
    }

    /**
     * Return the exclusions for the type of the given file.
     *
     * @param File $file The file to evaluate.
     *
     * @return array
     */
    private function get_minification_exclusions( File $file ): array {
        if ( ! isset( $this->settings['minification']['exclusions'] ) ) {
            return array();
        }

        if ( ! isset( $this->settings['minification']['exclusions'][ $file->get_file_type() ] ) ) {
            return array();
        }

        return $this->settings['minification']['exclusions'][ $file->get_file_type() ];
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
        $is_expired = Date_Utils::is_older_than(
            $optimization_record->get_updated_at(),
            $this->get_minification_ttl_in_days()
        );

        if ( ! $is_expired ) {
            // Optimization record exists, and it's still valid
            return true;
        }

        // Optimization record is expired, we have to compare the hashes
        $hashes_match = hash_equals( $optimization_record->get_hash(), $file->get_hash() );

        if ( $hashes_match ) {
            try {
                $this->optimizations_table->set_optimization_record( $optimization_record );
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
     * Whether the given file is excluded from minification.
     *
     * @param File $file The file to evaluate.
     *
     * @return bool `true` if the file is excluded from minification, `false` otherwise.
     */
    private function is_excluded_from_minification( File $file ): bool {
        $file_type = $file->get_file_type();

        /**
         * Filters the minification exclusions for the specified file type.
         *
         * @param array $exclusions Exclusions as an array of associative arrays with keys `url` and `is_regex`.
         */
        $exclusions = apply_filters(
            "pressidium_performance_minification_exclusions_{$file_type}",
            $this->get_minification_exclusions( $file )
        );

        return Array_Utils::some(
            $exclusions,
            function ( $exclusion ) use ( $file ) {
                return $this->matches_exclusion( $exclusion['url'], $exclusion['is_regex'], $file );
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
     * Whether this file should be minified.
     *
     * @param File $file The file to evaluate.
     *
     * @return bool `true` if the file should be minified, `false` otherwise.
     */
    public function should_be_minified( File $file ): bool {
        $file_url = $file->get_url();

        if ( ! isset( $this->memo_should_minify[ $file_url ] ) ) {
            $this->memo_should_minify[ $file_url ] = ! Array_Utils::is_any_callable_truthy(
                array(
                    function () use ( $file ) {
                        return $this->is_excluded_from_minification( $file );
                    },
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
        }

        return $this->memo_should_minify[ $file_url ];
    }

}
