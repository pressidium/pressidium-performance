<?php
/**
 * Image editor string backed enumeration.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Enumerations;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

enum Image_Editor: string {
    case IMAGICK = 'WP_Image_Editor_Imagick';
    case GD      = 'WP_Image_Editor_GD';
}
