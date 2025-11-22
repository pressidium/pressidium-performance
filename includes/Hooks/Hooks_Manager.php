<?php
/**
 * Hooks Manager.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Hooks_Manager class.
 *
 * @since 1.0.0
 */
final class Hooks_Manager {

    /**
     * Register an object.
     *
     * @param object $obj
     *
     * @return void
     */
    public function register( $obj ): void {
        if ( $obj instanceof Actions ) {
            $this->register_actions( $obj );
        }

        if ( $obj instanceof Filters ) {
            $this->register_filters( $obj );
        }
    }

    /**
     * Register the actions of the given object.
     *
     * @param object $obj
     *
     * @return void
     */
    private function register_actions( $obj ): void {
        $actions = $obj->get_actions();

        foreach ( $actions as $action_name => $action_details ) {
            $method        = $action_details[0];
            $priority      = $action_details[1] ?? 10;
            $accepted_args = $action_details[2] ?? 1;

            add_action(
                $action_name,
                array( $obj, $method ),
                $priority,
                $accepted_args
            );
        }
    }

    /**
     * Register the filters of the given object.
     *
     * @param object $obj
     *
     * @return void
     */
    private function register_filters( $obj ): void {
        $filters = $obj->get_filters();

        foreach ( $filters as $filter_name => $filter_details ) {
            $method        = $filter_details[0];
            $priority      = $filter_details[1] ?? 10;
            $accepted_args = $filter_details[2] ?? 1;

            add_filter(
                $filter_name,
                array( $obj, $method ),
                $priority,
                $accepted_args
            );
        }
    }

}
