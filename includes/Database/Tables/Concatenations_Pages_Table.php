<?php
/**
 * Concatenations pages table.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Database\Tables;

use Pressidium\WP\Performance\Database\Blueprint;
use Pressidium\WP\Performance\Database\Table;
use Pressidium\WP\Performance\Database\Query_Builder\Query;
use Pressidium\WP\Performance\Database\Query_Builder\Query_Exception;
use Pressidium\WP\Performance\Database\Query_Builder\WP_Database;
use Pressidium\WP\Performance\Logging\Logger;

use BadMethodCallException;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenations_Pages_Table class.
 *
 * @since 1.0.0
 */
final class Concatenations_Pages_Table extends Table {

    /**
     * Concatenations_Table constructor.
     *
     * @param Logger $logger An instance of `Logger`.
     */
    public function __construct( protected readonly Logger $logger ) {
        parent::__construct();
    }

    /**
     * Return the table name without the prefix.
     *
     * @return string
     */
    public function get_table_name(): string {
        return 'pressidium_performance_concatenations_pages';
    }

    /**
     * Build the table schema.
     *
     * Schema:
     *
     * | Column name     | Type         |
     * | --------------- | ------------ |
     * | id              | INT          |
     * | page_hash       | VARCHAR(255) |
     * | type            | VARCHAR(40)  |
     * | aggregated_hash | VARCHAR(255) |
     *
     * @param Blueprint $table Table's blueprint.
     *
     * @return void
     */
    protected function get_table_schema( Blueprint $table ): void {
        $table->integer( 'id' )
              ->not_nullable()
              ->auto_increment()
              ->primary();

        $table->string( 'page_hash', 255 )
              ->not_nullable();

        $table->string( 'type', 40 )
              ->not_nullable();

        $table->string( 'aggregated_hash', 255 )
              ->not_nullable();

        $table->timestamps();
    }

    /**
     * Create a new query instance for this table and return it.
     *
     * @return Query
     */
    public function query(): Query {
        global $wpdb;

        $query = new Query( new WP_Database( $wpdb ) );
        $query->from( $this->get_prefix() . $this->get_table_name() );

        return $query;
    }

    /**
     * Whether a mapping exists for the given page hash.
     *
     * @param string $page_hash Page hash to check.
     * @param string $type      Concatenation type to check.
     *
     * @return bool `true` if a mapping exists, `false` otherwise.
     */
    public function has_mapping( string $page_hash, string $type ): bool {
        try {
            $query = $this->query()
                          ->where( 'page_hash', $page_hash )
                          ->and_where( 'type', $type )
                          ->select( 'COUNT(*)' )
                          ->get_scalar();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not check if a mapping exists: %s',
                    esc_html( $exception->getMessage() )
                )
            );

            return false;
        }

        return (bool) $query;
    }

    /**
     * Return the aggregated hash mapped to the given page hash.
     *
     * @param string $page_hash Page hash.
     * @param string $type      Concatenation type.
     *
     * @return ?string Aggregated hash if found, `null` otherwise.
     */
    public function get_mapping( string $page_hash, string $type ): ?string {
        try {
            $aggregated_hash = $this->query()
                                    ->where( 'page_hash', $page_hash )
                                    ->and_where( 'type', $type )
                                    ->select( 'aggregated_hash' )
                                    ->get_scalar();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not retrieve mapping: %s',
                    esc_html( $exception->getMessage() )
                )
            );

            return null;
        }

        return $aggregated_hash;
    }

    /**
     * Insert a new page hash to aggregated hash mapping.
     *
     * @param string $page_hash       Page hash.
     * @param string $type            Concatenation type.
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return bool `true` if the mapping was inserted successfully, `false` otherwise.
     */
    public function insert_mapping( string $page_hash, string $type, string $aggregated_hash ): bool {
        try {
            return (bool) $this->query()->insert(
                array(
                    'page_hash'       => $page_hash,
                    'type'            => $type,
                    'aggregated_hash' => $aggregated_hash,
                )
            );
        } catch ( BadMethodCallException | Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not insert mapping: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }
    }

    /**
     * Update the aggregated hash for the given page hash.
     *
     * @param string $page_hash       Page hash.
     * @param string $type            Concatenation type.
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return bool `true` if the mapping was updated successfully, `false` otherwise.
     */
    public function update_mapping( string $page_hash, string $type, string $aggregated_hash ): bool {
        try {
            $data = array(
                'aggregated_hash' => $aggregated_hash,
            );

            $this->query()
                 ->where( 'page_hash', $page_hash )
                 ->and_where( 'type', $type )
                 ->update( $data );
        } catch ( BadMethodCallException | Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not update mapping: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }

        return true;
    }

    /**
     * Either insert the given mapping into the database if it doesn't exist, or update it if it does.
     *
     * @param string $page_hash       Page hash.
     * @param string $type            Concatenation type.
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return bool `true` if the mapping was set successfully, `false` otherwise.
     */
    public function set_mapping( string $page_hash, string $type, string $aggregated_hash ): bool {
        if ( ! $this->has_mapping( $page_hash, $type ) ) {
            return $this->insert_mapping( $page_hash, $type, $aggregated_hash );
        }

        return $this->update_mapping( $page_hash, $type, $aggregated_hash );
    }

    /**
     * Whether the given aggregated hash exists in the table.
     *
     * @param string $aggregated_hash Aggregated hash to check.
     *
     * @return bool `true` if the aggregated hash exists, `false` otherwise.
     */
    public function aggregated_hash_exists( string $aggregated_hash ): bool {
        try {
            $query = $this->query()
                          ->where( 'aggregated_hash', $aggregated_hash )
                          ->select( 'COUNT(*)' )
                          ->get_scalar();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not check if an aggregated hash exists: %s',
                    esc_html( $exception->getMessage() )
                )
            );

            return false;
        }

        return (bool) $query;
    }

    /**
     * Return the schema version of the table.
     *
     * Bump this up if the table schema changes in a future release.
     *
     * @return string
     */
    public function get_version(): string {
        return '1.0';
    }

}
