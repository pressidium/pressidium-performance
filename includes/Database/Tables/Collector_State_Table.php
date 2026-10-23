<?php
/**
 * Collector state table.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Database\Tables;

use Pressidium\WP\Performance\Database\Blueprint;
use Pressidium\WP\Performance\Database\Table;
use Pressidium\WP\Performance\Database\Query_Builder\Query_Exception;
use Pressidium\WP\Performance\Logging\Logger;

use BadMethodCallException;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Collector_State_Table class.
 *
 * Holds partial results produced by the EDGE Collector while a background process
 * is running. Each row is one chunk of one collector for one run. Rows are read
 * back at the finalize step to reassemble the payload sent to the EDGE API,
 * then deleted on success.
 *
 * A daily sweep clears orphaned rows from runs that crashed or were canceled.
 *
 * @since 1.2.0
 */
final class Collector_State_Table extends Table {

    const STATUS_OK    = 'ok';
    const STATUS_ERROR = 'error';

    /**
     * Collector_State_Table constructor.
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
        return 'pressidium_performance_collector_state';
    }

    /**
     * Build the table schema.
     *
     * Schema:
     *
     * | Column name | Type         |
     * | ----------- | ------------ |
     * | id          | INT          |
     * | run_id      | VARCHAR(36)  |
     * | collector   | VARCHAR(64)  |
     * | chunk_key   | VARCHAR(190) |
     * | data        | LONGTEXT     |
     * | status      | VARCHAR(16)  |
     * | error       | TEXT         |
     * | created_at  | TIMESTAMP    |
     * | updated_at  | TIMESTAMP    |
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

        $table->string( 'run_id', 36 )
              ->not_nullable();

        $table->string( 'collector', 64 )
              ->not_nullable();

        $table->string( 'chunk_key', 190 )
              ->not_nullable();

        $table->longtext( 'data' )
              ->not_nullable();

        $table->string( 'status', 16 )
              ->not_nullable()
              ->default_to( self::STATUS_OK );

        $table->text( 'error' );

        $table->timestamps();
    }

    /**
     * Whether a chunk row exists for the given `run_id`, `collector`, and `chunk_key` combination.
     *
     * @param string $run_id    Run identifier.
     * @param string $collector Collector name.
     * @param string $chunk_key Stable key identifying this chunk within the collector.
     *
     * @return bool
     */
    public function has_chunk( string $run_id, string $collector, string $chunk_key ): bool {
        try {
            $count = $this->query()
                          ->where( 'run_id', $run_id )
                          ->and_where( 'collector', $collector )
                          ->and_where( 'chunk_key', $chunk_key )
                          ->select( 'COUNT(*)' )
                          ->get_scalar();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not check if a collector chunk exists: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }

        return (bool) $count;
    }

    /**
     * Insert or update a chunk's payload for the given run.
     *
     * Idempotent: if a row already exists for this `run_id`, `collector`,
     * and `chunk_key` combination, it is updated in place, so retried
     * chunks do not duplicate rows.
     *
     * @param string  $run_id    Run identifier.
     * @param string  $collector Collector name.
     * @param string  $chunk_key Stable key identifying this chunk within the collector.
     * @param array   $data      Chunk payload (will be JSON-encoded).
     * @param string  $status    (Optional) One of `STATUS_OK` or `STATUS_ERROR`. Defaults to `STATUS_OK`.
     * @param ?string $error     (Optional) Error message when `status` is `STATUS_ERROR`.
     *
     * @return bool
     */
    public function set_chunk(
        string $run_id,
        string $collector,
        string $chunk_key,
        array $data,
        string $status = self::STATUS_OK,
        ?string $error = null
    ): bool {
        $encoded = wp_json_encode( $data );

        if ( $encoded === false ) {
            $this->logger->error(
                sprintf( 'Could not JSON-encode collector chunk for %s.', esc_html( $collector ) )
            );

            return false;
        }

        if ( $this->has_chunk( $run_id, $collector, $chunk_key ) ) {
            return $this->update_chunk( $run_id, $collector, $chunk_key, $encoded, $status, $error );
        }

        return $this->insert_chunk( $run_id, $collector, $chunk_key, $encoded, $status, $error );
    }

    /**
     * Insert a new chunk row.
     *
     * Assumes no row exists yet for this `run_id`, `collector`, and `chunk_key`
     * combination. Callers should go through `set_chunk()`, which checks first.
     *
     * @param string  $run_id       Run identifier.
     * @param string  $collector    Collector name.
     * @param string  $chunk_key    Stable key identifying this chunk within the collector.
     * @param string  $encoded_data JSON-encoded chunk payload.
     * @param string  $status       One of `STATUS_OK` or `STATUS_ERROR`.
     * @param ?string $error        Error message when `status` is `STATUS_ERROR`, `null` otherwise.
     *
     * @return bool
     */
    private function insert_chunk(
        string $run_id,
        string $collector,
        string $chunk_key,
        string $encoded_data,
        string $status,
        ?string $error
    ): bool {
        try {
            return (bool) $this->query()->insert(
                array(
                    'id'        => null,
                    'run_id'    => $run_id,
                    'collector' => $collector,
                    'chunk_key' => $chunk_key,
                    'data'      => $encoded_data,
                    'status'    => $status,
                    'error'     => $error,
                )
            );
        } catch ( BadMethodCallException | Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not insert collector chunk: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }
    }

