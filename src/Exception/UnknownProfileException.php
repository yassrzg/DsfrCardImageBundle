<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Exception;

/**
 * Profil demandé absent de la configuration.
 */
final class UnknownProfileException extends \InvalidArgumentException implements ExceptionInterface
{
}
