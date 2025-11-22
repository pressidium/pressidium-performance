<?php
/**
 * Payload for the concatenate background process.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenate_Payload class.
 *
 * @since 1.0.0
 */
class Concatenate_Payload {

    /**
     * Concatenate_Payload constructor.
     *
     * @param string    $file_uri        URI of one of the files to concatenate.
     * @param ?string   $sri_hash        SRI of one of the files to concatenate, if applicable.
     * @param string    $aggregated_hash Aggregated hash of the files to concatenate.
     * @param string    $type            Type of the files to concatenate.
     * @param int|false $post_id         Post ID to which the files to concatenate belong.
     */
    public function __construct(
        private readonly string $file_uri,
        private readonly ?string $sri_hash,
        private readonly string $aggregated_hash,
        private readonly string $type,
        private readonly int|false $post_id
    ) {}

    /**
     * Return the URI of one of the files to concatenate.
     *
     * @return string
     */
    public function get_file_uri(): string {
        return $this->file_uri;
    }

    /**
     * Return the aggregated hash of the files to concatenate.
     *
     * @return string
     */
    public function get_aggregated_hash(): string {
        return $this->aggregated_hash;
    }

    /**
     * Return the type of the files to concatenate.
     *
     * @return string
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Return the ID of the post to which the files to concatenate belong.
     *
     * @return int|false
     */
    public function get_post_id(): int|false {
        return $this->post_id;
    }

    /**
     * Return the SRI hash of one of the files to concatenate, if applicable.
     *
     * @return ?string
     */
    public function get_sri_hash(): ?string {
        return $this->sri_hash;
    }

}
