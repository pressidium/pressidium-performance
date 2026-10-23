<?php
/**
 * Chunked collector interface.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Chunked_Collector interface.
 *
 * Implemented by collectors whose work can be split across multiple background
 * tasks so each task stays well under PHP `max_execution_time` and `memory_limit`.
 *
 * - {@see get_chunks()} returns the list of chunks for one run, keyed by a
 *   stable identifier. Keys are used by the state table to make per-chunk
 *   writes idempotent on retry.
 *
 * - {@see collect_chunk()} processes one chunk and returns a numeric-keyed
 *   list. The collector's full output equals the concatenation of every
 *   chunk's list, in `get_chunks()` order.
 *
 * `collect()` (inherited from {@see Collector}) MUST return the same shape as
 * the concatenated chunks, so callers that have not yet been migrated to the
 * chunked path still get a correct result.
 *
 * @since 1.3.0
 */
interface Chunked_Collector extends Collector {

    /**
     * Return the chunks this collector will produce for one run.
     *
     * Keys are stable per-chunk identifiers used by the storage layer for idempotency.
     *
     * Must be cheap to call. Heavy work belongs in {@see collect_chunk()}.
     *
     * @return array<string, array<string, mixed>>
     */
    public function get_chunks(): array;

    /**
     * Collect data for one chunk.
     *
     * Must return a numeric-keyed list. Concatenating every chunk's list in
     * `get_chunks()` order reproduces the same shape `collect()` returns.
     *
     * @param array<string, mixed> $chunk Chunk descriptor.
     *
     * @return array<int, mixed>
     */
    public function collect_chunk( array $chunk ): array;

}
