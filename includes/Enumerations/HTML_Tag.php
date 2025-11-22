<?php
/**
 * HTML tag string backed enumeration.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Enumerations;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

enum HTML_Tag: string {
    case SCRIPT = 'SCRIPT';
    case LINK   = 'LINK';
}
