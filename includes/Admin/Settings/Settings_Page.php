<?php
/**
 * Settings admin page.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Admin\Settings;

use Pressidium\WP\Performance\Admin\Page;
use Pressidium\WP\Performance\Hooks\Filters;
use Pressidium\WP\Performance\Hooks\Actions;
use Pressidium\WP\Performance\Utils\WP_Utils;
use Pressidium\WP\Performance\Storage\Storage;

use Pressidium\WP\Performance\Dependencies\Psr\SimpleCache\InvalidArgumentException;

use const Pressidium\WP\Performance\PLUGIN_DIR;
use const Pressidium\WP\Performance\PLUGIN_URL;
use const Pressidium\WP\Performance\PLUGIN_FILE;
use const Pressidium\WP\Performance\VERSION;

use WP_Site_Health;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Settings_Page class.
 *
 * @since 1.0.0
 */
final class Settings_Page extends Page implements Actions, Filters {

    /**
     * @var Storage An instance of `Storage`.
     */
    private Storage $transient_storage;

    /**
     * Settings_Page constructor.
     *
     * @param Storage $transient_storage An instance of `Storage`.
     */
    public function __construct( Storage $transient_storage ) {
        $this->transient_storage = $transient_storage;
    }

    /**
     * Return the menu slug.
     *
     * @return string
     */
    protected function get_menu_slug(): string {
        return 'pressidium-performance';
    }

    /**
     * Return the option group.
     *
     * @return string
     */
    protected function get_option_group(): string {
        return 'pressidium_performance';
    }

    /**
     * Return the option name.
     *
     * @return string
     */
    protected function get_option_name(): string {
        return 'pressidium_performance_settings';
    }

    /**
     * Return the page title.
     *
     * @return string
     */
    protected function get_page_title(): string {
        return __( 'Pressidium Performance Plugin', 'pressidium-performance' );
    }

    /**
     * Return the menu title.
     *
     * @return string
     */
    protected function get_menu_title(): string {
        return __( 'Performance', 'pressidium-performance' );
    }

    /**
     * Return the capability required for this menu to be displayed to the user.
     *
     * Override this method if you want to change the required capability.
     *
     * @return string
     */
    protected function get_capability(): string {
        return 'manage_options';
    }

    /**
     * Return the description of this options page.
     *
     * @return string
     */
    protected function get_description(): string {
        return __( 'Fine-tune your optimization settings for optimal results.', 'pressidium-performance' );
    }

    /**
     * Return the URL to the icon to be used for this menu.
     *
     * @return string
     */
    protected function get_icon(): string {
        // phpcs:ignore Generic.Files.LineLength
        return 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyBpZD0iTGF5ZXJfMSIgZGF0YS1uYW1lPSJMYXllciAxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDgwIDEwODAiPgogIDxkZWZzPgogICAgPHN0eWxlPgogICAgICAuY2xzLTEgewogICAgICAgIGZpbGw6ICNlNGUzZTY7CiAgICAgIH0KICAgIDwvc3R5bGU+CiAgPC9kZWZzPgogIDxwYXRoIGNsYXNzPSJjbHMtMSIgZD0iTTY3OC44Myw2NzAuOThoMGMzOC4wNS0yNS40MSw3NS42OC01NC45NywxMDkuNzMtODkuMDIsMjE4Ljc3LTIxOC43NywxNTkuMjYtNDQ5Ljc4LDE1OS4yNi00NDkuNzgsMCwwLTIzMS4wMi01OS41LTQ0OS43OCwxNTkuMjYtMzQuMDUsMzQuMDUtNjMuNjEsNzEuNjgtODkuMDIsMTA5LjczaDBzLTExMy4zNi0xNi4xMi0xODcuMDUsNTcuMThjLTU3LjMzLDU3LjAyLTk3LjE3LDE2Mi4yNS05Ny4xNywxNjIuMjUsMCwwLDEwNi4zOC0yNC4yNSwxNzcuMzYtNy43LTkuMTgsMjQuNjQtMTcuNTQsNDYuMjQtMjIuODMsNTkuNzctMy41Myw5LjA0LTEuMzgsMTkuMyw1LjQ4LDI2LjE2bDQ4LjE4LDQ4LjE4LDQ4LjE4LDQ4LjE4YzYuODYsNi44NiwxNy4xMyw5LjAyLDI2LjE2LDUuNDgsMTMuNTMtNS4yOSwzNS4xMi0xMy42NCw1OS43Ny0yMi44MywxNi41NSw3MC45OC03LjcsMTc3LjM2LTcuNywxNzcuMzYsMCwwLDEwNS4yMy0zOS44NCwxNjIuMjUtOTcuMTcsNzMuMy03My42OSw1Ny4xOS0xODcuMDUsNTcuMTktMTg3LjA1Wk02MzguMTUsNDQxLjg1Yy0zNC40NS0zNC40NS0zNC40NS05MC4zLDAtMTI0Ljc1LDM0LjQ1LTM0LjQ1LDkwLjMtMzQuNDUsMTI0Ljc1LDAsMzQuNDUsMzQuNDUsMzQuNDUsOTAuMywwLDEyNC43NS0zNC40NSwzNC40NS05MC4zLDM0LjQ1LTEyNC43NSwwWiIvPgogIDxwYXRoIGNsYXNzPSJjbHMtMSIgZD0iTTM0NS40Niw4MjIuMDNzLTQ3LjE1LDcxLjg4LTEyNy40NywzOS45N2MtMzEuOS04MC4zMiwzOS45Ny0xMjcuNDcsMzkuOTctMTI3LjQ3LTY5LjA5LDguNTctMTAxLjg3LDYyLjU0LTEwMS44MiwxMTguMTYuMDQsNDQuOTMtMTguODUsOTAtMTguODUsOTAsMCwwLDQ1LjA3LTE4Ljg5LDkwLTE4Ljg1LDU1LjYyLjA1LDEwOS41OS0zMi43MywxMTguMTYtMTAxLjgyWiIvPgo8L3N2Zz4=';
    }

