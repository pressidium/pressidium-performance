<?php
/**
 * Cron manager.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Cron;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Cron_Manager class.
 *
 * @since 1.0.0
 */
final class Cron_Manager {

    /**
     * @var Cron_Job[] $jobs The cron jobs to manage.
     */
    private array $cron_jobs = array();

    /**
     * Register a cron job.
     *
     * @param Cron_Job $cron_job The cron job to register.
     *
     * @return void
     */
    public function register_cron_job( Cron_Job $cron_job ): void {
        $this->cron_jobs[] = $cron_job;

        add_action( $cron_job->get_identifier(), array( $cron_job, 'execute' ) );
    }

    /**
     * Schedule all cron jobs, if they are not already scheduled.
     *
     * This should run on the plugin activation.
     *
     * @return void
     */
    public function schedule_events(): void {
        foreach ( $this->cron_jobs as $cron_job ) {
            $identifier = $cron_job->get_identifier();
            $time       = time();
            $recurrence = $cron_job->get_schedule();

            // Only schedule if it isn't already scheduled
            if ( ! wp_next_scheduled( $identifier ) ) {
                wp_schedule_event( $time, $recurrence, $identifier );
            }
        }
    }

}
