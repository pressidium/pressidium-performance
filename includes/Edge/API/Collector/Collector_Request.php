<?php
/**
 * Collector request.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\API\Collector;

use Pressidium\WP\Performance\Edge\API\Request;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Collector_Request class.
 *
 * @since 1.2.0
 */
final class Collector_Request implements Request {

    /**
     * @var string Web property handle.
     */
    private string $web_property_handle;

    /**
     * @var array<string, array<string, mixed>> Collected data.
     */
    private array $data;

    /**
     * Set the web property handle.
     *
     * @param string $web_property_handle Web property handle.
     *
     * @return Collector_Request
     */
    public function set_web_property_handle( string $web_property_handle ): Collector_Request {
        $this->web_property_handle = $web_property_handle;

        return $this; // chainable
    }

    /**
     * Return the web property handle.
     *
     * @return string
     */
    public function get_web_property_handle(): string {
        return $this->web_property_handle;
    }

    /**
     * Set the collected data.
     *
     * @param array<string, array<string, mixed>> $data Collected data.
     *
     * @return Collector_Request
     */
    public function set_data( array $data ): Collector_Request {
        $this->data = $data;

        return $this; // chainable
    }

    /**
     * Return the collected data.
     *
     * @return array<string, array<string, mixed>>
     */
    public function get_data(): array {
        return $this->data;
    }

    /**
     * Return the request payload as an array.
     *
     * @return array<string, mixed>
     */
    public function to_payload(): array {
        // In the future, we could also append the web property handle to the request payload, if we needed to
        return $this->data;
    }

}

