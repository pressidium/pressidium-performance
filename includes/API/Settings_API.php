<?php
/**
 * Settings API.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\API;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Migrator;
use Pressidium\WP\Performance\Settings;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

use const Pressidium\WP\Performance\VERSION;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

class Settings_API extends API {

    /**
     * Settings_API constructor.
     *
     * @param Logger   $logger   An instance of `Logger`.
     * @param Settings $settings An instance of `Settings`.
     */
    public function __construct( Logger $logger, private readonly Settings $settings ) {
        parent::__construct( $logger );
    }

    /**
     * Return the settings schema to sanitize the request.
     *
     * @link https://developer.wordpress.org/reference/functions/rest_sanitize_value_from_schema/
     *
     * @return array
     */
    private function get_settings_schema(): array {
        return array(
            'type'       => 'object',
            'required'   => array(
                'minification',
                'concatenation',
                'imageOptimization',
            ),
            'properties' => array(
                'minification' => array(
                    'type'       => 'object',
                    'required'   => array(
                        'minifyJS',
                        'minifyCSS',
                        'exclusions',
                    ),
                    'properties' => array(
                        'minifyJS'   => array(
                            'type' => 'boolean',
                        ),
                        'minifyCSS'  => array(
                            'type' => 'boolean',
                        ),
                        'exclusions' => array(
                            'type'       => 'object',
                            'required'   => array(
                                'js',
                                'css',
                            ),
                            'properties' => array(
                                'js'  => array(
                                    'type'  => 'array',
                                    'items' => array(
                                        'type'       => 'object',
                                        'required'   => array(
                                            'url',
                                            'is_regex',
                                        ),
                                        'properties' => array(
                                            'url'      => array(
                                                'type' => 'string',
                                            ),
                                            'is_regex' => array(
                                                'type' => 'boolean',
                                            ),
                                        ),
                                    ),
                                ),
                                'css' => array(
                                    'type'  => 'array',
                                    'items' => array(
                                        'type'       => 'object',
                                        'required'   => array(
                                            'url',
                                            'is_regex',
                                        ),
                                        'properties' => array(
                                            'url'      => array(
                                                'type' => 'string',
                                            ),
                                            'is_regex' => array(
                                                'type' => 'boolean',
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
                'concatenation' => array(
                    'type'       => 'object',
                    'required'   => array(
                        'concatenateJS',
                        'concatenateCSS',
                        'exclusions',
                    ),
                    'properties' => array(
                        'concatenateJS'  => array(
                            'type' => 'boolean',
                        ),
                        'concatenateCSS' => array(
                            'type' => 'boolean',
                        ),
                        'exclusions' => array(
                            'type'       => 'object',
                            'required'   => array(
                                'js',
                                'css',
                            ),
                            'properties' => array(
                                'js'  => array(
                                    'type'  => 'array',
                                    'items' => array(
                                        'type'       => 'object',
                                        'required'   => array(
                                            'url',
                                            'is_regex',
                                        ),
                                        'properties' => array(
                                            'url'      => array(
                                                'type' => 'string',
                                            ),
                                            'is_regex' => array(
                                                'type' => 'boolean',
                                            ),
                                        ),
                                    ),
                                ),
                                'css' => array(
                                    'type'  => 'array',
                                    'items' => array(
                                        'type'       => 'object',
                                        'required'   => array(
                                            'url',
                                            'is_regex',
                                        ),
                                        'properties' => array(
                                            'url'      => array(
                                                'type' => 'string',
                                            ),
                                            'is_regex' => array(
                                                'type' => 'boolean',
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
                'imageOptimization' => array(
                    'type'       => 'object',
                    'required'   => array(
                        'autoOptimize',
                        'keepOriginalFiles',
                        'preferredImageEditor',
                        'formats',
                        'quality',
                        'exclusions',
                    ),
                    'properties' => array(
                        'autoOptimize'         => array(
                            'type' => 'boolean',
                        ),
                        'keepOriginalFiles'    => array(
                            'type' => 'boolean',
                        ),
                        'preferredImageEditor' => array(
                            'type' => 'integer',
                        ),
                        'formats'              => array(
                            'type'       => 'object',
                            'required'   => array(
                                'image/jpeg',
                                'image/png',
                                'image/gif',
                                'image/svg+xml',
                                'image/webp',
                                'image/avif',
                                'image/heif',
                                'image/heic',
                            ),
                            'properties' => array(
                                'image/jpeg'    => array(
                                    'type'       => 'object',
                                    'required'   => array(
                                        'shouldOptimize',
                                        'convertTo',
                                    ),
                                    'properties' => array(
                                        'shouldOptimize' => array(
                                            'type' => 'boolean',
                                        ),
                                        'convertTo'      => array(
                                            'type' => 'string',
                                        ),
                                    ),
                                ),
                                'image/png'     => array(
                                    'type'       => 'object',
                                    'required'   => array(
                                        'shouldOptimize',
                                        'convertTo',
                                    ),
                                    'properties' => array(
                                        'shouldOptimize' => array(
                                            'type' => 'boolean',
                                        ),
                                        'convertTo'      => array(
                                            'type' => 'string',
                                        ),
                                    ),
                                ),
                                'image/gif'     => array(
                                    'type'       => 'object',
                                    'required'   => array(
                                        'shouldOptimize',
                                        'convertTo',
                                    ),
                                    'properties' => array(
                                        'shouldOptimize' => array(
                                            'type' => 'boolean',
                                        ),
                                        'convertTo'      => array(
                                            'type' => 'string',
                                        ),
                                    ),
                                ),
                                'image/svg+xml' => array(
                                    'type'       => 'object',
                                    'required'   => array(
                                        'shouldOptimize',
                                        'convertTo',
                                    ),
                                    'properties' => array(
                                        'shouldOptimize' => array(
                                            'type' => 'boolean',
                                        ),
                                        'convertTo'      => array(
                                            'type' => 'string',
                                        ),
                                    ),
                                ),
                                'image/webp'    => array(
                                    'type'       => 'object',
                                    'required'   => array(
                                        'shouldOptimize',
                                        'convertTo',
                                    ),
                                    'properties' => array(
                                        'shouldOptimize' => array(
                                            'type' => 'boolean',
                                        ),
                                        'convertTo'      => array(
                                            'type' => 'string',
                                        ),
                                    ),
                                ),
                                'image/avif'    => array(
                                    'type'       => 'object',
                                    'required'   => array(
                                        'shouldOptimize',
                                        'convertTo',
                                    ),
                                    'properties' => array(
                                        'shouldOptimize' => array(
                                            'type' => 'boolean',
                                        ),
                                        'convertTo'      => array(
                                            'type' => 'string',
                                        ),
                                    ),
                                ),
                                'image/heif'    => array(
                                    'type'       => 'object',
                                    'required'   => array(
                                        'shouldOptimize',
                                        'convertTo',
                                    ),
                                    'properties' => array(
                                        'shouldOptimize' => array(
                                            'type' => 'boolean',
                                        ),
                                        'convertTo'      => array(
                                            'type' => 'string',
                                        ),
                                    ),
                                ),
                                'image/heic'    => array(
                                    'type'       => 'object',
                                    'required'   => array(
                                        'shouldOptimize',
                                        'convertTo',
                                    ),
                                    'properties' => array(
                                        'shouldOptimize' => array(
                                            'type' => 'boolean',
                                        ),
                                        'convertTo'      => array(
                                            'type' => 'string',
                                        ),
                                    ),
                                ),
                            ),
                        ),
                        'quality'              => array(
                            'type' => 'integer',
                        ),
                        'exclusions'           => array(
                            'type'  => 'array',
                            'items' => array(
                                'type'       => 'object',
                                'required'   => array(
                                    'url',
                                    'is_regex',
                                ),
                                'properties' => array(
                                    'url'      => array(
                                        'type' => 'string',
                                    ),
                                    'is_regex' => array(
                                        'type' => 'boolean',
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Migrate the given settings to the latest version, if necessary.
     *
     * @param array $settings Settings to migrate.
     *
     * @return array
     */
    private function maybe_migrate( array $settings ): array {
        $migrator          = new Migrator( $settings );
        $mirgated_settings = $migrator->maybe_migrate();

        return $mirgated_settings;
    }

    /**
     * Update settings.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function update_settings( WP_REST_Request $request ) {
        $settings = $request->get_param( 'settings' );
        $nonce    = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Updating settings failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        $settings = $this->maybe_migrate( $settings );

        $settings['version']  = VERSION;

        $set_successfully = $this->settings->set( $settings );

        $response = array( 'success' => false );

        if ( ! $set_successfully ) {
            $this->logger->error( 'Could not update settings on the database' );

            return rest_ensure_response( $response );
        }

        $response['success'] = true;
        $response['data']    = $settings;

        $this->logger->info( 'Updated settings successfully' );

        return rest_ensure_response( $response );
    }

    /**
     * Return current settings.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_settings( WP_REST_Request $request ) {
        $response = array( 'success' => false );
        $settings = $this->settings->get();

        if ( empty( $settings ) ) {
            $this->logger->error( 'Could not retrieve settings from the database' );

            return rest_ensure_response( $response );
        }

        $response['success'] = true;
        $response['data']    = $this->maybe_migrate( $settings );

        return rest_ensure_response( $response );
    }

    /**
     * Delete ALL settings.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function delete_settings( WP_REST_Request $request ) {
        $nonce = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Deleting settings failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        $deleted_successfully = $this->settings->remove();
        $response             = array( 'success' => $deleted_successfully );

        if ( ! $deleted_successfully ) {
            $this->logger->error( 'Could not delete settings from the database' );
        }

        $this->logger->info( 'Reset settings successfully' );
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
            '/settings',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_settings' ),
                'permission_callback' => '__return_true',
            )
        );

        $did_register_routes = $did_register_routes && register_rest_route(
            'pressidium-performance/v1',
            '/settings',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'update_settings' ),
                'args'                => array(
                    'nonce'    => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'settings' => array(
                        'type'              => 'object',
                        'required'          => true,
                        'sanitize_callback' => function ( $param ) {
                            return rest_sanitize_value_from_schema( $param, $this->get_settings_schema() );
                        },
                    ),
                ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );

        $did_register_routes = $did_register_routes && register_rest_route(
            'pressidium-performance/v1',
            '/settings',
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'delete_settings' ),
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
            $this->logger->error( 'Could not register REST route(s)' );
        }
    }

}
