<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Exception;

/**
 * Profil mal défini (nom, dimensions ou qualité).
 */
final class InvalidProfileException extends \InvalidArgumentException implements ExceptionInterface
{
}
