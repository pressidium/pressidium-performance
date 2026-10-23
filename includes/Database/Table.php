<?php
/**
 * Table.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Database;

use Pressidium\WP\Performance\Database\Query_Builder\Query;
use Pressidium\WP\Performance\Database\Query_Builder\WP_Database;
use Pressidium\WP\Performance\Sanitizer;
use Pressidium\WP\Performance\Utils\Array_Utils;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Table abstract class.
 *
 * @since 1.0.0
 */
abstract class Table {

    /**
     * @var string Database character collate.
     */
    private string $charset_collate;

    /**
     * @var string The assigned WordPress table prefix for the site.
     */
    private string $prefix;

    /**
     * Table constructor.
     */
    public function __construct() {
        global $wpdb;

        $this->charset_collate = $wpdb->get_charset_collate();
        $this->prefix          = $wpdb->prefix;
    }

    /**
     * Return the table name without the prefix.
     *
     * @return string
     */
    abstract protected function get_table_name(): string;

    /**
     * Build the table schema.
     *
     * @param Blueprint $table Table's blueprint.
     *
     * @return void
     */
    abstract protected function get_table_schema( Blueprint $table ): void;

    /**
     * Return the schema version of the table.
     *
     * @return string
     */
    abstract public function get_version(): string;

    /**
     * Return the table's prefix.
     *
     * @return string
     */
    public function get_prefix(): string {
        return $this->prefix;
    }

    /**
     * Sanitize the given table name.
     *
     * @throws Exception If the table name is empty.
     *
     * @param string $table_name Table name to sanitize.
     *
     * @return string
     */
    private function sanitize_table_name( string $table_name ): string {
        if ( empty( $table_name ) ) {
            throw new Exception( 'Table name cannot be empty.' );
        }

        $table_name_sanitizer = new Sanitizer( $table_name );
        $sanitized_table_name = $table_name_sanitizer
            ->remove_accents()
            ->remove_special_characters()
            ->replace_hyphens_with_underscores()
            ->get_value();

        if ( empty( $sanitized_table_name ) ) {
            throw new Exception( 'Sanitized table name cannot be empty.' );
        }

        return $sanitized_table_name;
    }

    /**
     * Return the table's slug.
     *
     * The slug is the sanitized table's name.
     *
     * Returns an empty string if the table name is empty, or if it consists
     * solely of characters that the sanitizer strips away. Both cases can only
     * ever be caused by a table declaring an invalid name, in which case every
     * subsequent query against that table will (loudly) fail.
     *
     * @return string
     */
    public function get_table_slug(): string {
        try {
            return $this->sanitize_table_name( $this->get_table_name() );
        } catch ( Exception $exception ) {
            return '';
        }
    }

