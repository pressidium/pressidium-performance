<?php
/**
 * Optimization API.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\API;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Settings;
use Pressidium\WP\Performance\Optimizations\Image\Image_Factory;
use Pressidium\WP\Performance\Optimizations\Image\Media_Library;
use Pressidium\WP\Performance\Optimizations\Image\Image_Optimization_Manager;
use Pressidium\WP\Performance\Optimizations\Image\Converters\Converter_Manager;
use Pressidium\WP\Performance\Database\Tables\Optimizations_Table;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Table;
use Pressidium\WP\Performance\Optimizations\Minification\Minification_Cleaner;
use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenation_Cleaner;
use Pressidium\WP\Performance\Utils\Numeric_Utils;
use Pressidium\WP\Performance\Exceptions\Image_Conversion_Exception;
use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;
use Pressidium\WP\Performance\Storage\Storage;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Optimization_API class.
 *
 * @since 1.0.0
 */
final class Optimization_API extends API {

    /**
     * @var array Settings.
     */
    private array $settings;

    /**
     * Optimization_API constructor.
     *
     * @param Logger                     $logger
     * @param Settings                   $settings_object
     * @param Image_Factory              $image_factory
     * @param Media_Library              $media_library
     * @param Image_Optimization_Manager $image_optimization_manager
     * @param Converter_Manager          $converter_manager
     * @param Optimizations_Table        $optimizations_table
     * @param Concatenations_Table       $concatenations_table
     * @param Storage                    $transient_storage
     * @param Minification_Cleaner       $minification_cleaner
     * @param Concatenation_Cleaner      $concatenation_cleaner
     */
    public function __construct(
        Logger $logger,
        Settings $settings_object,
        private readonly Image_Factory $image_factory,
        private readonly Media_Library $media_library,
        private readonly Image_Optimization_Manager $image_optimization_manager,
        private readonly Converter_Manager $converter_manager,
        private readonly Optimizations_Table $optimizations_table,
        private readonly Concatenations_Table $concatenations_table,
        private readonly Storage $transient_storage,
        private readonly Minification_Cleaner $minification_cleaner,
        private readonly Concatenation_Cleaner $concatenation_cleaner,
    ) {
        parent::__construct( $logger );

        $this->settings = $settings_object->get();
    }

