<?php
/**
 * Base API class.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\API;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Hooks\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * API abstract class.
 *
 * @since 1.0.0
 */
abstract class API implements Actions {

    /**
     * API constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct( protected readonly Logger $logger ) {}

    /**
     * Register REST routes.
     *
     * @return void
     */
    abstract public function register_rest_routes(): void;

    /**
     * Return the actions to register.
     *
     * @return array<string, array{0: string, 1?: int, 2?: int}>
     */
    public function get_actions(): array {
        return array(
            'rest_api_init' => array( 'register_rest_routes' ),
        );
    }

}
