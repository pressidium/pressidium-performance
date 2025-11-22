<?php
/**
 * Invalid argument exception.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Storage;

use Exception;

use Pressidium\WP\Performance\Dependencies\Psr\SimpleCache\InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Invalid_Argument class.
 *
 * Exception thrown when an invalid argument is used.
 *
 * @since 1.0.0
 */
class Invalid_Argument extends Exception implements InvalidArgumentException  {}
