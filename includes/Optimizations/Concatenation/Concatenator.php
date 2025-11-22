<?php
/**
 * Concatenator interface.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Files\File;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Table;
use Pressidium\WP\Performance\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenator interface.
 *
 * @since 1.0.0
 */
interface Concatenator {

    /**
     * Concatenator constructor.
     *
     * @param Logger               $logger               An instance of `Logger`.
     * @param Filesystem           $filesystem           An instance of `Filesystem`.
     * @param Concatenations_Table $concatenations_table An instance of `Concatenations_Table`.
     * @param Settings             $settings_object      An instance of `Settings`.
     */
    public function __construct(
        Logger $logger,
        Filesystem $filesystem,
        Concatenations_Table $concatenations_table,
        Settings $settings_object
    );

    /**
     * Merge the given files into a single file.
     *
     * @param string $contents        File contents.
     * @param string $script_type     Script type.
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return string Concatenated file location.
     */
    public function concatenate( string $contents, string $script_type, string $aggregated_hash ): string;

}
