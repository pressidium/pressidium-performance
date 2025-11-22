<?php
/**
 * Null WHERE clause.
 *
 * Based on `stephenharris/wp-query-builder`.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Database\Query_Builder;

use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Null_Where_Clause class.
 *
 * @since 1.0.0
 */
class Null_Where_Clause implements Where_Clause {

    /**
     * @var string Column name.
     */
    private string $column;

    /**
     * @var string Operator.
     */
    private string $operator;

    /**
     * Null_Where_Clause constructor.
     *
     * @param string $column Column name.
     * @param string $is_null Operator. Default is `Where_Clause::IS_NULL`.
     */
    public function __construct( string $column, string $is_null = Where_Clause::IS_NULL ) {
        $this->column   = $column;
        $this->operator = $is_null;

        $this->assert_valid_operator( $is_null );
    }

    /**
     * Build the SQL for the WHERE clause.
     *
     * @return string
     */
    public function build_sql(): string {
        return implode( ' ', array( $this->column, $this->operator ) );
    }

    /**
     * Return the bindings for the WHERE clause.
     *
     * @return string[]|float[]|int[]
     */
    public function get_bindings(): array {
        return array();
    }

    /**
     * Validate the given operator.
     *
     * @throws InvalidArgumentException If the operator is not valid.
     *
     * @param string $operator Operator to validate.
     *
     * @return void
     */
    private function assert_valid_operator( string $operator ): void {
        $allowed = array(
            Where_Clause::IS_NULL,
            Where_Clause::IS_NOT_NULL,
        );

        if ( ! in_array( $operator, $allowed, true ) ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid operator for Null_Where_Clause clause. Allowed values are: %s. You gave: \'%s\'',
                    esc_html( implode( ', ', $allowed ) ),
                    esc_html( $operator )
                )
            );
        }
    }

}
