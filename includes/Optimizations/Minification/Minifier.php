<?php
/**
 * Minifier interface.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification;

use Pressidium\WP\Performance\Files\File;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Minifier interface.
 *
 * @since 1.0.0
 */
interface Minifier {

    /**
     * Minify the given file.
     *
     * @param File $file File to minify.
     *
     * @return ?File Minified file or `null` if the file should not be minified.
     */
    public function minify( File $file ): ?File;

}
