<?php
/**
 * Minifier interface.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification;

use Pressidium\WP\Performance\Logging\Logger;
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
     * Minifier constructor.
     *
     * @param Logger                      $logger    An instance of `Logger`.
     * @param File_Minification_Evaluator $evaluator An instance of `File_Minification_Evaluator`.
     */
    public function __construct( Logger $logger, File_Minification_Evaluator $evaluator );

    /**
     * Minify the given file.
     *
     * @param File $file File to minify.
     *
     * @return File Minified file.
     */
    public function minify( File $file ): File;

}
