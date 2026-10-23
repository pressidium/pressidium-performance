<?php
/**
 * Validation API exception.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Exceptions\API;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Validation_Exception class.
 *
 * @since 1.1.0
 */
class Validation_Exception extends API_Exception {}
