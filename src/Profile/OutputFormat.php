<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Profile;

/**
 * Format des images générées.
 */
enum OutputFormat: string
{
    /** WebP si le serveur le gère ; sinon PNG pour une image pouvant être transparente, JPEG pour une photo. */
    case AUTO = 'auto';
    case WEBP = 'webp';
    case JPEG = 'jpeg';
    case PNG = 'png';

    public function extension(): string
    {
        return self::JPEG === $this ? 'jpg' : $this->value;
    }

    public function mimeType(): string
    {
        return 'image/'.$this->value;
    }
}
