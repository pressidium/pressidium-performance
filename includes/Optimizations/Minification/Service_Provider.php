<?php
/**
 * Minifier service provider.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Minification;

use Pressidium\WP\Performance\Optimizations\Minification\JS\Processor as JS_Minification_Processor;
use Pressidium\WP\Performance\Optimizations\Minification\JS\Minifier as JS_Minifier;

use Pressidium\WP\Performance\Optimizations\Minification\CSS\Processor as CSS_Minification_Processor;
use Pressidium\WP\Performance\Optimizations\Minification\CSS\Minifier as CSS_Minifier;

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
     * @var array
     */
    protected $provides = array(
        'file_minification_evaluator',
        'js_minification_processor',
        'css_minification_processor',
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
        $this->getContainer()->add( 'file_minification_evaluator', File_Minification_Evaluator::class )
             ->addArgument( 'logger' )
             ->addArgument( 'optimizations_table' )
             ->addArgument( 'settings' );

        $this->getContainer()->add( 'js_minification_processor', JS_Minification_Processor::class )
             ->addArgument( 'options' )
             ->addArgument( 'logger' )
             ->addArgument( 'file_reader' )
             ->addArgument( 'file_writer' )
             ->addArgument( 'file_minification_evaluator' )
             ->addArgument( 'url_builder' )
             ->addArgument(
                 new JS_Minifier(
                     $this->getContainer()->get( 'logger' ),
                     $this->getContainer()->get( 'file_minification_evaluator' ),
                 )
             )
             ->addArgument( 'optimizations_table' );

        $this->getContainer()->add( 'css_minification_processor', CSS_Minification_Processor::class )
             ->addArgument( 'options' )
             ->addArgument( 'logger' )
             ->addArgument( 'file_reader' )
             ->addArgument( 'file_writer' )
             ->addArgument( 'file_minification_evaluator' )
             ->addArgument( 'url_builder' )
             ->addArgument(
                 new CSS_Minifier(
                     $this->getContainer()->get( 'logger' ),
                     $this->getContainer()->get( 'file_minification_evaluator' ),
                 )
             )
             ->addArgument( 'optimizations_table' );
    }

}
