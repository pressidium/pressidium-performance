<?php
/**
 * Minification cleaner.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Database\Tables\Optimizations_Table;
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
 * Minification_Cleaner class.
 *
 * Clears everything minification leaves behind.
 *
 * @since 1.0.0
 */
final class Minification_Cleaner {

    /**
     * @var string[] Actions of the background processes that minify files.
     */
    private const BACKGROUND_PROCESS_ACTIONS = array(
        'minify_css_process',
        'minify_js_process',
    );

    /**
     * @var string[] Names of the transients caching minification statistics.
     */
    private const STATS_TRANSIENTS = array(
        'pressidium_performance_minifications_size_saved',
        'pressidium_performance_minified_files_count',
    );

    /**
     * Minification_Cleaner constructor.
     *
     * @param Logger              $logger              An instance of `Logger`.
     * @param Filesystem          $filesystem          An instance of `Filesystem`.
     * @param Optimizations_Table $optimizations_table An instance of `Optimizations_Table`.
     * @param Skip_Cache          $skip_cache          An instance of `Skip_Cache`.
     * @param Storage             $transient_storage   A PSR-16 cache storage instance.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly Filesystem $filesystem,
        private readonly Optimizations_Table $optimizations_table,
        private readonly Skip_Cache $skip_cache,
        private readonly Storage $transient_storage,
    ) {}

    /**
     * Cancel the background processes that minify files and delete their queues.
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
         * store a record and a file after we deleted everything. Stop it first.
         */
        $timeout = (int) apply_filters( 'pressidium_performance_minification_clear_timeout', 30 );

        if ( ! pressidium_performance_stop_background_processes( self::BACKGROUND_PROCESS_ACTIONS, $timeout ) ) {
            $this->logger->warning(
                sprintf( 'A minification process did not stop within %d seconds, clearing anyway', $timeout )
            );
        }

        pressidium_performance_clear_background_processes( self::BACKGROUND_PROCESS_ACTIONS );

        $this->logger->info( 'Cleared the minification background processes' );
    }

    /**
     * Delete all minified files.
     *
     * The `minified` directory itself is recreated right away, so it doesn't
     * have to wait for the next `Filesystem` instantiation to reappear.
     *
     * @throws Filesystem_Exception If the `minified` directory could not be recreated.
     *
     * @return int Number of files deleted.
     */
    private function delete_minified_files(): int {
        $minified_dir   = $this->filesystem->build_path( Output_Directory::MINIFIED->value );
        $files_count    = $this->filesystem->count_files( $minified_dir );
        $did_delete_dir = $this->filesystem->delete_dir( $minified_dir );

        if ( ! $did_delete_dir ) {
            $this->logger->warning(
                sprintf( 'Could not delete the `%s` directory', esc_html( $minified_dir ) )
            );

            return 0;
        }

        $this->filesystem->create_dir( $minified_dir );

        $this->logger->info( sprintf( 'Deleted %d minified file(s)', $files_count ) );

        return $files_count;
    }

    /**
     * Delete all minification records.
     *
     * Every record in the optimizations table is a minification (concatenations
     * live in their own table) so the whole table is truncated.
     *
     * @return int Number of records deleted.
     */
    private function delete_optimization_records(): int {
        $records_count = $this->optimizations_table->get_total_number_of_rows();

        $did_clear_table = $this->optimizations_table->clear();

        if ( ! $did_clear_table ) {
            $this->logger->error( 'Could not clear the optimizations table' );

            return 0;
        }

        $this->logger->info( sprintf( 'Deleted %d minification record(s)', $records_count ) );

        return $records_count;
    }

    /**
     * Delete the transients caching minification statistics.
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
                    'Could not delete the minification stats transients: %s',
                    esc_html( $exception->getMessage() )
                )
            );
        }
    }

    /**
     * Clear everything minification leaves behind.
     *
     * @throws Filesystem_Exception If the `minified` directory could not be recreated.
     *
     * @return array{files_deleted: int, records_deleted: int, transients_deleted: int}
     */
    public function clear(): array {
        $this->logger->info( 'Clearing minified files...' );

        // Cancel background processes and delete their queues
        $this->clear_background_processes();

        // Delete files, database records, and skip transients
        $files_deleted      = $this->delete_minified_files();
        $records_deleted    = $this->delete_optimization_records();
        $transients_deleted = $this->skip_cache->invalidate();

        // Delete stats transients
        $this->delete_stats_transients();

        return array(
            'files_deleted'      => $files_deleted,
            'records_deleted'    => $records_deleted,
            'transients_deleted' => $transients_deleted,
        );
    }

}