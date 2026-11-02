<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Storage;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Yassrzg\DsfrCardImageBundle\Exception\ImageProcessingException;
use Yassrzg\DsfrCardImageBundle\Profile\ImageProfile;
use Yassrzg\DsfrCardImageBundle\Profile\OutputFormat;

/**
 * Dossier des images générées, séparé des originaux : <dossier>/<profil>/<clé>.<extension>.
 *
 * La clé (SHA-1) est tirée du chemin de l'original, de sa date et de sa taille, et des réglages du profil : une image
 * déjà générée est réutilisée telle quelle, un original remplacé ou un profil modifié donne une nouvelle image
 * (les anciennes restent jusqu'à la purge). Deux originaux différents ne peuvent pas partager la même clé.
 */
final readonly class VariantStorage
{
    public function __construct(
        private string $directory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function path(string $source, ImageProfile $profile, OutputFormat $format): string
    {
        $real = realpath($source) ?: $source;
        $key = sha1(implode('|', [$real, (string) @filemtime($source), (string) @filesize($source), $profile->fingerprint(), $format->value]));

        return \sprintf('%s/%s/%s.%s', $this->directory, $profile->name, $key, $format->extension());
    }

    /**
     * Écrit l'image par un fichier temporaire renommé ensuite : une requête simultanée ne lit jamais une image
     * incomplète, et deux générations simultanées produisent le même fichier.
     *
     * @param callable(string): void $write écrit l'image dans le chemin temporaire reçu
     *
     * @throws ImageProcessingException dossier non créé ou non accessible en écriture
     */
    public function save(string $target, callable $write): void
    {
        $temporary = $target.'.'.bin2hex(random_bytes(4)).'.tmp';
        try {
            $this->filesystem->mkdir(\dirname($target));
            $write($temporary);
            $this->filesystem->rename($temporary, $target, true);
        } catch (IOExceptionInterface $e) {
            throw new ImageProcessingException(\sprintf('Dossier des images générées non accessible en écriture : %s', $e->getMessage()), 0, $e);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * Supprime les images générées (toutes, ou celles d'un profil) ; elles seront recréées à la demande.
     *
     * @return int nombre de fichiers supprimés
     */
    public function purge(?string $profile = null): int
    {
        $directory = null === $profile ? $this->directory : $this->directory.'/'.basename($profile);
        if (!is_dir($directory)) {
            return 0;
        }
        $count = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                ++$count;
            }
        }
        $this->filesystem->remove($directory);

        return $count;
    }
}
