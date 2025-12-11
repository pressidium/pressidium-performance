<?php
/**
 * Processor manager.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

use Pressidium\WP\Performance\Enumerations\HTML_Tag;
use Pressidium\WP\Performance\Hooks\Actions;
use Pressidium\WP\Performance\Hooks\Filters;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Utils\WP_Utils;

use Pressidium\WP\Performance\Enumerations\HTML_Bookmark;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Processor_Manager class.
 *
 * @since 1.0.0
 */
final class Processor_Manager implements Actions, Filters {

    /**
     * @var Processor[] Processor instances.
     */
    private array $processors = array();

    /**
     * Tag_Processor_Manager constructor.
     *
     * @param Logger $logger An instance of `Logger`.
     */
    public function __construct( private readonly Logger $logger ) {}

    /**
     * Register a processor.
     *
     * @param Processor $processor Processor to register.
     *
     * @return void
     */
    public function register_processor( Processor $processor ): void {
        $this->processors[] = $processor;
    }

    /**
     * Return all background processes.
     *
     * @return Background_Process[]
     */
    public function get_background_processes(): array {
        return array_map(
            function ( $processor ) {
                return $processor->get_background_process();
            },
            $this->processors
        );
    }

    /**
     * Iterate over tags in the HTML, calling the given callback for each one.
     *
     * @param HTML_Processor $html_processor HTML processor.
     * @param callable       $callback       Callback to call for each tag.
     *
     * @return void
     */
    private function iterate_tags( HTML_Processor $html_processor, callable $callback ): void {
        $callback();

        while ( $html_processor->next_tag( array( HTML_Tag::SCRIPT->value, HTML_Tag::LINK->value ) ) ) {
            $callback();
        }
    }

    /**
     * Iterate over registered processors, calling the given callback for each one.
     *
     * @param callable $callback Callback to call for each processor.
     *
     * @return void
     */
    private function iterate_processors( callable $callback ): void {
        foreach ( $this->processors as $processor ) {
            $callback( $processor );
        }
    }

    /**
     * Iterate over the entire HTML document, calling the given callback for each tag and processor.
     *
     * @param HTML_Processor $html_processor HTML processor.
     * @param callable       $callback       Callback to call for each tag and processor.
     *
     * @return void
     */
    private function iterate_document( HTML_Processor $html_processor, callable $callback ): void {
        // Seek back to the start of the HTML
        $html_processor->seek( 'start' );

        $this->iterate_tags(
            $html_processor,
            function () use ( $html_processor, $callback ) {
                $this->iterate_processors(
                    function ( $processor ) use ( $html_processor, $callback ) {
                        $callback( $html_processor, $processor );
                    }
                );
            }
        );
    }

    /**
     * Initialize the HTML processor and set a bookmark at the start of the HTML.
     *
     * @param string $html HTML to process.
     *
     * @return HTML_Processor Initialized HTML processor.
     */
    private function init_html_processor( string $html ): HTML_Processor {
        $html_processor = new HTML_Processor( $html );

        $html_processor->next_tag( array( HTML_Tag::SCRIPT->value, HTML_Tag::LINK->value ) );

        // Set a bookmark at the start of the HTML, so we can seek back to it later
        $html_processor->set_bookmark( HTML_Bookmark::START->value );

        return $html_processor;
    }

    /**
     * Process each tag in the HTML with the registered processors.
     *
     * @param HTML_Processor $html_processor
     *
     * @return void
     */
    private function process_tags( HTML_Processor $html_processor ): void {
        $this->iterate_document(
            $html_processor,
            function ( $html_processor, $processor ) {
                $processor->process( $html_processor );
            }
        );
    }

    /**
     * Run the `complete_process()` method for each processor.
     *
     * @return void
     */
    private function complete_process_tags(): void {
        $this->iterate_processors(
            function ( $processor ) {
                $processor->complete_process();
            }
        );
    }

    /**
     * Process each tag in the HTML with the registered processors in a second pass.
     *
     * @param HTML_Processor $html_processor
     *
     * @return void
     */
    private function postprocess_tags( HTML_Processor $html_processor ): void {
        $this->iterate_document(
            $html_processor,
            function ( $html_processor, $processor ) {
                $processor->postprocess( $html_processor );
            }
        );
    }

    /**
     * Run the `complete_postprocess()` method for each processor.
     *
     * @param HTML_Processor $html_processor
     *
     * @return void
     */
    private function complete_postprocess_tags( HTML_Processor $html_processor ): void {
        $this->iterate_processors(
            function ( $processor ) use ( $html_processor ) {
                $processor->complete_postprocess( $html_processor );
            }
        );
    }

    /**
     * Process the HTML to optimize it.
     *
     * @link https://developer.wordpress.org/reference/classes/wp_html_tag_processor/
     *
     * @param string $html Contents of the output buffer.
     *
     * @return string New output buffer, which will be sent to the browser.
     */
    public function process( string $html ): string {
        $this->logger->debug( 'Output generated, processing it via ' . count( $this->processors ) . ' processor(s).' );

        $html_processor = $this->init_html_processor( $html );

        // First pass
        $this->process_tags( $html_processor );
        $this->complete_process_tags();

        // Second pass
        $this->postprocess_tags( $html_processor );
        $this->complete_postprocess_tags( $html_processor );

        return $html_processor->get_updated_html();
    }

    /**
     * Whether this request is for an asset.
     *
     * @return bool
     */
    private function is_asset(): bool {
        $request_uri = WP_Utils::get_request_uri();

        // Regular expression to match a file extension at the end of the URI
        return preg_match( '/\.\w+$/', $request_uri ) === 1;
    }

    /**
     * Return whether the output should be processed.
     *
     * @return bool
     */
    private function should_process_output(): bool {
        return ! wp_doing_ajax() && ! wp_doing_cron() && ! is_admin() && ! $this->is_asset();
    }

    /**
     * Output `<script>` slots immediately following the opening `<head>` tag.
     *
     * These slots will be used by our processors to inject the concatenated scripts.
     * To achieve this, we hook into `wp_head` with a very low priority (`-1`)
     * so that our slots are output before any other tags.
     *
     * @link https://developer.wordpress.org/reference/hooks/wp_head/
     *
     * @return void
     */
    public function output_script_slots(): void {
        ?>

        <script type="text/javascript" data-pressidium-performance-slot="head-start"></script>
        <script type="module" data-pressidium-performance-slot="head-start"></script>

        <?php
    }

    /**
     * Return the actions to register.
     *
     * @return array<string, array{0: string, 1?: int, 2?: int}>
     */
    public function get_actions(): array {
        return array(
            'wp_head' => array( 'output_script_slots', -1 ),
        );
    }

    /**
     * Return the filters to register.
     *
     * @return array<string, array{0: string, 1?: int, 2?: int}>
     */
    public function get_filters(): array {
        $filters = array();

        if ( $this->should_process_output() ) {
            $filters['wp_template_enhancement_output_buffer'] = array( 'process' );
        }

        return $filters;
    }

}
