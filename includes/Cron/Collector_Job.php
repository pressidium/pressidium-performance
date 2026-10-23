<?php
/**
 * Collector cron job.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Cron;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Edge\Collector\Edge_Collector_Process;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Collector_Job class.
 *
 * @since 1.2.0
 */
final class Collector_Job implements Cron_Job {

    /**
     * Collector_Job constructor.
     *
     * @param Logger                 $logger            Logger instance.
     * @param Edge_Collector_Process $collector_process Background-process collector runner.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly Edge_Collector_Process $collector_process
    ) {}

    /**
     * Return the cron job identifier.
     *
     * @return string
     */
    public function get_identifier(): string {
        return 'pressidium_performance_collector_cron_job';
    }

    /**
     * Return the cron schedule.
     *
     * @return string
     */
    public function get_schedule(): string {
        return self::SCHEDULE_TWICEDAILY;
    }

    /**
     * Execute the cron job.
     *
     * Dispatches a new collector run. Actual data collection and the EDGE
     * API POST happen in subsequent background-process requests so the
     * cron tick itself never blocks on the full payload assembly.
     *
     * @return void
     */
    public function execute(): void {
        $this->logger->info( 'Dispatching collector run from cron.' );

        $run_id = $this->collector_process->start_run();

        if ( $run_id === null ) {
            $this->logger->warning( 'Collector cron job did not dispatch a run.' );
            return;
        }

        $this->logger->info( sprintf( 'Collector cron job dispatched run %s.', $run_id ) );
    }

}

