<?php
/**
 * Processor base class.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

use Pressidium\WP\Performance\Files\File_Reader;
use Pressidium\WP\Performance\Files\File_Writer;
use Pressidium\WP\Performance\Options\Options;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Optimizations\Minification\File_Minification_Evaluator;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Processor base class.
 *
 * @since 1.0.0
 */
abstract class Processor {

    /**
     * Tag_Processor constructor.
     *
     * @param Options                     $options                     An instance of `Options`.
     * @param Logger                      $logger                      An instance of `Logger`.
     * @param File_Reader                 $file_reader                 An instance of `File_Reader`.
     * @param File_Writer                 $file_writer                 An instance of `File_Writer`.
     * @param File_Minification_Evaluator $file_minification_evaluator An instance of `File_Minification_Evaluator`.
     * @param URL_Builder                 $url_builder                 An instance of `URL_Builder`.
     */
    public function __construct(
        protected Options $options,
        protected Logger $logger,
        protected File_Reader $file_reader,
        protected File_Writer $file_writer,
        protected File_Minification_Evaluator $file_minification_evaluator,
        protected URL_Builder $url_builder,
    ) {}

    /**
     * Process the HTML to optimize it.
     *
     * @param HTML_Processor $processor Processor.
     *
     * @return void
     */
    abstract public function process( HTML_Processor $processor ): void;

    /**
     * Finish the processing.
     *
     * @return void
     */
    abstract public function complete_process(): void;

    /**
     * Process the HTML to optimize it in a second pass.
     *
     * This is useful for optimizations that require a second pass, like concatenation.
     *
     * @param HTML_Processor $processor Processor.
     *
     * @return void
     */
    public function postprocess( HTML_Processor $processor ): void {}

    /**
     * Finish the post-processing.
     *
     * @param HTML_Processor $processor Processor.
     *
     * @return void
     */
    public function complete_postprocess( HTML_Processor $processor ): void {}

    /**
     * Return the background process.
     *
     * @return ?Background_Process
     */
    abstract public function get_background_process(): ?Background_Process;

}
