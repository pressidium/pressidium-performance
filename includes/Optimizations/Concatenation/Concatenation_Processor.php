<?php
/**
 * Concatenation processor base class.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation;

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

}
