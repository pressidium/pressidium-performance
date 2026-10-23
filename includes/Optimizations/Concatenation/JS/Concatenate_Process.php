<?php
/**
 * JS concatenation background process.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\JS;

use Pressidium\WP\Performance\Options\Options;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Background_Process;
use Pressidium\WP\Performance\Files\File;
use Pressidium\WP\Performance\Files\File_Reader;
use Pressidium\WP\Performance\SRI_Validator;
use Pressidium\WP\Performance\URL_Builder;
use Pressidium\WP\Performance\Enumerations\Exclusion_Reason;
use Pressidium\WP\Performance\Enumerations\Output_Directory;
use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenate_Payload;
use Pressidium\WP\Performance\Optimizations\Concatenation_File_Record;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Files_Table;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Table;
use Pressidium\WP\Performance\Optimizations\Minification\JS\Minifier as JS_Minifier;
use Pressidium\WP\Performance\Utils\Numeric_Utils;
use Pressidium\WP\Performance\Exceptions\Concatenation_Exception;
use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;
use Pressidium\WP\Performance\Exceptions\Minification_Exception;

use Pressidium\WP\Performance\Dependencies\Peast\Syntax\EncodingException;

use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenate_Process class.
 *
 * @since 1.0.0
 */
final class Concatenate_Process extends Background_Process {

    /**
     * @var string Unique prefix to use for the queue.
     */
    protected string $prefix = 'pressidium_performance';

    /**
     * @var string Action to perform.
     */
    protected string $action = 'concatenate_js_process';

    /**
     * Concatenate_Process constructor.
     *
     * @param Options                    $options
     * @param Logger                     $logger
     * @param File_Reader                $file_reader
     * @param SRI_Validator              $sri_validator
     * @param URL_Builder                $url_builder
     * @param Concatenator               $concatenator
     * @param Concatenations_Table       $concatenations_table
     * @param Concatenations_Files_Table $concatenations_files_table
     * @param JS_Minifier                $minifier                   An instance of the JS `Minifier`.
     * @param bool                       $should_minify              Whether to minify each script before it's written.
     */
    public function __construct(
        private readonly Options $options,
        private readonly Logger $logger,
        private readonly File_Reader $file_reader,
        private readonly SRI_Validator $sri_validator,
        private readonly URL_Builder $url_builder,
        private readonly Concatenator $concatenator,
        private readonly Concatenations_Table $concatenations_table,
        private readonly Concatenations_Files_Table $concatenations_files_table,
        private readonly JS_Minifier $minifier,
        private readonly bool $should_minify,
    ) {
        parent::__construct();
    }

    /**
     * Return the logger instance.
     *
     * @return Logger
     */
    protected function get_logger(): Logger {
        return $this->logger;
    }

    /**
     * Log a debug message.
     *
     * @param string $message Message to log.
     *
     * @return void
     */
    private function log_debug( string $message ): void {
        $this->logger->debug( sprintf( '[%s] %s', esc_html( $this->action ), esc_html( $message ) ) );
    }

    /**
     * Log an informational message.
     *
     * @param string $message Message to log.
     *
     * @return void
     */
    private function log_info( string $message ): void {
        $this->logger->info( sprintf( '[%s] %s', esc_html( $this->action ), esc_html( $message ) ) );
    }

    /**
     * Log a warning message.
     *
     * @param string $message Message to log.
     *
     * @return void
     */
    private function log_warning( string $message ): void {
        $this->logger->warning( sprintf( '[%s] %s', esc_html( $this->action ), esc_html( $message ) ) );
    }

    /**
     * Log an error message.
     *
     * @param string $message Message to log.
     *
     * @return void
     */
    private function log_error( string $message ): void {
        $this->logger->error( sprintf( '[%s] %s', esc_html( $this->action ), esc_html( $message ) ) );
    }

    /**
     * Actually concatenate the given files.
     *
     * @throws Concatenation_Exception If the files could not be concatenated.
     * @throws Filesystem_Exception    If the file could not be written.
     *
     * @param string $file_uri        URI of the file to concatenate.
     * @param string $contents        Contents of the file to concatenate.
     * @param string $aggregated_hash Aggregated hash of the concatenated files.
     * @param string $type            Type of the concatenated files.
     *
     * @return void
     */
    private function concatenate_file(
        string $file_uri,
        string $contents,
        string $aggregated_hash,
        string $type
    ): void {
        $this->log_info(
            sprintf(
                'Concatenating script (%s, %s, %s)',
                esc_url( $file_uri ),
                esc_html( $aggregated_hash ),
                esc_html( $type )
            )
        );

        /*
         * Append the file contents to the partial file of the bundle. The record is
         * only stored once the bundle is complete, see `complete_bundle()`.
         */
        $partial_path = $this->concatenator->concatenate( $contents, $type, $aggregated_hash );

        $this->log_info(
            sprintf(
                'Concatenated script (%s, %s, %s) to partial file: %s',
                esc_url( $file_uri ),
                esc_html( $aggregated_hash ),
                esc_html( $type ),
                esc_html( $partial_path )
            )
        );
    }

