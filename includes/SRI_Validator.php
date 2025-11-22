<?php
/**
 * Subresource Integrity (SRI) validator.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Files\File;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * SRI_Validator class.
 *
 * @since 1.0.0
 */
class SRI_Validator {

    /**
     * SRI_Validator constructor.
     *
     * @param Logger $logger An instance of `Logger`.
     */
    public function __construct( private readonly Logger $logger ) {}

    /**
     * Whether the file at the given URL matches the given SRI hash.
     *
     * Subresource integrity (SRI) hashes are base64-encoded and formatted like this:
     *
     * ```
     * <algorithm>-<expected_base64_hash>
     * ```
     *
     * where algorithm is a Secure Hash Algorithm (SHA).
     *
     * @param File   $file File to check.
     * @param string $sri  Base64-encoded SRI hash to check against.
     *
     * @return bool
     */
    public function is_valid( File $file, string $sri ): bool {
        if ( ! $sri ) {
            // No hash provided, so we assume it's valid
            return true;
        }

        [ $sha, $expected_hash ] = explode( '-', $sri );

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        $actual_hash = base64_encode( hash( $sha, $file->get_contents(), true ) );

        if ( $actual_hash !== $expected_hash ) {
            // Hashes mismatch, contents may have been tampered with
            $this->logger->info(
                sprintf(
                    'SRI validation failed for file: \'%s\'. Expected: \'%s\', got: \'%s\'',
                    esc_html( $file->get_url() ),
                    esc_html( $expected_hash ),
                    esc_html( $actual_hash )
                )
            );

            return false;
        }

        return true;
    }

}
