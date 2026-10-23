<?php
/**
 * WordPress core collector.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector\Collectors\WP_Core;

use Pressidium\WP\Performance\Edge\Collector\Collector;
use Pressidium\WP\Performance\Utils\WP_Utils;
use Pressidium\WP\Performance\Edge\Caching\Domains_Provider;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * WP_Core_Collector class.
 *
 * Collects data related to the WordPress core.
 *
 * @since 1.2.0
 */
final class WP_Core_Collector implements Collector {

    /**
     * Return the collector name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'wp_core';
    }

    /**
     * Collect WordPress core data.
     *
     * @return array<string, mixed>
     */
    public function collect(): array {
        return array(
            'wp_version'     => WP_Utils::get_wp_version(),
            'db_version'     => WP_Utils::get_db_version(),
            'updates'        => $this->get_available_updates(),
            'locale'         => WP_Utils::get_locale(),
            'timezone'       => WP_Utils::get_timezone(),
            'home_url'       => $this->get_home_url(),
            'site_url'       => $this->get_site_url(),
            'wp_type'        => $this->get_wp_type(),
            'multisite_type' => WP_Utils::get_multisite_type(),
            'primary_domain' => WP_Utils::get_domain(),
            'domains'        => $this->get_domains(),
            'user_count'     => WP_Utils::get_user_count(),
        );
    }

    /**
     * Return available WordPress core updates.
     *
     * @return array<int, array<string, mixed>>
     */
    private function get_available_updates(): array {
        if ( ! function_exists( 'get_core_updates' ) ) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        $updates = get_core_updates();

        if ( ! is_array( $updates ) ) {
            return array();
        }

        $available_updates = array();

        foreach ( $updates as $update ) {
            if ( ! is_object( $update ) ) {
                continue;
            }

            if ( isset( $update->response ) && $update->response === 'latest' ) {
                // Already on the latest version, skip
                continue;
            }

            $available_updates[] = array(
                'version'  => $update->version ?? '',
                'response' => $update->response ?? '',
            );
        }

        return $available_updates;
    }

    /**
     * Return the home URL.
     *
     * @return string
     */
    private function get_home_url(): string {
        return get_home_url();
    }

    /**
     * Return the site URL.
     *
     * @return string
     */
    private function get_site_url(): string {
        return get_site_url();
    }

    /**
     * Return the WordPress type ('standard' or 'multisite').
     *
     * @return string
     */
    private function get_wp_type(): string {
        return WP_Utils::is_multisite() ? 'multisite' : 'standard';
    }

    /**
     * Return all domains associated with this installation.
     *
     * For single sites, this returns the primary domain.
     * For multisites, this returns all domains across all sites.
     *
     * @return string[]
     */
    private function get_domains(): array {
        return Domains_Provider::get_all_domains();
    }

}
