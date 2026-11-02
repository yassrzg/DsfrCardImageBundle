<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Yassrzg\DsfrCardImageBundle\Exception\ImageProcessingException;
use Yassrzg\DsfrCardImageBundle\Exception\SourceNotFoundException;
use Yassrzg\DsfrCardImageBundle\Exception\UnknownProfileException;
use Yassrzg\DsfrCardImageBundle\Exception\UnsupportedImageException;
use Yassrzg\DsfrCardImageBundle\ImageVariantGenerator;
use Yassrzg\DsfrCardImageBundle\Processor\GdImageProcessor;
use Yassrzg\DsfrCardImageBundle\Profile\ImageProfile;
use Yassrzg\DsfrCardImageBundle\Profile\OutputFormat;
use Yassrzg\DsfrCardImageBundle\Profile\ProfileRegistry;
use Yassrzg\DsfrCardImageBundle\Storage\VariantStorage;
use Yassrzg\DsfrCardImageBundle\Tests\ImageFactory;

/**
 * Stockage des images générées et générateur : réutilisation, régénération, originaux intacts, erreurs d'écriture.
 */
#[RequiresPhpExtension('gd')]
final class VariantStorageTest extends TestCase
{
    private string $directory;
    private VariantStorage $storage;
    private ImageVariantGenerator $generator;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/dsfr-card-image-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->directory.'/sources');
        $this->storage = new VariantStorage($this->directory.'/cache');
        $this->generator = new ImageVariantGenerator(
            new ProfileRegistry([new ImageProfile('card', 160, 90), new ImageProfile('thumb', 80, 45)]),
            new GdImageProcessor(),
            $this->storage,
        );
    }

    protected function tearDown(): void
    {
        @chmod($this->directory.'/cache', 0o755);
        (new Filesystem())->remove($this->directory);
    }

    public function testPathsAreDeterministicAndSeparatedFromOriginals(): void
    {
        $source = ImageFactory::create($this->directory.'/sources/a.png', 320, 180);
        $other = ImageFactory::create($this->directory.'/sources/b.png', 320, 180);
        $card = new ImageProfile('card', 160, 90);

        $path = $this->storage->path($source, $card, OutputFormat::WEBP);
        self::assertSame($path, $this->storage->path($source, $card, OutputFormat::WEBP));
        self::assertStringStartsWith($this->directory.'/cache/card/', $path);
        self::assertStringEndsWith('.webp', $path);
        self::assertNotSame($path, $this->storage->path($other, $card, OutputFormat::WEBP), 'Pas de collision entre deux originaux.');
        self::assertNotSame($path, $this->storage->path($source, new ImageProfile('card', 160, 90, quality: 60), OutputFormat::WEBP), 'Réglage modifié : nouvelle image.');
        self::assertNotSame($path, $this->storage->path($source, $card, OutputFormat::JPEG));

        touch($source, time() + 10);
        clearstatcache();
        self::assertNotSame($path, $this->storage->path($source, $card, OutputFormat::WEBP), 'Original remplacé : nouvelle image.');
    }

    public function testVariantIsGeneratedOnceAndTheOriginalIsNeverModified(): void
    {
        $source = ImageFactory::create($this->directory.'/sources/photo.jpeg', 1600, 900, 'jpeg');
        $hash = sha1_file($source);

        $first = $this->generator->generate($source, 'card');
        self::assertFileExists($first->path);
        self::assertSame([160, 90], ImageFactory::size($first->path));
        touch($first->path, 1_000_000);
        clearstatcache();

        $second = $this->generator->generate($source, 'card');
        self::assertSame($first->path, $second->path);
        self::assertSame(1_000_000, filemtime($second->path), 'Image déjà générée : réutilisée sans nouveau traitement.');
        self::assertSame($hash, sha1_file($source), 'Original intact.');
        self::assertSame([], glob($this->directory.'/cache/card/*.tmp'), 'Aucun fichier temporaire laissé.');

        $custom = $this->generator->generate($source, new ImageProfile('card', 320, 180));
        self::assertNotSame($first->path, $custom->path);
        self::assertSame([320, 180], ImageFactory::size($custom->path));
    }

    public function testErrors(): void
    {
        $source = ImageFactory::create($this->directory.'/sources/photo.png', 320, 180);
        file_put_contents($this->directory.'/sources/notes.txt', 'texte');

        foreach ([
            [fn () => $this->generator->generate($source, 'hero'), UnknownProfileException::class],
            [fn () => $this->generator->generate($this->directory.'/sources/absent.png', 'card'), SourceNotFoundException::class],
            [fn () => $this->generator->generate($this->directory.'/sources/notes.txt', 'card'), UnsupportedImageException::class],
        ] as [$call, $exception]) {
            try {
                $call();
                self::fail($exception.' attendue.');
            } catch (\Throwable $e) {
                self::assertInstanceOf($exception, $e);
            }
        }
    }

    public function testUnwritableCacheDirectory(): void
    {
        if (0 === posix_geteuid()) {
            self::markTestSkipped('Droits non appliqués à root.');
        }
        $source = ImageFactory::create($this->directory.'/sources/photo.png', 320, 180);
        (new Filesystem())->mkdir($this->directory.'/cache');
        chmod($this->directory.'/cache', 0o500);

        try {
            $this->generator->generate($source, 'card');
            self::fail('Écriture impossible non signalée.');
        } catch (ImageProcessingException $e) {
            self::assertStringContainsString('écriture', $e->getMessage());
        }
    }

    public function testPurge(): void
    {
        $source = ImageFactory::create($this->directory.'/sources/photo.png', 320, 180);
        $this->generator->generate($source, 'card');
        $this->generator->generate($source, 'thumb');

        self::assertSame(1, $this->storage->purge('thumb'));
        self::assertDirectoryDoesNotExist($this->directory.'/cache/thumb');
        self::assertDirectoryExists($this->directory.'/cache/card');
        self::assertSame(1, $this->storage->purge());
        self::assertSame(0, $this->storage->purge());
        self::assertFileExists($source, 'Originaux jamais supprimés.');
    }
}
