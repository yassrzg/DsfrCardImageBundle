<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Exception;

/**
 * Fichier qui n'est pas une image gérée (format, dimensions, poids en pixels) ou format de sortie non géré par le serveur.
 */
final class UnsupportedImageException extends \RuntimeException implements ExceptionInterface
{
}
