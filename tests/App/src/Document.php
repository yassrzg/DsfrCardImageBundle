<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Tests\App\Src;

/** Objet de l'application (ex. entité) dont l'image a sa propre route. */
final readonly class Document
{
    public function __construct(public int $id)
    {
    }
}
