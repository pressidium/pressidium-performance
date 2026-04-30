<?php
/**
 * Media Library.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

use Pressidium\WP\Performance\Exceptions\Image_Conversion_Exception;

use Generator;
use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Media_Library class.
 *
 * @since 1.0.0
 */
class Media_Library {

    /**
     * Default batch size for fetching attachments.
     *
     * @var int
     */
    private const BATCH_SIZE = 500;

    /**
     * Media_Library constructor.
     *
     * @param Image_Attachment_Factory $image_attachment_factory Image attachment factory.
     */
    public function __construct( private readonly Image_Attachment_Factory $image_attachment_factory ) {}

    /**
     * Return all attachment IDs for image attachments.
     *
     * This is a lightweight query that only selects IDs,
     * suitable for feeding into a background process for
     * further processing.
     *
     * @return int[] Array of attachment IDs.
     */
    public function get_all_image_attachment_ids(): array {
        global $wpdb;

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "
                    SELECT ID
                    FROM {$wpdb->posts}
                    WHERE post_type = %s
                      AND post_mime_type LIKE %s
                    ORDER BY ID ASC
                ",
                'attachment',
                'image/%'
            )
        );

        return array_map( 'intval', $results );
    }

    /**
     * Return a batch of attachment posts that are images.
     *
     * Uses keyset pagination (seeking by the last seen post ID).
     * This keeps every batch equally fast regardless of how deep
     * into the result set we are.
     *
     * @param int $limit   Number of records to fetch.
     * @param int $last_id The last post ID from the previous batch (0 for the first batch).
     *
     * @return object[]
     */
    private function get_image_attachments_batch( int $limit, int $last_id = 0 ): array {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "
                    SELECT p.ID, p.guid, pm_meta.meta_value AS metadata
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm_meta ON p.ID = pm_meta.post_id AND pm_meta.meta_key = %s
                    WHERE p.post_type = %s
                      AND p.post_mime_type LIKE %s
                      AND p.ID > %d
                    ORDER BY p.ID ASC
                    LIMIT %d
                ",
                '_wp_attachment_metadata',
                'attachment',
                'image/%',
                $last_id,
                $limit
            )
        );

        if ( empty( $results ) ) {
            return array();
        }

        return $results;
    }

    /**
     * Yield all images from the Media Library in batches.
     *
     * Uses a generator to avoid loading all image attachments
     * into memory at once, preventing memory exhaustion on
     * sites with large media libraries.
     *
     * @return Generator<int, Image_Attachment>
     */
    public function get_all(): Generator {
        $last_id = 0;

        do {
            $attachments = $this->get_image_attachments_batch( self::BATCH_SIZE, $last_id );

            foreach ( $attachments as $attachment ) {
                $attachment_id = $attachment->ID;
                $metadata      = maybe_unserialize( $attachment->metadata );

                $last_id = (int) $attachment_id;

                try {
                    yield $this->image_attachment_factory->create( $attachment_id, $metadata );
                } catch ( InvalidArgumentException | Image_Conversion_Exception $exception ) {
                    // Skip invalid attachments
                    continue;
                }
            }

            $attachment_count = count( $attachments );
        } while ( $attachment_count === self::BATCH_SIZE );
    }

    /**
     * Return the total size saved across all optimized images in the Media Library.
     *
     * Processes attachments in batches to avoid memory exhaustion
     * on sites with large media libraries.
     *
     * @return int Size saved in bytes.
     */
    public function get_total_size_saved(): int {
        $total_saved = 0;
        $last_id     = 0;

        do {
            $attachments = $this->get_image_attachments_batch( self::BATCH_SIZE, $last_id );

            foreach ( $attachments as $attachment ) {
                $last_id  = (int) $attachment->ID;
                $metadata = maybe_unserialize( $attachment->metadata );

                if ( ! is_array( $metadata ) ) {
                    // Invalid metadata format, skip
                    continue;
                }

                // Normalize detection of optimization flag
                $is_optimized = false;
                if ( isset( $metadata['is_optimized'] ) ) {
                    $flag         = $metadata['is_optimized'];
                    $is_optimized = $flag === true || $flag === 1 || $flag === '1' || $flag === 'true';
                }

                if ( ! $is_optimized ) {
                    // Attachment not optimized, skip
                    continue;
                }

                $original_size = null;

                if ( isset( $metadata['original'] )
                    && is_array( $metadata['original'] )
                    && isset( $metadata['original']['filesize'] ) ) {
                    $original_size = (int) $metadata['original']['filesize'];
                }

                $optimized_size = null;

                if ( isset( $metadata['filesize'] ) ) {
                    $optimized_size = (int) $metadata['filesize'];
                }

                if ( $original_size === null || $optimized_size === null ) {
                    // Missing size information, skip
                    continue;
                }

                $diff = $original_size - $optimized_size;

                if ( $diff > 0 ) {
                    // Valid size saving, accumulate
                    $total_saved += $diff;
                }
            }

            $attachment_count = count( $attachments );
        } while ( $attachment_count === self::BATCH_SIZE );

        return $total_saved;
    }

}
