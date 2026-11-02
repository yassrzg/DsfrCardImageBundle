<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Processor;

use Yassrzg\DsfrCardImageBundle\Exception\ImageProcessingException;
use Yassrzg\DsfrCardImageBundle\Exception\SourceNotFoundException;
use Yassrzg\DsfrCardImageBundle\Exception\UnsupportedImageException;
use Yassrzg\DsfrCardImageBundle\Profile\ImageProfile;
use Yassrzg\DsfrCardImageBundle\Profile\OutputFormat;

/**
 * Traitement avec l'extension GD de PHP (aucune bibliothèque externe) : lecture JPEG, PNG, GIF ou WebP, remise
 * à l'endroit des photos JPEG selon leur orientation EXIF (extension exif), recadrage ou réduction (Geometry),
 * transparence conservée, écriture WebP, JPEG ou PNG.
 */
final readonly class GdImageProcessor
{
    /** Types d'images lus (constantes IMAGETYPE_*). */
    public const array SUPPORTED_TYPES = [\IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_GIF, \IMAGETYPE_WEBP];

    /**
     * @param int $maxPixels au-delà (largeur × hauteur), l'image n'est pas traitée : la décompresser en mémoire
     *                       risquerait de dépasser memory_limit
     */
    public function __construct(private int $maxPixels = 40_000_000)
    {
    }

    public static function isAvailable(): bool
    {
        return \extension_loaded('gd');
    }

    /**
     * @throws SourceNotFoundException   fichier absent ou illisible
     * @throws UnsupportedImageException format non géré ou image trop grande
     */
    public function inspect(string $path): SourceImage
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new SourceNotFoundException(\sprintf('Image source introuvable ou illisible : %s', $path));
        }
        $info = @getimagesize($path);
        if (false === $info || !\in_array($info[2], self::SUPPORTED_TYPES, true)) {
            throw new UnsupportedImageException(\sprintf('Format d\'image non géré : %s (JPEG, PNG, GIF ou WebP attendu).', $path));
        }
        [$width, $height, $type] = $info;
        if ($width < 1 || $height < 1 || $width * $height > $this->maxPixels) {
            throw new UnsupportedImageException(\sprintf('Image de %d × %d pixels non traitée (limite : %d pixels).', $width, $height, $this->maxPixels));
        }

        return new SourceImage($path, $width, $height, $type, $info['mime']);
    }

    /**
     * Format réellement produit : « auto » devient WebP si GD sait l'écrire, sinon PNG (transparence) ou JPEG.
     *
     * @throws UnsupportedImageException format demandé non géré par le GD du serveur
     */
    public function outputFormat(ImageProfile $profile, SourceImage $source): OutputFormat
    {
        $format = $profile->format;
        if (OutputFormat::AUTO === $format) {
            $format = self::canWrite(OutputFormat::WEBP) ? OutputFormat::WEBP : ($source->mayBeTransparent() ? OutputFormat::PNG : OutputFormat::JPEG);
        }
        if (!self::canWrite($format)) {
            throw new UnsupportedImageException(\sprintf('Le serveur ne sait pas écrire le format %s (extension GD).', $format->value));
        }

        return $format;
    }

    /**
     * Écrit dans $target l'image adaptée au profil. L'image source n'est jamais modifiée.
     *
     * @throws ImageProcessingException
     */
    public function process(SourceImage $source, ImageProfile $profile, OutputFormat $format, string $target): void
    {
        if (!self::isAvailable()) {
            throw new ImageProcessingException('Extension PHP GD absente : images non traitées.');
        }

        $image = $this->read($source);
        $image = $this->orient($image, $source);
        $geometry = Geometry::compute(imagesx($image), imagesy($image), $profile);

        $output = imagecreatetruecolor($geometry->width, $geometry->height);
        if (false === $output) {
            throw new ImageProcessingException('Mémoire insuffisante pour créer l\'image.');
        }
        if ($source->mayBeTransparent() && OutputFormat::JPEG !== $format) {
            imagealphablending($output, false);
            imagesavealpha($output, true);
            imagefill($output, 0, 0, (int) imagecolorallocatealpha($output, 0, 0, 0, 127));
        } else {
            // JPEG : pas de transparence, fond blanc plutôt que noir sous les zones transparentes.
            imagefill($output, 0, 0, (int) imagecolorallocate($output, 255, 255, 255));
        }
        imagecopyresampled($output, $image, 0, 0, $geometry->sourceX, $geometry->sourceY, $geometry->width, $geometry->height, $geometry->sourceWidth, $geometry->sourceHeight);

        $written = match ($format) {
            OutputFormat::WEBP => @imagewebp($output, $target, $profile->quality),
            OutputFormat::JPEG => @imagejpeg($output, $target, $profile->quality),
            // PNG : compression sans perte, niveau 0 à 9 tiré de la qualité (100 → 0, rapide ; 1 → 9, compact).
            OutputFormat::PNG => @imagepng($output, $target, (int) round((100 - $profile->quality) * 9 / 99)),
            OutputFormat::AUTO => false,
        };
        if (!$written) {
            throw new ImageProcessingException(\sprintf('Écriture impossible : %s', $target));
        }
    }

    private static function canWrite(OutputFormat $format): bool
    {
        if (!self::isAvailable()) {
            return false;
        }

        return match ($format) {
            OutputFormat::WEBP => \function_exists('imagewebp') && (bool) (imagetypes() & \IMG_WEBP),
            OutputFormat::JPEG => (bool) (imagetypes() & \IMG_JPG),
            OutputFormat::PNG => (bool) (imagetypes() & \IMG_PNG),
            OutputFormat::AUTO => true,
        };
    }

    private function read(SourceImage $source): \GdImage
    {
        $image = match ($source->type) {
            \IMAGETYPE_JPEG => @imagecreatefromjpeg($source->path),
            \IMAGETYPE_PNG => @imagecreatefrompng($source->path),
            \IMAGETYPE_GIF => @imagecreatefromgif($source->path),
            \IMAGETYPE_WEBP => \function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source->path) : false,
            default => false,
        };
        if (!$image instanceof \GdImage) {
            throw new ImageProcessingException(\sprintf('Image illisible ou corrompue : %s', $source->path));
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }

    /**
     * Photos JPEG : rotation ou miroir indiqués par l'appareil (balise EXIF Orientation), sinon l'image apparaît
     * couchée. Sans l'extension exif, l'image est gardée telle quelle.
     */
    private function orient(\GdImage $image, SourceImage $source): \GdImage
    {
        if (\IMAGETYPE_JPEG !== $source->type || !\function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($source->path);
        $orientation = \is_array($exif) && \is_int($exif['Orientation'] ?? null) ? $exif['Orientation'] : 1;
        if (\in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, \IMG_FLIP_HORIZONTAL);
        }
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };
        if (0 === $angle) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);

        return false === $rotated ? $image : $rotated;
    }
}
