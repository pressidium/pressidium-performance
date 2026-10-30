<?php
/**
 * Response.php
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\API;

use Pressidium\WP\Performance\Exceptions\API\API_Exception;

use WP_Error;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Response class.
 *
 * @since 1.1.0
 */
abstract class Response {

    /**
     * @var array<string, mixed> Raw response from the API.
     */
    protected array $response_raw;

    /**
     * @var ?string Body of the response as raw string.
     */
    protected ?string $body_raw = null;

    /**
     * @var ?array<string, null> Parsed body of the response. Associative array.
     */
    protected ?array $body = null;

    /**
     * @var ?int Response code.
     */
    protected ?int $status = null;

    /**
     * Response constructor.
     *
     * @throws API_Exception    If there is any error while handling the response.
     * @throws RuntimeException If there was an unexpected error while handling the response.
     *
     * @param array<string, mixed> $response_raw Raw response from the API.
     */
    public function __construct( array $response_raw ) {
        $response_code = wp_remote_retrieve_response_code( $response_raw );

        $this->response_raw = $response_raw;
        $this->status       = is_int( $response_code ) ? $response_code : null;
        $this->body_raw     = wp_remote_retrieve_body( $response_raw );
        $this->body         = json_decode( $this->body_raw ?? '', true );

        // Consider any 2xx response as successful
        if ( $this->status === null || $this->status < 200 || $this->status > 299 ) {
            $this->handle_errors( $this->get_error() );
        }
    }

    /**
     * Extract the first error from the response body, if present.
     *
     * @return ?WP_Error The first error as a `WP_Error` object, or `null` if no errors are present.
     */
    private function get_error(): ?WP_Error {
        if ( ! is_array( $this->body ) || ! isset( $this->body['errors'] ) ) {
            // No errors present
            return null;
        }

        $errors = $this->body['errors'];

        if ( ! is_array( $errors ) || empty( $errors ) ) {
            // No errors present
            return null;
        }

        $first_error = $this->body['errors'][0];

        if ( ! isset( $first_error['code'] ) || ! isset( $first_error['message'] ) ) {
            // Invalid error format
            return null;
        }

        return new WP_Error( $first_error['code'], $first_error['message'] );
    }

    /**
     * Handle any non-2xx response.
     *
     * @throws API_Exception    If there was an error while handling the response.
     * @throws RuntimeException If there was an unexpected error while handling the response.
     *
     * @param ?WP_Error $error The extracted error from the response, if any.
     *
     * @return void
     */
    abstract protected function handle_errors( ?WP_Error $error ): void;

    /**
     * Return the response code.
     *
     * @return int Response code.
     */
    public function get_status(): int {
        return $this->status;
    }

    /**
     * Return the value of the given response header.
     *
     * @param string $name Header name (case-insensitive).
     *
     * @return ?string Header value, or `null` if the header is not present.
     */
    public function get_header( string $name ): ?string {
        $value = wp_remote_retrieve_header( $this->response_raw, $name );

        if ( is_array( $value ) ) {
            // Header was sent multiple times, use the last value
            $value = end( $value );
        }

        return $value === '' || $value === false ? null : strval( $value );
    }

    /**
     * Return the parsed body of the response.
     *
     * @return array<string, mixed> An associative array representing the response body.
     */
    public function get_body(): array {
        return $this->body;
    }

}
