<?php
/**
 * Basic WHERE clause.
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
 * Basic_Where_Clause class.
 *
 * @since 1.0.0
 */
class Basic_Where_Clause implements Where_Clause {

    /**
     * @var string The column to compare.
     */
    private string $column;

    /**
     * @var string The operator to use for the comparison.
     */
    private string $operator;

    /**
     * @var string|float|int The value to compare.
     */
    private string|float|int $value;

    /**
     * Basic_Where_Clause constructor.
     *
     * @param string           $column   Column to compare.
     * @param string           $operator Operator to use for the comparison.
     * @param string|float|int $value    Value to compare.
     */
    public function __construct( string $column, string $operator, string|float|int $value ) {
        $this->column   = $column;
        $this->operator = $operator;
        $this->value    = $value;

        $this->assert_valid_operator( $operator );
    }

    /**
     * Build the SQL for the WHERE clause.
     *
     * @return string
     */
    public function build_sql(): string {
        return implode(
            ' ',
            array(
                $this->column,
                $this->operator,
                $this->get_placeholder( $this->value ),
            )
        );
    }

    /**
     * Return the bindings for the WHERE clause.
     *
     * @return string[]|float[]|int[]
     */
    public function get_bindings(): array {
        return array( $this->value );
    }

    /**
     * Validate the given operator.
     *
     * @throws InvalidArgumentException If the operator is invalid.
     *
     * @param string $operator Operator to validate.
     *
     * @return void
     */
    private function assert_valid_operator( string $operator ): void {
        $allowed = array(
            Where_Clause::EQUALS,
            Where_Clause::NOT_EQUALS,
            Where_Clause::GREATER,
            Where_Clause::LESS,
            Where_Clause::GREATER_EQUALS,
            Where_Clause::LESS_EQUALS,
        );

        if ( ! in_array( $operator, $allowed, true ) ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid operator for WHERE clause. Allowed values are: %s. You gave: \'%s\'',
                    esc_html( implode( ', ', $allowed ) ),
                    esc_html( $operator )
                )
            );
        }
    }

    /**
     * Return the placeholder for the given value.
     *
     * @param string|float|int $value The value to compare.
     *
     * @return string
     */
    private function get_placeholder( string|float|int $value ): string {
        if ( is_int( $value ) ) {
            return '%d';
        }

        if ( is_float( $value ) ) {
            return '%f';
        }

        return '%s';
    }

}
