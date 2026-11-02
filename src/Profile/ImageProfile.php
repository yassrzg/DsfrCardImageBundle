<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Profile;

use Yassrzg\DsfrCardImageBundle\Exception\InvalidProfileException;

/**
 * Profil de traitement : cadre cible (largeur × hauteur en pixels), mode d'adaptation, format et qualité de sortie.
 * Une image n'est jamais agrandie : une source plus petite que le cadre garde sa taille (pas d'image floue).
 */
final readonly class ImageProfile
{
    public function __construct(
        public string $name,
        public int $width,
        public int $height,
        public ResizeMode $mode = ResizeMode::COVER,
        public OutputFormat $format = OutputFormat::AUTO,
        public int $quality = 82,
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name)) {
            throw new InvalidProfileException(\sprintf('Nom de profil « %s » invalide : lettres minuscules, chiffres, « _ » et « - » seulement.', $name));
        }
        if ($width < 1 || $height < 1) {
            throw new InvalidProfileException(\sprintf('Profil « %s » : largeur et hauteur doivent être positives.', $name));
        }
        if ($quality < 1 || $quality > 100) {
            throw new InvalidProfileException(\sprintf('Profil « %s » : la qualité doit être comprise entre 1 et 100.', $name));
        }
    }

    /**
     * Empreinte des réglages qui changent l'image produite : un réglage modifié donne de nouvelles images dérivées.
     */
    public function fingerprint(): string
    {
        return implode('|', [$this->width, $this->height, $this->mode->value, $this->format->value, $this->quality]);
    }
}
