<?php
/**
 * API service provider.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\API;

use Pressidium\WP\Performance\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;

use Pressidium\WP\Performance\Dependencies\Psr\Container\ContainerExceptionInterface;
use Pressidium\WP\Performance\Dependencies\Psr\Container\NotFoundExceptionInterface;

/**
 * Service_Provider class.
 *
 * @since 1.0.0
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
        'logs_api',
        'optimization_api',
        'settings_api',
        'background_processes_api',
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
        $this->getContainer()->add( 'logs_api', Logs_API::class )
             ->addArgument( $this->getContainer()->get( 'logger' ) )
             ->addArgument( $this->getContainer()->get( 'logs' ) );

        $this->getContainer()->add( 'optimization_api', Optimization_API::class )
             ->addArgument( $this->getContainer()->get( 'logger' ) )
             ->addArgument( $this->getContainer()->get( 'settings' ) )
             ->addArgument( $this->getContainer()->get( 'image_factory' ) )
             ->addArgument( $this->getContainer()->get( 'media_library' ) )
             ->addArgument( $this->getContainer()->get( 'image_optimization_manager' ) )
             ->addArgument( $this->getContainer()->get( 'converter_manager' ) )
             ->addArgument( $this->getContainer()->get( 'optimizations_table' ) )
             ->addArgument( $this->getContainer()->get( 'concatenations_table' ) )
             ->addArgument( $this->getContainer()->get( 'transient' ) );

        $this->getContainer()->add( 'settings_api', Settings_API::class )
             ->addArgument( $this->getContainer()->get( 'logger' ) )
             ->addArgument( $this->getContainer()->get( 'settings' ) );

        $this->getContainer()->add( 'background_processes_api', Background_Processes_API::class )
             ->addArgument( $this->getContainer()->get( 'logger' ) )
             ->addArgument( $this->getContainer()->get( 'processor_manager' ) )
             ->addArgument( $this->getContainer()->get( 'image_optimization_manager' ) );
    }

}
