<?php
/**
 * Theme info.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector\Collectors\Theme;

use WP_Theme;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Theme_Info class.
 *
 * Represents a single installed WordPress theme and its metadata.
 *
 * @since 1.2.0
 */
final class Theme_Info {

    /**
     * Theme_Info constructor.
     *
     * @param WP_Theme              $theme             Theme object.
     * @param string[]              $active_slugs      Slugs of themes active on at least one site.
     * @param array<string, int[]>  $active_sites_map  Map of theme slug to blog IDs where it is active.
     * @param string[]              $auto_updates      List of theme slugs with auto-updates enabled.
     * @param array<string, string> $available_updates Available updates keyed by theme slug, values are new versions.
     */
    public function __construct(
        private readonly WP_Theme $theme,
        private readonly array $active_slugs,
        private readonly array $active_sites_map,
        private readonly array $auto_updates,
        private readonly array $available_updates
    ) {}

    /**
     * Return the theme data as an associative array.
     *
     * @return array{
     *     name:             string,
     *     slug:             string,
     *     version:          string,
     *     author:           string,
     *     theme_uri:        string,
     *     active:           bool,
     *     active_on:        int[],
     *     is_child_theme:   bool,
     *     parent_slug:      ?string,
     *     auto_updates:     bool,
     *     update_available: bool,
     *     update_version:   string,
     * }
     */
    public function to_array(): array {
        return array(
            'name'             => $this->get_name(),
            'slug'             => $this->get_slug(),
            'version'          => $this->get_version(),
            'author'           => $this->get_author(),
            'theme_uri'        => $this->get_theme_uri(),
            'active'           => $this->is_active(),
            'active_on'        => $this->get_active_sites(),
            'is_child_theme'   => $this->is_child_theme(),
            'parent_slug'      => $this->get_parent_slug(),
            'auto_updates'     => $this->has_auto_updates(),
            'update_available' => $this->has_update(),
            'update_version'   => $this->get_update_version(),
        );
    }

    /**
     * Return the theme name.
     *
     * @return string
     */
    private function get_name(): string {
        $name = $this->theme->get( 'Name' );

        if ( empty( $name ) ) {
            return '';
        }

        return $name;
    }

    /**
     * Return the theme slug (stylesheet directory name).
     *
     * @return string
     */
    private function get_slug(): string {
        return $this->theme->get_stylesheet();
    }

    /**
     * Return the theme version.
     *
     * @return string
     */
    private function get_version(): string {
        $version = $this->theme->get( 'Version' );

        if ( empty( $version ) ) {
            return '';
        }

        return $version;
    }

    /**
     * Return the theme author name.
     *
     * The value is stripped of any HTML tags that WordPress may
     * include in the `Author` header.
     *
     * @return string
     */
    private function get_author(): string {
        $author = $this->theme->get( 'Author' );

        if ( empty( $author ) ) {
            return '';
        }

        return wp_strip_all_tags( $author );
    }

    /**
     * Return the theme URI.
     *
     * @return string
     */
    private function get_theme_uri(): string {
        $theme_uri = $this->theme->get( 'ThemeURI' );

        if ( empty( $theme_uri ) ) {
            return '';
        }

        return $theme_uri;
    }

    /**
     * Return whether the theme is active on at least one site.
     *
     * @return bool
     */
    private function is_active(): bool {
        return in_array( $this->get_slug(), $this->active_slugs, true );
    }

    /**
     * Return the blog IDs this theme is active on.
     *
     * Returns an empty array if the theme is not active anywhere.
     *
     * @return int[]
     */
    private function get_active_sites(): array {
        return $this->active_sites_map[ $this->get_slug() ] ?? array();
    }

    /**
     * Return whether this is a child theme.
     *
     * @return bool
     */
    private function is_child_theme(): bool {
        return $this->theme->parent() !== false;
    }

    /**
     * Return the parent theme slug, or `null` if not a child theme.
     *
     * @return ?string
     */
    private function get_parent_slug(): ?string {
        $parent = $this->theme->parent();

        if ( $parent === false ) {
            return null;
        }

        return $parent->get_stylesheet();
    }

    /**
     * Return whether auto-updates are enabled for this theme.
     *
     * @return bool
     */
    private function has_auto_updates(): bool {
        return in_array( $this->get_slug(), $this->auto_updates, true );
    }

    /**
     * Return whether an update is available for this theme.
     *
     * @return bool
     */
    private function has_update(): bool {
        return isset( $this->available_updates[ $this->get_slug() ] );
    }

    /**
     * Return the available update version, or an empty string if none.
     *
     * @return string
     */
    private function get_update_version(): string {
        return $this->available_updates[ $this->get_slug() ] ?? '';
    }

}
