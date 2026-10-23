<?php
/**
 * Cache object.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Caching;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Cache_Object class.
 *
 * @since 1.1.0
 */
class Cache_Object {

    /**
     * @var Cache_Tag Cache tag.
     */
    private Cache_Tag $cache_tag;

    /**
     * @var string Priority of the cache object. Either 'high' or 'low'.
     */
    private string $priority;

    /**
     * Cache_Object constructor.
     *
     * @param Cache_Tag $cache_tag Cache tag.
     * @param string    $priority  Priority of the cache object. Either 'high' or 'low'.
     */
    public function __construct( Cache_Tag $cache_tag, string $priority ) {
        $this->cache_tag = $cache_tag;
        $this->priority  = $priority;
    }

    /**
     * Return the cache tag of the cache object.
     *
     * @return Cache_Tag
     */
    public function get_cache_tag(): Cache_Tag {
        return $this->cache_tag;
    }

    /**
     * Set the cache tag of the cache object.
     *
     * @param Cache_Tag $cache_tag Cache tag.
     *
     * @return void
     */
    public function set_cache_tag( Cache_Tag $cache_tag ): void {
        $this->cache_tag = $cache_tag;
    }

    /**
     * Return the priority of the cache object.
     *
     * @return string
     */
    public function get_priority(): string {
        return $this->priority;
    }

    /**
     * Set the priority of the cache object.
     *
     * @param string $priority Priority of the cache object. Either 'high' or 'low'.
     *
     * @return void
     */
    public function set_priority( string $priority ): void {
        $this->priority = $priority;
    }

    /**
     * Whether this cache object is the same as the given one.
     *
     * @param Cache_Object $cache_object Cache object to compare with.
     *
     * @return bool
     */
    public function is_same_as( Cache_Object $cache_object ): bool {
        return $this->cache_tag->get_value() === $cache_object->get_cache_tag()->get_value()
               && $this->priority === $cache_object->get_priority();
    }

}