    /**
     * Remember the post whose scripts are being concatenated by this chain.
     *
     * @param int|false $post_id Post ID to which the scripts belong.
     *
     * @return void
     */
    private function track_post( int|false $post_id ): void {
        $chain_id = $this->get_chain_id();

        update_option( "concatenate_process_{$chain_id}_post_id", $post_id );
    }

    /**
     * Remember that the bundle with the given aggregated hash is being built by this chain.
     *
     * That way, it can be completed once the queue is empty.
     *
     * @param string $aggregated_hash Aggregated hash of the bundle.
     * @param string $type            Type of the bundle.
     *
     * @return void
     */
    private function track_bundle( string $aggregated_hash, string $type ): void {
        $chain_id = $this->get_chain_id();
        $bundles  = get_option( "concatenate_process_{$chain_id}_bundles" );

        if ( ! is_array( $bundles ) ) {
            $bundles = array();
        }

        $bundles[ $aggregated_hash ] = $type;

        update_option( "concatenate_process_{$chain_id}_bundles", $bundles );

        $this->log_info( 'Storing aggregated hash: ' . esc_html( $aggregated_hash ) . ' (' . $chain_id . ')' );
    }

    /**
     * Return the state of the builds of this chain.
     *
     * @return array<string, array<string, mixed>> Build states, keyed by aggregated hash.
     */
    private function get_builds(): array {
        $chain_id = $this->get_chain_id();
        $builds   = get_option( "concatenate_process_{$chain_id}_builds" );

        return is_array( $builds ) ? $builds : array();
    }

    /**
     * Return the state of the build of the bundle with the given aggregated hash.
     *
     * Files of the same bundle may be processed across several requests,
     * so the state of its build is stored in an option keyed by the chain ID.
     *
     * @param string $aggregated_hash Aggregated hash of the bundle.
     *
     * @return array<string, mixed> State of the build.
     */
    private function get_build( string $aggregated_hash ): array {
        $build = $this->get_builds()[ $aggregated_hash ] ?? array();

        return array_merge(
            array(
                'files'         => array(), // files processed so far, keyed by their URI hash, in page order
                'has_contents'  => false,   // whether anything has been written to the bundle
                'has_failed'    => false,   // whether the bundle could not be written
                'original_size' => 0,       // total size of the scripts written to the bundle, in bytes
            ),
            $build
        );
    }

    /**
     * Store the state of the build of the bundle with the given aggregated hash.
     *
     * @param string               $aggregated_hash Aggregated hash of the bundle.
     * @param array<string, mixed> $build           State of the build.
     *
     * @return void
     */
    private function save_build( string $aggregated_hash, array $build ): void {
        $chain_id = $this->get_chain_id();
        $builds   = $this->get_builds();

        $builds[ $aggregated_hash ] = $build;

        update_option( "concatenate_process_{$chain_id}_builds", $builds );
    }

    /**
     * Record the given file in the state of a build.
     *
     * @param array<string, mixed> $build            State of the build.
     * @param string               $file_uri_hash    MD5 hash of the URI of the file.
     * @param ?Exclusion_Reason    $exclusion_reason Why the file could not be included, if it was excluded.
     *
     * @return array<string, mixed> Updated state of the build.
     */
    private function record_file( array $build, string $file_uri_hash, ?Exclusion_Reason $exclusion_reason ): array {
        $build['files'][ $file_uri_hash ] = array(
            'position'         => count( $build['files'] ),
            'exclusion_reason' => $exclusion_reason?->value,
        );

        return $build;
    }

    /**
     * Exclude the given file from the bundle with the given aggregated hash.
     *
     * Every script runs at the position of its own `script` tag, so an excluded
     * script keeps being served on its own, without affecting the execution order.
     *
     * @param string           $aggregated_hash  Aggregated hash of the bundle.
     * @param string           $file_uri         URI of the file.
     * @param Exclusion_Reason $exclusion_reason Why the file could not be included.
     *
     * @return void
     */
    private function exclude_file(
        string $aggregated_hash,
        string $file_uri,
        Exclusion_Reason $exclusion_reason
    ): void {
        $build = $this->get_build( $aggregated_hash );
        $build = $this->record_file( $build, md5( $file_uri ), $exclusion_reason );

        $this->save_build( $aggregated_hash, $build );

        $this->log_warning(
            sprintf(
                'Excluding script from bundle %s (%s): %s',
                esc_html( $aggregated_hash ),
                esc_html( $exclusion_reason->value ),
                esc_url( $file_uri )
            )
        );
    }

