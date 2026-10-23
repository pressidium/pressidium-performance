<?php
/**
 * API client.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\API;

use Pressidium\WP\Performance\Exceptions\API\Auth_Exception;
use Pressidium\WP\Performance\Exceptions\API\Unexpected_Exception;

use Pressidium\WP\Performance\Edge\Web_Property;
use Pressidium\WP\Performance\Exceptions\Encryption_Exception;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Options\Encrypted_Options;
use Pressidium\WP\Performance\Utils\String_Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * API_Client class.
 *
 * @since 1.1.0
 */
final class API_Client {

    /**
     * @var string Bearer token option key.
     */
    const BEARER_TOKEN_KEY = 'pressidium_edge_api_bearer_token';

    /**
     * API_Client constructor.
     *
     * @param Logger            $logger            Logger instance.
     * @param Encrypted_Options $encrypted_options Encrypted options instance.
     * @param Web_Property      $web_property      Web property instance.
     * @param string            $base_url          Base URL for the API.
     * @param string            $client_id         Client ID for API authentication.
     * @param string            $client_secret     Client secret for API authentication.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly Encrypted_Options $encrypted_options,
        private readonly Web_Property $web_property,
        private readonly string $base_url,
        private readonly string $client_id,
        private readonly string $client_secret
    ) {}

    /**
     * Return the API URL for the given route.
     *
     * @param string $route API route to use.
     *
     * @return string
     */
    private function get_api_url( string $route ): string {
        return sprintf(
            '%s/%s',
            String_Utils::untrailing_slash_it( $this->base_url ),
            String_Utils::unleading_slash_it( $route )
        );
    }

    /**
     * Authenticate with the API and retrieve a bearer token.
     *
     * This will also securely store the token for future requests.
     *
     * @throws Auth_Exception If authentication fails.
     *
     * @param ?string $client_id     Optional. Client ID. Omit to use the stored one.
     * @param ?string $client_secret Optional. Client secret. Omit to use the stored one.
     *
     * @return string Bearer token.
     */
    public function auth( ?string $client_id = null, ?string $client_secret = null ): string {
        $this->logger->debug( 'Authenticating with the API using a client ID and secret pair' );

        $data = array(
            'client_id'     => $client_id ?? $this->client_id,
            'client_secret' => $client_secret ?? $this->client_secret,
        );

        $headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded',
        );

