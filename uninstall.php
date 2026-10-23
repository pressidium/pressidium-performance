<?php
/**
 * Uninstall plugin.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Recursively remove the directory at the given path and all its contents.
 *
 * Removes all files and subdirectories in the given
 * directory and then removes the directory itself.
 *
 * @param string $directory Path to the directory to remove.
 *
 * @return void
 */
function pressidium_performance_rm_dir( string $directory ): void {
    global $wp_filesystem;

    if ( ! is_dir( $directory ) ) {
        return;
    }

    $files = array_diff( scandir( $directory ), array( '.', '..' ) );

    foreach ( $files as $file ) {
        $file_path = $directory . DIRECTORY_SEPARATOR . $file;

        if ( is_dir( $file_path ) ) {
            pressidium_performance_rm_dir( $file_path );
            continue;
        }

        wp_delete_file( $file_path );
    }

    $wp_filesystem->delete( $directory );
}

/**
 * Delete every option whose name matches the given `LIKE` pattern.
 *
 * @param string $pattern `LIKE` pattern to match option names against,
 *                        with the wildcards already in place.
 *
 * @return void
 */
function pressidium_performance_delete_options_like( string $pattern ): void {
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $option_names = (array) $wpdb->get_col(
        $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern
        )
    );

    foreach ( $option_names as $option_name ) {
        delete_option( $option_name );
    }
}

/**
 * Delete all transients whose name starts with the given prefix.
 *
 * This is only useful for sites that do not use a persistent object cache (Redis),
 * as the transients are stored in the options table and can be deleted directly.
 *
 * @param string $prefix Prefix to match transient names against.
 *
 * @return void
 */
function pressidium_performance_delete_transients_with_prefix( string $prefix ): void {
    global $wpdb;

    $value_prefix   = '_transient_';
    $timeout_prefix = '_transient_timeout_';

    $value_like   = $wpdb->esc_like( $value_prefix . $prefix ) . '%';
    $timeout_like = $wpdb->esc_like( $timeout_prefix . $prefix ) . '%';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $option_names = (array) $wpdb->get_col(
        $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $value_like,
            $timeout_like
        )
    );

    $transient_names = array();

    foreach ( $option_names as $option_name ) {
        $option_prefix = str_starts_with( $option_name, $timeout_prefix ) ? $timeout_prefix : $value_prefix;

        $transient_names[] = substr( $option_name, strlen( $option_prefix ) );
    }

    foreach ( array_unique( $transient_names ) as $transient_name ) {
        delete_transient( $transient_name );
    }
}

/**
 * Clean up before uninstalling the plugin.
 *
 * @return void
 */
function pressidium_performance_uninstall(): void {
    // Set the upload directory path
    $upload_dir = wp_upload_dir()['basedir'];

    // Clean up before uninstalling this plugin
    delete_option( 'pressidium_performance_settings' );
    delete_option( 'pressidium_performance_table_versions' );
    delete_option( 'pressidium_performance_activated' );
    delete_option( 'pressidium_performance_minification_skip_cache_generation_counter' );
    delete_option( 'pressidium_edge_api_client_id' );
    delete_option( 'pressidium_edge_api_client_secret' );
    delete_option( 'pressidium_edge_api_bearer_token' );
    delete_option( 'pressidium_edge_web_property_handle' );

    /*
     * Concatenation processes store the bundles they are building and the post IDs under
     * an option keyed by their chain ID, and only delete them once the queue runs
     * complete. A queue that was canceled may leave them behind.
     */
    global $wpdb;

    $chain_id_like = $wpdb->esc_like( 'concatenate_process_' ) . '%';

    pressidium_performance_delete_options_like( $chain_id_like . $wpdb->esc_like( '_bundles' ) );
    pressidium_performance_delete_options_like( $chain_id_like . $wpdb->esc_like( '_post_id' ) );
    pressidium_performance_delete_options_like( $chain_id_like . $wpdb->esc_like( '_builds' ) );

    // Legacy option (older versions of the plugin kept a single aggregated hash per type)
    pressidium_performance_delete_options_like( $chain_id_like . $wpdb->esc_like( '_hash' ) );

    // Delete custom directory
    $custom_dir = $upload_dir . DIRECTORY_SEPARATOR . 'pressidium-performance';
    pressidium_performance_rm_dir( $custom_dir );

    // Delete the custom tables
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pressidium_performance_optimizations" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pressidium_performance_concatenations" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pressidium_performance_concatenations_files" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pressidium_performance_concatenations_pages" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pressidium_performance_collector_state" );


    // Cancel any background process still running and unschedule cron jobs
    require_once plugin_dir_path( __FILE__ ) . 'includes/cleanup.php';

    pressidium_performance_clear_background_processes();
    pressidium_performance_clear_cron_jobs();

    // Clear transients
    delete_transient( 'pressidium_collector_last_run' );
    delete_transient( 'pressidium_has_page_cache' );
    delete_transient( 'pressidium_performance_minifications_size_saved' );
    delete_transient( 'pressidium_performance_minified_files_count' );
    delete_transient( 'pressidium_performance_concatenations_size_saved' );
    delete_transient( 'pressidium_performance_concatenated_files_count' );
    delete_transient( 'pressidium_performance_images_size_saved' );

    // Legacy transient (older versions of the plugin were missing the `performance` in the prefix)
    delete_transient( 'pressidium_concatenated_files_count' );

    pressidium_performance_delete_transients_with_prefix( 'pp_skip_minify_' );
}

/**
 * Clean up before uninstalling the plugin from all sites in a multisite network.
 *
 * @return void
 */
function pressidium_performance_uninstall_multisite(): void {
    if ( is_multisite() ) {
        $blogs = get_sites();

        if ( ! empty( $blogs ) ) {
            // Multisite - iterate over all sites
            foreach ( $blogs as $blog ) {
                switch_to_blog( $blog->blog_id );
                pressidium_performance_uninstall();
                restore_current_blog();
            }

            return;
        }
    }

    // Single site
    pressidium_performance_uninstall();
}

pressidium_performance_uninstall_multisite();
