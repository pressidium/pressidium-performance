<?php
/**
 * Query.
 *
 * Based on `stephenharris/wp-query-builder`.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Database\Query_Builder;

use InvalidArgumentException;
use BadMethodCallException;
use Exception;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Query class.
 *
 * @since 1.0.0
 */
class Query {

    /**
     * Constant representing a SELECT SQL statement.
     */
    const SELECT = 'SELECT';

    /**
     * Constant representing an INSERT SQL statement.
     */
    const INSERT = 'INSERT';

    /**
     * Constant representing a DELETE SQL statement.
     */
    const DELETE = 'DELETE';

    /**
     * Constant representing an UPDATE SQL statement.
     */
    const UPDATE = 'UPDATE';

    /**
     * @var Database Instance of `Database`.
     */
    private Database $db;

    /**
     * @var ?string Query type.
     */
    private ?string $type = null;

    /**
     * @var string[] Array of fields to select. Each item of the array is a column name.
     */
    private array $select_fields = array();

    /**
     * @var bool Whether to select DISTINCT values.
     */
    private bool $select_distinct = false;

    /**
     * @var array<string, mixed> Associate array of fields to update.
     *                           Key is the column name, value is the new value to be set.
     */
    private array $update_fields = array();

    /**
     * @var bool Whether to count found rows.
     */
    private bool $should_count_found_rows = false;

    /**
     * @var string Table name.
     */
    private string $table;

    /**
     * @var array Array of `Join_Clause` instances.
     */
    private array $joins = array();

    /**
     * @var Composite_Where_Clause Where clause.
     */
    private Composite_Where_Clause $wheres;

    /**
     * @var array Array of bindings.
     */
    private array $bindings = array();

    /**
     * @var array Array of ORDER BY clauses.
     */
    private array $order = array();

    /**
     * @var int LIMIT.
     */
    private int $limit = 0;

    /**
     * @var int OFFSET.
     */
    private int $offset = 0;

    /**
     * @var string GROUP BY.
     */
    private string $group_by = '';

    /**
     * Query constructor.
     *
     * @param Database $db Instance of `Database`.
     */
    public function __construct( Database $db ) {
        $this->db     = $db;
        $this->wheres = new Composite_Where_Clause();
    }

    /**
     * Select the given fields.
     *
     * @param string|string[] $fields Fields to select.
     *
     * @return Query
     */
    public function select( string|array $fields = '*' ): Query {
        $this->type            = self::SELECT;
        $this->select_distinct = false;
        $this->select_fields   = is_array( $fields ) ? $fields : array( $fields );

        return $this; // chainable
    }

    /**
     * Select the given DISTINCT fields.
     *
     * @noinspection PhpUnused
     *
     * @param string|string[] $fields Fields to select.
     *
     * @return Query
     */
    public function select_distinct( string|array $fields = '*' ): Query {
        $this->select( $fields );
        $this->select_distinct = true;

        return $this; // chainable
    }

    /**
     * Set the query to count the total number of rows found.
     *
     * @noinspection PhpUnused
     *
     * @return Query
     */
    public function count_found_rows(): Query {
        $this->should_count_found_rows = true;

        return $this; // chainable
    }

    /**
     * Set the query to not count the total number of rows found.
     *
     * @noinspection PhpUnused
     *
     * @return Query
     */
    public function do_not_count_found_rows(): Query {
        $this->should_count_found_rows = false;

        return $this; // chainable
    }

    /**
     * Set the name of the table to query.
     *
     * @param string $table Table name.
     *
     * @return Query
     */
    public function table( string $table ): Query {
        $this->table = $table;

        return $this; // chainable
    }

    /**
     * Alias for `table()`.
     *
     * @param string $table Table name.
     *
     * @return Query
     */
    public function from( string $table ): Query {
        return $this->table( $table ); // chainable
    }

    /**
     * Return the placeholder for the given value.
     *
     * @param string|float|int|null $value The value to compare.
     *
     * @return string
     */
    private function get_placeholder( string|float|int|null $value ): string {
        if ( is_int( $value ) ) {
            return '%d';
        }

        if ( is_float( $value ) ) {
            return '%f';
        }

        return '%s';
    }

    /**
     * Start a new transaction.
     *
     * @throws Query_Exception If the query fails.
     *
     * @return void
     */
    public function start_transaction(): void {
        $this->db->query( 'START TRANSACTION' );

        if ( ! empty( $this->db->last_error ) ) {
            throw new Query_Exception( esc_html( $this->db->last_error ) );
        }
    }

