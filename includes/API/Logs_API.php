<?php
/**
 * Logs API.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\API;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Logs;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Logs_API class.
 *
 * @since 1.0.0
 */
final class Logs_API extends API {

    /**
     * Logs_API constructor.
     *
     * @param Logger $logger Logger instance.
     * @param Logs   $logs   Logs instance.
     */
    public function __construct( Logger $logger, protected readonly Logs $logs ) {
        parent::__construct( $logger );
    }

    /**
     * Handler for the `GET /pressidium-performance/v1/logs` REST route.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_logs( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Retrieving logs failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        $response = array(
            'success' => true,
            'data'    => $this->logs->get_logs(),
        );

        return rest_ensure_response( $response );
    }

    /**
     * Handler for the `DELETE /pressidium-performance/v1/logs` REST route.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function delete_logs( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Deleting logs failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        $deleted_successfully = $this->logs->clear();
        $response             = array( 'success' => $deleted_successfully );

        if ( ! $deleted_successfully ) {
            $this->logger->error( 'Could not clear the log file' );
        }

        $this->logger->info( 'Cleared logs successfully' );
        return rest_ensure_response( $response );
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_rest_routes(): void {
        $did_register_routes = register_rest_route(
            'pressidium-performance/v1',
            '/logs',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_logs' ),
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

        $did_register_routes = register_rest_route(
            'pressidium-performance/v1',
            '/logs',
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'delete_logs' ),
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

        if ( ! $did_register_routes ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'Failed to register REST routes for the Logs API' );
        }
    }

}
