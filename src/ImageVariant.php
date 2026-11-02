<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle;

use Yassrzg\DsfrCardImageBundle\Profile\OutputFormat;

/**
 * Image générée pour un profil : chemin du fichier et format.
 */
final readonly class ImageVariant
{
    public function __construct(
        public string $path,
        public OutputFormat $format,
    ) {
    }

    public function mimeType(): string
    {
        return $this->format->mimeType();
    }
}
