<?php
/**
 * WHERE IN clause.
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
 * Where_In_Clause class.
 *
 * @since 1.0.0
 */
class Where_In_Clause implements Where_Clause {

    /**
     * @var string Column name.
     */
    private string $column;

    /**
     * @var string[]|float[]|int[] Values.
     */
    private array $bindings;

    /**
     * Where_In_Clause constructor.
     *
     * @param string $column Column name.
     * @param array  $values Values.
     */
    public function __construct( string $column, array $values ) {
        $this->column   = $column;
        $this->bindings = $values;
    }

    /**
     * Build the SQL for the WHERE clause.
     *
     * @return string
     */
    public function build_sql(): string {
        $in_list = '(' . implode( ', ', array_fill( 0, count( $this->bindings ), '%s' ) ) . ')';

        return implode( ' ', array( $this->column, 'IN', $in_list ) );
    }

    /**
     * Return the bindings for the WHERE clause.
     *
     * @return array|float[]|int[]|string[]
     */
    public function get_bindings(): array {
        return $this->bindings;
    }

}
