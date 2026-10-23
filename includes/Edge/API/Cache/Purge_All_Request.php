<?php
/**
 * Purge all request.
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
 * Purge_All_Request class.
 *
 * @since 1.1.0
 */
final class Purge_All_Request implements Request {

    /**
     * @var string[] $domains Domains to purge.
     */
    private array $domains;

    /**
     * Set the domains to purge.
     *
     * @param string[] $domains Domains to purge.
     *
     * @return Purge_All_Request
     */
    public function set_domains( array $domains ): Purge_All_Request {
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
     * Return the request payload as an array.
     *
     * @return array<string, mixed>
     */
    public function to_payload(): array {
        return array(
            'domains' => $this->domains,
        );
    }

}
