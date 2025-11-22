<?php
/**
 * Files service provider.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Files;

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
        'file_reader',
        'file_writer',
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
        $this->getContainer()->add( 'file_reader', File_Reader::class )
             ->addArgument( $this->getContainer()->get( 'logger' ) )
             ->addArgument( $this->getContainer()->get( 'filesystem' ) );

        $this->getContainer()->add( 'file_writer', File_Writer::class )
             ->addArgument( $this->getContainer()->get( 'filesystem' ) );
    }

}
