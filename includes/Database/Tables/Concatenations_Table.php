<?php
/**
 * Concatenations table.
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
use Pressidium\WP\Performance\Optimizations\Concatenation_Record;
use Pressidium\WP\Performance\Logging\Logger;

use BadMethodCallException;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenations_Table class.
 *
 * @since 1.0.0
 */
final class Concatenations_Table extends Table {

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
        return 'pressidium_performance_concatenations';
    }

    /**
     * Build the table schema.
     *
     * Schema:
     *
     * | Column name      | Type         |
     * | ---------------- | ------------ |
     * | id               | INT          |
     * | aggregated_hash  | VARCHAR(255) |
     * | type             | VARCHAR(40)  |
     * | concatenated_uri | VARCHAR(255) |
     * | files_count      | INT          |
     * | original_size    | INT          |
     * | optimized_size   | INT          |
     * | is_minified      | TINYINT      |
     * | created_at       | TIMESTAMP    |
     * | updated_at       | TIMESTAMP    |
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

        $table->string( 'aggregated_hash', 255 )
              ->not_nullable()
              ->unique();

        $table->string( 'type', 40 )
              ->not_nullable();

        $table->string( 'concatenated_uri', 255 )
              ->not_nullable();

        $table->integer( 'files_count' );

        $table->integer( 'original_size' );

        $table->integer( 'optimized_size' );

        $table->boolean( 'is_minified' )
              ->default_to( '0' );

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
     * Whether a concatenation record exists for the given aggregated hash.
     *
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return bool
     */
    public function has_concatenation_record( string $aggregated_hash ): bool {
        try {
            $query = $this->query()
                          ->where( 'aggregated_hash', $aggregated_hash )
                          ->select( 'COUNT(*)' )
                          ->get_scalar();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not check if a concatenation record exists: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }

        return (bool) $query;
    }

    /**
     * Insert the given concatenation record into the database.
     *
     * @throws Exception If the table name is empty.
     *
     * @param Concatenation_Record $concatenation_record Concatenation record to insert.
     *
     * @return bool Whether the record was inserted successfully.
     */
    public function insert_concatenation_record( Concatenation_Record $concatenation_record ): bool {
        try {
            return (bool) $this->query()->insert(
                array(
                    'id'               => null,
                    'aggregated_hash'  => $concatenation_record->get_aggregated_hash(),
                    'type'             => $concatenation_record->get_type(),
                    'concatenated_uri' => $concatenation_record->get_concatenated_uri(),
                    'is_minified'      => $concatenation_record->get_is_minified(),
                )
            );
        } catch ( BadMethodCallException | Query_Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not insert concatenation record: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }
    }

    /**
     * Update the given concatenation record in the database.
     *
     * @param Concatenation_Record $concatenation_record Concatenation record to update.
     *
     * @return bool Whether the record was updated successfully.
     */
    public function update_concatenation_record( Concatenation_Record $concatenation_record ): bool {
        try {
            $data = array(
                'concatenated_uri' => $concatenation_record->get_concatenated_uri(),
                'is_minified'      => $concatenation_record->get_is_minified(),
                'files_count'      => $concatenation_record->get_files_count(),
                'original_size'    => $concatenation_record->get_original_size(),
                'optimized_size'   => $concatenation_record->get_optimized_size(),
            );

            $this->query()
                 ->where( 'aggregated_hash', $concatenation_record->get_aggregated_hash() )
                 ->update( $data );
        } catch ( BadMethodCallException | Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not update concatenation record: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }

        return true;
    }

    /**
     * Either insert the given concatenation record into the database if it doesn't exist, or update it if it does.
     *
     * @throws Exception If the table name is empty.
     *
     * @param Concatenation_Record $concatenation_record Concatenation record to insert or update.
     *
     * @return bool Whether the record was inserted or updated successfully.
     */
    public function set_concatenation_record( Concatenation_Record $concatenation_record ): bool {
        if ( ! $this->has_concatenation_record( $concatenation_record->get_aggregated_hash() ) ) {
            return $this->insert_concatenation_record( $concatenation_record );
        }

        return $this->update_concatenation_record( $concatenation_record );
    }

    /**
     * Return the concatenation record for the given aggregated hash, or `null` if it doesn't exist.
     *
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return ?Concatenation_Record
     */
    public function get_concatenation_record( string $aggregated_hash ): ?Concatenation_Record {
        try {
            $record = $this->query()
                           ->where( 'aggregated_hash', $aggregated_hash )
                           ->select()
                           ->first();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not get concatenation record: %s', esc_html( $exception->getMessage() ) )
            );

            return null;
        }

        if ( ! $record ) {
            return null;
        }

        $concatenation_record = new Concatenation_Record();
        $concatenation_record
            ->set_id( $record->id )
            ->set_aggregated_hash( $record->aggregated_hash )
            ->set_type( $record->type )
            ->set_concatenated_uri( $record->concatenated_uri )
            ->set_files_count( $record->files_count )
            ->set_original_size( $record->original_size )
            ->set_optimized_size( $record->optimized_size )
            ->set_is_minified( boolval( $record->is_minified ) )
            ->set_created_at( $record->created_at )
            ->set_updated_at( $record->updated_at );

        return $concatenation_record;
    }

    /**
     * Return the total size saved from all concatenations.
     *
     * @return int
     */
    public function get_total_size_saved(): int {
        try {
            $size_saved = $this->query()
                               ->select( 'SUM(original_size - optimized_size) AS total_size_saved' )
                               ->get_scalar();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not get total size saved from concatenations: %s',
                    esc_html( $exception->getMessage() )
                )
            );

            return 0;
        }

        return (int) $size_saved;
    }

    /**
     * Delete the concatenation record with the given aggregated hash.
     *
     * @param string $aggregated_hash
     *
     * @return bool
     */
    public function delete_by_aggregated_hash( string $aggregated_hash ): bool {
        try {
            $this->query()
                 ->where( 'aggregated_hash', $aggregated_hash )
                 ->delete();

            return true;
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not delete concatenation record with aggregated hash %s: %s',
                    esc_html( $aggregated_hash ),
                    esc_html( $exception->getMessage() )
                )
            );

            return false;
        }
    }

    /**
     * Return the total number of files concatenated.
     *
     * @return int
     */
    public function get_total_files_concatenated(): int {
        try {
            $files_concatenated = $this->query()
                                      ->select( 'SUM(files_count) AS total_files_concatenated' )
                                      ->get_scalar();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not get total files concatenated: %s',
                    esc_html( $exception->getMessage() )
                )
            );

            return 0;
        }

        return (int) $files_concatenated;
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
