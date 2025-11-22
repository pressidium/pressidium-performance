<?php
/**
 * Raw SQL.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Database;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Raw class.
 *
 * @since 1.0.0
 */
final class Raw {

    /**
     * Raw constructor.
     *
     * @param string $sql SQL string.
     */
    public function __construct( private readonly string $sql ) {}

    /**
     * Return the SQL string.
     *
     * @return string
     */
    public function __toString(): string {
        return $this->sql;
    }

}
