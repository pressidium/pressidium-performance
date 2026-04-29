<?php
/**
 * Background process interface.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

use Pressidium\WP\Performance\Background_Processing\WP_Background_Process;
use Pressidium\WP\Performance\Logging\Logger;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Background_Process class.
 *
 * @since 1.0.0
 */
abstract class Background_Process extends WP_Background_Process {

    /**
     * Background_Process constructor.
     */
    public function __construct() {
        parent::__construct();

        add_action( $this->identifier . '_dispatch_failed', array( $this, 'on_dispatch_failed' ), 10, 2 );
        add_action( $this->identifier . '_dispatch_result', array( $this, 'on_dispatch_result' ), 10, 2 );
    }

    /**
     * Return the items to process.
     *
     * @return array
     */
    abstract public function get_items(): array;

    /**
     * Return the logger instance.
     *
     * @return Logger
     */
    abstract protected function get_logger(): Logger;

    /**
     * Return the name of the action of this background process.
     *
     * @return string
     */
    public function get_action(): string {
        return $this->action;
    }

    /**
     * Log a dispatch failure.
     *
     * Hooked to the `{identifier}_dispatch_failed` action fired by the
     * parent `WP_Background_Process` when the loopback `wp_remote_post()`
     * returns a `WP_Error`.
     *
     * @param WP_Error $error    The error returned by the dispatch attempt.
     * @param string   $chain_id The background process chain ID.
     *
     * @return void
     */
    public function on_dispatch_failed( WP_Error $error, string $chain_id ): void {
        $this->get_logger()->warning(
            sprintf(
                '[%s] Background process dispatch failed (chain: %s): %s (code: %s)',
                $this->action,
                $chain_id,
                $error->get_error_message(),
                $error->get_error_code()
            )
        );
    }

    /**
     * Log a successful dispatch.
     *
     * Hooked to the `{identifier}_dispatch_result` action fired by the
     * parent `WP_Async_Request` after every `wp_remote_post()` call.
     * Only logs on success. Failures are handled by `on_dispatch_failed()`.
     *
     * @param array|WP_Error $result The dispatch result.
     * @param string         $url    The dispatched URL.
     *
     * @return void
     */
    public function on_dispatch_result( array|WP_Error $result, string $url ): void {
        if ( is_wp_error( $result ) ) {
            // Already logged by `on_dispatch_failed()`
            return;
        }

        $this->get_logger()->debug(
            sprintf(
                '[%s] Background process dispatch initiated to %s',
                $this->action,
                $url
            )
        );
    }

}
