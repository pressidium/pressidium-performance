<?php
/**
 * Composite WHERE clause.
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
 * Composite_Where_Clause class.
 *
 * @since 1.0.0
 */
class Composite_Where_Clause implements Where_Clause {

    /**
     * @var Where_Clause[] The WHERE clauses.
     */
    private array $where_clauses = array();

    /**
     * @var string[]|float[]|int[] The bindings for the WHERE clause.
     */
    private array $bindings = array();

    /**
     * Return whether the composite WHERE clause is empty.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return count( $this->where_clauses ) === 0;
    }

    /**
     * Add a WHERE clause with an AND operator.
     *
     * @param Where_Clause $where The WHERE clause to add.
     *
     * @return void
     */
    public function and_where( Where_Clause $where ): void {
        $this->where_clauses[] = array( 'AND', $where );
    }

    /**
     * Add a WHERE clause with an OR operator.
     *
     * @param Where_Clause $where The WHERE clause to add.
     *
     * @return void
     */
    public function or_where( Where_Clause $where ): void {
        $this->where_clauses[] = array( 'OR', $where );
    }

    /**
     * Build the SQL for the WHERE clause.
     *
     * @return string
     */
    public function build_sql(): string {
        $this->bindings = array();

        if ( count( $this->where_clauses ) === 0 ) {
            return '';
        }

        $sql = '';

        foreach ( $this->where_clauses as $i => $where_clause ) {
            if ( $i > 0 ) {
                $operator = $where_clause[0];

                $sql .= ' ' . $operator;
            }

            $where_clause_sql = $where_clause[1]->build_sql();

            if ( $where_clause[1] instanceof Composite_Where_Clause ) {
                $where_clause_sql = '(' . $where_clause_sql . ')';
            }

            $sql .= ' ' . $where_clause_sql;

            $this->bindings = array_merge( $this->bindings, $where_clause[1]->get_bindings() );
        }

        return trim( $sql );
    }

    /**
     * Return the bindings for the WHERE clause.
     *
     * @return string[]|float[]|int[]
     */
    public function get_bindings(): array {
        return $this->bindings;
    }

}
