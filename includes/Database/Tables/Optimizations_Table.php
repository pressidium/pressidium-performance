<?php
/**
 * Optimizations table.
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
use Pressidium\WP\Performance\Optimizations\Optimization_Record;
use Pressidium\WP\Performance\Logging\Logger;

use BadMethodCallException;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Optimizations_Table class.
 *
 * @since 1.0.0
 */
final class Optimizations_Table extends Table {

    /**
     * Optimizations_Table constructor.
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
        return 'pressidium_performance_optimizations';
    }

    /**
     * Build the table schema.
     *
     * Schema:
     *
     * | Column name    | Type         |
     * | -------------- | ------------ |
     * | id             | INT          |
     * | original_uri   | VARCHAR(255) |
     * | optimized_uri  | VARCHAR(255) |
     * | hash           | VARCHAR(255) |
     * | original_size  | INT          |
     * | optimized_size | INT          |
     * | created_at     | TIMESTAMP    |
     * | updated_at     | TIMESTAMP    |
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

        $table->string( 'original_uri', 255 )
              ->not_nullable()
              ->unique();

        $table->string( 'optimized_uri', 255 )
              ->not_nullable();

        $table->string( 'hash', 255 )
              ->not_nullable();

        $table->integer( 'original_size' )
              ->not_nullable();

        $table->integer( 'optimized_size' )
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
     * Whether an optimization record exists for the given original URI.
     *
     * @param string $original_uri Original URI.
     *
     * @return bool
     */
    public function has_optimization_record( string $original_uri ): bool {
        try {
            $query = $this->query()
                          ->where( 'original_uri', $original_uri )
                          ->select( 'COUNT(*)' )
                          ->get_scalar();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not check if an optimization record exists: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }

        return (bool) $query;
    }

    /**
     * Insert the given optimization record into the database.
     *
     * @throws Exception If the table name is empty.
     *
     * @param Optimization_Record $optimization_record Optimization record to insert.
     *
     * @return bool Whether the record was inserted successfully.
     */
    public function insert_optimization_record( Optimization_Record $optimization_record ): bool {
        try {
            return (bool) $this->query()->insert(
                array(
                    'id'             => null,
                    'original_uri'   => $optimization_record->get_original_uri(),
                    'optimized_uri'  => $optimization_record->get_optimized_uri(),
                    'original_size'  => $optimization_record->get_original_size(),
                    'optimized_size' => $optimization_record->get_optimized_size(),
                    'hash'           => $optimization_record->get_hash(),
                )
            );
        } catch ( BadMethodCallException | Query_Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not insert optimization record: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }
    }

    /**
     * Update the given optimization record in the database.
     *
     * @throws Exception If the table name is empty.
     *
     * @param Optimization_Record $optimization_record Optimization record to update.
     *
     * @return bool Whether the record was updated successfully.
     */
    public function update_optimization_record( Optimization_Record $optimization_record ): bool {
        try {
            $data = array(
                'optimized_uri'  => $optimization_record->get_optimized_uri(),
                'hash'           => $optimization_record->get_hash(),
                'original_size'  => $optimization_record->get_original_size(),
                'optimized_size' => $optimization_record->get_optimized_size(),
            );

            $this->query()
                 ->where( 'original_uri', $optimization_record->get_original_uri() )
                 ->update( $data );
        } catch ( BadMethodCallException | Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not update optimization record: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }

        return true;
    }

    /**
     * Either insert the given optimization record into the database if it doesn't exist, or update it if it does.
     *
     * @throws Exception If the table name is empty.
     *
     * @param Optimization_Record $optimization_record Optimization record to insert or update.
     *
     * @return bool Whether the record was inserted or updated successfully.
     */
    public function set_optimization_record( Optimization_Record $optimization_record ): bool {
        if ( ! $this->has_optimization_record( $optimization_record->get_original_uri() ) ) {
            return $this->insert_optimization_record( $optimization_record );
        }

        return $this->update_optimization_record( $optimization_record );
    }

    /**
     * Return the optimization record for the given original URI, or `null` if it doesn't exist.
     *
     * @param string $original_uri Original URI.
     *
     * @return ?Optimization_Record
     */
    public function get_optimization_record( string $original_uri ): ?Optimization_Record {
        try {
            $record = $this->query()
                           ->where( 'original_uri', $original_uri )
                           ->select()
                           ->first();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not get optimization record: %s', esc_html( $exception->getMessage() ) )
            );

            return null;
        }

        if ( ! $record ) {
            return null;
        }

        $optimization_record = new Optimization_Record();
        $optimization_record
            ->set_id( $record->id )
            ->set_original_uri( $record->original_uri )
            ->set_optimized_uri( $record->optimized_uri )
            ->set_hash( $record->hash )
            ->set_original_size( $record->original_size )
            ->set_optimized_size( $record->optimized_size )
            ->set_created_at( $record->created_at )
            ->set_updated_at( $record->updated_at );

        return $optimization_record;
    }

    /**
     * Return the total size saved from all minifications.
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
                    'Could not get total size saved from minifications: %s',
                    esc_html( $exception->getMessage() )
                )
            );

            return 0;
        }

        return (int) $size_saved;
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
