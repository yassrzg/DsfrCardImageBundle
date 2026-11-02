<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Yassrzg\DsfrCardImageBundle\Controller\ImageController;

/*
 * Route des images du dossier source (option source_directory), à importer avec un préfixe :
 *
 *     dsfr_card_image:
 *         resource: '@DsfrCardImageBundle/config/routes.php'
 *         prefix: /card-image
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(ImageController::ROUTE, '/{profile}/{path}')
        ->controller(ImageController::class)
        ->requirements(['profile' => '[a-z0-9][a-z0-9_-]*', 'path' => '.+'])
        ->methods(['GET', 'HEAD']);
};
