<?php
/**
 * Database collector.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector\Collectors\Database;

use Pressidium\WP\Performance\Edge\Collector\Collector;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Database_Collector class.
 *
 * Collects data related to the WordPress database.
 *
 * @since 1.2.0
 */
final class Database_Collector implements Collector {

    /**
     * Return the collector name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'database';
    }

    /**
     * Collect database data.
     *
     * @return array<string, mixed>
     */
    public function collect(): array {
        return array(
            'name'           => $this->get_name_of_database(),
            'server_version' => $this->get_server_version(),
            'tables'         => $this->get_tables(),
            'table_prefix'   => $this->get_table_prefix(),
            'charset'        => $this->get_charset(),
            'collation'      => $this->get_collation(),
        );
    }

    /**
     * Return the database name.
     *
     * @return string
     */
    private function get_name_of_database(): string {
        global $wpdb;

        return $wpdb->dbname;
    }

    /**
     * Return the database server version.
     *
     * @return string
     */
    private function get_server_version(): string {
        global $wpdb;

        return $wpdb->db_version();
    }

    /**
     * Return the table names, their sizes in bytes, and estimated row counts.
     *
     * @link https://dev.mysql.com/doc/refman/9.6/en/information-schema-tables-table.html
     *
     * @return array<int, array{name: string, size: int, rows: int}>
     */
    private function get_tables(): array {
        global $wpdb;

        /*
         * Columns in `information_schema.tables` that represent table statistics
         * hold cached values. So fetching size and row counts is a cheap metadata
         * lookup rather than a full scan per table.
         *
         * Note: `table_rows` is an InnoDB approximation, not an exact count.
         */
        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT
                     table_name AS name,
                     (data_length + index_length) AS size_bytes,
                     data_length AS data_bytes,
                     index_length AS index_bytes,
                     table_rows AS estimated_row_count
                 FROM information_schema.tables
                 WHERE table_schema = %s
                 ORDER BY size_bytes DESC',
                $wpdb->dbname
            ),
            ARRAY_A
        );

        if ( ! is_array( $results ) ) {
            return array();
        }

        return array_map(
            static function ( array $row ): array {
                return array(
                    'name'                => $row['name'] ?? '',
                    'size_bytes'          => (int) ( $row['size_bytes'] ?? 0 ),
                    'data_bytes'          => (int) ( $row['data_bytes'] ?? 0 ),
                    'index_bytes'         => (int) ( $row['index_bytes'] ?? 0 ),
                    'size_human'          => size_format( $row['size_bytes'] ),
                    'estimated_row_count' => (int) ( $row['estimated_row_count'] ?? 0 ),
                );
            },
            $results
        );
    }

    /**
     * Return the database table prefix.
     *
     * @return string
     */
    private function get_table_prefix(): string {
        global $wpdb;

        return $wpdb->prefix;
    }

    /**
     * Return the database charset.
     *
     * @return string
     */
    private function get_charset(): string {
        global $wpdb;

        return $wpdb->charset;
    }

    /**
     * Return the database collation.
     *
     * @return string
     */
    private function get_collation(): string {
        global $wpdb;

        return $wpdb->collate;
    }

}
