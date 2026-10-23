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
use Pressidium\WP\Performance\Database\Query_Builder\Query_Exception;
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
     * Return the mapping row for the given page hash.
     *
     * Reads the aggregated hash and the timestamp together, so `set_mapping()`
     * can decide whether anything has to be written without a second query.
     *
     * @param string $page_hash Page hash.
     * @param string $type      Concatenation type.
     *
     * @return ?object Row holding `aggregated_hash` and `updated_at`, `null` if there is none.
     */
    private function get_mapping_row( string $page_hash, string $type ): ?object {
        try {
            $row = $this->query()
                        ->where( 'page_hash', $page_hash )
                        ->and_where( 'type', $type )
                        ->select( array( 'aggregated_hash', 'updated_at' ) )
                        ->first();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not retrieve mapping row: %s',
                    esc_html( $exception->getMessage() )
                )
            );

            return null;
        }

        return is_object( $row ) ? $row : null;
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
     * Bump the `updated_at` timestamp of the given mapping.
     *
     * A mapping is only rewritten when the page's assets change, and MySQL fires
     * `ON UPDATE CURRENT_TIMESTAMP` only when a value actually changes. So without
     * an explicit touch, `updated_at` records when the mapping last changed instead
     * of when the page was last seen.
     *
     * We use a raw SQL query here because the query builder's `SET` clause doesn't
     * support raw expressions like `CURRENT_TIMESTAMP`.
     *
     * @param string $page_hash Page hash.
     * @param string $type      Concatenation type.
     *
     * @return bool Whether the mapping was touched successfully.
     */
    public function touch_mapping( string $page_hash, string $type ): bool {
        global $wpdb;

        $table_name = $this->get_prefix() . $this->get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "UPDATE {$table_name} SET updated_at = CURRENT_TIMESTAMP WHERE page_hash = %s AND type = %s",
                $page_hash,
                $type
            )
        );

        if ( $result === false ) {
            $this->logger->error(
                sprintf( 'Could not touch mapping: %s', esc_html( $wpdb->last_error ) )
            );

            return false;
        }

        return true;
    }

    /**
     * Either insert the given mapping into the database if it doesn't exist, or update it if it does.
     *
     * This runs on every frontend render, so it does as little writing as it can get
     * away with. An unchanged mapping is only touched once per touch interval, which
     * keeps `updated_at` fresh enough for a cleanup job that works in days, without
     * writing on every page view.
     *
     * @param string $page_hash                  Page hash.
     * @param string $type                       Concatenation type.
     * @param string $aggregated_hash            Aggregated hash.
     * @param int    $touch_interval_in_seconds  (Optional) How stale an unchanged mapping has to be
     *                                           before it is touched. Defaults to a day.
     *
     * @return bool `true` if the mapping was set successfully, `false` otherwise.
     */
    public function set_mapping(
        string $page_hash,
        string $type,
        string $aggregated_hash,
        int $touch_interval_in_seconds = DAY_IN_SECONDS
    ): bool {
        $row = $this->get_mapping_row( $page_hash, $type );

        if ( $row === null ) {
            return $this->insert_mapping( $page_hash, $type, $aggregated_hash );
        }

        if ( $row->aggregated_hash !== $aggregated_hash ) {
            // The page's assets have changed, so updating the mapping will bump `updated_at` on its own
            return $this->update_mapping( $page_hash, $type, $aggregated_hash );
        }

        $updated_at = strtotime( $row->updated_at . ' UTC' );

        if ( $updated_at === false ) {
            // Could not make sense of the stored timestamp, touch it to get a usable one
            return $this->touch_mapping( $page_hash, $type );
        }

        if ( $updated_at > time() - $touch_interval_in_seconds ) {
            // Still fresh enough, leave it alone
            return true;
        }

        // The mapping is unchanged, so touch it to keep it alive
        return $this->touch_mapping( $page_hash, $type );
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
     * Delete all mappings that have not been seen for the given number of days.
     *
     * A mapping is considered expired if its `updated_at` timestamp is older than
     * the given TTL in days. The grace period keeps mappings around for a bit longer
     * after expiring, giving them a chance to be touched by a late visitor.
     *
     * @param int $ttl_in_days          The TTL in days after which a mapping is considered expired.
     * @param int $grace_period_in_days Additional days after expiration before a mapping is deleted.
     *
     * @return int Number of mappings deleted.
     */
    public function delete_expired_mappings( int $ttl_in_days, int $grace_period_in_days = 0 ): int {
        $threshold_in_days   = $ttl_in_days + $grace_period_in_days;
        $threshold_timestamp = gmdate( 'Y-m-d H:i:s', time() - ( $threshold_in_days * DAY_IN_SECONDS ) );

        try {
            $expired_count = (int) $this->query()
                                        ->where( 'updated_at', '<', $threshold_timestamp )
                                        ->select( 'COUNT(*)' )
                                        ->get_scalar();

            if ( $expired_count === 0 ) {
                return 0;
            }

            $this->query()
                 ->where( 'updated_at', '<', $threshold_timestamp )
                 ->delete();
        } catch ( BadMethodCallException | Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not delete expired mappings: %s',
                    esc_html( $exception->getMessage() )
                )
            );

            return 0;
        }

        return $expired_count;
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