    /**
     * Check if a table exists in the database.
     *
     * @param string $table_name The name of the table to check.
     *
     * @return bool `true` if the table exists, `false` if it doesn't.
     */
    private function exists( string $table_name ): bool {
        global $wpdb;

        // Use `$wpdb->get_var()` to execute a SQL query to check for the table's existence
        $result = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) )
        );

        // If the result matches the provided table name, the table exists; otherwise, it doesn't
        return $result === $table_name;
    }

    /**
     * Create a new query instance for this table and return it.
     *
     * @return Query
     */
    public function query(): Query {
        global $wpdb;

        $query = new Query( new WP_Database( $wpdb ) );
        $query->from( $this->get_prefix() . $this->get_table_slug() );

        return $query;
    }

    /**
     * Insert a row into the table.
     *
     * Wrapper around `wpdb::insert()`.
     *
     * @link https://developer.wordpress.org/reference/classes/wpdb/insert/
     *
     * @param array<string, mixed> $data   Data to insert (in column => value pairs).
     *                                     Both $data columns and $data values should be "raw" (neither should be
     *                                     SQL escaped). Sending a `null` value will cause the column to be set to
     *                                     NULL - the corresponding format is ignored in this case.
     * @param string[]|string      $format Optional. An array of formats to be mapped to each of the values in $data.
     *                                     If `string`, that format will be used for all the values in $data.
     *                                     A format is one of `'%d'`, `'%f'`, `'%s'` (integer, float, string).
     *                                     If omitted, all values in $data will be treated as strings unless
     *                                     otherwise specified in `wpdb::$field_types`.
     *
     * @return int|false The number of rows inserted, or `false` on error.
     */
    public function insert( array $data, array|string $format = null ): int|false {
        global $wpdb;

        $wpdb->show_errors();

        return $wpdb->insert( $this->prefix . $this->get_table_slug(), $data, $format );
    }

    /**
     * Update a row in the table.
     *
     * Wrapper around `wpdb::update()`.
     *
     * @link https://developer.wordpress.org/reference/classes/wpdb/update/
     *
     * @param array<string, mixed> $data         Data to update (in column => value pairs).
     *                                           Both $data columns and $data values should be "raw" (neither should
     *                                           be SQL escaped). Sending a `null` value will cause the column to be
     *                                           set to NULL - the corresponding format is ignored in this case.
     * @param array<string, mixed> $where        A named array of WHERE clauses (in column => value pairs).
     *                                           Multiple clauses will be joined with `AND`s. Both $where columns
     *                                           and $where values should be "raw" (neither should be SQL escaped).
     *                                           Sending a `null` value will create an `IS NULL` comparison - the
     *                                           corresponding format will be ignored in this case.
     * @param string[]|string      $format       Optional. An array of formats to be mapped to each of the values
     *                                           in $data. If `string`, that format will be used for all the values
     *                                           in $data. A format is one of `'%d'`, `'%f'`, `'%s'` (integer, float,
     *                                           string). If omitted, all values in $data will be treated as strings
     *                                           unless otherwise specified in `wpdb::$field_types`.
     * @param string[]|string      $where_format Optional. An array of formats to be mapped to each of the values in
     *                                           $where. If `string`, that format will be used for all the values in
     *                                           $where. A format is one of `'%d'`, `'%f'`, `'%s'` (integer, float,
     *                                           string). If omitted, all values in $where will be treated as strings.
     *
     * @return int|false The number of rows updated, or `false` on error.
     */
    public function update(
        array $data,
        array $where,
        array|string $format = null,
        array|string $where_format = null
    ): int|false {
        global $wpdb;

        return $wpdb->update( $this->prefix . $this->get_table_slug(), $data, $where, $format, $where_format );
    }

    /**
     * Return the total number of rows in the table.
     *
     * @return int
     */
    public function get_total_number_of_rows(): int {
        global $wpdb;

        $table_name = $this->get_prefix() . $this->get_table_slug();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) );
    }

    /**
     * Return table rows for the given page and number of items per page.
     *
     * @param int $page     Page number.
     * @param int $per_page Number of items per page.
     *
     * @return array<string, mixed>
     */
    public function get_rows( int $page, int $per_page = 20 ): array {
        global $wpdb;

        $table_name = $this->get_prefix() . $this->get_table_slug();

        $offset = ( $page - 1 ) * $per_page;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d',
                $table_name,
                $per_page,
                $offset
            ),
            ARRAY_A
        );
    }

    /**
     * Clear the table.
     *
     * @return bool
     */
    public function clear(): bool {
        global $wpdb;

        $table_name = $this->get_prefix() . $this->get_table_slug();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        return (bool) $wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table_name ) );
    }

    /**
     * Run the given schema query.
     *
     * `dbDelta()` swallows errors, so migrations run their queries through this instead,
     * to make sure a failure stops the upgrade before the new table version is stored.
     *
     * @throws Exception If the query failed.
     *
     * @param string $sql Query to run.
     *
     * @return void
     */
    protected function run_schema_query( string $sql ): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->query( $sql );

        if ( $result === false ) {
            throw new Exception(
                sprintf( 'Schema query failed: %s', esc_html( $wpdb->last_error ) )
            );
        }
    }

    /**
     * Return the columns of the given table, as reported by `SHOW COLUMNS`.
     *
     * @throws Exception If the columns could not be read.
     *
     * @param string $table_name Table name, including the prefix.
     *
     * @return array<string, object> Column information, keyed by column name.
     */
    protected function get_columns( string $table_name ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
        $columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}" );

        if ( ! is_array( $columns ) || $wpdb->last_error !== '' ) {
            throw new Exception(
                sprintf(
                    'Could not read the columns of %s: %s',
                    esc_html( $table_name ),
                    esc_html( $wpdb->last_error )
                )
            );
        }

        return array_column( $columns, null, 'Field' );
    }

    /**
     * Return the names of the indexes that cover the given column, except the `PRIMARY` key.
     *
     * @throws Exception If the indexes could not be read.
     *
     * @param string $table_name  Table name, including the prefix.
     * @param string $column_name Column name.
     *
     * @return string[] Index names.
     */
    protected function get_index_names_for_column( string $table_name, string $column_name ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
        $indexes = $wpdb->get_results( "SHOW INDEX FROM {$table_name}" );

        if ( ! is_array( $indexes ) || $wpdb->last_error !== '' ) {
            throw new Exception(
                sprintf(
                    'Could not read the indexes of %s: %s',
                    esc_html( $table_name ),
                    esc_html( $wpdb->last_error )
                )
            );
        }

        $index_names = array();

        foreach ( $indexes as $index ) {
            if ( $index->Column_name === $column_name && $index->Key_name !== 'PRIMARY' ) {
                $index_names[] = $index->Key_name;
            }
        }

        return Array_Utils::dedupe_values( $index_names );
    }

    /**
     * Return the migrations that bring an existing table up to date, before `dbDelta()` runs.
     *
     * `dbDelta()` only adds columns and indexes and changes column types. It can't rename a
     * column, drop an index, or fill a new column for existing rows, so tables that need any
     * of that override this method.
     *
     * Each migration is keyed by the table version that introduced it, and only runs when
     * upgrading from a version prior to that.
     *
     * @return array<string, callable(string): void> Migrations, keyed by table version. Each one
     *                                               is given the table name, including the prefix.
     */
    protected function get_migrations(): array {
        return array();
    }

    /**
     * Run the migrations introduced after the given table version, oldest first.
     *
     * @throws Exception If a migration failed.
     *
     * @param string    $table_name   Table name, including the prefix.
     * @param string    $from_version Table version we are upgrading from.
     * @param ?callable $on_migrated  Called with the version of each migration once it succeeds.
     *
     * @return void
     */
    private function migrate( string $table_name, string $from_version, ?callable $on_migrated ): void {
        $migrations = $this->get_migrations();

        uksort(
            $migrations,
            function ( $version_a, $version_b ) {
                return version_compare( (string) $version_a, (string) $version_b );
            }
        );

        foreach ( $migrations as $version => $migration ) {
            $version = (string) $version;

            if ( version_compare( $version, $from_version, '<=' ) ) {
                // The table is already at or past this migration
                continue;
            }

            $migration( $table_name );

            if ( $on_migrated !== null ) {
                /*
                 * Recorded as soon as each migration succeeds, so a later one failing
                 * doesn't have this one run again on a table it already migrated.
                 */
                $on_migrated( $version );
            }
        }
    }

    /**
     * Actually modify the database to create the table.
     *
     * If the table already exists and we know which version it is at, the migrations
     * introduced since then run first, see {@see self::get_migrations()}. Without a known
     * version, an existing table is taken to be up to date.
     *
     * @throws Exception If the table name is empty.
     * @throws Exception If an existing table could not be migrated.
     *
     * @param ?string   $from_version Table version stored for the existing table, if any.
     * @param ?callable $on_migrated  Called with the version of each migration once it succeeds.
     *
     * @return void
     */
    public function create( ?string $from_version = null, ?callable $on_migrated = null ): void {
        if ( ! function_exists( '\dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $table_name = $this->prefix . $this->get_table_slug();

        if ( $from_version !== null && $this->exists( $table_name ) ) {
            $this->migrate( $table_name, $from_version, $on_migrated );
        }

        $schema = Schema::create(
            $table_name,
            $this->charset_collate,
            function ( Blueprint $table ) {
                $this->get_table_schema( $table );
            }
        );

        $sql = $schema->generate();

        dbDelta( $sql );
    }

}
