<?php
/**
 * Logger Interface.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Logging;

use Pressidium\WP\Performance\Dependencies\Psr\Log\LogLevel;
use Pressidium\WP\Performance\Dependencies\Psr\Log\LoggerInterface;

use Pressidium\WP\Performance\Dependencies\Psr\Log\InvalidArgumentException;

use RuntimeException;
use Exception;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Logger interface.
 *
 * PSR-3 compliant logger interface.
 *
 * @since 1.0.0
 */
interface Logger extends LoggerInterface {

    const LEVELS = array(
        LogLevel::DEBUG     => 0,
        LogLevel::INFO      => 1,
        LogLevel::NOTICE    => 2,
        LogLevel::WARNING   => 3,
        LogLevel::ERROR     => 4,
        LogLevel::CRITICAL  => 5,
        LogLevel::ALERT     => 6,
        LogLevel::EMERGENCY => 7,
    );

    /**
     * Log the given exception.
     *
     * @throws InvalidArgumentException If the log level is not defined.
     * @throws InvalidArgumentException If the exception message is empty.
     * @throws RuntimeException         If the log cannot be written.
     *
     * @param Exception $exception Exception to log.
     *
     * @return void
     */
    public function log_exception( Exception $exception ): void;

    /**
     * Return the logs.
     *
     * If logs are not retrievable, an empty string should be returned.
     *
     * @throws RuntimeException If the logs cannot be retrieved.
     *
     * @return string
     */
    public function get_logs(): string;

    /**
     * Clear logs.
     *
     * If there are no stored logs, this method should do nothing.
     *
     * @throws RuntimeException If the logs cannot be cleared.
     *
     * @return void
     */
    public function clear(): void;

}