    /**
     * Discard the bundle with the given aggregated hash, and release its claim.
     *
     * That way, a later request can claim the build again.
     *
     * @param string $aggregated_hash Aggregated hash of the bundle.
     *
     * @return void
     */
    private function fail_bundle( string $aggregated_hash ): void {
        $this->concatenator->discard( $aggregated_hash );
        $this->concatenations_table->release_build( $aggregated_hash );
    }

    /**
     * Return the file records of the given build.
     *
     * @param int                  $concatenation_id ID of the concatenation.
     * @param array<string, mixed> $build            State of the build.
     *
     * @return Concatenation_File_Record[]
     */
    private function get_file_records( int $concatenation_id, array $build ): array {
        $file_records = array();

        foreach ( $build['files'] as $file_uri_hash => $file ) {
            $exclusion_reason = $file['exclusion_reason'] === null
                ? null
                : Exclusion_Reason::from( $file['exclusion_reason'] );

            // Scripts are never split into segments, every included script is in the one bundle
            $file_record = new Concatenation_File_Record();
            $file_record
                ->set_concatenation_id( $concatenation_id )
                ->set_file_uri_hash( (string) $file_uri_hash )
                ->set_position( $file['position'] )
                ->set_segment( $exclusion_reason === null ? 0 : null )
                ->set_is_excluded( $exclusion_reason !== null )
                ->set_exclusion_reason( $exclusion_reason );

            $file_records[] = $file_record;
        }

        return $file_records;
    }

    /**
     * Return the size of the file at the given path.
     *
     * @param string $file_path Path of the file.
     *
     * @return ?int Size in bytes, or `null` if it could not be determined.
     */
    private function get_file_size( string $file_path ): ?int {
        // The file was just moved into place, make sure we don't get a stale size
        clearstatcache( true, $file_path );

        $size = filesize( $file_path );

        return $size === false ? null : $size;
    }

    /**
     * Close the partial file of the given bundle and move it into place, then publish its record.
     *
     * The bundle is an unterminated object literal until it's closed, so the record is only
     * marked as ready after the file is in place and the files of the bundle are stored.
     *
     * @param string               $aggregated_hash Aggregated hash of the bundle.
     * @param array<string, mixed> $build           State of the build.
     *
     * @return void
     */
    private function complete_bundle( string $aggregated_hash, array $build ): void {
        // The record that claimed the build
        $concatenation_record = $this->concatenations_table->get_concatenation_record( $aggregated_hash );

        if ( $concatenation_record === null ) {
            $this->log_error( sprintf( 'Could not find the record of bundle %s', esc_html( $aggregated_hash ) ) );

            $this->fail_bundle( $aggregated_hash );
            return;
        }

        if ( $concatenation_record->get_is_ready() ) {
            /*
             * Another run already completed and published this bundle. Completing it again would
             * replace the published bundle and its files with this run's view of the build, so
             * only discard what this run may have left behind.
             */
            $this->log_info( sprintf( 'Bundle %s was already published, skipping', esc_html( $aggregated_hash ) ) );

            $this->concatenator->discard( $aggregated_hash );
            return;
        }

        /*
         * Every script may have been excluded, in which case there is no bundle. The record
         * is still published, so we don't keep trying to build a bundle with nothing to concatenate.
         */
        $concatenated_uri = '';
        $optimized_size   = 0;

        if ( $build['has_contents'] ) {
            $concatenated_file_location = $this->concatenator->complete( $aggregated_hash );

            if ( $concatenated_file_location === null ) {
                $this->log_error( sprintf( 'Could not complete bundle %s', esc_html( $aggregated_hash ) ) );

                $this->fail_bundle( $aggregated_hash );
                return;
            }

            // Build the concatenated URI
            $concatenated_uri = $this->url_builder->build_url(
                Output_Directory::CONCATENATED->value,
                basename( $concatenated_file_location )
            );

            $optimized_size = $this->get_file_size( $concatenated_file_location ) ?? 0;
        }

        $file_records = $this->get_file_records( $concatenation_record->get_id(), $build );

        if ( ! $this->concatenations_files_table->replace_files( $concatenation_record->get_id(), $file_records ) ) {
            $this->fail_bundle( $aggregated_hash );
            return;
        }

        $included_files = array_filter(
            $file_records,
            function ( Concatenation_File_Record $file_record ): bool {
                return ! $file_record->is_excluded();
            }
        );

        // Publish the concatenation record
        $concatenation_record
            ->set_concatenated_uri( $concatenated_uri )
            ->set_is_minified( $this->should_minify )
            ->set_files_count( count( $included_files ) )
            ->set_original_size( $build['original_size'] )
            ->set_optimized_size( $optimized_size );

        $this->concatenations_table->publish_build( $concatenation_record );
    }

