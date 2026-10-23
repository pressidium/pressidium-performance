<?php
/**
 * JavaScript concatenation processor.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\JS;

use Pressidium\WP\Performance\Background_Process;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Files_Table;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Pages_Table;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Table;
use Pressidium\WP\Performance\Files\File_Reader;
use Pressidium\WP\Performance\Files\File_Writer;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\SRI_Validator;
use Pressidium\WP\Performance\HTML_Processor;
use Pressidium\WP\Performance\Options\Options;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenator;
use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenate_Payload;
use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenation_Processor;
use Pressidium\WP\Performance\Optimizations\Concatenation_File_Record;
use Pressidium\WP\Performance\Optimizations\Concatenation_Record;
use Pressidium\WP\Performance\Optimizations\Minification\File_Minification_Evaluator;
use Pressidium\WP\Performance\Optimizations\Minification\JS\Minifier as JS_Minifier;
use Pressidium\WP\Performance\Settings;
use Pressidium\WP\Performance\URL_Builder;
use Pressidium\WP\Performance\Exclusion_Matcher;
use Pressidium\WP\Performance\Utils\Array_Utils;
use Pressidium\WP\Performance\Utils\WP_Utils;
use Pressidium\WP\Performance\Enumerations\HTML_Tag;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Processor class.
 *
 * @since 1.0.0
 */
final class Processor extends Concatenation_Processor {

    /**
     * @var string[] Types of scripts that can be concatenated.
     */
    const CONCATENABLE_TYPES = array( 'text/javascript', 'module' );

    /**
     * @var Concatenate_Process Process to concatenate in the background.
     */
    private Concatenate_Process $concatenate_process;

    /**
     * @var array<string, array<array<string, string>>> Metadata of the scripts to concatenate, grouped by type.
     */
    private array $script_metadata = array();

    /**
     * @var array<string, Concatenation_Record>|null Concatenation records stored in the database, grouped by type.
     */
    private ?array $concatenation_records;

    /**
     * @var array<string, array<string, Concatenation_File_Record>> Files of the concatenation records,
     *                                                              keyed by their URI hash, grouped by type.
     */
    private array $concatenated_files = array();

    /**
     * @var array Settings.
     */
    private array $settings;

    /**
     * Processor constructor.
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     *
     * @param Options                     $options                     An instance of `Options`.
     * @param Logger                      $logger                      An instance of `Logger`.
     * @param File_Reader                 $file_reader                 An instance of `File_Reader`.
     * @param File_Writer                 $file_writer                 An instance of `File_Writer`.
     * @param File_Minification_Evaluator $file_minification_evaluator An instance of `File_Minification_Evaluator`.
     * @param URL_Builder                 $url_builder                 An instance of `URL_Builder`.
     * @param Filesystem                  $filesystem                  An instance of `Filesystem`.
     * @param Concatenator                $concatenator                An instance of `Concatenator`.
     * @param Concatenations_Pages_Table  $concatenations_pages_table  An instance of `Concatenations_Pages_Table`.
     * @param Concatenations_Table        $concatenations_table        An instance of `Concatenations_Table`.
     * @param Concatenations_Files_Table  $concatenations_files_table  An instance of `Concatenations_Files_Table`.
     * @param SRI_Validator               $sri_validator               An instance of `SRI_Validator`.
     * @param JS_Minifier                 $minifier                    An instance of the JS `Minifier`.
     * @param Settings                    $settings_object             An instance of `Settings`.
     */
    public function __construct(
        Options $options,
        Logger $logger,
        File_Reader $file_reader,
        File_Writer $file_writer,
        File_Minification_Evaluator $file_minification_evaluator,
        URL_Builder $url_builder,
        Filesystem $filesystem,
        Concatenator $concatenator,
        Concatenations_Pages_Table $concatenations_pages_table,
        Concatenations_Table $concatenations_table,
        Concatenations_Files_Table $concatenations_files_table,
        SRI_Validator $sri_validator,
        JS_Minifier $minifier,
        Settings $settings_object,
    ) {
        parent::__construct(
            $options,
            $logger,
            $file_reader,
            $file_writer,
            $file_minification_evaluator,
            $url_builder,
            $filesystem,
            $minifier,
            $concatenator,
            $concatenations_pages_table,
            $concatenations_table,
            $concatenations_files_table,
            $sri_validator,
        );

        $this->settings = $settings_object->get();

        // Instantiate the concatenate process
        $this->concatenate_process = new Concatenate_Process(
            $this->options,
            $this->logger,
            $this->file_reader,
            $this->sri_validator,
            $this->url_builder,
            $this->concatenator,
            $this->concatenations_table,
            $this->concatenations_files_table,
            $minifier,
            $this->should_minify(),
        );
    }

