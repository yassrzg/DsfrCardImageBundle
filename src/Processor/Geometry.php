<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Processor;

use Yassrzg\DsfrCardImageBundle\Profile\ImageProfile;
use Yassrzg\DsfrCardImageBundle\Profile\ResizeMode;

/**
 * Zone de l'image source à reprendre et taille de l'image produite, pour un profil donné (calcul pur, sans GD).
 *
 * cover : zone centrée aux proportions du cadre, puis réduite ; contain : image entière réduite dans le cadre.
 * Jamais d'agrandissement.
 */
final readonly class Geometry
{
    /**
     * @param positive-int $width  largeur de l'image produite
     * @param positive-int $height hauteur de l'image produite
     */
    private function __construct(
        public int $sourceX,
        public int $sourceY,
        public int $sourceWidth,
        public int $sourceHeight,
        public int $width,
        public int $height,
    ) {
    }

    public static function compute(int $width, int $height, ImageProfile $profile): self
    {
        if ($width < 1 || $height < 1) {
            throw new \InvalidArgumentException('Dimensions de l\'image source invalides.');
        }
        $maxWidth = max(1, $profile->width);

        if (ResizeMode::CONTAIN === $profile->mode) {
            $scale = min(1, $profile->width / $width, $profile->height / $height);

            return new self(0, 0, $width, $height, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        }

        $ratio = $profile->width / $profile->height;
        [$cropWidth, $cropHeight] = $width / $height > $ratio
            ? [max(1, (int) round($height * $ratio)), $height]
            : [$width, max(1, (int) round($width / $ratio))];
        // Taille tirée de la largeur seule : proportions exactes du cadre (pas de 799 × 450 par arrondi).
        $targetWidth = min($maxWidth, $cropWidth);
        $targetHeight = max(1, (int) round($targetWidth / $ratio));

        return new self(intdiv($width - $cropWidth, 2), intdiv($height - $cropHeight, 2), $cropWidth, $cropHeight, $targetWidth, $targetHeight);
    }
}
