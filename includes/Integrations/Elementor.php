<?php
/**
 * Elementor integration.
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
 * Elementor class.
 *
 * @since 1.0.1
 */
class Elementor implements Actions {

    /**
     * Whether Elementor is active.
     *
     * @return bool
     */
    private function is_active(): bool {
        return defined( 'ELEMENTOR_VERSION' );
    }

    /**
     * Clear Elementor cache.
     *
     * @return void
     */
    public function clear_elementor_cache(): void {
        if ( ! $this->is_active() || ! class_exists( '\Elementor\Plugin' ) ) {
            return;
        }

        $elementor = \Elementor\Plugin::instance();

        if ( ! isset( $elementor->files_manager ) || ! method_exists( $elementor->files_manager, 'clear_cache' ) ) {
            return;
        }

        $elementor->files_manager->clear_cache();
    }

    /**
     * Return the actions to register.
     *
     * @return array<string, array{0: string, 1?: int, 2?: int}>
     */
    public function get_actions(): array {
        return array(
            'pressidium_performance_image_optimization_complete' => array( 'clear_elementor_cache' ),
        );
    }

}
