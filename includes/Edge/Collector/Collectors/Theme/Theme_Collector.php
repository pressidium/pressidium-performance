<?php
/**
 * Theme collector.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector\Collectors\Theme;

use Pressidium\WP\Performance\Edge\Collector\Chunked_Collector;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Theme_Collector class.
 *
 * Collects data related to the installed WordPress themes.
 *
 * @since 1.2.0
 */
final class Theme_Collector implements Chunked_Collector {

    /**
     * Maximum number of themes processed in a single chunk.
     */
    private const CHUNK_SIZE = 10;

    /**
     * Return the collector name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'themes';
    }

    /**
     * Return the chunks this collector will process.
     *
     * @return array<string, array{offset: int, limit: int}>
     */
    public function get_chunks(): array {
        $total = count( $this->get_all_themes() );

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
     * Collect theme data for one chunk.
     *
     * @param array<string, mixed> $chunk Chunk descriptor with `offset` and `limit`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function collect_chunk( array $chunk ): array {
        $offset = isset( $chunk['offset'] ) ? (int) $chunk['offset'] : 0;
        $limit  = isset( $chunk['limit'] ) ? (int) $chunk['limit'] : self::CHUNK_SIZE;

        $themes = array_slice( $this->get_all_themes(), $offset, $limit, true );

        if ( empty( $themes ) ) {
            return array();
        }

        $active_slugs     = $this->get_active_theme_slugs();
        $active_sites_map = $this->get_active_sites_map();
        $auto_updates     = $this->get_auto_update_themes();
        $update_info      = $this->get_update_info();

        $collected = array();

        foreach ( $themes as $theme ) {
            $theme_info = new Theme_Info(
                $theme,
                $active_slugs,
                $active_sites_map,
                $auto_updates,
                $update_info
            );

            $collected[] = $theme_info->to_array();
        }

        return $collected;
    }

    /**
     * Collect theme data.
     *
     * Concatenates every chunk's output in case we want to get all theme data in one go.
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
     * Return all installed themes.
     *
     * @return \WP_Theme[]
     */
    private function get_all_themes(): array {
        return wp_get_themes();
    }

    /**
     * Return the slugs of all active themes across every site.
     *
     * On single-site installations, this returns the current active theme slug.
     * On multisites, this returns the active theme slug for every site.
     *
     * @return string[]
     */
    private function get_active_theme_slugs(): array {
        if ( ! is_multisite() ) {
            return array( get_stylesheet() );
        }

        $slugs = array();

        foreach ( get_sites() as $site ) {
            $slug = get_blog_option( $site->blog_id, 'stylesheet' );

            if ( is_string( $slug ) && $slug !== '' ) {
                $slugs[] = $slug;
            }
        }

        return array_unique( $slugs );
    }

    /**
     * Return a map of theme slug to blog IDs where the theme is active.
     *
     * On single-site installations, the current theme is mapped to blog ID `1`.
     *
     * @return array<string, int[]>
     */
    private function get_active_sites_map(): array {
        if ( ! is_multisite() ) {
            return array( get_stylesheet() => array( get_current_blog_id() ) );
        }

        $map = array();

        foreach ( get_sites() as $site ) {
            $slug    = get_blog_option( $site->blog_id, 'stylesheet' );
            $blog_id = (int) $site->blog_id;

            if ( ! is_string( $slug ) || $slug === '' ) {
                continue;
            }

            if ( ! isset( $map[ $slug ] ) ) {
                $map[ $slug ] = array();
            }

            $map[ $slug ][] = $blog_id;
        }

        return $map;
    }

    /**
     * Return the list of theme slugs with auto-updates enabled.
     *
     * @return string[]
     */
    private function get_auto_update_themes(): array {
        return (array) get_site_option( 'auto_update_themes', array() );
    }

    /**
     * Return available update information keyed by theme slug.
     *
     * Each value is the new version string available.
     *
     * @return array<string, string>
     */
    private function get_update_info(): array {
        $update_data = get_site_transient( 'update_themes' );

        if (
            ! is_object( $update_data )
            || ! isset( $update_data->response )
            || ! is_array( $update_data->response )
        ) {
            return array();
        }

        $updates = array();

        foreach ( $update_data->response as $slug => $info ) {
            $new_version = is_array( $info ) ? ( $info['new_version'] ?? '' ) : '';

            if ( $new_version !== '' ) {
                $updates[ $slug ] = $new_version;
            }
        }

        return $updates;
    }

}
