<?php
/**
 * Stager.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Caching;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Stager class.
 *
 * @since 1.1.0
 */
class Stager {

    /**
     * @var Cache_Object[] Cache objects to purge.
     */
    private array $cache_objects = array();

    /**
     * @var Cacheable_Post[] Posts to purge.
     */
    private static array $posts = array();

    /**
     * @var int[] Post IDs.
     */
    private static array $post_ids = array();

    /**
     * Stager constructor.
     *
     * @param Cache_Tag_Factory $cache_tag_factory
     * @param Cacheable_Post    $cacheable_post
     */
    public function __construct(
        private readonly Cache_Tag_Factory $cache_tag_factory,
        private readonly Cacheable_Post $cacheable_post
    ) {}

    /**
     * Push the given cache object to the cache objects array ensuring no duplicates.
     *
     * @param Cache_Object $cache_object Cache object to push.
     *
     * @return void
     */
    private function push_cache_object( Cache_Object $cache_object ): void {
        // TODO: Confirm that we can use `in_array()` here

        if ( in_array( $cache_object, $this->cache_objects, true ) ) {
            // The cache object is already in the array, bail early
            return;
        }

        $this->cache_objects[] = $cache_object;
    }

    /**
     * Stage the given cache tag for purging with the given priority.
     *
     * @param Cache_Tag $cache_tag Cache tag to purge.
     * @param string    $priority  Priority of the cache tag.
     *
     * @return void
     */
    private function stage_cache_tag( Cache_Tag $cache_tag, string $priority ): void {
        $this->push_cache_object( new Cache_Object( $cache_tag, $priority ) );
    }

    /**
     * Stage home page.
     *
     * @return Stager
     */
    public function stage_home_page(): Stager {
        if ( ! $this->cacheable_post->purgeable ) {
            return $this; // chainable
        }

        $this->stage_cache_tag(
            $this->cache_tag_factory->create( Cache_Tag::FRONT ),
            Cache_Priority::HIGH
        );

        return $this; // chainable
    }

    /**
     * Stage post page.
     *
     * @return Stager
     */
    public function stage_post_page(): Stager {
        if ( ! $this->cacheable_post->purgeable ) {
            return $this; // chainable
        }

        $post_id = $this->cacheable_post->get_post_id();

        $this->stage_cache_tag(
            $this->cache_tag_factory->create( Cache_Tag::POST, $post_id ),
            Cache_Priority::HIGH
        );

        return $this; // chainable
    }

    /**
     * Stage posts' index page.
     *
     * @return Stager
     */
    public function stage_posts_index_page(): Stager {
        if ( ! $this->cacheable_post->purgeable || $this->cacheable_post->is_custom_post_type() ) {
            return $this; // chainable
        }

        $this->stage_cache_tag(
            $this->cache_tag_factory->create( Cache_Tag::POST_TYPE, 'post' ),
            Cache_Priority::LOW
        );

        return $this; // chainable
    }

    /**
     * Stage previous/next posts.
     *
     * @return Stager
     */
    public function stage_adjacent_posts(): Stager {
        if ( ! $this->cacheable_post->purgeable || $this->cacheable_post->is_custom_post_type() ) {
            return $this; // chainable
        }

        $prev_post = $this->cacheable_post->get_previous_post();
        $next_post = $this->cacheable_post->get_next_post();

        if ( $prev_post ) {
            $this->stage_cache_tag(
                $this->cache_tag_factory->create( Cache_Tag::POST, (string) $prev_post->ID ),
                Cache_Priority::LOW
            );
        }

        if ( $next_post ) {
            $this->stage_cache_tag(
                $this->cache_tag_factory->create( Cache_Tag::POST, (string) $next_post->ID ),
                Cache_Priority::LOW
            );
        }

        return $this; // chainable
    }

