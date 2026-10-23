<?php
/**
 * MU-Plugin info.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector\Collectors\MU_Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * MU_Plugin_Info class.
 *
 * Represents a single installed WordPress must-use plugin and its metadata.
 *
 * Must-use plugins are always active, cannot be deactivated through the
 * admin interface, do not support auto-updates, and are not distributed
 * through the WordPress.org plugin repository.
 *
 * @since 1.2.0
 */
final class MU_Plugin_Info {

    /**
     * MU_Plugin_Info constructor.
     *
     * @param string               $plugin_file Plugin file path relative to the mu-plugins directory.
     * @param array<string, mixed> $header_data Plugin header data as returned by `get_mu_plugins()`.
     */
    public function __construct(
        private readonly string $plugin_file,
        private readonly array $header_data
    ) {}

    /**
     * Return the mu-plugin data as an associative array.
     *
     * @return array{
     *     name:    string,
     *     slug:    string,
     *     version: string,
     *     author:  string,
     * }
     */
    public function to_array(): array {
        return array(
            'name'    => $this->get_name(),
            'slug'    => $this->get_slug(),
            'version' => $this->get_version(),
            'author'  => $this->get_author(),
        );
    }

    /**
     * Return the mu-plugin name.
     *
     * @return string
     */
    private function get_name(): string {
        return $this->header_data['Name'] ?? '';
    }

    /**
     * Derive the mu-plugin slug from its file path.
     *
     * Must-use plugins are typically single files placed directly in the
     * `mu-plugins` directory (e.g. `my-mu-plugin.php`), so the slug is
     * the filename without its extension. If a directory separator is
     * present, the directory name is used instead.
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
     * Return the mu-plugin version.
     *
     * @return string
     */
    private function get_version(): string {
        return $this->header_data['Version'] ?? '';
    }

    /**
     * Return the mu-plugin author name.
     *
     * The value is stripped of any HTML tags that WordPress may
     * include in the `Author` header.
     *
     * @return string
     */
    private function get_author(): string {
        return wp_strip_all_tags( $this->header_data['Author'] ?? '' );
    }

}

