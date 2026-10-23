<?php
/**
 * Headers.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Caching;

use WP_HTTP_Response;
use WP_REST_Server;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Headers class.
 *
 * @since 1.1.0
 */
class Headers {

    /**
     * @var ?Headers Singleton instance of this class.
     */
    private static ?Headers $instance = null;

    /**
     * @var array<string, string> Additional headers to send.
     */
    private array $headers = array();

    /**
     * Return the instance of this class.
     *
     * @return Headers
     */
    public static function get_instance(): Headers {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Headers constructor.
     */
    public function __construct() {
        /*
         * We use `send_headers` to ensure that headers are sent as late as possible.
         * This is to allow headers to be added later than the `parse_query` action.
         * We need this to allow the `Surrogate-Key` header, for example, to access
         * the queried object.
         *
         * Since WordPress 6.1 `send_headers` is triggered after WordPress parses
         * the query, so we can utilize `is_` functions and access the queried object.
         *
         * Action sequence for WordPress >= 6.1:
         *
         * - `wp_loaded`
         * - `parse_request`
         * - `parse_query`
         * - `pre_get_posts` - WP main query
         * - `send_headers`
         *
         * @link https://make.wordpress.org/core/2022/10/10/moving-the-send_headers-action-to-later-in-the-load/
         */
        add_action( 'send_headers', array( $this, 'send_headers' ), 100 );

        /**
         * We use `admin_init` to send headers for admin requests, since the `send_headers` action is only
         * triggered for front pages, so it won't be triggered for admin requests.
         */
        add_action( 'admin_init', array( $this, 'send_headers' ), 100 );

        /*
         * We use `rest_post_dispatch` to send headers for REST API requests.
         * This is to ensure that headers are sent for REST API requests as well,
         * since they are not handled by the `send_headers` nor `wp` actions.
         */
        add_filter( 'rest_post_dispatch', array( $this, 'send_rest_headers' ), 100, 3 );
    }

    /**
     * Add a header to the headers array.
     *
     * @param string $name  Header name.
     * @param string $value Header value.
     *
     * @return void
     */
    public function add_header( string $name, string $value ): void {
        $this->headers[ $name ] = $value;
    }

    /**
     * Remove the header with the given name.
     *
     * @param string $name Header name.
     *
     * @return void
     */
    public function remove_header( string $name ): void {
        if ( isset( $this->headers[ $name ] ) ) {
            unset( $this->headers[ $name ] );
        }
    }

    /**
     * Send any additional headers.
     *
     * @return void
     */
    public function send_headers(): void {
        if ( headers_sent() ) {
            // Headers already sent, bail early
            return;
        }

        foreach ( $this->headers as $name => $value ) {
            header( sprintf( '%s: %s', esc_attr( $name ), esc_attr( $value ) ) );
        }
    }

    /**
     * Send any additional headers for REST API requests.
     *
     * @param WP_HTTP_Response $result  Result to send to the client.
     *                                  Usually a `WP_REST_Response`.
     * @param WP_REST_Server   $server  Server instance.
     * @param WP_REST_Request  $request Request used to generate the response.
     *
     * @return WP_HTTP_Response
     */
    public function send_rest_headers(
        WP_HTTP_Response $result,
        WP_REST_Server $server,
        WP_REST_Request $request
    ): WP_HTTP_Response {
        foreach ( $this->headers as $name => $value ) {
            $result->header( $name, $value );
        }

        return $result;
    }

}
