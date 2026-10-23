<?php
/**
 * Cache tag factory.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Caching;

use Pressidium\WP\Performance\Edge\Web_Property;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Cache_Tag_Factory class.
 *
 * @since 1.1.0
 */
class Cache_Tag_Factory {

    /**
     * Cache_Tag_Factory constructor.
     *
     * @param Web_Property $web_property Web_Property instance.
     */
    public function __construct( private readonly Web_Property $web_property ) {}

    /**
     * Create a cache tag.
     *
     * @param string  $name  Cache tag name.
     * @param ?string $param Optional parameter for dynamic cache tags.
     *
     * @return Cache_Tag
     */
    public function create( string $name, ?string $param = null ): Cache_Tag {
        return new Cache_Tag( $this->web_property->get_web_property_handle(), $name, $param );
    }

}
