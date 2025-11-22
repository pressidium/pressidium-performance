<?php
/**
 * JS concatenation background process.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\JS;

use Pressidium\WP\Performance\Options\Options;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Background_Process;
use Pressidium\WP\Performance\Files\File_Reader;
use Pressidium\WP\Performance\SRI_Validator;
use Pressidium\WP\Performance\URL_Builder;
use Pressidium\WP\Performance\Enumerations\Output_Directory;
use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenate_Payload;
use Pressidium\WP\Performance\Optimizations\Concatenation_Record;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Table;
use Pressidium\WP\Performance\Utils\Numeric_Utils;
use Pressidium\WP\Performance\Exceptions\Concatenation_Exception;
use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenate_Process class.
 *
 * @since 1.0.0
 */
final class Concatenate_Process extends Background_Process {

    /**
     * @var string Unique prefix to use for the queue.
     */
    protected $prefix = 'pressidium_performance';

    /**
     * @var string Action to perform.
     */
    protected $action = 'concatenate_js_process';

    /**
     * Concatenate_Process constructor.
     *
     * @param Options              $options
     * @param Logger               $logger
     * @param File_Reader          $file_reader
     * @param SRI_Validator        $sri_validator
     * @param URL_Builder          $url_builder
     * @param Concatenator         $concatenator
     * @param Concatenations_Table $concatenations_table
     */
    public function __construct(
        private readonly Options $options,
        private readonly Logger $logger,
        private readonly File_Reader $file_reader,
        private readonly SRI_Validator $sri_validator,
        private readonly URL_Builder $url_builder,
        private readonly Concatenator $concatenator,
        private readonly Concatenations_Table $concatenations_table,
    ) {
        parent::__construct();
    }

    /**
     * Log a debug message.
     *
     * @param string $message Message to log.
     *
     * @return void
     */
    private function log_debug( string $message ): void {
        $this->logger->debug( sprintf( '[%s] %s', esc_html( $this->action ), esc_html( $message ) ) );
    }

    /**
     * Log an informational message.
     *
     * @param string $message Message to log.
     *
     * @return void
     */
    private function log_info( string $message ): void {
        $this->logger->info( sprintf( '[%s] %s', esc_html( $this->action ), esc_html( $message ) ) );
    }

    /**
     * Log a warning message.
     *
     * @param string $message Message to log.
     *
     * @return void
     */
    private function log_warning( string $message ): void {
        $this->logger->warning( sprintf( '[%s] %s', esc_html( $this->action ), esc_html( $message ) ) );
    }

    /**
     * Log an error message.
     *
     * @param string $message Message to log.
     *
     * @return void
     */
    private function log_error( string $message ): void {
        $this->logger->error( sprintf( '[%s] %s', esc_html( $this->action ), esc_html( $message ) ) );
    }

    /**
     * Actually concatenate the given files.
     *
     * @throws Concatenation_Exception If the files could not be concatenated.
     * @throws Filesystem_Exception    If the file could not be written.
     *
     * @param string $file_uri        URI of the file to concatenate.
     * @param string $contents        Contents of the file to concatenate.
     * @param string $aggregated_hash Aggregated hash of the concatenated files.
     * @param string $type            Type of the concatenated files.
     *
     * @return void
     */
    private function concatenate_file(
        string $file_uri,
        string $contents,
        string $aggregated_hash,
        string $type
    ): void {
        $this->log_info(
            sprintf(
                'Concatenating script (%s, %s, %s)',
                esc_url( $file_uri ),
                esc_html( $aggregated_hash ),
                esc_html( $type )
            )
        );

        // Append the file contents to the concatenated file
        $concatenated_file_location = $this->concatenator->concatenate( $contents, $type, $aggregated_hash );

        // Build the concatenated URI
        $concatenated_uri = $this->url_builder->build_url(
            Output_Directory::CONCATENATED->value,
            basename( $concatenated_file_location )
        );

        // Store the concatenation record
        $concatenation_record = new Concatenation_Record();
        $concatenation_record
            ->set_aggregated_hash( $aggregated_hash )
            ->set_type( $type )
            ->set_concatenated_uri( $concatenated_uri )
            ->set_is_minified( false );

        try {
            $this->concatenations_table->set_concatenation_record( $concatenation_record );
        } catch ( Exception $exception ) {
            $this->logger->log_exception( $exception );
        }
    }

