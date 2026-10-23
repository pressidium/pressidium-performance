<?php
/**
 * Minification processor base class.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification;

use Pressidium\WP\Performance\Processor;
use Pressidium\WP\Performance\Files\File_Reader;
use Pressidium\WP\Performance\Files\File_Writer;
use Pressidium\WP\Performance\URL_Builder;
use Pressidium\WP\Performance\Options\Options;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Database\Tables\Optimizations_Table;
use Pressidium\WP\Performance\Optimizations\Optimization_Record;
use Pressidium\WP\Performance\SRI_Validator;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Minification_Processor base class.
 *
 * @since 1.0.0
 */
abstract class Minification_Processor extends Processor {

    /**
     * Minification_Processor constructor.
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
        protected readonly Minifier $minifier,
        protected readonly Optimizations_Table $optimizations_table,
        protected readonly SRI_Validator $sri_validator
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
     * Whether the given optimization record is stale based on the given integrity metadata.
     *
     * A tag that no longer lists the hash we validated against is asking for different content
     * than the one we optimized, which means whatever we have generated is now stale.
     *
     * @param Optimization_Record      $optimization_record Optimization record to check.
     * @param ?array<string, string[]> $sri_hashes          Integrity metadata of the tag, keyed by algorithm.
     *
     * @return bool `true` if the record is stale, `false` if it can still be used.
     */
    protected function is_record_stale( Optimization_Record $optimization_record, ?array $sri_hashes ): bool {
        if ( $sri_hashes === null ) {
            // No integrity metadata present, so the record cannot be considered stale
            return false;
        }

        $sri_hash = $optimization_record->get_sri_hash();

        if ( $sri_hash === null ) {
            // We haven't stored any hash for this record, so we consider this stale
            return true;
        }

        return ! $this->sri_validator->contains_hash( $sri_hashes, $sri_hash );
    }

}
