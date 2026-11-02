<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Tests\App\Src;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Yassrzg\DsfrCardImageBundle\Url\ImageUrlResolverInterface;

/** Résolveur propre à l'application : ses objets gardent leur route (et ses règles d'accès). */
final readonly class DocumentUrlResolver implements ImageUrlResolverInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function supports(mixed $image): bool
    {
        return $image instanceof Document;
    }

    public function url(mixed $image, string $profile): string
    {
        \assert($image instanceof Document);

        return $this->urlGenerator->generate('document_image', ['id' => $image->id, 'profile' => $profile]);
    }
}
