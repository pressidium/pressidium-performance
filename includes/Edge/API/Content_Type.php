<?php
/**
 * Content-Type enumeration.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\API;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Content_Type enumeration.
 *
 * @since 1.1.0
 */
enum Content_Type: string {

    case JSON            = 'application/json';
    case FORM_URLENCODED = 'application/x-www-form-urlencoded';

    /**
     * Encode data based on the content type.
     *
     * @param array $data Data to encode.
     *
     * @return string Encoded data.
     */
    public function encode( array $data = array() ): string {
        return match ( $this ) {
            Content_Type::JSON            => wp_json_encode( $data ),
            Content_Type::FORM_URLENCODED => http_build_query( $data ),
        };
    }

}