    /**
     * Return whether the current page is the settings page.
     *
     * @return bool
     */
    private function is_settings_page(): bool {
        if ( ! is_admin() ) {
            return false;
        }

        $screen = get_current_screen();

        if ( empty( $screen ) ) {
            return false;
        }

        return str_ends_with( $screen->id, $this->get_menu_slug() );
    }

    /**
     * Whether page cache is detected and the server response time is good.
     *
     * Return value is memoized with a TTL of 1 day.
     *
     * @return bool
     */
    private function has_page_cache(): bool {
        try {
            $key = 'pressidium_has_page_cache';

            $has_cache = $this->transient_storage->get( $key, null );

            if ( is_null( $has_cache ) ) {
                // Transient does not exist or has expired, check if page cache is detected
                require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';

                $site_health  = new WP_Site_Health();
                $cache_result = $site_health->get_test_page_cache();

                $has_cache = array_key_exists( 'status', $cache_result ) && $cache_result['status'] === 'good';

                $this->transient_storage->set( $key, $has_cache, DAY_IN_SECONDS );
            }

            return (bool) $has_cache;
        } catch ( InvalidArgumentException $exception ) {
            // Something went wrong, default to `false`
            return false;
        }
    }

    /**
     * Enqueue scripts for the settings page.
     *
     * @return void
     */
    private function enqueue_scripts(): void {
        if ( ! $this->is_settings_page() ) {
            // Not the settings page, bail early
            return;
        }

        $assets_file = PLUGIN_DIR . 'public/bundle.admin.asset.php';

        if ( ! file_exists( $assets_file ) ) {
            // File doesn't exist, bail early
            return;
        }

        $assets = require $assets_file;

        $dependencies = $assets['dependencies'] ?? array();
        $version      = $assets['version'] ?? filemtime( $assets_file );

        wp_enqueue_style(
            'performance-admin-style',
            PLUGIN_URL . 'public/bundle.admin.css',
            array( 'wp-components' ),
            $version
        );

        wp_enqueue_script(
            'performance-admin-script',
            PLUGIN_URL . 'public/bundle.admin.js',
            $dependencies,
            $version,
            true
        );

        wp_localize_script(
            'performance-admin-script',
            'pressidiumPerfAdminDetails',
            array(
                'domain'         => WP_Utils::get_domain(),
                'assets'         => array(
                    'icon'  => esc_url( PLUGIN_URL . 'assets/images/icon.png' ),
                    'promo' => esc_url( PLUGIN_URL . 'assets/images/promo.png' ),
                ),
                'api'            => array(
                    'route'                   => 'pressidium-performance/v1/settings',
                    'logs_route'              => 'pressidium-performance/v1/logs',
                    'processes_route'         => 'pressidium-performance/v1/processes',
                    'image_convert_route'     => 'pressidium-performance/v1/optimization/image/convert',
                    'image_convert_all_route' => 'pressidium-performance/v1/optimization/image/convert-all',
                    'minifications_route'     => 'pressidium-performance/v1/optimization/minification/minifications',
                    'concatenations_route'    => 'pressidium-performance/v1/optimization/concatenation/concatenations',
                    'stats_route'             => 'pressidium-performance/v1/optimization/stats',
                    'nonce'                   => wp_create_nonce( 'pressidium_performance_rest' ),
                ),
                'has_page_cache' => $this->has_page_cache(),
            )
        );
    }

