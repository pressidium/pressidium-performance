<?php
/**
 * CSS Minifier.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification\CSS;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Files\File;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Enumerations\Output_Directory;
use Pressidium\WP\Performance\Exceptions\Minification_Exception;
use Pressidium\WP\Performance\Optimizations\Minification\File_Minification_Evaluator;
use Pressidium\WP\Performance\Optimizations\Minification\Minified_Path_Builder;
use Pressidium\WP\Performance\Optimizations\Minification\Minifier as Minifier_Interface;

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
     * @param Filesystem                  $filesystem                  An instance of `Filesystem`.
     * @param Minified_Path_Builder       $minified_path_builder       An instance of `Minified_Path_Builder`.
     */
    public function __construct(
        protected readonly Logger $logger,
        protected readonly File_Minification_Evaluator $file_minification_evaluator,
        protected readonly Filesystem $filesystem,
        protected readonly Minified_Path_Builder $minified_path_builder,
    ) {}

    /**
     * Minify the given file as if it were served from the given target path.
     *
     * @throws Minification_Exception If the file could not be minified.
     *
     * @param File   $file        File to minify.
     * @param string $target_path Filesystem path (or directory) the minified contents will be served from.
     *
     * @return string Minified contents.
     */
    public function minify_to( File $file, string $target_path ): string {
        $source_path = $file->get_path();

        try {
            $minifier = new Minify_CSS();

            // Do  not inline small images and fonts as base64 data URIs, keep referencing them by URL
            $minifier->setImportExtensions( array() );

            if ( is_string( $source_path ) && $this->filesystem->exists( $source_path ) ) {
                /*
                 * Given both the source and the destination, `Minify` rewrites relative
                 * `url()` and `@import` paths so they still resolve from the target path.
                 * It only does so when it knows where the CSS came from, which means we have
                 * to pass it a readable path instead of just the file contents.
                 *
                 * We have to call `execute()` instead of `minify()` to prevent saving the file.
                 */
                $minifier->add( $source_path );
                $minified_contents = $minifier->execute( $target_path );
            } else {
                /*
                 * No local copy to resolve against (probably a cross-origin stylesheet),
                 * so we minify the contents as they are. Any relative paths they contain are left alone.
                 */
                $minifier->add( $file->get_contents() );
                $minified_contents = $minifier->execute();
            }
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

        $relative_path = $this->minified_path_builder->build_relative_path(
            $file->get_url(),
            $file->get_content_hash()
        );

        $minified_contents = $this->minify_to(
            $file,
            $this->filesystem->build_path( Output_Directory::MINIFIED->value, $relative_path )
        );

        return new File(
            $this->logger,
            $relative_path,
            $minified_contents
        );
    }

}
