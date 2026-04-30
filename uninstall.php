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

    // Delete custom directory
    $custom_dir = $upload_dir . DIRECTORY_SEPARATOR . 'pressidium-performance';
    pressidium_performance_rm_dir( $custom_dir );

    // Delete the custom tables
    global $wpdb;

    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pressidium_performance_optimizations" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pressidium_performance_concatenations" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pressidium_performance_concatenations_pages" );


    // Unschedule cron jobs
    $timestamp = wp_next_scheduled( 'pressidium_performance_clean_up_cron_job' );
    wp_unschedule_event( $timestamp, 'pressidium_performance_clean_up_cron_job' );
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
