<?php
/**
 * Schema.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Database;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Schema class.
 *
 * @since 1.0.0
 */
final class Schema {

    /**
     * @var Blueprint Blueprint object.
     */
    private Blueprint $blueprint;

    /**
     * Schema constructor.
     *
     * @param string   $table_name      Name of the table.
     * @param string   $charset_collate Database character collate.
     * @param callable $callback        Callback function to define the table's blueprint.
     */
    public function __construct(
        private readonly string $table_name,
        private readonly string $charset_collate,
        callable $callback
    ) {
        $this->blueprint = new Blueprint();

        /**
         * @param Blueprint $table Table's blueprint.
         *
         * @return void
         */
        $callback( $this->blueprint );
    }

    /**
     * Create a new schema.
     *
     * @param string   $table_name      Name of the table.
     * @param string   $charset_collate Database character collate.
     * @param callable $callback        Callback function to define the table's blueprint.
     *
     * @return Schema
     */
    public static function create( string $table_name, string $charset_collate, callable $callback ): Schema {
        return new self( $table_name, $charset_collate, $callback );
    }

    /**
     * Generate SQL for the table's blueprint object.
     *
     * @return string
     */
    public function generate(): string {
        return $this->blueprint->generate( $this->table_name, $this->charset_collate );
    }

}
