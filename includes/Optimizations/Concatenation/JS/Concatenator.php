<?php
/**
 * JS concatenator.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\JS;

use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenator as Concatenator_Interface;

use Pressidium\WP\Performance\Exceptions\Concatenation_Exception;
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
     * Return the path of the concatenated file for the given aggregated hash.
     *
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return string
     */
    private function get_dest_path( string $aggregated_hash ): string {
        return $this->filesystem->build_path(
            Output_Directory::CONCATENATED->value,
            $aggregated_hash . '.js'
        );
    }

    /**
     * Return the path of the partial file for the given aggregated hash.
     *
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return string
     */
    private function get_partial_path( string $aggregated_hash ): string {
        return $this->filesystem->build_path(
            Output_Directory::CONCATENATED->value,
            $aggregated_hash . self::PARTIAL_SUFFIX . '.js'
        );
    }

    /**
     * Append the given contents to the partial file of the bundle.
     *
     * @throws Concatenation_Exception If the contents could not be written.
     *
     * @param string $contents        File contents.
     * @param string $type            Script type.
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return string Partial file location.
     */
    public function concatenate( string $contents, string $type, string $aggregated_hash ): string {
        $partial_path = $this->get_partial_path( $aggregated_hash );

        if ( ! $this->filesystem->exists( $partial_path ) ) {
            $this->logger->debug( "[JS_Concatenator] Creating partial concatenated file at {$partial_path}" );

            // Append initial contents to the concatenated file
            $output  = "window.pressidiumPerformanceConcatenatedChunks = window.pressidiumPerformanceConcatenatedChunks || {};\n";
            $output .= "window.pressidiumPerformanceConcatenatedChunks.chunks = window.pressidiumPerformanceConcatenatedChunks.chunks || {};\n";
            $output .= "window.pressidiumPerformanceConcatenatedChunks.chunks['{$type}'] = {\n";

            if ( ! $this->filesystem->create_file( $partial_path, $output ) ) {
                throw new Concatenation_Exception(
                    esc_html( "Could not create partial concatenated file at {$partial_path}" )
                );
            }
        }

        // Append the contents of this file
        $this->logger->debug( "[JS_Concatenator] Appending to partial concatenated file at {$partial_path}" );

        if ( ! $this->filesystem->append( $partial_path, "\n" . $contents ) ) {
            throw new Concatenation_Exception(
                esc_html( "Could not append to partial concatenated file at {$partial_path}" )
            );
        }

        return $partial_path;
    }

    /**
     * Append the closing code to the partial file of the bundle and move it into place.
     *
     * The bundle is an unterminated object literal until the closing code is appended,
     * so it must not be served before this runs.
     *
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return ?string Concatenated file location, or `null` if the bundle could not be completed.
     */
    public function complete( string $aggregated_hash ): ?string {
        $partial_path = $this->get_partial_path( $aggregated_hash );
        $dest_path    = $this->get_dest_path( $aggregated_hash );

        if ( ! $this->filesystem->exists( $partial_path ) ) {
            $this->logger->error( "[JS_Concatenator] Could not find partial concatenated file at {$partial_path}" );
            return null;
        }

        $this->logger->info( "[JS_Concatenator] Closing partial concatenated file at {$partial_path}" );

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
        $output .= "    (0, eval)(chunk);\n";
        $output .= "  }\n";
        $output .= "};\n\n";

        /*
         * Chunks of deferred scripts are queued where their `script` tag used to be and run
         * once the document has been parsed, in queue order, just like native deferred scripts.
         * The queue runs when the document becomes interactive, which is right where browsers
         * run deferred scripts, after parsing and before `DOMContentLoaded` is dispatched.
         */
        $output .= "window.pressidiumPerformanceConcatenatedChunks.deferred = window.pressidiumPerformanceConcatenatedChunks.deferred || [];\n\n";
        $output .= "window.pressidiumPerformanceConcatenatedChunks.deferChunk = (chunkId, type) => {\n";
        $output .= "  if (document.readyState !== 'loading') {\n";
        $output .= "    // The queue has already run, run this chunk right away\n";
        $output .= "    window.pressidiumPerformanceConcatenatedChunks.runChunk(chunkId, type);\n";
        $output .= "    return;\n";
        $output .= "  }\n\n";
        $output .= "  window.pressidiumPerformanceConcatenatedChunks.deferred.push([chunkId, type]);\n";
        $output .= "};\n\n";
        $output .= "window.pressidiumPerformanceConcatenatedChunks.runDeferredChunks = () => {\n";
        $output .= "  const deferred = window.pressidiumPerformanceConcatenatedChunks.deferred;\n\n";
        $output .= "  while (deferred.length) {\n";
        $output .= "    const [chunkId, type] = deferred.shift();\n\n";
        $output .= "    try {\n";
        $output .= "      window.pressidiumPerformanceConcatenatedChunks.runChunk(chunkId, type);\n";
        $output .= "    } catch (error) {\n";
        $output .= "      // Report the error like an uncaught one from a separate script, and go on with the next chunk\n";
        $output .= "      if (typeof reportError === 'function') {\n";
        $output .= "        reportError(error);\n";
        $output .= "      } else {\n";
        $output .= "        setTimeout(() => { throw error; });\n";
        $output .= "      }\n";
        $output .= "    }\n";
        $output .= "  }\n";
        $output .= "};\n\n";
        $output .= "document.addEventListener('readystatechange', () => {\n";
        $output .= "  if (document.readyState === 'interactive') {\n";
        $output .= "    window.pressidiumPerformanceConcatenatedChunks.runDeferredChunks();\n";
        $output .= "  }\n";
        $output .= "});\n";

        if ( ! $this->filesystem->append( $partial_path, "\n" . $output ) ) {
            $this->logger->error( "[JS_Concatenator] Could not close partial concatenated file at {$partial_path}" );
            return null;
        }

        if ( ! $this->filesystem->move( $partial_path, $dest_path ) ) {
            $this->logger->error( "[JS_Concatenator] Could not move {$partial_path} to {$dest_path}" );
            return null;
        }

        $this->logger->info( "[JS_Concatenator] Completed concatenated file at {$dest_path}" );

        return $dest_path;
    }

    /**
     * Delete the partial file of the bundle, if any.
     *
     * @param string $aggregated_hash Aggregated hash.
     *
     * @return void
     */
    public function discard( string $aggregated_hash ): void {
        $partial_path = $this->get_partial_path( $aggregated_hash );

        if ( $this->filesystem->exists( $partial_path ) ) {
            $this->logger->debug( "[JS_Concatenator] Discarding partial concatenated file at {$partial_path}" );
            $this->filesystem->delete_file( $partial_path );
        }
    }

}
