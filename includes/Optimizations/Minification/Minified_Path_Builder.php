<?php
/**
 * Minified path builder.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification;

use Pressidium\WP\Performance\URL_Builder;

use Pressidium\WP\Performance\Enumerations\Output_Directory;
use Pressidium\WP\Performance\Exceptions\Minification_Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Minified_Path_Builder class.
 *
 * Owns the naming scheme of minified files, so the output filename, public URL,
 * and resolved file path always match.
 *
 * @since 1.2.0
 */
final class Minified_Path_Builder {

    /**
     * @var int Number of leading hash characters to include in the filename.
     */
    private const HASH_LENGTH = 8;

    /**
     * Minified_Path_Builder constructor.
     *
     * @param URL_Builder $url_builder An instance of `URL_Builder`.
     */
    public function __construct( private readonly URL_Builder $url_builder ) {}

    /**
     * Throw if the given relative path would escape the output directory.
     *
     * Neither `Filesystem::join()` nor `Filesystem::build_path()` normalizes the paths they
     * are given, so a `..` segment that makes it this far ends up writing outside the
     * minified directory.
     *
     * @throws Minification_Exception If the path is not safe to write to.
     *
     * @param string $relative_path Relative path to check.
     *
     * @return void
     */
    private function assert_path_is_safe( string $relative_path ): void {
        if ( str_contains( $relative_path, "\0" ) ) {
            throw new Minification_Exception( 'Could not minify file: Path contains a null byte' );
        }

        if ( in_array( '..', explode( '/', $relative_path ), true ) ) {
            throw new Minification_Exception(
                'Could not minify file: Path traverses outside the output directory'
            );
        }
    }

    /**
     * Return the filename to write the minified version of the given file under.
     *
     * @throws Minification_Exception If a filename could not be derived.
     *
     * @param string $source_filename Filename of the original file.
     * @param string $hash            Hash of the original file's contents.
     *
     * @return string Filename, in the form `<name>.<hash>.<extension>`.
     */
    public function build_filename( string $source_filename, string $hash ): string {
        if ( strlen( $hash ) < self::HASH_LENGTH ) {
            throw new Minification_Exception( 'Could not minify file: Hash is too short to name the file with' );
        }

        $name      = pathinfo( $source_filename, PATHINFO_FILENAME );
        $extension = pathinfo( $source_filename, PATHINFO_EXTENSION );

        if ( $name === '' ) {
            throw new Minification_Exception( 'Could not minify file: URL does not point to a file' );
        }

        $short_hash = substr( $hash, 0, self::HASH_LENGTH );

        if ( $extension === '' ) {
            return sprintf( '%s.%s', $name, $short_hash );
        }

        return sprintf( '%s.%s.%s', $name, $short_hash, $extension );
    }

    /**
     * Return the path of the minified version of the given file, relative to the output directory.
     *
     * @throws Minification_Exception If the URL could not be parsed.
     *
     * @param string $file_url File URL.
     * @param string $hash     Hash of the file's contents.
     *
     * @return string Relative path.
     */
    public function build_relative_path( string $file_url, string $hash ): string {
        $components = wp_parse_url( $file_url );

        if ( ! is_array( $components ) || ! isset( $components['path'] ) ) {
            throw new Minification_Exception( 'Could not minify file: URL could not be parsed' );
        }

        /*
         * A root-relative `src` has no host of its own. It is served from this site by
         * definition, so file it under this site's host instead of giving up on it.
         */
        $url_host = $components['host'] ?? wp_parse_url( home_url(), PHP_URL_HOST );

        if ( ! is_string( $url_host ) || empty( $url_host ) ) {
            throw new Minification_Exception( 'Could not minify file: URL has no host' );
        }

        // We deliberately leave out the port since `:` is not a legal filename character on every platform
        $url_host = strtolower( $url_host );
        $url_path = ltrim( $components['path'], '/' );

        if ( $url_path === '' ) {
            throw new Minification_Exception( 'Could not minify file: URL does not point to a file' );
        }

        $relative_path = $url_host . '/' . $url_path;

        $this->assert_path_is_safe( $relative_path );

        $directory = dirname( $relative_path );
        $filename  = $this->build_filename( basename( $relative_path ), $hash );

        return $directory === '.' ? $filename : $directory . '/' . $filename;
    }

    /**
     * Strip the scheme from the given URL.
     *
     * @param string $url URL to strip the scheme from.
     *
     * @return string URL without its scheme.
     */
    private function strip_scheme( string $url ): string {
        return (string) preg_replace( '#^(https?:)?//#i', '', $url );
    }

    /**
     * Return the path the given optimized URI points to, relative to the output directory.
     *
     * @param string $optimized_uri Optimized URI.
     *
     * @return ?string Relative path, or `null` if the URI is not one of ours.
     */
    public function relative_path_from_uri( string $optimized_uri ): ?string {
        // Records written on Windows before the scheme was normalized may hold backslashes
        $uri      = $this->strip_scheme( str_replace( '\\', '/', $optimized_uri ) );
        $base_url = $this->strip_scheme( $this->url_builder->get_base_url() );

        $prefix = $base_url . '/' . Output_Directory::MINIFIED->value . '/';

        if ( ! str_starts_with( $uri, $prefix ) ) {
            return null;
        }

        $relative_path = substr( $uri, strlen( $prefix ) );

        return empty( $relative_path ) ? null : $relative_path;
    }

}
