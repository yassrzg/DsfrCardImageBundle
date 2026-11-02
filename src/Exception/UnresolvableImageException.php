<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Exception;

/**
 * Aucun résolveur d'adresse (ImageUrlResolverInterface) ne prend en charge l'image demandée.
 */
final class UnresolvableImageException extends \InvalidArgumentException implements ExceptionInterface
{
}
