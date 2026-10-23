<?php
/**
 * JS chunk encoder.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\JS;

use Pressidium\WP\Performance\Exceptions\Concatenation_Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Chunk_Encoder class.
 *
 * Turns a script into the entry of the chunks object literal of a bundle, keyed by the
 * hash of its URI, which is what the `script` tags that run it pass to `runChunk()`.
 *
 * @since 1.2.0
 */
final class Chunk_Encoder {

    /**
     * Return the chunk entry for the given script.
     *
     * An IIFE is wrapped in a function, so the bundle runs it directly. Anything else is
     * stored as a string literal, which `runChunk()` passes to an indirect `eval`, named
     * after its URL so it shows up under it in the devtools.
     *
     * @throws Concatenation_Exception If the script could not be encoded as a string.
     *
     * @param string $file_uri URI of the script.
     * @param string $contents Contents to write (minified or not).
     * @param bool   $is_iife  Whether the script is an IIFE.
     *
     * @return string
     */
    public static function encode( string $file_uri, string $contents, bool $is_iife ): string {
        $entry = "\t'" . md5( $file_uri ) . "': ";

        if ( $is_iife ) {
            return $entry . "(function() {\n" . $contents . "\n}),";
        }

        $encoded = wp_json_encode(
            $contents . "\n//# sourceURL=" . $file_uri,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ( ! is_string( $encoded ) ) {
            throw new Concatenation_Exception( 'Could not encode script as a string' );
        }

        return $entry . $encoded . ',';
    }

}
