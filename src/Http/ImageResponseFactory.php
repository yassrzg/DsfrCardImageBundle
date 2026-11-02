<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Yassrzg\DsfrCardImageBundle\Exception\ExceptionInterface;
use Yassrzg\DsfrCardImageBundle\Exception\SourceNotFoundException;
use Yassrzg\DsfrCardImageBundle\Exception\UnknownProfileException;
use Yassrzg\DsfrCardImageBundle\ImageVariantGenerator;
use Yassrzg\DsfrCardImageBundle\Processor\GdImageProcessor;

/**
 * Réponse HTTP d'une image adaptée à un profil, pour le contrôleur du bundle comme pour ceux de l'application (qui
 * gardent leurs propres règles d'accès) :
 * - image générée pour le profil ;
 * - traitement impossible (GD absent, format ou taille non gérés) : l'original s'il s'agit bien d'une image ;
 * - fichier absent ou qui n'est pas une image : image de remplacement neutre (la page reste lisible).
 * Profil inconnu : 404.
 */
final readonly class ImageResponseFactory
{
    /** Image de remplacement (SVG statique, aucune donnée) : fond gris et pictogramme d'image. */
    public const string PLACEHOLDER = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 90" role="img" aria-label="Image indisponible"><rect width="160" height="90" fill="#eeeeee"/><path d="M62 30h36v30H62z" fill="none" stroke="#929292" stroke-width="3"/><path d="m66 56 10-12 8 9 5-5 7 8z" fill="#929292"/><circle cx="88" cy="38" r="3" fill="#929292"/></svg>';
    /** Durée de cache de l'image de remplacement (secondes) : l'image peut arriver peu après. */
    private const int PLACEHOLDER_MAX_AGE = 300;

    /**
     * @param int  $maxAge durée de cache navigateur des images (secondes), revalidée ensuite (Last-Modified)
     * @param bool $public cache partagé autorisé (proxy, CDN) : seulement pour des images publiques
     */
    public function __construct(
        private ImageVariantGenerator $generator,
        private int $maxAge = 3600,
        private bool $public = false,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param string|null $source chemin absolu de l'original, null s'il n'existe pas (image de remplacement)
     *
     * @throws NotFoundHttpException profil inconnu
     */
    public function create(Request $request, ?string $source, string $profile): Response
    {
        if (null === $source || '' === $source || !is_file($source)) {
            return $this->placeholder();
        }

        try {
            $variant = $this->generator->generate($source, $profile);
            $response = new BinaryFileResponse($variant->path, autoLastModified: true);
            $response->headers->set('Content-Type', $variant->mimeType());
        } catch (UnknownProfileException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        } catch (SourceNotFoundException) {
            return $this->placeholder();
        } catch (ExceptionInterface $e) {
            $original = self::originalImageType($source);
            $this->logger?->warning('Image non adaptée au profil {profile} : {error}', ['profile' => $profile, 'error' => $e->getMessage(), 'fallback' => null === $original ? 'placeholder' : 'original']);
            if (null === $original) {
                return $this->placeholder();
            }
            $response = new BinaryFileResponse($source, autoLastModified: true);
            $response->headers->set('Content-Type', $original);
        }

        $this->cache($response, $this->maxAge);
        $response->setContentDisposition('inline', 'image');
        $response->isNotModified($request);

        return $response;
    }

    public function placeholder(): Response
    {
        $response = new Response(self::PLACEHOLDER, Response::HTTP_OK, ['Content-Type' => 'image/svg+xml']);
        $this->cache($response, self::PLACEHOLDER_MAX_AGE);

        return $response;
    }

    /** Type MIME de l'original s'il s'agit d'une image lisible par le navigateur (sinon il n'est jamais servi). */
    private static function originalImageType(string $source): ?string
    {
        $info = @getimagesize($source);

        return false !== $info && \in_array($info[2], GdImageProcessor::SUPPORTED_TYPES, true) ? $info['mime'] : null;
    }

    private function cache(Response $response, int $maxAge): void
    {
        $this->public ? $response->setPublic() : $response->setPrivate();
        $response->setMaxAge($maxAge);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
    }
}
