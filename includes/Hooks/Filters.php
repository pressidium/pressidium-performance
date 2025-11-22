<?php
/**
 * Filters interface.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Filters interface.
 *
 * @since 1.0.0
 */
interface Filters {

    /**
     * Return the filters to register.
     *
     * @return array<string, array{0: string, 1?: int, 2?: int}>
     */
    public function get_filters(): array;

}
