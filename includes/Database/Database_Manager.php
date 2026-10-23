<?php
/**
 * Database manager.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Database;

use Pressidium\WP\Performance\Options\Options;
use Pressidium\WP\Performance\Logging\Logger;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Database_Manager class.
 *
 * @since 1.0.0
 */
final class Database_Manager {

    /**
     * @var string Key for the table versions option.
     */
    const TABLE_VERSIONS_OPTIONS_KEY = 'pressidium_performance_table_versions';

    /**
     * @var Table[] Array of registered tables.
     */
    private array $tables;

    /**
     * Database_Manager constructor.
     *
     * @param Options $options An instance of the `Options` class.
     * @param Logger  $logger  An instance of the `Logger` class.
     */
    public function __construct( private Options $options, private Logger $logger ) {
        $this->tables = array();
    }

    /**
     * Register the given table.
     *
     * @param Table $table Table to register.
     *
     * @return void
     */
    public function register_table( Table $table ): void {
        $this->tables[] = $table;
    }

    /**
     * Return the stored table versions, keyed by table slug.
     *
     * @return array<string, string>|false Table versions, or `false` if none are stored.
     */
    private function get_stored_table_versions(): array|false {
        $table_versions = $this->options->get( self::TABLE_VERSIONS_OPTIONS_KEY );

        return is_array( $table_versions ) ? $table_versions : false;
    }

    /**
     * Return a callback that stores the given table's version after each of its migrations.
     *
     * @param Table $table Table being migrated.
     *
     * @return callable(string): void
     */
    private function record_migration_for( Table $table ): callable {
        return function ( string $version ) use ( $table ) {
            $table_versions = $this->get_stored_table_versions() ?: array();

            $table_versions[ $table->get_table_slug() ] = $version;

            $this->options->set( self::TABLE_VERSIONS_OPTIONS_KEY, $table_versions );
        };
    }

    /**
     * Create the database tables.
     *
     * @return bool
     */
    public function create_tables(): bool {
        $stored_versions = $this->get_stored_table_versions() ?: array();
        $table_versions  = array();

        foreach ( $this->tables as $table ) {
            try {
                $table->create(
                    $stored_versions[ $table->get_table_slug() ] ?? null,
                    $this->record_migration_for( $table )
                );

                $table_versions[ $table->get_table_slug() ] = $table->get_version();
            } catch ( Exception $exception ) {
                $this->logger->error( 'Database table(s) could not be created.' );

                return false;
            }
        }

        $this->options->set( self::TABLE_VERSIONS_OPTIONS_KEY, $table_versions );
        $this->logger->info( 'Database table(s) created successfully.' );

        return true;
    }

    /**
     * Upgrade the database tables depending on the current database version.
     *
     * Tables that have been registered since the last time the table versions were stored
     * (i.e. tables added by a plugin update) are created, instead of being upgraded.
     *
     * Will return `false` if there was an error while upgrading, or there was nothing to upgrade.
     *
     * @return bool
     */
    public function maybe_upgrade_tables(): bool {
        $table_versions = $this->get_stored_table_versions();
        $did_upgrade    = false;

        if ( $table_versions === false ) {
            return false;
        }

        foreach ( $this->tables as $table ) {
            try {
                $new_version = $table->get_version();

                if ( ! isset( $table_versions[ $table->get_table_slug() ] ) ) {
                    /*
                     * We have no record of this table, so it must have been registered
                     * after the table versions were last stored. Create it instead.
                     */
                    $table->create();

                    $table_versions[ $table->get_table_slug() ] = $new_version;
                    $did_upgrade                                = true;

                    $this->logger->info( "Database table {$table->get_table_slug()} created" );

                    continue;
                }

                $current_version = $table_versions[ $table->get_table_slug() ];

                if ( empty( $new_version ) || empty( $current_version ) ) {
                    continue;
                }

                if ( version_compare( $new_version, $current_version, '<=' ) ) {
                    continue;
                }

                $table->create( $current_version, $this->record_migration_for( $table ) );

                $table_versions[ $table->get_table_slug() ] = $new_version;
                $did_upgrade                                = true;

                $this->logger->info( "Database table {$table->get_table_slug()} upgraded" );
            } catch ( Exception $exception ) {
                $this->logger->error( 'Database table(s) could not be upgraded.' );

                return false;
            }
        }

        if ( $did_upgrade ) {
            $this->options->set( self::TABLE_VERSIONS_OPTIONS_KEY, $table_versions );
        }

        return $did_upgrade;
    }

}
