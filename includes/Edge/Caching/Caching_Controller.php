<?php
/**
 * Caching controller.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Caching;

use Pressidium\WP\Performance\Hooks\Actions;
use Pressidium\WP\Performance\Logging\Logger;

use Pressidium\WP\Performance\Edge\API\Edge_API_Client;
use Pressidium\WP\Performance\Edge\API\Cache\Purge_Request;
use Pressidium\WP\Performance\Edge\API\Cache\Purge_All_Request;
use Pressidium\WP\Performance\Edge\Web_Property;
use Pressidium\WP\Performance\Utils\Array_Utils;
use Pressidium\WP\Performance\Utils\String_Utils;

use Pressidium\WP\Performance\Exceptions\API\API_Exception;
use Pressidium\WP\Performance\Exceptions\API\Rate_Limit_Exception;

use WP_Error;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Caching_Controller class.
 *
 * @since 1.1.0
 */
class Caching_Controller implements Actions {

    /**
     * Caching_Controller constructor.
     *
     * @param Logger            $logger            `Logger` instance.
     * @param Posts_Caching     $posts_caching     Shared `Posts_Caching` instance.
     * @param Edge_API_Client   $edge_api_client   `Edge_API_client` instance.
     * @param Cache_Tag_Factory $cache_tag_factory `Cache_Tag_Factory` instance.
     * @param Web_Property      $web_property      `Web_Property` instance.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly Posts_Caching $posts_caching,
        private readonly Edge_API_Client $edge_api_client,
        private readonly Cache_Tag_Factory $cache_tag_factory,
        private readonly Web_Property $web_property
    ) {}

    /**
     * Whether edge caching is enabled.
     *
     * Checks that a valid web property handle has been configured.
     *
     * @return bool
     */
    private function is_caching_enabled(): bool {
        if ( function_exists( 'is_pressidium_platform' ) && \is_pressidium_platform() ) {
            // Running on Pressidium, disable EDGE caching to avoid conflicts with Pressidium's built-in caching
            return false;
        }

        $is_connected_to_edge = ! empty( $this->web_property->get_web_property_handle() );

        // Caching is only enabled if we are connected to Pressidium EDGE
        return $is_connected_to_edge;
    }

    /**
     * Handle the various post status updates.
     *
     * We need this to handle existing posts being updated
     * from 'publish' to 'draft', 'pending', or 'private'.
     *
     * @link https://developer.wordpress.org/reference/hooks/pre_post_update/
     *
     * @param int                  $post_id Post ID.
     * @param array<string, mixed> $data    Array of unslashed post data.
     */
    public function handle_post_status_transition( int $post_id, array $data ): void {
        $post = get_post( $post_id );

        if ( ! is_object( $post ) ) {
            return;
        }

        if ( ! ( $post->post_status === 'publish'
                && in_array( $data['post_status'], array( 'draft', 'pending', 'private' ), true ) ) ) {
            return;
        }

        $this->stage_page_cache_for_purging( $post->ID, true );
    }

    /**
     * Purge all caches when Elementor changes something.
     *
     * We use a dedicated method for this, to prevent unnecessarily invalidating
     * the cache when Elementor is not active (a third-party plugin may trigger
     * the same hooks) or when Elementor is active, but it is using the
     * Internal Embeddings as its CSS print method.
     *
     * @return void
     */
    public function elementor_purge_cache(): void {
        if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
            // Elementor is not active, no need to purge the cache
            return;
        }

        if ( get_option( 'elementor_css_print_method', 'external' ) === 'internal' ) {
            // Elementor is using Internal Embeddings, no need to purge the cache
            return;
        }

