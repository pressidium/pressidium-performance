<?php
/**
 * Join clause.
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
 * Join_Clause class.
 *
 * @since 1.0.0
 */
class Join_Clause {

    const LEFT  = 'LEFT';
    const RIGHT = 'RIGHT';
    const INNER = 'INNER';
    const FULL  = 'FULL';
    const USING = 'USING';

    /**
     * @var string Type of the JOIN clause.
     */
    private string $type;

    /**
     * @var string Table to join.
     */
    private string $table;

    /**
     * @var ?string First column to join.
     */
    private ?string $column_1 = null;

    /**
     * @var ?string Second column to join.
     */
    private ?string $column_2 = null;

    /**
     * @var ?string Operator to use for the comparison.
     */
    private ?string $operator = null;

    /**
     * Join_Clause constructor.
     *
     * @param string $type Type of the JOIN clause.
     * @param string $table Table to join.
     */
    public function __construct( string $type, string $table ) {
        $this->assert_valid_join_type( $type );

        $this->type  = $type;
        $this->table = $table;
    }

    /**
     * Set the columns to join, and the operator for the comparison.
     *
     * @param string  $first    First column to join.
     * @param string  $operator Operator to use for the comparison.
     * @param ?string $second   Second column to join.
     *
     * @return void
     */
    public function on( string $first, string $operator = Join_Clause::USING, ?string $second = null ): void {
        $this->column_1 = $first;
        $this->column_2 = $second;

        $this->assert_valid_operator( $operator );

        $this->operator = $operator;
    }

    /**
     * Build the SQL for the JOIN clause.
     *
     * @return string
     */
    public function build_sql(): string {
        if ( $this->operator === Join_Clause::USING ) {
            $parts = array(
                $this->type,
                'JOIN',
                $this->table,
                'USING',
                $this->column_1,
            );
        } else {
            $parts = array(
                $this->type,
                'JOIN',
                $this->table,
                'ON',
                $this->column_1,
                $this->operator,
                $this->column_2,
            );
        }

        return implode( ' ', array_filter( $parts ) );
    }

    /**
     * Validate the given JOIN type.
     *
     * @throws InvalidArgumentException If the type is invalid.
     *
     * @param string $type Type to validate.
     *
     * @return void
     */
    private function assert_valid_join_type( string $type ): void {
        $allowed = array(
            Join_Clause::LEFT,
            Join_Clause::RIGHT,
            Join_Clause::INNER,
            Join_Clause::FULL,
        );

        if ( ! in_array( $type, $allowed, true ) ) {
            throw new InvalidArgumentException( sprintf(
                'Invalid JOIN type. Allowed values are: %s. You gave: \'%s\'',
                esc_html( implode( ', ', $allowed ) ),
                esc_html( $type )
            ) );
        }
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
            Join_Clause::USING,
        );

        if ( ! in_array( $operator, $allowed, true ) ) {
            throw new InvalidArgumentException( sprintf(
                'Invalid operator for ON. Allowed values are: %s. You gave: \'%s\'',
                esc_html( implode( ', ', $allowed ) ),
                esc_html( $operator )
            ) );
        }
    }

}
