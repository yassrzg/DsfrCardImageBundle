<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Tests\App;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Yassrzg\DsfrCardImageBundle\DsfrCardImageBundle;
use Yassrzg\DsfrCardImageBundle\Tests\App\Src\DocumentUrlResolver;

/**
 * Application minimale pour les tests : FrameworkBundle, TwigBundle et le bundle, avec un résolveur d'adresse propre
 * à l'application (DocumentUrlResolver) et la route du bundle sous /card-image.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new DsfrCardImageBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return \dirname(__DIR__, 2).'/var/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return \dirname(__DIR__, 2).'/var/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'router' => ['utf8' => true],
        ]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates']);
        $container->extension('dsfr_card_image', [
            'cache_directory' => '%kernel.project_dir%/../../var/card_images',
            'profiles' => [
                'card' => ['width' => 800, 'height' => 450],
                'card_contain' => ['width' => 800, 'height' => 450, 'mode' => 'contain'],
                'square' => ['width' => 200, 'height' => 200, 'format' => 'png', 'quality' => 90],
            ],
        ]);
        $container->services()
            ->set('logger', \Psr\Log\NullLogger::class)
            ->set(DocumentUrlResolver::class)->autowire()->autoconfigure();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@DsfrCardImageBundle/config/routes.php')->prefix('/card-image');
        $routes->add('document_image', '/document/{id}/{profile}');
    }
}