    /**
     * Commit the current transaction.
     *
     * @throws Query_Exception If the query fails.
     *
     * @return void
     */
    public function commit(): void {
        $this->db->query( 'COMMIT' );

        if ( ! empty( $this->db->last_error ) ) {
            throw new Query_Exception( esc_html( $this->db->last_error ) );
        }
    }

    /**
     * Rollback the current transaction.
     *
     * @throws Query_Exception If the query fails.
     *
     * @return void
     */
    public function rollback(): void {
        $this->db->query( 'ROLLBACK' );

        if ( ! empty( $this->db->last_error ) ) {
            throw new Query_Exception( esc_html( $this->db->last_error ) );
        }
    }

    /**
     * Insert the given data into the database.
     *
     * @throws BadMethodCallException If no table is set.
     * @throws Query_Exception        If the query fails.
     *
     * @param ?array<string, mixed> $data Data to insert.
     *
     * @return int|false Number of rows affected/inserted, or `false` on failure.
     */
    public function insert( ?array $data = null ): int|false {
        $this->type = self::INSERT;

        if ( ! $this->table ) {
            throw new BadMethodCallException(
                'No table set. Please call ->table(\'table_name\') before calling ->insert().'
            );
        }

        $placeholders = array_map( array( $this, 'get_placeholder' ), $data );
        $result       = $this->db->insert( $this->table, $data, $placeholders );

        if ( ! empty( $this->db->last_error ) ) {
            throw new Query_Exception( esc_html( $this->db->last_error ) );
        }

        return $result;
    }

    /**
     * Set the data to update.
     *
     * @param ?array<string, mixed> $data Data to update.
     *
     * @return Query
     */
    public function set( ?array $data ): Query {
        $this->update_fields = $data;

        return $this; // chainable
    }

    /**
     * Update all rows in the table.
     *
     * @throws BadMethodCallException If no table is set.
     * @throws Exception              If the SQL statement is not set.
     * @throws Query_Exception        If the query fails.
     *
     * @param ?array<string, mixed> $data Data to update.
     *
     * @return void
     *
     * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
     */
    public function update_all( ?array $data = null ): void {
        $this->type = self::UPDATE;

        if ( ! is_null( $data ) ) {
            $this->update_fields = $data;
        }

        if ( ! $this->table ) {
            throw new BadMethodCallException(
                'No table set. Please call ->table(\'table_name\') before calling ->update().'
            );
        }

        $sql = $this->build_sql_and_prepare();
        $this->db->query( $sql );

        if ( ! empty( $this->db->last_error ) ) {
            throw new Query_Exception( esc_html( $this->db->last_error ) );
        }
    }

    /**
     * Update the given data in the database.
     *
     * @throws BadMethodCallException If no table is set.
     * @throws BadMethodCallException If no where condition is set.
     * @throws Exception              If the SQL statement is not set.
     * @throws Query_Exception        If the query fails.
     *
     * @param ?array<string, mixed> $data  Data to update.
     *
     * @return void
     *
     * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
     */
    public function update( ?array $data = null ): void {
        if ( $this->wheres->is_empty() ) {
            throw new BadMethodCallException(
                'No where condition set. Please use update_all instead.'
            );
        }

        $this->update_all( $data );
    }

    /**
     * Delete all rows from the table even if no where condition is set.
     *
     * @throws BadMethodCallException If no table is set.
     * @throws Exception              If the SQL statement is not set.
     * @throws Query_Exception        If the query fails.
     *
     * @return void
     *
     * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
     */
    public function delete_all(): void {
        $this->type = self::DELETE;

        if ( ! $this->table ) {
            throw new BadMethodCallException(
                'No table set. Please call ->table(\'table_name\') before calling ->delete().'
            );
        }

        $sql = $this->build_sql_and_prepare();

        $this->db->query( $sql );

        if ( ! empty( $this->db->last_error ) ) {
            throw new Query_Exception( esc_html( $this->db->last_error ) );
        }
    }

    /**
     * Delete row(s) from the database only if a where condition is set.
     *
     * @throws BadMethodCallException If no where condition is set.
     * @throws BadMethodCallException If no table is set.
     * @throws Exception              If the SQL statement is not set.
     * @throws Query_Exception        If the query fails.
     *
     * @return void
     *
     * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
     */
    public function delete(): void {
        if ( $this->wheres->is_empty() ) {
            throw new BadMethodCallException(
                'No where condition set. Please use ->delete_all() instead.'
            );
        }

        $this->delete_all();
    }

