<?php
/**
 * Minify background process.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification\JS;

use Pressidium\WP\Performance\Background_Process;
use Pressidium\WP\Performance\Files\File;
use Pressidium\WP\Performance\Files\File_Reader;
use Pressidium\WP\Performance\Files\File_Writer;
use Pressidium\WP\Performance\URL_Builder;
use Pressidium\WP\Performance\SRI_Validator;
use Pressidium\WP\Performance\Optimizations\Minification\Minification_Payload;
use Pressidium\WP\Performance\Optimizations\Optimization_Record;
use Pressidium\WP\Performance\Database\Tables\Optimizations_Table;
use Pressidium\WP\Performance\Utils\Numeric_Utils;
use Pressidium\WP\Performance\Logging\Logger;

use Pressidium\WP\Performance\Enumerations\Output_Directory;

use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;
use Pressidium\WP\Performance\Exceptions\Minification_Exception;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Minify_Process class.
 *
 * @since 1.0.0
 */
final class Minify_Process extends Background_Process {

    /**
     * @var string The unique prefix to use for the queue.
     */
    protected string $prefix = 'pressidium_performance';

    /**
     * @var string The action to perform.
     */
    protected string $action = 'minify_js_process';

    /**
     * Minify_Process constructor.
     *
     * @param Logger              $logger              An instance of `Logger`.
     * @param File_Reader         $file_reader         An instance of `File_Reader`.
     * @param File_Writer         $file_writer         An instance of `File_Writer`.
     * @param URL_Builder         $url_builder         An instance of `URL_Builder`.
     * @param Minifier            $minifier            An instance of `Minifier`.
     * @param Optimizations_Table $optimizations_table An instance of `Optimizations_Table`.
     * @param SRI_Validator       $sri_validator       An instance of `SRI_Validator`.
     */
    public function __construct(
        protected readonly Logger $logger,
        protected readonly File_Reader $file_reader,
        protected readonly File_Writer $file_writer,
        protected readonly URL_Builder $url_builder,
        protected readonly Minifier $minifier,
        protected readonly Optimizations_Table $optimizations_table,
        protected readonly SRI_Validator $sri_validator,
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
     * Actually minify the given file.
     *
     * @throws Minification_Exception If the file could not be minified.
     * @throws Filesystem_Exception   If the file could not be written.
     *
     * @param File    $file     File to minify.
     * @param ?string $sri_hash Hash we validated the file against, if any.
     *
     * @return void
     */
    private function minify_file( File $file, ?string $sri_hash = null ): void {
        // Minify file
        $this->logger->debug( "[{$this->action}] Minifying file..." );
        $minified_file = $this->minifier->minify( $file );

        if ( $minified_file === null ) {
            $this->logger->debug( "[{$this->action}] File no longer needs minifying, skipping..." );
            return;
        }

        // Save the minified file
        $this->logger->debug( "[{$this->action}] Saving minified file..." );
        $this->file_writer->write( $minified_file, Output_Directory::MINIFIED->value );

        // Store minified file info in the database
        $this->logger->debug( "[{$this->action}] Storing minified file info in the database..." );
        $this->logger->debug( "[{$this->action}] Original file hash: {$file->get_content_hash()}" );

        // Build the optimized URI
        $optimized_uri = $this->url_builder->build_url(
            Output_Directory::MINIFIED->value,
            $minified_file->get_path()
        );

        // Store the optimization record
        $optimization_record = new Optimization_Record();
        $optimization_record
            ->set_original_uri( $file->get_url() )
            ->set_optimized_uri( $optimized_uri )
            ->set_content_hash( $file->get_content_hash() )
            ->set_original_size( $file->get_size_in_bytes() )
            ->set_optimized_size( $minified_file->get_size_in_bytes() )
            ->set_sri_hash( $sri_hash );

        try {
            $was_stored = $this->optimizations_table->set_optimization_record( $optimization_record );

            if ( ! $was_stored ) {
                $this->logger->error(
                    sprintf( '[%s] Could not store the optimization record for: %s',
                        esc_html( $this->action ),
                        esc_url( $file->get_url() )
                    )
                );
            }
        } catch ( Exception $exception ) {
            $this->logger->log_exception( $exception );
        }
    }

    /**
     * Perform task with queued item.
     *
     * Perform any actions required on each queue item.
     * Return the modified item for further processing
     * in the next pass-through. Or, return `false` to
     * remove the item from the queue.
     *
     * @param mixed $item Queue item to iterate over.
     *
     * @return mixed
     */
    protected function task( $item ) {
        $this->logger->debug( 'Processing minify task in the background...' );

        if ( ! ( $item instanceof Minification_Payload ) ) {
            // Queued item is not valid, remove it from the queue
            $this->logger->warning(
                sprintf( '[%s] Queued item is not valid, removing it from queue.', esc_html( $this->action ) )
            );
            return false;
        }

        $file_uri   = $item->get_file_uri();
        $post_id    = $item->get_post_id();
        $sri_hashes = $item->get_sri_hashes();

        try {
            $file = $this->file_reader->read_remote( $file_uri );

            $this->logger->debug(
                sprintf( '[%s] Processing %s in the background...',
                    esc_html( $this->action ),
                    esc_url( $file->get_url() )
                )
            );

            $sri_hash = null;

            if ( ! empty( $sri_hashes ) ) {
                // We only need to store the one hash the file actually matched
                $sri_hash = $this->sri_validator->get_matching_hash( $file, $sri_hashes );

                if ( $sri_hash === null ) {
                    /*
                     * The file doesn't match the integrity metadata of the tag that referenced it.
                     * We intentionally record nothing here, so that the original tag is left alone.
                     */
                    $this->logger->warning(
                        sprintf( '[%s] SRI validation failed for file: %s, removing it from the queue.',
                            esc_html( $this->action ),
                            esc_url( $file_uri )
                        )
                    );

                    return false;
                }
            }

            $this->minify_file( $file, $sri_hash );
        } catch ( Minification_Exception | Filesystem_Exception $exception ) {
            $this->logger->error(
                sprintf( '[%s] Could not minify file: %s',
                    esc_html( $this->action ),
                    esc_url( $file_uri )
                )
            );
            $this->logger->log_exception( $exception );
        }

        // Clean post from cache
        clean_post_cache( $post_id );

        $this->logger->debug(
            sprintf( '[%s] File is done processing, removing it from the queue.', esc_html( $this->action ) )
        );

        return false;
    }

    /**
     * Complete processing.
     *
     * @return void
     */
    protected function complete(): void {
        parent::complete();

        $this->logger->info( sprintf( '[%s] All JS minifications were complete.', esc_html( $this->action ) ) );
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
            $this->logger->debug( '--> No items to process...' );
            return array();
        }

        $next_batch = $batches[0];
        $items      = array();

        foreach ( $next_batch->data as $item ) {
            if ( ! ( $item instanceof Minification_Payload ) ) {
                // Queued item is not valid, skip it
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
