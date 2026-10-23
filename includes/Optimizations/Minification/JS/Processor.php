<?php
/**
 * JavaScript minification processor.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification\JS;

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
use Pressidium\WP\Performance\SRI_Validator;

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
     * @var string[] Script types whose contents are JavaScript, and can therefore be minified.
     */
    const MINIFIABLE_SCRIPT_TYPES = array(
        'module',
        'text/javascript',
        // Legacy/obsolete MIME types
        'application/javascript',
        'text/ecmascript',
        'application/ecmascript',
        'text/x-javascript',
        'application/x-javascript',
        'text/jscript',
    );

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
     * @param SRI_Validator               $sri_validator               An instance of `SRI_Validator`.
     */
    public function __construct(
        Options $options,
        Logger $logger,
        File_Reader $file_reader,
        File_Writer $file_writer,
        File_Minification_Evaluator $file_minification_evaluator,
        URL_Builder $url_builder,
        Minifier $minifier,
        Optimizations_Table $optimizations_table,
        SRI_Validator $sri_validator
    ) {
        parent::__construct(
            $options,
            $logger,
            $file_reader,
            $file_writer,
            $file_minification_evaluator,
            $url_builder,
            $minifier,
            $optimizations_table,
            $sri_validator
        );

        // Initialize the minify background process
        $this->minify_process = new Minify_Process(
            $logger,
            $file_reader,
            $file_writer,
            $url_builder,
            $minifier,
            $optimizations_table,
            $sri_validator
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
     * Schedule the script at the given URI for minification if needed.
     *
     * @param string                   $script_uri URI of the script to minify.
     * @param ?array<string, string[]>  $sri_hashes Integrity metadata of the `<script>` tag, if any.
     *
     * @return void
     */
    private function maybe_schedule_script_for_minification( string $script_uri, ?array $sri_hashes ): void {
        try {
            if ( $this->minify_process->is_active() ) {
                // A minification process is already active, bail early
                $this->logger->debug( 'A minification process is already active, skipping...' );
                return;
            }

            if ( ! $this->file_minification_evaluator->should_be_minified_by_url( $script_uri ) ) {
                // File should not be minified based on its URL, bail early
                $this->logger->debug( "File '{$script_uri}' should not be minified based on its URL, skipping..." );
                return;
            }

            $this->logger->debug( "Could not determine if the file '{$script_uri}' should be minified based on its URL, reading the file to evaluate it..." );

            $file = $this->file_reader->read_remote( $script_uri );

            if ( ! $this->file_minification_evaluator->should_be_minified( $file ) ) {
                // File should not be minified, bail early
                $this->logger->debug( "File '{$script_uri}' should not be minified, skipping..." );
                return;
            }

            // Schedule the file for minification
            $this->logger->debug( "Scheduling file for minification: {$file->get_url()}" );
            $this->minify_process->push_to_queue(
                new Minification_Payload(
                    $file->get_url(),
                    get_the_ID(),
                    $sri_hashes
                )
            );
        } catch ( Filesystem_Exception $exception ) {
            $this->logger->log_exception( $exception );
        }
    }

    /**
     * Whether a `<script>` tag with the given `type` attribute contains minifiable JavaScript.
     *
     * We treat a missing or empty `type` as a script, so it's minifiable. Anything else has to be
     * an explicitly JavaScript type, so other stuff, like data blocks (e.g. `application/ld+json`)
     * are left alone.
     *
     * @param string|bool|null $type Value of the `type` attribute, as returned by the HTML processor.
     *
     * @return bool
     */
    private function is_minifiable_script_type( $type ): bool {
        if ( ! is_string( $type ) || trim( $type ) === '' ) {
            // No `type` attribute (or an empty one), so we treat this as minifiable
            return true;
        }

        // Strip any MIME parameters (e.g. `text/javascript; charset=utf-8`)
        $parts = explode( ';', $type );
        $mime  = strtolower( trim( $parts[0] ) );

        return in_array( $mime, self::MINIFIABLE_SCRIPT_TYPES, true );
    }

    /**
     * Process the HTML to schedule any JS scripts for minification.
     *
     * @param HTML_Processor $processor HTML processor.
     *
     * @return void
     */
    public function process( HTML_Processor $processor ): void {
        if ( $processor->get_tag() !== HTML_Tag::SCRIPT->value ) {
            // Not a script tag, bail early
            return;
        }

        $src = $processor->get_attribute( 'src' );

        if ( empty( $src ) ) {
            // No `src` attribute, bail early
            return;
        }

        if ( ! $this->is_minifiable_script_type( $processor->get_attribute( 'type' ) ) ) {
            // Not a script that can be minified, bail early
            return;
        }

        if ( ! $this->file_minification_evaluator->is_allowed_origin( $src ) ) {
            /*
             * The script is served from a host whose assets we may not minify. Bail before we
             * look up any optimization record, so that records created while third-party assets
             * were still allowed stop being applied.
             */
            $this->logger->debug( "Script '{$src}' is not served from an allowed host, skipping..." );
            return;
        }

        $this->logger->debug( 'Parsing tag using the JS minification processor...' );

        $sri_hashes = $processor->get_sri_hashes();

        $optimization_record = $this->optimizations_table->get_optimization_record( $src );

        if ( $optimization_record && $this->is_record_stale( $optimization_record, $sri_hashes ) ) {
            /*
             * The tag no longer lists the hash we validated the original script against, so
             * the script it points to has most likely changed under the same URI.
             *
             * Drop the record, to avoid serving a stale minified copy and let the evaluator
             * below schedule the script to be re-minified.
             */
            $this->logger->info(
                "Integrity metadata of '{$src}' no longer lists the hash we validated it against, invalidating its optimization record..."
            );

            $this->optimizations_table->delete_optimization_record( $src );

            $optimization_record = null;
        }

        $this->logger->debug( "Maybe schedule script '{$src}' for minification..." );
        $this->maybe_schedule_script_for_minification( $src, $sri_hashes );

        if ( ! $optimization_record ) {
            // There are no optimization records, bail early
            $this->logger->debug( "No optimization records found for '{$src}'" );
            return;
        }

        $this->logger->debug( "Replacing script '{$src}' with optimized version..." );
        $processor->set_attribute( 'src', $optimization_record->get_optimized_uri() );

        if ( $sri_hashes !== null ) {
            /*
             * We have validated the original script against one of these hashes server-side, so
             * we know we have minified the actual contents the tag asked for. We can safely drop
             * the `integrity` attribute here.
             */
            $processor->remove_attribute( 'integrity' );
        }
    }

    /**
     * Finish the processing.
     *
     * @return void
     */
    public function complete_process(): void {
        $this->logger->debug( 'JS processor has finished pushing scripts for minification to the queue.' );

        $this->logger->debug( 'Saving minification queue...' );
        $this->logger->debug( 'Dispatching minify process...' );

        /*
         * Save queue and dispatch the minify process,
         * start minifying files in the background.
         */
        $this->minify_process->save()->dispatch();
    }

}
