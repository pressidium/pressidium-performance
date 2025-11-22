<?php
/**
 * Where_Clause interface.
 *
 * Based on `stephenharris/wp-query-builder`.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Database\Query_Builder;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Where_Clause interface.
 *
 * @since 1.0.0
 */
interface Where_Clause {

    const EQUALS         = '=';
    const IN             = 'IN';
    const NOT_EQUALS     = '!=';
    const GREATER        = '>';
    const LESS           = '<';
    const GREATER_EQUALS = '>=';
    const LESS_EQUALS    = '<=';
    const IS_NULL        = 'IS NULL';
    const IS_NOT_NULL    = 'IS NOT NULL';

    /**
     * Build the SQL for the WHERE clause.
     *
     * @return string
     */
    public function build_sql(): string;

    /**
     * Return the bindings for the WHERE clause.
     *
     * @return string[]|float[]|int[]
     */
    public function get_bindings(): array;

}