        $this->purge_all_caches();
    }

    /**
     * Add the `Surrogate-Key` header to the response.
     *
     * @return void
     */
    public function add_surrogate_key_header(): void {
        if ( ! $this->is_caching_enabled() ) {
            return;
        }

        $cache_tag_manager = new Cache_Tag_Manager( $this->cache_tag_factory, $this->web_property );
        $cache_tag_manager->add_cache_tag_header();
    }

    /**
     * Stage a cached post/page for purging, when something happens on it (update, edit, etc.).
     *
     * @param ?int $post_id       ID of the post to stage for purging.
     * @param bool $ignore_status If set to `true`, the post/page will be purged regardless of its status.
     *
     * @return void
     */
    public function stage_page_cache_for_purging( ?int $post_id = null, bool $ignore_status = false ): void {
        if ( ! $this->is_caching_enabled() ) {
            return;
        }

        $this->posts_caching->stage_post( $post_id, $ignore_status );
    }

    /**
     * Return properly formatted URIs for cache purging.
     *
     * This method will ensure that the URIs are properly formatted by:
     *
     * - Lowercasing them
     * - Getting rid of any empty values
     * - Trimming leading and/or trailing whitespace
     * - Ensuring there is exactly one leading slash
     * - Removing the scheme (http/https) if it exists
     * - Removing the host if it exists
     *
     * Accepted URIs can be in various formats (case-insensitive):
     *
     * - `https://example.com/foo/`
     * - `https://example.com/foo`
     * - `http://example.com/foo/`
     * - `http://example.com/foo`
     * - `example.com/foo/`
     * - `example.com/foo`
     * - `/foo/`
     * - `/foo
     * - `foo/`
     * - `foo`
     *
     * Returned URIs will be in the format of `/foo` or `/foo/bar`,
     * with a leading slash and no trailing slash.
     *
     * @param string[] $uris URIs to format.
     *
     * @return string[]
     */
    private function get_properly_formatted_uris( array $uris ): array {
        if ( empty( $uris ) ) {
            return array();
        }

        $formatted_uris = array();

        $site_url = get_bloginfo( 'url' );
        $scheme   = wp_parse_url( $site_url, PHP_URL_SCHEME );
        $host     = String_Utils::trailing_slash_it( wp_parse_url( $site_url, PHP_URL_HOST ) );

        foreach ( $uris as $uri ) {
            $uri = strtolower( trim( $uri ) );

            if ( empty( $uri ) ) {
                continue;
            }

            $formatted_uri = str_starts_with( $uri, $host ) ? $scheme . '://' . $uri : $uri;
            $formatted_uri = wp_parse_url( $formatted_uri, PHP_URL_PATH );
            $formatted_uri = String_Utils::leading_slash_it( String_Utils::untrailing_slash_it( $formatted_uri ) );

            $formatted_uris[] = $formatted_uri;
        }

        return $formatted_uris;
    }

    /**
     * Return an error for when caching is not enabled.
     *
     * @return WP_Error
     */
    private function get_caching_disabled_error(): WP_Error {
        return new WP_Error(
            'caching_disabled',
            __( 'Pressidium EDGE caching is not enabled.', 'pressidium-performance' ),
            array( 'status' => 500 ) // 500 Internal Server Error
        );
    }

    /**
     * Return an error for a failed cache invalidation request.
     *
     * @return WP_Error
     */
    private function get_purge_failed_error(): WP_Error {
        return new WP_Error(
            'purge_failed',
            __( 'Failed to purge cache.', 'pressidium-performance' ),
            array( 'status' => 500 ) // 500 Internal Server Error
        );
    }

    /**
     * Return a human-readable error for a rate-limited cache invalidation request.
     *
     * @param Rate_Limit_Exception $exception Rate limit exception.
     *
     * @return WP_Error
     */
    private function get_rate_limited_error( Rate_Limit_Exception $exception ): WP_Error {
        $reset_after = $exception->get_reset_after();

        if ( $reset_after !== null && $reset_after > 0 ) {
            $message = sprintf(
                /* translators: %s: Human-readable time until the purge limit resets (e.g. "5 mins"). */
                __(
                    'You have sent too many cache purge requests in a short period of time. Please try again in %s.',
                    'pressidium-performance'
                ),
                human_time_diff( time(), time() + $reset_after )
            );
        } else {
            $message = __(
                'You have sent too many cache purge requests in a short period of time. Please wait a while and try again.',
                'pressidium-performance'
            );
        }

        return new WP_Error(
            'rate_limited',
            $message,
            array( 'status' => 429 ) // 429 Too Many Requests
        );
    }

    /**
     * Send a cache invalidation request to the Pressidium EDGE API.
     *
     * @param string[]                $domains        Domains to be purged.
     * @param array<string, string[]> $surrogate_keys Surrogate keys to be purged.
     * @param string[]                $uris           URIs to be purged.
     * @param ?callable               $callback       Callback to be executed after the purge request is
     *                                                successfully sent.
     *
     * @return true|WP_Error `true` if the cache invalidation request was sent successfully, `WP_Error` otherwise.
     */
    public function send_purge_cache_request(
        array $domains,
        array $surrogate_keys,
        array $uris = array(),
        ?callable $callback = null
    ): bool|WP_Error {
        // Validations
        $has_surrogate_keys = ! empty( $surrogate_keys );
        $has_uris           = ! empty( $uris );

        if ( ! $has_surrogate_keys && ! $has_uris ) {
            // No surrogate keys or URIs to purge, bail early
            return new WP_Error(
                'nothing_to_purge',
                __( 'Please add at least one cache tag or URL to purge.', 'pressidium-performance' ),
                array( 'status' => 400 ) // 400 Bad Request
            );
        }

        if ( ! $has_surrogate_keys ) {
            $surrogate_keys = array();
        }

        if ( ! array_key_exists( 'high', $surrogate_keys ) ) {
            $surrogate_keys['high'] = array();
        }

        if ( ! array_key_exists( 'low', $surrogate_keys ) ) {
            $surrogate_keys['low'] = array();
        }

        // Get the properly formatted URIs
        $uris = $this->get_properly_formatted_uris( $uris );

        // Make sure all surrogate keys and URIs are unique
        foreach ( $surrogate_keys as $key => $value ) {
            $priority = strtolower( $key );

            if ( ! in_array( $priority, array( 'low', 'high' ), true ) ) {
                // Invalid priority, skip this surrogate key
                $this->logger->warning(
                    sprintf(
                        "Invalid cache tag priority '%s' for tag '%s', skipping",
                        esc_html( $priority ),
                        esc_html( $key )
                    )
                );
            }

            $surrogate_keys[ $priority ] = Array_Utils::dedupe_values( $value );
        }

        if ( $has_uris ) {
            $uris = Array_Utils::dedupe_values( $uris );
        }

        /*
         * At this point, we should have collected all surrogate
         * keys, URIs, and domains that need to be invalidated,
         * so go ahead and send the request.
         */
        try {
            $purge_request = ( new Purge_Request() )
                ->set_cache_keys( $surrogate_keys )
                ->set_domains( $domains )
                ->set_uris( $uris );

            $this->edge_api_client->cache()->purge( $purge_request );
        } catch ( Rate_Limit_Exception $exception ) {
            $this->logger->error( sprintf( 'Failed to purge cache: %s', esc_html( $exception->getMessage() ) ) );

            return $this->get_rate_limited_error( $exception );
        } catch ( API_Exception | RuntimeException $exception ) {
            $this->logger->error( sprintf( 'Failed to purge cache: %s', esc_html( $exception->getMessage() ) ) );

            return $this->get_purge_failed_error();
        }

        if ( is_callable( $callback ) ) {
            $callback(
                array(
                    'surrogate_keys' => $surrogate_keys,
                    'uris'           => $uris,
                    'domains'        => $domains,
                )
            );
        }

        return true;
    }

    /**
     * Purge the cache for the given surrogate keys and/or URIs.
     *
     * @param array<string, string[]> $surrogate_keys Surrogate keys to be purged.
     * @param string[]                $uris           URIs to be purged.
     *
     * @return true|WP_Error `true` if the purge request was sent successfully, `WP_Error` otherwise.
     */
    public function purge_cache( array $surrogate_keys = array(), array $uris = array() ): bool|WP_Error {
        if ( ! $this->is_caching_enabled() ) {
            return $this->get_caching_disabled_error();
        }

        $domains = Domains_Provider::get_current_site_domains();

        $result = $this->send_purge_cache_request( $domains, $surrogate_keys, $uris );

        if ( is_wp_error( $result ) ) {
            $this->logger->error(
                'Failed to purge cache',
                array(
                    'cache_tags' => $surrogate_keys,
                    'uris'       => $uris,
                )
            );

            return $result;
        }

        /**
         * Successfully sent the flush request.
         *
         * @param string[]                             $domains    Domains purged.
         * @param string[]                             $uris       URIs purged.
         * @param array{high: string[], low: string[]} $cache_tags Cache tags purged.
         */
        do_action( 'pressidium_edge_partial_cache_purged_manually', $domains, $uris, $surrogate_keys );

        return true;
    }

    /**
     * Purge all caches via the Edge API.
     *
     * @return true|WP_Error `true` if the purge request was sent successfully, `WP_Error` otherwise.
     */
    public function purge_all_caches(): bool|WP_Error {
        if ( ! $this->is_caching_enabled() ) {
            return $this->get_caching_disabled_error();
        }

        $domains = Domains_Provider::get_all_domains();

        try {
            $purge_all_request = ( new Purge_All_Request() )->set_domains( $domains );
            $this->edge_api_client->cache()->purge_all( $purge_all_request );
        } catch ( Rate_Limit_Exception $exception ) {
            $this->logger->error(
                sprintf( 'Failed to purge all caches: %s', esc_html( $exception->getMessage() ) )
            );

            return $this->get_rate_limited_error( $exception );
        } catch ( API_Exception | RuntimeException $exception ) {
            $this->logger->error(
                sprintf( 'Failed to purge all caches: %s', esc_html( $exception->getMessage() ) )
            );

            return $this->get_purge_failed_error();
        }

        /**
         * Successfully purged all caches.
         *
         * @param string[] $domains Domains purged.
         */
        do_action( 'pressidium_edge_all_caches_purged', $domains );

        return true;
    }

    /**
     * Purge the WordPress object cache.
     *
     * @return void
     */
    public function purge_site_object_cache(): void {
        wp_cache_flush();
    }

    /**
     * Return the cache objects to purge, deduplicated and grouped by priority.
     *
     * @return array<string, string[]> Cache objects to purge, grouped by priority.
     */
    private function get_cache_objects_to_purge(): array {
        $cache_objects = $this->posts_caching->get_resources_to_purge();

        if ( empty( $cache_objects ) ) {
            // No cache objects to purge
            return array();
        }

        // Map `Cache_Object` instances to value/priority arrays
        $mapped = array_map(
            function ( $cache_object ) {
                if ( ! $cache_object instanceof Cache_Object ) {
                    return $cache_object;
                }

                return array(
                    'value'    => $cache_object->get_cache_tag()->get_value(),
                    'priority' => $cache_object->get_priority(),
                );
            },
            $cache_objects
        );

        // Deduplicate by value
        $seen   = array();
        $unique = array();

        foreach ( $mapped as $item ) {
            if ( ! is_array( $item ) || ! isset( $item['value'] ) ) {
                continue;
            }

            if ( in_array( $item['value'], $seen, true ) ) {
                continue;
            }

            $seen[]   = $item['value'];
            $unique[] = $item;
        }

        $low_priority_tags  = array();
        $high_priority_tags = array();

        foreach ( $unique as $cache_object ) {
            if ( $cache_object['priority'] === 'high' ) {
                $high_priority_tags[] = $cache_object['value'];
                continue;
            }

            $low_priority_tags[] = $cache_object['value'];
        }

        return array(
            'low'  => $low_priority_tags,
            'high' => $high_priority_tags,
        );
    }

    /**
     * Purge the cache of staged pages (if any).
     *
     * @see https://developer.wordpress.org/reference/hooks/shutdown/
     * Fires just before PHP shuts down execution.
     *
     * @return void
     */
    public function purge_cache_of_staged_pages(): void {
        $cache_tags = $this->get_cache_objects_to_purge();
        $post_ids   = $this->posts_caching->get_post_ids_to_purge();

        /**
         * Filters computed cache tags that are going to be purged.
         *
         * @param array $cache_tags Cache tags to be purged.
         * @param array $post_ids   IDs of the posts/pages to be purged.
         */
        $cache_tags = apply_filters( 'pressidium_edge_purge_cache_tags', $cache_tags, $post_ids );

        /**
         * Filters computed URIs that are going to be purged.
         *
         * @param array $uris     URIs to be purged.
         * @param array $post_ids IDs of the posts to be purged.
         */
        $uris = apply_filters( 'pressidium_edge_purge_uris', array(), $post_ids );

        if ( empty( $cache_tags ) ) {
            // No cache objects to purge left after filtering, bail early
            return;
        }

        $domains = Domains_Provider::get_current_site_domains();

        $on_purge_callback = function ( $purge_data ) use ( $post_ids, $domains, $cache_tags, $uris ) {
            $purge_data = array_merge(
                array(
                    'surrogate_keys' => $cache_tags,
                    'uris'           => $uris,
                    'domains'        => $domains,
                    'post_ids'       => $post_ids,
                ),
                $purge_data
            );

            /**
             * Successfully sent the cache purge request.
             *
             * @param array $post_ids   IDs of the posts purged.
             * @param array $domains    Domains purged.
             * @param array $cache_tags Cache tags purged.
             * @param array $uris       URIs purged.
             */
            do_action(
                'pressidium_edge_partial_cache_purged',
                $purge_data['post_ids'],
                $purge_data['domains'],
                $purge_data['surrogate_keys'],
                $purge_data['uris']
            );
        };

        $result = $this->send_purge_cache_request(
            $domains,
            $cache_tags,
            $uris,
            $on_purge_callback
        );

        if ( is_wp_error( $result ) ) {
            // Failed to send the purge request, bail early
            return;
        }

        // Purge cache request sent, clear staged posts/pages and paths
        $this->posts_caching->clear_all();
    }

    /**
     * Return the actions to register.
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     *
     * @return array<string, array{0: string, 1?: int, 2?: int}>
     */
    public function get_actions(): array {
        if ( ! $this->is_caching_enabled() ) {
            return array();
        }

        return array(
            // Purge cache for a specific post when something happens to it
            'wp_trash_post'                           => array( 'stage_page_cache_for_purging' ),
            'delete_post'                             => array( 'stage_page_cache_for_purging' ),
            'clean_post_cache'                        => array( 'stage_page_cache_for_purging' ),

            // Handle post status update (before update)
            'pre_post_update'                         => array( 'handle_post_status_transition', 10, 2 ),

            // Comment-specific purging
            'wp_update_comment_count'                 => array( 'stage_page_cache_for_purging' ),

            // Theme related
            'wp_ajax_customize_save'                  => array( 'purge_all_caches' ),
            'after_switch_theme'                      => array( 'purge_all_caches' ),

            // Widget changes
            'wp_ajax_save-widget'                     => array( 'purge_all_caches' ),
            'wp_ajax_widgets-order'                   => array( 'purge_all_caches' ),
            'update_option_sidebars_widgets'          => array( 'purge_all_caches' ),

            // Plugin activate/deactivate
            'deactivated_plugin'                      => array( 'purge_all_caches' ),
            'activated_plugin'                        => array( 'purge_all_caches' ),

            // Menu changes
            'wp_update_nav_menu'                      => array( 'purge_all_caches' ),
            'wp_update_nav_menu_item'                 => array( 'purge_all_caches' ),

            // Theme customizer
            'customize_save'                          => array( 'purge_all_caches' ),

            // Third-party plugins
            'gform_after_save_form'                   => array( 'purge_all_caches' ),

            // Elementor integration
            'elementor/editor/after_save'             => array( 'elementor_purge_cache' ),
            'elementor/document/after_save'           => array( 'elementor_purge_cache' ),
            'elementor/core/files/clear_cache'        => array( 'elementor_purge_cache' ),
            'elementor/maintenance_mode/mode_changed' => array( 'elementor_purge_cache' ),
            'update_option__elementor_global_css'     => array( 'elementor_purge_cache' ),
            'delete_option__elementor_global_css'     => array( 'elementor_purge_cache' ),

            // Purge database cache when something happens that doesn't use the WordPress API
            'signup_finished'                         => array( 'purge_site_object_cache' ),
            'bp_core_clear_cache'                     => array( 'purge_site_object_cache' ),
            'bp_blogs_new_blog'                       => array( 'purge_site_object_cache' ),

            // Add surrogate key header
            'send_headers'                            => array( 'add_surrogate_key_header' ),
            'admin_init'                              => array( 'add_surrogate_key_header' ),
            'rest_api_init'                           => array( 'add_surrogate_key_header' ),

            // Send purge cache requests (if any) as the very last thing we do
            'shutdown'                                => array( 'purge_cache_of_staged_pages' ),
        );
    }

}