    /**
     * Left join the given table.
     *
     * @noinspection PhpUnused
     *
     * @param string  $table         Name of the table to join.
     * @param string  $first_column  First column.
     * @param string  $operator      Operator.
     * @param ?string $second_column Second column.
     *
     * @return Query
     */
    public function left_join(
        string $table,
        string $first_column,
        string $operator = Join_Clause::USING,
        ?string $second_column = null
    ): Query {
        $this->join( Join_Clause::LEFT, $table, $first_column, $operator, $second_column );

        return $this; // chainable
    }

    /**
     * Right join the given table.
     *
     * @noinspection PhpUnused
     *
     * @param string  $table         Name of the table to join.
     * @param string  $first_column  First column.
     * @param string  $operator      Operator.
     * @param ?string $second_column Second column.
     *
     * @return Query
     */
    public function right_join(
        string $table,
        string $first_column,
        string $operator = Join_Clause::USING,
        ?string $second_column = null
    ): Query {
        $this->join( Join_Clause::RIGHT, $table, $first_column, $operator, $second_column );

        return $this; // chainable
    }

    /**
     * Inner join the given table.
     *
     * @noinspection PhpUnused
     *
     * @param string  $table         Name of the table to join.
     * @param string  $first_column  First column.
     * @param string  $operator      Operator.
     * @param ?string $second_column Second column.
     *
     * @return Query
     */
    public function inner_join(
        string $table,
        string $first_column,
        string $operator = Join_Clause::USING,
        ?string $second_column = null
    ): Query {
        $this->join( Join_Clause::INNER, $table, $first_column, $operator, $second_column );

        return $this; // chainable
    }

    /**
     * Full join the given table.
     *
     * @noinspection PhpUnused
     *
     * @param string  $table
     * @param string  $first_column
     * @param string  $operator
     * @param ?string $second_column
     *
     * @return Query
     */
    public function full_join(
        string $table,
        string $first_column,
        string $operator = Join_Clause::USING,
        ?string $second_column = null
    ): Query {
        $this->join( Join_Clause::FULL, $table, $first_column, $operator, $second_column );

        return $this; // chainable
    }

    /**
     * Join the given table using the given join type.
     *
     * @param string $type          Join type.
     * @param string $table         Name of the table to join.
     * @param string $first_column  First column.
     * @param string $operator      Operator.
     * @param string $second_column Second column.
     *
     * @return void
     */
    private function join(
        string $type,
        string $table,
        string $first_column,
        string $operator,
        string $second_column
    ): void {
        $join = new Join_Clause( $type, $table );
        $join->on( $first_column, $operator, $second_column );

        $this->joins[] = $join;
    }

    /**
     * Filter the results of the query based on a condition.
     *
     * Supports the following signatures:
     *
     * - `and_where( string $column, string $operator, mixed $value )`
     * - `and_where( string $column, mixed $value )`
     * - `and_where( Where_Clause $where_instance )`
     *
     * @param string|Where_Clause $column_or_where_obj Column name or instance of `Where_Clause`.
     * @param mixed               $operator            Operator. Defaults to `=`.
     * @param mixed               $value               Value.
     *
     * @return Query
     */
    public function and_where(
        string|Where_Clause $column_or_where_obj,
        mixed $operator = '=',
        mixed $value = null
    ): Query {
        if ( $column_or_where_obj instanceof Where_Clause ) {
            $this->wheres->and_where( $column_or_where_obj );

            return $this; // chainable
        }

        if ( func_num_args() === 2 ) {
            $value    = $operator;
            $operator = Where_Clause::EQUALS;
        }

        $this->wheres->and_where( new Basic_Where_Clause( $column_or_where_obj, $operator, $value ) );

        return $this; // chainable
    }

    /**
     * Filter the results of the query based on a condition.
     *
     * Supports the following signatures:
     *
     * - `where( string $column, string $operator, mixed $value )`
     * - `where( string $column, mixed $value )`
     * - `where( Where_Clause $where_instance )`
     *
     * @param string|Where_Clause $column_or_where_obj Column name or instance of `Where_Clause`.
     * @param mixed               $operator            Operator. Defaults to `=`.
     * @param mixed               $value               Value.
     *
     * @return Query
     */
    public function where(
        string|Where_Clause $column_or_where_obj,
        mixed $operator = '=',
        mixed $value = null
    ): Query {
        if ( func_num_args() === 2 ) {
            $value    = $operator;
            $operator = Where_Clause::EQUALS;
        }

        $this->and_where( $column_or_where_obj, $operator, $value );

        return $this; // chainable
    }

