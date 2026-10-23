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
use Pressidium\WP\Performance\Optimizations\Minification\Minified_Path_Builder;
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
     * @param Minified_Path_Builder       $minified_path_builder       An instance of `Minified_Path_Builder`.
     */
    public function __construct(
        protected readonly Logger $logger,
        protected readonly File_Minification_Evaluator $file_minification_evaluator,
        protected readonly Minified_Path_Builder $minified_path_builder,
    ) {}

    /**
     * Minify the contents of the given file.
     *
     * Unlike `minify()`, this does not consult the evaluator.
     *
     * @throws Minification_Exception If the file could not be minified.
     *
     * @param File $file File to minify.
     *
     * @return string Minified contents.
     */
    public function minify_contents( File $file ): string {
        try {
            $minifier = new Minify\JS();
            $minifier->add( $file->get_contents() );
            $minified_contents = $minifier->minify();
        } catch ( Exception $exception ) {
            throw new Minification_Exception(
                sprintf( 'Could not minify file: %s', esc_html( $exception->getMessage() ) )
            );
        }

        /*
         * The input is never empty here, as callers reject empty files, so empty
         * output means the minifier failed rather than that there was nothing to minify.
         */
        if ( ! is_string( $minified_contents ) || trim( $minified_contents ) === '' ) {
            throw new Minification_Exception(
                sprintf( 'Minification produced empty output: %s', esc_url( $file->get_url() ) )
            );
        }

        return $minified_contents;
    }

    /**
     * Minify the given file.
     *
     * @throws Minification_Exception If the file could not be minified.
     *
     * @param File $file File to minify.
     *
     * @return ?File Minified file or `null` if the file should not be minified.
     */
    public function minify( File $file ): ?File {
        if ( ! $this->file_minification_evaluator->should_be_minified( $file ) ) {
            return null;
        }

        return new File(
            $this->logger,
            $this->minified_path_builder->build_relative_path( $file->get_url(), $file->get_content_hash() ),
            $this->minify_contents( $file )
        );
    }

}
