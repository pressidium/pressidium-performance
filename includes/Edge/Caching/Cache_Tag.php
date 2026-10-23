<?php
/**
 * Cache tag.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Caching;

use Pressidium\WP\Performance\Utils\WP_Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Cache_Tag class.
 *
 * @since 1.1.0
 */
class Cache_Tag {

    const AUTHOR    = 'author';
    const TAXONOMY  = 'taxonomy';
    const POST_TYPE = 'post_type';
    const POST      = 'post';
    const DATE      = 'date';
    const SITEMAP   = 'sitemap';
    const FEED      = 'feed';
    const REST      = 'rest';
    const SEARCH    = 'search';
    const FRONT     = 'front';
    const ADMIN     = 'admin';
    const AJAX      = 'ajax';

    /**
     * @var string Cache tag name.
     */
    private string $name;

    /**
     * @var ?string Optional parameter for dynamic cache tags.
     */
    private ?string $param;

    /**
     * @var bool Whether the key should be prefixed with the web property handle and blog ID.
     */
    private bool $should_prefix = true;

    /**
     * Cache_Tag constructor.
     *
     * @param string  $web_property_handle Web property handle.
     * @param string  $name                Cache tag name.
     * @param ?string $param               Optional parameter for dynamic cache tags.
     */
    public function __construct( private readonly string $web_property_handle, string $name, ?string $param = null ) {
        $this->name  = $name;
        $this->param = $param;
    }

    /**
     * Prefix the key with the web property handle and blog ID.
     *
     * If `should_prefix` is set to `false`, the key won't be prefixed.
     *
     * @param string $key Cache tag to prefix.
     *
     * @return string
     */
    private function prefix_key( string $key ): string {
        if ( ! $this->should_prefix ) {
            return $key;
        }

        return implode( '_', array( $this->web_property_handle, WP_Utils::get_blog_id(), $key ) );
    }

    /**
     * Set the cache tag to not be prefixed.
     *
     * @return Cache_Tag
     */
    public function do_not_prefix(): Cache_Tag {
        $this->should_prefix = false;

        return $this; // chainable
    }

    /**
     * Return the value of the cache tag.
     *
     * @return string
     */
    public function get_value(): string {
        if ( is_null( $this->param ) ) {
            return $this->prefix_key( $this->name );
        }

        $cache_tag = implode( '-', array( $this->name, $this->param ) );

        return $this->prefix_key( $cache_tag );
    }

}
