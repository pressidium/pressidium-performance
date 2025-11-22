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
     */
    public function __construct(
        private Logger $logger,
        private Filesystem $filesystem,
        private Concatenations_Pages_Table $concatenations_pages_table,
        private Concatenations_Table $concatenations_table
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
     * Execute the cron job.
     *
     * @return void
     */
    public function execute(): void {
        $concatenated_dir = $this->filesystem->build_path( Output_Directory::CONCATENATED->value );
        $files_in_dir     = $this->filesystem->list_files( $concatenated_dir );

        foreach ( $files_in_dir as $file ) {
            $aggregated_hash = pathinfo( basename( $file['name'] ), PATHINFO_FILENAME );

            if ( empty( $aggregated_hash ) ) {
                // Invalid file name, skip
                continue;
            }

            $is_referenced = $this->concatenations_pages_table->aggregated_hash_exists( $aggregated_hash );

            if ( ! $is_referenced ) {
                // File is not referenced, delete it
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

}
