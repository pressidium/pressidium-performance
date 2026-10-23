<?php
/**
 * API exception.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Exceptions\API;

use Pressidium\WP\Performance\Exceptions\Pressidium_Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * API_Exception class.
 *
 * @since 1.1.0
 */
class API_Exception extends Pressidium_Exception {}
