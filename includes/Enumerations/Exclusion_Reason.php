<?php
/**
 * Exclusion reason string backed enumeration.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Enumerations;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Why a file could not be included in a concatenated bundle.
 */
enum Exclusion_Reason: string {
    case READ_FAILED   = 'read_failed';
    case SRI_MISMATCH  = 'sri_mismatch';
    case MINIFY_FAILED = 'minify_failed';
    case EVAL_SCOPE    = 'eval_scope';
    case PARSE_ERROR   = 'parse_error';
    case INVALID_UTF8  = 'invalid_utf8';
}
