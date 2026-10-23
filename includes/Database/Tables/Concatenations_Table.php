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
use Pressidium\WP\Performance\Database\Query_Builder\Query_Exception;
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
     * | is_ready         | TINYINT      |
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

        /*
         * Whether the bundle is complete. A row is inserted with `is_ready = 0` to claim
         * the build of a bundle, see `claim_build()`, and flipped once it's published.
         *
         * Defaults to `1`, so the rows that existed before this column was added,
         * which were only ever written for complete bundles, are marked as ready.
         */
        $table->boolean( 'is_ready' )
              ->not_nullable()
              ->default_to( '1' );

        $table->timestamps();
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
     * Return the table name, including the prefix.
     *
     * @return string
     */
    private function get_full_table_name(): string {
        return $this->get_prefix() . $this->get_table_slug();
    }

    /**
     * Claim the build of the bundle with the given aggregated hash.
     *
     * Inserts a row with `is_ready = 0`. Since `aggregated_hash` is unique, only one of
     * any number of concurrent requests will be able to insert it, so only that request
     * builds the bundle.
     *
     * If the bundle is still not ready after the given timeout, we assume its build is
     * stuck and the claim is taken over. Only one request can take over a claim, since the
     * conditional update only matches the row once.
     *
     * @param string $aggregated_hash    Aggregated hash of the bundle.
     * @param string $type               Type of the bundle.
     * @param int    $timeout_in_seconds Number of seconds after which a claim on a bundle that isn't ready can be taken over.
     *
     * @return bool Whether the build was claimed.
     */
    public function claim_build( string $aggregated_hash, string $type, int $timeout_in_seconds ): bool {
        global $wpdb;

        $table_name = $this->get_full_table_name();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
        $inserted = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$table_name} (aggregated_hash, type, concatenated_uri, is_ready)
                 VALUES (%s, %s, '', 0)",
                $aggregated_hash,
                $type
            )
        );

        if ( $inserted === false ) {
            $this->logger->error(
                sprintf(
                    'Could not claim the build of bundle %s: %s',
                    esc_html( $aggregated_hash ),
                    esc_html( $wpdb->last_error )
                )
            );

            return false;
        }

        if ( $inserted === 1 ) {
            // No row existed for this aggregated hash, the claim is ours
            return true;
        }

        // A row exists already, take it over only if it's a build that has gone stale
        $taken_over = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_name}
                 SET type = %s, updated_at = CURRENT_TIMESTAMP
                 WHERE aggregated_hash = %s
                   AND is_ready = 0
                   AND updated_at < DATE_SUB( CURRENT_TIMESTAMP, INTERVAL %d SECOND )",
                $type,
                $aggregated_hash,
                $timeout_in_seconds
            )
        );
        // phpcs:enable

        if ( $taken_over === false ) {
            $this->logger->error(
                sprintf(
                    'Could not take over the build of bundle %s: %s',
                    esc_html( $aggregated_hash ),
                    esc_html( $wpdb->last_error )
                )
            );

            return false;
        }

        return $taken_over === 1;
    }

    /**
     * Publish the given concatenation record, marking its bundle as ready.
     *
     * @param Concatenation_Record $concatenation_record Concatenation record to publish.
     *
     * @return bool Whether the record was published.
     */
    public function publish_build( Concatenation_Record $concatenation_record ): bool {
        $data = array(
            'concatenated_uri' => $concatenation_record->get_concatenated_uri(),
            'is_minified'      => $concatenation_record->get_is_minified(),
            'is_ready'         => 1,
        );

        // Only store counts and sizes that were computed while building the bundle
        if ( $concatenation_record->get_files_count() !== null ) {
            $data['files_count'] = $concatenation_record->get_files_count();
        }

        if ( $concatenation_record->get_original_size() !== null ) {
            $data['original_size'] = $concatenation_record->get_original_size();
        }

        if ( $concatenation_record->get_optimized_size() !== null ) {
            $data['optimized_size'] = $concatenation_record->get_optimized_size();
        }

        try {
            $this->query()
                 ->where( 'aggregated_hash', $concatenation_record->get_aggregated_hash() )
                 ->update( $data );
        } catch ( BadMethodCallException | Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not publish bundle %s: %s',
                    esc_html( $concatenation_record->get_aggregated_hash() ),
                    esc_html( $exception->getMessage() )
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Release the claim on the bundle with the given aggregated hash without publishing it.
     *
     * That way, a later request can claim the build again.
     *
     * @param string $aggregated_hash Aggregated hash of the bundle.
     *
     * @return bool Whether the claim was released.
     */
    public function release_build( string $aggregated_hash ): bool {
        try {
            $this->query()
                 ->where( 'aggregated_hash', $aggregated_hash )
                 ->and_where( 'is_ready', 0 )
                 ->delete();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not release the build of bundle %s: %s',
                    esc_html( $aggregated_hash ),
                    esc_html( $exception->getMessage() )
                )
            );

            return false;
        }

        return true;
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
            ->set_is_ready( boolval( $record->is_ready ) )
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
        return '1.1';
    }

}
