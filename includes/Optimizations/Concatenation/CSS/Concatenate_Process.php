<?php
/**
 * CSS concatenate background process.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\CSS;

use Pressidium\WP\Performance\Background_Process;
use Pressidium\WP\Performance\Database\Tables\Concatenations_Table;
use Pressidium\WP\Performance\Enumerations\Output_Directory;
use Pressidium\WP\Performance\Exceptions\Concatenation_Exception;
use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;
use Pressidium\WP\Performance\Files\File_Reader;
use Pressidium\WP\Performance\Files\File_Writer;
use Pressidium\WP\Performance\SRI_Validator;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenate_Payload;
use Pressidium\WP\Performance\Optimizations\Concatenation_Record;
use Pressidium\WP\Performance\Optimizations\Minification\Minifier;
use Pressidium\WP\Performance\URL_Builder;
use Pressidium\WP\Performance\Utils\Numeric_Utils;

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
     * @var string The unique prefix to use for the queue.
     */
    protected string $prefix = 'pressidium_performance';

    /**
     * @var string The action to perform.
     */
    protected string $action = 'concatenate_css_process';

    /**
     * Concatenate_Process constructor.
     *
     * @param Logger               $logger               An instance of `Logger`.
     * @param File_Reader          $file_reader          An instance of `File_Reader`.
     * @param File_Writer          $file_writer          An instance of `File_Writer`.
     * @param URL_Builder          $url_builder          An instance of `URL_Builder`.
     * @param Minifier             $minifier             An instance of `Minifier`.
     * @param Concatenator         $concatenator         An instance of `Concatenator`.
     * @param Concatenations_Table $concatenations_table An instance of `Concatenations_Table`.
     * @param SRI_Validator        $sri_validator        An instance of `SRI_Validator`.
     * @param array                $settings             Settings.
     */
    public function __construct(
        protected readonly Logger $logger,
        protected readonly File_Reader $file_reader,
        protected readonly File_Writer $file_writer,
        protected readonly URL_Builder $url_builder,
        protected readonly Minifier $minifier,
        protected readonly Concatenator $concatenator,
        protected readonly Concatenations_Table $concatenations_table,
        protected readonly SRI_Validator $sri_validator,
        protected readonly array $settings,
    ) {
        parent::__construct();
    }

    /**
     * Actually concatenate the given files.
     *
     * @throws Filesystem_Exception If the file could not be written.
     *
     * @param string $file_uri        URI of the file to concatenate.
     * @param string $contents        File contents.
     * @param string $aggregated_hash Aggregated hash of the concatenated files.
     * @param string $type            Type of the concatenated files.
     *
     * @return void
     */
    private function concatenate_file( string $file_uri, string $contents, string $aggregated_hash, string $type ): void {
        // Concatenate files
        $this->logger->info(
            sprintf( 'Concatenating stylesheet (%s, %s)', esc_url( $file_uri ), esc_html( $aggregated_hash ) )
        );

        // Append the file contents to the concatenated file
        $file_location = $this->concatenator->concatenate( $contents, $type, $aggregated_hash );

        // Build the concatenated URI
        $concatenated_uri = $this->url_builder->build_url(
            Output_Directory::CONCATENATED->value,
            basename( $file_location )
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
     * Perform task with queued item.
     *
     * Perform any actions required on each queue item.
     * Return the modified item for further processing
     * in the next pass through. Or, return `false` to
     * remove the item from the queue.
     *
     * @param mixed $item Queue item to iterate over.
     *
     * @return mixed
     */
    protected function task( $item ) {
        $this->logger->debug( 'Processing concatenate task in the background...' );

        if ( ! ( $item instanceof Concatenate_Payload ) ) {
            // Queued item is not an instance of `Concatenate_Payload`, remove it from the queue
            $this->logger->warning(
                sprintf( '[%s] Queued item is not a valid payload, removing from queue.', esc_html( $this->action ) )
            );
            return false;
        }

        $file_uri = $item->get_file_uri();
        $sri_hash = $item->get_sri_hash();
        $post_id  = $item->get_post_id();

        $chain_id = $this->get_chain_id();

        update_option( "concatenate_process_{$chain_id}_post_id", $post_id );

        // Read the file
        $file = $this->file_reader->read_remote( $file_uri );

        if ( $sri_hash ) {
            /*
             * If SRI is present, we need to ensure that the script is not modified
             * by performing the validation on the server side. If the file is modified,
             * the SRI validation will fail, and we should skip the concatenation of that
             * file. If the file is not modified, we can safely concatenate it, but we
             * need to flag it as having SRI to ensure that we remove the `integrity`
             * attribute from the script tag in the post-processing step.
             */

            $is_sri_hash_valid = $this->sri_validator->is_valid( $file, $item->get_sri_hash() );

            if ( ! $is_sri_hash_valid ) {
                $this->logger->warning(
                    sprintf(
                        '[%s] SRI validation failed for file: %s, removing from queue.',
                        esc_html( $this->action ),
                        esc_html( $file_uri )
                    )
                );

                return false;
            }
        }

        try {
            $this->concatenate_file(
                $file_uri,
                $file->get_contents(),
                $item->get_aggregated_hash(),
                $item->get_type()
            );
        } catch ( Concatenation_Exception | Filesystem_Exception $exception ) {
            $this->logger->error( sprintf( '[%s] Could not concatenate file.', esc_html( $this->action ) ) );
            $this->logger->log_exception( $exception );
        }

        $this->logger->debug( sprintf( '[%s] File processed, removing from queue.', esc_html( $this->action ) ) );
        return false;
    }

    /**
     * Complete processing.
     *
     * @return void
     */
    protected function complete(): void {
        parent::complete();

        $this->logger->info( sprintf( '[%s] All concatenations were complete.', esc_html( $this->action ) ) );

        $chain_id = $this->get_chain_id();
        $post_id  = get_option( "concatenate_process_{$chain_id}_post_id" );

        if ( $post_id ) {
            clean_post_cache( $post_id );
        }

        delete_option( "concatenate_process_{$chain_id}_post_id" );
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
