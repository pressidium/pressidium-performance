<?php
/**
 * Plugin Name: Pressidium Performance
 * Plugin URI: https://pressidium.com/open-source/performance-plugin/
 * Description: Speed up your WordPress site, improve Core Web Vitals and enhance user experience with one-click image optimization, CSS & JavaScript minification.
 * Version: 1.0.1
 * Author: Pressidium®
 * Author URI: https://pressidium.com/
 * Text Domain: pressidium-performance
 * Domain Path: /languages
 * License: GPLv2
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Setup plugin constants.
 *
 * @return void
 */
function setup_constants(): void {
    if ( ! defined( 'Pressidium\WP\Performance\VERSION' ) ) {
        define( 'Pressidium\WP\Performance\VERSION', '1.0.1' );
    }

    if ( ! defined( 'Pressidium\WP\Performance\PLUGIN_DIR' ) ) {
        define( 'Pressidium\WP\Performance\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
    }

    if ( ! defined( 'Pressidium\WP\Performance\PLUGIN_URL' ) ) {
        define( 'Pressidium\WP\Performance\PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    }

    if ( ! defined( 'Pressidium\WP\Performance\PLUGIN_FILE' ) ) {
        define( 'Pressidium\WP\Performance\PLUGIN_FILE', __FILE__ );
    }

    if ( ! defined( 'Pressidium\WP\Performance\MINIMUM_WP_VERSION' ) ) {
        define( 'Pressidium\WP\Performance\MINIMUM_WP_VERSION', '6.9' );
    }

    if ( ! defined( 'Pressidium\WP\Performance\MINIMUM_PHP_VERSION' ) ) {
        define( 'Pressidium\WP\Performance\MINIMUM_PHP_VERSION', '8.1' );
    }
}

/**
 * Whether the plugin was just activated.
 *
 * @return bool
 */
function is_activated(): bool {
    $just_activated = is_admin() && get_option( 'pressidium_performance_activated' );

    if ( $just_activated ) {
        delete_option( 'pressidium_performance_activated' );

        return true;
    }

    return false;
}

/**
 * Display an admin notice if the plugin does not meet the minimum WordPress version.
 *
 * @return void
 */
function admin_notice_minimum_wp_version(): void {
    $message = sprintf(
        /* translators: 1: Plugin name, 2: WordPress version */
        esc_html__( '%1$s requires WordPress version %2$s or greater.', 'pressidium-performance' ),
        '<strong>' . esc_html__( 'Pressidium Performance', 'pressidium-performance' ) . '</strong>',
        '<strong>' . esc_html( __NAMESPACE__ . '\MINIMUM_WP_VERSION' ) . '</strong>'
    );

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', $message );
}

/**
 * Display an admin notice if the plugin does not meet the minimum PHP version.
 *
 * @return void
 */
function admin_notice_minimum_php_version(): void {
    $message = sprintf(
        /* translators: 1: Plugin name, 2: PHP version */
        esc_html__( '%1$s requires PHP version %2$s or greater.', 'pressidium-performance' ),
        '<strong>' . esc_html__( 'Pressidium Performance', 'pressidium-performance' ) . '</strong>',
        '<strong>' . esc_html( MINIMUM_PHP_VERSION ) . '</strong>'
    );

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', $message );
}

/**
 * Whether the plugin meets the minimum PHP version requirements.
 *
 * @return bool
 */
function meets_wp_version_requirements(): bool {
    $wp_version = get_bloginfo( 'version' );

    return version_compare( $wp_version, __NAMESPACE__ . '\MINIMUM_WP_VERSION', '>=' );
}

/**
 * Whether the plugin meets the minimum PHP version requirements.
 *
 * @return bool
 */
function meets_php_version_requirements(): bool {
    return version_compare( PHP_VERSION, MINIMUM_PHP_VERSION, '>=' );
}

/**
 * Check if the plugin is compatible with the current environment.
 *
 * @return void
 */
function meets_version_requirements(): bool {
    $requirements_met = true;

    // Check if it meets the minimum WordPress version
    if ( ! meets_wp_version_requirements() ) {
        add_action( 'admin_notices', __NAMESPACE__ . '\admin_notice_minimum_wp_version' );
        $requirements_met = false;
    }

    // Check if it meets the minimum PHP version
    if ( ! meets_php_version_requirements() ) {
        add_action( 'admin_notices', __NAMESPACE__ . '\admin_notice_minimum_php_version' );
        $requirements_met = false;
    }

    return $requirements_met;
}

/**
 * Check if the plugin is compatible with the current WordPress version and if not, prevent activation.
 *
 * We have to do this because the plugin uses new features of the HTML API introduced in WordPress 6.7.
 *
 * `WP_HTML_Tag_Processor::set_modifiable_text()` introduces in WordPress 6.7,
 * which is used in our plugin to modify HTML tags when concatenating JS files.
 *
 * @link https://make.wordpress.org/core/2023/03/07/introducing-the-html-api-in-wordpress-6-2/
 * @link https://make.wordpress.org/core/2024/03/04/updates-to-the-html-api-in-6-5/
 * @link https://make.wordpress.org/core/2024/06/24/updates-to-the-html-api-in-6-6/
 * @link https://make.wordpress.org/core/2024/10/17/updates-to-the-html-api-in-6-7/
 * @link https://make.wordpress.org/core/2023/08/19/progress-report-html-api/
 * @link https://core.trac.wordpress.org/ticket/57575
 *
 * @return void
 */
function activate_plugin(): void {
    if ( ! meets_wp_version_requirements() ) {
        $message = sprintf(
            '%s %s',
            sprintf(
                /* translators: %s: WordPress version. */
                esc_html__(
                    'Could not be activated. This plugin requires WordPress %s or higher.',
                    'pressidium-performance'
                ),
                __NAMESPACE__ . '\MINIMUM_WP_VERSION'
            ),
            sprintf(
                '<a href="%s">%s</a>.',
                esc_url( self_admin_url( 'update-core.php' ) ),
                esc_html__( 'Please update WordPress', 'pressidium-performance' )
            )
        );

        wp_die( wp_kses( $message, array( 'a' => array( 'href' => array() ) ) ) );
    }

    add_option( 'pressidium_performance_activated', true );
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function init_plugin(): void {
    // Composer autoload
    require_once __DIR__ . '/vendor/autoload.php';

    // Setup plugin constants
    setup_constants();

    if ( ! meets_version_requirements() ) {
        // Minimum requirements not met, bail early
        return;
    }

    // Initialize the plugin
    $plugin = new Plugin();

    if ( is_activated() ) {
        // Mark the plugin as activated
        $plugin->mark_as_activated();
    }

    // Initialize the plugin
    $plugin->init();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\activate_plugin' );
add_action( 'plugins_loaded', __NAMESPACE__ . '\init_plugin' );