        try {
            $response = $this->post( '/oauth/token', $data, $headers );
            $status   = wp_remote_retrieve_response_code( $response );

            if ( $status !== 200 ) {
                throw new Auth_Exception(
                    'Authentication failed with status code: ' . esc_html( strval( $status ) )
                );
            }

            $raw_body    = wp_remote_retrieve_body( $response );
            $parsed_body = json_decode( $raw_body, true );

            if ( ! is_array( $parsed_body ) || ! isset( $parsed_body['access_token'] ) ) {
                throw new Auth_Exception( 'Authentication response is missing access token.' );
            }

            $this->logger->debug( 'Received bearer token from API' );

            // Store the encrypted bearer token
            $access_token = $parsed_body['access_token'];

            $this->encrypted_options->set( self::BEARER_TOKEN_KEY, $access_token );

            // Store the web property environment ID (i.e. web property handle)
            $web_property_env_id = $parsed_body['web_property_env_id'];

            $this->web_property->set_web_property_handle( $web_property_env_id );

            return $access_token;
        } catch ( Encryption_Exception $exception ) {
            throw new Auth_Exception(
                'Could not store token: ' . esc_html( $exception->getMessage() )
            );
        } catch ( Unexpected_Exception $exception ) {
            throw new Auth_Exception(
                'An unexpected error occurred during authentication: ' . esc_html( $exception->getMessage() )
            );
        }
    }

    /**
     * Return the `Content-Type` from the request headers.
     *
     * @param array<string, string> $headers Request headers.
     *
     * @return Content_Type
     */
    private function get_content_type( array $headers = array() ): Content_Type {
        // Default to JSON if not specified
        return Content_Type::tryFrom(
            $headers['Content-Type'] ?? ''
        ) ?? Content_Type::JSON;
    }

    /**
     * Send a POST request to the API.
     *
     * @throws Unexpected_Exception If the request fails.
     *
     * @param string $route   Route to send the request to.
     * @param array  $data    Optional. Data to send to the API.
     * @param array  $headers Optional. Headers to send with the request.
     * @param int    $timeout Optional. Timeout for the request in seconds. Defaults to `30` seconds.
     *
     * @return array
     */
    public function post(
        string $route,
        array $data = array(),
        array $headers = array(),
        int $timeout = 30
    ): array {
        $this->logger->debug( 'Sending POST request to ' . esc_html( $route ) );

        $url          = $this->get_api_url( $route );
        $content_type = $this->get_content_type( $headers );

        $args = array(
            'timeout'     => $timeout,
            'headers'     => array_merge( array( 'Content-Type' => $content_type->value ), $headers ),
            'body'        => $content_type->encode( $data ),
            'data_format' => 'body',
        );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            /*
             * We throw an exception when something went wrong with the request
             * (e.g. network error, timeout, DNS issue, etc.). If the API returns
             * an error response (e.g. `4xx` or `5xx`), that should won't result in
             * a `WP_Error`, but rather a normal response that we can handle normally.
             */
            throw new Unexpected_Exception( esc_html( $response->get_error_message() ) );
        }

        return $response;
    }

    /**
     * Send a GET request to the API.
     *
     * @throws Unexpected_Exception If the request fails.
     *
     * @param string $route   Route to send the request to.
     * @param array  $data    Optional. Data to send to the API.
     * @param array  $headers Optional. Headers to send with the request.
     * @param int    $timeout Optional. Timeout for the request in seconds. Defaults to `30` seconds.
     *
     * @return array
     */
    public function get(
        string $route,
        array $data = array(),
        array $headers = array(),
        int $timeout = 30
    ): array {
        $this->logger->debug( 'Sending GET request to ' . esc_html( $route ) );

        $url = $this->get_api_url( $route );

        if ( ! empty( $data ) ) {
            $url = add_query_arg( $data, $url );
        }

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => $timeout,
                'headers' => array_merge( $headers ),
            )
        );

        if ( is_wp_error( $response ) ) {
            /*
             * We throw an exception when something went wrong with the request
             * (e.g. network error, timeout, DNS issue, etc.). If the API returns
             * an error response (e.g. `4xx` or `5xx`), that should won't result in
             * a `WP_Error`, but rather a normal response that we can handle normally.
             */
            throw new Unexpected_Exception( esc_html( $response->get_error_message() ) );
        }

        return $response;
    }

    /**
     * Return the bearer token for authentication.
     *
     * @return string
     */
    private function get_bearer_token(): string {
        return $this->encrypted_options->get( self::BEARER_TOKEN_KEY );
    }

    /**
     * Send a POST request to the API passing a bearer token.
     *
     * @throws Unexpected_Exception If the request fails.
     *
     * @param string $route   Route to send the request to.
     * @param array  $data    Optional. Data to send to the API.
     * @param array  $headers Optional. Headers to send to the API.
     * @param int    $timeout Optional. Timeout for the request in seconds. Defaults to `30` seconds.
     *
     * @return array
     */
    public function auth_post(
        string $route,
        array $data = array(),
        array $headers = array(),
        int $timeout = 30
    ): array {
        $this->logger->debug( 'Sending authenticated POST request to ' . esc_html( $route ) );

        $headers['Authorization'] = sprintf( 'Bearer %s', $this->get_bearer_token() );

        $response = $this->post( $route, $data, $headers, $timeout );

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code === 401 || $status_code === 403 ) {
            // Token expired or invalid, refresh it
            $this->logger->debug( 'Bearer token expired or invalid. Refreshing token.' );

            try {
                // Re-authenticate to get a new bearer token
                $this->auth();
            } catch ( Auth_Exception $exception ) {
                throw new Unexpected_Exception(
                    'Authentication failed while refreshing bearer token: ' . esc_html( $exception->getMessage() )
                );
            }

            $headers['Authorization'] = sprintf( 'Bearer %s', $this->get_bearer_token() );

            // Retry the request with the new token
            $this->logger->debug( 'Retrying request with new token' );

            return $this->post( $route, $data, $headers, $timeout );
        }

        return $response;
    }

    /**
     * Send a GET request to the API passing a bearer token.
     *
     * @throws Unexpected_Exception If the request fails.
     *
     * @param string $route   Route to send the request to.
     * @param array  $data    Optional. Data to send to the API.
     * @param array  $headers Optional. Headers to send to the API.
     * @param int    $timeout Optional. Timeout for the request in seconds. Defaults to `30` seconds.
     *
     * @return array
     */
    public function auth_get(
        string $route,
        array $data = array(),
        array $headers = array(),
        int $timeout = 30
    ): array {
        $this->logger->debug( 'Sending authenticated GET request to ' . esc_html( $route ) );

        $headers['Authorization'] = sprintf( 'Bearer %s', $this->get_bearer_token() );

        $response = $this->get( $route, $data, $headers, $timeout );

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code === 401 || $status_code === 403 ) {
            // Token expired or invalid, refresh it
            $this->logger->debug( 'Bearer token expired or invalid. Refreshing token.' );

            try {
                // Re-authenticate to get a new bearer token
                $this->auth();
            } catch ( Auth_Exception $exception ) {
                throw new Unexpected_Exception(
                    'Authentication failed while refreshing bearer token: ' . esc_html( $exception->getMessage() )
                );
            }

            $headers['Authorization'] = sprintf( 'Bearer %s', $this->get_bearer_token() );

            // Retry the request with the new token
            $this->logger->debug( 'Retrying request with new token' );

            return $this->get( $route, $data, $headers, $timeout );
        }

        return $response;
    }

}
