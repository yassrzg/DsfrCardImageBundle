<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle;

use Yassrzg\DsfrCardImageBundle\Exception\ExceptionInterface;
use Yassrzg\DsfrCardImageBundle\Exception\ImageProcessingException;
use Yassrzg\DsfrCardImageBundle\Exception\SourceNotFoundException;
use Yassrzg\DsfrCardImageBundle\Exception\UnknownProfileException;
use Yassrzg\DsfrCardImageBundle\Exception\UnsupportedImageException;
use Yassrzg\DsfrCardImageBundle\Processor\GdImageProcessor;
use Yassrzg\DsfrCardImageBundle\Profile\ImageProfile;
use Yassrzg\DsfrCardImageBundle\Profile\ProfileRegistry;
use Yassrzg\DsfrCardImageBundle\Storage\VariantStorage;

/**
 * Point d'entrée du bundle : image adaptée à un profil, générée une fois puis réutilisée. L'original n'est jamais modifié.
 */
final readonly class ImageVariantGenerator
{
    public function __construct(
        private ProfileRegistry $profiles,
        private GdImageProcessor $processor,
        private VariantStorage $storage,
    ) {
    }

    /**
     * @param string|ImageProfile $profile nom d'un profil de la configuration, ou profil construit par l'appelant
     *
     * @throws UnknownProfileException   profil absent de la configuration
     * @throws SourceNotFoundException   image source absente ou illisible
     * @throws UnsupportedImageException format non géré, image trop grande, format de sortie non géré par le serveur
     * @throws ImageProcessingException  GD absent, image corrompue, dossier de destination non accessible en écriture
     * @throws ExceptionInterface        (toutes les erreurs ci-dessus)
     */
    public function generate(string $source, string|ImageProfile $profile): ImageVariant
    {
        $profile = \is_string($profile) ? $this->profiles->get($profile) : $profile;
        if (!GdImageProcessor::isAvailable()) {
            throw new ImageProcessingException('Extension PHP GD absente : images non traitées.');
        }
        $image = $this->processor->inspect($source);
        $format = $this->processor->outputFormat($profile, $image);
        $target = $this->storage->path($source, $profile, $format);
        if (!is_file($target)) {
            $this->storage->save($target, fn (string $temporary) => $this->processor->process($image, $profile, $format, $temporary));
        }

        return new ImageVariant($target, $format);
    }
}
