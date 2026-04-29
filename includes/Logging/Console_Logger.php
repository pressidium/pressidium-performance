<?php
/**
 * Console logger.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Logging;

use Pressidium\WP\Performance\Dependencies\Psr\Log\LogLevel;
use Pressidium\WP\Performance\Dependencies\Psr\Log\InvalidArgumentException;

use Exception;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Console_Logger class.
 *
 * @since 1.0.0
 */
class Console_Logger implements Logger {

    /**
     * @var string Minimum log level to log.
     */
    private string $min_log_level;

    /**
     * Console_Logger constructor.
     *
     * @param bool $debug_mode Whether debug mode is enabled.
     */
    public function __construct(
        private readonly bool $debug_mode = false,
    ) {
        $this->min_log_level = $this->debug_mode ? LogLevel::DEBUG : LogLevel::INFO;
    }

    /**
     * Log a message with a level of an emergency — system is unusable.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function emergency( $message, array $context = array() ): void {
        $this->log( LogLevel::CRITICAL, $message, $context );
    }

    /**
     * Log a message with a level of an alert — actions must be taken immediately.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function alert( $message, array $context = array() ): void {
        $this->log( LogLevel::ALERT, $message, $context );
    }

    /**
     * Log a message with a level of a critical condition.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function critical( $message, array $context = array() ): void {
        $this->log( LogLevel::CRITICAL, $message, $context );
    }

    /**
     * Log a message with a level of an error — runtime errors that do not require immediate action.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function error( $message, array $context = array() ): void {
        $this->log( LogLevel::ERROR, $message, $context );
    }

    /**
     * Log a message with a level of warning — exceptional occurrences that are not errors.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function warning( $message, array $context = array() ): void {
        $this->log( LogLevel::WARNING, $message, $context );
    }

    /**
     * Log a message with a level of notice — normal but significant events.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function notice( $message, array $context = array() ): void {
        $this->log( LogLevel::NOTICE, $message, $context );
    }

    /**
     * Log a message with a level of info — interesting events.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function info( $message, array $context = array() ): void {
        $this->log( LogLevel::INFO, $message, $context );
    }

    /**
     * Log a message with a level of debug — detailed debug information.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function debug( $message, array $context = array() ): void {
        $this->log( LogLevel::DEBUG, $message, $context );
    }

    /**
     * Log a message with an arbitrary level.
     *
     * @throws InvalidArgumentException If the log level is invalid.
     * @throws InvalidArgumentException If the message is empty.
     *
     * @param mixed  $level   Log level.
     * @param string $message Log message.
     * @param array  $context Any extraneous information that does not fit well in a string.
     *
     * @return void
     */
    public function log( $level, $message, array $context = array() ): void {
        if ( ! isset( self::LEVELS[ $level ] ) ) {
            throw new InvalidArgumentException( 'Invalid log level: ' . esc_html( $level ) );
        }

        if ( self::LEVELS[ $level ] < self::LEVELS[ $this->min_log_level ] ) {
            return;
        }

        if ( empty( $message ) ) {
            throw new InvalidArgumentException( 'Empty message' );
        }

        if ( ! empty( $context ) ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions
            $message = $message . PHP_EOL . print_r( $context, true );
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        error_log( sprintf( '[%s] %s', $level, $message ) );
    }

    /**
     * Log the given exception.
     *
     * When debug mode is enabled, logs the exception message
     * along with its full stack trace. When disabled, logs a
     * compact summary with the message, code, file, and line.
     *
     * @throws InvalidArgumentException If the exception message is empty.
     *
     * @param Exception $exception Exception to log.
     *
     * @return void
     */
    public function log_exception( Exception $exception ): void {
        if ( $this->debug_mode ) {
            $this->error(
                sprintf(
                    '%s: %s',
                    esc_html( $exception->getMessage() ),
                    esc_html( $exception->getTraceAsString() )
                )
            );
            return;
        }

        $this->error(
            sprintf(
                '%s (code: %s) in %s:%d',
                $exception->getMessage(),
                $exception->getCode(),
                $exception->getFile(),
                $exception->getLine()
            )
        );
    }

    /**
     * Return an empty string since console logs are not stored in a file.
     *
     * @return string
     */
    public function get_logs(): string {
        return '';
    }

    /**
     * Clear logs.
     *
     * @return void
     */
    public function clear(): void {
        // Do nothing since console logs are not stored in a file.
    }

}