    /**
     * Return the contents to write to the concatenated file.
     *
     * Chunks are keyed by the hash of the URI of their script, which is what the
     * `script` tags that run them pass to `runChunk()`, see `Processor::postprocess_by_type()`.
     * It never has to be escaped, unlike the URI itself.
     *
     * @throws EncodingException       If the source is not valid UTF-8.
     * @throws Concatenation_Exception If the source could not be encoded as a string.
     * @throws Throwable               If the source could not be parsed.
     *
     * @param string $file_uri    URI of the file to concatenate.
     * @param File   $file        File to concatenate.
     * @param string $script_type Type of the script, either 'script' or 'module'.
     *
     * @return ?string Contents to write, or `null` if the file cannot be concatenated.
     */
    private function get_contents_to_write( string $file_uri, File $file, string $script_type ): ?string {
        // Decide how to run the script based on the source as written, before minifying it
        $evaluator = new JS_Evaluator( $file->get_contents(), $script_type );
        $is_iife   = $evaluator->is_program_an_iife();

        if ( ! $is_iife && $evaluator->has_declarations_lost_in_eval() ) {
            /*
             * The source would be run via an indirect `eval`, which would scope
             * some of its declarations (e.g. `let`, `const`, `class`) to the `eval` itself
             */
            return null;
        }

        $file_contents = $this->maybe_minify( $file );

        $this->log_debug(
            $is_iife
                ? 'Source is safe to be wrapped in a function'
                : 'Source is suspected to contain global variables, storing it as a string'
        );

        // The source is valid UTF-8, since the evaluator could parse it, so it can be encoded as is
        return Chunk_Encoder::encode( $file_uri, $file_contents, $is_iife );
    }

    /**
     * Minify the given script, if minification is enabled.
     *
     * Minification is only an optimization, so if it fails, the script is kept as is.
     *
     * @param File $file Script to minify.
     *
     * @return string Contents to write.
     */
    private function maybe_minify( File $file ): string {
        if ( ! $this->should_minify ) {
            return $file->get_contents();
        }

        try {
            return $this->minifier->minify_contents( $file );
        } catch ( Minification_Exception $exception ) {
            $this->log_warning(
                sprintf(
                    'Could not minify script, concatenating it as is: %s (%s)',
                    esc_url( $file->get_url() ),
                    esc_html( $exception->getMessage() )
                )
            );

            return $file->get_contents();
        }
    }

