<?php
/**
 * Collector manager.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector;

use Pressidium\WP\Performance\Logging\Logger;

use Pressidium\WP\Performance\Database\Tables\Collector_State_Table;

use Pressidium\WP\Performance\Edge\API\Edge_API_Client;
use Pressidium\WP\Performance\Edge\API\Collector\Collector_Request;
use Pressidium\WP\Performance\Edge\Web_Property;

use Pressidium\WP\Performance\Exceptions\API\API_Exception;

use RuntimeException;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Collector_Manager class.
 *
 * @since 1.2.0
 */
class Collector_Manager {

    /**
     * @var Collector[] Registered collectors.
     */
    private array $collectors = array();

    /**
     * @var array<string, array<string, mixed>> Collected data payload.
     */
    private array $payload = array();

    /**
     * Collector_Manager constructor.
     *
     * @param Logger                $logger          Logger instance.
     * @param Edge_API_Client       $edge_api_client Edge API client instance.
     * @param Web_Property          $web_property    Web property instance.
     * @param Collector_State_Table $state_table     Persistent storage for partial chunk results.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly Edge_API_Client $edge_api_client,
        private readonly Web_Property $web_property,
        private readonly Collector_State_Table $state_table
    ) {}

    /**
     * Register a collector.
     *
     * @param Collector $collector Collector instance to register.
     *
     * @return void
     */
    public function register( Collector $collector ): void {
        $this->collectors[] = $collector;
    }

    /**
     * Run all registered collectors and build the payload.
     *
     * Iterates over all registered collectors, calling their `collect()`
     * method and storing the results keyed by each collector's name.
     *
     * @return void
     */
    public function collect_all(): void {
        $this->payload = array();

        foreach ( $this->collectors as $collector ) {
            $name = $collector->get_name();

            try {
                $this->payload[ $name ] = $collector->collect();
            } catch ( RuntimeException $exception ) {
                $this->logger->error(
                    sprintf(
                        'Collector "%s" failed: %s',
                        esc_html( $name ),
                        esc_html( $exception->getMessage() )
                    )
                );
            }
        }
    }

    /**
     * Return the collected data payload.
     *
     * @return array<string, array<string, mixed>>
     */
    public function get_payload(): array {
        return $this->payload;
    }

    /**
     * Collect data from all registered collectors and send the payload to the Edge API.
     *
     * Synchronous one-shot. Nothing in the plugin calls it, both scheduled and
     * REST-triggered runs go through {@see Edge_Collector_Process::start_run()},
     * which spreads the work across background tasks. Kept as an escape hatch we can
     * expose (e.g. as a WP-CLI command) for debugging and for sites where the
     * background queue cannot run. Do not remove as "unused".
     *
     * @api
     *
     * @return bool Whether the payload was sent successfully.
     */
    public function collect_and_send(): bool {
        $this->collect_all();

        return $this->send_payload( $this->get_payload() );
    }

    /**
     * Return the names of every registered collector.
     *
     * @return string[]
     */
    public function get_collector_names(): array {
        return array_map(
            static fn( Collector $collector ): string => $collector->get_name(),
            $this->collectors
        );
    }

    /**
     * Build the queue items for a new collector run.
     *
     * Each registered collector contributes one item per chunk. Non-chunked
     * collectors contribute a single item with `chunk_key = 'all'`. A trailing
     * `finalize` item assembles the payload and sends the POST request to the
     * EDGE API after every chunk has been processed.
     *
     * The web property handle is checked up front so we don't pay for a run
     * we can't send to the EDGE API anyway.
     *
     * @param string $run_id Run identifier.
     *
     * @return array<int, array<string, mixed>> Empty when no run can be started.
     */
    public function build_chunks( string $run_id ): array {
        if ( empty( $this->web_property->get_web_property_handle() ) ) {
            $this->logger->warning( 'Cannot start collector run: No web property handle configured.' );
            return array();
        }

        $items = array();

        foreach ( $this->collectors as $collector ) {
            $name = $collector->get_name();

            if ( ! ( $collector instanceof Chunked_Collector ) ) {
                $items[] = array(
                    'type'      => 'chunk',
                    'run_id'    => $run_id,
                    'collector' => $name,
                    'chunk_key' => 'all',
                    'chunk'     => null,
                );

                continue;
            }

            foreach ( $collector->get_chunks() as $chunk_key => $chunk ) {
                $items[] = array(
                    'type'      => 'chunk',
                    'run_id'    => $run_id,
                    'collector' => $name,
                    'chunk_key' => (string) $chunk_key,
                    'chunk'     => $chunk,
                );
            }
        }

        if ( empty( $items ) ) {
            return array();
        }

        $items[] = array(
            'type'   => 'finalize',
            'run_id' => $run_id,
        );

        return $items;
    }

