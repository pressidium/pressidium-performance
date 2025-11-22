<?php
/**
 * Filesystem exception.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Filesystem_Exception class.
 *
 * @since 1.0.0
 */
class Filesystem_Exception extends Pressidium_Exception {}
