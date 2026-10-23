<?php
/**
 * WP Background Process.
 *
 * Based on WP Background Processing by Delicious Brains
 *
 * @link github.com/deliciousbrains/wp-background-processing
 *
 * Modified by Konstantinos Pappas <konpap@pressidium.com>
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Background_Processing;

use WP_Error;

use stdClass;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Abstract WP_Background_Process class.
 *
 * @abstract
 * @extends WP_Async_Request
 */
abstract class WP_Background_Process extends WP_Async_Request {

    /**
     * The default query arg name used for passing the chain ID to new processes.
     */
    const CHAIN_ID_ARG_NAME = 'chain_id';

    /**
     * Unique background process chain ID.
     *
     * @var string
     */
    private string $chain_id;

    /**
     * Action
     *
     * (default value: 'background_process')
     *
     * @var string
     * @access protected
     */
    protected string $action = 'background_process';

    /**
     * Start time of current process.
     *
     * (default value: 0)
     *
     * @var int
     * @access protected
     */
    protected int $start_time = 0;

    /**
     * Value of the lock held by this process (`<expires at>|<token>`), or `null` if it holds none.
     *
     * @var ?string
     */
    private ?string $lock_value = null;

    /**
     * Cron_hook_identifier
     *
     * @var string
     * @access protected
     */
    protected string $cron_hook_identifier;

    /**
     * Cron_interval_identifier
     *
     * @var string
     * @access protected
     */
    protected string $cron_interval_identifier;

    /**
     * Restrict object instantiation when using unserialize.
     *
     * @var bool|array
     */
    protected bool|array $allowed_batch_data_classes = true;

    /**
     * The status set when process is cancelling.
     *
     * @var int
     */
    const STATUS_CANCELLED = 1;

    /**
     * The status set when process is paused or pausing.
     *
     * @var int;
     */
    const STATUS_PAUSED = 2;

    /**
     * Initiate new background process.
     *
     * @param bool|string[] $allowed_batch_data_classes Optional. Array of class names that can
     *                                                  be unserialized. Default true (any class).
     */
    public function __construct( bool|array $allowed_batch_data_classes = true ) {
        parent::__construct();

        if ( empty( $allowed_batch_data_classes ) && $allowed_batch_data_classes !== false ) {
            $allowed_batch_data_classes = true;
        }

        if ( ! is_bool( $allowed_batch_data_classes ) && ! is_array( $allowed_batch_data_classes ) ) {
            $allowed_batch_data_classes = true;
        }

        /*
         * If allowed_batch_data_classes property set in subclass,
         * only apply override if not allowing any class.
         */
        if ( $this->allowed_batch_data_classes === true || $allowed_batch_data_classes !== true ) {
            $this->allowed_batch_data_classes = $allowed_batch_data_classes;
        }

        $this->cron_hook_identifier     = $this->identifier . '_cron';
        $this->cron_interval_identifier = $this->identifier . '_cron_interval';

        add_action( $this->cron_hook_identifier, array( $this, 'handle_cron_healthcheck' ) );
        add_filter( 'cron_schedules', array( $this, 'schedule_cron_healthcheck' ) );

        // Ensure dispatch query args included extra data
        add_filter( $this->identifier . '_query_args', array( $this, 'filter_dispatch_query_args' ) );
    }

