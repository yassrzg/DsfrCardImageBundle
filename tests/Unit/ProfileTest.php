<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yassrzg\DsfrCardImageBundle\Exception\ExceptionInterface;
use Yassrzg\DsfrCardImageBundle\Exception\InvalidProfileException;
use Yassrzg\DsfrCardImageBundle\Exception\UnknownProfileException;
use Yassrzg\DsfrCardImageBundle\Profile\ImageProfile;
use Yassrzg\DsfrCardImageBundle\Profile\OutputFormat;
use Yassrzg\DsfrCardImageBundle\Profile\ProfileRegistry;
use Yassrzg\DsfrCardImageBundle\Profile\ResizeMode;

final class ProfileTest extends TestCase
{
    public function testInvalidProfilesAreRejected(): void
    {
        foreach ([['Carte', 800, 450, 82], ['card', 0, 450, 82], ['card', 800, -1, 82], ['card', 800, 450, 0], ['card', 800, 450, 101], ['../x', 800, 450, 82]] as [$name, $width, $height, $quality]) {
            try {
                new ImageProfile($name, $width, $height, quality: $quality);
                self::fail(\sprintf('Profil accepté : %s %d×%d q%d', $name, $width, $height, $quality));
            } catch (InvalidProfileException $e) {
                self::assertInstanceOf(ExceptionInterface::class, $e);
            }
        }
    }

    public function testFingerprintChangesWithEverySettingThatAltersTheImage(): void
    {
        $base = new ImageProfile('card', 800, 450);
        $variants = [
            new ImageProfile('card', 801, 450),
            new ImageProfile('card', 800, 451),
            new ImageProfile('card', 800, 450, ResizeMode::CONTAIN),
            new ImageProfile('card', 800, 450, format: OutputFormat::JPEG),
            new ImageProfile('card', 800, 450, quality: 70),
        ];
        foreach ($variants as $variant) {
            self::assertNotSame($base->fingerprint(), $variant->fingerprint());
        }
        self::assertSame($base->fingerprint(), (new ImageProfile('other', 800, 450))->fingerprint(), 'Le nom ne change pas l\'image.');
    }

    public function testRegistryFromConfiguration(): void
    {
        $registry = ProfileRegistry::fromConfig([
            'card' => ['width' => 800, 'height' => 450, 'mode' => 'cover', 'format' => 'auto', 'quality' => 82],
            'logo' => ['width' => 400, 'height' => 400, 'mode' => 'contain', 'format' => 'png', 'quality' => 100],
        ]);

        self::assertSame(['card', 'logo'], array_keys($registry->all()));
        self::assertTrue($registry->has('logo'));
        self::assertSame(ResizeMode::CONTAIN, $registry->get('logo')->mode);
        self::assertSame(OutputFormat::PNG, $registry->get('logo')->format);

        $this->expectException(UnknownProfileException::class);
        $this->expectExceptionMessage('profils déclarés : card, logo');
        $registry->get('hero');
    }
}
