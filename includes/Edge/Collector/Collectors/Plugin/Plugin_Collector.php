<?php
/**
 * Plugin collector.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector\Collectors\Plugin;

use Pressidium\WP\Performance\Edge\Collector\Chunked_Collector;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Plugin_Collector class.
 *
 * Collects data related to the installed WordPress plugins.
 *
 * @since 1.2.0
 */
final class Plugin_Collector implements Chunked_Collector {

    /**
     * Maximum number of plugins processed in a single chunk.
     *
     * Tuned so each background task fits comfortably under PHP execution and
     * memory limits even on large installs.
     */
    private const CHUNK_SIZE = 20;

    /**
     * Return the collector name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'plugins';
    }

    /**
     * Return the chunks this collector will process.
     *
     * @return array<string, array{offset: int, limit: int}>
     */
    public function get_chunks(): array {
        $total = count( $this->get_all_plugins() );

        if ( $total === 0 ) {
            return array();
        }

        $chunks = array();

        for ( $offset = 0; $offset < $total; $offset += self::CHUNK_SIZE ) {
            $chunks[ sprintf( 'offset-%d', $offset ) ] = array(
                'offset' => $offset,
                'limit'  => self::CHUNK_SIZE,
            );
        }

        return $chunks;
    }

    /**
     * Collect plugin data for one chunk.
     *
     * @param array<string, mixed> $chunk Chunk descriptor with `offset` and `limit`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function collect_chunk( array $chunk ): array {
        $offset = isset( $chunk['offset'] ) ? (int) $chunk['offset'] : 0;
        $limit  = isset( $chunk['limit'] ) ? (int) $chunk['limit'] : self::CHUNK_SIZE;

        $plugins = array_slice( $this->get_all_plugins(), $offset, $limit, true );

        if ( empty( $plugins ) ) {
            return array();
        }

        $active_plugins  = $this->get_active_plugins();
        $network_plugins = $this->get_network_active_plugins();
        $auto_updates    = $this->get_auto_update_plugins();
        $update_info     = $this->get_update_info();
        $wp_org_slugs    = $this->get_wp_org_slugs();

        $collected = array();

        foreach ( $plugins as $plugin_file => $plugin_data ) {
            $plugin_info = new Plugin_Info(
                $plugin_file,
                $plugin_data,
                $active_plugins,
                $network_plugins,
                $auto_updates,
                $update_info,
                $wp_org_slugs
            );

            $collected[] = $plugin_info->to_array();
        }

        return $collected;
    }

    /**
     * Collect plugin data.
     *
     * Concatenates every chunk's output in case we want to get all plugin data in one go.
     *
     * For large installations this may exceed PHP execution and memory limits,
     * so it's recommended to use chunked collection instead.
     *
     * @return array<int, array<string, mixed>>
     */
    public function collect(): array {
        $collected = array();

        foreach ( $this->get_chunks() as $chunk ) {
            foreach ( $this->collect_chunk( $chunk ) as $entry ) {
                $collected[] = $entry;
            }
        }

        return $collected;
    }

    /**
     * Return all installed plugins.
     *
     * @return array<string, array<string, mixed>>
     */
    private function get_all_plugins(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return get_plugins();
    }

    /**
     * Return the list of active plugin files.
     *
     * @return string[]
     */
    private function get_active_plugins(): array {
        return (array) get_option( 'active_plugins', array() );
    }

    /**
     * Return the list of network-activated plugin files.
     *
     * @return string[]
     */
    private function get_network_active_plugins(): array {
        if ( ! is_multisite() ) {
            return array();
        }

        return array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
    }

    /**
     * Return the list of plugin files with auto-updates enabled.
     *
     * @return string[]
     */
    private function get_auto_update_plugins(): array {
        return (array) get_site_option( 'auto_update_plugins', array() );
    }

    /**
     * Return available update information keyed by plugin file.
     *
     * Each value is the new version string available.
     *
     * @return array<string, string>
     */
    private function get_update_info(): array {
        $update_data = get_site_transient( 'update_plugins' );

        if (
            ! is_object( $update_data )
            || ! isset( $update_data->response )
            || ! is_array( $update_data->response )
        ) {
            return array();
        }

        $updates = array();

        foreach ( $update_data->response as $plugin_file => $info ) {
            if ( is_object( $info ) && ! empty( $info->new_version ) ) {
                $updates[ $plugin_file ] = $info->new_version;
            }
        }

        return $updates;
    }

    /**
     * Return the slugs of all plugins known to the WordPress.org repository.
     *
     * Plugins that do not appear here are considered premium/third-party.
     *
     * @return string[]
     */
    private function get_wp_org_slugs(): array {
        $update_data = get_site_transient( 'update_plugins' );

        if ( ! is_object( $update_data ) ) {
            return array();
        }

        $slugs = array();

        // Plugins with available updates.
        if ( isset( $update_data->response ) && is_array( $update_data->response ) ) {
            foreach ( $update_data->response as $plugin_file => $info ) {
                $slugs[] = $this->derive_slug( $plugin_file );
            }
        }

        // Plugins that are up-to-date.
        if ( isset( $update_data->no_update ) && is_array( $update_data->no_update ) ) {
            foreach ( $update_data->no_update as $plugin_file => $info ) {
                $slugs[] = $this->derive_slug( $plugin_file );
            }
        }

        return array_unique( $slugs );
    }

    /**
     * Derive the plugin slug from its file path.
     *
     * Used only to build the list of WordPress.org slugs. Per-plugin
     * slug derivation is handled by {@see Plugin_Info}.
     *
     * @param string $plugin_file Plugin file path relative to the plugins directory.
     *
     * @return string
     */
    private function derive_slug( string $plugin_file ): string {
        if ( str_contains( $plugin_file, '/' ) ) {
            return dirname( $plugin_file );
        }

        return basename( $plugin_file, '.php' );
    }

}
