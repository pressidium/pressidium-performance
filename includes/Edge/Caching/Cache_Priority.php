<?php
/**
 * Cache priority constants.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Caching;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Cache_Priority class.
 *
 * @since 1.1.0
 */
class Cache_Priority {

    const LOW  = 'low';
    const HIGH = 'high';

}
