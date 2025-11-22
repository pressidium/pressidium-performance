<?php
/**
 * Optimization record.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Optimization_Record class.
 *
 * @since 1.0.0
 */
final class Optimization_Record {

    /**
     * @var int Unique ID of the optimization.
     */
    private int $id;

    /**
     * @var string Original URI of the file.
     */
    private string $original_uri;

    /**
     * @var string Optimized URI of the file.
     */
    private string $optimized_uri;

    /**
     * @var string Hash of the file.
     */
    private string $hash;

    /**
     * @var int Original size of the file.
     */
    private int $original_size;

    /**
     * @var int Optimized size of the file.
     */
    private int $optimized_size;

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
     * Return the unique ID of the optimization.
     *
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Set the unique ID of the optimization.
     *
     * @param int $id
     *
     * @return Optimization_Record
     */
    public function set_id( int $id ): Optimization_Record {
        $this->id = $id;

        return $this; // chainable
    }

    /**
     * Return the original URI of the file.
     *
     * @return string
     */
    public function get_original_uri(): string {
        return $this->original_uri;
    }

    /**
     * Set the original URI of the file.
     *
     * @param string $original_uri
     *
     * @return $this
     */
    public function set_original_uri( string $original_uri ): Optimization_Record {
        $this->original_uri = $original_uri;

        return $this; // chainable
    }

    /**
     * Return the optimized URI of the file.
     *
     * @return string
     */
    public function get_optimized_uri(): string {
        return $this->optimized_uri;
    }

    /**
     * Set the optimized URI of the file.
     *
     * @param string $optimized_uri
     *
     * @return $this
     */
    public function set_optimized_uri( string $optimized_uri ): Optimization_Record {
        $this->optimized_uri = $optimized_uri;

        return $this; // chainable
    }

    /**
     * Return the hash of the file.
     *
     * @return string
     */
    public function get_hash(): string {
        return $this->hash;
    }

    /**
     * Set the hash of the file.
     *
     * @param string $hash
     *
     * @return $this
     */
    public function set_hash( string $hash ): Optimization_Record {
        $this->hash = $hash;

        return $this; // chainable
    }

    /**
     * Return the original size of the file.
     *
     * @return int
     */
    public function get_original_size(): int {
        return $this->original_size;
    }

    /**
     * Set the original size of the file.
     *
     * @param int $original_size
     *
     * @return $this
     */
    public function set_original_size( int $original_size ): Optimization_Record {
        $this->original_size = $original_size;

        return $this; // chainable
    }

    /**
     * Return the optimized size of the file.
     *
     * @return int
     */
    public function get_optimized_size(): int {
        return $this->optimized_size;
    }

    /**
     * Set the optimized size of the file.
     *
     * @param int $optimized_size
     *
     * @return $this
     */
    public function set_optimized_size( int $optimized_size ): Optimization_Record {
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
     * @return $this
     */
    public function set_created_at( string $created_at ): Optimization_Record {
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
     * @return $this
     */
    public function set_updated_at( string $updated_at ): Optimization_Record {
        $this->updated_at = $updated_at;

        return $this; // chainable
    }

}
