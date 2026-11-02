<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Yassrzg\DsfrCardImageBundle\Url\ImageUrlGenerator;

/**
 * card_image_url(image, profil) : adresse de l'image adaptée au profil, à placer dans l'attribut src. Le balisage
 * (carte DSFR, classes, dimensions) reste celui de l'application.
 */
final class CardImageExtension extends AbstractExtension
{
    public function __construct(private readonly ImageUrlGenerator $urls)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('card_image_url', $this->urls->generate(...))];
    }
}
