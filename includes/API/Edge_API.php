<?php
/**
 * Pressidium EDGE API.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\API;

use Pressidium\WP\Performance\Exceptions\API\Auth_Exception;
use Pressidium\WP\Performance\Exceptions\Encryption_Exception;

use Pressidium\WP\Performance\Options\Encrypted_Options;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Edge\API\Edge_API_Client;
use Pressidium\WP\Performance\Edge\API\API_Client;
use Pressidium\WP\Performance\Edge\Web_Property;
use Pressidium\WP\Performance\Edge\Caching\Caching_Controller;
use Pressidium\WP\Performance\Edge\Collector\Edge_Collector_Process;
use Pressidium\WP\Performance\Storage\Transient;
use Pressidium\WP\Performance\Storage\Invalid_Argument;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Edge_API class.
 *
 * @since 1.1.0
 */
final class Edge_API extends API {

    /**
     * The minimum interval, in seconds, between consecutive collector runs.
     *
     * @var int
     */
    private const COLLECTOR_RATE_LIMIT = 15 * MINUTE_IN_SECONDS;

    /**
     * The transient key used to enforce the collector rate limit.
     *
     * @var string
     */
    private const COLLECTOR_RATE_LIMIT_KEY = 'pressidium_collector_last_run';

    /**
     * Edge_API constructor.
     *
     * @param Logger                 $logger             Logger instance.
     * @param Encrypted_Options      $encrypted_options  Encrypted options instance.
     * @param Edge_API_Client        $edge_api_client    API client instance.
     * @param Caching_Controller     $caching_controller Caching controller instance.
     * @param Edge_Collector_Process $collector_process  Background-process collector runner.
     * @param Web_Property           $web_property       Web property.
     * @param Transient              $transient          Transient storage instance.
     */
    public function __construct(
        Logger $logger,
        private readonly Encrypted_Options $encrypted_options,
        private readonly Edge_API_Client $edge_api_client,
        private readonly Caching_Controller $caching_controller,
        private readonly Edge_Collector_Process $collector_process,
        private readonly Web_Property $web_property,
        private readonly Transient $transient
    ) {
        parent::__construct( $logger );
    }

    /**
     * Whether this installation is connected to Pressidium EDGE or not.
     *
     * @return bool
     */
    private function is_connected_to_edge(): bool {
        return ! empty( $this->web_property->get_web_property_handle() );
    }

