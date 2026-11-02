<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Yassrzg\DsfrCardImageBundle\Http\ImageResponseFactory;

/**
 * Images du dossier source (option source_directory) adaptées à un profil : GET <préfixe>/{profile}/{path}.
 *
 * Seules les images contenues dans le dossier source sont servies : chemin avec « .. », lien symbolique qui en sort
 * ou fichier qui n'est pas une image → 404 ou image de remplacement, jamais le contenu du fichier. Les règles
 * d'accès se posent dans l'application (access_control sur le préfixe de la route).
 */
final readonly class ImageController
{
    public const string ROUTE = 'dsfr_card_image';

    public function __construct(
        private ImageResponseFactory $responses,
        private string $sourceDirectory,
    ) {
    }

    public function __invoke(Request $request, string $profile, string $path): Response
    {
        return $this->responses->create($request, $this->resolve($path), $profile);
    }

    /** Chemin absolu de l'image dans le dossier source, null si elle n'existe pas. */
    private function resolve(string $path): ?string
    {
        $segments = explode('/', str_replace('\\', '/', $path));
        if (str_contains($path, "\0") || \in_array('..', $segments, true) || str_starts_with($path, '/')) {
            throw new NotFoundHttpException('Chemin d\'image invalide.');
        }
        $root = realpath($this->sourceDirectory);
        $real = false === $root ? false : realpath($root.'/'.$path);
        if (false === $real) {
            return null;
        }
        if (!str_starts_with($real, $root.\DIRECTORY_SEPARATOR)) {
            throw new NotFoundHttpException('Image hors du dossier source.');
        }

        return $real;
    }
}
