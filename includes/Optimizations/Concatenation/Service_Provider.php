<?php
/**
 * Concatenation service provider.
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation;

use Pressidium\WP\Performance\Optimizations\Concatenation\JS\Processor as JS_Concatenation_Processor;
use Pressidium\WP\Performance\Optimizations\Concatenation\JS\Concatenator as JS_Concatenator;
use Pressidium\WP\Performance\Optimizations\Minification\JS\Minifier as JS_Minifier;

use Pressidium\WP\Performance\Optimizations\Concatenation\CSS\Processor as CSS_Concatenation_Processor;
use Pressidium\WP\Performance\Optimizations\Concatenation\CSS\Concatenator as CSS_Concatenator;
use Pressidium\WP\Performance\Optimizations\Minification\CSS\Minifier as CSS_Minifier;

use Pressidium\WP\Performance\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;

use Pressidium\WP\Performance\Dependencies\Psr\Container\ContainerExceptionInterface;
use Pressidium\WP\Performance\Dependencies\Psr\Container\NotFoundExceptionInterface;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

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
        'js_concatenation_processor',
        'css_concatenation_processor',
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
        $this->getContainer()->add( 'js_concatenation_processor', JS_Concatenation_Processor::class )
             ->addArgument( 'options' )
             ->addArgument( 'logger' )
             ->addArgument( 'file_reader' )
             ->addArgument( 'file_writer' )
             ->addArgument( 'file_minification_evaluator' )
             ->addArgument( 'url_builder' )
             ->addArgument( 'filesystem' )
             ->addArgument( new JS_Concatenator(
                 $this->getContainer()->get( 'logger' ),
                 $this->getContainer()->get( 'filesystem' ),
                 $this->getContainer()->get( 'concatenations_table' ),
                 $this->getContainer()->get( 'settings' )
             ) )
             ->addArgument( 'concatenations_pages_table' )
             ->addArgument( 'concatenations_table' )
             ->addArgument( 'sri_validator' )
             ->addArgument( new JS_Minifier(
                 $this->getContainer()->get( 'logger' ),
                 $this->getContainer()->get( 'file_minification_evaluator' ),
             ) )
             ->addArgument( 'settings' );

        $this->getContainer()->add( 'css_concatenation_processor', CSS_Concatenation_Processor::class )
             ->addArgument( 'options' )
             ->addArgument( 'logger' )
             ->addArgument( 'file_reader' )
             ->addArgument( 'file_writer' )
             ->addArgument( 'file_minification_evaluator' )
             ->addArgument( 'url_builder' )
             ->addArgument( 'filesystem' )
             ->addArgument( new CSS_Concatenator(
                 $this->getContainer()->get( 'logger' ),
                 $this->getContainer()->get( 'filesystem' ),
                 $this->getContainer()->get( 'concatenations_table' ),
                 $this->getContainer()->get( 'settings' )
             ) )
             ->addArgument( 'concatenations_pages_table' )
             ->addArgument( 'concatenations_table' )
             ->addArgument( 'sri_validator' )
             ->addArgument( new CSS_Minifier(
                 $this->getContainer()->get( 'logger' ),
                 $this->getContainer()->get( 'file_minification_evaluator' ),
             ) )
             ->addArgument( 'settings' );
    }

}