    /**
     * Handler for the `GET /pressidium-performance/v1/edge/credentials` REST route.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function has_credentials( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Checking for Pressidium EDGE credentials failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Could not pass the security check.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        $has_client_id     = $this->encrypted_options->has( Edge_API_Client::CLIENT_ID_KEY );
        $has_client_secret = $this->encrypted_options->has( Edge_API_Client::CLIENT_SECRET_KEY );

        $response = array(
            'success'         => true,
            'has_credentials' => $has_client_id && $has_client_secret,
        );

        return rest_ensure_response( $response );
    }

    /**
     * Handler for the `POST /pressidium-performance/v1/edge/credentials` REST route.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function update_credentials( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce         = $request->get_param( 'nonce' );
        $client_id     = $request->get_param( 'client_id' );
        $client_secret = $request->get_param( 'client_secret' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Updating Pressidium EDGE credentials failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Could not pass the security check.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        try {
            // Validate oauth credentials by attempting to fetch an oauth token
            $token = $this->edge_api_client->auth( $client_id, $client_secret );
        } catch ( Auth_Exception $exception ) {
            $this->logger->error(
                'Pressidium EDGE API authentication failed: ' . esc_html( $exception->getMessage() )
            );

            return new WP_Error(
                'invalid_credentials',
                __( 'Could not validate the provided credentials.', 'pressidium-performance' ),
                array( 'status' => 400 )
            );
        }

        try {
            // Store encrypted client ID, client secret, and oauth token
            $this->encrypted_options->set( Edge_API_Client::CLIENT_ID_KEY, $client_id );
            $this->encrypted_options->set( Edge_API_Client::CLIENT_SECRET_KEY, $client_secret );
            $this->encrypted_options->set( API_Client::BEARER_TOKEN_KEY, $token );
        } catch ( Encryption_Exception $exception ) {
            $this->logger->error(
                'Could not encrypt Pressidium EDGE API credentials: ' . esc_html( $exception->getMessage() )
            );

            return new WP_Error(
                'could_not_update_credentials',
                __( 'Failed to store credentials.', 'pressidium-performance' ),
                array( 'status' => 500 )
            );
        }

        $response = array( 'success' => true );

        return rest_ensure_response( $response );
    }

    /**
     * Handler for the `DELETE /pressidium-performance/v1/edge/credentials` REST route.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function delete_credentials( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce = $request->get_param( 'nonce' );

        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Deleting Pressidium EDGE credentials failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Could not pass the security check.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        // Remove stored client ID, client secret, and oauth token
        $did_delete = $this->encrypted_options->remove( Edge_API_Client::CLIENT_ID_KEY );
        $did_delete = $did_delete && $this->encrypted_options->remove( Edge_API_Client::CLIENT_SECRET_KEY );
        $did_delete = $did_delete && $this->encrypted_options->remove( API_Client::BEARER_TOKEN_KEY );

        if ( ! $did_delete ) {
            $this->logger->error( 'Could not delete Pressidium EDGE API credentials: Unknown error' );

            return new WP_Error(
                'could_not_delete_credentials',
                __( 'Failed to delete credentials.', 'pressidium-performance' ),
                array( 'status' => 500 )
            );
        }

        try {
            $this->transient->delete( self::COLLECTOR_RATE_LIMIT_KEY );
        } catch ( Invalid_Argument $exception ) {
            // Gracefully handle exception, just log it and continue
            $this->logger->warning(
                sprintf(
                    'Could not delete collector rate limit transient after deleting credentials: %s',
                    esc_html( $exception->getMessage() )
                )
            );
        }

        $response = array( 'success' => true );

        return rest_ensure_response( $response );
    }

    /**
     * Handler for the `POST /pressidium-performance/v1/edge/cache/purge-all` REST route.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function purge_all( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce = $request->get_param( 'nonce' );

        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Purging the entire Pressidium EDGE cache failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Could not pass the security check.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        $result = $this->caching_controller->purge_all_caches();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $this->logger->info( 'Successfully requested purge of the entire Pressidium EDGE cache' );

        $response = array( 'success' => true );

        return rest_ensure_response( $response );
    }

    /**
     * Handler for the `POST /pressidium-performance/v1/edge/cache/purge` REST route.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function purge( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce      = $request->get_param( 'nonce' );
        $cache_tags = $request->get_param( 'cache_tags' );
        $uris       = $request->get_param( 'uris' );

        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Purging specific Pressidium EDGE cache assets failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Could not pass the security check.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        $surrogate_keys = array();

        if ( is_array( $cache_tags ) && ! empty( $cache_tags ) ) {
            $surrogate_keys['high'] = $cache_tags;
        }

        $result = $this->caching_controller->purge_cache(
            $surrogate_keys,
            is_array( $uris ) ? $uris : array()
        );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( 'Failed to purge specific Pressidium EDGE assets' );

            return $result;
        }

        $this->logger->info(
            'Successfully requested to purge assets from the Pressidium EDGE cache',
            array(
                'cache_tags' => $cache_tags,
                'uris'       => $uris,
            )
        );

        $response = array( 'success' => true );

        return rest_ensure_response( $response );
    }

    /**
     * Handler for the `POST /pressidium-performance/v1/edge/run-collector` REST route.
     *
     * Dispatches a new collector run. Actual data collection and the
     * EDGE API POST happen across multiple background-process requests.
     *
     * This endpoint returns as soon as the run is enqueued.
     *
     * The endpoint is publicly accessible. It is guarded by:
     *
     * - A conflict check that refuses to start a new run while one is already running.
     * - A 15-minute rate limit transient that prevents rapid re-triggers. The limit
     *   is set only after a run has been successfully dispatched so failed attempts
     *   do not eat the cooldown window.
     *
     * Responses:
     *
     * - `202` Accepted. The run was enqueued, the body carries its `run_id`.
     * - `409` Conflict. A collector process is already running.
     * - `429` Too Many Requests. Still within the 15-minute cooldown.
     * - `500` Internal Server Error. Not connected to EDGE, or the run could not be dispatched.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function run_collector( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        if ( ! $this->is_connected_to_edge() ) {
            return new WP_Error(
                'not_connected_to_edge',
                __(  'Installation is not connected to Pressidium EDGE.', 'pressidium-performance' ),
                array( 'status' => 500 ) // 500 Internal Server Error
            );
        }

        if ( $this->collector_process->is_active() ) {
            return new WP_Error(
                'collector_already_running',
                __( 'A collector process is already running.', 'pressidium-performance' ),
                array( 'status' => 409 ) // 409 Conflict
            );
        }

        $is_rate_limited = false;

        try {
            $is_rate_limited = $this->transient->has( self::COLLECTOR_RATE_LIMIT_KEY );
        } catch ( Invalid_Argument $exception ) {
            /*
             * Gracefully handle exception, just log it and continue.
             * The conflict check above still prevents concurrent runs.
             */
            $this->logger->warning(
                sprintf(
                    'Could not read the collector rate limit transient: %s',
                    esc_html( $exception->getMessage() )
                )
            );
        }

        if ( $is_rate_limited ) {
            return new WP_Error(
                'rate_limited',
                __( 'The collector has already run recently. Please try again later.', 'pressidium-performance' ),
                array( 'status' => 429 ) // 429 Too Many Requests
            );
        }

        $run_id = $this->collector_process->start_run();

        if ( $run_id === null ) {
            return new WP_Error(
                'collector_failed',
                __( 'Failed to dispatch a collector run.', 'pressidium-performance' ),
                array( 'status' => 500 ) // 500 Internal Server Error
            );
        }

        try {
            $this->transient->set( self::COLLECTOR_RATE_LIMIT_KEY, time(), self::COLLECTOR_RATE_LIMIT );
        } catch ( Invalid_Argument $exception ) {
            /*
             * Gracefully handle exception, just log it and continue.
             * The run has already been dispatched, so we still respond with `202`.
             */
            $this->logger->warning(
                sprintf(
                    'Could not set the collector rate limit transient: %s',
                    esc_html( $exception->getMessage() )
                )
            );
        }

        $this->logger->info( sprintf( 'Dispatched collector run %s via REST API.', $run_id ) );

        $response = rest_ensure_response(
            array(
                'success' => true,
                'run_id'  => $run_id,
            )
        );

        $response->set_status( 202 ); // Accepted

        return $response;
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_rest_routes(): void {
        $did_register_routes = register_rest_route(
            'pressidium-performance/v1',
            '/edge/credentials',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'has_credentials' ),
                'args'                => array(
                    'nonce' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );

        $did_register_routes = $did_register_routes && register_rest_route(
            'pressidium-performance/v1',
            '/edge/credentials',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'update_credentials' ),
                'args'                => array(
                    'nonce'         => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'client_id'     => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'client_secret' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );

        $did_register_routes = $did_register_routes && register_rest_route(
            'pressidium-performance/v1',
            '/edge/credentials',
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'delete_credentials' ),
                'args'                => array(
                    'nonce' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );

        $did_register_routes = $did_register_routes && register_rest_route(
            'pressidium-performance/v1',
            '/edge/cache/purge-all',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'purge_all' ),
                'args'                => array(
                    'nonce' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );

        $did_register_routes = $did_register_routes && register_rest_route(
            'pressidium-performance/v1',
            '/edge/cache/purge',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'purge' ),
                'args'                => array(
                    'nonce'      => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'cache_tags' => array(
                        'type'     => 'array',
                        'required' => false,
                        'default'  => array(),
                        'items'    => array(
                            'type' => 'string',
                        ),
                    ),
                    'uris'       => array(
                        'type'     => 'array',
                        'required' => false,
                        'default'  => array(),
                        'items'    => array(
                            'type' => 'string',
                        ),
                    ),
                ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );

        $did_register_routes = $did_register_routes && register_rest_route(
            'pressidium-performance/v1',
            '/edge/run-collector',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'run_collector' ),
                'permission_callback' => '__return_true',
            )
        );

        if ( ! $did_register_routes ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'Failed to register REST routes for the Pressidium EDGE API' );
        }
    }

}