    /**
     * Filter the results of the query based on a condition.
     *
     * Supports the following signatures:
     *
     * - `or_where( string $column, string $operator, mixed $value )`
     * - `or_where( string $column, mixed $value )`
     * - `or_where( Where_Clause $where_instance )`
     *
     * @noinspection PhpUnused
     *
     * @param string|Where_Clause $column_or_where_obj Column name or instance of `Where_Clause`.
     * @param mixed               $operator            Operator. Defaults to `=`.
     * @param mixed               $value               Value.
     *
     * @return Query
     */
    public function or_where(
        string|Where_Clause $column_or_where_obj,
        mixed $operator = '=',
        mixed $value = null
    ): Query {
        if ( $column_or_where_obj instanceof Where_Clause ) {
            $this->wheres->or_where( $column_or_where_obj );

            return $this; // chainable
        }

        if ( func_num_args() === 2 ) {
            $value    = $operator;
            $operator = Where_Clause::EQUALS;
        }

        $this->wheres->or_where( new Basic_Where_Clause( $column_or_where_obj, $operator, $value ) );

        return $this; // chainable
    }


    /**
     * Filter the results of the query based on the given range of values for the given column.
     *
     * @noinspection PhpUnused
     *
     * @param string           $column  Name of the column.
     * @param string|float|int $value_1 Lower value of the range.
     * @param string|float|int $value_2 Upper value of the range.
     *
     * @return Query
     */
    public function where_between( string $column, string|float|int $value_1, string|float|int $value_2 ): Query {
        $this->wheres->and_where( new Between_Where_Clause( $column, $value_1, $value_2 ) );

        return $this; // chainable
    }

    /**
     * Filter the results of the query where the given column is `null`.
     *
     * @noinspection PhpUnused
     *
     * @param string $column Name of the column.
     *
     * @return Query
     */
    public function and_where_null( string $column ): Query {
        $this->wheres->and_where( new Null_Where_Clause( $column, Where_Clause::IS_NULL ) );

        return $this; // chainable
    }

    /**
     * Filter the results of the query where the given column is `null`.
     *
     * @noinspection PhpUnused
     *
     * @param string $column Name of the column.
     *
     * @return Query
     */
    public function or_where_null( string $column ): Query {
        $this->wheres->or_where( new Null_Where_Clause( $column, Where_Clause::IS_NULL ) );

        return $this; // chainable
    }

    /**
     * Filter the results of the query based on the given values for the given column.
     *
     * @noinspection PhpUnused
     *
     * @param string $column Name of the column.
     * @param array  $values Set of values to filter against.
     *
     * @return Query
     */
    public function where_in( string $column, array $values ): Query {
        $this->wheres->and_where( new Where_In_Clause( $column, $values ) );

        return $this; // chainable
    }

    /**
     * Filter the results of the query based on the given search term across the given field(s).
     *
     * @param string|string[] $field_or_fields Field name or array of field names.
     * @param string          $search_term     Search term.
     *
     * @return Query
     */
    public function search( string|array $field_or_fields, string $search_term ): Query {
        $search = new Composite_Where_Clause();
        $fields = is_array( $field_or_fields ) ? $field_or_fields : array( $field_or_fields );

        foreach ( $fields as $field ) {
            $search->or_where( new Where_Like_Clause( $field, $search_term ) );
        }

        $this->wheres->and_where( $search );

        return $this; // chainable
    }

    /**
     * Set the order of the results.
     *
     * @noinspection PhpUnused
     *
     * @param string $column Name of the column.
     * @param string $order  ASC or DESC.
     *
     * @return Query
     */
    public function order_by( string $column, string $order ): Query {
        $this->order = array();
        $this->then_order_by( $column, $order );

        return $this; // chainable
    }

    /**
     * Set the order of the results.
     *
     * @param string $column Name of the column.
     * @param string $order  ASC or DESC (case-insensitive).
     *
     * @return Query
     */
    public function then_order_by( string $column, string $order ): Query {
        $order         = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';
        $this->order[] = "$column $order";

        return $this; // chainable
    }

