<?php
/**
 * CSS minifier of the `MatthiasMullie\Minify` library, with safer path rewriting.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification\CSS;

use Pressidium\WP\Performance\Dependencies\MatthiasMullie\Minify;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Minify_CSS class.
 *
 * `MatthiasMullie\Minify::canImportByPath()` only leaves `data:`, `http(s):`
 * and root-relative paths (`/`) alone when rewriting relative `url()` and
 * `@import` paths. It rewrites anything else, which breaks fragment-only
 * references (e.g. `filter: url(#blur)`, `mask: url(#m)`, `clip-path: url(#c)`).
 *
 * @since 1.2.0
 */
final class Minify_CSS extends Minify\CSS {

    // phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Overrides a library method

    /**
     * Whether the given path is a relative path that can be rewritten or imported.
     *
     * @param string $path Path to check.
     *
     * @return bool
     */
    protected function canImportByPath( $path ) {
        if ( ! parent::canImportByPath( $path ) ) {
            return false;
        }

        // Fragment-only references and URLs with a scheme are not relative paths
        return preg_match( '/^(#|[a-z][a-z0-9+.-]*:)/i', $path ) === 0;
    }

    // phpcs:enable

}
