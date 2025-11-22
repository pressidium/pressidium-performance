<?php
/**
 * HTML processor.
 *
 * Basically, this is a wrapper class for `WP_HTML_Tag_Processor`
 * so our plugin does not depend on WordPress core classes.
 *
 * That way, we could easily expand on this class and implement
 * our own logic, use `WP_HTML_Processor` or any other class
 * that we might need in the future.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

use WP_HTML_Tag_Processor;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * HTML_Processor class.
 *
 * @since 1.0.0
 */
final class HTML_Processor extends WP_HTML_Tag_Processor {

    /**
     * Find the next tag matching the given query.
     *
     * @phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
     *
     * @phpstan-impure
     *
     * @param array|string|null $query
     *
     *     Optional. Which tag name to find, having which class, etc. Default is to find any tag.
     *
     *     @type string|null $tag_name     Which tag to find, or `null` for "any tag."
     *     @type string      $tag_closers  'visit' to pause at tag closers, 'skip' or unset to only visit openers.
     *     @type int|null    $match_offset Find the Nth tag matching all search criteria.
     *                                     1 for "first" tag, 3 for "third," etc.
     *                                     Defaults to first tag.
     *     @type string|null $class_name   Tag must contain this whole class name to match.
     *     @type string[]    $breadcrumbs  DOM sub-path at which element is found, e.g. `array( 'FIGURE', 'IMG' )`.
     *                                     May also contain the wildcard `*` which matches a single element,
     *                                     e.g. `array( 'SECTION', '*' )`.
     *
     * @return bool Whether a tag was matched.
     */
    public function next_tag( $query = null ): bool {
        return parent::next_tag( $query );
    }

}
