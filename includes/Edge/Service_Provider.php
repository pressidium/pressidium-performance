<?php
/**
 * Pressidium EDGE service provider.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2026 Pressidium
 */

namespace Pressidium\WP\Performance\Edge;

use Pressidium\WP\Performance\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;

use Pressidium\WP\Performance\Dependencies\Psr\Container\ContainerExceptionInterface;
use Pressidium\WP\Performance\Dependencies\Psr\Container\NotFoundExceptionInterface;

use Pressidium\WP\Performance\Edge\API\Edge_API_Client;
use Pressidium\WP\Performance\Edge\Caching\Cache_Tag_Factory;
use Pressidium\WP\Performance\Edge\Caching\Caching_Controller;
use Pressidium\WP\Performance\Edge\Caching\Posts_Caching;
use Pressidium\WP\Performance\Edge\Collector\Collector_Change_Tracker;
use Pressidium\WP\Performance\Edge\Collector\Collector_Manager;
use Pressidium\WP\Performance\Edge\Collector\Edge_Collector_Process;
use Pressidium\WP\Performance\Edge\Collector\Collectors\WP_Core\WP_Core_Collector;
use Pressidium\WP\Performance\Edge\Collector\Collectors\PHP\PHP_Collector;
use Pressidium\WP\Performance\Edge\Collector\Collectors\Database\Database_Collector;
use Pressidium\WP\Performance\Edge\Collector\Collectors\Plugin\Plugin_Collector;
use Pressidium\WP\Performance\Edge\Collector\Collectors\Theme\Theme_Collector;
use Pressidium\WP\Performance\Edge\Collector\Collectors\MU_Plugin\MU_Plugin_Collector;

/**
 * Service_Provider class.
 *
 * @since 1.1.0
 */
final class Service_Provider extends AbstractServiceProvider {

    /**
     * The provided array is a way to let the container
     * know that a service is provided by this service
     * provider. Every service that is registered via
     * this service provider must have an alias added
     * to this array, or it will be ignored.
     *
     * @var string[]
     */
    protected $provides = array(
        'edge_api_client',
        'web_property',
        'cache_tag_factory',
        'posts_caching',
        'caching_controller',
        'collector_manager',
        'edge_collector_process',
        'collector_change_tracker',
        'wp_core_collector',
        'php_collector',
        'database_collector',
        'plugin_collector',
        'theme_collector',
        'mu_plugin_collector',
    );

    /**
     * Access the container and register or retrieve anything that you need to.
     *
     * Remember, every alias registered within this method
     * must be declared in the `$provides` array.
     *
     * @throws NotFoundExceptionInterface  No entry was found in the container.
     * @throws ContainerExceptionInterface Something went wrong with the container.
     *
     * @return void
     */
    public function register(): void {
        $this->getContainer()->add( 'web_property', Web_Property::class )
             ->addArgument( $this->getContainer()->get( 'options' ) );

        $this->getContainer()->add( 'edge_api_client', Edge_API_Client::class )
             ->addArgument( $this->getContainer()->get( 'logger' ) )
             ->addArgument( $this->getContainer()->get( 'encrypted_options' ) )
             ->addArgument( $this->getContainer()->get( 'web_property' ) );

        $this->getContainer()->add( 'cache_tag_factory', Cache_Tag_Factory::class )
             ->addArgument( $this->getContainer()->get( 'web_property' ) );

        $this->getContainer()->share( 'posts_caching', Posts_Caching::class )
             ->addArgument( $this->getContainer()->get( 'cache_tag_factory' ) );

        $this->getContainer()->add( 'caching_controller', Caching_Controller::class )
             ->addArgument( $this->getContainer()->get( 'logger' ) )
             ->addArgument( $this->getContainer()->get( 'posts_caching' ) )
             ->addArgument( $this->getContainer()->get( 'edge_api_client' ) )
             ->addArgument( $this->getContainer()->get( 'cache_tag_factory' ) )
             ->addArgument( $this->getContainer()->get( 'web_property' ) );

        $this->getContainer()->share( 'collector_manager', Collector_Manager::class )
             ->addArgument( $this->getContainer()->get( 'logger' ) )
             ->addArgument( $this->getContainer()->get( 'edge_api_client' ) )
             ->addArgument( $this->getContainer()->get( 'web_property' ) )
             ->addArgument( $this->getContainer()->get( 'collector_state_table' ) );

        $this->getContainer()->share( 'edge_collector_process', Edge_Collector_Process::class )
             ->addArgument( $this->getContainer()->get( 'logger' ) )
             ->addArgument( $this->getContainer()->get( 'collector_manager' ) );

        $this->getContainer()->add( 'collector_change_tracker', Collector_Change_Tracker::class )
             ->addArgument( $this->getContainer()->get( 'logger' ) )
             ->addArgument( $this->getContainer()->get( 'web_property' ) )
             ->addArgument( $this->getContainer()->get( 'edge_collector_process' ) );

        $this->getContainer()->add( 'wp_core_collector', WP_Core_Collector::class );

        $this->getContainer()->add( 'php_collector', PHP_Collector::class );

        $this->getContainer()->add( 'database_collector', Database_Collector::class );

        $this->getContainer()->add( 'plugin_collector', Plugin_Collector::class );

        $this->getContainer()->add( 'theme_collector', Theme_Collector::class );

        $this->getContainer()->add( 'mu_plugin_collector', MU_Plugin_Collector::class );
    }

}
