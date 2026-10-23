<?php
/**
 * Concatenation cleaner.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Table;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Pages_Table;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Files_Table;
use Pressidium\WP\Performance\Enumerations\Output_Directory;
use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;
use Pressidium\WP\Performance\Storage\Storage;

use function Pressidium\WP\Performance\pressidium_performance_clear_background_processes;
use function Pressidium\WP\Performance\pressidium_performance_stop_background_processes;

use const Pressidium\WP\Performance\PLUGIN_DIR;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenation_Cleaner class.
 *
 * Clears everything concatenation leaves behind.
 *
 * @since 1.0.0
 */
final class Concatenation_Cleaner {

    /**
     * @var string[] Actions of the background processes that concatenate files.
     */
    private const BACKGROUND_PROCESS_ACTIONS = array(
        'concatenate_css_process',
        'concatenate_js_process',
    );

    /**
     * @var string[] Names of the transients caching concatenation statistics.
     */
    private const STATS_TRANSIENTS = array(
        'pressidium_performance_concatenations_size_saved',
        'pressidium_performance_concatenated_files_count',

        /*
         * Older versions wrote the concatenated files count under a key missing
         * the `performance` part of the prefix, so it was never read back. It is
         * listed here as well to clean it up on installs that still carry it.
         */
        'pressidium_concatenated_files_count',
    );

    /**
     * Concatenation_Cleaner constructor.
     *
     * @param Logger                     $logger                     An instance of `Logger`.
     * @param Filesystem                 $filesystem                 An instance of `Filesystem`.
     * @param Concatenations_Table       $concatenations_table       An instance of `Concatenations_Table`.
     * @param Concatenations_Pages_Table $concatenations_pages_table An instance of `Concatenations_Pages_Table`.
     * @param Concatenations_Files_Table $concatenations_files_table An instance of `Concatenations_Files_Table`.
     * @param Storage                    $transient_storage          A PSR-16 cache storage instance.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly Filesystem $filesystem,
        private readonly Concatenations_Table $concatenations_table,
        private readonly Concatenations_Pages_Table $concatenations_pages_table,
        private readonly Concatenations_Files_Table $concatenations_files_table,
        private readonly Storage $transient_storage,
    ) {}

    /**
     * Cancel the background processes that concatenate files and delete their queues.
     *
     * That way, if a batch was already queued when the cleanup started,
     * it won't recreate the files and records we are about to delete.
     *
     * @return void
     */
    private function clear_background_processes(): void {
        require_once PLUGIN_DIR . 'includes/cleanup.php';

        /*
         * A process that is running keeps working through the items it already loaded, and would
         * publish a bundle of whatever it wrote after we deleted everything. Stop it first.
         */
        $timeout = (int) apply_filters( 'pressidium_performance_concatenation_clear_timeout', 30 );

        if ( ! pressidium_performance_stop_background_processes( self::BACKGROUND_PROCESS_ACTIONS, $timeout ) ) {
            $this->logger->warning(
                sprintf( 'A concatenation process did not stop within %d seconds, clearing anyway', $timeout )
            );
        }

        pressidium_performance_clear_background_processes( self::BACKGROUND_PROCESS_ACTIONS );

        $this->logger->info( 'Cleared the concatenation background processes' );
    }

    /**
     * Delete all concatenated files.
     *
     * The `concatenated` directory itself is recreated right away, so it doesn't
     * have to wait for the next `Filesystem` instantiation to reappear.
     *
     * @throws Filesystem_Exception If the `concatenated` directory could not be recreated.
     *
     * @return int Number of files deleted.
     */
    private function delete_concatenated_files(): int {
        $concatenated_dir = $this->filesystem->build_path( Output_Directory::CONCATENATED->value );
        $files_count      = $this->filesystem->count_files( $concatenated_dir );
        $did_delete_dir   = $this->filesystem->delete_dir( $concatenated_dir );

        if ( ! $did_delete_dir ) {
            $this->logger->warning(
                sprintf( 'Could not delete the `%s` directory', esc_html( $concatenated_dir ) )
            );

            return 0;
        }

        $this->filesystem->create_dir( $concatenated_dir );

        $this->logger->info( sprintf( 'Deleted %d concatenated file(s)', $files_count ) );

        return $files_count;
    }

    /**
     * Delete all concatenation records.
     *
     * Every record in the concatenations table is a bundle, so the
     * whole table is truncated.
     *
     * @return int Number of records deleted.
     */
    private function delete_concatenation_records(): int {
        $records_count = $this->concatenations_table->get_total_number_of_rows();

        $did_clear_table = $this->concatenations_table->clear();

        if ( ! $did_clear_table ) {
            $this->logger->error( 'Could not clear the concatenations table' );

            return 0;
        }

        $this->logger->info( sprintf( 'Deleted %d concatenation record(s)', $records_count ) );

        return $records_count;
    }

    /**
     * Delete the records of the files of all bundles.
     *
     * They only describe the bundles we just deleted, so the whole table is truncated.
     *
     * @return void
     */
    private function delete_concatenation_files(): void {
        $did_clear_table = $this->concatenations_files_table->clear();

        if ( ! $did_clear_table ) {
            $this->logger->error( 'Could not clear the concatenations files table' );

            return;
        }

        $this->logger->info( 'Deleted the records of the concatenated files' );
    }

    /**
     * Delete all page to bundle mappings.
     *
     * A mapping left behind would keep pointing at a bundle we just deleted,
     * so the whole table is truncated along with the records it references.
     *
     * @return int Number of mappings deleted.
     */
    private function delete_page_mappings(): int {
        $mappings_count = $this->concatenations_pages_table->get_total_number_of_rows();

        $did_clear_table = $this->concatenations_pages_table->clear();

        if ( ! $did_clear_table ) {
            $this->logger->error( 'Could not clear the concatenations pages table' );

            return 0;
        }

        $this->logger->info( sprintf( 'Deleted %d page mapping(s)', $mappings_count ) );

        return $mappings_count;
    }

    /**
     * Delete the transients caching concatenation statistics.
     *
     * They would otherwise keep reporting the pre-cleanup
     * numbers until their TTL expires.
     *
     * @return void
     */
    private function delete_stats_transients(): void {
        try {
            $this->transient_storage->deleteMultiple( self::STATS_TRANSIENTS );
        } catch ( Exception $exception ) {
            $this->logger->debug(
                sprintf(
                    'Could not delete the concatenation stats transients: %s',
                    esc_html( $exception->getMessage() )
                )
            );
        }
    }

    /**
     * Clear everything concatenation leaves behind.
     *
     * @throws Filesystem_Exception If the `concatenated` directory could not be recreated.
     *
     * @return array{files_deleted: int, records_deleted: int, mappings_deleted: int}
     */
    public function clear(): array {
        $this->logger->info( 'Clearing concatenated files...' );

        // Cancel background processes and delete their queues
        $this->clear_background_processes();

        // Delete files, database records, and page mappings
        $files_deleted    = $this->delete_concatenated_files();
        $records_deleted  = $this->delete_concatenation_records();
        $mappings_deleted = $this->delete_page_mappings();

        $this->delete_concatenation_files();

        // Delete stats transients
        $this->delete_stats_transients();

        return array(
            'files_deleted'    => $files_deleted,
            'records_deleted'  => $records_deleted,
            'mappings_deleted' => $mappings_deleted,
        );
    }

}
