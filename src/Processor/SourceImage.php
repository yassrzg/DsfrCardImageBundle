<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Processor;

/**
 * Image source reconnue : chemin, dimensions et type (constante IMAGETYPE_* de PHP).
 */
final readonly class SourceImage
{
    public function __construct(
        public string $path,
        public int $width,
        public int $height,
        public int $type,
        public string $mimeType,
    ) {
    }

    /** Format pouvant contenir de la transparence (PNG, GIF, WebP) : à conserver dans l'image produite. */
    public function mayBeTransparent(): bool
    {
        return \IMAGETYPE_JPEG !== $this->type;
    }
}
