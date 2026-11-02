<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Url;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Yassrzg\DsfrCardImageBundle\Controller\ImageController;

/**
 * Chemin relatif au dossier source du bundle (ex. « images/equipe.jpg ») : adresse de la route du bundle
 * (config/routes.php, à importer dans l'application).
 */
final readonly class PathUrlResolver implements ImageUrlResolverInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function supports(mixed $image): bool
    {
        return \is_string($image) && '' !== $image;
    }

    public function url(mixed $image, string $profile): string
    {
        \assert(\is_string($image));

        return $this->urlGenerator->generate(ImageController::ROUTE, ['profile' => $profile, 'path' => ltrim($image, '/')]);
    }
}
