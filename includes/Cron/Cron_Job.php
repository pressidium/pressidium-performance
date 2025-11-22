<?php
/**
 * Cron job.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Cron;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Cron_Job interface.
 *
 * @since 1.0.0
 */
interface Cron_Job {

    public const SCHEDULE_HOURLY     = 'hourly';
    public const SCHEDULE_DAILY      = 'daily';
    public const SCHEDULE_TWICEDAILY = 'twicedaily';

    /**
     * Return the cron job identifier.
     *
     * @return string
     */
    public function get_identifier(): string;

    /**
     * Return the schedule: 'hourly', 'daily', 'twicedaily' or a custom schedule.
     *
     * @return string
     */
    public function get_schedule(): string;

    /**
     * Execute the cron job.
     *
     * This is the callback that runs when the cron job is triggered.
     *
     * @return void
     */
    public function execute(): void;

}
