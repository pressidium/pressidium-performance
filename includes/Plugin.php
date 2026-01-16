<?php
/**
 * Plugin.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance;

use Pressidium\WP\Performance\Files\Service_Provider as Files_Service_Provider;
use Pressidium\WP\Performance\Admin\Settings\Service_Provider as Settings_Service_Provider;
use Pressidium\WP\Performance\Optimizations\Minification\Service_Provider as Minify_Service_Provider;
use Pressidium\WP\Performance\Optimizations\Concatenation\Service_Provider as Concatenation_Service_Provider;
use Pressidium\WP\Performance\Optimizations\Image\Service_Provider as Image_Optimizations_Service_Provider;
use Pressidium\WP\Performance\Feedback\Service_Provider as Feedback_Service_Provider;
use Pressidium\WP\Performance\Cron\Service_Provider as Cron_Service_Provider;
use Pressidium\WP\Performance\API\Service_Provider as API_Service_Provider;
use Pressidium\WP\Performance\Database\Service_Provider as Database_Service_Provider;

use Pressidium\WP\Performance\Hooks\Hooks_Manager;
use Pressidium\WP\Performance\Logging\Logger;
use Pressidium\WP\Performance\Logging\File_Logger;
use Pressidium\WP\Performance\Options\WP_Options;
use Pressidium\WP\Performance\Files\Filesystem;
use Pressidium\WP\Performance\Storage\Transient;

use Pressidium\WP\Performance\Optimizations\Image\Converters\Webp_Converter;
use Pressidium\WP\Performance\Optimizations\Image\Converters\Avif_Converter;
use Pressidium\WP\Performance\Optimizations\Image\Converters\Converter_Manager;
use Pressidium\WP\Performance\Optimizations\Image\Image_Attachment_Factory;
use Pressidium\WP\Performance\Optimizations\Image\Image_Factory;

use Pressidium\WP\Performance\Database\Database_Manager;
use Pressidium\WP\Performance\Cron\Cron_Manager;

use Pressidium\WP\Performance\Dependencies\League\Container\Container;

use Pressidium\WP\Performance\Dependencies\Psr\Container\ContainerExceptionInterface;
use Pressidium\WP\Performance\Dependencies\Psr\Container\NotFoundExceptionInterface;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * Plugin class.
 *
 * @since 1.0.0
 */
final class Plugin {

    /**
     * @var Logger Logger instance.
     */
    private Logger $logger;

    /**
     * @var bool Whether the plugin was just activated.
     */
    private bool $just_activated = false;

    /**
     * Mark the plugin as activated.
     *
     * @return void
     */
    public function mark_as_activated(): void {
        $this->just_activated = true;
    }

    /**
     * Add service providers to the container.
     *
     * @param Container $container Dependency injection container.
     *
     * @return void
     */
    private function add_service_providers( Container $container ): void {
        try {
            $container->addServiceProvider( Feedback_Service_Provider::class );
            $container->addServiceProvider( Files_Service_Provider::class );
            $container->addServiceProvider( Settings_Service_Provider::class );
            $container->addServiceProvider( Cron_Service_Provider::class );
            $container->addServiceProvider( Database_Service_Provider::class );
            $container->addServiceProvider( Minify_Service_Provider::class );
            $container->addServiceProvider( Concatenation_Service_Provider::class );
            $container->addServiceProvider( Image_Optimizations_Service_Provider::class );
            $container->addServiceProvider( API_Service_Provider::class );
        } catch ( ContainerExceptionInterface | NotFoundExceptionInterface ) {
            $this->logger->error( 'Could not add service providers' );
        }
    }

    /**
     * Register hooks with the `Hooks_Manager`.
     *
     * @param Hooks_Manager $hooks_manager Hooks manager.
     * @param Container     $container     Dependency injection container.
     *
     * @return void
     */
    private function register_hooks( Hooks_Manager $hooks_manager, Container $container ): void {
        try {
            $hooks_manager->register( $container->get( 'settings_api' ) );
            $hooks_manager->register( $container->get( 'settings_page' ) );
            $hooks_manager->register( $container->get( 'processor_manager' ) );
            $hooks_manager->register( $container->get( 'js_minification_processor' ) );
            $hooks_manager->register( $container->get( 'logs_api' ) );
            $hooks_manager->register( $container->get( 'background_processes_api' ) );
            $hooks_manager->register( $container->get( 'optimization_api' ) );
            $hooks_manager->register( $container->get( 'image_optimization_manager' ) );
            $hooks_manager->register( $container->get( 'original_files_deletion_manager' ) );
            $hooks_manager->register( $container->get( 'feedback' ) );

        } catch ( ContainerExceptionInterface | NotFoundExceptionInterface $exception ) {
            $this->logger->error(
                sprintf( 'Could not register hooks: %s', esc_html( $exception->getMessage() ) )
            );
        }
    }

