<?php
/**
 * Pressidium integration.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Integrations;

use Pressidium\WP\Performance\Hooks\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Pressidium class.
 *
 * @since 1.0.1
 */
class Pressidium implements Actions {

    /**
     * Whether we are currently running on Pressidium.
     *
     * @return bool
     */
    private function is_pressidium(): bool {
        return isset( $_SERVER['PRESSIDIUM_PLATFORM'] ) || getenv( 'PRESSIDIUM_PLATFORM' ) !== false;
    }

    /**
     * Clear Pressidium cache.
     *
     * @return void
     */
    public function clear_pressidium_cache(): void {
        if ( ! $this->is_pressidium() || ! class_exists( '\NinukisCaching' ) ) {
            return;
        }

        $pressidium_caching = \NinukisCaching::get_instance();

        if ( ! method_exists( $pressidium_caching, 'purgeAllCaches' ) ) {
            return;
        }

        $pressidium_caching->purgeAllCaches();
    }

    /**
     * Return the actions to register.
     *
     * @return array<string, array{0: string, 1?: int, 2?: int}>
     */
    public function get_actions(): array {
        return array(
            'pressidium_performance_image_optimization_complete' => array( 'clear_pressidium_cache' ),
        );
    }

}
