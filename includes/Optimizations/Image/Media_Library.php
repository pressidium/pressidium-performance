<?php
/**
 * Media Library.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

use Pressidium\WP\Performance\Exceptions\Image_Conversion_Exception;

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
     * Media_Library constructor.
     *
     * @param Image_Attachment_Factory $image_attachment_factory Image attachment factory.
     */
    public function __construct( private readonly Image_Attachment_Factory $image_attachment_factory ) {}

    /**
     * Return all attachment posts that are images.
     *
     * @return object[]
     */
    private function get_all_image_attachments(): array {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "
                    SELECT p.ID, p.guid, pm_meta.meta_value AS metadata
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm_meta ON p.ID = pm_meta.post_id AND pm_meta.meta_key = %s
                    WHERE p.post_type = %s
                      AND p.post_mime_type LIKE %s
                ",
                '_wp_attachment_metadata',
                'attachment',
                'image/%'
            )
        );

        if ( empty( $results ) ) {
            return array();
        }

        return $results;
    }

    /**
     * Return all images from the Media Library.
     *
     * @return Image_Attachment[]
     */
    public function get_all(): array {
        $attachments = $this->get_all_image_attachments();

        $image_attachments = array();

        foreach ( $attachments as $attachment ) {
            $attachment_id = $attachment->ID;
            $metadata      = maybe_unserialize( $attachment->metadata );

            try {
                $image_attachments[] = $this->image_attachment_factory->create( $attachment_id, $metadata );
            } catch ( InvalidArgumentException | Image_Conversion_Exception $exception ) {
                // Skip invalid attachments
                continue;
            }
        }

        return $image_attachments;
    }

    /**
     * Return the total size saved across all optimized images in the Media Library.
     *
     * @return int Size saved in bytes.
     */
    public function get_total_size_saved(): int {
        $attachments = $this->get_all_image_attachments();

        if ( empty( $attachments ) ) {
            return 0;
        }

        $total_saved = 0;

        foreach ( $attachments as $attachment ) {
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

        return (int) $total_saved;
    }

}
