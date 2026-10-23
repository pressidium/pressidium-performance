<?php
/**
 * Collector interface.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Collector interface.
 *
 * @since 1.2.0
 */
interface Collector {

    /**
     * Return the collector name.
     *
     * This is used as the key in the collected data payload.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Collect data.
     *
     * @return array
     */
    public function collect(): array;

}
