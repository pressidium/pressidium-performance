<?php
/**
 * JavaScript Minifier.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification\JS;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Files\File;
use Pressidium\WP\Performance\Exceptions\Minification_Exception;
use Pressidium\WP\Performance\Optimizations\Minification\File_Minification_Evaluator;
use Pressidium\WP\Performance\Optimizations\Minification\Minifier as Minifier_Interface;

use Pressidium\WP\Performance\Dependencies\MatthiasMullie\Minify;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Minifier class.
 *
 * @since 1.0.0
 */
final class Minifier implements Minifier_Interface {

    /**
     * Minifier constructor.
     *
     * @param Logger                      $logger                      An instance of `Logger`.
     * @param File_Minification_Evaluator $file_minification_evaluator An instance of `File_Minification_Evaluator`.
     */
    public function __construct(
        protected readonly Logger $logger,
        protected readonly File_Minification_Evaluator $file_minification_evaluator,
    ) {}

    /**
     * Return the relative path of the given file URL.
     *
     * @throws Minification_Exception If the URL could not be parsed.
     *
     * @param string $file_url File URL.
     *
     * @return string Relative path.
     */
    private function get_relative_path( string $file_url ): string {
        $components = wp_parse_url( $file_url );

        if ( ! is_array( $components ) || ! isset( $components['host'] ) || ! isset( $components['path'] ) ) {
            throw new Minification_Exception( 'Could not minify file: URL could not be parsed' );
        }

        $url_host = $components['host'];
        $url_path = $components['path'];

        $relative_path = ltrim( $url_path, '/' );
        $relative_path = $url_host . '/' . $relative_path;

        return str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );
    }

    /**
     * Minify the given file.
     *
     * @throws Minification_Exception If the file could not be minified.
     *
     * @param File $file File to minify.
     *
     * @return File Minified file.
     */
    public function minify( File $file ): File {
        if ( ! $this->file_minification_evaluator->should_be_minified( $file ) ) {
            return $file;
        }

        try {
            $minifier = new Minify\JS();
            $minifier->add( $file->get_contents() );
            $minified_contents = $minifier->minify();
        } catch ( Exception $exception ) {
            throw new Minification_Exception(
                sprintf( 'Could not minify file: %s', esc_html( $exception->getMessage() ) )
            );
        }

        return new File(
            $this->logger,
            $this->get_relative_path( $file->get_url() ),
            $minified_contents
        );
    }

}