    /**
     * Update an existing chunk row in place.
     *
     * Matches the row by its `run_id`, `collector`, and `chunk_key` combination,
     * overwriting its payload, status, and error. Callers should go through
     * `set_chunk()`, which only reaches this method when such a row exists.
     *
     * @param string  $run_id       Run identifier.
     * @param string  $collector    Collector name.
     * @param string  $chunk_key    Stable key identifying this chunk within the collector.
     * @param string  $encoded_data JSON-encoded chunk payload.
     * @param string  $status       One of `STATUS_OK` or `STATUS_ERROR`.
     * @param ?string $error        Error message when `status` is `STATUS_ERROR`, `null` otherwise.
     *
     * @return bool `false` if the query failed.
     *              A successful query that matched
     *              no rows still returns `true`.
     */
    private function update_chunk(
        string $run_id,
        string $collector,
        string $chunk_key,
        string $encoded_data,
        string $status,
        ?string $error
    ): bool {
        try {
            $this->query()
                 ->where( 'run_id', $run_id )
                 ->and_where( 'collector', $collector )
                 ->and_where( 'chunk_key', $chunk_key )
                 ->update(
                     array(
                         'data'   => $encoded_data,
                         'status' => $status,
                         'error'  => $error,
                     )
                 );
        } catch ( BadMethodCallException | Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not update collector chunk: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }

        return true;
    }

    /**
     * Return every row for the given run, ordered by `collector` then `id`.
     *
     * Rows are returned with `data` already JSON-decoded into an associative
     * array, ready to be merged into the final payload.
     *
     * @param string $run_id Run identifier.
     *
     * @return array<int, array{collector: string, chunk_key: string, data: array, status: string, error: ?string}>
     */
    public function get_run_rows( string $run_id ): array {
        try {
            $rows = $this->query()
                         ->where( 'run_id', $run_id )
                         ->order_by( 'collector', 'ASC' )
                         ->then_order_by( 'id', 'ASC' )
                         ->select( array( 'collector', 'chunk_key', 'data', 'status', 'error' ) )
                         ->get();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not load collector run rows: %s', esc_html( $exception->getMessage() ) )
            );

            return array();
        }

        if ( ! $rows ) {
            return array();
        }

        $decoded = array();

        foreach ( $rows as $row ) {
            $data = json_decode( $row->data, true );

            $decoded[] = array(
                'collector' => $row->collector,
                'chunk_key' => $row->chunk_key,
                'data'      => is_array( $data ) ? $data : array(),
                'status'    => $row->status,
                'error'     => $row->error,
            );
        }

        return $decoded;
    }

    /**
     * Return the number of chunks recorded for the given run, optionally filtered by collector.
     *
     * @param string  $run_id    Run identifier.
     * @param ?string $collector (Optional) Collector name to filter by.
     *
     * @return int
     */
    public function count_run_chunks( string $run_id, ?string $collector = null ): int {
        try {
            $query = $this->query()->where( 'run_id', $run_id );

            if ( $collector !== null ) {
                $query->and_where( 'collector', $collector );
            }

            $count = $query->select( 'COUNT(*)' )->get_scalar();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not count collector chunks: %s', esc_html( $exception->getMessage() ) )
            );

            return 0;
        }

        return (int) $count;
    }

    /**
     * Delete every row belonging to the given run.
     *
     * Called after a successful finalize so the table does not accumulate completed runs.
     *
     * @param string $run_id Run identifier.
     *
     * @return bool
     */
    public function delete_run( string $run_id ): bool {
        try {
            $this->query()
                 ->where( 'run_id', $run_id )
                 ->delete();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not delete collector run rows: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }

        return true;
    }

    /**
     * Garbage-collect rows older than the given cutoff.
     *
     * Catches runs that were canceled while running or whose
     * finalize step failed, leaving orphaned rows in the table.
     *
     * @param string $cutoff_datetime Cutoff MySQL DATETIME
     *                                (e.g. `gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )`)
     *
     * @return bool
     */
    public function delete_older_than( string $cutoff_datetime ): bool {
        try {
            $this->query()
                 ->where( 'created_at', '<', $cutoff_datetime )
                 ->delete();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not garbage-collect collector state: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }

        return true;
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
