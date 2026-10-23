<?php
/**
 * CSS concatenate background process.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\CSS;

use Pressidium\WP\Performance\Background_Process;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Files_Table;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Table;
use Pressidium\WP\Performance\Enumerations\Exclusion_Reason;
use Pressidium\WP\Performance\Enumerations\Output_Directory;
use Pressidium\WP\Performance\Exceptions\Concatenation_Exception;
use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;
use Pressidium\WP\Performance\Exceptions\Minification_Exception;
use Pressidium\WP\Performance\Files\File;
use Pressidium\WP\Performance\Files\File_Reader;
use Pressidium\WP\Performance\Files\File_Writer;
use Pressidium\WP\Performance\SRI_Validator;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenate_Payload;
use Pressidium\WP\Performance\Optimizations\Concatenation_File_Record;
use Pressidium\WP\Performance\Optimizations\Minification\CSS\Minifier as CSS_Minifier;
use Pressidium\WP\Performance\URL_Builder;
use Pressidium\WP\Performance\Utils\Numeric_Utils;

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
     * @var string The unique prefix to use for the queue.
     */
    protected string $prefix = 'pressidium_performance';

    /**
     * @var string The action to perform.
     */
    protected string $action = 'concatenate_css_process';

    /**
     * Concatenate_Process constructor.
     *
     * @param Logger                     $logger                     An instance of `Logger`.
     * @param File_Reader                $file_reader                An instance of `File_Reader`.
     * @param File_Writer                $file_writer                An instance of `File_Writer`.
     * @param URL_Builder                $url_builder                An instance of `URL_Builder`.
     * @param CSS_Minifier               $minifier                   An instance of the CSS `Minifier`.
     * @param Concatenator               $concatenator               An instance of `Concatenator`.
     * @param Concatenations_Table       $concatenations_table       An instance of `Concatenations_Table`.
     * @param Concatenations_Files_Table $concatenations_files_table An instance of `Concatenations_Files_Table`.
     * @param SRI_Validator              $sri_validator              An instance of `SRI_Validator`.
     * @param array                      $settings                   Settings.
     */
    public function __construct(
        protected readonly Logger $logger,
        protected readonly File_Reader $file_reader,
        protected readonly File_Writer $file_writer,
        protected readonly URL_Builder $url_builder,
        protected readonly CSS_Minifier $minifier,
        protected readonly Concatenator $concatenator,
        protected readonly Concatenations_Table $concatenations_table,
        protected readonly Concatenations_Files_Table $concatenations_files_table,
        protected readonly SRI_Validator $sri_validator,
        protected readonly array $settings,
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
     * Actually concatenate the given files.
     *
     * @throws Concatenation_Exception If the file could not be written.
     * @throws Filesystem_Exception    If the file could not be written.
     *
     * @param string $file_uri    URI of the file to concatenate.
     * @param string $contents    File contents.
     * @param string $segment_key Key of the segment of the bundle to append to.
     * @param string $type        Type of the concatenated files.
     *
     * @return void
     */
    private function concatenate_file( string $file_uri, string $contents, string $segment_key, string $type ): void {
        // Concatenate files
        $this->logger->info(
            sprintf( 'Concatenating stylesheet (%s, %s)', esc_url( $file_uri ), esc_html( $segment_key ) )
        );

        /*
         * Append the file contents to the partial file of the segment. The record is
         * only stored once the bundle is complete, see `complete_bundle()`.
         */
        $this->concatenator->concatenate( $contents, $type, $segment_key );
    }

    /**
     * Remember the post whose stylesheets are being concatenated by this chain.
     *
     * @param int|false $post_id Post ID to which the stylesheets belong.
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
                'segment'       => -1,      // index of the current segment, `-1` before anything is written
                'is_open'       => false,   // whether the next stylesheet can be appended to the current segment
                'files'         => array(), // files processed so far, keyed by their URI hash, in page order
                'original_size' => 0,       // total size of the stylesheets written to the bundle, in bytes
                'has_failed'    => false,   // whether the bundle could not be written
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
     * @param ?int                 $segment          Index of the segment holding the file, if any.
     * @param ?Exclusion_Reason    $exclusion_reason Why the file could not be included, if it was excluded.
     *
     * @return array<string, mixed> Updated state of the build.
     */
    private function record_file(
        array $build,
        string $file_uri_hash,
        ?int $segment,
        ?Exclusion_Reason $exclusion_reason
    ): array {
        $build['files'][ $file_uri_hash ] = array(
            'position'         => count( $build['files'] ),
            'segment'          => $segment,
            'exclusion_reason' => $exclusion_reason?->value,
        );

        return $build;
    }

    /**
     * Exclude the given file from the bundle with the given aggregated hash.
     *
     * The file keeps being served on its own, so the current segment is closed, and
     * the next stylesheet starts a new one. That way, the order of the cascade is preserved.
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
        $build = $this->record_file( $build, md5( $file_uri ), null, $exclusion_reason );

        $build['is_open'] = false;

        $this->save_build( $aggregated_hash, $build );

        $this->logger->warning(
            sprintf(
                '[%s] Excluding stylesheet from bundle %s (%s): %s',
                esc_html( $this->action ),
                esc_html( $aggregated_hash ),
                esc_html( $exclusion_reason->value ),
                esc_url( $file_uri )
            )
        );
    }

    /**
     * Start a new segment in the given build.
     *
     * @param array<string, mixed> $build           State of the build.
     * @param string               $aggregated_hash Aggregated hash of the bundle.
     *
     * @return array<string, mixed> Updated state of the build.
     */
    private function open_segment( array $build, string $aggregated_hash ): array {
        $build['segment'] += 1;
        $build['is_open'] = true;

        // Start from scratch, in case a previous run left a partial file behind without completing it
        $this->concatenator->discard( Concatenator::get_segment_key( $aggregated_hash, $build['segment'] ) );

        return $build;
    }

    /**
     * Whether the given contents start with an `@import` rule.
     *
     * `Minify` moves any `@import` it could not merge (e.g. one pointing at another
     * origin) to the top of its output. That is only valid at the top of a stylesheet,
     * so these contents have to start a segment of their own.
     *
     * @link https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@import
     *
     * @param string $contents Contents to check.
     *
     * @return bool Whether the contents start with an `@import` rule.
     */
    private function has_import( string $contents ): bool {
        return (bool) preg_match( '/^\s*@import\b/i', $contents );
    }

    /**
     * Minify the given stylesheet so it can be appended to a bundle.
     *
     * Minifying rewrites relative `url()` and `@import` paths, so they still resolve
     * from the directory of the bundle, and merges local `@import`ed stylesheets in.
     *
     * @throws Minification_Exception If the stylesheet could not be minified.
     *
     * @param File $file Stylesheet to minify.
     *
     * @return string Contents to append.
     */
    private function get_contents_to_write( File $file ): string {
        return $this->minifier->minify_to( $file, $this->concatenator->get_bundle_directory() );
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
     * Discard the bundle with the given aggregated hash, and release its claim.
     *
     * That way, a later request can claim the build again.
     *
     * @param string               $aggregated_hash Aggregated hash of the bundle.
     * @param array<string, mixed> $build           State of the build.
     *
     * @return void
     */
    private function fail_bundle( string $aggregated_hash, array $build ): void {
        for ( $segment = 0; $segment <= $build['segment']; $segment += 1 ) {
            $segment_key = Concatenator::get_segment_key( $aggregated_hash, $segment );

            $this->concatenator->discard( $segment_key );
            $this->concatenator->delete( $segment_key );
        }

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

            $file_record = new Concatenation_File_Record();
            $file_record
                ->set_concatenation_id( $concatenation_id )
                ->set_file_uri_hash( (string) $file_uri_hash )
                ->set_position( $file['position'] )
                ->set_segment( $file['segment'] )
                ->set_is_excluded( $exclusion_reason !== null )
                ->set_exclusion_reason( $exclusion_reason );

            $file_records[] = $file_record;
        }

        return $file_records;
    }

    /**
     * Move the segments of the given bundle into place, then publish its record.
     *
     * The record is only marked as ready after its segments are in place and its files
     * are stored, so a ready record never points at a partially written stylesheet.
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
            $this->logger->error(
                sprintf(
                    '[%s] Could not find the record of bundle %s',
                    esc_html( $this->action ),
                    esc_html( $aggregated_hash )
                )
            );

            $this->fail_bundle( $aggregated_hash, $build );
            return;
        }

        if ( $concatenation_record->get_is_ready() ) {
            /*
             * Another run already completed and published this bundle. Completing it again would
             * replace (or, on failure, delete) the published segments and their files, so only
             * discard the partial segments this run may have left behind.
             */
            $this->logger->info(
                sprintf(
                    '[%s] Bundle %s was already published, skipping',
                    esc_html( $this->action ),
                    esc_html( $aggregated_hash )
                )
            );

            for ( $segment = 0; $segment <= $build['segment']; $segment += 1 ) {
                $this->concatenator->discard( Concatenator::get_segment_key( $aggregated_hash, $segment ) );
            }

            return;
        }

        $segment_paths = array();

        for ( $segment = 0; $segment <= $build['segment']; $segment += 1 ) {
            $segment_key   = Concatenator::get_segment_key( $aggregated_hash, $segment );
            $file_location = $this->concatenator->complete( $segment_key );

            if ( $file_location === null ) {
                $this->logger->error(
                    sprintf(
                        '[%s] Could not complete segment %d of bundle %s',
                        esc_html( $this->action ),
                        $segment,
                        esc_html( $aggregated_hash )
                    )
                );

                $this->fail_bundle( $aggregated_hash, $build );
                return;
            }

            $segment_paths[ $segment ] = $file_location;
        }

        $file_records = $this->get_file_records( $concatenation_record->get_id(), $build );

        if ( ! $this->concatenations_files_table->replace_files( $concatenation_record->get_id(), $file_records ) ) {
            $this->fail_bundle( $aggregated_hash, $build );
            return;
        }

        /*
         * Every stylesheet may have been excluded, in which case there are no segments. The record
         * is still published, so we don't keep trying to build a bundle with nothing to concatenate.
         */
        $concatenated_uri = isset( $segment_paths[0] )
            ? $this->url_builder->build_url( Output_Directory::CONCATENATED->value, basename( $segment_paths[0] ) )
            : '';

        $optimized_size = 0;

        foreach ( $segment_paths as $segment_path ) {
            $optimized_size += $this->get_file_size( $segment_path ) ?? 0;
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
            ->set_is_minified( true )
            ->set_files_count( count( $included_files ) )
            ->set_original_size( $build['original_size'] )
            ->set_optimized_size( $optimized_size );

        $this->concatenations_table->publish_build( $concatenation_record );
    }

    /**
     * Perform task with queued item.
     *
     * Perform any actions required on each queue item.
     * Return the modified item for further processing
     * in the next pass through. Or, return `false` to
     * remove the item from the queue.
     *
     * @param mixed $item Queue item to iterate over.
     *
     * @return mixed
     */
    protected function task( $item ) {
        $this->logger->debug( 'Processing concatenate task in the background...' );

        if ( ! ( $item instanceof Concatenate_Payload ) ) {
            // Queued item is not an instance of `Concatenate_Payload`, remove it from the queue
            $this->logger->warning(
                sprintf( '[%s] Queued item is not a valid payload, removing from queue.', esc_html( $this->action ) )
            );
            return false;
        }

        $file_uri        = $item->get_file_uri();
        $file_uri_hash   = md5( $file_uri );
        $sri_hashes      = $item->get_sri_hashes();
        $aggregated_hash = $item->get_aggregated_hash();

        $this->track_post( $item->get_post_id() );
        $this->track_bundle( $aggregated_hash, $item->get_type() );

        $build = $this->get_build( $aggregated_hash );

        if ( $build['has_failed'] ) {
            $this->logger->debug(
                sprintf(
                    '[%s] Bundle could not be written, removing file from queue: %s',
                    esc_html( $this->action ),
                    esc_url( $file_uri )
                )
            );
            return false;
        }

        if ( $item->starts_segment() && $build['is_open'] ) {
            /*
             * Styles that are not part of the bundle sit before this stylesheet on the page,
             * so close the current segment. Even if this stylesheet is not written (e.g. it
             * is empty, or excluded), the next one still has to start a new segment.
             */
            $build['is_open'] = false;

            $this->save_build( $aggregated_hash, $build );
        }

        if ( isset( $build['files'][ $file_uri_hash ] ) ) {
            // The same stylesheet is linked more than once on the page, it only has to be concatenated once
            $this->logger->debug(
                sprintf(
                    '[%s] Stylesheet already processed, skipping: %s',
                    esc_html( $this->action ),
                    esc_url( $file_uri )
                )
            );
            return false;
        }

        // Read the file
        try {
            $file = $this->file_reader->read_remote( $file_uri );
        } catch ( Filesystem_Exception $exception ) {
            $this->logger->error(
                sprintf( '[%s] Could not read file: %s', esc_html( $this->action ), esc_html( $file_uri ) )
            );
            $this->logger->log_exception( $exception );

            $this->exclude_file( $aggregated_hash, $file_uri, Exclusion_Reason::READ_FAILED );
            return false;
        }

        if ( ! empty( $sri_hashes ) && ! $this->sri_validator->is_valid( $file, $sri_hashes ) ) {
            /*
             * The stylesheet doesn't match the hashes in its `integrity` attribute, so the
             * browser would refuse it. Keep serving it on its own, instead of concatenating
             * contents the page would never have loaded.
             */
            $this->exclude_file( $aggregated_hash, $file_uri, Exclusion_Reason::SRI_MISMATCH );
            return false;
        }

        if ( trim( $file->get_contents() ) === '' ) {
            // Nothing to concatenate, its `link` tag can be disabled without losing anything
            $this->logger->debug(
                sprintf( '[%s] Stylesheet is empty, skipping: %s', esc_html( $this->action ), esc_url( $file_uri ) )
            );

            $this->save_build( $aggregated_hash, $this->record_file( $build, $file_uri_hash, null, null ) );
            return false;
        }

        try {
            $contents_to_write = $this->get_contents_to_write( $file );
        } catch ( Minification_Exception $exception ) {
            $this->logger->error(
                sprintf( '[%s] Could not minify stylesheet: %s', esc_html( $this->action ), esc_url( $file_uri ) )
            );
            $this->logger->log_exception( $exception );

            $this->exclude_file( $aggregated_hash, $file_uri, Exclusion_Reason::MINIFY_FAILED );
            return false;
        }

        /*
         * Start a new segment if the previous stylesheet was excluded, other styles sit before
         * this one on the page (or this is the first one), or if this stylesheet starts with
         * an `@import` rule, which has to be at the top.
         */
        if ( ! $build['is_open'] || $this->has_import( $contents_to_write ) ) {
            $build = $this->open_segment( $build, $aggregated_hash );
        }

        try {
            $this->concatenate_file(
                $file_uri,
                $contents_to_write,
                Concatenator::get_segment_key( $aggregated_hash, $build['segment'] ),
                $item->get_type()
            );
        } catch ( Concatenation_Exception | Filesystem_Exception $exception ) {
            $this->logger->error( sprintf( '[%s] Could not concatenate file.', esc_html( $this->action ) ) );
            $this->logger->log_exception( $exception );

            /*
             * Nothing is wrong with the stylesheet itself, so instead of excluding it,
             * give up on this build and let a later request try again.
             */
            $build['has_failed'] = true;

            $this->save_build( $aggregated_hash, $build );
            return false;
        }

        $build = $this->record_file( $build, $file_uri_hash, $build['segment'], null );

        $build['original_size'] += $file->get_size_in_bytes();

        $this->save_build( $aggregated_hash, $build );

        $this->logger->debug( sprintf( '[%s] File processed, removing from queue.', esc_html( $this->action ) ) );
        return false;
    }

    /**
     * Complete processing.
     *
     * @return void
     */
    protected function complete(): void {
        parent::complete();

        $this->logger->info( sprintf( '[%s] All concatenations were complete.', esc_html( $this->action ) ) );

        $chain_id = $this->get_chain_id();
        $bundles  = get_option( "concatenate_process_{$chain_id}_bundles" );
        $post_id  = get_option( "concatenate_process_{$chain_id}_post_id" );

        if ( is_array( $bundles ) ) {
            foreach ( array_keys( $bundles ) as $aggregated_hash ) {
                $build = $this->get_build( (string) $aggregated_hash );

                if ( $build['has_failed'] ) {
                    $this->fail_bundle( (string) $aggregated_hash, $build );
                    continue;
                }

                $this->complete_bundle( (string) $aggregated_hash, $build );
            }
        }

        if ( $post_id ) {
            clean_post_cache( $post_id );
        }

        delete_option( "concatenate_process_{$chain_id}_bundles" );
        delete_option( "concatenate_process_{$chain_id}_post_id" );
        delete_option( "concatenate_process_{$chain_id}_builds" );
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
