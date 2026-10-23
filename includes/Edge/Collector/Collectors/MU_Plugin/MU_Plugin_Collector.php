<?php
/**
 * MU-Plugin collector.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector\Collectors\MU_Plugin;

use Pressidium\WP\Performance\Edge\Collector\Collector;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * MU_Plugin_Collector class.
 *
 * Collects data related to the installed WordPress must-use plugins.
 *
 * Must-use plugins reside in the `mu-plugins` directory and are
 * automatically loaded by WordPress on every request. Unlike regular
 * plugins, they cannot be deactivated, do not support auto-updates,
 * and are not subject to network-activation on multisite.
 *
 * @since 1.2.0
 */
final class MU_Plugin_Collector implements Collector {

    /**
     * Return the collector name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'mu_plugins';
    }

    /**
     * Collect must-use plugin data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function collect(): array {
        $mu_plugins = $this->get_all_mu_plugins();

        $collected = array();

        foreach ( $mu_plugins as $plugin_file => $plugin_data ) {
            $mu_plugin_info = new MU_Plugin_Info( $plugin_file, $plugin_data );

            $collected[] = $mu_plugin_info->to_array();
        }

        return $collected;
    }

    /**
     * Return all installed must-use plugins.
     *
     * @return array<string, array<string, mixed>>
     */
    private function get_all_mu_plugins(): array {
        if ( ! function_exists( 'get_mu_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return get_mu_plugins();
    }

}
