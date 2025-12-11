<?php
/**
 * Background process interface.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

use Pressidium\WP\Performance\Background_Processing\WP_Background_Process;

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
     * Return the items to process.
     *
     * @return array
     */
    abstract public function get_items(): array;

    /**
     * Return the name of the action of this background process.
     *
     * @return string
     */
    public function get_action(): string {
        return $this->action;
    }

}