    /**
     * Schedule the cron healthcheck and dispatch an async request to start processing the queue.
     *
     * @access public
     * @return array|WP_Error|false HTTP Response array, WP_Error on failure, or false if not attempted.
     */
    public function dispatch(): array|WP_Error|false {
        if ( $this->is_processing() ) {
            // Process already running
            return false;
        }

        /**
         * Filter fired before background process dispatches its next process.
         *
         * @param bool $cancel Should the dispatch be cancelled? Default false.
         * @param string $chain_id The background process chain ID.
         */
        $cancel = apply_filters( $this->identifier . '_pre_dispatch', false, $this->get_chain_id() );

        if ( $cancel ) {
            return false;
        }

        // Schedule the cron healthcheck
        $this->schedule_event();

        // Perform remote post
        $result = parent::dispatch();

        if ( is_wp_error( $result ) ) {
            /**
             * Fires when a background process dispatch fails.
             *
             * @param WP_Error $result   The error returned by the dispatch attempt.
             * @param string   $chain_id The background process chain ID.
             */
            do_action( $this->identifier . '_dispatch_failed', $result, $this->get_chain_id() );
        }

        return $result;
    }

    /**
     * Push to the queue.
     *
     * Note, save must be called in order to persist queued items to a batch for processing.
     *
     * @param mixed $data Data.
     *
     * @return WP_Background_Process
     */
    public function push_to_queue( $data ): WP_Background_Process {
        $this->data[] = $data;

        return $this;
    }

    /**
     * Save the queued items for future processing.
     *
     * @return WP_Background_Process
     */
    public function save(): WP_Background_Process {
        $key = $this->generate_key();

        if ( ! empty( $this->data ) ) {
            update_site_option( $key, $this->data );
        }

        // Clean out data so that new data isn't prepended with closed session's data
        $this->data = array();

        return $this;
    }

    /**
     * Update a batch's queued items.
     *
     * @param string $key  Key.
     * @param array  $data Data.
     *
     * @return WP_Background_Process
     */
    public function update( string $key, array $data ): WP_Background_Process {
        if ( ! empty( $data ) ) {
            update_site_option( $key, $data );
        }

        return $this;
    }

    /**
     * Delete a batch of queued items.
     *
     * @param string $key Key.
     *
     * @return WP_Background_Process
     */
    public function delete( string $key ): WP_Background_Process {
        delete_site_option( $key );

        return $this;
    }

    /**
     * Delete entire job queue.
     *
     * @return void
     */
    public function delete_all(): void {
        $batches = $this->get_batches();

        foreach ( $batches as $batch ) {
            $this->delete( $batch->key );
        }

        delete_site_option( $this->get_status_key() );

        $this->cancelled();
    }

    /**
     * Cancel job on next batch.
     *
     * @return void
     */
    public function cancel(): void {
        update_site_option( $this->get_status_key(), self::STATUS_CANCELLED );

        // Just in case the job was paused at the time
        $this->dispatch();
    }

    /**
     * Whether the process has been cancelled.
     *
     * @return bool
     */
    public function is_cancelled(): bool {
        return $this->get_status() === self::STATUS_CANCELLED;
    }

    /**
     * Called when background process has been cancelled.
     *
     * @return void
     */
    protected function cancelled(): void {
        do_action( $this->identifier . '_cancelled', $this->get_chain_id() );
    }

    /**
     * Pause job on next batch.
     *
     * @return void
     */
    public function pause(): void {
        update_site_option( $this->get_status_key(), self::STATUS_PAUSED );
    }

    /**
     * Whether the process has been paused.
     *
     * @return bool
     */
    public function is_paused(): bool {
        return $this->get_status() === self::STATUS_PAUSED;
    }

    /**
     * Called when background process has been paused.
     *
     * @return void
     */
    protected function paused(): void {
        do_action( $this->identifier . '_paused', $this->get_chain_id() );
    }

    /**
     * Resume job.
     *
     * @return void
     */
    public function resume(): void {
        delete_site_option( $this->get_status_key() );

        $this->schedule_event();
        $this->dispatch();
        $this->resumed();
    }

    /**
     * Called when background process has been resumed.
     *
     * @return void
     */
    protected function resumed(): void {
        do_action( $this->identifier . '_resumed', $this->get_chain_id() );
    }

    /**
     * Whether the process has items in the queue.
     *
     * @return bool
     */
    public function is_queued(): bool {
        return ! $this->is_queue_empty();
    }