    /**
     * Stage archive page.
     *
     * @return Stager
     */
    public function stage_archive_page(): Stager {
        if ( ! $this->cacheable_post->purgeable ) {
            return $this; // chainable
        }

        $post_type = $this->cacheable_post->get_post_type();

        if ( $post_type === 'post' ) {
            // We already staged the posts index page, so no need to stage it again
            return $this; // chainable
        }

        $this->stage_cache_tag(
            $this->cache_tag_factory->create( Cache_Tag::POST_TYPE, $post_type ),
            Cache_Priority::LOW
        );

        return $this; // chainable
    }

    /**
     * Stage author page.
     *
     * @return Stager
     */
    public function stage_author_page(): Stager {
        if ( ! $this->cacheable_post->purgeable ) {
            return $this; // chainable
        }

        $author_id = $this->cacheable_post->get_post_author_id();

        $this->stage_cache_tag(
            $this->cache_tag_factory->create( Cache_Tag::AUTHOR, $author_id ),
            Cache_Priority::LOW
        );

        return $this; // chainable
    }

    /**
     * Stage the REST endpoints that *might be* cached.
     *
     * @return Stager
     */
    public function stage_rest_endpoints(): Stager {
        if ( ! $this->cacheable_post->purgeable ) {
            return $this; // chainable
        }

        $this->stage_cache_tag(
            $this->cache_tag_factory->create( Cache_Tag::REST ),
            Cache_Priority::LOW
        );

        return $this; // chainable
    }

    /**
     * Stage date based archives.
     *
     * @return Stager
     */
    public function stage_date_based_archives(): Stager {
        if ( ! $this->cacheable_post->purgeable ) {
            return $this; // chainable
        }

        $this->stage_cache_tag(
            $this->cache_tag_factory->create( Cache_Tag::DATE ),
            Cache_Priority::LOW
        );

        return $this; // chainable
    }

    /**
     * Stage feed archives (only if the post/page being purged is *not* older than 7 days).
     *
     * @return Stager
     */
    public function stage_feed_archives(): Stager {
        if ( ! $this->cacheable_post->purgeable
            || $this->cacheable_post->is_older_than( 7 * DAY_IN_SECONDS ) ) {
            return $this; // chainable
        }

        $this->stage_cache_tag(
            $this->cache_tag_factory->create( Cache_Tag::FEED ),
            Cache_Priority::LOW
        );

        return $this; // chainable
    }

    /**
     * Stage taxonomy terms (tags, categories, etc.).
     *
     * @return Stager
     */
    public function stage_taxonomies(): Stager {
        if ( ! $this->cacheable_post->purgeable ) {
            return $this; // chainable
        }

        $term_ids = $this->cacheable_post->get_taxonomy_term_ids();

        foreach ( $term_ids as $term_id ) {
            $this->stage_cache_tag(
                $this->cache_tag_factory->create( Cache_Tag::TAXONOMY, strval( $term_id ) ),
                Cache_Priority::LOW
            );
        }

        return $this; // chainable
    }

    /**
     * Stage sitemaps.
     *
     * @return Stager
     */
    public function stage_sitemaps(): Stager {
        if ( ! $this->cacheable_post->purgeable ) {
            return $this; // chainable
        }

        $this->stage_cache_tag(
            $this->cache_tag_factory->create( Cache_Tag::SITEMAP ),
            Cache_Priority::LOW
        );

        return $this; // chainable
    }

    /**
     * Stage search results.
     *
     * @return Stager
     */
    public function stage_search_results(): Stager {
        if ( ! $this->cacheable_post->purgeable ) {
            return $this; // chainable
        }

        $this->stage_cache_tag(
            $this->cache_tag_factory->create( Cache_Tag::SEARCH ),
            Cache_Priority::LOW
        );

        return $this; // chainable
    }

    /**
     * Return all cacheable resources that have been staged for purging.
     *
     * @return array<array<string, string>>
     */
    public function get_resources_to_purge(): array {
        return array_map(
            function ( $cache_object ) {
                return array(
                    'value'    => $cache_object->get_cache_tag()->get_value(),
                    'priority' => $cache_object->get_priority(),
                );
            },
            $this->cache_objects
        );
    }

}
