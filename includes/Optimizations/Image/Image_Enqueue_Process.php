<?php
/**
 * Image enqueue process.
 *
 * Intermediate background process that receives chunks of attachment IDs,
 * filters out already-optimized attachments, and pushes the remaining IDs
 * to the `Image_Optimization_Process` queue for actual optimization.
 *
 * This avoids memory exhaustion on sites with large media libraries,
 * since the filtering happens in the background — respecting time and
 * memory limits, rather than in a single HTTP request.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

use Pressidium\WP\Performance\Background_Process;
use Pressidium\WP\Performance\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Image_Enqueue_Process class.
 *
 * Each queue item is an array (chunk) of attachment IDs. The task
 * warms the post meta cache for the whole chunk in a single query,
 * filters out attachments whose metadata already contains
 * `is_optimized => true`, and pushes the rest to the
 * `Image_Optimization_Process` queue.
 *
 * When all chunks have been processed, `complete()` dispatches
 * the optimization process so the actual image optimization begins.
 *
 * @since 1.0.0
 */
class Image_Enqueue_Process extends Background_Process {

    /**
     * @var string The unique prefix to use for the queue.
     */
    protected string $prefix = 'pressidium_performance';

    /**
     * @var string The action to perform.
     */
    protected string $action = 'enqueue_image_optimizations';

    /**
     * @var ?Image_Optimization_Process Reference to the optimization process.
     */
    private ?Image_Optimization_Process $optimization_process = null;

    /**
     * Image_Enqueue_Process constructor.
     *
     * @param Logger $logger An instance of `Logger`.
     */
    public function __construct( protected readonly Logger $logger ) {
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
     * Set the optimization process to push items into.
     *
     * Uses setter injection to avoid a circular dependency
     * (the manager creates both processes and wires them together).
     *
     * @param Image_Optimization_Process $process Optimization process.
     *
     * @return void
     */
    public function set_optimization_process( Image_Optimization_Process $process ): void {
        $this->optimization_process = $process;
    }

    /**
     * Whether the given attachment has already been optimized.
     *
     * Checks the `is_optimized` flag in the attachment metadata.
     * Assumes the post meta cache has already been primed.
     *
     * @param int $attachment_id Attachment ID.
     *
     * @return bool
     */
    private function is_already_optimized( int $attachment_id ): bool {
        /*
         * This is going to get the attachment metadata from cache
         * since we have already warmed the post meta cache for the
         * whole chunk in the `task()` method.
         */
        $metadata = wp_get_attachment_metadata( $attachment_id );

        if ( ! is_array( $metadata ) || ! isset( $metadata['is_optimized'] ) ) {
            return false;
        }

        $flag = $metadata['is_optimized'];

        return $flag === true || $flag === 1 || $flag === '1' || $flag === 'true';
    }

    /**
     * Perform task with a queued chunk of attachment IDs.
     *
     * Warms the meta cache for the entire chunk in a single query,
     * then pushes unoptimized attachment IDs to the optimization
     * process queue.
     *
     * @param mixed $item Queue item (array of attachment IDs).
     *
     * @return false Always returns false to remove the chunk from the queue.
     */
    protected function task( $item ) {
        if ( $this->optimization_process === null ) {
            $this->logger->error(
                sprintf( '[%s] Optimization process not set, cannot enqueue attachments.', esc_html( $this->action ) )
            );

            return false;
        }

        $chunk = (array) $item;

        if ( empty( $chunk ) ) {
            return false;
        }

        $this->logger->debug(
            sprintf( '[%s] Processing chunk of %d attachment ID(s).', esc_html( $this->action ), count( $chunk ) )
        );

        // Warm the post meta cache for the entire chunk in a single query
        update_meta_cache( 'post', $chunk );

        $enqueued = 0;

        foreach ( $chunk as $attachment_id ) {
            $attachment_id = (int) $attachment_id;

            if ( $this->is_already_optimized( $attachment_id ) ) {
                continue;
            }

            $this->optimization_process->push_to_queue( $attachment_id );
            $enqueued += 1;
        }

        // Persist the optimization queue after each chunk
        $this->optimization_process->save();

        $this->logger->debug(
            sprintf(
                '[%s] Enqueued %d of %d attachment(s) for optimization.',
                esc_html( $this->action ),
                $enqueued,
                count( $chunk )
            )
        );

        return false;
    }

    /**
     * Complete processing.
     *
     * Called when all chunks have been processed.
     * Dispatches the optimization process so the actual image optimization begins.
     *
     * @return void
     */
    protected function complete(): void {
        parent::complete();

        if ( $this->optimization_process !== null ) {
            $this->logger->info(
                sprintf( '[%s] All chunks processed, dispatching image optimization process.', esc_html( $this->action ) )
            );
            $this->optimization_process->dispatch();
        }
    }

    /**
     * Return the items to process.
     *
     * Limited to the items of the next batch.
     *
     * @return array<int, array{attachment_ids: int}>
     */
    public function get_items(): array {
        $batches = $this->get_batches( 1 );

        if ( empty( $batches ) ) {
            return array();
        }

        $next_batch = $batches[0];
        $items      = array();

        foreach ( $next_batch->data as $chunk ) {
            $chunk = (array) $chunk;

            $items[] = array(
                'attachments_count' => count( $chunk ),
            );
        }

        return $items;
    }

}