    /**
     * Register database tables with the `Database_Manager`.
     *
     * @param Database_Manager $database_manager Database manager.
     * @param Container        $container        Dependency injection container.
     *
     * @return void
     */
    private function register_tables( Database_Manager $database_manager, Container $container ): void {
        try {
            $database_manager->register_table( $container->get( 'optimizations_table' ) );
            $database_manager->register_table( $container->get( 'concatenations_table' ) );
            $database_manager->register_table( $container->get( 'concatenations_pages_table' ) );
        } catch ( ContainerExceptionInterface | NotFoundExceptionInterface $exception ) {
            $this->logger->error(
                sprintf( 'Could not register tables: %s', esc_html( $exception->getMessage() ) )
            );
        }

        if ( $this->just_activated ) {
            $database_manager->create_tables();
            return;
        }

        $database_manager->maybe_upgrade_tables();
    }

    /**
     * Register cron jobs with the `Cron_Manager`.
     *
     * @param Cron_Manager $cron_manager Cron manager.
     * @param Container    $container    Dependency injection container.
     *
     * @return void
     */
    private function register_cron_jobs( Cron_Manager $cron_manager, Container $container ): void {
        try {
            $cron_manager->register_cron_job( $container->get( 'clean_up_cron_job' ) );
        } catch ( ContainerExceptionInterface | NotFoundExceptionInterface $exception ) {
            $this->logger->error(
                sprintf( 'Could not register cron jobs: %s', esc_html( $exception->getMessage() ) )
            );
        }

        if ( $this->just_activated ) {
            $cron_manager->schedule_events();
        }
    }

    /**
     * Register processors with the `Processor_Manager`.
     *
     * @param Processor_Manager $processor_manager Processor manager to register processors with.
     * @param Container         $container         Dependency injection container.
     * @param Settings          $settings_object   Settings instance.
     *
     * @return void
     */
    private function register_processors(
        Processor_Manager $processor_manager,
        Container $container,
        Settings $settings_object
    ): void {
        $settings = $settings_object->get();

        try {
            if ( $settings['minification']['minifyJS'] ) {
                $processor_manager->register_processor( $container->get( 'js_minification_processor' ) );
            }

            if ( $settings['minification']['minifyCSS'] ) {
                $processor_manager->register_processor( $container->get( 'css_minification_processor' ) );
            }

            if ( $settings['concatenation']['concatenateJS'] ) {
                $processor_manager->register_processor( $container->get( 'js_concatenation_processor' ) );
            }

            if ( $settings['concatenation']['concatenateCSS'] ) {
                $processor_manager->register_processor( $container->get( 'css_concatenation_processor' ) );
            }
        } catch ( ContainerExceptionInterface | NotFoundExceptionInterface $exception ) {
            $this->logger->error(
                sprintf( 'Could not register tag processors: %s', esc_html( $exception->getMessage() ) )
            );
        }
    }

    /**
     * Initialize the plugin.
     *
     * @return void
     */
    public function init(): void {
        define( __NAMESPACE__ . '\NS', __NAMESPACE__ . '\\' );

        $container = new Container();

        $hooks_manager = new Hooks_Manager();
        $container->add( 'hooks_manager', $hooks_manager );

        $filesystem = new Filesystem();
        $container->add( 'filesystem', $filesystem );

        $url_builder = new URL_Builder();
        $container->add( 'url_builder', $url_builder );

        $this->logger = new File_Logger( $filesystem );
        $container->add( 'logger', $this->logger );

        $logs = new Logs( $this->logger );
        $container->add( 'logs', $logs );

        $options = new WP_Options();
        $container->add( 'options', $options );

        $transient = new Transient();
        $container->add( 'transient', $transient );

        $settings = new Settings( $options );
        $container->add( 'settings', $settings );

        $image_factory = new Image_Factory( $settings, $filesystem );
        $container->add( 'image_factory', $image_factory );

        $attachment_factory = new Image_Attachment_Factory( $image_factory );
        $container->add( 'image_attachment_factory', $attachment_factory );

        $converter_manager = new Converter_Manager(
            $settings,
            array(
                Webp_Converter::MIME_TYPE => new Webp_Converter( $image_factory ),
                Avif_Converter::MIME_TYPE => new Avif_Converter( $image_factory ),
            )
        );
        $container->add( 'converter_manager', $converter_manager );

        $database_manager = new Database_Manager( $options, $this->logger );
        $container->add( 'database_manager', $database_manager );

        $cron_manager = new Cron_Manager();
        $container->add( 'cron_manager', $cron_manager );

        $sri_validator = new SRI_Validator( $this->logger );
        $container->add( 'sri_validator', $sri_validator );

        $processor_manager = new Processor_Manager( $this->logger );
        $container->add( 'processor_manager', $processor_manager );

        $this->add_service_providers( $container );
        $this->register_processors( $processor_manager, $container, $settings );
        $this->register_tables( $database_manager, $container );
        $this->register_cron_jobs( $cron_manager, $container );
        $this->register_hooks( $hooks_manager, $container );
    }

}