    /**
     * Return the background process.
     *
     * @return ?Background_Process
     */
    public function get_background_process(): ?Background_Process {
        return $this->concatenate_process;
    }

    /**
     * Return the exclusions for this file type.
     *
     * @return array
     */
    private function get_exclusions(): array {
        if ( ! isset( $this->settings['concatenation']['exclusions'] ) ) {
            return array();
        }

        if ( ! isset( $this->settings['concatenation']['exclusions']['js'] ) ) {
            return array();
        }

        return $this->settings['concatenation']['exclusions']['js'];
    }

    /**
     * Whether the given script URI is excluded from concatenation.
     *
     * @param string $script_uri Script URI to check.
     *
     * @return bool
     */
    private function is_excluded( string $script_uri ): bool {
        $file_type = 'js';

        /**
         * Filters the concatenation exclusions for the specified file type.
         *
         * @param array $exclusions Exclusions as an array of associative arrays with keys `url` and `is_regex`.
         */
        $exclusions = apply_filters(
            "pressidium_performance_concatenation_exclusions_{$file_type}",
            $this->get_exclusions()
        );

        return Exclusion_Matcher::matches_any( $script_uri, (array) $exclusions );
    }

    /**
     * Whether concatenation is restricted to the assets of this site only.
     *
     * @return bool
     */
    private function is_same_origin_only(): bool {
        return (bool) ( $this->settings['concatenation']['sameOriginOnly'] ?? true );
    }

    /**
     * Whether the currently iterated tag of the given HTML processor is a script tag.
     *
     * @param HTML_Processor $processor HTML processor.
     *
     * @return bool Whether the tag is a script tag.
     */
    private function is_script_tag( HTML_Processor $processor ): bool {
        return $processor->get_tag() === HTML_Tag::SCRIPT->value;
    }

    /**
     * Return the type of the currently iterated script tag.
     *
     * Defaults to 'text/javascript' if the `type` attribute is not set.
     *
     * @param HTML_Processor $processor HTML processor.
     *
     * @return string Script type.
     */
    private function get_script_type( HTML_Processor $processor ): string {
        return $processor->get_attribute( 'type' ) ?? 'text/javascript';
    }

    /**
     * Whether the currently iterated script tag is a script that can be concatenated.
     *
     * @param HTML_Processor $processor HTML processor.
     *
     * @return bool Whether the tag is a script that can be concatenated.
     */
    private function is_concatenable_script( HTML_Processor $processor ): bool {
        if ( ! $this->is_script_tag( $processor ) ) {
            // Not a script tag, bail early
            return false;
        }

        $type = $this->get_script_type( $processor );

        return in_array( $type, self::CONCATENABLE_TYPES, true );
    }

    /**
     * Process the HTML to schedule JS scripts for concatenation.
     *
     * @param HTML_Processor $processor
     *
     * @return void
     */
    public function process( HTML_Processor $processor ): void {
        if ( ! $this->is_concatenable_script( $processor ) ) {
            // Not a script tag that can be concatenated, bail early
            return;
        }

        $src = $processor->get_attribute( 'src' );

        if ( ! $src ) {
            // No src attribute, bail early
            return;
        }

        if ( $this->is_excluded( $src ) ) {
            // The script is excluded from concatenation, bail early
            $this->logger->info(
                sprintf( 'Script \'%s\' is excluded from concatenation, skipping...', esc_url( $src ) )
            );
            return;
        }

        if ( $this->is_same_origin_only() && ! $this->is_allowed_origin( $src ) ) {
            // The script is served from another origin, leave it untouched
            $this->logger->debug(
                sprintf( 'Script \'%s\' is not served from an allowed host, skipping...', esc_url( $src ) )
            );
            return;
        }

        $type = $this->get_script_type( $processor );

        // Temporarily skip `module` scripts until we figure out how to handle them properly
        // We need to run a real bundler either by spawning a process to run esbuild/rollup/webpack
        // or by using a remote service via an API to do the bundling for us
        if ( $type === 'module' ) {
            $this->logger->info( sprintf( 'Skipping `module` script \'%s\' for now...', esc_url( $src ) ) );
            return;
        }

        $sri_hashes = $processor->get_sri_hashes();

        // Add the script URI to the list of scripts to concatenate
        $this->script_metadata[ $type ][] = array(
            'uri' => $src,
            'sri' => $sri_hashes,
        );
    }

    /**
     * Return the URIs of the scripts to concatenate for the given type.
     *
     * @param string $type Script type.
     *
     * @return string[]
     */
    private function get_script_uris( string $type ): array {
        return array_column( $this->script_metadata[ $type ] ?? array(), 'uri' );
    }

