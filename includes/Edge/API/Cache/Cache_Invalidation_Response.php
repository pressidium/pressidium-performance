<?php
/**
 * Cache invalidation response.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\API\Cache;

use Pressidium\WP\Performance\Exceptions\API\API_Exception;
use Pressidium\WP\Performance\Exceptions\API\Auth_Exception;
use Pressidium\WP\Performance\Exceptions\API\Rate_Limit_Exception;
use Pressidium\WP\Performance\Exceptions\API\Resource_Not_Found_Exception;
use Pressidium\WP\Performance\Exceptions\API\Web_Property_Not_Found_Exception;
use Pressidium\WP\Performance\Exceptions\API\Validation_Exception;

use Pressidium\WP\Performance\Edge\API\Response;

use WP_Error;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Cache_Invalidation_Response class.
 *
 * @since 1.1.0
 */
final class Cache_Invalidation_Response extends Response {

    /**
     * Handle any non-2xx response.
     *
     * @throws Auth_Exception                   If authentication fails.
     * @throws Rate_Limit_Exception             If the request was rate limited.
     * @throws Resource_Not_Found_Exception     If the specified resource is not found.
     * @throws Web_Property_Not_Found_Exception If the specified web property is not found.
     * @throws Validation_Exception             If there is a validation error.
     * @throws API_Exception                    If there is a general API error.
     * @throws RuntimeException                 For any other error.
     *
     * @param ?WP_Error $error The error extracted from the response.
     *
     * @return void
     */
    protected function handle_errors( ?WP_Error $error ): void {
        if ( $this->status < 400 || $this->status >= 599 ) {
            throw new RuntimeException( 'An unexpected error occurred while purging all cache.' );
        }

        if ( $this->status === 429 ) {
            throw new Rate_Limit_Exception(
                'Rate limited while invalidating the cache.',
                $this->get_rate_limit_reset()
            );
        }

        if ( is_wp_error( $error ) ) {
            $this->handle_response_error( $error );
        }

        /*
         * API did not return a specific error, fall back
         * to the status code to determine what went wrong.
         */
        $this->handle_response_status( $this->status );
    }

    /**
     * Return the seconds until the rate limit resets, from the `x-ratelimit-reset` header.
     *
     * @return ?int Seconds until the rate limit resets, or `null` if the header is missing or invalid.
     */
    private function get_rate_limit_reset(): ?int {
        $reset = $this->get_header( 'x-ratelimit-reset' );

        if ( $reset === null || ! ctype_digit( trim( $reset ) ) ) {
            return null;
        }

        return intval( trim( $reset ) );
    }

    /**
     * Handle error returned in response body.
     *
     * @throws Resource_Not_Found_Exception     If the specified resource is not found.
     * @throws Web_Property_Not_Found_Exception If the specified web property is not found.
     * @throws RuntimeException                 For any other error.
     *
     * @param WP_Error $error Error extracted from the response.
     *
     * @return void
     */
    private function handle_response_error( WP_Error $error ): void {
        $error_code    = $error->get_error_code();
        $error_message = $error->get_error_message();

        throw match ( $error_code ) {
            'not-found' => new Resource_Not_Found_Exception(
                sprintf(
                    'Could not find resource: %s',
                    esc_html( $error_message )
                )
            ),
            'webproperty-not-found' => new Web_Property_Not_Found_Exception(
                sprintf(
                    'Could not find web property: %s',
                    esc_html( $error_message )
                )
            ),
            default => new RuntimeException(
                sprintf(
                    'An unexpected error \'%s\' occurred while invalidating the cache: %s',
                    esc_html( $error_code ),
                    esc_html( $error_message )
                )
            ),
        };
    }

    /**
     * Handle response status code that is not 2xx.
     *
     * @throws Resource_Not_Found_Exception If the specified resource is not found.
     * @throws Validation_Exception         If there is a validation error.
     * @throws API_Exception                If there is a general API error.
     * @throws RuntimeException             For any other error.
     *
     * @param int $status_code Response status code.
     *
     * @return void
     */
    private function handle_response_status( $status_code ): void {
        throw match ( $this->status ) {
            403 => new Auth_Exception(
                'Authentication failed while invalidating the cache.'
            ),
            404 => new Resource_Not_Found_Exception(
                'The specified resource could not be found while invalidating the cache.'
            ),
            422 => new Validation_Exception(
                'There was a validation error while invalidating the cache.'
            ),
            500 => new API_Exception(
                'A general API error occurred while invalidating the cache.'
            ),
            default => new RuntimeException(
                sprintf(
                    'An unexpected error with status code %d occurred while invalidating the cache.',
                    esc_html( strval( $this->status ) )
                )
            ),
        };
    }

}
