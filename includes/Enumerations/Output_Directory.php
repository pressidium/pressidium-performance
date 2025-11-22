<?php
/**
 * Output directory string backed enumeration.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Enumerations;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

enum Output_Directory: string {
    case MINIFIED     = 'minified';
    case CONCATENATED = 'concatenated';
}
