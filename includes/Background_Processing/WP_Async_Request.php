<?php
/**
 * WP Async Request.
 *
 * Based on WP Background Processing by Delicious Brains
 *
 * @link github.com/deliciousbrains/wp-background-processing
 *
 * Modified by Konstantinos Pappas <konpap@pressidium.com>
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Background_Processing;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Abstract WP_Async_Request class.
 *
 * @abstract
 */
abstract class WP_Async_Request {

    /**
     * Prefix.
     *
     * (default value: 'pressidium')
     *
     * @var string
     * @access protected
     */
    protected string $prefix = 'pressidium';

    /**
     * Action.
     *
     * (default value: 'async_request')
     *
     * @var string
     * @access protected
     */
    protected string $action = 'async_request';

    /**
     * Identifier.
     *
     * @var mixed
     * @access protected
     */
    protected string $identifier;

    /**
     * Data.
     *
     * (default value: array())
     *
     * @var array
     * @access protected
     */
    protected array $data = array();

    /**
     * Initiate new async request.
     */
    public function __construct() {
        $this->identifier = $this->prefix . '_' . $this->action;

        add_action( 'wp_ajax_' . $this->identifier, array( $this, 'maybe_handle' ) );
        add_action( 'wp_ajax_nopriv_' . $this->identifier, array( $this, 'maybe_handle' ) );
    }

    /**
     * Set data used during the request.
     *
     * @param array $data Data.
     *
     * @return $this
     */
    public function data( array $data ): WP_Async_Request {
        $this->data = $data;

        return $this;
    }

    /**
     * Dispatch the async request.
     *
     * @return array|WP_Error|false HTTP Response array, `WP_Error` on failure, or `false` if not attempted.
     */
    public function dispatch(): array|WP_Error|false {
        $url  = add_query_arg( $this->get_query_args(), $this->get_query_url() );
        $args = $this->get_post_args();

        return wp_remote_post( esc_url_raw( $url ), $args );
    }

    /**
     * Return query args.
     *
     * @return array<string, mixed>
     */
    protected function get_query_args(): array {
        if ( property_exists( $this, 'query_args' ) ) {
            return $this->query_args;
        }

        $args = array(
            'action' => $this->identifier,
            'nonce'  => wp_create_nonce( $this->identifier ),
        );

        /**
         * Filters the query arguments used during an async request.
         *
         * @param array $args
         */
        return apply_filters( $this->identifier . '_query_args', $args );
    }

    /**
     * Return query URL.
     *
     * @return string
     */
    protected function get_query_url(): string {
        if ( property_exists( $this, 'query_url' ) ) {
            return $this->query_url;
        }

        $url = admin_url( 'admin-ajax.php' );

        /**
         * Filters the query URL used during an async request.
         *
         * @param string $url
         */
        return apply_filters( $this->identifier . '_query_url', $url );
    }

    /**
     * Return post args.
     *
     * @return array<string, mixed>
     */
    protected function get_post_args(): array {
        if ( property_exists( $this, 'post_args' ) ) {
            return $this->post_args;
        }

        $args = array(
            'timeout'   => 5,
            'blocking'  => false,
            'body'      => $this->data,
            // Passing cookies ensures request is performed as initiating user
            'cookies'   => $_COOKIE,
            // Local requests, fine to pass `false`
            'sslverify' => apply_filters( 'pressidium_performance_https_local_ssl_verify', false ),
        );

        /**
         * Filters the post arguments used during an async request.
         *
         * @param array $args
         */
        return apply_filters( $this->identifier . '_post_args', $args );
    }

    /**
     * Maybe handle a dispatched request.
     *
     * Check for correct nonce and pass to handler.
     *
     * @return void|mixed
     */
    public function maybe_handle() {
        // Don't lock up other requests while processing
        session_write_close();

        check_ajax_referer( $this->identifier, 'nonce' );

        $this->handle();

        return $this->maybe_wp_die();
    }

    /**
     * Should the process exit with wp_die?
     *
     * @param mixed $return_value What to return if filter says don't die, default is null.
     *
     * @return void|mixed
     */
    protected function maybe_wp_die( $return_value = null ) {
        /**
         * Should wp_die be used?
         *
         * @return bool
         */
        if ( apply_filters( $this->identifier . '_wp_die', true ) ) {
            wp_die();
        }

        return $return_value;
    }

    /**
     * Handle a dispatched request.
     *
     * Override this method to perform any actions required
     * during the async request.
     */
    abstract protected function handle(): void;
}
