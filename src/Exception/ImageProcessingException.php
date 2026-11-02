<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Exception;

/**
 * Échec du traitement : GD absent, image corrompue, écriture impossible dans le dossier des images générées.
 */
final class ImageProcessingException extends \RuntimeException implements ExceptionInterface
{
}
