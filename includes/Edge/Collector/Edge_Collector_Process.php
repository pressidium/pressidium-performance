<?php
/**
 * EDGE collector background process.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector;

use Pressidium\WP\Performance\Background_Process;
use Pressidium\WP\Performance\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Edge_Collector_Process class.
 *
 * Splits the EDGE Collector wok across multiple background tasks so each task
 * stays well under PHP `max_execution_time` and `memory_limit`.
 *
 * Queue items are plain associative arrays with the following shape:
 *
 *   { type: 'chunk',    run_id, collector, chunk_key, chunk: ?array }
 *   { type: 'finalize', run_id }
 *
 * `chunk` items defer to {@see Collector_Manager::run_chunk()} which persists
 * the result to the `collector_state` table.
 *
 * The trailing `finalize` item calls {@see Collector_Manager::finalize_run()}
 * which reassembles the payload and send a POST request to the EDGE API.
 *
 * @since 1.2.0
 */
class Edge_Collector_Process extends Background_Process {

    /**
     * @var string The unique prefix to use for the queue.
     */
    protected string $prefix = 'pressidium_performance';

    /**
     * @var string The action to perform.
     */
    protected string $action = 'collect_edge_data';

    /**
     * Edge_Collector_Process constructor.
     *
     * @param Logger            $logger            An instance of `Logger`.
     * @param Collector_Manager $collector_manager Collector orchestrator.
     */
    public function __construct(
        protected readonly Logger $logger,
        protected readonly Collector_Manager $collector_manager
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
     * Start a new collector run.
     *
     * Asks the collector manager for the queue items, pushes them, then dispatches the
     * background process. Returns the run identifier so callers can surface it in the
     * response or use it to drive background process polling.
     *
     * Returns `null` when no run could be started (either no collectors are
     * registered, the web property handle is missing, or another collector
     * process is already running).
     *
     * @return ?string
     */
    public function start_run(): ?string {
        if ( $this->is_active() ) {
            $this->logger->info(
                sprintf(
                    '[%s] A collector process is already running, will not start a new one.',
                    esc_html( $this->action )
                )
            );

            return null;
        }

        $run_id = wp_generate_uuid4();
        $items  = $this->collector_manager->build_chunks( $run_id );

        if ( empty( $items ) ) {
            $this->logger->info(
                sprintf( '[%s] No chunks to enqueue, skipping dispatch.', esc_html( $this->action ) )
            );

            return null;
        }

        foreach ( $items as $item ) {
            $this->push_to_queue( $item );
        }

        $this->save()->dispatch();

        $this->logger->info(
            sprintf(
                '[%s] Dispatched collector run %s with %d task(s).',
                esc_html( $this->action ),
                esc_html( $run_id ),
                count( $items )
            )
        );

        return $run_id;
    }

    /**
     * Perform task with queued item.
     *
     * @param mixed $item Queue item.
     *
     * @return mixed Return `false` to remove the item from the queue.
     */
    protected function task( $item ) {
        if ( ! is_array( $item ) || ! isset( $item['type'], $item['run_id'] ) ) {
            $this->logger->warning(
                sprintf( '[%s] Malformed queue item, dropping.', esc_html( $this->action ) )
            );

            return false;
        }

        $run_id = (string) $item['run_id'];
        $type   = (string) $item['type'];

        if ( $type === 'chunk' ) {
            $collector = isset( $item['collector'] ) ? (string) $item['collector'] : '';
            $chunk_key = isset( $item['chunk_key'] ) ? (string) $item['chunk_key'] : '';
            $chunk     = isset( $item['chunk'] ) && is_array( $item['chunk'] ) ? $item['chunk'] : null;

            if ( empty( $collector ) || empty( $chunk_key ) ) {
                $this->logger->warning(
                    sprintf(
                        '[%s] Chunk item missing collector or chunk_key, dropping.',
                        esc_html( $this->action )
                    )
                );

                return false;
            }

            $this->logger->debug(
                sprintf(
                    '[%s] Running %s chunk "%s" (run %s).',
                    esc_html( $this->action ),
                    esc_html( $collector ),
                    esc_html( $chunk_key ),
                    esc_html( $run_id )
                )
            );

            $this->collector_manager->run_chunk( $run_id, $collector, $chunk_key, $chunk );

            return false;
        }

        if ( $type === 'finalize' ) {
            $this->logger->debug(
                sprintf( '[%s] Finalizing collector run %s.', esc_html( $this->action ), esc_html( $run_id ) )
            );

            $this->collector_manager->finalize_run( $run_id );

            return false;
        }

        $this->logger->warning(
            sprintf(
                '[%s] Unknown queue item type "%s", dropping.',
                esc_html( $this->action ),
                esc_html( $type )
            )
        );

        return false;
    }

    /**
     * Called once the queue has drained.
     *
     * @return void
     */
    protected function complete(): void {
        parent::complete();

        $this->logger->info( sprintf( '[%s] Collector run complete.', esc_html( $this->action ) ) );
    }

    /**
     * Return the items currently queued, for the `/processes` API.
     *
     * Each entry summarizes one pending task: which collector and chunk it
     * targets, or `finalize` for the trailing finalize step.
     *
     * @return array<int, array{type: string, collector?: string, chunk_key?: string}>
     */
    public function get_items(): array {
        $batches = $this->get_batches( 1 );

        if ( empty( $batches ) ) {
            return array();
        }

        $items = array();

        foreach ( $batches[0]->data as $item ) {
            if ( ! is_array( $item ) || ! isset( $item['type'] ) ) {
                continue;
            }

            $summary = array( 'type' => (string) $item['type'] );

            if ( isset( $item['collector'] ) ) {
                $summary['collector'] = (string) $item['collector'];
            }

            if ( isset( $item['chunk_key'] ) ) {
                $summary['chunk_key'] = (string) $item['chunk_key'];
            }

            $items[] = $summary;
        }

        return $items;
    }

}