    /**
     * Return the contents to write to the concatenated file.
     *
     * @param string $file_uri      URI of the file to concatenate.
     * @param string $file_contents Contents of the file to concatenate.
     * @param string $script_type   Type of the script, either 'script' or 'module'.
     *
     * @return string
     */
    private function get_contents_to_write( string $file_uri, string $file_contents, string $script_type ): string {
        $evaluator = new JS_Evaluator( $file_contents, $script_type );
        $is_iife   = $evaluator->is_program_an_iife();

        $contents_to_write = "\t'" . $file_uri . "': ";

        if ( $is_iife ) {
            $this->log_debug( 'Source is safe to be wrapped in a function' );

            $contents_to_write .= "(function() {\n" . $file_contents . "\n}),";

            return $contents_to_write;
        }

        // If the program is not an IIFE, we can base64 encode it
        $this->log_debug( 'Source is suspected to contain global variables, base64 encoding it' );
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        $contents_to_write .= "'" . base64_encode( $file_contents ) . "',";

        return $contents_to_write;
    }

    /**
     * Perform task with queued item.
     *
     * Perform any actions required on each queued item.
     * Return the modified item for further processing
     * in the next pass through. Or, return `false` to
     * remove the item from the queue.
     *
     * @param mixed $item Queued item to iterate over.
     *
     * @return mixed
     */
    protected function task( $item ) {
        $this->log_debug( 'Processing task in the background...' );

        if ( ! ( $item instanceof Concatenate_Payload ) ) {
            // Queued item is not an instance of `Concatenate_Payload`, remove it from the queue
            $this->log_warning( 'Queued item is not a valid payload, removing it from queue.' );
            return false;
        }

        $file_uri        = $item->get_file_uri();
        $sri_hash        = $item->get_sri_hash();
        $script_type     = $item->get_type();
        $aggregated_hash = $item->get_aggregated_hash();
        $post_id         = $item->get_post_id();

        // TODO: (enhancement) Replace `get_option()` calls with our `Options` abstraction (constructor dependency)

        $chain_id = $this->get_chain_id();

        $opt_to_store    = array();
        $existing_option = get_option( "concatenate_process_{$chain_id}_hash" );

        if ( $existing_option ) {
            $opt_to_store = $existing_option;
        }

        $opt_to_store[ $script_type ] = $aggregated_hash;

        update_option( "concatenate_process_{$chain_id}_hash", $opt_to_store );
        update_option( "concatenate_process_{$chain_id}_post_id", $post_id );

        $this->log_info( 'Storing aggregated hash: ' . esc_html( $aggregated_hash ) . ' (' . $chain_id . ')' );

        try {
            $file = $this->file_reader->read_remote( $file_uri );
        } catch ( Filesystem_Exception $exception ) {
            $this->log_error( sprintf( 'Could not read file: %s', esc_html( $file_uri ) ) );
            $this->logger->log_exception( $exception );

            // Skip this file and remove it from the queue
            return false;
        }

        if ( $sri_hash ) {
            $is_sri_hash_valid = $this->sri_validator->is_valid( $file, $sri_hash );

            if ( ! $is_sri_hash_valid ) {
                $this->log_warning(
                    sprintf( 'SRI validation failed for file: %s, removing from queue.', esc_html( $file_uri ) )
                );

                return false;
            }

            $this->log_debug( 'SRI validation passes for file: ' . esc_html( $file_uri ) );
        }

        try {
            $this->concatenate_file(
                $file_uri,
                $this->get_contents_to_write( $file_uri, $file->get_contents(), $script_type ),
                $aggregated_hash,
                $script_type
            );
        } catch ( Concatenation_Exception | Filesystem_Exception $exception ) {
            $this->log_error( 'Could not concatenate file.' );
            $this->logger->log_exception( $exception );
        }

        $this->log_debug( 'File is done processing, removing it from the queue.' );

        return false;
    }

    /**
     * Complete processing.
     *
     * @return void
     */
    protected function complete(): void {
        parent::complete();

        $chain_id = $this->get_chain_id();

        $aggregated_hashes = get_option( "concatenate_process_{$chain_id}_hash" );
        $post_id           = get_option( "concatenate_process_{$chain_id}_post_id" );

        if ( $aggregated_hashes && is_array( $aggregated_hashes ) ) {
            foreach ( $aggregated_hashes as $type => $aggregated_hash ) {
                $this->concatenator->close_file( $aggregated_hash );
                $this->log_info( 'All concatenations were complete (' . $chain_id . ', ' . $aggregated_hash . ').' );
            }

            // Clean post from cache
            if ( $post_id ) {
                clean_post_cache( $post_id );
            }

            // Clean up the stored hash and post ID
            delete_option( "concatenate_process_{$chain_id}_hash" );
            delete_option( "concatenate_process_{$chain_id}_post_id" );
        }
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
            return array();
        }

        $next_batch = $batches[0];
        $items      = array();

        foreach ( $next_batch->data as $item ) {
            if ( ! ( $item instanceof Concatenate_Payload ) ) {
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
