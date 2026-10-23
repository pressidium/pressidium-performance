<?php
/**
 * Web property.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge;

use Pressidium\WP\Performance\Options\Options;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Web_Property class.
 *
 * @since 1.1.0
 */
class Web_Property {

    const OPTION_KEY = 'pressidium_edge_web_property_handle';

    /**
     * Web_Property constructor.
     *
     * @param Options $options Options instance.
     */
    public function __construct( private readonly Options $options ) {}

    /**
     * Return the web property handle.
     *
     * @return ?string
     */
    public function get_web_property_handle(): ?string {
        return $this->options->get( self::OPTION_KEY );
    }

    /**
     * Store the given web property handle.
     *
     * @param string $handle Web property handle to store.
     *
     * @return void
     */
    public function set_web_property_handle( string $handle ): void {
        $this->options->set( self::OPTION_KEY, $handle );
    }

}