    /**
     * Set he maximum number of results to return.
     *
     * @param int $limit Maximum number of results to return.
     *
     * @return Query
     */
    public function limit( int $limit ): Query {
        $this->limit = max( 0, $limit );

        return $this; // chainable
    }

    /**
     * Set the number of results to skip before starting to return results.
     *
     * @param int $offset Number of results to skip.
     *
     * @return Query
     */
    public function offset( int $offset ): Query {
        $this->offset = max( 0, $offset );

        return $this; // chainable
    }

    /**
     * Group the results of the query by the given column.
     *
     * @noinspection PhpUnused
     *
     * @param string $column Name of the column.
     *
     * @return Query
     */
    public function group_by( string $column ): Query {
        $this->group_by = $column;

        return $this; // chainable
    }

    /**
     * Execute the query and return the results.
     *
     * @throws Exception       If the SQL statement is not set.
     * @throws Query_Exception If the query fails.
     *
     * @return ?object[]
     *
     * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
     */
    public function get(): ?array {
        $sql     = $this->build_sql_and_prepare();
        $results = $this->db->get_results( $sql );

        if ( ! empty( $this->db->last_error ) ) {
            throw new Query_Exception( esc_html( $this->db->last_error ) );
        }

        return $results;
    }

    /**
     * Execute the query and return the first result.
     *
     * @throws Exception       If the SQL statement is not set.
     * @throws Query_Exception If the query fails.
     *
     * @return mixed
     */
    public function first(): mixed {
        $results = $this->limit( 1 )->get();

        if ( count( $results ) === 0 ) {
            return null;
        }

        return array_shift( $results );
    }

    /**
     * Execute the query and return a single value.
     *
     * If the SQL result contains more than one column and/or
     * more than one row, the value in the column and row
     * specified is returned.
     *
     * @noinspection PhpUnused
     *
     * @throws Exception       If the SQL statement is not set.
     * @throws Query_Exception If the query fails.
     *
     * @return ?string Query result (as string), or `null` on failure.
     *
     * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
     */
    public function get_scalar(): ?string {
        $sql    = $this->build_sql_and_prepare();
        $scalar = $this->db->get_var( $sql );

        if ( ! empty( $this->db->last_error ) ) {
            throw new Query_Exception( esc_html( $this->db->last_error ) );
        }

        return $scalar;
    }

    /**
     * Return the value of the given column.
     *
     * @noinspection PhpUnused
     *
     * @throws Exception                If the SQL statement is not set.
     * @throws InvalidArgumentException If the column does not exist in the result set.
     *
     * @param ?string $column Name of the column.
     *
     * @return array
     *
     * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
     */
    public function get_column( ?string $column = null ): array {
        $sql     = $this->build_sql_and_prepare();
        $results = $this->db->get_results( $sql );

        $values = array();

        if ( empty( $results ) ) {
            return $values;
        }

        foreach ( $results as $result ) {
            $result_values = get_object_vars( $result );

            if ( is_null( $column ) ) {
                $values[] = array_shift( $result_values );
                continue;
            }

            if ( ! array_key_exists( $column, $result_values ) ) {
                $columns = implode( ', ', array_keys( $result_values ) );

                throw new InvalidArgumentException(
                    esc_html( "Column {$column} not found in returned set. Must be one of {$columns}." )
                );
            }

            $values[] = $result_values[ $column ];
        }

        return $values;
    }

    /**
     * Return the total number of rows in the result set.
     *
     * @noinspection PhpUnused
     *
     * @throws BadMethodCallException If `count_found_rows()` has not been called before executing the query.
     * @throws Query_Exception        If the query fails.
     *
     * @return int Total number of rows.
     */
    public function get_total_row_count(): int {
        // TODO: Support total row count where no limits have been placed

        if ( ! $this->should_count_found_rows ) {
            throw new BadMethodCallException(
                // phpcs:ignore Generic.Files.LineLength
                'get_total_row_count() can only be called if you have called ->count_found_rows() before executing the query.'
            );
        }

        $found_rows = (int) $this->db->get_var( 'SELECT FOUND_ROWS();' );

        if ( ! empty( $this->db->last_error ) ) {
            throw new Query_Exception( esc_html( $this->db->last_error ) );
        }

        return $found_rows;
    }

