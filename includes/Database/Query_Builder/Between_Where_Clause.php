<?php
/**
 * Between WHERE clause.
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
 * Between_Where_Clause class.
 *
 * @since 1.0.0
 */
class Between_Where_Clause implements Where_Clause {

    /**
     * @var string The column to compare.
     */
    private string $column;

    /**
     * @var string|float|int First value to compare.
     */
    private string|float|int $value_1;

    /**
     * @var string|float|int Second value to compare.
     */
    private string|float|int $value_2;

    /**
     * Between_Where_Clause constructor.
     *
     * @param string           $column
     * @param string|float|int $value_1
     * @param string|float|int $value_2
     */
    public function __construct( string $column, string|float|int $value_1, string|float|int $value_2 ) {
        $this->column  = $column;
        $this->value_1 = $value_1;
        $this->value_2 = $value_2;
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
                'BETWEEN',
                $this->get_placeholder( $this->value_1 ),
                'AND',
                $this->get_placeholder( $this->value_2 ),
            )
        );
    }

    /**
     * Return the bindings for the WHERE clause.
     *
     * @return string[]|float[]|int[]
     */
    public function get_bindings(): array {
        return array( $this->value_1, $this->value_2 );
    }

    /**
     * @param string|float|int $value
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
