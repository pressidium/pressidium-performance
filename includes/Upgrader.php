<?php
/**
 * Upgrader.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance;

use const Pressidium\WP\Performance\VERSION;

use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Optimizations\Concatenation\Concatenation_Cleaner;
use Pressidium\WP\Performance\Exceptions\Filesystem_Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Upgrader class.
 *
 * @since 1.2.0
 */
final class Upgrader {

    /**
     * Upgrader constructor.
     *
     * @param Logger                $logger                An instance of `Logger`.
     * @param Settings              $settings              An instance of `Settings`.
     * @param Concatenation_Cleaner $concatenation_cleaner An instance of `Concatenation_Cleaner`.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly Settings $settings,
        private readonly Concatenation_Cleaner $concatenation_cleaner,
    ) {}

    /**
     * Whether the plugin was just upgraded.
     *
     * @param array $settings Settings to check against.
     *
     * @return bool
     */
    public function did_just_upgrade( array $settings ): bool {
        if ( ! isset( $settings['version'] ) ) {
            return false;
        }

        if ( ! defined( 'Pressidium\WP\Performance\VERSION' ) ) {
            return false;
        }

        return version_compare( $settings['version'], VERSION, '<' );
    }

    /**
     * Migrate the given settings to the latest version.
     *
     * @param array $settings Settings to migrate.
     *
     * @return array
     */
    private function maybe_migrate( array $settings ): array {
        $migrator          = new Migrator( $settings );
        $migrated_settings = $migrator->maybe_migrate();

        return $migrated_settings;
    }

    /**
     * Clear all concatenation records and cancel any running concatenations.
     *
     * @return void
     */
    private function clear_concatenations(): void {
        try {
            $this->concatenation_cleaner->clear();
        } catch ( Filesystem_Exception $exception ) {
            $this->logger->error(
                sprintf( 'Could not clear concatenations during upgrade: %s', $exception->getMessage() )
            );
        }
    }

    /**
     * Upgrade from versions prior to 1.2.0.
     *
     * @return void
     */
    private function upgrade_to_1_2_0(): void {
        $this->clear_concatenations();
    }

    /**
     * Run the upgrade steps introduced after the given version.
     *
     * @param string $old_version Version we are upgrading from.
     *
     * @return void
     */
    private function run_upgrade_steps( string $old_version ): void {
        if ( version_compare( $old_version, '1.2.0', '<' ) ) {
            $this->upgrade_to_1_2_0();
        }
    }

    /**
     * Maybe upgrade.
     *
     * @return void
     */
    public function maybe_upgrade(): void {
        if ( ! is_admin() ) {
            // We only run the upgrade in the admin area, bail early
            return;
        }

        $settings = $this->settings->get();

        if ( ! $this->did_just_upgrade( $settings ) ) {
            // Plugin was not just upgraded, bail early
            return;
        }

        $old_version = $settings['version'] ?? '0.0.0';

        $settings            = $this->maybe_migrate( $settings );
        $settings['version'] = VERSION;

        $set_successfully = $this->settings->set( $settings );

        if ( ! $set_successfully ) {
            $this->logger->error(
                sprintf(
                    'Could not save settings after upgrade from version %s to %s.',
                    $old_version,
                    VERSION
                )
            );

            return;
        } else {
            /*
             * Only run once the new version is stored. A failed save has the upgrade
             * retried on the next request, which would run the steps again.
             */
            $this->run_upgrade_steps( $old_version );

            $this->logger->info(
                sprintf(
                    'Successfully upgraded from version %s to %s.',
                    $old_version,
                    VERSION
                )
            );
        }
    }

}
