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
     * | Column name       | Type         |
     * | ----------------- | ------------ |
     * | id                | INT          |
     * | original_uri      | TEXT         |
     * | original_uri_hash | VARCHAR(64)  |
     * | optimized_uri     | TEXT         |
     * | content_hash      | VARCHAR(255) |
     * | original_size     | INT          |
     * | optimized_size    | INT          |
     * | sri_hash          | VARCHAR(255) |
     * | created_at        | TIMESTAMP    |
     * | updated_at        | TIMESTAMP    |
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

        /*
         * `TEXT`, since URLs can get really long, so we can't really index them directly.
         * A `TEXT` index would only cover a prefix, and a `VARCHAR` long enough for any
         * URL is also too wide to index. We look records up by `original_uri_hash` instead.
         */
        $table->text( 'original_uri' )
              ->not_nullable();

        // Hash of the original URI, to look records up by
        $table->string( 'original_uri_hash', 64 )
              ->not_nullable()
              ->unique();

        // `TEXT` since URLs can get really long
        $table->text( 'optimized_uri' )
              ->not_nullable();

        // Hash of the original file's contents, to tell whether it has changed since we minified it
        $table->string( 'content_hash', 255 )
              ->not_nullable();

        $table->integer( 'original_size' )
              ->not_nullable();

        $table->integer( 'optimized_size' )
              ->not_nullable();

        /*
         * Holds the single hash we validated the original file against, formatted as
         * `<algorithm>-<base64_hash>`. The longest one we accept is a SHA-512 hash,
         * which may have a length of up to 95 characters.
         *
         * `sha512-` which is 7 characters
         * base64-encoded string which is 86-88 characters
         * so we need a total of 95 characters at most.
         */
        $table->string( 'sri_hash', 255 )
              ->nullable();

        $table->timestamps();
    }

    /**
     * Return the hash of the given original URI, as stored in the `original_uri_hash` column.
     *
     * @param string $original_uri Original URI.
     *
     * @return string Hex-encoded hash.
     */
    private function hash_original_uri( string $original_uri ): string {
        return hash( 'sha256', $original_uri );
    }

    /**
     * Return a query matching the optimization record for the given original URI.
     *
     * @param string $original_uri Original URI.
     *
     * @return Query
     */
    private function query_by_original_uri( string $original_uri ): Query {
        return $this->query()
                    ->where( 'original_uri_hash', $this->hash_original_uri( $original_uri ) )
                    ->and_where( 'original_uri', $original_uri );
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
            $query = $this->query_by_original_uri( $original_uri )
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
                    'id'                => null,
                    'original_uri'      => $optimization_record->get_original_uri(),
                    'original_uri_hash' => $this->hash_original_uri( $optimization_record->get_original_uri() ),
                    'optimized_uri'     => $optimization_record->get_optimized_uri(),
                    'original_size'     => $optimization_record->get_original_size(),
                    'optimized_size'    => $optimization_record->get_optimized_size(),
                    'content_hash'      => $optimization_record->get_content_hash(),
                    'sri_hash'          => $optimization_record->get_sri_hash(),
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
                'content_hash'   => $optimization_record->get_content_hash(),
                'original_size'  => $optimization_record->get_original_size(),
                'optimized_size' => $optimization_record->get_optimized_size(),
                'sri_hash'       => $optimization_record->get_sri_hash(),
            );

            $this->query_by_original_uri( $optimization_record->get_original_uri() )
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
            $record = $this->query_by_original_uri( $original_uri )
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
            ->set_content_hash( $record->content_hash )
            ->set_original_size( $record->original_size )
            ->set_optimized_size( $record->optimized_size )
            ->set_sri_hash( $record->sri_hash ?? null )
            ->set_created_at( $record->created_at )
            ->set_updated_at( $record->updated_at );

        return $optimization_record;
    }

    /**
     * Return the optimized URI of every optimization record.
     *
     * @return ?string[] Optimized URIs, or `null` if they could not be read.
     */
    public function get_all_optimized_uris(): ?array {
        try {
            $records = $this->query()
                            ->select( 'optimized_uri' )
                            ->get();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not get the optimized URIs: %s', esc_html( $exception->getMessage() ) )
            );

            return null;
        }

        if ( ! $records ) {
            return array();
        }

        return array_map(
            function ( $record ) {
                return $record->optimized_uri;
            },
            $records
        );
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
     * Touch the optimization record for the given original URI to refresh its `updated_at` timestamp.
     *
     * This is useful when the record's data hasn't changed (e.g. the hash is the same),
     * but we still want to mark it as recently validated to prevent it from being considered expired.
     *
     * We use a raw SQL query here because the query builder's `SET` clause doesn't support
     * raw expressions like `CURRENT_TIMESTAMP`, it would escape them as string literals.
     *
     * @param string $original_uri Original URI of the optimization record to touch.
     *
     * @return bool Whether the record was touched successfully.
     */
    public function touch_optimization_record( string $original_uri ): bool {
        global $wpdb;

        $table_name = $this->get_prefix() . $this->get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "UPDATE {$table_name} SET updated_at = CURRENT_TIMESTAMP WHERE original_uri_hash = %s AND original_uri = %s",
                $this->hash_original_uri( $original_uri ),
                $original_uri
            )
        );

        if ( $result === false ) {
            $this->logger->error(
                sprintf( 'Could not touch optimization record: %s', esc_html( $wpdb->last_error ) )
            );

            return false;
        }

        return true;
    }

    /**
     * Return all optimization records that have been expired for the given number of days.
     *
     * A record is considered expired if its `updated_at` timestamp is older than
     * the given TTL in days. The grace period allows records to remain in the database
     * for a bit longer after expiring, giving them a chance to be re-validated.
     *
     * @param int $ttl_in_days          The TTL in days after which a record is considered expired.
     * @param int $grace_period_in_days Additional days after expiration before a record is eligible for cleanup.
     *
     * @return Optimization_Record[]
     */
    public function get_expired_optimization_records( int $ttl_in_days, int $grace_period_in_days = 0 ): array {
        $threshold_in_days = $ttl_in_days + $grace_period_in_days;

        try {
            $threshold_timestamp = gmdate( 'Y-m-d H:i:s', time() - ( $threshold_in_days * DAY_IN_SECONDS ) );

            $records = $this->query()
                            ->where( 'updated_at', '<', $threshold_timestamp )
                            ->select()
                            ->get();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not get expired optimization records: %s', esc_html( $exception->getMessage() ) )
            );

            return array();
        }

        if ( ! $records ) {
            return array();
        }

        return array_map(
            function ( $record ) {
                $optimization_record = new Optimization_Record();
                $optimization_record
                    ->set_id( $record->id )
                    ->set_original_uri( $record->original_uri )
                    ->set_optimized_uri( $record->optimized_uri )
                    ->set_content_hash( $record->content_hash )
                    ->set_original_size( $record->original_size )
                    ->set_optimized_size( $record->optimized_size )
                    ->set_sri_hash( $record->sri_hash ?? null )
                    ->set_created_at( $record->created_at )
                    ->set_updated_at( $record->updated_at );

                return $optimization_record;
            },
            $records
        );
    }

    /**
     * Delete the optimization record for the given original URI.
     *
     * @param string $original_uri Original URI of the optimization record to delete.
     *
     * @return bool Whether the record was deleted successfully.
     */
    public function delete_optimization_record( string $original_uri ): bool {
        try {
            $this->query_by_original_uri( $original_uri )
                 ->delete();

            return true;
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not delete optimization record for %s: %s',
                    esc_html( $original_uri ),
                    esc_html( $exception->getMessage() )
                )
            );

            return false;
        }
    }

    /**
     * Rename the `hash` column to `content_hash`, keeping its values.
     *
     * @throws Exception If the column could not be renamed.
     *
     * @param string $table_name Table name, including the prefix.
     *
     * @return void
     */
    private function migrate_hash_to_content_hash( string $table_name ): void {
        $columns = $this->get_columns( $table_name );

        if ( ! isset( $columns['hash'] ) ) {
            // Already renamed
            return;
        }

        if ( isset( $columns['content_hash'] ) ) {
            /*
             * No released schema has both, so we can't tell which one holds the content
             * hashes. Stop the upgrade rather than guess and drop the wrong column.
             */
            throw new Exception(
                sprintf(
                    'Both `hash` and `content_hash` exist in %s, refusing to migrate',
                    esc_html( $table_name )
                )
            );
        }

        $this->run_schema_query(
            "ALTER TABLE {$table_name} CHANGE COLUMN `hash` `content_hash` VARCHAR(255) NOT NULL"
        );
    }

    /**
     * Drop the UNIQUE index on `original_uri`.
     *
     * Records are now looked up by `original_uri_hash`.
     * The UNIQUE index on `original_uri` has to be removed before we can change the column to `TEXT`.
     *
     * @throws Exception If the index could not be dropped.
     *
     * @param string $table_name Table name, including the prefix.
     *
     * @return void
     */
    private function migrate_original_uri_index( string $table_name ): void {
        foreach ( $this->get_index_names_for_column( $table_name, 'original_uri' ) as $index_name ) {
            $this->run_schema_query( "ALTER TABLE {$table_name} DROP INDEX `{$index_name}`" );
        }
    }

    /**
     * Change the `original_uri` and `optimized_uri` columns to `TEXT`.
     *
     * @throws Exception If a column could not be changed.
     *
     * @param string $table_name Table name, including the prefix.
     *
     * @return void
     */
    private function migrate_uri_columns_to_text( string $table_name ): void {
        $columns = $this->get_columns( $table_name );

        foreach ( array( 'original_uri', 'optimized_uri' ) as $column_name ) {
            if ( strtolower( $columns[ $column_name ]->Type ) === 'text' ) {
                continue;
            }

            $this->run_schema_query( "ALTER TABLE {$table_name} MODIFY COLUMN `{$column_name}` TEXT NOT NULL" );
        }
    }

    /**
     * Add the `original_uri_hash` column, fill it in for existing rows, and make it unique.
     *
     * @throws Exception If the column could not be added, filled in, or made unique.
     *
     * @param string $table_name Table name, including the prefix.
     *
     * @return void
     */
    private function migrate_original_uri_hash( string $table_name ): void {
        $length  = 64;
        $columns = $this->get_columns( $table_name );

        if ( ! isset( $columns['original_uri_hash'] ) ) {
            $this->run_schema_query(
                "ALTER TABLE {$table_name} ADD COLUMN `original_uri_hash` VARCHAR({$length}) NULL AFTER `original_uri`"
            );
        }

        $this->run_schema_query(
            "UPDATE {$table_name} SET `original_uri_hash` = SHA2( `original_uri`, 256 )
             WHERE `original_uri_hash` IS NULL OR `original_uri_hash` = ''"
        );

        $columns = $this->get_columns( $table_name );

        if ( $columns['original_uri_hash']->Null === 'YES' ) {
            $this->run_schema_query(
                "ALTER TABLE {$table_name} MODIFY COLUMN `original_uri_hash` VARCHAR({$length}) NOT NULL"
            );
        }

        if ( empty( $this->get_index_names_for_column( $table_name, 'original_uri_hash' ) ) ) {
            $this->run_schema_query(
                "ALTER TABLE {$table_name} ADD UNIQUE KEY `original_uri_hash` (`original_uri_hash`)"
            );
        }
    }

    /**
     * Migrate a table from before version 1.3.
     *
     * Look records up by a hash of their original URI, so URIs can be stored as `TEXT`,
     * and rename `hash` to `content_hash`.
     *
     * Each step checks the structure of the table first, so this is safe
     * to run again after a partial failure.
     *
     * @throws Exception If the table could not be migrated.
     *
     * @param string $table_name Table name, including the prefix.
     *
     * @return void
     */
    private function migrate_to_1_3( string $table_name ): void {
        $this->migrate_hash_to_content_hash( $table_name );
        $this->migrate_original_uri_index( $table_name );
        $this->migrate_uri_columns_to_text( $table_name );
        $this->migrate_original_uri_hash( $table_name );
    }

    /**
     * Return the migrations that bring an existing table up to date, keyed by table version.
     *
     * Each migration is keyed by the table version that introduced it,
     * and only runs when upgrading from a version prior to that.
     *
     * @return array<string, callable(string): void>
     */
    protected function get_migrations(): array {
        return array(
            '1.3' => function ( string $table_name ) {
                $this->migrate_to_1_3( $table_name );
            },
        );
    }

    /**
     * Return the schema version of the table.
     *
     * Bump this up if the table schema changes in a future release.
     *
     * @return string
     */
    public function get_version(): string {
        return '1.3';
    }

}
