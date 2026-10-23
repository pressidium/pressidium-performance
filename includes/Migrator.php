<?php
/**
 * Migrator.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Migrator class.
 *
 * @since 1.0.0
 */
class Migrator {

    /**
     * @var array Settings.
     */
    private array $settings;

    /**
     * Migrator constructor.
     *
     * @param array $settings Settings to migrate.
     */
    public function __construct( array $settings = array() ) {
        $this->settings = $settings;
    }

    /**
     * Migrate settings coming from versions prior to 1.2.0.
     *
     * @return void
     */
    private function migrate_1_2_0(): void {
        $minification_same_origin_only = $this->settings['minification']['sameOriginOnly'] ?? true;

        $this->settings['minification']['sameOriginOnly'] = $minification_same_origin_only;

        $concatenation_same_origin_only = $this->settings['concatenation']['sameOriginOnly'] ?? true;

        $this->settings['concatenation']['sameOriginOnly'] = $concatenation_same_origin_only;

        /*
         * Minification is now off by default, even for existing installations
         * (admins can always re-enable it, if they want to)
         */
        $this->settings['minification']['minifyJS']  = false;
        $this->settings['minification']['minifyCSS'] = false;
    }

    /**
     * Migrate settings if necessary.
     *
     * @return array Migrated settings.
     */
    public function maybe_migrate(): array {
        if ( ! isset( $this->settings['version'] ) ) {
            // We do not have a version, so we assume that we are not upgrading from a previous version
            return $this->settings;
        }

        if ( version_compare( $this->settings['version'], '1.2.0', '<' ) ) {
            // We are upgrading from a version prior to 1.2.0, so we need to migrate the settings
            $this->migrate_1_2_0();
        }

        return $this->settings;
    }

}
