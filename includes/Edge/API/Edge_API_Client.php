<?php
/**
 * Pressidium EDGE API client.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\API;

use Pressidium\WP\Performance\Exceptions\API\Auth_Exception;
use Pressidium\WP\Performance\Exceptions\Decryption_Exception;

use Pressidium\WP\Performance\Edge\API\Cache\Cache_API;
use Pressidium\WP\Performance\Edge\API\Collector\Collector_API;
use Pressidium\WP\Performance\Edge\Web_Property;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Options\Encrypted_Options;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Edge_API_Client class.
 *
 * @since 1.1.0
 */
final class Edge_API_Client {

    /**
     * @var string Client ID option key.
     */
    const CLIENT_ID_KEY = 'pressidium_edge_api_client_id';

    /**
     * @var string Client secret option key.
     */
    const CLIENT_SECRET_KEY = 'pressidium_edge_api_client_secret';

    /**
     * @var API_Client $api API client.
     */
    private API_Client $api;

    /**
     * @var Cache_API $cache Cache API.
     */
    private Cache_API $cache_api;

    /**
     * @var Collector_API $collector_api Collector API.
     */
    private Collector_API $collector_api;

    /**
     * Edge_API constructor.
     *
     * @param Logger            $logger            Logger instance.
     * @param Encrypted_Options $encrypted_options Encrypted options instance.
     * @param Web_Property      $web_property      Web property instance.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly Encrypted_Options $encrypted_options,
        private readonly Web_Property $web_property,
    ) {
        $this->api = new API_Client(
            $logger,
            $encrypted_options,
            $web_property,
            $this->get_base_url(),
            $this->get_client_id(),
            $this->get_client_secret()
        );

        $this->cache_api     = new Cache_API( $this->api );
        $this->collector_api = new Collector_API( $this->api );
    }

    /**
     * Return oauth token.
     *
     * @throws Auth_Exception If authentication fails.
     *
     * @param ?string $client_id     Optional. Client ID. Omit to use the stored one.
     * @param ?string $client_secret Optional. Client Secret. Omit to use the stored one.
     *
     * @return string
     */
    public function auth( ?string $client_id = null, ?string $client_secret = null ): string {
        return $this->api->auth( $client_id, $client_secret );
    }

    /**
     * Return the Cache API instance.
     *
     * @return Cache_API
     */
    public function cache(): Cache_API {
        return $this->cache_api;
    }

    /**
     * Return the Collector API instance.
     *
     * @return Collector_API
     */
    public function collector(): Collector_API {
        return $this->collector_api;
    }

    /**
     * Return the base URL for the API.
     *
     * Can be overridden by defining the `PRESSIDIUM_PERFORMANCE_EDGE_API_URL`
     * constant in `wp-config.php`.
     *
     * @return string
     */
    private function get_base_url(): string {
        if ( defined( '\PRESSIDIUM_PERFORMANCE_EDGE_API_URL' )
            && ! empty( \PRESSIDIUM_PERFORMANCE_EDGE_API_URL ) ) {
            return \PRESSIDIUM_PERFORMANCE_EDGE_API_URL;
        }

        return 'https://edge-api.pressidium.com';
    }

    /**
     * Return the client ID for authentication.
     *
     * @return string
     */
    private function get_client_id(): string {
        try {
            return $this->encrypted_options->get( self::CLIENT_ID_KEY );
        } catch ( Decryption_Exception $exception ) {
            $this->logger->error(
                'Failed to decrypt Pressidium EDGE API client ID: ' . esc_html( $exception->getMessage() )
            );

            return '';
        }
    }

    /**
     * Return the client secret for authentication.
     *
     * @return string
     */
    private function get_client_secret(): string {
        try {
            return $this->encrypted_options->get( self::CLIENT_SECRET_KEY );
        } catch ( Decryption_Exception $exception ) {
            $this->logger->error(
                'Failed to decrypt Pressidium EDGE API client secret: ' . esc_html( $exception->getMessage() )
            );

            return '';
        }
    }

}
