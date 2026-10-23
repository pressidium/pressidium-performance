<?php
/**
 * Mapped domains provider.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Caching;

use Pressidium\WP\Performance\Utils\Array_Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Domains_Provider class.
 *
 * @since 1.1.0
 */
class Domains_Provider {

    /**
     * Whether the current multisite installation uses the `sunrise.php` file for domain mapping.
     *
     * @return bool
     */
    private static function is_sunrise(): bool {
        return defined( 'SUNRISE' ) && SUNRISE;
    }

    /**
     * Return the mapped domains a blog may be associated with based on the `sunrise.php` file.
     *
     * @param ?int $blog_id Blog ID to return mapped domains for.
     *                      If `null`, mapped domains for all blogs will be returned.
     *
     * @return string[]
     */
    private static function get_sunrise_mapped_domains( ?int $blog_id = null ): array {
        global $wpdb;

        $mapped_domains = array();

        if ( ! self::is_sunrise() || ! isset( $wpdb->dmtable ) || ! is_multisite() ) {
            return array();
        }

        if ( $blog_id ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare( "SELECT domain FROM {$wpdb->dmtable} WHERE blog_id = %d", $blog_id )
            );
        } else {
            $rows = $wpdb->get_results( "SELECT domain FROM {$wpdb->dmtable}", OBJECT );
        }

        if ( $rows && count( $rows ) > 0 ) {
            foreach ( $rows as $row ) {
                $mapped_domains[] = strtolower( $row->domain );
            }
        }

        return $mapped_domains;
    }

    /**
     * Return the mapped domains a blog may be associated with on installations that don't use the `sunrise.php` file.
     *
     * @param ?int $blog_id Blog ID to return mapped domains for.
     *                      If `null`, mapped domains for all blogs will be returned.
     *
     * @return array
     */
    private static function get_non_sunrise_mapped_domains( ?int $blog_id = null ): array {
        $mapped_domains = array();

        if ( ! is_multisite() ) {
            return array();
        }

        $sites = get_sites();

        foreach ( $sites as $site ) {
            if ( $blog_id && (int) $site->blog_id !== (int) $blog_id ) {
                continue;
            }

            $site_url_parts = wp_parse_url( get_site_url( $site->blog_id ) );

            if ( isset( $site_url_parts['host'] ) ) {
                $mapped_domains[] = strtolower( $site_url_parts['host'] );
            }
        }

        return $mapped_domains;
    }

    /**
     * Return the mapped domains a blog may be associated with.
     *
     * @param ?int $blog_id Blog ID to return mapped domains for.
     *                      If `null`, mapped domains for all blogs will be returned.
     *
     * @return string[]
     */
    private static function get_filtered_mapped_domains( ?int $blog_id = null ): array {
        if ( self::is_sunrise() ) {
            $mapped_domains = self::get_sunrise_mapped_domains( $blog_id );
        } else {
            $mapped_domains = self::get_non_sunrise_mapped_domains( $blog_id );
        }

        /**
         * Filters the list of mapped domains a blog may be associated with.
         *
         * @param string[] $mapped_domains The list of mapped domains.
         * @param int|null $blog_id        The ID of the blog.
         */
        return apply_filters( 'pressidium_edge_multisite_mapped_domains', $mapped_domains, $blog_id );
    }

    /**
     * Return all domains the current site may be associated with.
     *
     * @return array
     */
    public static function get_current_site_domains(): array {
        $domains = array();

        // Primary domain for the current site
        $blog_url_parts = wp_parse_url( get_bloginfo( 'url' ) );
        $domains[]      = $blog_url_parts['host'];

        if ( ! is_multisite() ) {
            return $domains;
        }

        // Mapped domains for the current site
        $mapped_domains = self::get_filtered_mapped_domains( get_current_blog_id() );
        $domains        = array_merge( $domains, $mapped_domains );

        return Array_Utils::dedupe_values( $domains );
    }

    /**
     * Return all domains all sites may be associated with.
     *
     * If this runs on a standalone installation, it will return the domain of the site.
     *
     * @return string[]
     */
    public static function get_all_domains(): array {
        $domains = array();

        if ( ! is_multisite() ) {
            /*
             * For standalone installations, we just return the domain
             * of the site, as there are no mapped domains to consider.
             */
            $blog_url_parts = wp_parse_url( get_bloginfo( 'url' ) );
            $domains[]      = strtolower( $blog_url_parts['host'] );

            return $domains;
        }

        $sites = get_sites();

        foreach ( $sites as $site ) {
            $site_url_parts = wp_parse_url( get_site_url( $site->blog_id ) );

            if ( isset( $site_url_parts['host'] ) ) {
                // Primary domain for the currently iterated site
                $domains[] = strtolower( $site_url_parts['host'] );
            }

            // Mapped domains for the currently iterated site
            $mapped_domains = self::get_filtered_mapped_domains( $site->blog_id );
            $domains        = array_merge( $domains, $mapped_domains );
        }

        return Array_Utils::dedupe_values( $domains );
    }

}
