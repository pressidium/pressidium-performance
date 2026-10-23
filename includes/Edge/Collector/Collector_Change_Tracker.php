<?php
/**
 * Collector change tracker.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge\Collector;

use Pressidium\WP\Performance\Hooks\Actions;
use Pressidium\WP\Performance\Logging\Logger;

use Pressidium\WP\Performance\Edge\Web_Property;

use const Pressidium\WP\Performance\PLUGIN_FILE;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Collector_Change_Tracker class.
 *
 * Schedules a collector run whenever the site's plugin, theme or core state changes,
 * so the telemetry stored by the EDGE API stays up to date between the twice-daily runs.
 *
 * Changes are batched: The first change schedules a single WP-Cron event
 * `DEBOUNCE_WINDOW` seconds out, and any change within that window finds the
 * event already scheduled and does nothing. This way, bulk operations (e.g.
 * updating 20 plugins at once) result in a single collector run.
 *
 * @since 1.2.0
 */
final class Collector_Change_Tracker implements Actions {

    /**
     * @var string Hook of the single WP-Cron event that runs the pending collection.
     */
    public const CRON_HOOK = 'pressidium_performance_collector_pending_run';

    /**
     * @var int Seconds to wait after the first change, before running the collector.
     */
    private const DEBOUNCE_WINDOW = 5 * MINUTE_IN_SECONDS;

    /**
     * Collector_Change_Tracker constructor.
     *
     * @param Logger                 $logger            Logger instance.
     * @param Web_Property           $web_property      Web property instance.
     * @param Edge_Collector_Process $collector_process Background-process collector runner.
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly Web_Property $web_property,
        private readonly Edge_Collector_Process $collector_process
    ) {}

    /**
     * Schedule a collector run, unless one is already pending.
     *
     * @return void
     */
    public function mark_pending(): void {
        if ( empty( $this->web_property->get_web_property_handle() ) ) {
            // Not connected to EDGE, there's nowhere to send the telemetry to
            return;
        }

        if ( wp_next_scheduled( self::CRON_HOOK ) ) {
            // A run is already pending, it will pick up this change as well
            return;
        }

        wp_schedule_single_event( time() + self::DEBOUNCE_WINDOW, self::CRON_HOOK );

        $this->logger->debug(
            sprintf( 'Scheduled a collector run in %d seconds.', self::DEBOUNCE_WINDOW )
        );
    }

    /**
     * Handle a plugin being activated or deactivated.
     *
     * @param string $plugin Path to the plugin file relative to the plugins directory.
     *
     * @return void
     */
    public function handle_plugin_state_change( string $plugin ): void {
        if ( plugin_basename( PLUGIN_FILE ) === $plugin ) {
            /*
             * When our own plugin is deactivated, `deactivated_plugin` fires
             * after our deactivation hook has cleared our cron jobs, so we'd
             * leave an orphaned event behind.
             */
            return;
        }

        $this->mark_pending();
    }

    /**
     * Handle a plugin or theme being deleted.
     *
     * @param string $slug    Plugin file or theme stylesheet.
     * @param bool   $deleted Whether the plugin or theme was deleted successfully.
     *
     * @return void
     */
    public function handle_deleted( string $slug, bool $deleted ): void {
        if ( ! $deleted ) {
            return;
        }

        $this->mark_pending();
    }

    /**
     * Handle an upgrader finishing an install or update.
     *
     * @param mixed                $upgrader   `WP_Upgrader` instance (unused).
     * @param array<string, mixed> $hook_extra Array of bulk item update data.
     *
     * @return void
     */
    public function handle_upgrader_complete( $upgrader, array $hook_extra ): void {
        $type   = $hook_extra['type'] ?? '';
        $action = $hook_extra['action'] ?? '';

        if ( ! in_array( $type, array( 'plugin', 'theme', 'core' ), true ) ) {
            // Ignore other updates (e.g. translation updates)
            return;
        }

        if ( ! in_array( $action, array( 'install', 'update' ), true ) ) {
            // Ignore other actions
            return;
        }

        $this->mark_pending();
    }

    /**
     * Run the pending collection.
     *
     * If a collector run is already in progress, it may have collected the
     * plugins and themes before the change happened, so we reschedule instead
     * of dropping the change.
     *
     * @return void
     */
    public function run_pending_collection(): void {
        if ( $this->collector_process->is_active() ) {
            $this->logger->info( 'A collector run is in progress, rescheduling the pending collector run.' );
            $this->mark_pending();
            return;
        }

        $run_id = $this->collector_process->start_run();

        if ( $run_id === null ) {
            $this->logger->warning( 'Pending collector run was not dispatched.' );
            return;
        }

        $this->logger->info( sprintf( 'Dispatched pending collector run %s.', esc_html( $run_id ) ) );
    }

    /**
     * Return the actions to register.
     *
     * @return array<string, array{0: string, 1?: int, 2?: int}>
     */
    public function get_actions(): array {
        return array(
            // Plugin activate/deactivate
            'activated_plugin'           => array( 'handle_plugin_state_change' ),
            'deactivated_plugin'         => array( 'handle_plugin_state_change' ),

            // Plugin/theme deletion
            'deleted_plugin'             => array( 'handle_deleted', 10, 2 ),
            'deleted_theme'              => array( 'handle_deleted', 10, 2 ),

            // Theme switch
            'switch_theme'               => array( 'mark_pending', 10, 0 ),

            // Plugin/theme/core installs and updates, including automatic updates
            'upgrader_process_complete'  => array( 'handle_upgrader_complete', 10, 2 ),
            '_core_updated_successfully' => array( 'mark_pending', 10, 0 ),

            // Debounced collector run
            self::CRON_HOOK              => array( 'run_pending_collection', 10, 0 ),
        );
    }

}