    /**
     * Enqueue styles for the installed plugins page.
     *
     * @param string $hook The current admin page.
     *
     * @return void
     */
    private function enqueue_styles( string $hook ): void {
        if ( $hook !== 'plugins.php' ) {
            // Not on the plugins page, bail early
            return;
        }

        wp_enqueue_style(
            'pressidium-performance-installed-plugins',
            PLUGIN_URL . 'assets/css/admin-styles.css',
            array(), // no dependencies
            VERSION
        );
    }

    /**
     * Enqueue any scripts and styles needed for the admin pages.
     *
     * @param string $hook The current admin page.
     *
     * @return void
     */
    public function admin_enqueue_scripts_and_styles( string $hook ): void {
        $this->enqueue_scripts();
        $this->enqueue_styles( $hook );
    }

    /**
     * Add information about this plugin to the left side of the admin footer.
     *
     * @param string|null $content The existing content.
     *
     * @return string|null The modified content including the plugin information.
     */
    public function admin_footer_info( ?string $content ): ?string {
        if ( ! $this->is_settings_page() ) {
            // Not the settings page, bail early
            return $content;
        }

        return sprintf(
            '<span id="pressidium-cc-footer">%s</span>',
            sprintf(
                /* translators: 1: Developer name, 2: Link to the docs. */
                __( 'Developed by %1$s | Curious to learn more? Head over to the %2$s.', 'pressidium-performance' ),
                sprintf(
                    '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
                    esc_url( 'https://pressidium.com/' ),
                    esc_html( 'Pressidium®' )
                ),
                sprintf(
                    '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
                    esc_url( 'https://github.com/pressidium/pressidium-performance/wiki' ),
                    __( 'docs', 'pressidium-performance' )
                )
            )
        );
    }

    /**
     * Add plugin version to the right side of the admin footer.
     *
     * @param string|null $content The existing content.
     *
     * @return string|null The modified content including the plugin version.
     */
    public function admin_footer_version( ?string $content ): ?string {
        if ( ! $this->is_settings_page() ) {
            // Not the settings page, bail early
            return $content;
        }

        return sprintf(
            /* translators: Plugin version. */
            __( 'Version %s', 'pressidium-performance' ),
            esc_html( VERSION )
        );
    }

    /**
     * Filter the action links displayed for this plugin to add links to its settings page and documentation.
     *
     * @link https://developer.wordpress.org/reference/hooks/plugin_action_links/
     *
     * @param string[] $actions     An array of plugin action links.
     * @param string   $plugin_file Path to the plugin file relative to the `plugins` directory.
     *
     * @return string[] Plugin action links including the settings and documentation links.
     */
    public function add_plugin_links( array $actions, string $plugin_file ): array {
        if ( plugin_basename( PLUGIN_FILE ) !== $plugin_file ) {
            return $actions;
        }

        $settings_page_url = add_query_arg(
            array( 'page' => $this->get_menu_slug() ),
            admin_url( 'admin.php' )
        );

        $documentation_url = 'https://github.com/pressidium/pressidium-performance/wiki';

        $actions['settings'] = sprintf(
            '<a href="%1$s" rel="noopener noreferrer" class="pressidium-action-link">%2$s</a>',
            esc_url( $settings_page_url ),
            esc_html__( 'Settings', 'pressidium-performance' ),
        );

        $actions['documentation'] = sprintf(
            '<a href="%1$s" target="_blank" rel="noopener noreferrer" class="pressidium-action-link">%2$s<span class="screen-reader-text">%3$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a>',
            esc_url( $documentation_url ),
            esc_html__( 'Docs', 'pressidium-performance' ),
            /* translators: Accessibility text. */
            esc_html__( '(opens in a new tab)', 'pressidium-performance' )
        );

        return $actions;
    }

    /**
     * Return the actions to register.
     *
     * @return array<string, array{0: string, 1?: int, 2?: int}>
     */
    public function get_actions(): array {
        $actions = parent::get_actions();

        $actions['admin_enqueue_scripts'] = array( 'admin_enqueue_scripts_and_styles' );

        return $actions;
    }

    /**
     * Return the filters to register.
     *
     * @return array
     */
    public function get_filters(): array {
        return array(
            'admin_footer_text'   => array( 'admin_footer_info' ),
            'update_footer'       => array( 'admin_footer_version', 11 ),
            'plugin_action_links' => array( 'add_plugin_links', 10, 2 ),
        );
    }

}
