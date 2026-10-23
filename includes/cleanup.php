<?php
/**
 * Cleanup routines.
 *
 * This should not use our dependency injection container nor Composer autoload
 * so it could be loaded both when the plugin is deactivated and uninstalled.
 *
 * That's why we also use simple global functions to perform the cleanup
 * instead of classes and methods.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Return the action of every background process the plugin registers.
 *
 * @return string[]
 */
function pressidium_performance_background_process_actions(): array {
    return array(
        'collect_edge_data',
        'concatenate_css_process',
        'concatenate_js_process',
        'minify_css_process',
        'minify_js_process',
        'optimize_images',
    );
}

/**
 * Return the identifier of every cron job the plugin schedules.
 *
 * @return string[]
 */
function pressidium_performance_cron_job_identifiers(): array {
    return array(
        'pressidium_performance_clean_up_cron_job',
        'pressidium_performance_collector_cron_job',
        'pressidium_performance_collector_pending_run',
    );
}

/**
 * Return the table that holds the background process locks.
 *
 * Locks are options of the main site, see `WP_Background_Process::get_lock_table()`.
 *
 * @return string
 */
function pressidium_performance_background_process_lock_table(): string {
    global $wpdb;

    return is_multisite() ? $wpdb->get_blog_prefix( get_main_site_id() ) . 'options' : $wpdb->options;
}

/**
 * Whether the background process with the given identifier currently holds its lock.
 *
 * The lock is an `<expires at>|<token>` option, see `WP_Background_Process::lock_process()`.
 *
 * @param string $identifier Identifier of the background process.
 *
 * @return bool
 */
function pressidium_performance_is_background_process_locked( string $identifier ): bool {
    global $wpdb;

    $lock_table = pressidium_performance_background_process_lock_table();

    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $lock_value = $wpdb->get_var(
        $wpdb->prepare( "SELECT option_value FROM {$lock_table} WHERE option_name = %s", $identifier . '_process_lock' )
    );
    // phpcs:enable

    return is_string( $lock_value ) && (int) strtok( $lock_value, '|' ) > time();
}

/**
 * Ask the given background processes to stop, and wait for the ones that are running to do so.
 *
 * @param string[] $actions            Actions of the background processes to stop.
 * @param int      $timeout_in_seconds Maximum number of seconds to wait for them to stop.
 *
 * @return bool Whether every process stopped within the timeout.
 */
function pressidium_performance_stop_background_processes( array $actions, int $timeout_in_seconds ): bool {
    $identifiers = array();

    foreach ( $actions as $action ) {
        $identifier = 'pressidium_performance_' . $action;

        /*
         * Running processes check their status between items, and a request that is about
         * to start (dispatched, but not locked yet) checks it before taking the lock, so
         * cancel every process, not just the locked ones (1 is `STATUS_CANCELLED`).
         */
        update_site_option( $identifier . '_status', 1 );

        if ( pressidium_performance_is_background_process_locked( $identifier ) ) {
            $identifiers[] = $identifier;
        }
    }

    $deadline = microtime( true ) + $timeout_in_seconds;

    while ( ! empty( $identifiers ) ) {
        $identifiers = array_values(
            array_filter( $identifiers, __NAMESPACE__ . '\pressidium_performance_is_background_process_locked' )
        );

        if ( empty( $identifiers ) ) {
            break;
        }

        if ( microtime( true ) >= $deadline ) {
            return false;
        }

        usleep( 250000 );
    }

    return true;
}

/**
 * Cancel every background process and remove all the state it leaves behind.
 *
 * A queue outlives the request that created it, so a process that was mid-run
 * when the plugin was deactivated, reinstalled, or upgraded would otherwise keep
 * its batches in the options table forever. `is_active()` keeps reporting `true`
 * for such a queue, which blocks every subsequent run with no way to recover
 * from the UI.
 *
 * @param ?string[] $actions (Optional) Actions of the background processes to clear.
 *                           Defaults to every background process the plugin registers.
 *
 * @return void
 */
function pressidium_performance_clear_background_processes( ?array $actions = null ): void {
    global $wpdb;

    $actions = $actions ?? pressidium_performance_background_process_actions();

    foreach ( $actions as $action ) {
        $identifier = 'pressidium_performance_' . $action;
        $like       = $wpdb->esc_like( $identifier . '_batch_' ) . '%';

        /*
         * Batch keys end in a hash, so we have to look them up before we can
         * delete them. Batches are stored as site options, which live in
         * `sitemeta` on multisite and in `options` everywhere else.
         */
        if ( is_multisite() ) {
            $keys = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s AND site_id = %d",
                    $like,
                    get_current_network_id()
                )
            );
        } else {
            $keys = $wpdb->get_col(
                $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
            );
        }

        $keys = (array) $keys;

        foreach ( $keys as $key ) {
            // Delete through the API so the options cache is invalidated too
            delete_site_option( $key );
        }

        delete_site_option( $identifier . '_status' );

        $lock_table = pressidium_performance_background_process_lock_table();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$lock_table} WHERE option_name = %s", $identifier . '_process_lock' )
        );
        // phpcs:enable

        // Written by previous versions, which kept the lock in a site transient
        delete_site_transient( $identifier . '_process_lock' );

        // Unschedule the health check that would otherwise keep re-dispatching
        wp_clear_scheduled_hook( $identifier . '_cron' );
    }
}

/**
 * Unschedule every cron job the plugin schedules.
 *
 * @return void
 */
function pressidium_performance_clear_cron_jobs(): void {
    foreach ( pressidium_performance_cron_job_identifiers() as $identifier ) {
        wp_clear_scheduled_hook( $identifier );
    }
}
