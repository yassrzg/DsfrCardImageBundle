<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Url;

use Yassrzg\DsfrCardImageBundle\Exception\UnknownProfileException;
use Yassrzg\DsfrCardImageBundle\Exception\UnresolvableImageException;
use Yassrzg\DsfrCardImageBundle\Profile\ProfileRegistry;

/**
 * Adresse d'une image pour un profil, par le premier résolveur qui la prend en charge (Twig, contrôleurs, API).
 */
final readonly class ImageUrlGenerator
{
    /**
     * @param iterable<ImageUrlResolverInterface> $resolvers par priorité décroissante
     */
    public function __construct(
        private ProfileRegistry $profiles,
        private iterable $resolvers,
    ) {
    }

    /**
     * @throws UnknownProfileException    profil absent de la configuration (erreur détectée dès l'affichage de la page)
     * @throws UnresolvableImageException aucun résolveur pour cette image
     */
    public function generate(mixed $image, string $profile): string
    {
        $this->profiles->get($profile);
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($image)) {
                return $resolver->url($image, $profile);
            }
        }

        throw new UnresolvableImageException(\sprintf('Aucun résolveur d\'adresse pour une image de type %s (voir ImageUrlResolverInterface).', get_debug_type($image)));
    }
}
