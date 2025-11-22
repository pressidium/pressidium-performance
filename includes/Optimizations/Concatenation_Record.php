<?php
/**
 * Concatenation record.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenation_Record class.
 *
 * @since 1.0.0
 */
final class Concatenation_Record {

    /**
     * @var int Unique ID of the concatenation.
     */
    private int $id;

    /**
     * @var string Aggregated hash of the concatenated files.
     */
    private string $aggregated_hash;

    /**
     * @var string Type of the concatenated files.
     */
    private string $type;

    /**
     * @var string Concatenated URI of the file.
     */
    private string $concatenated_uri;

    /**
     * @var bool Whether the concatenated file is minified.
     */
    private bool $is_minified;

    /**
     * @var int Number of files that were concatenated.
     */
    private ?int $files_count = null;

    /**
     * @var int Original size of the concatenated files.
     */
    private ?int $original_size = null;

    /**
     * @var int Optimized size of the concatenated file.
     */
    private ?int $optimized_size = null;

    /**
     * @var string Created_at timestamp.
     */
    private string $created_at;

    /**
     * @var string Updated_at timestamp.
     */
    private string $updated_at;

    /**
     *
     * Return the unique ID of the concatenation.
     *
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Set the unique ID of the concatenation.
     *
     * @param int $id
     *
     * @return Concatenation_Record
     */
    public function set_id( int $id ): Concatenation_Record {
        $this->id = $id;

        return $this; // chainable
    }

    /**
     * Return the aggregated hash of the concatenated files.
     *
     * @return string
     */
    public function get_aggregated_hash(): string {
        return $this->aggregated_hash;
    }

    /**
     * Set the aggregated hash of the concatenated files.
     *
     * @param string $aggregated_hash
     *
     * @return Concatenation_Record
     */
    public function set_aggregated_hash( string $aggregated_hash ): Concatenation_Record {
        $this->aggregated_hash = $aggregated_hash;

        return $this; // chainable
    }

    /**
     * Return the type of the concatenated files.
     *
     * @return string
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Set the type of the concatenated files.
     *
     * @param string $type
     *
     * @return Concatenation_Record
     */
    public function set_type( string $type ): Concatenation_Record {
        $this->type = $type;

        return $this; // chainable
    }

    /**
     * Return the concatenated URI of the file.
     *
     * @return string
     */
    public function get_concatenated_uri(): string {
        return $this->concatenated_uri;
    }

    /**
     * Set the concatenated URI of the file.
     *
     * @param string $concatenated_uri
     *
     * @return Concatenation_Record
     */
    public function set_concatenated_uri( string $concatenated_uri ): Concatenation_Record {
        $this->concatenated_uri = $concatenated_uri;

        return $this; // chainable
    }

    /**
     * Return whether the concatenated file is minified.
     *
     * @return bool
     */
    public function get_is_minified(): bool {
        return $this->is_minified;
    }

    /**
     * Set whether the concatenated file is minified.
     *
     * @param bool $is_minified
     *
     * @return Concatenation_Record
     */
    public function set_is_minified( bool $is_minified ): Concatenation_Record {
        $this->is_minified = $is_minified;

        return $this; // chainable
    }

    /**
     * Return the number of files that were concatenated.
     *
     * @return ?int
     */
    public function get_files_count(): ?int {
        return $this->files_count;
    }

    /**
     * Set the number of files that were concatenated.
     *
     * @param ?int $files_count
     *
     * @return Concatenation_Record
     */
    public function set_files_count( ?int $files_count ): Concatenation_Record {
        $this->files_count = $files_count;

        return $this; // chainable
    }

    /**
     * Return the original size of the concatenated files.
     *
     * @return ?int
     */
    public function get_original_size(): ?int {
        return $this->original_size;
    }

    /**
     * Set the original size of the concatenated files.
     *
     * @param ?int $original_size
     *
     * @return Concatenation_Record
     */
    public function set_original_size( ?int $original_size ): Concatenation_Record {
        $this->original_size = $original_size;

        return $this; // chainable
    }

    /**
     * Return the optimized size of the concatenated file.
     *
     * @return ?int
     */
    public function get_optimized_size(): ?int {
        return $this->optimized_size;
    }

    /**
     * Set the optimized size of the concatenated file.
     *
     * @param ?int $optimized_size
     *
     * @return Concatenation_Record
     */
    public function set_optimized_size( ?int $optimized_size ): Concatenation_Record {
        $this->optimized_size = $optimized_size;

        return $this; // chainable
    }

    /**
     * Return the created_at timestamp.
     *
     * @return string
     */
    public function get_created_at(): string {
        return $this->created_at;
    }

    /**
     * Set the created_at timestamp.
     *
     * @param string $created_at
     *
     * @return Concatenation_Record
     */
    public function set_created_at( string $created_at ): Concatenation_Record {
        $this->created_at = $created_at;

        return $this; // chainable
    }

    /**
     * Return the updated_at timestamp.
     *
     * @return string
     */
    public function get_updated_at(): string {
        return $this->updated_at;
    }

    /**
     * Set the updated_at timestamp.
     *
     * @param string $updated_at
     *
     * @return Concatenation_Record
     */
    public function set_updated_at( string $updated_at ): Concatenation_Record {
        $this->updated_at = $updated_at;

        return $this; // chainable
    }

}
