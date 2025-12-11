<?php
/**
 * WPDB wrapper.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Database\Query_Builder;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * WP_Database class.
 *
 * This is an object-oriented wrapper around the WordPress wpdb class,
 * implementing the Database interface for database operations.
 *
 * It does not add any additional logic but provides a clean abstraction layer.
 * Any SQL injection prevention or data sanitization should be handled
 * before passing data to this class.
 *
 * @since 1.0.0
 */
class WP_Database implements Database {

    /**
     * @var wpdb The WordPress database object.
     */
    private wpdb $wpdb;

    /**
     * WP_Database constructor.
     *
     * @param $wpdb wpdb The WordPress database object.
     */
    public function __construct( wpdb $wpdb ) {
        $this->wpdb = $wpdb;
    }

    /**
     * Perform a database query, using the current database connection.
     *
     * @link https://developer.wordpress.org/reference/classes/wpdb/query/
     *
     * @param string $query Database query.
     *
     * @return int|bool `true` for CREATE, ALTER, TRUNCATE and DROP queries.
     *                  Number of rows affected/selected for all other queries.
     *                  `false` on error.
     */
    public function query( string $query ): int|bool {
        return $this->wpdb->query( $query );
    }

    /**
     * Retrieve an entire SQL result set from the database (i.e., many rows).
     *
     * @link https://developer.wordpress.org/reference/classes/wpdb/get_results/
     *
     * @param string $query SQL query.
     *
     * @return ?object[] Database query results as an array of rows, where each row is an object.
     */
    public function get_results( string $query ): ?array {
        return $this->wpdb->get_results( $query );
    }

    /**
     * Retrieve one value from the database.
     *
     * @link https://developer.wordpress.org/reference/classes/wpdb/get_var/
     *
     * @param string $query SQL query.
     *
     * @return ?string Database query result (as string), or `null` on failure.
     */
    public function get_var( string $query ): ?string {
        return $this->wpdb->get_var( $query );
    }

    /**
     * Insert a row into the table.
     *
     * @link https://developer.wordpress.org/reference/classes/wpdb/insert/
     *
     * @param string               $table        Table name.
     * @param array<string, mixed> $data         Data to insert (in column => value pairs).
     * @param string[]|string      $placeholders Optional. Array of `sprintf()`-like placeholders for the data.
     *
     * @return int|false The number of rows inserted, or `false` on error.
     */
    public function insert( string $table, array $data, string|array $placeholders = array(  ) ): int|false {
        return $this->wpdb->insert( $table, $data, $placeholders );
    }

    /**
     * Prepare an SQL query for safe execution.
     *
     * @link https://developer.wordpress.org/reference/classes/wpdb/prepare/
     *
     * @param string $query    SQL query with `sprintf()`-like placeholders.
     * @param mixed  $bindings Optional. Further variables to substitute into the query's placeholders.
     *
     * @return mixed Sanitized query string, if there is a query to prepare.
     */
    public function prepare( string $query, mixed $bindings ): mixed {
        return $this->wpdb->prepare( $query, $bindings );
    }

}
