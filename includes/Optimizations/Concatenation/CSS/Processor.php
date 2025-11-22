<?php
/**
 * CSS concatenation processor.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\CSS;

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
     * @var string Stylesheet `rel` attribute.
     */
    const STYLESHEET = 'stylesheet';

    /**
     * @var Concatenate_Process Process to concatenate in the background.
     */
    private Concatenate_Process $concatenate_process;

    /**
     * @var array<array<string, string>> Metadata of the stylesheets to concatenate, grouped by type.
     */
    private array $stylesheet_metadata = array();

    /**
     * @var ?Concatenation_Record Concatenation record stored in the database.
     */
    private ?Concatenation_Record $concatenation_record;

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
        Settings $settings_object
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
            $sri_validator
        );

        $this->settings = $settings_object->get();

        // Instantiate the concatenate process
        $this->concatenate_process = new Concatenate_Process(
            $this->logger,
            $this->file_reader,
            $this->file_writer,
            $this->url_builder,
            $this->minifier,
            $this->concatenator,
            $this->concatenations_table,
            $this->sri_validator,
            $this->settings
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
     * Whether the specified URL matches the given stylesheet URI (or a regex pattern of it).
     *
     * @param string $stylesheet_uri Stylesheet URI to check.
     * @param string $url_or_pattern URL or regex pattern to match.
     * @param bool   $is_regex       Whether we are matching a regex pattern.
     *
     * @return bool
     */
    private function matches_exclusion( string $stylesheet_uri, string $url_or_pattern, bool $is_regex ): bool {
        if ( $is_regex ) {
            return preg_match( '#' . $url_or_pattern . '#', $stylesheet_uri );
        }

        return $url_or_pattern === $stylesheet_uri;
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

        if ( ! isset( $this->settings['concatenation']['exclusions']['css'] ) ) {
            return array();
        }

        return $this->settings['concatenation']['exclusions']['css'];
    }

    /**
     * Whether the given stylesheet URI is excluded from concatenation.
     *
     * @param string $stylesheet_uri Stylesheet URI to check.
     *
     * @return bool
     */
    private function is_excluded( string $stylesheet_uri ): bool {
        $file_type = 'css';

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
            function ( $exclusion ) use ( $stylesheet_uri ) {
                return $this->matches_exclusion( $stylesheet_uri, $exclusion['url'], $exclusion['is_regex'] );
            }
        );
    }

    /**
     * Whether the currently iterated tag of the given HTML processor is a link tag.
     *
     * @param HTML_Processor $processor HTML processor.
     *
     * @return bool Whether the tag is a link tag.
     */
    private function is_link_tag( HTML_Processor $processor ): bool {
        return $processor->get_tag() === HTML_Tag::LINK->value;
    }

    /**
     * Whether the currently iterated `link` tag is a stylesheet that can be concatenated.
     *
     * @param HTML_Processor $processor HTML processor.
     *
     * @return bool Whether the tag is a stylesheet that can be concatenated.
     */
    private function is_concatenable_stylesheet( HTML_Processor $processor ): bool {
        if ( ! $this->is_link_tag( $processor ) ) {
            // Not a link tag, bail early
            return false;
        }

        $rel = $processor->get_attribute( 'rel' );

        if ( ! $rel ) {
            // No `rel` attribute, bail early
            return false;
        }

        return $rel === self::STYLESHEET;
    }

    /**
     * Process the HTML to schedule stylesheets for concatenation.
     *
     * @param HTML_Processor $processor
     *
     * @return void
     */
    public function process( HTML_Processor $processor ): void {
        if ( ! $this->is_concatenable_stylesheet( $processor ) ) {
            // Not a stylesheet that can be concatenated, bail early
            return;
        }

        $href = $processor->get_attribute( 'href' );

        if ( ! $href ) {
            // No `href` attribute, bail early
            return;
        }

        if ( $this->is_excluded( $href ) ) {
            // The stylesheet is excluded from concatenation, bail early
            $this->logger->info(
                sprintf( 'Stylesheet \'%s\' is excluded from concatenation, skipping...', esc_url( $href ) )
            );
            return;
        }

        $sri_hash = $processor->get_attribute( 'integrity' );

        // Add the stylesheet URI and SRI hash (if any) to the list of stylesheets to concatenate
        $this->stylesheet_metadata[] = array(
            'uri' => $href,
            'sri' => $sri_hash,
        );
    }

    /**
     * Return the URIs of the stylesheets to concatenate.
     *
     * @return string[]
     */
    private function get_stylesheet_uris(): array {
        return array_column( $this->stylesheet_metadata, 'uri' );
    }

    /**
     * Compute and return the aggregated hash of the stylesheets to concatenate.
     *
     * @return string Aggregated hash.
     */
    private function compute_aggregated_hash(): string {
        // Sort the stylesheet URIs to ensure the hash is consistent
        return md5( implode( ',', Array_Utils::sort( $this->get_stylesheet_uris() ) ) );
    }

    /**
     * Whether CSS files should be minified.
     *
     * @return bool
     */
    private function should_minify(): bool {
        return $this->settings['minification']['minifyCSS'] ?? false;
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

            $filename  = $concatenation_record->get_aggregated_hash() . '.css';
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
     * Finish the processing.
     *
     * @return void
     */
    public function complete_process(): void {
        $this->logger->debug( 'CSS processor has finished gathering stylesheets for concatenation' );

        if ( empty( $this->stylesheet_metadata ) ) {
            // No stylesheets to concatenate, bail early
            return;
        }

        // Compute the aggregated hash of the stylesheets to concatenate
        $aggregated_hash = $this->compute_aggregated_hash();

        // Set the post mapping for the aggregated hash
        if ( ! is_404() ) {
            $this->concatenations_pages_table->set_mapping(
                WP_Utils::get_unique_page_hash(), // unique identifier for the current page
                self::STYLESHEET,
                $aggregated_hash
            );
        }

        /*
         * Check if there is a concatenation record for the aggregated hash,
         * meaning the stylesheets have already been concatenated.
         */
        $concatenation_record = $this->concatenations_table->get_concatenation_record( $aggregated_hash );

        // Store the concatenation record
        $this->concatenation_record = $concatenation_record;

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

            // Store the number of stylesheets that were concatenated
            $concatenation_record->set_files_count( count( $this->stylesheet_metadata ) );

            // Update the concatenation record in the database
            $updated = $this->concatenations_table->update_concatenation_record( $concatenation_record );

            if ( ! $updated ) {
                // Could not update the concatenation record
                $this->logger->error( 'Could not update the concatenation record' );
            }

            return;
        }

        // TODO: (enhancement) If a previous mapping existed, check if that concatenation record is still used
        // TODO: (enhancement) by another post. If not, consider deleting the concatenated file and its record
        // TODO: (enhancement) from the database, so it doesn't have to wait for the clean-up cron job.

        // There is no concatenation record, schedule the stylesheets for concatenation
        $this->logger->debug( 'No concatenation record found for the aggregated hash, scheduling concatenation' );

        if ( $this->concatenate_process->is_active() ) {
            // A concatenation process is already active, bail early
            $this->logger->debug( 'A concatenation process is already active, skipping...' );
            return;
        }

        foreach ( $this->stylesheet_metadata as $meta ) {
            $this->concatenate_process->push_to_queue(
                new Concatenate_Payload(
                    $meta['uri'],
                    $meta['sri'],
                    $aggregated_hash,
                    self::STYLESHEET,
                    get_the_ID()
                )
            );
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
     * Whether the given stylesheet URI is one of the concatenated stylesheets.

     * @param string $href Stylesheet URI.
     *
     * @return bool Whether the stylesheet is concatenated.
     */
    private function is_concatenated_stylesheet( string $href ): bool {
        return in_array( $href, $this->get_stylesheet_uris(), true );
    }

    /**
     * Whether the given stylesheet URI is the first stylesheet to be concatenated.
     *
     * @param string $href Stylesheet URI.
     *
     * @return bool Whether the stylesheet is the first one.
     */
    private function is_first_stylesheet( string $href ): bool {
        return $href === $this->get_stylesheet_uris()[0];
    }

    /**
     * Process the HTML to optimize it in a second pass.
     *
     * @link https://developer.mozilla.org/en-US/docs/Web/HTML/Element/link#disabled
     *
     * @param HTML_Processor $processor Processor.
     *
     * @return void
     */
    public function postprocess( HTML_Processor $processor ): void {
        if ( ! $this->is_concatenable_stylesheet( $processor ) ) {
            // Not a link tag that can be concatenated, bail early
            return;
        }

        $href = $processor->get_attribute( 'href' );

        if ( ! $href ) {
            // No `href` attribute, bail early
            return;
        }

        if ( empty( $this->concatenation_record ) ) {
            // No concatenation record, bail early
            return;
        }

        $concatenated_uri = $this->concatenation_record->get_concatenated_uri();

        if ( ! $concatenated_uri ) {
            // No concatenated URI, bail early
            return;
        }

        /*
         * There is a concatenation record, disable all `link` tags that were
         * concatenated and replace the first one with the concatenated stylesheet.
         */
        if ( $this->is_concatenated_stylesheet( $href ) ) {
            if ( $this->is_first_stylesheet( $href ) ) {
                // The first stylesheet, replace it with the concatenated stylesheet
                $processor->set_attribute( 'href', $concatenated_uri );
                return;
            }

            // Not the first stylesheet, disable it
            $processor->set_attribute( 'disabled', true );
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
        $this->logger->debug( 'Finished replacing link tags with the concatenated stylesheet' );
    }

}
