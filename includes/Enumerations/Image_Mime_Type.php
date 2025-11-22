<?php
/**
 * Image mime type string backed enumeration.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Enumerations;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

enum Image_Mime_Type: string {
    case JPEG = 'image/jpeg';
    case PNG  = 'image/png';
    case WEBP = 'image/webp';
    case GIF  = 'image/gif';
    case AVIF = 'image/avif';
}