    /**
     * Compute and return the aggregated hash of the scripts to concatenate of the given type.
     *
     * @param string $type Script type.
     *
     * @return string Aggregated hash.
     */
    private function compute_aggregated_hash( string $type ): string {
        /*
         * Sort the script URIs, so pages with the same scripts in a different order share one bundle.
         * Unlike stylesheets, the order of the bundle does not matter, since each chunk runs
         * from a `runChunk()` call where its `script` tag used to be, or is queued from a
         * `deferChunk()` call there if the script is deferred.
         */
        return md5( implode( ',', Array_Utils::sort( $this->get_script_uris( $type ) ) ) );
    }

    /**
     * Whether JavaScript files should be minified.
     *
     * Each script is minified before it's written to the bundle, rather than the bundle as a whole,
     * since `Minify` cannot recognize string literals longer than 64 KiB, which chunks may well be.
     *
     * @return bool
     */
    private function should_minify(): bool {
        //return $this->settings['minification']['minifyJS'] ?? false;

        // Do not minify concatenated files even if minification is enabled while the feature is in beta
        return false;
    }

    /**
     * Finish the processing of the given type.
     *
     * @param string $type Script type.
     *
     * @return void
     */
    private function complete_process_by_type( string $type ): void {
        if ( empty( $this->script_metadata[ $type ] ) ) {
            // No scripts to concatenate for the currently iterated type, bail early
            return;
        }

        // Compute the aggregated hash of the scripts to concatenate
        $aggregated_hash = $this->compute_aggregated_hash( $type );

        /*
         * Check if there is a ready concatenation record for the aggregated hash,
         * meaning the scripts have already been concatenated.
         */
        $concatenation_record = $this->concatenations_table->get_concatenation_record( $aggregated_hash );

        if ( $concatenation_record !== null && ! $concatenation_record->get_is_ready() ) {
            $concatenation_record = null;
        }

        // Set the post mapping for the aggregated hash
        if ( ! is_404() ) {
            $this->concatenations_pages_table->set_mapping(
                WP_Utils::get_unique_page_hash(), // unique identifier for the current page
                $type,
                $aggregated_hash,
                $this->get_mapping_touch_interval()
            );
        }

        // Store the concatenation record for the currently iterated type
        $this->concatenation_records[ $type ] = $concatenation_record;

        if ( ! empty( $concatenation_record ) ) {
            // Load its files, so we know which scripts it includes
            $this->concatenated_files[ $type ] = array();

            foreach ( $this->concatenations_files_table->get_files( $concatenation_record->get_id() ) as $file ) {
                $this->concatenated_files[ $type ][ $file->get_file_uri_hash() ] = $file;
            }

            // Store the number of scripts that were concatenated
            $concatenation_record->set_files_count( $this->count_included_scripts( $type ) );

            // Update the concatenation record in the database
            $updated = $this->concatenations_table->update_concatenation_record( $concatenation_record );

            if ( ! $updated ) {
                // Could not update the concatenation record
                $this->logger->error( 'Could not update the concatenation record' );
            }
            return;
        }

        // TODO: If a previous mapping existed, check if that concatenation record is still used by any other post
        // TODO: If not, consider deleting the concatenated file and its record from the database

        // There is no concatenation record, schedule the scripts for concatenation
        $this->logger->debug( 'No concatenation record found for the aggregated hash, scheduling concatenation' );

        if ( $this->concatenate_process->is_active() ) {
            // A concatenation process is already active, bail early
            $this->logger->debug( 'A concatenation process is already active, skipping...' );
            return;
        }

        if ( ! $this->claim_build( $aggregated_hash, $type ) ) {
            // Another request is already building this bundle, bail early
            $this->logger->debug( 'The bundle is already being built, skipping...' );
            return;
        }

        // Start from scratch, in case a previous run left a partial file behind without completing it
        $this->concatenator->discard( $aggregated_hash );

        foreach ( $this->script_metadata[ $type ] as $meta ) {
            $this->concatenate_process->push_to_queue(
                new Concatenate_Payload(
                    $meta['uri'],
                    $meta['sri'],
                    $aggregated_hash,
                    $type,
                    get_the_ID()
                )
            );
        }
    }

    /**
     * Finish the processing.
     *
     * @return void
     */
    public function complete_process(): void {
        $this->logger->debug( 'JS processor has finished gathering script for concatenation' );

        // Process each type separately
        foreach ( self::CONCATENABLE_TYPES as $type ) {
            $this->complete_process_by_type( $type );
        }

        $this->logger->debug( 'Saving concatenation queue...' );
        $this->logger->debug( 'Dispatching concatenate process...' );

        /*
         * Save queue and dispatch the concatenate process,
         * start concatenating files in the background.
         */
        $this->concatenate_process->save()->dispatch();
    }

    /**
     * Whether the given script URI is included in the bundle of the given type.
     *
     * Scripts that could not be included in the bundle keep being served on their own.
     *
     * @param string $type Script type.
     * @param string $src  Script URI.
     *
     * @return bool Whether the script is included in the bundle.
     */
    private function is_included_script( string $type, string $src ): bool {
        $file = $this->concatenated_files[ $type ][ md5( $src ) ] ?? null;

        return $file !== null && ! $file->is_excluded();
    }

