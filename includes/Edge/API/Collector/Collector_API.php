<?php
/**
 * Collector API.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\API\Collector;

use Pressidium\WP\Performance\Exceptions\API\API_Exception;
use Pressidium\WP\Performance\Edge\API\API_Client;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Collector_API class.
 *
 * @since 1.2.0
 */
final class Collector_API {

    /**
     * Collector_API constructor.
     *
     * @param API_Client $api API client.
     */
    public function __construct( private readonly API_Client $api ) {}

    /**
     * Send collected data to the Edge API.
     *
     * @throws API_Exception    If an error occurs during the API request.
     * @throws RuntimeException If an unexpected error occurs during the API request.
     *
     * @param Collector_Request $request Collector request.
     *
     * @return Collector_Response
     */
    public function send( Collector_Request $request ): Collector_Response {
        return new Collector_Response(
            $this->api->auth_post( '/api/v1/collector/push', $request->to_payload() )
        );
    }

}
