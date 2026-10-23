<?php
/**
 * Concatenation processor base class.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation;

use Pressidium\WP\Performance\Database\Tables\Concatenations_Files_Table;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Pages_Table;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Table;
use Pressidium\WP\Performance\Optimizations\Minification\File_Minification_Evaluator;
use Pressidium\WP\Performance\Optimizations\Minification\Minifier;
use Pressidium\WP\Performance\Files\File_Reader;
use Pressidium\WP\Performance\Files\File_Writer;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\SRI_Validator;
use Pressidium\WP\Performance\Options\Options;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Processor;
use Pressidium\WP\Performance\URL_Builder;
use Pressidium\WP\Performance\Utils\Array_Utils;
use Pressidium\WP\Performance\Utils\URL_Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenation_Processor base class.
 *
 * @since 1.0.0
 */
abstract class Concatenation_Processor extends Processor {

    /**
     * @var string[]|null Memoization of the hosts whose assets may be concatenated.
     */
    private ?array $allowed_hosts = null;

    /**
     * Concatenation_Processor constructor.
     *
     * @param Options                     $options                     An instance of `Options`.
     * @param Logger                      $logger                      An instance of `Logger`.
     * @param File_Reader                 $file_reader                 An instance of `File_Reader`.
     * @param File_Writer                 $file_writer                 An instance of `File_Writer`.
     * @param File_Minification_Evaluator $file_minification_evaluator An instance of `File_Minification_Evaluator`.
     * @param URL_Builder                 $url_builder                 An instance of `URL_Builder`.
     * @param Filesystem                  $filesystem                  An instance of `Filesystem`.
     * @param Minifier                    $minifier                    An instance of `Minifier`.
     * @param Concatenator                $concatenator                An instance of `Concatenator`.
     * @param Concatenations_Pages_Table  $concatenations_pages_table  An instance of `Concatenations_Pages_Table`.
     * @param Concatenations_Table        $concatenations_table        An instance of `Concatenations_Table`.
     * @param Concatenations_Files_Table  $concatenations_files_table  An instance of `Concatenations_Files_Table`.
     * @param SRI_Validator               $sri_validator               An instance of `SRI_Validator`.
     */
    public function __construct(
        Options $options,
        Logger $logger,
        File_Reader $file_reader,
        File_Writer $file_writer,
        File_Minification_Evaluator $file_minification_evaluator,
        URL_Builder $url_builder,
        protected readonly Filesystem $filesystem,
        protected readonly Minifier $minifier,
        protected readonly Concatenator $concatenator,
        protected readonly Concatenations_Pages_Table $concatenations_pages_table,
        protected readonly Concatenations_Table $concatenations_table,
        protected readonly Concatenations_Files_Table $concatenations_files_table,
        protected readonly SRI_Validator $sri_validator,
    ) {
        parent::__construct(
            $options,
            $logger,
            $file_reader,
            $file_writer,
            $file_minification_evaluator,
            $url_builder
        );
    }

    /**
     * Return the hosts whose assets may be concatenated.
     *
     * Includes the hosts this site serves its own assets from, plus any host added
     * through the `pressidium_performance_concatenation_allowed_hosts` filter.
     *
     * @return string[] Lowercase hostnames.
     */
    private function get_allowed_hosts(): array {
        if ( $this->allowed_hosts !== null ) {
            // Memoized, return the cached value
            return $this->allowed_hosts;
        }

        /**
         * Filters the hosts whose assets may be concatenated, in addition to this site's own host.
         *
         * @param string[] $hosts Additional allowed hostnames.
         */
        $additional_hosts = apply_filters( 'pressidium_performance_concatenation_allowed_hosts', array() );

        $additional_hosts = array_map( array( URL_Utils::class, 'normalize_host' ), (array) $additional_hosts );

        $this->allowed_hosts = Array_Utils::dedupe_values(
            array_merge( URL_Utils::get_site_hosts(), array_filter( $additional_hosts ) )
        );

        return $this->allowed_hosts;
    }

    /**
     * Whether the file at the given URL is served from a host whose assets we may concatenate.
     *
     * @param string $file_url URL of the file to evaluate.
     *
     * @return bool
     */
    protected function is_allowed_origin( string $file_url ): bool {
        $host = URL_Utils::get_host( $file_url );

        if ( $host === null ) {
            // Relative URL, so the file is served from this site
            return true;
        }

        return in_array( $host, $this->get_allowed_hosts(), true );
    }

    /**
     * Return how many seconds have to pass before a mapping is touched again.
     *
     * This is used to avoid writing to the database too often.
     * Touching a page mapping is what tells the cleanup job the page is still being
     * visited. Since the cleanup job works in days, there is no point in writing
     * more often than once a day per page.
     *
     * @return int Touch interval in seconds.
     */
    protected function get_mapping_touch_interval(): int {
        /**
         * Filters how many seconds have to pass before a mapping is touched again.
         *
         * @param int $touch_interval_in_seconds Touch interval in seconds.
         */
        return (int) apply_filters(
            'pressidium_performance_concatenation_mapping_touch_interval',
            DAY_IN_SECONDS
        );
    }

    /**
     * Return how many seconds a bundle may stay not ready before its build is taken to be dead.
     *
     * Builds are only claimed while no concatenation process is active, so a claim older than
     * this, on a bundle that is still not ready, was left behind by a build that never completed.
     *
     * @return int Timeout in seconds.
     */
    protected function get_build_timeout(): int {
        /**
         * Filters how many seconds a bundle may stay not ready before its build is taken over.
         *
         * @param int $timeout_in_seconds Timeout in seconds.
         */
        return (int) apply_filters(
            'pressidium_performance_concatenation_build_timeout',
            30 * MINUTE_IN_SECONDS
        );
    }

    /**
     * Claim the build of the bundle with the given aggregated hash.
     *
     * Only one request can claim a build, see `Concatenations_Table::claim_build()`,
     * so concurrent requests for the same bundle don't queue its files more than once.
     *
     * @param string $aggregated_hash Aggregated hash of the bundle.
     * @param string $type            Type of the bundle.
     *
     * @return bool Whether the build was claimed.
     */
    protected function claim_build( string $aggregated_hash, string $type ): bool {
        return $this->concatenations_table->claim_build( $aggregated_hash, $type, $this->get_build_timeout() );
    }

}