    /**
     * Whether the process is currently active (e.g. starting, working, paused or cleaning up).
     *
     * @return bool
     */
    public function is_active(): bool {
        return $this->is_queued() || $this->is_processing() || $this->is_paused() || $this->is_cancelled();
    }

    /**
     * Generate key for a batch.
     *
     * Generates a unique key based on microtime. Queue items are
     * given a unique key so that they can be merged upon save.
     *
     * @param ?int    $length Optional max length to trim key to, defaults to 64 characters.
     * @param ?string $key    Optional string to append to identifier before hash, defaults to "batch".
     *
     * @return string
     */
    protected function generate_key( ?int $length = 64, ?string $key = 'batch' ): string {
        $unique  = md5( microtime() . wp_rand() );
        $prepend = $this->identifier . '_' . $key . '_';

        return substr( $prepend . $unique, 0, $length );
    }

    /**
     * Return the status key.
     *
     * @return string
     */
    protected function get_status_key(): string {
        return $this->identifier . '_status';
    }

    /**
     * Return the status value for the process.
     *
     * @return int
     */
    protected function get_status(): int {
        global $wpdb;

        if ( is_multisite() ) {
            $status = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_value FROM $wpdb->sitemeta WHERE meta_key = %s AND site_id = %d LIMIT 1",
                    $this->get_status_key(),
                    get_current_network_id()
                )
            );
        } else {
            $status = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1",
                    $this->get_status_key()
                )
            );
        }

        return absint( $status );
    }

    /**
     * Maybe process a batch of queued items.
     *
     * Checks whether data exists within the queue and that
     * the process is not already running.
     *
     * @return void
     */
    public function maybe_handle(): void {
        // Don't lock up other requests while processing
        session_write_close();

        check_ajax_referer( $this->identifier, 'nonce' );

        // Background process already running
        if ( $this->is_processing() ) {
            $this->maybe_wp_die();
            return;
        }

        // Cancel requested
        if ( $this->is_cancelled() ) {
            $this->clear_scheduled_event();
            $this->delete_all();

            $this->maybe_wp_die();
            return;
        }

        // Pause requested
        if ( $this->is_paused() ) {
            $this->clear_scheduled_event();
            $this->paused();

            $this->maybe_wp_die();
            return;
        }

        // No data to process
        if ( $this->is_queue_empty() ) {
            $this->maybe_wp_die();
            return;
        }

        $this->handle();

        $this->maybe_wp_die();
    }

    /**
     * Whether the queue is empty.
     *
     * @return bool
     */
    protected function is_queue_empty(): bool {
        return $this->get_batch() === null;
    }

    /**
     * Whether the process is currently running.
     *
     * Check whether the current process is already running
     * in a background process.
     *
     * @return bool
     *
     * @deprecated 1.1.0 Superseded.
     * @see        is_processing()
     */
    protected function is_process_running(): bool {
        return $this->is_processing();
    }

    /**
     * Return the name of the option that holds the process lock.
     *
     * @return string
     */
    protected function get_lock_name(): string {
        return $this->identifier . '_process_lock';
    }

    /**
     * Return the table that holds the process lock.
     *
     * The lock has to be taken with a single atomic query, which needs the unique
     * key on `option_name` of an options table (`sitemeta` has no such key), so
     * on multisite the options table of the main site is used.
     *
     * @return string
     */
    private function get_lock_table(): string {
        global $wpdb;

        return is_multisite() ? $wpdb->get_blog_prefix( get_main_site_id() ) . 'options' : $wpdb->options;
    }

    /**
     * Return the expiry timestamp of a lock value.
     *
     * @param string $lock_value Lock value (`<expires at>|<token>`).
     *
     * @return int
     */
    private function get_lock_expiry( string $lock_value ): int {
        return (int) strtok( $lock_value, '|' );
    }

    /**
     * Whether the process is currently running.
     *
     * @return bool
     */
    public function is_processing(): bool {
        global $wpdb;

        $lock_table = $this->get_lock_table();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $lock_value = $wpdb->get_var(
            $wpdb->prepare( "SELECT option_value FROM {$lock_table} WHERE option_name = %s", $this->get_lock_name() )
        );
        // phpcs:enable

        // Process already running, unless its lock has expired
        return is_string( $lock_value ) && $this->get_lock_expiry( $lock_value ) > time();
    }

    /**
     * Lock process.
     *
     * Lock the process so that multiple instances can't run simultaneously.
     * Override if applicable, but the duration should be greater than that
     * defined in the time_exceeded() method.
     *
     * Taking the lock is atomic: it is inserted with `INSERT IGNORE` (so only one of any
     * concurrent requests creates it), or taken over with a conditional `UPDATE` once it
     * has expired (its process died without releasing it). Checking for a lock and then
     * setting it in separate queries would let concurrent requests all get through, and
     * process the same batches in parallel.
     *
     * A process that already holds the lock may call this again to extend it.
     *
     * @param bool $reset_start_time Optional, default true.
     *
     * @return bool Whether this process holds the lock.
     */
    public function lock_process( bool $reset_start_time = true ): bool {
        global $wpdb;

        if ( $reset_start_time ) {
            // Set start time of current process
            $this->start_time = time();
        }

        $lock_duration = ( property_exists( $this, 'queue_lock_time' ) )
            ? $this->queue_lock_time
            : 60; // 1 minute

        $lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );

        $now        = time();
        $lock_value = ( $now + (int) $lock_duration ) . '|' . wp_generate_uuid4();
        $lock_table = $this->get_lock_table();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $inserted = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$lock_table} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
                $this->get_lock_name(),
                $lock_value
            )
        );

        $locked = $inserted === 1;

        if ( ! $locked ) {
            // The lock exists, take it over only if it is ours, or it has expired
            $updated = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$lock_table} SET option_value = %s
                    WHERE option_name = %s
                    AND ( option_value = %s OR CAST( SUBSTRING_INDEX( option_value, '|', 1 ) AS UNSIGNED ) <= %d )",
                    $lock_value,
                    $this->get_lock_name(),
                    (string) $this->lock_value,
                    $now
                )
            );

            $locked = $updated === 1;
        }
        // phpcs:enable

        $this->lock_value = $locked ? $lock_value : null;

        /**
         * Action to note whether the background process managed to create its lock.
         *
         * The lock is used to signify that a process is running a task and no other
         * process should be allowed to run the same task until the lock is released.
         *
         * @param bool $locked Whether the lock was successfully created.
         * @param string $lock_value Value used for the lock (`<expires at>|<token>`).
         * @param int $lock_duration Max number of seconds that the lock will live for.
         * @param string $chain_id Current background process chain ID.
         */
        do_action(
            $this->identifier . '_process_locked',
            $locked,
            $lock_value,
            $lock_duration,
            $this->get_chain_id()
        );

        return $locked;
    }

    /**
     * Extend the lock this process holds.
     *
     * Only the existing lock row is updated, so a lock that expired and was taken over by
     * another process, or that was deleted (e.g. when clearing the process), is never taken
     * back or re-created. Once the lock is lost, the queue belongs to someone else.
     *
     * @return bool Whether this process still holds the lock.
     */
    private function refresh_lock(): bool {
        global $wpdb;

        if ( $this->lock_value === null ) {
            return false;
        }

        $lock_duration = ( property_exists( $this, 'queue_lock_time' ) )
            ? $this->queue_lock_time
            : 60; // 1 minute

        $lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );

        $token      = (string) strstr( $this->lock_value, '|' );
        $lock_value = ( time() + (int) $lock_duration ) . $token;
        $lock_table = $this->get_lock_table();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $lock_value === $this->lock_value ) {
            // Same expiry as before (same second), nothing to extend: just confirm the lock is still ours
            $current = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT option_value FROM {$lock_table} WHERE option_name = %s",
                    $this->get_lock_name()
                )
            );

            $held = $current === $this->lock_value;
        } else {
            $updated = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$lock_table} SET option_value = %s WHERE option_name = %s AND option_value = %s",
                    $lock_value,
                    $this->get_lock_name(),
                    $this->lock_value
                )
            );

            $held = $updated === 1;
        }
        // phpcs:enable

        $this->lock_value = $held ? $lock_value : null;

        return $held;
    }

    /**
     * Unlock process.
     *
     * Unlock the process so that other instances can spawn.
     *
     * @return WP_Background_Process
     */
    protected function unlock_process(): WP_Background_Process {
        global $wpdb;

        $unlocked = false;

        if ( $this->lock_value !== null ) {
            $lock_table = $this->get_lock_table();

            // Only release our own lock, never one another process took over after ours expired
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $unlocked = (bool) $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$lock_table} WHERE option_name = %s AND option_value = %s",
                    $this->get_lock_name(),
                    $this->lock_value
                )
            );
            // phpcs:enable

            $this->lock_value = null;
        }

        /**
         * Action to note whether the background process managed to release its lock.
         *
         * The lock is used to signify that a process is running a task and no other
         * process should be allowed to run the same task until the lock is released.
         *
         * @param bool $unlocked Whether the lock was released.
         * @param string $chain_id Current background process chain ID.
         */
        do_action( $this->identifier . '_process_unlocked', $unlocked, $this->get_chain_id() );

        return $this;
    }

    /**
     * Return batch.
     *
     * @return ?stdClass The first batch of queued items, or `null` if the queue is empty.
     */
    protected function get_batch(): ?stdClass {
        return array_reduce(
            $this->get_batches( 1 ),
            static function ( $carry, $batch ) {
                return $batch;
            },
            null
        );
    }

    /**
     * Return batches.
     *
     * @param int $limit Number of batches to return, defaults to all.
     *
     * @return stdClass[]
     */
    public function get_batches( int $limit = 0 ): array {
        global $wpdb;

        if ( empty( $limit ) || ! is_int( $limit ) ) {
            $limit = 0;
        }

        $table        = $wpdb->options;
        $column       = 'option_name';
        $key_column   = 'option_id';
        $value_column = 'option_value';

        if ( is_multisite() ) {
            $table        = $wpdb->sitemeta;
            $column       = 'meta_key';
            $key_column   = 'meta_id';
            $value_column = 'meta_value';
        }

        $key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

        $sql = '
			SELECT *
			FROM ' . $table . '
			WHERE ' . $column . ' LIKE %s
			ORDER BY ' . $key_column . ' ASC
			';

        $args = array( $key );

        if ( ! empty( $limit ) ) {
            $sql .= ' LIMIT %d';

            $args[] = $limit;
        }

        $items = $wpdb->get_results(
            $wpdb->prepare(
                $sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $args
            )
        );

        $batches = array();

        if ( ! empty( $items ) ) {
            $allowed_classes = $this->allowed_batch_data_classes;

            $batches = array_map(
                static function ( $item ) use ( $column, $value_column, $allowed_classes ) {
                    $batch       = new stdClass();
                    $batch->key  = $item->{$column};
                    $batch->data = static::maybe_unserialize( $item->{$value_column}, $allowed_classes );

                    return $batch;
                },
                $items
            );
        }

        return $batches;
    }

    /**
     * Handle a dispatched request.
     *
     * Pass each queue item to the task handler, while remaining
     * within server memory and time limit constraints.
     */
    protected function handle(): void {
        if ( ! $this->lock_process() ) {
            // Another request got the lock first and is processing the queue
            $this->maybe_wp_die();
            return;
        }

        /**
         * Number of seconds to sleep between batches. Defaults to 0 seconds, minimum 0.
         *
         * @param int $seconds
         */
        $throttle_seconds = max(
            0,
            apply_filters(
                $this->identifier . '_seconds_between_batches',
                apply_filters(
                    $this->prefix . '_seconds_between_batches',
                    0
                )
            )
        );

        do {
            $batch = $this->get_batch();

            if ( $batch === null ) {
                // The queue was emptied (e.g. cleared) after it was checked
                break;
            }

            foreach ( $batch->data as $key => $value ) {
                $task = $this->task( $value );

                /*
                 * The lock expires after a fixed time, so extend it after every item. If it
                 * cannot be extended, it expired during the task and another process took it
                 * over (or the process was cleared): that process owns the queue now, so
                 * persist nothing and stop.
                 */
                if ( ! $this->refresh_lock() ) {
                    $this->maybe_wp_die();
                    return;
                }

                if ( $task !== false ) {
                    $batch->data[ $key ] = $task;
                } else {
                    unset( $batch->data[ $key ] );
                }

                // Keep the batch up to date while processing it
                if ( ! empty( $batch->data ) ) {
                    $this->update( $batch->key, $batch->data );
                }

                // Let the server breathe a little
                sleep( $throttle_seconds );

                // Batch limits reached, or pause or cancel requested
                if ( ! $this->should_continue() ) {
                    break;
                }
            }

            // Delete current batch if fully processed
            if ( empty( $batch->data ) ) {
                $this->delete( $batch->key );
            }
        } while ( ! $this->is_queue_empty() && $this->should_continue() );

        if ( $this->is_cancelled() ) {
            /*
             * Cancel requested while processing: drop the queue while still holding the lock,
             * so whoever is waiting for it to be released (e.g. when clearing everything the
             * process produced) finds nothing left to process. Completing the process now
             * would publish the results of a run that was asked to stop.
             */
            $this->clear_scheduled_event();
            $this->delete_all();
            $this->unlock_process();

            $this->maybe_wp_die();
            return;
        }

        // Start next batch or complete process
        if ( ! $this->is_queue_empty() ) {
            $this->unlock_process();
            $this->dispatch();
        } elseif ( $this->refresh_lock() ) {
            /*
             * Complete while still holding the lock, so that releasing it means the process
             * is done writing (completing may publish what the run produced). If the lock
             * was lost meanwhile, the process that took it over completes instead.
             */
            $this->complete();
            $this->unlock_process();
        }

        $this->maybe_wp_die();
    }

    /**
     * Whether the memory limit has been exceeded.
     *
     * Ensures the batch process never exceeds 90%
     * of the maximum WordPress memory.
     *
     * @return bool
     */
    protected function memory_exceeded(): bool {
        $memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
        $current_memory = memory_get_usage( true );
        $return         = false;

        if ( $current_memory >= $memory_limit ) {
            $return = true;
        }

        return apply_filters( $this->identifier . '_memory_exceeded', $return );
    }

    /**
     * Get memory limit in bytes.
     *
     * @return int
     */
    protected function get_memory_limit(): int {
        if ( function_exists( 'ini_get' ) ) {
            $memory_limit = ini_get( 'memory_limit' );
        } else {
            // Sensible default
            $memory_limit = '128M';
        }

        if ( ! $memory_limit || - 1 === intval( $memory_limit ) ) {
            // Unlimited, set to 32 GB
            $memory_limit = '32000M';
        }

        return wp_convert_hr_to_bytes( $memory_limit );
    }

    /**
     * Whether the time limit has been exceeded.
     *
     * Ensures the batch never exceeds a sensible time limit.
     * A timeout limit of 30s is common on shared hosting.
     *
     * @return bool
     */
    protected function time_exceeded(): bool {
        // Filter the default time limit of 20 seconds
        $finish = $this->start_time + apply_filters( $this->identifier . '_default_time_limit', 20 );
        $return = false;

        if ( time() >= $finish ) {
            $return = true;
        }

        return apply_filters( $this->identifier . '_time_exceeded', $return );
    }

    /**
     * Complete processing.
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     *
     * @return void
     */
    protected function complete(): void {
        delete_site_option( $this->get_status_key() );

        // Remove the cron healthcheck job from the cron schedule
        $this->clear_scheduled_event();

        $this->completed();
    }

    /**
     * Called when background process has completed.
     *
     * @return void
     */
    protected function completed(): void {
        do_action( $this->identifier . '_completed', $this->get_chain_id() );
    }

    /**
     * Return the cron healthcheck interval in minutes.
     *
     * Default is 5 minutes, minimum is 1 minute.
     *
     * @return int
     */
    public function get_cron_interval(): int {
        $interval = 5;

        if ( property_exists( $this, 'cron_interval' ) ) {
            $interval = $this->cron_interval;
        }

        $interval = apply_filters( $this->cron_interval_identifier, $interval );

        return is_int( $interval ) && $interval > 0 ? $interval : 5;
    }

    /**
     * Schedule the cron healthcheck job.
     *
     * @access public
     *
     * @param mixed $schedules Schedules.
     *
     * @return mixed
     */
    public function schedule_cron_healthcheck( $schedules ) {
        $interval = $this->get_cron_interval();

        if ( $interval === 1 ) {
            $display = __( 'Every Minute', 'pressidium-performance' );
        } else {
            /* translators: %d refers to number of minutes. */
            $display = sprintf( __( 'Every %d Minutes', 'pressidium-performance' ), $interval );
        }

        // Adds an "Every NNN Minute(s)" schedule to the existing cron schedules
        $schedules[ $this->cron_interval_identifier ] = array(
            'interval' => MINUTE_IN_SECONDS * $interval,
            'display'  => $display,
        );

        return $schedules;
    }

    /**
     * Handle cron healthcheck event.
     *
     * Restart the background process if not already running
     * and data exists in the queue.
     *
     * If the loopback dispatch fails (e.g. the server cannot reach its own
     * admin-ajax.php URL), fall back to processing the queue directly
     * within the current cron request to prevent the queue from being
     * stuck indefinitely.
     *
     * @return void
     */
    public function handle_cron_healthcheck(): void {
        if ( $this->is_processing() ) {
            // Background process already running
            exit;
        }

        if ( $this->is_queue_empty() ) {
            // No data to process
            $this->clear_scheduled_event();
            exit;
        }

        $result = $this->dispatch();

        if ( is_wp_error( $result ) ) {
            $this->handle();
        }
    }

    /**
     * Schedule the cron healthcheck event.
     *
     * @return void
     */
    protected function schedule_event(): void {
        if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
            wp_schedule_event(
                time() + ( $this->get_cron_interval() * MINUTE_IN_SECONDS ),
                $this->cron_interval_identifier,
                $this->cron_hook_identifier
            );
        }
    }

    /**
     * Clear scheduled cron healthcheck event.
     *
     * @return void
     */
    protected function clear_scheduled_event(): void {
        $timestamp = wp_next_scheduled( $this->cron_hook_identifier );

        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, $this->cron_hook_identifier );
        }
    }

    /**
     * Cancel the background process.
     *
     * Stop processing queue items, clear cron job and delete batch.
     *
     * @deprecated 1.1.0 Superseded.
     * @see        cancel()
     *
     * @return void
     */
    public function cancel_process(): void {
        $this->cancel();
    }

    /**
     * Perform task with queued item.
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param mixed $item Queue item to iterate over.
     *
     * @return mixed
     */
    abstract protected function task( $item );

    /**
     * Maybe unserialize data, but not if an object.
     *
     * @param mixed         $data            Data to be unserialized.
     * @param bool|string[] $allowed_classes Array of class names that can be unserialized.
     *
     * @return mixed
     */
    protected static function maybe_unserialize( $data, bool|array $allowed_classes ) {
        if ( is_serialized( $data ) ) {
            $options = array();

            if ( is_bool( $allowed_classes ) || is_array( $allowed_classes ) ) {
                $options['allowed_classes'] = $allowed_classes;
            }

            return @unserialize( $data, $options ); // @phpcs:ignore
        }

        return $data;
    }

    /**
     * Whether the current background process should continue running the task.
     *
     * @return bool
     */
    public function should_continue(): bool {
        /**
         * Filter whether the current background process should continue running the task
         * if there is data to be processed.
         *
         * If the processing time or memory limits have been exceeded, the value will be false.
         * If pause or cancel have been requested, the value will be false.
         *
         * It is very unlikely that you would want to override a false value with true.
         *
         * If false is returned here, it does not necessarily mean background processing is
         * complete. If there is batch data still to be processed and pause or cancel have not
         * been requested, it simply means this background process should spawn a new process
         * for the chain to continue processing and then close itself down.
         *
         * @param bool $continue Should the current process continue processing the task?
         * @param string $chain_id The current background process chain's ID.
         *
         * @return bool
         */
        return apply_filters(
            $this->identifier . '_should_continue',
            ! ( $this->time_exceeded() || $this->memory_exceeded() || $this->is_paused() || $this->is_cancelled() ),
            $this->get_chain_id()
        );
    }

    /**
     * Return the string used to identify this type of background process.
     *
     * @return string
     */
    public function get_identifier(): string {
        return $this->identifier;
    }

    /**
     * Return the current background process chain's ID.
     *
     * If the chain's ID hasn't been set before this function is first used,
     * and hasn't been passed as a query arg during dispatch,
     * the chain ID will be generated before being returned.
     *
     * @return string
     */
    public function get_chain_id(): string {
        if ( empty( $this->chain_id )
            && wp_doing_ajax()
            && isset( $_REQUEST['action'] )
            && $_REQUEST['action'] === $this->identifier ) {
            check_ajax_referer( $this->identifier, 'nonce' );

            if ( ! empty( $_GET[ $this->get_chain_id_arg_name() ] ) ) {
                $chain_id = sanitize_key( $_GET[ $this->get_chain_id_arg_name() ] );

                if ( wp_is_uuid( $chain_id ) ) {
                    $this->chain_id = $chain_id;

                    return $this->chain_id;
                }
            }
        }

        if ( empty( $this->chain_id ) ) {
            $this->chain_id = wp_generate_uuid4();
        }

        return $this->chain_id;
    }

    /**
     * Filter the query arguments used during an async request.
     *
     * @param array<string, mixed> $args Current query args.
     *
     * @return array<string, mixed>
     */
    public function filter_dispatch_query_args( array $args ): array {
        $args[ $this->get_chain_id_arg_name() ] = $this->get_chain_id();

        return $args;
    }

    /**
     * Return the query arg name used for passing the chain ID to new processes.
     *
     * @return string
     */
    private function get_chain_id_arg_name(): string {
        static $chain_id_arg_name;

        if ( ! empty( $chain_id_arg_name ) ) {
            return $chain_id_arg_name;
        }

        /**
         * Filter the query arg name used for passing the chain ID to new processes.
         *
         * If you encounter problems with using the default query arg name, you can
         * change it with this filter.
         *
         * @param string $chain_id_arg_name Default "chain_id".
         *
         * @return string
         */
        $chain_id_arg_name = apply_filters(
            $this->identifier . '_chain_id_arg_name',
            self::CHAIN_ID_ARG_NAME
        );

        if ( ! is_string( $chain_id_arg_name ) || empty( $chain_id_arg_name ) ) {
            $chain_id_arg_name = self::CHAIN_ID_ARG_NAME;
        }

        return $chain_id_arg_name;
    }

}
