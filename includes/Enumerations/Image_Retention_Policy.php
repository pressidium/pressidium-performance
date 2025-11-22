<?php
/**
 * Image retention policy enumeration.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Enumerations;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

enum Image_Retention_Policy {
    case RETAIN_ORIGINAL;
    case DELETE_ORIGINAL;
}
