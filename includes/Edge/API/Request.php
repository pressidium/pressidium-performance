<?php
/**
 * Request interface.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\API;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Request interface.
 *
 * @since 1.1.0
 */
interface Request {

    /**
     * Return the request payload as an array.
     *
     * @return array<string, mixed>
     */
    public function to_payload(): array;

}