    /**
     * Build the SQL statement and prepare it.
     *
     * @throws Exception If the SQL statement is not set.
     *
     * @return string SQL statement.
     */
    private function build_sql_and_prepare(): string {
        $sql = $this->build_sql();

        if ( count( $this->bindings ) > 0 ) {
            $sql = $this->db->prepare( $sql, $this->bindings );
        }

        return $sql;
    }

    /**
     * Build the SQL statement.
     *
     * @throws Exception If the SQL statement is not set.
     *
     * @return string SQL statement.
     */
    private function build_sql(): string {
        $sql = '';

        switch ( $this->type ) {
            case self::SELECT:
                $found_rows = $this->should_count_found_rows && $this->limit > 0 ? 'SQL_CALC_FOUND_ROWS' : '';
                $distinct   = $this->select_distinct ? 'DISTINCT' : '';
                $parts      = array(
                    'SELECT',
                    $found_rows,
                    $distinct,
                    implode( ', ', $this->select_fields ),
                    'FROM',
                    $this->table,
                );

                $sql = implode( ' ', array_filter( $parts ) );
                break;

            case self::UPDATE:
                $sql_parts = array( 'UPDATE', $this->table, $this->build_set_sql(), $this->build_where_sql() );

                return trim( implode( ' ', $sql_parts ) ) . ';';

            case self::DELETE:
                $sql_parts = array( 'DELETE FROM', $this->table, $this->build_where_sql() );

                return trim( implode( ' ', $sql_parts ) ) . ';';

            default:
                throw new Exception(
                    'SQL statement not set. You must call ->select(), ->update(), ->insert() or ->delete()'
                );
        }

        $sql_parts = array_filter(
            array(
                $sql,
                $this->build_join_sql(),
                $this->build_where_sql(),
                $this->build_group_by_sql(),
                $this->build_order_by(),
                $this->build_limit_offset(),
            )
        );

        return implode( ' ', $sql_parts ) . ';';
    }

    /**
     * Build the JOIN SQL statement.
     *
     * @return string JOIN SQL statement.
     */
    private function build_join_sql(): string {
        if ( count( $this->joins ) === 0 ) {
            return '';
        }

        $join_sql = array();

        foreach ( $this->joins as $join ) {
            $join_sql[] = $join->build_sql();
        }

        return implode( ' ', $join_sql );
    }

    /**
     * Build the WHERE SQL statement.
     *
     * @return string WHERE SQL statement.
     */
    private function build_where_sql(): string {
        $sql = $this->wheres->build_sql();

        if ( $sql === '' ) {
            return '';
        }

        $this->bindings = array_merge( $this->bindings, $this->wheres->get_bindings() );

        return 'WHERE ' . $sql;
    }

    /**
     * Build the SET SQL statement.
     *
     * @throws BadMethodCallException If no fields to update have been set.
     *
     * @return string SET SQL statement.
     */
    private function build_set_sql(): string {
        if ( count( $this->update_fields ) === 0 ) {
            throw new BadMethodCallException(
                'update() can only be called if you have called set() or passed in an array of fields to update.'
            );
        }

        $parts    = array();
        $bindings = array();

        foreach ( $this->update_fields as $key => $value ) {
            if ( is_null( $value ) ) {
                $parts[] = "{$key} = NULL";
                continue;
            }

            $bindings[] = $value;
            $parts[]    = "{$key} = %s";
        }

        $this->bindings = array_merge( $this->bindings, $bindings );

        return 'SET ' . implode( ', ', $parts );
    }

    /**
     * Build the GROUP BY SQL statement.
     *
     * @return string GROUP BY SQL statement.
     */
    private function build_group_by_sql(): string {
        if ( empty( $this->group_by ) ) {
            return '';
        }

        return sprintf( 'GROUP BY %s', $this->group_by );
    }

    /**
     * Build the ORDER BY SQL statement.
     *
     * @return string ORDER BY SQL statement.
     */
    private function build_order_by(): string {
        if ( count( $this->order ) === 0 ) {
            return '';
        }

        return 'ORDER BY ' . implode( ', ', $this->order );
    }

    /**
     * Build the LIMIT/OFFSET SQL statement.
     *
     * @return string LIMIT/OFFSET SQL statement.
     */
    private function build_limit_offset(): string {
        if ( $this->limit <= 0 ) {
            return '';
        }

        if ( $this->offset > 0 ) {
            return sprintf( 'LIMIT %d, %d', $this->offset, $this->limit );
        }

        return sprintf( 'LIMIT %d', $this->limit );
    }

}