    /**
     * Look up a registered collector by name.
     *
     * @param string $name Collector name as returned by `get_name()`.
     */
    private function find_collector( string $name ): ?Collector {
        foreach ( $this->collectors as $collector ) {
            if ( $collector->get_name() === $name ) {
                return $collector;
            }
        }

        return null;
    }

    /**
     * Run one chunk and persist its result.
     *
     * Idempotent: Re-running the same `run_id`, `collector`
     * and `chunk_key` combination overwrites the row in place
     * via {@see Collector_State_Table::set_chunk()}.
     *
     * Failures are logged AND recorded so the run can finalize
     * with whatever succeeded.
     *
     * @param string                $run_id    Run identifier.
     * @param string                $collector Collector name.
     * @param string                $chunk_key Stable key identifying this chunk.
     * @param ?array<string, mixed> $chunk     Chunk descriptor (`null` for non-chunked collectors).
     */
    public function run_chunk( string $run_id, string $collector, string $chunk_key, ?array $chunk ): void {
        $collector_instance = $this->find_collector( $collector );

        if ( $collector_instance === null ) {
            $this->logger->error(
                sprintf( 'Collector "%s" is not registered, skipping chunk.', esc_html( $collector ) )
            );

            return;
        }

        try {
            $data = $collector_instance instanceof Chunked_Collector
                ? $collector_instance->collect_chunk( $chunk ?? array() )
                : $collector_instance->collect();

            $this->state_table->set_chunk( $run_id, $collector, $chunk_key, $data );
        } catch ( Throwable $exception ) {
            $message = $exception->getMessage();

            $this->logger->error(
                sprintf(
                    'Collector "%s" chunk "%s" failed: %s',
                    esc_html( $collector ),
                    esc_html( $chunk_key ),
                    esc_html( $message )
                )
            );

            $this->state_table->set_chunk(
                $run_id,
                $collector,
                $chunk_key,
                array(),
                Collector_State_Table::STATUS_ERROR,
                $message
            );
        }
    }

    /**
     * Reassemble every successful chunk for the run and send the payload to the EDGE API.
     *
     * Clears the run's state rows on success.
     *
     * @param string $run_id Run identifier.
     *
     * @return bool Whether the payload was sent successfully.
     */
    public function finalize_run( string $run_id ): bool {
        $rows = $this->state_table->get_run_rows( $run_id );

        if ( empty( $rows ) ) {
            $this->logger->info(
                sprintf( 'No collector state for run %s. There is nothing to send.', esc_html( $run_id ) )
            );

            return false;
        }

        $grouped = array();

        foreach ( $rows as $row ) {
            if ( $row['status'] !== Collector_State_Table::STATUS_OK ) {
                // Skip any errors
                continue;
            }

            $grouped[ $row['collector'] ][] = $row['data'];
        }

        $payload = array();

        foreach ( $this->collectors as $collector ) {
            $name = $collector->get_name();

            if ( ! isset( $grouped[ $name ] ) ) {
                // Collector produced nothing or failed, skip it
                continue;
            }

            if ( $collector instanceof Chunked_Collector ) {
                // Concatenate every chunk's list to reproduce what `collect()` returns
                $merged = array();

                foreach ( $grouped[ $name ] as $chunk_data ) {
                    foreach ( (array) $chunk_data as $entry ) {
                        $merged[] = $entry;
                    }
                }

                $payload[ $name ] = $merged;

                continue;
            }

            // Non-chunked collectors persist exactly one row
            $payload[ $name ] = $grouped[ $name ][0];
        }

        $did_send = $this->send_payload( $payload );

        if ( $did_send ) {
            $this->state_table->delete_run( $run_id );
        }

        return $did_send;
    }

    /**
     * POST the assembled payload to the EDGE API.
     *
     * @param array<string, mixed> $payload Assembled payload.
     *
     * @return bool Whether the payload was sent successfully.
     */
    private function send_payload( array $payload ): bool {
        $web_property_handle = $this->web_property->get_web_property_handle();

        if ( empty( $web_property_handle ) ) {
            $this->logger->warning( 'Cannot send collector payload: No web property handle configured.' );
            return false;
        }

        if ( empty( $payload ) ) {
            $this->logger->info( 'No data collected, skipping the API request.' );
            return false;
        }

        try {
            $request = new Collector_Request();
            $request->set_web_property_handle( $web_property_handle )
                    ->set_data( $payload );

            $this->edge_api_client->collector()->send( $request );
        } catch ( API_Exception | RuntimeException $exception ) {
            $this->logger->error(
                sprintf( 'Failed to send collector payload: %s', esc_html( $exception->getMessage() ) )
            );

            return false;
        }

        $this->logger->info( 'Successfully sent collector payload to the Edge API.' );

        return true;
    }

}
