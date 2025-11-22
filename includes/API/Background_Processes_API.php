<?php
/**
 * Background processes API.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\API;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Processor_Manager;
use Pressidium\WP\Performance\Optimizations\Image\Image_Optimization_Manager;
use Pressidium\WP\Performance\Background_Process;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

class Background_Processes_API extends API {

    /**
     * @var Background_Process[] Background processes.
     */
    private array $background_processes = array();

    /**
     * Background_Processes_API constructor.
     *
     * @param Logger                     $logger                     An instance of `Logger`.
     * @param Processor_Manager          $processor_manager          An instance of `Processor_Manager`.
     * @param Image_Optimization_Manager $image_optimization_manager An instance of `Image_Optimization_Manager`.
     */
    public function __construct(
        Logger $logger,
        Processor_Manager $processor_manager,
        Image_Optimization_Manager $image_optimization_manager
    ) {
        parent::__construct( $logger );

        $this->background_processes = array_merge(
            $processor_manager->get_background_processes(),
            $image_optimization_manager->get_background_processes()
        );
    }

    /**
     * Return the background process with the specified action.
     *
     * @throws Exception If the background process with the specified action is not found.
     *
     * @param string $action Action of the background process.
     *
     * @return Background_Process
     */
    private function get_background_process_by_action( string $action ): Background_Process {
        foreach ( $this->background_processes as $process ) {
            if ( $process->get_action() === $action ) {
                return $process;
            }
        }

        throw new Exception(
            sprintf( 'Background process with action \'%s\' not found', esc_html( $action ) )
        );
    }

    /**
     * Return all background processes.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_background_processes( WP_REST_Request $request ) {
        $response = array(
            'success' => true,
            'data'    => array_map(
                function ( $process ) {
                    return array(
                        'action'        => $process->get_action(),
                        'is_active'     => $process->is_active(),
                        'is_processing' => $process->is_processing(),
                        'is_cancelled'  => $process->is_cancelled(),
                        'is_paused'     => $process->is_paused(),
                        'is_queued'     => $process->is_queued(),
                        'items'         => $process->get_items(),
                    );
                },
                $this->background_processes
            ),
        );

        return rest_ensure_response( $response );
    }

    /**
     * Pause a background process.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function pause_background_process( WP_REST_Request $request ) {
        $nonce = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Pausing a background process failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        try {
            $action = $request->get_param( 'action' );

            $process = $this->get_background_process_by_action( $action );

            $process->pause();

            return rest_ensure_response( array( 'success' => true ) );
        } catch ( Exception $e ) {
            $this->logger->error( $e->getMessage() );

            return new WP_Error(
                'background_process_not_found',
                __( 'Background process not found.', 'pressidium-performance' ),
                array( 'status' => 404 )
            );
        }
    }

    /**
     * Resume a background process.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function resume_background_process( WP_REST_Request $request ) {
        $nonce = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Resuming a background process failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        try {
            $action = $request->get_param( 'action' );

            $process = $this->get_background_process_by_action( $action );

            $process->resume();

            return rest_ensure_response( array( 'success' => true ) );
        } catch ( Exception $e ) {
            $this->logger->error( $e->getMessage() );

            return new WP_Error(
                'background_process_not_found',
                __( 'Background process not found.', 'pressidium-performance' ),
                array( 'status' => 404 )
            );
        }
    }

    /**
     * Cancel a background process.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function cancel_background_process( WP_REST_Request $request ) {
        $nonce = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Cancelling a background process failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        try {
            $action = $request->get_param( 'action' );

            $process = $this->get_background_process_by_action( $action );

            $process->cancel();

            return rest_ensure_response( array( 'success' => true ) );
        } catch ( Exception $e ) {
            $this->logger->error( $e->getMessage() );

            return new WP_Error(
                'background_process_not_found',
                __( 'Background process not found.', 'pressidium-performance' ),
                array( 'status' => 404 )
            );
        }
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_rest_routes(): void {
        $did_register_routes = register_rest_route(
            'pressidium-performance/v1',
            '/processes',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_background_processes' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );

        $did_register_routes = $did_register_routes && register_rest_route(
            'pressidium-performance/v1',
            '/processes/pause',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'pause_background_process' ),
                'args'                => array(
                    'nonce'  => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'action' => array(
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
            '/processes/resume',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'resume_background_process' ),
                'args'                => array(
                    'nonce'  => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'action' => array(
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
            '/processes/cancel',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'cancel_background_process' ),
                'args'                => array(
                    'nonce'  => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'action' => array(
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
            $this->logger->error( 'Could not register REST route(s)' );
        }
    }

}