    /**
     * Perform task with queued item.
     *
     * Perform any actions required on each queued item.
     * Return the modified item for further processing
     * in the next pass through. Or, return `false` to
     * remove the item from the queue.
     *
     * @param mixed $item Queued item to iterate over.
     *
     * @return mixed
     */
    protected function task( $item ) {
        $this->log_debug( 'Processing task in the background...' );

        if ( ! ( $item instanceof Concatenate_Payload ) ) {
            // Queued item is not an instance of `Concatenate_Payload`, remove it from the queue
            $this->log_warning( 'Queued item is not a valid payload, removing it from queue.' );
            return false;
        }

        $file_uri        = $item->get_file_uri();
        $file_uri_hash   = md5( $file_uri );
        $sri_hashes      = $item->get_sri_hashes();
        $script_type     = $item->get_type();
        $aggregated_hash = $item->get_aggregated_hash();

        $this->track_post( $item->get_post_id() );
        $this->track_bundle( $aggregated_hash, $script_type );

        $build = $this->get_build( $aggregated_hash );

        if ( $build['has_failed'] ) {
            $this->log_debug( 'Bundle could not be written, removing file from queue: ' . esc_url( $file_uri ) );
            return false;
        }

        if ( isset( $build['files'][ $file_uri_hash ] ) ) {
            // The same script is loaded more than once on the page, it only has to be concatenated once
            $this->log_debug( 'Script already processed, skipping: ' . esc_url( $file_uri ) );
            return false;
        }

        try {
            $file = $this->file_reader->read_remote( $file_uri );
        } catch ( Filesystem_Exception $exception ) {
            $this->log_error( sprintf( 'Could not read file: %s', esc_html( $file_uri ) ) );
            $this->logger->log_exception( $exception );

            $this->exclude_file( $aggregated_hash, $file_uri, Exclusion_Reason::READ_FAILED );
            return false;
        }

        if ( ! empty( $sri_hashes ) ) {
            $is_valid_sri = $this->sri_validator->is_valid( $file, $sri_hashes );

            if ( ! $is_valid_sri ) {
                // The browser would refuse the script, keep serving it on its own
                $this->exclude_file( $aggregated_hash, $file_uri, Exclusion_Reason::SRI_MISMATCH );
                return false;
            }

            $this->log_debug( 'SRI validation passes for file: ' . esc_html( $file_uri ) );
        }

        try {
            $contents_to_write = $this->get_contents_to_write( $file_uri, $file, $script_type );
        } catch ( EncodingException $exception ) {
            $this->log_error(
                sprintf( 'Script is not valid UTF-8: %s (%s)', esc_url( $file_uri ), esc_html( $exception->getMessage() ) )
            );

            $this->exclude_file( $aggregated_hash, $file_uri, Exclusion_Reason::INVALID_UTF8 );
            return false;
        } catch ( Throwable $throwable ) {
            $this->log_error(
                sprintf( 'Could not parse script: %s (%s)', esc_url( $file_uri ), esc_html( $throwable->getMessage() ) )
            );

            $this->exclude_file( $aggregated_hash, $file_uri, Exclusion_Reason::PARSE_ERROR );
            return false;
        }

        if ( $contents_to_write === null ) {
            // The script declares globals that would be lost when concatenated
            $this->exclude_file( $aggregated_hash, $file_uri, Exclusion_Reason::EVAL_SCOPE );
            return false;
        }

        try {
            $this->concatenate_file(
                $file_uri,
                $contents_to_write,
                $aggregated_hash,
                $script_type
            );
        } catch ( Concatenation_Exception | Filesystem_Exception $exception ) {
            $this->log_error( 'Could not concatenate file.' );
            $this->logger->log_exception( $exception );

            /*
             * Nothing is wrong with the script itself, so instead of excluding it,
             * give up on this build and let a later request try again.
             */
            $build['has_failed'] = true;

            $this->save_build( $aggregated_hash, $build );
            return false;
        }

        $build = $this->record_file( $build, $file_uri_hash, null );

        $build['has_contents']   = true;
        $build['original_size'] += $file->get_size_in_bytes();

        $this->save_build( $aggregated_hash, $build );

        $this->log_debug( 'File is done processing, removing it from the queue.' );

        return false;
    }

    /**
     * Complete processing.
     *
     * @return void
     */
    protected function complete(): void {
        parent::complete();

        $chain_id = $this->get_chain_id();

        $bundles = get_option( "concatenate_process_{$chain_id}_bundles" );
        $post_id = get_option( "concatenate_process_{$chain_id}_post_id" );

        if ( $bundles && is_array( $bundles ) ) {
            foreach ( array_keys( $bundles ) as $aggregated_hash ) {
                $build = $this->get_build( (string) $aggregated_hash );

                if ( $build['has_failed'] ) {
                    $this->fail_bundle( (string) $aggregated_hash );
                    continue;
                }

                $this->complete_bundle( (string) $aggregated_hash, $build );
            }

            // Clean post from cache
            if ( $post_id ) {
                clean_post_cache( $post_id );
            }

            // Clean up the stored bundles and post ID
            delete_option( "concatenate_process_{$chain_id}_bundles" );
            delete_option( "concatenate_process_{$chain_id}_post_id" );
        }

        delete_option( "concatenate_process_{$chain_id}_builds" );

        // Written by previous versions, which kept a single hash per type
        delete_option( "concatenate_process_{$chain_id}_hash" );
    }

    /**
     * Return the items to process.
     *
     * Limited to the items of the next batch.
     *
     * @return array
     */
    public function get_items(): array {
        $batches = $this->get_batches( 1 );

        if ( empty( $batches ) ) {
            return array();
        }

        $next_batch = $batches[0];
        $items      = array();

        foreach ( $next_batch->data as $item ) {
            if ( ! ( $item instanceof Concatenate_Payload ) ) {
                continue;
            }

            try {
                $file = $this->file_reader->read_remote( $item->get_file_uri() );
            } catch ( Filesystem_Exception $exception ) {
                continue;
            }

            $items[] = array(
                'location' => $file->get_url(),
                'type'     => $file->get_file_type(),
                'size'     => Numeric_Utils::format_bytes_to_decimal( $file->get_size_in_bytes() ),
            );
        }

        return $items;
    }

}
