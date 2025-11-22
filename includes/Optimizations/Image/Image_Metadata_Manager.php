<?php
/**
 * Image metadata manager.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Image;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Image_Metadata_Manager class.
 *
 * @since 1.0.0
 */
class Image_Metadata_Manager {

    /**
     * Ensure the given path is relative to the `wp-contents/uploads/` directory.
     *
     * @param string $path Path to ensure is relative.
     *
     * @return string Path relative to the `wp-contents/uploads/` directory.
     */
    private function ensure_path_is_relative( string $path ): string {
        return str_replace( wp_get_upload_dir()['basedir'], '', $path );
    }

    /**
     * Keep a copy of the original metadata under the `original` key, if not already kept.
     *
     * @param array $metadata Metadata to keep the original of.
     *
     * @return array Metadata with the original kept.
     */
    private function maybe_keep_original_meta( array $metadata ): array {
        if ( array_key_exists( 'original', $metadata ) ) {
            // Already kept the original metadata, return as is
            return $metadata;
        }

        $metadata['original'] = $metadata;

        return $metadata;
    }

    /**
     * Update the metadata for the given attachment ID, size variant name, and optimized image.
     *
     * @param int    $attachment_id     Attachment ID.
     * @param string $size_variant_name Size variant name.
     * @param Image  $optimized_image   Optimized image.
     *
     * @return void
     */
    public function update( int $attachment_id, string $size_variant_name, Image $optimized_image ): void {
        $metadata = wp_get_attachment_metadata( $attachment_id );
        $metadata = $this->maybe_keep_original_meta( $metadata );

        $relative_path = $this->ensure_path_is_relative( $optimized_image->get_path() );

        if ( $size_variant_name === 'full' ) {
            $metadata['file']         = $relative_path;
            $metadata['filesize']     = filesize( $optimized_image->get_path() );
            $metadata['mime-type']    = $optimized_image->get_mime_type();
            $metadata['is_optimized'] = true;

            update_attached_file( $attachment_id, $optimized_image->get_path() );
            wp_update_attachment_metadata( $attachment_id, $metadata );

            return;
        }

        $metadata['sizes'][ $size_variant_name ]['file']         = basename( $optimized_image->get_path() );
        $metadata['sizes'][ $size_variant_name ]['filesize']     = filesize( $optimized_image->get_path() );
        $metadata['sizes'][ $size_variant_name ]['mime-type']    = $optimized_image->get_mime_type();
        $metadata['sizes'][ $size_variant_name ]['is_optimized'] = true;

        // TODO: (enhancement) Wrap our custom metadata in a `pressidium_` prefixed key to avoid any plugin conflicts

        wp_update_attachment_metadata( $attachment_id, $metadata );
    }

}
