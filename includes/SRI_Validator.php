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
 * @link https://developer.mozilla.org/en-US/docs/Web/Security/Defenses/Subresource_Integrity
 *
 * @since 1.0.0
 */
class SRI_Validator {

    /**
     * @var string[] Hashing algorithms we support. Weakest to strongest.
     */
    const SUPPORTED_ALGORITHMS = array( 'sha256', 'sha384', 'sha512' );

    /**
     * SRI_Validator constructor.
     *
     * @param Logger $logger An instance of `Logger`.
     */
    public function __construct( private readonly Logger $logger ) {}

    /**
     * Return the strongest of the given algorithms.
     *
     * When multiple values using different hash functions are present in the value
     * of the `integrity` attribute, browsers will use only the strongest function
     * provided. So, this method uses the same approach to determine which algorith
     * to use when validating the file.
     *
     * @link https://developer.mozilla.org/en-US/docs/Web/Security/Defenses/Subresource_Integrity#how_browsers_handle_subresource_integrity
     *
     * @param string[] $algorithms Algorithms to pick from.
     *
     * @return string The strongest algorithm from the given list (e.g. `sha512`).
     */
    private function get_strongest_algorithm( array $algorithms ): string {
        $strongest = $algorithms[0];

        foreach ( $algorithms as $algorithm ) {
            $is_stronger = array_search( $algorithm, self::SUPPORTED_ALGORITHMS, true )
                > array_search( $strongest, self::SUPPORTED_ALGORITHMS, true );

            if ( $is_stronger ) {
                $strongest = $algorithm;
            }
        }

        return $strongest;
    }

    /**
     * Return the hash from the given integrity metadata that the given file matches.
     *
     * Integrity metadata may list more than one hash and more than one hashing algorithm.
     * In that case, the strongest algorithm is used to validate the file.
     * If multiple hashes are provided for the same algorithm, the file is considered valid
     * if it matches any of them. This enables a developer to provide alternate versions
     * of a resource, while still checking their integrity.
     *
     * @link https://developer.mozilla.org/en-US/docs/Web/Security/Defenses/Subresource_Integrity#how_browsers_handle_subresource_integrity
     *
     * @param File                    $file       File to check.
     * @param array<string, string[]> $sri_hashes Integrity metadata, keyed by algorithm.
     *                                            Each value is an array of expected base64-encoded
     *                                            hashes for that algorithm.
     *
     * @return ?string The hash the file matches, formatted as `<algorithm>-<base64_hash>`,
     *                 or `null` if it matches none of them.
     */
    public function get_matching_hash( File $file, array $sri_hashes ): ?string {
        if ( empty( $sri_hashes ) ) {
            // No integrity metadata to match the file against
            return null;
        }

        $algorithms          = array_keys( $sri_hashes );
        $strongest_algorithm = $this->get_strongest_algorithm( $algorithms );
        $expected_hashes     = $sri_hashes[ $strongest_algorithm ];

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        $actual_hash = base64_encode( hash( $strongest_algorithm, $file->get_contents(), true ) );

        foreach ( $expected_hashes as $expected_hash ) {
            if ( hash_equals( $expected_hash, $actual_hash ) ) {
                return "{$strongest_algorithm}-{$expected_hash}";
            }
        }

        // Hashes mismatch, contents may have been tampered with
        $this->logger->info(
            sprintf(
                'SRI validation failed for file: \'%s\'. Expected one of: \'%s\', got: \'%s\'',
                esc_html( $file->get_url() ),
                esc_html( implode( '\', \'', $expected_hashes ) ),
                esc_html( $actual_hash )
            )
        );

        return null;
    }

    /**
     * Whether the given file matches the given integrity metadata.
     *
     * @param File                    $file       File to check.
     * @param array<string, string[]> $sri_hashes Integrity metadata, keyed by algorithm.
     *
     * @return bool `true` if the file matches the integrity metadata, `false` otherwise.
     */
    public function is_valid( File $file, array $sri_hashes ): bool {
        return $this->get_matching_hash( $file, $sri_hashes ) !== null;
    }

    /**
     * Whether the given integrity metadata still lists the given hash.
     *
     * This is how we tell that the `integrity` attribute of a tag has changed since we
     * optimized the file it points to. The hash we validated the original contents against
     * no longer being listed means the author expects different bytes than the ones we
     * optimized, so whatever we generated from them is stale.
     *
     * @param array<string, string[]> $sri_hashes Integrity metadata, keyed by algorithm.
     * @param string                  $sri_hash   Hash to look for, formatted as `<algorithm>-<base64_hash>`.
     *
     * @return bool `true` if the metadata lists the hash, `false` otherwise.
     */
    public function contains_hash( array $sri_hashes, string $sri_hash ): bool {
        foreach ( $sri_hashes as $algorithm => $expected_hashes ) {
            foreach ( $expected_hashes as $expected_hash ) {
                if ( hash_equals( "{$algorithm}-{$expected_hash}", $sri_hash ) ) {
                    return true;
                }
            }
        }

        return false;
    }

}
