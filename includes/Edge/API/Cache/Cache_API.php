<?php
/**
 * Cache API.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\API\Cache;

use Pressidium\WP\Performance\Exceptions\API\API_Exception;
use Pressidium\WP\Performance\Edge\API\API_Client;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Edge_Cache_API class.
 *
 * @since 1.1.0
 */
final class Cache_API {

    /**
     * Edge_Cache_API constructor.
     *
     * @param API_Client $api API client.
     */
    public function __construct( private readonly API_Client $api ) {}

    /**
     * Purge cache assets.
     *
     * @throws API_Exception    If an error occurs during the API request.
     * @throws RuntimeException If an unexpected error occurs during the API request.
     *
     * @param Purge_Request $request
     *
     * @return Cache_Invalidation_Response
     */
    public function purge( Purge_Request $request ): Cache_Invalidation_Response {
        return new Cache_Invalidation_Response(
            $this->api->auth_post( '/api/v1/cache/purge', $request->to_payload() )
        );
    }

    /**
     * Purge site caches.
     *
     * @throws API_Exception    If an error occurs during the API request.
     * @throws RuntimeException If an unexpected error occurs during the API request.
     *
     * @param Purge_All_Request $request Cache invalidation request.
     *
     * @return Cache_Invalidation_Response Cache invalidation response.
     */
    public function purge_all( Purge_All_Request $request ): Cache_Invalidation_Response {
        return new Cache_Invalidation_Response(
            $this->api->auth_post( '/api/v1/cache/purge_all', $request->to_payload() )
        );
    }

}
