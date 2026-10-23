<?php
/**
 * Web property not found API exception.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Exceptions\API;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Web_Property_Not_Found_Exception class.
 *
 * @since 1.1.0
 */
class Web_Property_Not_Found_Exception extends API_Exception {}
