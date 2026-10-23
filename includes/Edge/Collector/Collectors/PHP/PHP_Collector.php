<?php
/**
 * PHP collector.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector\Collectors\PHP;

use Pressidium\WP\Performance\Edge\Collector\Collector;
use Pressidium\WP\Performance\Utils\WP_Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * PHP_Collector class.
 *
 * Collects data related to the PHP environment.
 *
 * @since 1.2.0
 */
final class PHP_Collector implements Collector {

    /**
     * Return the collector name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'php';
    }

    /**
     * Collect PHP environment data.
     *
     * @return array<string, mixed>
     */
    public function collect(): array {
        return array(
            'server_architecture' => $this->get_server_architecture(),
            'web_server'          => $this->get_web_server(),
            'php_version'         => WP_Utils::get_php_version(),
            'php_sapi'            => $this->get_php_sapi(),
            'extensions'          => $this->get_extensions(),
            'time_limit'          => $this->get_time_limit(),
            'memory_limit'        => WP_Utils::get_human_readable_memory_limit(),
        );
    }

    /**
     * Return the server architecture.
     *
     * @return string
     */
    private function get_server_architecture(): string {
        if ( ! function_exists( 'php_uname' ) ) {
            return 'unknown';
        }

        return sprintf( '%s %s %s', php_uname( 's' ), php_uname( 'r' ), php_uname( 'm' ) );
    }

    /**
     * Return the PHP SAPI name.
     *
     * @return string
     */
    private function get_php_sapi(): string {
        return php_sapi_name();
    }

    /**
     * Return the web server software.
     *
     * @return string
     */
    private function get_web_server(): string {
        return isset( $_SERVER['SERVER_SOFTWARE'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) )
            : '';
    }

    /**
     * Return the list of loaded PHP extensions.
     *
     * @return string[]
     */
    private function get_extensions(): array {
        return get_loaded_extensions();
    }

    /**
     * Return the PHP maximum execution time limit in seconds.
     *
     * @return int
     */
    private function get_time_limit(): int {
        if ( function_exists( 'ini_get' ) ) {
            return (int) ini_get( 'max_execution_time' );
        }

        return 0;
    }

}
