<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Exception;

/**
 * Image source absente ou illisible.
 */
final class SourceNotFoundException extends \RuntimeException implements ExceptionInterface
{
}
