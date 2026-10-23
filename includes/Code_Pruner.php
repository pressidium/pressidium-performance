<?php
/**
 * Code pruner.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Code_Pruner class.
 *
 * @since 1.0.0
 */
class Code_Pruner {

    /**
     * @var string Code to prune.
     */
    private string $code;

    /**
     * Code_Pruner constructor.
     *
     * @param ?string $code Code to prune.
     */
    public function __construct( ?string $code ) {
        $this->code = $code ?? '';
    }

    /**
     * Return the pruned code.
     *
     * @return string
     */
    public function get_pruned_code(): string {
        return $this->code;
    }

    /**
     * Remove any block comments from the code.
     *
     * @return Code_Pruner
     */
    public function prune_block_comments(): Code_Pruner {
        // Every block comment, wherever it sits
        $pruned_code = preg_replace( '/\/\*.*?\*\//s', '', $this->code );

        if ( ! is_string( $pruned_code ) ) {
            return $this;
        }

        $this->code = $pruned_code;

        return $this; // chainable
    }

    /**
     * Remove any inline comments from the code.
     *
     * @return Code_Pruner
     */
    public function prune_inline_comments(): Code_Pruner {
        // Whole-line comments only (`m`: per line), so a `//` inside a string (e.g. a URL) is left alone
        $pruned_code = preg_replace( '/^\s*\/\/.*$/m', '', $this->code );

        if ( ! is_string( $pruned_code ) ) {
            return $this;
        }

        $this->code = $pruned_code;

        return $this; // chainable
    }

    /**
     * Remove any empty lines from the code.
     *
     * @return Code_Pruner
     */
    public function prune_empty_lines(): Code_Pruner {
        // Drop whitespace-only lines together with their line break
        $pruned_code = preg_replace( '/^[ \t]*\R/m', '', $this->code );

        if ( ! is_string( $pruned_code ) ) {
            return $this;
        }

        $this->code = $pruned_code;

        return $this; // chainable
    }

}
