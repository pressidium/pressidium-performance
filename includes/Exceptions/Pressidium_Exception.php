<?php
/**
 * Pressidium exception.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Exceptions;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Pressidium_Exception class.
 *
 * This is the base class for all exceptions thrown by the plugin.
 *
 * @since 1.0.0
 */
abstract class Pressidium_Exception extends Exception {}
