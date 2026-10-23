<?php
/**
 * Headers.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Caching;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Posts_Caching class.
 *
 * @since 1.1.0
 */
class Posts_Caching {

    /**
     * @var array Resources that have been staged for purging.
     */
    private array $staged_resources = array();

    /**
     * @var Cacheable_Post[] Posts to purge.
     */
    private array $posts = array();

    /**
     * @var int[] Post IDs.
     */
    private array $post_ids = array();

    /**
     * Posts_Caching constructor.
     *
     * @param Cache_Tag_Factory $cache_tag_factory Cache tag factory.
     */
    public function __construct( private readonly Cache_Tag_Factory $cache_tag_factory ) {}

    /**
     * Whether the given post has already been handled.
     *
     * @param Cacheable_Post $cacheable_post Cacheable post.
     *
     * @return bool
     */
    private function already_handled( Cacheable_Post $cacheable_post ): bool {
        return in_array( $cacheable_post->get_post_id(), $this->post_ids, true );
    }

    /**
     * Stage the given post ID for purging.
     *
     * @param int  $post_id       Post ID.
     * @param bool $ignore_status If set to `true`, the post will be
     *                            purged regardless of its status
     *                            (defaults to `false`).
     *
     * @return void
     */
    public function stage_post( int $post_id, bool $ignore_status = false ): void {
        // TODO: Maybe do not use a `bool` for `$ignore_status`

        if ( in_array( $post_id, $this->post_ids, true ) ) {
            return;
        }

        $post = get_post( $post_id );

        if ( ! is_object( $post ) ) {
            return;
        }

        $this->posts[] = new Cacheable_Post( $post, $ignore_status );
    }

    /**
     * Instantiate and return a stager for the given cacheable post.
     *
     * @param Cacheable_Post $cacheable_post Cacheable post.
     *
     * @return Stager
     */
    private function build_stager( Cacheable_Post $cacheable_post ): Stager {
        return new Stager( $this->cache_tag_factory, $cacheable_post );
    }

    /**
     * Stage all cacheable resources of the given post.
     *
     * @param Cacheable_Post $cacheable_post Cacheable post.
     *
     * @return Stager
     */
    private function stage_all_resources_of_post( Cacheable_Post $cacheable_post ): Stager {
        $stager = $this->build_stager( $cacheable_post );

        if ( ! $cacheable_post->purgeable || $this->already_handled( $cacheable_post ) ) {
            return $stager;
        }

        $stager
            ->stage_home_page()
            ->stage_post_page()
            ->stage_adjacent_posts()
            ->stage_posts_index_page()
            ->stage_archive_page()
            ->stage_author_page()
            ->stage_rest_endpoints()
            ->stage_date_based_archives()
            ->stage_feed_archives()
            ->stage_taxonomies()
            ->stage_sitemaps()
            ->stage_search_results();

        $this->post_ids[] = $cacheable_post->get_post_id();

        return $stager;
    }

    /**
     * Stage all cacheable resources for the currently staged posts.
     *
     * @return void
     */
    private function stage_all_resources_of_all_posts(): void {
        foreach ( $this->posts as $cacheable_post ) {
            if ( ! $cacheable_post->purgeable ) {
                continue;
            }

            $stager = $this->stage_all_resources_of_post( $cacheable_post );

            $this->staged_resources = array_merge(
                $this->staged_resources,
                $stager->get_resources_to_purge()
            );
        }
    }

    /**
     * Clear staged resources.
     *
     * @return void
     */
    public function clear_staged_resources(): void {
        $this->staged_resources = array();
    }

    /**
     * Clear posts.
     *
     * @return void
     */
    public function clear_posts(): void {
        $this->posts = array();
    }

    /**
     * Clear post IDs.
     *
     * @return void
     */
    public function clear_post_ids(): void {
        $this->post_ids = array();
    }

    /**
     * Clear both staged paths and posts.
     *
     * @return void
     */
    public function clear_all(): void {
        $this->clear_staged_resources();
        $this->clear_posts();
        $this->clear_post_ids();
    }

    /**
     * Return all cacheable resources that have been staged for purging.
     *
     * @return Cache_Object[]|string[]
     */
    public function get_resources_to_purge(): array {
        if ( empty( $this->staged_resources ) ) {
            $this->stage_all_resources_of_all_posts();
        }

        return $this->staged_resources;
    }

    /**
     * Return the IDs of the posts to purge.
     *
     * @return int[]
     */
    public function get_post_ids_to_purge(): array {
        return $this->post_ids;
    }

}
