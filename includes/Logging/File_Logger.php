<?php
/**
 * File logger.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Logging;

use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;

use Pressidium\WP\Performance\Dependencies\Psr\Log\LogLevel;
use Pressidium\WP\Performance\Dependencies\Psr\Log\InvalidArgumentException;

use RuntimeException;
use Exception;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * File_Logger class.
 *
 * @since 1.0.0
 */
final class File_Logger implements Logger {

    /**
     * File_Logger constructor.
     *
     * @param Filesystem $filesystem An instance of `Filesystem`.
     */
    public function __construct( private readonly Filesystem $filesystem ) {}

    /**
     * @var string Minimum log level to log.
     */
    const MIN_LOG_LEVEL = LogLevel::INFO;

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
     * Return the path to the `logs/` directory.
     *
     * @throws RuntimeException If the `logs/` directory could not be created.
     * @throws RuntimeException If the `logs/` directory is not writable.
     * @throws RuntimeException If the `.htaccess` file could not be created.
     *
     * @return string
     */
    public function get_logs_path(): string {
        $uploads_dir = wp_get_upload_dir()['basedir'];
        $log_path    = trailingslashit( $uploads_dir ) . 'pressidium-performance/logs/error.log';

        /**
         * Filters the path to the log file.
         *
         * @since 1.0.0
         *
         * @param string $log_path Path to the log file.
         *
         * @return string
         */
        $log_path = apply_filters( 'pressidium_performance_logs_path', $log_path );

        $log_dir       = dirname( $log_path );
        $htaccess_path = trailingslashit( $log_dir ) . '.htaccess';

        if ( ! file_exists( $log_dir ) ) {
            // Attempt to create the logs directory if it doesn't exist
            $did_create = wp_mkdir_p( $log_dir );

            if ( ! $did_create ) {
                throw new RuntimeException( 'Could not create logs directory: ' . esc_html( $log_dir ) );
            }
        }

        if ( ! wp_is_writable( $log_dir ) ) {
            // Directory is not writable
            throw new RuntimeException( 'Logs directory is not writable: ' . esc_html( $log_dir ) );
        }

        if ( ! file_exists( $htaccess_path ) ) {
            // Create a `.htaccess` file to prevent direct access
            $htaccess_content = "Deny from all\n";
            $did_write        = file_put_contents( $htaccess_path, $htaccess_content );

            if ( ! $did_write ) {
                throw new RuntimeException( 'Could not create .htaccess file: ' . esc_html( $htaccess_path ) );
            }
        }

        // No need to check if the file exists, it will be created when we log the first message
        return $log_path;
    }

    /**
     * Log a message with an arbitrary level.
     *
     * @throws InvalidArgumentException If the log level is invalid.
     * @throws InvalidArgumentException If the message is empty.
     * @throws RuntimeException         If the log file could not be written to.
     *
     * @param mixed  $level   Log level.
     * @param string $message Log message.
     * @param array  $context Any extraneous information that does not fit well in a string.
     *
     * @return void
     */
    public function log( $level, $message, array $context = array() ): void {
        $destination = $this->get_logs_path();

        if ( ! isset( self::LEVELS[ $level ] ) ) {
            throw new InvalidArgumentException( 'Invalid log level: ' . esc_html( $level ) );
        }

        if ( self::LEVELS[ $level ] < self::LEVELS[ self::MIN_LOG_LEVEL ] ) {
            return;
        }

        if ( empty( $message ) ) {
            throw new InvalidArgumentException( 'Empty message' );
        }

        if ( ! empty( $context ) ) {
            $message = $message . PHP_EOL . print_r( $context, true );
        }

        $timestamp = gmdate( 'Y-m-d H:i:s' );
        $message   = sprintf( '[%s] [%s] %s', $timestamp, $level, esc_html( $message ) );

        $did_write = $this->filesystem->append( $destination, $message . PHP_EOL );

        if ( ! $did_write ) {
            throw new RuntimeException( 'Could not write to log file: ' . esc_html( $destination ) );
        }
    }

    /**
     * Log the given exception to a log file.
     *
     * @throws InvalidArgumentException If the exception message is empty.
     * @throws RuntimeException         If the log file could not be written to.
     *
     * @param Exception $exception Exception to log.
     *
     * @return void
     */
    public function log_exception( Exception $exception ): void {
        $this->error( $exception->getMessage(), array( 'exception' => $exception ) );
    }

    /**
     * Read the log file and return its contents.
     *
     * @throws RuntimeException If the log file could not be read.
     *
     * @return string
     */
    public function get_logs(): string {
        $source = $this->get_logs_path();

        if ( ! $this->filesystem->exists( $source ) ) {
            // File does not exist, so there are no logs
            return '';
        }

        try {
            $logs = $this->filesystem->read( $source );
        } catch ( Filesystem_Exception $exception ) {
            throw new RuntimeException( esc_html( $exception->getMessage() ) );
        }

        return $logs;
    }

    /**
     * Clear logs.
     *
     * @throws RuntimeException If the log file could not be cleared.
     *
     * @return void
     */
    public function clear(): void {
        $destination = $this->get_logs_path();
        $did_clear   = $this->filesystem->write( $destination, '' );

        if ( $did_clear === false ) {
            throw new RuntimeException( 'Could not clear log file: ' . esc_html( $destination ) );
        }
    }

}
