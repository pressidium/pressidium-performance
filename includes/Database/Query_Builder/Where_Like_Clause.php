<?php
/**
 * WHERE LIKE clause.
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

class Where_Like_Clause implements Where_Clause {

    /**
     * @var string Column name.
     */
    private string $column;

    /**
     * @var string Search term.
     */
    private string $search_term;

    /**
     * Where_Like_Clause constructor.
     *
     * @param string $column      Column name.
     * @param string $search_term Search term.
     */
    public function __construct( string $column, string $search_term ) {
        $this->column      = $column;
        $this->search_term = '%' . addcslashes( $search_term, '_%\\' ) . '%';
    }

    /**
     * Build the SQL for the WHERE clause.
     *
     * @return string
     */
    public function build_sql(): string {
        return implode( ' ', array( $this->column, 'LIKE', '%s' ) );
    }

    /**
     * Return the bindings for the WHERE clause.
     *
     * @return string[]|float[]|int[]
     */
    public function get_bindings(): array {
        return array( $this->search_term );
    }

}
