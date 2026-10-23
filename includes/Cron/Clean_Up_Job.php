<?php
/**
 * Clean-up cron job.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Cron;

use Pressidium\WP\Performance\Enumerations\Output_Directory;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Pages_Table;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Table;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Files_Table;
use Pressidium\WP\Performance\Database\Tables\Optimizations_Table;
use Pressidium\WP\Performance\Optimizations\Minification\Minified_Path_Builder;
use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenator;
use Pressidium\WP\Performance\Utils\String_Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Clean_Up_Job class.
 *
 * @since 1.0.0
 */
final class Clean_Up_Job implements Cron_Job {

    /**
     * Clean_Up_Job constructor.
     *
     * @param Logger                     $logger
     * @param Filesystem                 $filesystem
     * @param Concatenations_Pages_Table $concatenations_pages_table
     * @param Concatenations_Table       $concatenations_table
     * @param Concatenations_Files_Table $concatenations_files_table
     * @param Optimizations_Table        $optimizations_table
     * @param Minified_Path_Builder      $minified_path_builder
     */
    public function __construct(
        private Logger $logger,
        private Filesystem $filesystem,
        private Concatenations_Pages_Table $concatenations_pages_table,
        private Concatenations_Table $concatenations_table,
        private Concatenations_Files_Table $concatenations_files_table,
        private Optimizations_Table $optimizations_table,
        private Minified_Path_Builder $minified_path_builder
    ) {}

    /**
     * Return the cron job identifier.
     *
     * @return string
     */
    public function get_identifier(): string {
        return 'pressidium_performance_clean_up_cron_job';
    }

