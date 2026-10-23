<?php
/**
 * Concatenation file record.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations;

use Pressidium\WP\Performance\Enumerations\Exclusion_Reason;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Concatenation_File_Record class.
 *
 * Describes a file a concatenated bundle was built from, i.e. whether
 * it was included in the bundle, and if so, in which segment of it.
 *
 * @since 1.2.0
 */
final class Concatenation_File_Record {

    /**
     * @var int ID of the concatenation the file belongs to.
     */
    private int $concatenation_id;

    /**
     * @var string MD5 hash of the URI of the file.
     */
    private string $file_uri_hash;

    /**
     * @var int Position of the file on the page that built the bundle.
     */
    private int $position;

    /**
     * @var ?int Index of the segment holding the file, or `null` if it holds no contents of it.
     */
    private ?int $segment = null;

    /**
     * @var bool Whether the file could not be included in the bundle.
     */
    private bool $is_excluded = false;

    /**
     * @var ?Exclusion_Reason Why the file could not be included in the bundle, if it was excluded.
     */
    private ?Exclusion_Reason $exclusion_reason = null;

    /**
     * Return the ID of the concatenation the file belongs to.
     *
     * @return int
     */
    public function get_concatenation_id(): int {
        return $this->concatenation_id;
    }

    /**
     * Set the ID of the concatenation the file belongs to.
     *
     * @param int $concatenation_id
     *
     * @return Concatenation_File_Record
     */
    public function set_concatenation_id( int $concatenation_id ): Concatenation_File_Record {
        $this->concatenation_id = $concatenation_id;

        return $this; // chainable
    }

    /**
     * Return the MD5 hash of the URI of the file.
     *
     * @return string
     */
    public function get_file_uri_hash(): string {
        return $this->file_uri_hash;
    }

    /**
     * Set the MD5 hash of the URI of the file.
     *
     * @param string $file_uri_hash
     *
     * @return Concatenation_File_Record
     */
    public function set_file_uri_hash( string $file_uri_hash ): Concatenation_File_Record {
        $this->file_uri_hash = $file_uri_hash;

        return $this; // chainable
    }

    /**
     * Return the position of the file on the page that built the bundle.
     *
     * @return int
     */
    public function get_position(): int {
        return $this->position;
    }

    /**
     * Set the position of the file on the page that built the bundle.
     *
     * @param int $position
     *
     * @return Concatenation_File_Record
     */
    public function set_position( int $position ): Concatenation_File_Record {
        $this->position = $position;

        return $this; // chainable
    }

    /**
     * Return the index of the segment holding the file.
     *
     * @return ?int Segment index, or `null` if the file was excluded or is empty.
     */
    public function get_segment(): ?int {
        return $this->segment;
    }

    /**
     * Set the index of the segment holding the file.
     *
     * @param ?int $segment
     *
     * @return Concatenation_File_Record
     */
    public function set_segment( ?int $segment ): Concatenation_File_Record {
        $this->segment = $segment;

        return $this; // chainable
    }

    /**
     * Whether the file could not be included in the bundle.
     *
     * @return bool
     */
    public function is_excluded(): bool {
        return $this->is_excluded;
    }

    /**
     * Set whether the file could not be included in the bundle.
     *
     * @param bool $is_excluded
     *
     * @return Concatenation_File_Record
     */
    public function set_is_excluded( bool $is_excluded ): Concatenation_File_Record {
        $this->is_excluded = $is_excluded;

        return $this; // chainable
    }

    /**
     * Return why the file could not be included in the bundle.
     *
     * @return ?Exclusion_Reason Exclusion reason, or `null` if the file was included (or the reason is unknown).
     */
    public function get_exclusion_reason(): ?Exclusion_Reason {
        return $this->exclusion_reason;
    }

    /**
     * Set why the file could not be included in the bundle.
     *
     * @param ?Exclusion_Reason $exclusion_reason
     *
     * @return Concatenation_File_Record
     */
    public function set_exclusion_reason( ?Exclusion_Reason $exclusion_reason ): Concatenation_File_Record {
        $this->exclusion_reason = $exclusion_reason;

        return $this; // chainable
    }

}
