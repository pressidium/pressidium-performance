<?php
/**
 * Sanitizer.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\API;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Sanitizer class.
 *
 * @since 1.0.0
 */
final class Sanitizer {

    /**
     * Sanitize a text field value.
     *
     * @param mixed $text_field
     *
     * @return string
     */
    public static function sanitize_text_field( $text_field ): string {
        return sanitize_text_field( $text_field );
    }

    /**
     * Sanitize an email value.
     *
     * @param mixed $email
     *
     * @return string
     */
    public static function sanitize_email( $email ): string {
        return sanitize_email( $email );
    }

    /**
     * Sanitize a URL value.
     *
     * @param mixed $url
     *
     * @return string
     */
    public static function sanitize_url( $url ): string {
        return esc_url_raw( $url );
    }

    /**
     * Sanitize a checkbox value.
     *
     * @param mixed $checkbox
     *
     * @return bool
     */
    public static function sanitize_checkbox( $checkbox ): bool {
        return isset( $checkbox ) && $checkbox === 'on';
    }

}
