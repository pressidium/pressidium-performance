<?php
/**
 * JavaScript concatenation processor.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\JS;

use Pressidium\WP\Performance\Background_Process;
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
use Pressidium\WP\Performance\Optimizations\Concatenation_Record;
use Pressidium\WP\Performance\Optimizations\Minification\File_Minification_Evaluator;
use Pressidium\WP\Performance\Optimizations\Minification\Minifier;
use Pressidium\WP\Performance\Settings;
use Pressidium\WP\Performance\URL_Builder;
use Pressidium\WP\Performance\Utils\Array_Utils;
use Pressidium\WP\Performance\Utils\WP_Utils;
use Pressidium\WP\Performance\Enumerations\HTML_Tag;
use Pressidium\WP\Performance\Enumerations\Output_Directory;
use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;

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
     * @param SRI_Validator               $sri_validator               An instance of `SRI_Validator`.
     * @param Minifier                    $minifier                    An instance of `Minifier`.
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
        SRI_Validator $sri_validator,
        Minifier $minifier,
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
     * Whether the specified URL matches the given script URI (or a regex pattern of it).
     *
     * @param string $script_uri     Script URI to check.
     * @param string $url_or_pattern URL or regex pattern to match.
     * @param bool   $is_regex       Whether we are matching a regex pattern.
     *
     * @return bool
     */
    private function matches_exclusion( string $script_uri, string $url_or_pattern, bool $is_regex ): bool {
        if ( $is_regex ) {
            return preg_match( '#' . $url_or_pattern . '#', $script_uri );
        }

        return $url_or_pattern === $script_uri;
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

        return Array_Utils::some(
            $exclusions,
            function ( $exclusion ) use ( $script_uri ) {
                return $this->matches_exclusion( $script_uri, $exclusion['url'], $exclusion['is_regex'] );
            }
        );
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

        $type = $this->get_script_type( $processor );

        // Temporarily skip `module` scripts until we figure out how to handle them properly
        // We need to run a real bundler either by spawning a process to run esbuild/rollup/webpack
        // or by using a remote service via an API to do the bundling for us
        if ( $type === 'module' ) {
            $this->logger->info( sprintf( 'Skipping `module` script \'%s\' for now...', esc_url( $src ) ) );
            return;
        }

        $sri_hash = $processor->get_attribute( 'integrity' );

        // Add the script URI to the list of scripts to concatenate
        $this->script_metadata[ $type ][] = array(
            'uri' => $src,
            'sri' => $sri_hash,
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
        // Sort the script URIs to ensure the hash is consistent
        return md5( implode( ',', Array_Utils::sort( $this->get_script_uris( $type ) ) ) );
    }

    /**
     * Whether JavaScript files should be minified.
     *
     * @return bool
     */
    private function should_minify(): bool {
        //return $this->settings['minification']['minifyJS'] ?? false;

        // Do not minify concatenated files even if minification is enabled while the feature is in beta
        return false;
    }

    /**
     * Minify the concatenated file, if not already minified.
     *
     * @param Concatenation_Record $concatenation_record Concatenation record.
     *
     * @return Concatenation_Record
     */
    private function maybe_minify_concatenated_file( Concatenation_Record $concatenation_record ): Concatenation_Record {
        if ( ! $this->should_minify() ) {
            // Minification is disabled, bail early
            return $concatenation_record;
        }

        if ( $concatenation_record->get_is_minified() ) {
            // The concatenated file is already minified, bail early
            return $concatenation_record;
        }

        try {
            // Minify concatenated file
            $concatenated_file = $this->file_reader->read_remote( $concatenation_record->get_concatenated_uri() );
            $minified_file     = $this->minifier->minify( $concatenated_file );

            $filename  = $concatenation_record->get_aggregated_hash() . '.js';
            $sub_dir   = Output_Directory::CONCATENATED->value;
            $file_path = $this->filesystem->build_path( $sub_dir, $filename );

            // Overwrite the concatenated file with the minified one
            $written = $this->filesystem->write( $file_path, $minified_file->get_contents() );

            if ( ! $written ) {
                // Could not write the minified file
                $this->logger->error(
                    sprintf(
                        'Could not write the minified concatenated file at: %s',
                        esc_html( $concatenated_file->get_url() )
                    )
                );
                return $concatenation_record;
            }

            // Update the concatenation record with the minified status
            $concatenation_record->set_is_minified( true );
            $concatenation_record->set_original_size( $concatenated_file->get_size_in_bytes() );
            $concatenation_record->set_optimized_size( $minified_file->get_size_in_bytes() );
        } catch ( Filesystem_Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not minify the concatenated file: %s', esc_html( $exception->getMessage() ) )
            );
        }

        return $concatenation_record;
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
         * Check if there is a concatenation record for the aggregated hash,
         * meaning the scripts have already been concatenated.
         */
        $concatenation_record = $this->concatenations_table->get_concatenation_record( $aggregated_hash );

        // Set the post mapping for the aggregated hash
        if ( ! is_404() ) {
            $this->concatenations_pages_table->set_mapping(
                WP_Utils::get_unique_page_hash(), // unique identifier for the current page
                $type,
                $aggregated_hash
            );
        }

        // Store the concatenation record for the currently iterated type
        $this->concatenation_records[ $type ] = $concatenation_record;

        if ( ! empty( $concatenation_record ) ) {
            // There is a concatenation record stored already, maybe minify it
            $concatenation_record = $this->maybe_minify_concatenated_file( $concatenation_record );

            if ( ! $concatenation_record->get_is_minified() ) {
                /*
                 * File was not minified, read the concatenated file to get its size
                 * and set both the original and optimized sizes to the same value.
                 */
                $concatenated_file = $this->file_reader->read_remote( $concatenation_record->get_concatenated_uri() );

                $concatenation_record->set_original_size( $concatenated_file->get_size_in_bytes() );
                $concatenation_record->set_optimized_size( $concatenated_file->get_size_in_bytes() );
            }

            // Store the number of scripts that were concatenated
            $concatenation_record->set_files_count( count( $this->script_metadata[ $type ] ) );

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
     * Whether the given script URI is one of the concatenated scripts.
     *
     * @param string $type Script type.
     * @param string $src  Script URI.
     *
     * @return bool Whether the script is concatenated.
     */
    private function is_concatenated_script( string $type, string $src ): bool {
        return in_array( $src, $this->get_script_uris( $type ), true );
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
         * There is a concatenation record, disable all script tags that were
         * concatenated and replace the first one with the concatenated script.
         */
        if ( $this->is_concatenated_script( $type, $src ) ) {
            $processor->remove_attribute( 'src' );
            $processor->remove_attribute( 'integrity' );

            // TODO: Maybe remove more attributes?

            $inline_script = "window.pressidiumPerformanceConcatenatedChunks.runChunk('" . esc_url( $src ) . "', '" . esc_attr( $type ) . "');";

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