    /**
     * Return the cron schedule.
     *
     * @return string
     */
    public function get_schedule(): string {
        return self::SCHEDULE_DAILY;
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
     * Return the grace period in days for expired minification records.
     *
     * Records are kept for this many additional days after expiring,
     * giving them a chance to be re-validated before their files are deleted.
     *
     * @return int
     */
    private function get_minification_grace_period_in_days(): int {
        /**
         * Filters the grace period in days for expired minification records.
         *
         * @param int $grace_period_in_days Grace period in days.
         */
        return apply_filters( 'pressidium_performance_minification_grace_period_in_days', 7 );
    }

    /**
     * Return the concatenation time to live (TTL) in days.
     *
     * @return int
     */
    private function get_concatenation_ttl_in_days(): int {
        /**
         * Filters the concatenation time-to-live (TTL) in days.
         *
         * @param int $ttl_in_days Concatenation TTL in days.
         */
        return apply_filters( 'pressidium_performance_concatenation_ttl_in_days', 7 );
    }

    /**
     * Return the grace period in days for expired page mappings.
     *
     * Page mapping are kept for this many additional days after expiring, giving
     * them a chance to be touched before the bundles they reference are deleted.
     *
     * @return int
     */
    private function get_concatenation_grace_period_in_days(): int {
        /**
         * Filters the grace period in days for expired page mappings.
         *
         * @param int $grace_period_in_days Grace period in days.
         */
        return apply_filters( 'pressidium_performance_concatenation_grace_period_in_days', 7 );
    }

    /**
     * Clean up page mappings for pages that have not been visited in a while.
     *
     * A page mapping keeps the bundle it references alive, so nothing is ever cleaned up
     * until the mapping itself goes away. This has to run before the unreferenced bundle
     * cleanup, which is what actually deletes the files and records that are left behind.
     *
     * @return void
     */
    private function clean_up_page_mappings(): void {
        $ttl_in_days          = $this->get_concatenation_ttl_in_days();
        $grace_period_in_days = $this->get_concatenation_grace_period_in_days();

        $deleted_count = $this->concatenations_pages_table->delete_expired_mappings(
            $ttl_in_days,
            $grace_period_in_days
        );

        if ( $deleted_count === 0 ) {
            return;
        }

        $this->logger->info( sprintf( 'Deleted %d expired page mapping(s)', $deleted_count ) );
    }

    /**
     * Clean up unreferenced concatenated files.
     *
     * @return void
     */
    private function clean_up_concatenated_files(): void {
        $concatenated_dir = $this->filesystem->build_path( Output_Directory::CONCATENATED->value );
        $files_in_dir     = $this->filesystem->list_files( $concatenated_dir );

        foreach ( $files_in_dir as $file ) {
            // A bundle that is still being built is named `<hash>.partial.<ext>`
            $aggregated_hash = pathinfo( basename( $file['name'] ), PATHINFO_FILENAME );
            $aggregated_hash = String_Utils::strip_suffix( Concatenator::PARTIAL_SUFFIX, $aggregated_hash );

            // Stylesheet bundles are split into segments, named `<hash>-<segment>.css`
            $aggregated_hash = preg_replace( '/-\d+$/', '', $aggregated_hash );

            if ( empty( $aggregated_hash ) ) {
                // Invalid file name, skip
                continue;
            }

            $is_referenced = $this->concatenations_pages_table->aggregated_hash_exists( $aggregated_hash );

            if ( ! $is_referenced ) {
                // File is not referenced, delete it along with the records of its files
                $concatenation_record = $this->concatenations_table->get_concatenation_record( $aggregated_hash );

                if ( $concatenation_record !== null ) {
                    $this->concatenations_files_table->delete_by_concatenation_id( $concatenation_record->get_id() );
                }

                $did_delete = $this->concatenations_table->delete_by_aggregated_hash( $aggregated_hash );

                if ( ! $did_delete ) {
                    // Could not delete database record, skip file deletion
                    $this->logger->warning(
                        sprintf( 'Could not delete concatenated record %s', esc_html( $aggregated_hash ) )
                    );

                    continue;
                }

                $destination = $this->filesystem->build_path( Output_Directory::CONCATENATED->value, $file['name'] );
                $this->filesystem->delete_file( $destination );

                $this->logger->info(
                    sprintf( 'Deleted unreferenced concatenated file: %s', esc_html( $file['name'] ) )
                );
            }
        }
    }

    /**
     * Clean up expired optimization records.
     *
     * Only the records are deleted in this methid. The files they used to point at are left
     * as is. `clean_up_unreferenced_minified_files()` will safely delete the files as well.
     *
     * @return void
     */
    private function clean_up_expired_optimization_records(): void {
        $ttl_in_days          = $this->get_minification_ttl_in_days();
        $grace_period_in_days = $this->get_minification_grace_period_in_days();

        $expired_records = $this->optimizations_table->get_expired_optimization_records(
            $ttl_in_days,
            $grace_period_in_days
        );

        if ( empty( $expired_records ) ) {
            return;
        }

        $this->logger->info(
            sprintf( 'Found %d expired minification record(s) to clean up', count( $expired_records ) )
        );

        foreach ( $expired_records as $record ) {
            $original_uri = $record->get_original_uri();

            $did_delete = $this->optimizations_table->delete_optimization_record( $original_uri );

            if ( ! $did_delete ) {
                $this->logger->warning(
                    sprintf( 'Could not delete optimization record for %s', esc_html( $original_uri ) )
                );
            }
        }
    }

    /**
     * Return the relative paths of every minified file an optimization record points at.
     *
     * @return ?array<string, true> Set of relative paths for lookups by key, or `null` if
     *                              the optimization records could not be read.
     */
    private function get_referenced_minified_paths(): ?array {
        $optimized_uris = $this->optimizations_table->get_all_optimized_uris();

        if ( empty( $optimized_uris ) ) {
            return null;
        }

        $referenced_paths = array();

        foreach ( $optimized_uris as $optimized_uri ) {
            $relative_path = $this->minified_path_builder->relative_path_from_uri( $optimized_uri );

            if ( $relative_path === null ) {
                continue;
            }

            $referenced_paths[ $relative_path ] = true;
        }

        return $referenced_paths;
    }

    /**
     * Clean up unreferenced minified files that are older than the TTL plus the grace period.
     *
     * @return void
     */
    private function clean_up_unreferenced_minified_files(): void {
        // We have to wait for the grace period to expire before we can be sure a file is safe to delete
        $threshold_in_days = $this->get_minification_ttl_in_days() + $this->get_minification_grace_period_in_days();
        $threshold         = time() - ( $threshold_in_days * DAY_IN_SECONDS );

        $minified_dir = $this->filesystem->build_path( Output_Directory::MINIFIED->value );
        $files        = $this->filesystem->list_files_recursive( $minified_dir );

        if ( empty( $files ) ) {
            // No minified files to clean up, bail early
            return;
        }

        $referenced_paths = $this->get_referenced_minified_paths();

        if ( $referenced_paths === null ) {
            // Could not read the records, so we have no idea which of these files are still in use
            $this->logger->warning(
                'Could not read the optimization records, skipping the minified file cleanup'
            );

            return;
        }

        $deleted_count = 0;

        foreach ( $files as $relative_path => $last_modified ) {
            if ( isset( $referenced_paths[ $relative_path ] ) ) {
                // Still referenced by an optimization record, skip
                continue;
            }

            if ( $last_modified >= $threshold ) {
                // Not old enough to delete yet, skip
                continue;
            }

            // File is unreferenced and old enough, delete it
            $did_delete = $this->filesystem->delete_file(
                $this->filesystem->build_path( Output_Directory::MINIFIED->value, $relative_path )
            );

            if ( ! $did_delete ) {
                $this->logger->warning(
                    sprintf( 'Could not delete unreferenced minified file: %s', esc_html( $relative_path ) )
                );

                continue;
            }

            $deleted_count += 1;
        }

        if ( $deleted_count === 0 ) {
            // No unreferenced minified files were deleted, bail early
            return;
        }

        $pruned_count = $this->filesystem->prune_empty_dirs( $minified_dir );

        $this->logger->info(
            sprintf(
                'Deleted %d unreferenced minified file(s) and %d empty directory(s)',
                $deleted_count,
                $pruned_count
            )
        );
    }

    /**
     * Execute the cron job.
     *
     * @return void
     */
    public function execute(): void {
        /*
         * Order matters here. We have to clean any expired mappings first,
         * so the bundles they were keeping alive show up as unreferenced below.
         */
        $this->clean_up_page_mappings();
        $this->clean_up_concatenated_files();

        /*
         * Order matters here. We have to clean any expired records fitst,
         * so the files they were keeping alive show up as unreferenced below.
         */
        $this->clean_up_expired_optimization_records();
        $this->clean_up_unreferenced_minified_files();
    }

}
