<?php
/**
 * Concatenator interface.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation;

use Pressidium\WP\Performance\Exceptions\Concatenation_Exception;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Table;
use Pressidium\WP\Performance\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenator interface.
 *
 * Files are concatenated into a partial file (`<key>.partial.<ext>`), which is only
 * moved into place once the bundle is complete, so a partially written bundle is never served.
 *
 * A bundle file is identified by its key, which is the aggregated hash of the bundle, or
 * the key of one of its segments if the bundle is split.
 *
 * @since 1.0.0
 */
interface Concatenator {

    /**
     * @var string Suffix appended to the key in the filename of a bundle that is still being built.
     */
    const PARTIAL_SUFFIX = '.partial';

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
     * Append the given contents to the partial file of the bundle.
     *
     * @throws Concatenation_Exception If the contents could not be written.
     *
     * @param string $contents File contents.
     * @param string $type     File type.
     * @param string $key      Key of the bundle file.
     *
     * @return string Partial file location.
     */
    public function concatenate( string $contents, string $type, string $key ): string;

    /**
     * Finish the partial file of the bundle and move it into place.
     *
     * @param string $key Key of the bundle file.
     *
     * @return ?string Concatenated file location, or `null` if the bundle could not be completed.
     */
    public function complete( string $key ): ?string;

    /**
     * Delete the partial file of the bundle, if any.
     *
     * Called before a bundle is built, so it doesn't append to a file
     * left behind by a run that never completed, and when a build fails.
     *
     * @param string $key Key of the bundle file.
     *
     * @return void
     */
    public function discard( string $key ): void;

}
