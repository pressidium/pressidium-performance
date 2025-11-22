<?php
/**
 * JS concatenator.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\JS;

use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenator as Concatenator_Interface;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Table;
use Pressidium\WP\Performance\Settings;

use Pressidium\WP\Performance\Enumerations\Output_Directory;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenator class.
 *
 * @since 1.0.0
 */
final class Concatenator implements Concatenator_Interface {

    /**
     * Concatenator constructor.
     *
     * @param Logger               $logger
     * @param Filesystem           $filesystem
     * @param Concatenations_Table $concatenations_table
     * @param Settings             $settings_object
     */
    public function __construct(
        protected readonly Logger $logger,
        protected readonly Filesystem $filesystem,
        protected readonly Concatenations_Table $concatenations_table,
        protected readonly Settings $settings_object,
    ) {}

    /**
     * Merge the given files into a single file.
     *
     * @param string $contents        File contents.
     * @param string $script_type     Script type.
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return string Concatenated file location.
     */
    public function concatenate( string $contents, string $script_type, string $aggregated_hash ): string {
        $dest_filename = $aggregated_hash . '.js';
        $sub_dir       = Output_Directory::CONCATENATED->value;
        $dest_path     = $this->filesystem->build_path( $sub_dir, $dest_filename );

        if ( ! $this->filesystem->exists( $dest_path ) ) {
            $this->logger->debug( "[JS_Concatenator] Creating concatenated file at {$dest_path}" );

            // Append initial contents to the concatenated file
            $output  = "window.pressidiumPerformanceConcatenatedChunks = window.pressidiumPerformanceConcatenatedChunks || {};\n";
            $output .= "window.pressidiumPerformanceConcatenatedChunks.chunks = window.pressidiumPerformanceConcatenatedChunks.chunks || {};\n";
            $output .= "window.pressidiumPerformanceConcatenatedChunks.chunks['{$script_type}'] = {\n";

            $this->filesystem->create_file( $dest_path, $output );

            // Append the contents of this file
            $this->filesystem->append( $dest_path, "\n" . $contents );

            return $dest_path;
        }

        $this->logger->debug( "[JS_Concatenator] Appending to concatenated file at {$dest_path}" );
        $this->filesystem->append( $dest_path, "\n" . $contents );

        return $dest_path;
    }

    /**
     * Append the closing code to the concatenated file.
     *
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return void
     */
    public function close_file( string $aggregated_hash ): void {
        // TODO: Come up with a better name for this method (context: this appends the last bit of content)

        $dest_filename = $aggregated_hash . '.js';
        $sub_dir       = Output_Directory::CONCATENATED->value;
        $dest_path     = $this->filesystem->build_path( $sub_dir, $dest_filename );

        if ( $this->filesystem->exists( $dest_path ) ) {
            $this->logger->info( "[JS_Concatenator] Closing concatenated file at {$dest_path}" );

            $output  = "};\n\n";
            $output .= "window.pressidiumPerformanceConcatenatedChunks.runChunk = (chunkId, type) => {\n";
            $output .= "  window.pressidiumPerformanceConcatenatedChunks.chunks = window.pressidiumPerformanceConcatenatedChunks.chunks || {};\n";
            $output .= "  window.pressidiumPerformanceConcatenatedChunks.chunks[type] = window.pressidiumPerformanceConcatenatedChunks.chunks[type] || {};\n\n";
            $output .= "  if (!window.pressidiumPerformanceConcatenatedChunks.chunks[type].hasOwnProperty(chunkId)) {\n";
            $output .= "    console.warn('[Pressidium Performance] Could not find chunk: ' + chunkId);\n";
            $output .= "    return;\n";
            $output .= "  }\n\n";
            $output .= "  const chunk = window.pressidiumPerformanceConcatenatedChunks.chunks[type][chunkId];\n\n";
            $output .= "  if (typeof chunk === 'function') {\n";
            $output .= "    chunk();\n";
            $output .= "  } else {\n";
            $output .= "    const decodedChunk = atob(chunk);\n";
            $output .= "    eval(decodedChunk);\n";
            $output .= "  }\n";
            $output .= "};\n";

            $this->filesystem->append( $dest_path, "\n" . $output );
        } else {
            $this->logger->error( "[JS_Concatenator] Could not find concatenated file at {$dest_path} to close it" );
        }
    }

}
