<?php
/**
 * Purge request.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\API\Cache;

use Pressidium\WP\Performance\Edge\API\Request;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Purge_Request class.
 *
 * @since 1.1.0
 */
final class Purge_Request implements Request {

    /**
     * @var array{high: string[], low: string[]} $cache_keys Cache keys to purge.
     */
    private array $cache_keys;

    /**
     * @var string[] $domains Domains to purge.
     */
    private array $domains;

    /**
     * @var string[] $uris URIs to purge.
     */
    private ?array $uris;

    /**
     * Set the cache keys to purge.
     *
     * @param array{high: string[], low: string[]} $cache_keys Cache keys to purge as an associative array where keys
     *                                                         can be 'high' and 'low' depending on their priority,
     *                                                         and values are arrays of cache tags to purge.
     *
     * @return Purge_Request
     */
    public function set_cache_keys( array $cache_keys ): Purge_Request {
        $this->cache_keys = $cache_keys;

        return $this; // chainable
    }

    /**
     * Return the cache keys to purge.
     *
     * @return array{high: string[], low: string[]} Cache keys to purge.
     */
    public function get_cache_keys(): array {
        $default = array(
            'high' => array(),
            'low'  => array(),
        );

        return array_merge( $default, $this->cache_keys );
    }

    /**
     * Set the domains to purge.
     *
     * @param string[] $domains Domains to purge.
     *
     * @return Purge_Request
     */
    public function set_domains( array $domains ): Purge_Request {
        $this->domains = $domains;

        return $this; // chainable
    }

    /**
     * Return the domains to purge.
     *
     * @return string[] Domains to purge.
     */
    public function get_domains(): array {
        return $this->domains;
    }

    /**
     * Set the URIs to purge.
     *
     * @param string[] $uris URIs to purge.
     *
     * @return Purge_Request
     */
    public function set_uris( array $uris ): Purge_Request {
        $this->uris = $uris;

        return $this; // chainable
    }

    /**
     * Return the URIs to purge.
     *
     * @return string[] URIs to purge.
     */
    public function get_uris(): array {
        return is_array( $this->uris ) ? $this->uris : array();
    }

    /**
     * Return the request payload as an array.
     *
     * @return array{
     *     cache_keys: array{high: string[], low: string[]},
     *     domains: string[],
     *     uris: string[]
     * }
     */
    public function to_payload(): array {
        return array(
            'cache_keys' => $this->cache_keys,
            'domains'    => $this->domains,
            'uris'       => $this->uris,
        );
    }

}
