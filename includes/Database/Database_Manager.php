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
     * Create the database tables.
     *
     * @return bool
     */
    public function create_tables(): bool {
        $table_versions = array();

        foreach ( $this->tables as $table ) {
            try {
                $table->create();

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
     * Will return `false` if there was an error while upgrading, or there was nothing to upgrade.
     *
     * @return bool
     */
    public function maybe_upgrade_tables(): bool {
        $table_versions = $this->options->get( self::TABLE_VERSIONS_OPTIONS_KEY );
        $did_upgrade    = false;

        if ( $table_versions === false ) {
            return false;
        }

        foreach ( $this->tables as $table ) {
            try {
                $new_version     = $table->get_version();
                $current_version = $table_versions[ $table->get_table_slug() ];

                if ( empty( $new_version ) || empty( $current_version ) ) {
                    continue;
                }

                if ( version_compare( $new_version, $current_version, '<=' ) ) {
                    continue;
                }

                $table->create();

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
