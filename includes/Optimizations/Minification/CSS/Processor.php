<?php
/**
 * CSS minification processor.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification\CSS;

use Pressidium\WP\Performance\Background_Process;
use Pressidium\WP\Performance\Files\File_Reader;
use Pressidium\WP\Performance\Files\File_Writer;
use Pressidium\WP\Performance\HTML_Processor;
use Pressidium\WP\Performance\Options\Options;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Database\Tables\Optimizations_Table;
use Pressidium\WP\Performance\Optimizations\Minification\File_Minification_Evaluator;
use Pressidium\WP\Performance\Optimizations\Minification\Minification_Payload;
use Pressidium\WP\Performance\Optimizations\Minification\Minification_Processor;
use Pressidium\WP\Performance\Enumerations\HTML_Tag;

use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;
use Pressidium\WP\Performance\URL_Builder;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Processor class.
 *
 * @since 1.0.0
 */
final class Processor extends Minification_Processor {

    /**
     * @var Minify_Process Process to minify in the background.
     */
    private Minify_Process $minify_process;

    /**
     * Processor constructor.
     *
     * @param Options                     $options                     An instance of `Options`.
     * @param Logger                      $logger                      An instance of `Logger`.
     * @param File_Reader                 $file_reader                 An instance of `File_Reader`.
     * @param File_Writer                 $file_writer                 An instance of `File_Writer`.
     * @param File_Minification_Evaluator $file_minification_evaluator An instance of `File_Minification_Evaluator`.
     * @param URL_Builder                 $url_builder                 An instance of `URL_Builder`.
     * @param Minifier                    $minifier                    An instance of `Minifier`.
     * @param Optimizations_Table         $optimizations_table         An instance of `Optimizations_Table`.
     */
    public function __construct(
        Options $options,
        Logger $logger,
        File_Reader $file_reader,
        File_Writer $file_writer,
        File_Minification_Evaluator $file_minification_evaluator,
        URL_Builder $url_builder,
        Minifier $minifier,
        Optimizations_Table $optimizations_table
    ) {
        parent::__construct(
            $options,
            $logger,
            $file_reader,
            $file_writer,
            $file_minification_evaluator,
            $url_builder,
            $minifier,
            $optimizations_table
        );

        // Initialize the minify background process
        $this->minify_process = new Minify_Process(
            $logger,
            $file_reader,
            $file_writer,
            $url_builder,
            $minifier,
            $optimizations_table
        );
    }

    /**
     * Return the background process.
     *
     * @return ?Background_Process
     */
    public function get_background_process(): ?Background_Process {
        return $this->minify_process;
    }

    /**
     * Schedule the stylesheet at the given URI for minification if needed.
     *
     * @param string $stylesheet_uri URI of the stylesheet to minify.
     *
     * @return void
     */
    private function maybe_schedule_stylesheet_for_minification( string $stylesheet_uri ): void {
        try {
            if ( $this->minify_process->is_active() ) {
                // A minification process is already active, bail early
                $this->logger->debug( 'A minification process is already active, skipping...' );
                return;
            }

            $file = $this->file_reader->read_remote( $stylesheet_uri );

            if ( ! $this->file_minification_evaluator->should_be_minified( $file ) ) {
                // File should not be minified, bail early
                $this->logger->debug( "File '{$stylesheet_uri}' should not be minified, skipping..." );
                return;
            }

            // Schedule the file for minification
            $this->logger->debug( "Scheduling file for minification: {$file->get_url()}" );
            $this->minify_process->push_to_queue(
                new Minification_Payload(
                    $file->get_url(),
                    get_the_ID()
                )
            );
        } catch ( Filesystem_Exception $exception ) {
            $this->logger->log_exception( $exception );
        }
    }

    /**
     * Process the HTML to schedule any stylesheets for minification.
     *
     * @param HTML_Processor $processor HTML processor.
     *
     * @return void
     */
    public function process( HTML_Processor $processor ): void {
        if ( $processor->get_tag() !== HTML_Tag::LINK->value ) {
            // Not a link tag, bail early
            return;
        }

        $href = $processor->get_attribute( 'href' );

        if ( empty( $href ) ) {
            // No `href` attribute, bail early
            return;
        }

        $rel = $processor->get_attribute( 'rel' );

        if ( $rel !== 'stylesheet' ) {
            // Not a stylesheet, bail early
            return;
        }

        $this->logger->debug( 'Parsing tag using the CSS minification processor...' );

        $optimization_record = $this->optimizations_table->get_optimization_record( $href );

        $this->logger->debug( "Maybe schedule stylesheet '{$href}' for minification..." );
        $this->maybe_schedule_stylesheet_for_minification( $href );

        if ( ! $optimization_record ) {
            // There are no optimization records, bail early
            $this->logger->debug( "No optimization records found for '{$href}'" );
            return;
        }

        $this->logger->debug( "Replacing stylesheet '{$href}' with optimized version..." );
        $processor->set_attribute( 'href', $optimization_record->get_optimized_uri() );
    }

    /**
     * Finish the processing.
     *
     * @return void
     */
    public function complete_process(): void {
        $this->logger->debug( 'CSS processor has finished pushing stylesheets for minification to the queue.' );

        $this->logger->debug( 'Saving minification queue...' );
        $this->logger->debug( 'Dispatching minify process...' );

        /*
         * Save queue and dispatch the minify process,
         * start minifying files in the background.
         */
        $this->minify_process->save()->dispatch();
    }

}
