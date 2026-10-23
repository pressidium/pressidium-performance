<?php
/**
 * Plugin info.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector\Collectors\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Plugin_Info class.
 *
 * Represents a single installed WordPress plugin and its metadata.
 *
 * @since 1.2.0
 */
final class Plugin_Info {

    /**
     * Plugin_Info constructor.
     *
     * @param string                $plugin_file      Plugin file path relative to the plugins directory.
     * @param array<string, mixed>  $header_data      Plugin header data as returned by `get_plugins()`.
     * @param string[]              $active_plugins   List of active plugin files.
     * @param string[]              $network_plugins  List of network-activated plugin files.
     * @param string[]              $auto_updates     List of plugin files with auto-updates enabled.
     * @param array<string, string> $available_updates Available updates keyed by plugin file, values are new versions.
     * @param string[]              $wp_org_slugs     Slugs of plugins known to the WordPress.org repository.
     */
    public function __construct(
        private readonly string $plugin_file,
        private readonly array $header_data,
        private readonly array $active_plugins,
        private readonly array $network_plugins,
        private readonly array $auto_updates,
        private readonly array $available_updates,
        private readonly array $wp_org_slugs
    ) {}

    /**
     * Return the plugin data as an associative array.
     *
     * @return array{
     *     name:              string,
     *     slug:              string,
     *     version:           string,
     *     author:            string,
     *     plugin_uri:        string,
     *     active:            bool,
     *     network_activated: bool,
     *     auto_updates:      bool,
     *     update_available:  bool,
     *     update_version:    string,
     *     is_in_wp_org:      bool,
     * }
     */
    public function to_array(): array {
        return array(
            'name'              => $this->get_name(),
            'slug'              => $this->get_slug(),
            'version'           => $this->get_version(),
            'author'            => $this->get_author(),
            'plugin_uri'        => $this->get_plugin_uri(),
            'active'            => $this->is_active(),
            'network_activated' => $this->is_network_active(),
            'auto_updates'      => $this->has_auto_updates(),
            'update_available'  => $this->has_update(),
            'update_version'    => $this->get_update_version(),
            'is_in_wp_org'      => $this->is_in_wp_org(),
        );
    }

    /**
     * Return the plugin name.
     *
     * @return string
     */
    private function get_name(): string {
        return $this->header_data['Name'] ?? '';
    }

    /**
     * Derive the plugin slug from its file path.
     *
     * For plugins in a directory (e.g. `akismet/akismet.php`), the slug is
     * the directory name. For single-file plugins (e.g. `hello.php`), the
     * slug is the filename without its extension.
     *
     * @return string
     */
    private function get_slug(): string {
        if ( str_contains( $this->plugin_file, '/' ) ) {
            return dirname( $this->plugin_file );
        }

        return basename( $this->plugin_file, '.php' );
    }

    /**
     * Return the plugin version.
     *
     * @return string
     */
    private function get_version(): string {
        return $this->header_data['Version'] ?? '';
    }

    /**
     * Return the plugin author name.
     *
     * The value is stripped of any HTML tags that WordPress may
     * include in the `Author` header.
     *
     * @return string
     */
    private function get_author(): string {
        return wp_strip_all_tags( $this->header_data['Author'] ?? '' );
    }

    /**
     * Return the plugin URI.
     *
     * @return string
     */
    private function get_plugin_uri(): string {
        return $this->header_data['PluginURI'] ?? '';
    }

    /**
     * Return whether the plugin is active.
     *
     * A plugin is considered active if it is activated on the
     * current site or network-activated on a multisite.
     *
     * @return bool
     */
    private function is_active(): bool {
        return $this->is_site_active() || $this->is_network_active();
    }

    /**
     * Return whether the plugin is activated on the current site.
     *
     * @return bool
     */
    private function is_site_active(): bool {
        return in_array( $this->plugin_file, $this->active_plugins, true );
    }

    /**
     * Return whether the plugin is network-activated.
     *
     * @return bool
     */
    private function is_network_active(): bool {
        return in_array( $this->plugin_file, $this->network_plugins, true );
    }

    /**
     * Return whether auto-updates are enabled for this plugin.
     *
     * @return bool
     */
    private function has_auto_updates(): bool {
        return in_array( $this->plugin_file, $this->auto_updates, true );
    }

    /**
     * Return whether an update is available for this plugin.
     *
     * @return bool
     */
    private function has_update(): bool {
        return isset( $this->available_updates[ $this->plugin_file ] );
    }

    /**
     * Return the available update version, or an empty string if none.
     *
     * @return string
     */
    private function get_update_version(): string {
        return $this->available_updates[ $this->plugin_file ] ?? '';
    }

    /**
     * Return whether this is plugin seems to be known to the WordPress.org plugin repository.
     *
     * @return bool
     */
    private function is_in_wp_org(): bool {
        return in_array( $this->get_slug(), $this->wp_org_slugs, true );
    }

}
