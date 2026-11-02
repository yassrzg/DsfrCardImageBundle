<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Yassrzg\DsfrCardImageBundle\Exception\ImageProcessingException;
use Yassrzg\DsfrCardImageBundle\Exception\SourceNotFoundException;
use Yassrzg\DsfrCardImageBundle\Exception\UnsupportedImageException;
use Yassrzg\DsfrCardImageBundle\Processor\GdImageProcessor;
use Yassrzg\DsfrCardImageBundle\Profile\ImageProfile;
use Yassrzg\DsfrCardImageBundle\Profile\OutputFormat;
use Yassrzg\DsfrCardImageBundle\Profile\ResizeMode;
use Yassrzg\DsfrCardImageBundle\Tests\ImageFactory;

#[RequiresPhpExtension('gd')]
final class GdImageProcessorTest extends TestCase
{
    private string $directory;
    private GdImageProcessor $processor;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/dsfr-card-image-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->directory);
        $this->processor = new GdImageProcessor();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testEveryInputFormatIsConvertedToEveryOutputFormat(): void
    {
        $profile = static fn (OutputFormat $format): ImageProfile => new ImageProfile('card', 160, 90, format: $format);
        foreach (['jpeg' => \IMAGETYPE_JPEG, 'png' => \IMAGETYPE_PNG, 'gif' => \IMAGETYPE_GIF, 'webp' => \IMAGETYPE_WEBP] as $type => $constant) {
            $source = $this->processor->inspect(ImageFactory::create("$this->directory/source.$type", 640, 480, $type));
            self::assertSame($constant, $source->type);
            foreach ([OutputFormat::WEBP, OutputFormat::JPEG, OutputFormat::PNG] as $format) {
                $target = "$this->directory/$type-to.".$format->extension();
                $this->processor->process($source, $profile($format), $format, $target);
                self::assertSame([160, 90], ImageFactory::size($target), "$type → {$format->value}");
                self::assertSame($format->mimeType(), getimagesize($target)['mime'] ?? null, "$type → {$format->value}");
            }
        }
    }

    public function testAutoFormatPrefersWebp(): void
    {
        $profile = new ImageProfile('card', 160, 90);
        $photo = $this->processor->inspect(ImageFactory::create("$this->directory/photo.jpeg", 320, 180, 'jpeg'));

        self::assertSame(\function_exists('imagewebp') ? OutputFormat::WEBP : OutputFormat::JPEG, $this->processor->outputFormat($profile, $photo));
        self::assertSame(OutputFormat::PNG, $this->processor->outputFormat(new ImageProfile('card', 1, 1, format: OutputFormat::PNG), $photo));
    }

    public function testTransparencyIsKeptWhenTheFormatAllowsIt(): void
    {
        $source = $this->processor->inspect(ImageFactory::create("$this->directory/logo.png", 400, 400, 'png', true));
        $contain = new ImageProfile('logo', 200, 200, ResizeMode::CONTAIN);

        foreach ([OutputFormat::PNG, OutputFormat::WEBP] as $format) {
            $target = "$this->directory/logo.".$format->extension();
            $this->processor->process($source, $contain, $format, $target);
            self::assertSame(127, ImageFactory::alphaAt($target, 100, 100), $format->value.' : transparence conservée');
        }

        // JPEG : pas de transparence, fond blanc (et non noir).
        $this->processor->process($source, $contain, OutputFormat::JPEG, "$this->directory/logo.jpg");
        [$red, $green, $blue] = ImageFactory::rgbAt("$this->directory/logo.jpg", 100, 100);
        self::assertGreaterThan(245, min($red, $green, $blue));
    }

    public function testCoverCropsAndContainKeepsTheWholeImage(): void
    {
        $source = $this->processor->inspect(ImageFactory::create("$this->directory/square.png", 1000, 1000));

        $this->processor->process($source, new ImageProfile('card', 800, 450), OutputFormat::PNG, "$this->directory/cover.png");
        $this->processor->process($source, new ImageProfile('logo', 800, 450, ResizeMode::CONTAIN), OutputFormat::PNG, "$this->directory/contain.png");

        self::assertSame([800, 450], ImageFactory::size("$this->directory/cover.png"));
        self::assertSame([450, 450], ImageFactory::size("$this->directory/contain.png"));
    }

    public function testSmallAndLargeImages(): void
    {
        $tiny = $this->processor->inspect(ImageFactory::create("$this->directory/tiny.png", 1, 1));
        $this->processor->process($tiny, new ImageProfile('card', 800, 450), OutputFormat::PNG, "$this->directory/tiny-out.png");
        self::assertSame([1, 1], ImageFactory::size("$this->directory/tiny-out.png"), 'Jamais agrandie.');

        $large = $this->processor->inspect(ImageFactory::create("$this->directory/large.jpeg", 4000, 3000, 'jpeg'));
        $this->processor->process($large, new ImageProfile('card', 800, 450), OutputFormat::WEBP, "$this->directory/large.webp");
        self::assertSame([800, 450], ImageFactory::size("$this->directory/large.webp"));
        self::assertLessThan(filesize("$this->directory/large.jpeg"), filesize("$this->directory/large.webp"));

        $this->expectException(UnsupportedImageException::class);
        (new GdImageProcessor(maxPixels: 1_000_000))->inspect("$this->directory/large.jpeg");
    }

    public function testExifOrientationIsApplied(): void
    {
        if (!\function_exists('exif_read_data')) {
            self::markTestSkipped('Extension exif absente.');
        }
        // Photo enregistrée couchée (400 × 300), à afficher debout (300 × 400).
        $source = $this->processor->inspect(ImageFactory::jpegWithOrientation("$this->directory/photo.jpg", 400, 300, 6));
        $this->processor->process($source, new ImageProfile('portrait', 1000, 1000, ResizeMode::CONTAIN), OutputFormat::JPEG, "$this->directory/upright.jpg");

        self::assertSame([300, 400], ImageFactory::size("$this->directory/upright.jpg"));
    }

    public function testMissingUnreadableAndUnsupportedFiles(): void
    {
        try {
            $this->processor->inspect("$this->directory/absent.png");
            self::fail('Fichier absent accepté.');
        } catch (SourceNotFoundException) {
        }

        file_put_contents("$this->directory/notes.txt", 'texte');
        try {
            $this->processor->inspect("$this->directory/notes.txt");
            self::fail('Fichier texte accepté.');
        } catch (UnsupportedImageException) {
        }

        file_put_contents("$this->directory/image.svg", '<svg xmlns="http://www.w3.org/2000/svg"/>');
        try {
            $this->processor->inspect("$this->directory/image.svg");
            self::fail('SVG accepté.');
        } catch (UnsupportedImageException) {
        }

        // En-tête PNG valide, contenu tronqué : reconnu, mais illisible au traitement.
        $png = (string) file_get_contents(ImageFactory::create("$this->directory/whole.png", 300, 300));
        file_put_contents("$this->directory/broken.png", substr($png, 0, 40));
        $broken = $this->processor->inspect("$this->directory/broken.png");
        $this->expectException(ImageProcessingException::class);
        $this->processor->process($broken, new ImageProfile('card', 160, 90), OutputFormat::PNG, "$this->directory/broken-out.png");
    }
}
