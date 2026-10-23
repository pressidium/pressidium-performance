<?php
/**
 * Cacheable post.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Caching;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Cacheable_Post class.
 *
 * @since 1.1.0
 */
class Cacheable_Post {

    /**
     * @var ?WP_Post The post as an instance of `WP_Post`.
     */
    public ?WP_Post $post;

    /**
     * @var ?bool Whether the post can (and should) be purged.
     */
    public ?bool $purgeable = null;

    /**
     * Cacheable_Post constructor.
     *
     * @param WP_Post $post          The post as an instance of `WP_Post`.
     * @param bool    $ignore_status If set to `true`, the post will be
     *                               purged regardless of its status
     *                               (defaults to `false`).
     */
    public function __construct( $post, bool $ignore_status = false ) {
        $this->post      = $post;
        $this->purgeable = false;

        if ( is_object( $this->post ) ) {
            $this->purgeable = $this->is_purgeable( $ignore_status );
        }
    }

    /**
     * Whether this is a public post.
     *
     * @return bool
     */
    public function is_public(): bool {
        $post_type = get_post_type_object( $this->post->post_type );
        return is_object( $post_type ) && $post_type->public === true;
    }

    /**
     * Whether the type of this post is cached.
     *
     * @return bool
     */
    public function is_cacheable(): bool {
        $non_cacheable_types = array( 'attachment', 'revision' );
        return ! in_array( $this->post->post_type, $non_cacheable_types, true );
    }

    /**
     * Whether the post is published.
     *
     * We don't need to purge drafts, scheduled, deleted posts, etc.
     *
     * @return bool
     */
    public function is_published(): bool {
        return $this->post->post_status === 'publish';
    }

    /**
     * Whether this post should be purged.
     *
     * @param bool $ignore_status If set to `true`, the post will be
     *                            purged regardless of its status.
     *
     * @return bool
     */
    public function is_purgeable( bool $ignore_status = false ): bool {
        if ( ! $this->is_public() ) {
            return false;
        }

        return ( $this->is_cacheable() && $this->is_published() ) || $ignore_status;
    }

    /**
     * Whether this post is a custom post type.
     *
     * @return bool
     */
    public function is_custom_post_type(): bool {
        return $this->post->post_type !== 'post';
    }

    /**
     * Return all taxonomy term IDs of the post.
     *
     * @return int[]
     */
    public function get_taxonomy_term_ids(): array {
        return Taxonomies::get_taxonomy_terms( $this->post->ID );
    }

    /**
     * Return the adjacent post (if any).
     *
     * @param bool $is_previous Whether to retrieve the previous post.
     *                          Defaults to `true`.
     *
     * @return null|string|WP_Post
     */
    private function get_adjacent_post( bool $is_previous = true ): null|string|WP_Post {
        global $post;

        if ( ! $this->post ) {
            return null;
        }

        $old_post = $post;

        // Store the existing post so we can restore it later
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $post = $this->post;

        $adjacent_post = get_adjacent_post( false, '', $is_previous );

        // Restore the original global post
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $post = $old_post;

        return $adjacent_post;
    }

    /**
     * Return the previous post (if any).
     *
     * @return null|string|WP_Post
     */
    public function get_previous_post(): null|string|WP_Post {
        return $this->get_adjacent_post( true );
    }

    /**
     * Return the next post (if any).
     *
     * @return null|string|WP_Post
     */
    public function get_next_post(): null|string|WP_Post {
        return $this->get_adjacent_post( false );
    }

    /**
     * Whether this post is older than the given number of seconds.
     *
     * @param int $seconds
     *
     * @return bool
     */
    public function is_older_than( int $seconds ): bool {
        return time() - strtotime( $this->post->post_date_gmt ) > $seconds;
    }

    /**
     * Return the ID of the post.
     *
     * @return int
     */
    public function get_post_id(): int {
        return $this->post->ID;
    }

    /**
     * Return the ID of the author of the post.
     *
     * @return string
     */
    public function get_post_author_id(): string {
        return $this->post->post_author;
    }

    /**
     * Return the date of the post as a `YYYY-MM-DD HH:MM:SS` formatted string.
     *
     * @return string
     */
    public function get_post_date(): string {
        return $this->post->post_date;
    }

    /**
     * Return the type of the post.
     *
     * @return string
     */
    public function get_post_type(): string {
        return $this->post->post_type;
    }

}
