<?php
/**
 * CSS concatenation processor.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\CSS;

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
use Pressidium\WP\Performance\Optimizations\Concatenation\CSS\Concatenator as CSS_Concatenator;
use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenate_Payload;
use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenation_Processor;
use Pressidium\WP\Performance\Optimizations\Concatenation_File_Record;
use Pressidium\WP\Performance\Optimizations\Concatenation_Record;
use Pressidium\WP\Performance\Optimizations\Minification\File_Minification_Evaluator;
use Pressidium\WP\Performance\Optimizations\Minification\CSS\Minifier as CSS_Minifier;
use Pressidium\WP\Performance\Settings;
use Pressidium\WP\Performance\URL_Builder;
use Pressidium\WP\Performance\Exclusion_Matcher;
use Pressidium\WP\Performance\Utils\WP_Utils;
use Pressidium\WP\Performance\Enumerations\HTML_Tag;
use Pressidium\WP\Performance\Enumerations\Output_Directory;

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
     * Metadata of the stylesheets to concatenate.
     *
     * @var array<array{uri: string, sri: ?array<string, string[]>, starts_segment: bool}>
     */
    private array $stylesheet_metadata = array();

    /**
     * @var ?Concatenation_Record Concatenation record stored in the database.
     */
    private ?Concatenation_Record $concatenation_record;

    /**
     * @var array<string, Concatenation_File_Record> Files of the concatenation record, keyed by their URI hash.
     */
    private array $concatenated_files = array();

    /**
     * @var array<int, true> Segments whose stylesheet has already replaced a `link` tag.
     */
    private array $placed_segments = array();

    /**
     * @var bool Whether styles that are not part of the bundle follow the last stylesheet to concatenate.
     */
    private bool $has_pending_barrier = false;

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
     * @param CSS_Minifier                $minifier                    An instance of the CSS `Minifier`.
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
        CSS_Minifier $minifier,
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
            $concatenations_files_table,
            $sri_validator
        );

        $this->settings = $settings_object->get();

        // Instantiate the concatenate process
        $this->concatenate_process = new Concatenate_Process(
            $this->logger,
            $this->file_reader,
            $this->file_writer,
            $this->url_builder,
            $minifier,
            $this->concatenator,
            $this->concatenations_table,
            $this->concatenations_files_table,
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

        return Exclusion_Matcher::matches_any( $stylesheet_uri, (array) $exclusions );
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
     * Whether the currently iterated tag applies styles to the page.
     *
     * That is, a non-empty `style` tag, or a `link` tag of a stylesheet that is
     * neither an alternate stylesheet nor disabled.
     *
     * @param HTML_Processor $processor HTML processor.
     *
     * @return bool
     */
    private function applies_styles( HTML_Processor $processor ): bool {
        if ( $processor->get_tag() === HTML_Tag::STYLE->value ) {
            return trim( $processor->get_modifiable_text() ) !== '';
        }

        if ( ! $this->is_link_tag( $processor ) || $processor->get_attribute( 'disabled' ) !== null ) {
            return false;
        }

        $rel = $processor->get_attribute( 'rel' );

        if ( ! is_string( $rel ) ) {
            return false;
        }

        $rel_tokens = preg_split( '/\s+/', strtolower( $rel ), -1, PREG_SPLIT_NO_EMPTY );

        return in_array( self::STYLESHEET, $rel_tokens, true ) && ! in_array( 'alternate', $rel_tokens, true );
    }

    /**
     * Return the `href` of the currently iterated stylesheet, if it can be concatenated.
     *
     * @param HTML_Processor $processor HTML processor.
     *
     * @return ?string Stylesheet URI, or `null` if the stylesheet cannot be concatenated.
     */
    private function get_concatenable_href( HTML_Processor $processor ): ?string {
        if ( ! $this->is_concatenable_stylesheet( $processor ) ) {
            // Not a stylesheet that can be concatenated, bail early
            return null;
        }

        $href = $processor->get_attribute( 'href' );

        if ( ! $href || ! is_string( $href ) ) {
            // No `href` attribute, bail early
            return null;
        }

        if ( $this->is_excluded( $href ) ) {
            // The stylesheet is excluded from concatenation, bail early
            $this->logger->info(
                sprintf( 'Stylesheet \'%s\' is excluded from concatenation, skipping...', esc_url( $href ) )
            );
            return null;
        }

        if ( $this->is_same_origin_only() && ! $this->is_allowed_origin( $href ) ) {
            // The stylesheet is served from another origin, leave it untouched
            $this->logger->debug(
                sprintf( 'Stylesheet \'%s\' is not served from an allowed host, skipping...', esc_url( $href ) )
            );
            return null;
        }

        return $href;
    }

    /**
     * Process the HTML to schedule stylesheets for concatenation.
     *
     * @param HTML_Processor $processor
     *
     * @return void
     */
    public function process( HTML_Processor $processor ): void {
        $href = $this->get_concatenable_href( $processor );

        if ( $href === null ) {
            /*
             * Styles that stay where they are, between stylesheets to concatenate, have to
             * keep their place in the cascade, so the next stylesheet starts a new segment.
             */
            if ( ! empty( $this->stylesheet_metadata ) && $this->applies_styles( $processor ) ) {
                $this->has_pending_barrier = true;
            }

            return;
        }

        $sri_hashes = $processor->get_sri_hashes();

        // Add the stylesheet URI and SRI hashes (if any) to the list of stylesheets to concatenate
        $this->stylesheet_metadata[] = array(
            'uri'            => $href,
            'sri'            => $sri_hashes,
            'starts_segment' => $this->has_pending_barrier,
        );

        $this->has_pending_barrier = false;
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
     * Return the URIs of the stylesheets to concatenate that have to start a new segment.
     *
     * @return string[]
     */
    private function get_segment_start_uris(): array {
        return array_column(
            array_filter(
                $this->stylesheet_metadata,
                function ( array $meta ): bool {
                    return $meta['starts_segment'];
                }
            ),
            'uri'
        );
    }

    /**
     * Compute and return the aggregated hash of the stylesheets to concatenate.
     *
     * The stylesheet URIs are kept in page order, since the order of the bundle is the cascade
     * order, so pages with the same stylesheets in a different order need a bundle of their own.
     * Pages with the same stylesheets but styles in between at different places are segmented
     * differently, so they cannot share a bundle either.
     *
     * @return string Aggregated hash.
     */
    private function compute_aggregated_hash(): string {
        $key = implode( ',', $this->get_stylesheet_uris() );

        $segment_start_uris = $this->get_segment_start_uris();

        if ( ! empty( $segment_start_uris ) ) {
            $key .= '|' . implode( ',', $segment_start_uris );
        }

        return md5( $key );
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
                $aggregated_hash,
                $this->get_mapping_touch_interval()
            );
        }

        /*
         * Check if there is a ready concatenation record for the aggregated hash,
         * meaning the stylesheets have already been concatenated.
         */
        $concatenation_record = $this->concatenations_table->get_concatenation_record( $aggregated_hash );

        if ( $concatenation_record !== null && ! $concatenation_record->get_is_ready() ) {
            $concatenation_record = null;
        }

        // Store the concatenation record
        $this->concatenation_record = $concatenation_record;

        if ( ! empty( $concatenation_record ) ) {
            /*
             * There is a concatenation record stored already. Load its files, so we know
             * which stylesheets it includes, and in which segment.
             */
            foreach ( $this->concatenations_files_table->get_files( $concatenation_record->get_id() ) as $file ) {
                $this->concatenated_files[ $file->get_file_uri_hash() ] = $file;
            }

            // Store the number of stylesheets that were concatenated
            $concatenation_record->set_files_count( $this->count_included_stylesheets() );

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

        if ( ! $this->claim_build( $aggregated_hash, self::STYLESHEET ) ) {
            // Another request is already building this bundle, bail early
            $this->logger->debug( 'The bundle is already being built, skipping...' );
            return;
        }

        foreach ( $this->stylesheet_metadata as $meta ) {
            $this->concatenate_process->push_to_queue(
                new Concatenate_Payload(
                    $meta['uri'],
                    $meta['sri'],
                    $aggregated_hash,
                    self::STYLESHEET,
                    get_the_ID(),
                    $meta['starts_segment']
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
     * Return the concatenated file record of the given stylesheet URI.
     *
     * @param string $href Stylesheet URI.
     *
     * @return ?Concatenation_File_Record File record, or `null` if the stylesheet is not part of the bundle.
     */
    private function get_concatenated_file( string $href ): ?Concatenation_File_Record {
        return $this->concatenated_files[ md5( $href ) ] ?? null;
    }

    /**
     * Return the number of stylesheets on this page that are included in the bundle.
     *
     * @return int
     */
    private function count_included_stylesheets(): int {
        return count(
            array_filter(
                $this->get_stylesheet_uris(),
                function ( string $href ): bool {
                    $file = $this->get_concatenated_file( $href );

                    return $file !== null && ! $file->is_excluded();
                }
            )
        );
    }

    /**
     * Return the URI of the stylesheet of the given segment of the bundle.
     *
     * @param int $segment Segment index.
     *
     * @return string
     */
    private function get_segment_uri( int $segment ): string {
        $aggregated_hash = $this->concatenation_record->get_aggregated_hash();
        $segment_key     = CSS_Concatenator::get_segment_key( $aggregated_hash, $segment );

        return $this->url_builder->build_url( Output_Directory::CONCATENATED->value, $segment_key . '.css' );
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

        $file = $this->get_concatenated_file( $href );

        if ( $file === null || $file->is_excluded() ) {
            // Not part of the bundle, keep serving the stylesheet on its own
            return;
        }

        $segment = $file->get_segment();

        if ( $segment === null ) {
            // An empty stylesheet, there is nothing of it to load
            $processor->set_attribute( 'disabled', true );
            return;
        }

        if ( isset( $this->placed_segments[ $segment ] ) ) {
            // The stylesheet of its segment has already replaced an earlier `link` tag, disable it
            $processor->set_attribute( 'disabled', true );
            return;
        }

        /*
         * The first stylesheet of its segment, replace it with the stylesheet of the segment.
         *
         * Segments are split at the stylesheets that could not be included in the bundle,
         * and at any other styles (stylesheets not concatenated, or `style` tags) between
         * the stylesheets of the bundle. Those keep their place, so the order of the
         * cascade is preserved.
         *
         * Its `integrity` attribute was computed for the original stylesheet, so it would
         * make the browser refuse the stylesheet of the segment.
         */
        $processor->set_attribute( 'href', $this->get_segment_uri( $segment ) );
        $processor->remove_attribute( 'integrity' );

        $this->placed_segments[ $segment ] = true;
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