    /**
     * Return the number of scripts of the given type on this page that are included in the bundle.
     *
     * @param string $type Script type.
     *
     * @return int
     */
    private function count_included_scripts( string $type ): int {
        return count(
            array_filter(
                $this->get_script_uris( $type ),
                function ( string $src ) use ( $type ): bool {
                    return $this->is_included_script( $type, $src );
                }
            )
        );
    }

    /**
     * Whether the currently iterated script tag is a deferred script.
     *
     * @param HTML_Processor $processor HTML processor.
     *
     * @return bool
     */
    private function is_deferred_script( HTML_Processor $processor ): bool {
        return $processor->get_attribute( 'defer' ) !== null && $processor->get_attribute( 'async' ) === null;
    }

    /**
     * Process the HTML to optimize it in a second pass for the given type.
     *
     * @param HTML_Processor $processor HTML processor.
     * @param string         $src       Script URI.
     * @param string         $type      Script type.
     *
     * @return void
     */
    private function postprocess_by_type( HTML_Processor $processor, string $src, string $type ): void {
        if ( empty( $this->concatenation_records[ $type ] ) ) {
            // No concatenation record for the currently iterated type, bail early
            return;
        }

        $concatenated_uri = $this->concatenation_records[ $type ]->get_concatenated_uri();

        if ( ! $concatenated_uri ) {
            // No concatenated URI, bail early
            return;
        }

        /*
         * There is a concatenation record, turn the script tags that were concatenated
         * into inline scripts that run their chunk of the concatenated script.
         */
        if ( $this->is_included_script( $type, $src ) ) {
            $processor->remove_attribute( 'src' );
            $processor->remove_attribute( 'integrity' );

            // TODO: Maybe remove more attributes?

            $is_deferred = $this->is_deferred_script( $processor );

            if ( $is_deferred ) {
                // Inline scripts cannot be deferred, the bundle runtime defers the chunk instead
                $processor->remove_attribute( 'defer' );
            }

            $chunk_id      = md5( $src );
            $inline_script = sprintf(
                "window.pressidiumPerformanceConcatenatedChunks.%s('%s', '%s');",
                $is_deferred ? 'deferChunk' : 'runChunk',
                $chunk_id,
                esc_attr( $type )
            );

            $processor->set_modifiable_text( $inline_script );
        }
    }

    /**
     * Inject the concatenated scripts into their respective slots.
     *
     * @param HTML_Processor $processor Processor.
     *
     * @return bool Whether a concatenated script was injected.
     */
    private function inject_concatenated_scripts( HTML_Processor $processor ): bool {
        $is_performance_slot = $processor->get_attribute( 'data-pressidium-performance-slot' ) !== null;

        if ( ! $is_performance_slot ) {
            // Not a performance slot, bail early
            return false;
        }

        $type = $this->get_script_type( $processor );


        if ( empty( $this->concatenation_records[ $type ] ) ) {
            // No concatenation record for the currently iterated type, skip
            return false;
        }

        $concatenated_uri = $this->concatenation_records[ $type ]->get_concatenated_uri();

        if ( ! $concatenated_uri ) {
            // No concatenated URI for this type, skip
            return false;
        }

        // Check if this is our slot for the concatenated script
        $is_performance_slot = $processor->get_attribute( 'data-pressidium-performance-slot' ) !== null;

        if ( $is_performance_slot ) {
            // This is our slot, replace it with the concatenated script
            $processor->set_attribute( 'src', $concatenated_uri );
            return true;
        }

        return false;
    }

    /**
     * Process the HTML to optimize it in a second pass.
     *
     * @param HTML_Processor $processor Processor.
     *
     * @return void
     */
    public function postprocess( HTML_Processor $processor ): void {
        if ( ! $this->is_concatenable_script( $processor ) ) {
            // Not a script tag that can be concatenated, bail early
            return;
        }

        $did_inject_script = $this->inject_concatenated_scripts( $processor );

        if ( $did_inject_script ) {
            // We injected the concatenated script, bail early
            return;
        }

        $src = $processor->get_attribute( 'src' );

        if ( ! $src ) {
            // No src attribute, bail early
            return;
        }

        foreach ( self::CONCATENABLE_TYPES as $type ) {
            $this->postprocess_by_type( $processor, $src, $type );
        }
    }

    /**
     * Finish the post-processing.
     *
     * @param HTML_Processor $processor Processor.
     *
     * @return void
     */
    public function complete_postprocess( HTML_Processor $processor ): void {
        $this->logger->debug( 'Finished replacing script tags with the concatenated script' );
    }

}
