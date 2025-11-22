<?php
/**
 * Logs.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Logging\File_Logger;

use Pressidium\WP\Performance\Utils\WP_Utils;

use RuntimeException;

use const Pressidium\WP\Performance\VERSION;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Logs class.
 *
 * @since 1.0.0
 */
class Logs {

    /**
     * @var Logger An instance of `Logger`.
     */
    private Logger $logger;

    /**
     * Logs constructor.
     *
     * @param Logger $logger An instance of `Logger`.
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }

    /**
     * Return debug info.
     *
     * @return string
     */
    private function get_debug_info(): string {
        global $wpdb;

        $logs_location = $this->logger instanceof File_Logger ? $this->logger->get_logs_path() : 'N/A';

        return implode(
            "\n",
            array(
                sprintf( 'Pressidium Performance v%s', VERSION ),
                sprintf( 'WordPress v%s', get_bloginfo( 'version' ) ),
                sprintf( 'PHP v%s', phpversion() ),
                sprintf( 'MySQL/MariaDB v%s', $wpdb->db_server_info() ),
                $wpdb->get_charset_collate(),
                sprintf( 'Memory limit: %s', WP_Utils::get_human_readable_memory_limit() ),
                sprintf( 'Imagick %s', WP_Utils::get_imagick_version() ),
                sprintf( 'GD %s', WP_Utils::get_gd_version() ),
                sprintf( 'Logs located @ %s', $logs_location ),
                sprintf( 'Installation @ %s', get_bloginfo( 'url' ) ),
            )
        ) . "\n";
    }

    /**
     * Return the logs.
     *
     * @return string
     */
    public function get_logs(): string {
        $logs = '';

        try {
            $logs = $this->logger->get_logs();
        } catch ( RuntimeException $exception ) {
            $this->logger->log_exception( $exception );
        }

        // Prepend debug info
        return sprintf( "%s\n%s", $this->get_debug_info(), $logs );
    }

    /**
     * Clear logs.
     *
     * @return bool
     */
    public function clear(): bool {
        try {
            $this->logger->clear();
        } catch ( RuntimeException $exception ) {
            $this->logger->log_exception( $exception );

            return false;
        }

        return true;
    }

}
