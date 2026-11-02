<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Tests;

/**
 * Images de test créées avec GD (dimensions, formats et transparence choisis par chaque test).
 */
final class ImageFactory
{
    /**
     * Image d'une couleur unie ; $transparent : fond entièrement transparent (PNG, GIF, WebP).
     *
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function create(string $path, int $width, int $height, string $type = 'png', bool $transparent = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        if ($transparent) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        } else {
            imagefill($image, 0, 0, (int) imagecolorallocate($image, 0, 0, 145));
        }
        $written = match ($type) {
            'jpeg' => imagejpeg($image, $path, 90),
            'gif' => imagegif($image, $path),
            'webp' => imagewebp($image, $path, 90),
            default => imagepng($image, $path),
        };
        if (!$written) {
            throw new \RuntimeException('Image de test non créée : '.$path);
        }

        return $path;
    }

    /**
     * Photo JPEG avec la balise EXIF Orientation (6 : à tourner de 90° dans le sens horaire pour l'afficher).
     *
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function jpegWithOrientation(string $path, int $width, int $height, int $orientation): string
    {
        self::create($path, $width, $height, 'jpeg');
        $jpeg = (string) file_get_contents($path);
        // TIFF little-endian, une entrée IFD0 : Orientation (0x0112), SHORT, 1 valeur.
        $tiff = 'II*'."\0".pack('V', 8).pack('v', 1).pack('vvVvv', 0x0112, 3, 1, $orientation, 0).pack('V', 0);
        $exif = 'Exif'."\0\0".$tiff;
        $segment = "\xFF\xE1".pack('n', \strlen($exif) + 2).$exif;
        file_put_contents($path, substr($jpeg, 0, 2).$segment.substr($jpeg, 2));

        return $path;
    }

    /** @return array{0: int, 1: int} */
    public static function size(string $path): array
    {
        $info = getimagesize($path);
        if (false === $info) {
            throw new \RuntimeException('Image illisible : '.$path);
        }

        return [$info[0], $info[1]];
    }

    /** Opacité du pixel (0 : opaque, 127 : transparent). */
    public static function alphaAt(string $path, int $x, int $y): int
    {
        $image = imagecreatefromstring((string) file_get_contents($path));
        if (false === $image) {
            throw new \RuntimeException('Image illisible : '.$path);
        }

        return (imagecolorat($image, $x, $y) >> 24) & 0x7F;
    }

    /** @return array{0: int, 1: int, 2: int} rouge, vert, bleu du pixel */
    public static function rgbAt(string $path, int $x, int $y): array
    {
        $image = imagecreatefromstring((string) file_get_contents($path));
        if (false === $image) {
            throw new \RuntimeException('Image illisible : '.$path);
        }
        $color = imagecolorat($image, $x, $y);

        return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
    }
}