    /**
     * Handler for the `POST /pressidium-performance/v1/optimization/image/convert` REST route.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function rest_image_convert( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Image conversion failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        $attachment_id_param = Sanitizer::sanitize_text_field( $request->get_param( 'attachment_id' ) );

        if ( ! is_numeric( $attachment_id_param ) ) {
            return new WP_Error( 'invalid_attachment_id', 'Invalid attachment ID' );
        }

        $attachment_id   = intval( $attachment_id_param );
        $attachment_url  = wp_get_attachment_url( $attachment_id );
        $attachment_path = get_attached_file( $attachment_id );

        // TODO: (enhancement) Extract that to its own separate method

        try {
            $image = $this->image_factory->create( $attachment_url, $attachment_path, $attachment_id );
        } catch ( Image_Conversion_Exception $exception ) {
            $this->logger->error( 'Failed to convert image', array( 'exception' => $exception ) );

            return new WP_Error( 'image_creation_error', $exception->getMessage() );
        }

        $converter = $this->converter_manager->get_converter( $image->get_mime_type() );

        $compression_quality = apply_filters(
            'pressidium_performance_image_compression_quality',
            $this->settings['imageOptimization']['quality'] ?? 75
        );

        $image->set_compression_quality( $compression_quality );

        try {
            $converter
                ->load( $image )
                ->convert();
        } catch ( Image_Conversion_Exception $exception ) {
            $this->logger->error( 'Failed to convert image', array( 'exception' => $exception ) );

            return new WP_Error( 'image_conversion_error', $exception->getMessage() );
        }

        return new WP_REST_Response( array( 'success' => true ) );
    }

    /**
     * Handler for the `POST /pressidium-performance/v1/optimization/image/convert-all` REST route.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function rest_image_convert_all( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Image conversion failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        $attachment_ids = $this->media_library->get_all_image_attachment_ids();

        if ( empty( $attachment_ids ) ) {
            return new WP_Error(
                'no_images_found',
                __( 'No images found in the media library', 'pressidium-performance' )
            );
        }

        $this->logger->debug(
            sprintf( 'Enqueueing %d image attachments for optimization in the background', count( $attachment_ids ) )
        );
        $this->image_optimization_manager->enqueue_all( $attachment_ids );

        return new WP_REST_Response( array( 'success' => true ) );
    }

    /**
     * Handler for the `POST /pressidium-performance/v1/optimization/image/revert-all` REST route.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function rest_image_revert_all( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Image revert failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        $attachment_ids = $this->media_library->get_all_image_attachment_ids();

        if ( empty( $attachment_ids ) ) {
            return new WP_Error(
                'no_optimized_images_found',
                __( 'No optimized images found in the media library.', 'pressidium-performance' )
            );
        }

        $this->logger->debug(
            sprintf( 'Enqueueing %d optimized image attachments for revert in the background', count( $attachment_ids ) )
        );
        $this->image_optimization_manager->revert_all( $attachment_ids );

        return new WP_REST_Response( array( 'success' => true ) );
    }

    /**
     * Handler for the `GET /pressidium-performance/v1/optimization/minification/minifications` REST route.
     *
     * Paginated list of minifications.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function rest_get_minifications( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce    = $request->get_param( 'nonce' );
        $page     = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' ) ?? 10;

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Fetching minifications failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        $number_of_rows = $this->optimizations_table->get_total_number_of_rows();
        $rows           = $this->optimizations_table->get_rows( $page, $per_page );

        $data = array(
            'success' => true,
            'data'    => array_map(
                function ( $row ) {
                    $original_size  = intval( $row['original_size'] );
                    $optimized_size = intval( $row['optimized_size'] );

                    $row['size_diff'] = sprintf(
                        '%s → %s (saved %s, %s)',
                        Numeric_Utils::format_bytes_to_decimal( $original_size ),
                        Numeric_Utils::format_bytes_to_decimal( $optimized_size ),
                        Numeric_Utils::format_bytes_to_decimal( $original_size - $optimized_size ),
                        number_format( Numeric_Utils::calc_percent_diff( $original_size, $optimized_size ), 2 ) . '%'
                    );

                    return $row;
                },
                $rows
            ),
        );

        $headers = array(
            'X-WP-Total'      => $number_of_rows,
            'X-WP-TotalPages' => ceil( $number_of_rows / $per_page ),
        );

        return rest_ensure_response( new WP_REST_Response( $data, 200, $headers ) );
    }

    /**
     * Handler for the `POST /pressidium-performance/v1/optimization/minification/clear` REST route.
     *
     * Deletes all minified files, their optimization records, and any cached
     * decisions about files that should skip minification.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function rest_clear_minifications( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Clearing minified files failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        try {
            $data = $this->minification_cleaner->clear();
        } catch ( Filesystem_Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not clear minified files: %s', esc_html( $exception->getMessage() ) )
            );

            return new WP_Error(
                'minification_clear_failed',
                __( 'Could not clear minified files.', 'pressidium-performance' ),
                array( 'status' => 500 )
            );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $data,
            )
        );
    }

    /**
     * Handler for the `GET /pressidium-performance/v1/optimization/concatenation/concatenations` REST route.
     *
     * Paginated list of concatenations.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function rest_get_concatenations( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce    = $request->get_param( 'nonce' );
        $page     = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' ) ?? 10;

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Fetching concatenations failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        $number_of_rows = $this->concatenations_table->get_total_number_of_rows();
        $rows           = $this->concatenations_table->get_rows( $page, $per_page );

        $data = array(
            'success' => true,
            'data'    => array_map(
                function ( $row ) {
                    if ( ! isset( $row['original_size'] ) || ! isset( $row['optimized_size'] ) ) {
                        $row['size_diff'] = __( 'N/A', 'pressidium-performance' );

                        return $row;
                    }

                    $original_size  = intval( $row['original_size'] );
                    $optimized_size = intval( $row['optimized_size'] );

                    $percent_diff = Numeric_Utils::calc_percent_diff( $original_size, $optimized_size );
                    $no_savings   = $percent_diff > -0.01;

                    if ( $no_savings ) {
                        $row['size_diff'] = sprintf(
                            '%s',
                            Numeric_Utils::format_bytes_to_decimal( $optimized_size ),
                        );

                        return $row;
                    }

                    $row['size_diff'] = sprintf(
                        '%s → %s (saved %s, %s)',
                        Numeric_Utils::format_bytes_to_decimal( $original_size ),
                        Numeric_Utils::format_bytes_to_decimal( $optimized_size ),
                        Numeric_Utils::format_bytes_to_decimal( $original_size - $optimized_size ),
                        number_format( $percent_diff, 2 ) . '%'
                    );

                    return $row;
                },
                $rows
            ),
        );

        $headers = array(
            'X-WP-Total'      => $number_of_rows,
            'X-WP-TotalPages' => ceil( $number_of_rows / $per_page ),
        );

        return rest_ensure_response( new WP_REST_Response( $data, 200, $headers ) );
    }

    /**
     * Handler for the `POST /pressidium-performance/v1/optimization/concatenation/clear` REST route.
     *
     * Deletes all concatenated files, their concatenation records,
     * and the page to bundle mappings pointing at them.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function rest_clear_concatenations( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Clearing concatenated files failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        try {
            $data = $this->concatenation_cleaner->clear();
        } catch ( Filesystem_Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not clear concatenated files: %s', esc_html( $exception->getMessage() ) )
            );

            return new WP_Error(
                'concatenation_clear_failed',
                __( 'Could not clear concatenated files.', 'pressidium-performance' ),
                array( 'status' => 500 )
            );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $data,
            )
        );
    }

    /**
     * Handler for the `GET /pressidium-performance/v1/optimization/stats` REST route.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function rest_get_optimization_stats( WP_REST_Request $request ): WP_Error|WP_REST_Response {
        $nonce = $request->get_param( 'nonce' );

        // Validate nonce
        if ( ! wp_verify_nonce( $nonce, 'pressidium_performance_rest' ) ) {
            $this->logger->error( 'Fetching optimization stats failed due to invalid nonce' );

            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'pressidium-performance' ),
                array( 'status' => 403 )
            );
        }

        $ttl = 10 * MINUTE_IN_SECONDS;

        $minifications_size_saved = $this->transient_storage->get(
            'pressidium_performance_minifications_size_saved',
            null
        );

        if ( $minifications_size_saved === null ) {
            $minifications_size_saved = $this->optimizations_table->get_total_size_saved();

            $this->transient_storage->set(
                'pressidium_performance_minifications_size_saved',
                $minifications_size_saved,
                $ttl
            );
        }

        $concatenations_size_saved = $this->transient_storage->get(
            'pressidium_performance_concatenations_size_saved',
            null
        );

        if ( $concatenations_size_saved === null ) {
            $concatenations_size_saved = $this->concatenations_table->get_total_size_saved();

            $this->transient_storage->set(
                'pressidium_performance_concatenations_size_saved',
                $concatenations_size_saved,
                $ttl
            );
        }

        $images_size_saved = $this->transient_storage->get(
            'pressidium_performance_images_size_saved',
            null
        );

        if ( $images_size_saved === null ) {
            $images_size_saved = $this->media_library->get_total_size_saved();

            $this->transient_storage->set(
                'pressidium_performance_images_size_saved',
                $images_size_saved,
                $ttl
            );
        }

        $minified_files_count = $this->transient_storage->get(
            'pressidium_performance_minified_files_count',
            null
        );

        if ( $minified_files_count === null ) {
            $minified_files_count = $this->optimizations_table->get_total_number_of_rows();

            $this->transient_storage->set(
                'pressidium_performance_minified_files_count',
                $minified_files_count,
                $ttl
            );
        }

        $concatenated_files_count = $this->transient_storage->get(
            'pressidium_performance_concatenated_files_count',
            null
        );

        if ( $concatenated_files_count === null ) {
            $concatenated_files_count = $this->concatenations_table->get_total_files_concatenated();

            $this->transient_storage->set(
                'pressidium_performance_concatenated_files_count',
                $concatenated_files_count,
                $ttl
            );
        }

        $data = array(
            'minifications'       => array(
                'files_count'      => $minified_files_count,
                'total_size_saved' => $minifications_size_saved > 0
                    ? Numeric_Utils::format_bytes_to_decimal( $minifications_size_saved )
                    : 'N/A',
            ),
            'concatenations'      => array(
                'files_count'      => $concatenated_files_count,
                'total_size_saved' => $concatenations_size_saved > 0
                    ? Numeric_Utils::format_bytes_to_decimal( $concatenations_size_saved )
                    : 'N/A',
            ),
            'image_optimizations' => array(
                'total_size_saved' => $images_size_saved > 0
                    ? Numeric_Utils::format_bytes_to_decimal( $images_size_saved )
                    : 'N/A',
            ),
        );

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $data,
            )
        );
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_rest_routes(): void {
        $did_register_routes = register_rest_route(
            'pressidium-performance/v1',
            '/optimization/image/convert',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_image_convert' ),
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
            '/optimization/image/convert-all',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_image_convert_all' ),
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
            '/optimization/image/revert-all',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_image_revert_all' ),
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
            '/optimization/minification/minifications',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_get_minifications' ),
                'args'                => array(
                    'nonce'    => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'page'     => array(
                        'type' => 'integer',
                    ),
                    'per_page' => array(
                        'type' => 'integer',
                    ),
                ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );

        $did_register_routes = $did_register_routes && register_rest_route(
            'pressidium-performance/v1',
            '/optimization/minification/clear',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_clear_minifications' ),
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
            '/optimization/concatenation/concatenations',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_get_concatenations' ),
                'args'                => array(
                    'nonce'    => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'page'     => array(
                        'type' => 'integer',
                    ),
                    'per_page' => array(
                        'type' => 'integer',
                    ),
                ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );

        $did_register_routes = $did_register_routes && register_rest_route(
            'pressidium-performance/v1',
            '/optimization/concatenation/clear',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_clear_concatenations' ),
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
            '/optimization/stats',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_get_optimization_stats' ),
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
            ),
        );

        if ( ! $did_register_routes ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'Failed to register REST routes for the Optimization API' );
        }
    }

}
