<?php
/**
 * Concatenations files table.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Database\Tables;

use Pressidium\WP\Performance\Database\Blueprint;
use Pressidium\WP\Performance\Database\Table;
use Pressidium\WP\Performance\Database\Query_Builder\Query_Exception;
use Pressidium\WP\Performance\Enumerations\Exclusion_Reason;
use Pressidium\WP\Performance\Optimizations\Concatenation_File_Record;
use Pressidium\WP\Performance\Logging\Logger;

use BadMethodCallException;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenations_Files_Table class.
 *
 * Maps each concatenated bundle to the files it was built from, one row per
 * (bundle, file), so we know which files are included in the bundle and which
 * have to keep being served on their own.
 *
 * @since 1.2.0
 */
final class Concatenations_Files_Table extends Table {

    /**
     * Concatenations_Files_Table constructor.
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
        return 'pressidium_performance_concatenations_files';
    }

    /**
     * Build the table schema.
     *
     * Schema:
     *
     * | Column name      | Type        |
     * | ---------------- | ----------- |
     * | id               | INT         |
     * | concatenation_id | INT         |
     * | file_uri_hash    | CHAR(32)    |
     * | position         | INT         |
     * | segment          | INT         |
     * | is_excluded      | TINYINT     |
     * | exclusion_reason | VARCHAR(40) |
     * | created_at       | TIMESTAMP   |
     * | updated_at       | TIMESTAMP   |
     *
     * `concatenation_id` references `id` in the concatenations table. It isn't declared as
     * a foreign key, since `dbDelta()` doesn't support them, so deleting a concatenation
     * has to delete its files too, see `delete_by_concatenation_id()`.
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

        $table->integer( 'concatenation_id' )
              ->not_nullable();

        // MD5 hash of the URI, we only need it to match `href` and `src` attributes
        $table->char( 'file_uri_hash', 32 )
              ->not_nullable();

        $table->integer( 'position' )
              ->not_nullable();

        // `NULL` if the bundle holds no contents of the file (i.e. it was excluded or is empty)
        $table->integer( 'segment' );

        $table->boolean( 'is_excluded' )
              ->not_nullable()
              ->default_to( '0' );

        $table->string( 'exclusion_reason', 40 );

        $table->timestamps();

        // Also serves as the index for looking up the files of a concatenation
        $table->unique_key( 'concatenation_file', array( 'concatenation_id', 'file_uri_hash' ) );
    }

    /**
     * Replace the files of the concatenation with the given ID.
     *
     * Any files stored already (e.g. by a build that was taken over) are deleted first.
     *
     * @param int                         $concatenation_id ID of the concatenation.
     * @param Concatenation_File_Record[] $files            Files of the concatenation.
     *
     * @return bool Whether the files were stored.
     */
    public function replace_files( int $concatenation_id, array $files ): bool {
        $query = $this->query();

        try {
            $query->start_transaction();

            $this->query()
                 ->where( 'concatenation_id', $concatenation_id )
                 ->delete();

            foreach ( $files as $file ) {
                $exclusion_reason = $file->get_exclusion_reason();

                $this->query()->insert(
                    array(
                        'concatenation_id' => $concatenation_id,
                        'file_uri_hash'    => $file->get_file_uri_hash(),
                        'position'         => $file->get_position(),
                        'segment'          => $file->get_segment(),
                        'is_excluded'      => $file->is_excluded() ? 1 : 0,
                        'exclusion_reason' => $exclusion_reason?->value,
                    )
                );
            }

            $query->commit();
        } catch ( BadMethodCallException | Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not store the files of concatenation %d: %s',
                    $concatenation_id,
                    esc_html( $exception->getMessage() )
                )
            );

            try {
                $query->rollback();
            } catch ( Query_Exception $rollback_exception ) {
                $this->logger->log_exception( $rollback_exception );
            }

            return false;
        }

        return true;
    }

    /**
     * Return the files of the concatenation with the given ID, in the order they appeared on the page.
     *
     * @param int $concatenation_id ID of the concatenation.
     *
     * @return Concatenation_File_Record[]
     */
    public function get_files( int $concatenation_id ): array {
        try {
            $rows = $this->query()
                         ->where( 'concatenation_id', $concatenation_id )
                         ->select()
                         ->order_by( 'position', 'ASC' )
                         ->get();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not get the files of concatenation %d: %s',
                    $concatenation_id,
                    esc_html( $exception->getMessage() )
                )
            );

            return array();
        }

        if ( empty( $rows ) ) {
            return array();
        }

        return array_map(
            function ( object $row ): Concatenation_File_Record {
                $file = new Concatenation_File_Record();

                return $file
                    ->set_concatenation_id( (int) $row->concatenation_id )
                    ->set_file_uri_hash( $row->file_uri_hash )
                    ->set_position( (int) $row->position )
                    ->set_segment( $row->segment === null ? null : (int) $row->segment )
                    ->set_is_excluded( boolval( $row->is_excluded ) )
                    ->set_exclusion_reason( Exclusion_Reason::tryFrom( (string) $row->exclusion_reason ) );
            },
            $rows
        );
    }

    /**
     * Delete the files of the concatenation with the given ID.
     *
     * @param int $concatenation_id ID of the concatenation.
     *
     * @return bool Whether the files were deleted.
     */
    public function delete_by_concatenation_id( int $concatenation_id ): bool {
        try {
            $this->query()
                 ->where( 'concatenation_id', $concatenation_id )
                 ->delete();
        } catch ( Query_Exception | Exception $exception ) {
            $this->logger->error(
                sprintf(
                    'Could not delete the files of concatenation %d: %s',
                    $concatenation_id,
                    esc_html( $exception->getMessage() )
                )
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
