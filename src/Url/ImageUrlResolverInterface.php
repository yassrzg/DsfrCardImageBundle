<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Url;

/**
 * Adresse d'une image adaptée à un profil. L'application déclare un résolveur pour ses propres objets (entité de
 * médiathèque, pièce jointe…) afin de garder ses routes et ses règles d'accès ; le bundle fournit celui des chemins
 * relatifs au dossier source (PathUrlResolver). Le premier résolveur qui prend en charge l'image est utilisé.
 * Un service autoconfiguré qui implémente l'interface est pris en compte (étiquette TAG, option « priority »).
 */
interface ImageUrlResolverInterface
{
    public const string TAG = 'dsfr_card_image.url_resolver';

    public function supports(mixed $image): bool;

    public function url(mixed $image, string $profile): string;
}
