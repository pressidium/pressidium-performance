<?php
/**
 * Settings.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

use Pressidium\WP\Performance\Options\Options;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Settings class.
 *
 * @since 1.0.0
 */
class Settings {

    /**
     * @var string Options key.
     */
    const OPTIONS_KEY = 'pressidium_performance_settings';

    /**
     * @var Options An instance of `Options`.
     */
    private Options $options;

    /**
     * Settings constructor.
     *
     * @param Options $options An instance of `Options`.
     */
    public function __construct( Options $options ) {
        $this->options = $options;
    }

    /**
     * Return default values for the settings.
     *
     * @return array
     */
    private function get_default_values(): array {
        return array(
            'minification'      => array(
                'minifyJS'   => true,
                'minifyCSS'  => true,
                'exclusions' => array(
                    'js'  => array(),
                    'css' => array(),
                ),
            ),
            'concatenation'     => array(
                'concatenateJS'  => false,
                'concatenateCSS' => false,
                'exclusions'     => array(
                    'js'  => array(),
                    'css' => array(),
                ),
            ),
            'imageOptimization' => array(
                'autoOptimize'         => true,
                'keepOriginalFiles'    => true,
                'preferredImageEditor' => 'auto',
                'formats'              => array(
                    'image/jpeg'    => array(
                        'shouldOptimize' => true,
                        'convertTo'      => 'image/webp',
                    ),
                    'image/png'     => array(
                        'shouldOptimize' => true,
                        'convertTo'      => 'image/webp',
                    ),
                    'image/gif'     => array(
                        'shouldOptimize' => true,
                        'convertTo'      => 'image/webp',
                    ),
                    'image/svg+xml' => array(
                        'shouldOptimize' => true,
                        'convertTo'      => 'image/webp',
                    ),
                    'image/webp'    => array(
                        'shouldOptimize' => true,
                        'convertTo'      => 'image/webp',
                    ),
                    'image/avif'    => array(
                        'shouldOptimize' => true,
                        'convertTo'      => 'image/webp',
                    ),
                    'image/heif'    => array(
                        'shouldOptimize' => true,
                        'convertTo'      => 'image/webp',
                    ),
                    'image/heic'    => array(
                        'shouldOptimize' => true,
                        'convertTo'      => 'image/webp',
                    ),
                ),
                'quality'              => 80,
                'exclusions'           => array(),
            ),
        );
    }

    /**
     * Return settings.
     *
     * @return array
     */
    public function get(): array {
        $settings = $this->options->get( self::OPTIONS_KEY );

        if ( ! empty( $settings ) ) {
            return $settings;
        }

        return $this->get_default_values();
    }

    /**
     * Set settings.
     *
     * @param array $settings Settings to store.
     *
     * @return bool Whether the settings were stored successfully.
     */
    public function set( array $settings ): bool {
        if ( empty( $settings ) ) {
            $settings = $this->get_default_values();
        }

        return $this->options->set( self::OPTIONS_KEY, $settings );
    }

    /**
     * Remove settings.
     *
     * @return bool Whether the settings were removed successfully.
     */
    public function remove(): bool {
        return $this->options->remove( self::OPTIONS_KEY );
    }

}
