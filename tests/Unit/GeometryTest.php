<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yassrzg\DsfrCardImageBundle\Processor\Geometry;
use Yassrzg\DsfrCardImageBundle\Profile\ImageProfile;
use Yassrzg\DsfrCardImageBundle\Profile\ResizeMode;

final class GeometryTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, ResizeMode, array{int, int, int, int, int, int}}>
     */
    public static function cases(): iterable
    {
        // [x, y, largeur source, hauteur source, largeur produite, hauteur produite]
        yield 'cover, déjà 16/9' => [1600, 900, ResizeMode::COVER, [0, 0, 1600, 900, 800, 450]];
        yield 'cover, carré : bandes haute et basse coupées' => [1000, 1000, ResizeMode::COVER, [0, 218, 1000, 563, 800, 450]];
        yield 'cover, panoramique : côtés coupés' => [4000, 1000, ResizeMode::COVER, [1111, 0, 1778, 1000, 800, 450]];
        yield 'cover, portrait' => [600, 1200, ResizeMode::COVER, [0, 431, 600, 338, 600, 338]];
        yield 'cover, plus petite que le cadre : jamais agrandie' => [320, 180, ResizeMode::COVER, [0, 0, 320, 180, 320, 180]];
        yield 'cover, bande très fine' => [2000, 10, ResizeMode::COVER, [991, 0, 18, 10, 18, 10]];
        yield 'contain, carré : image entière' => [1000, 1000, ResizeMode::CONTAIN, [0, 0, 1000, 1000, 450, 450]];
        yield 'contain, panoramique' => [1600, 400, ResizeMode::CONTAIN, [0, 0, 1600, 400, 800, 200]];
        yield 'contain, plus petite : taille gardée' => [300, 100, ResizeMode::CONTAIN, [0, 0, 300, 100, 300, 100]];
        yield 'contain, 1 pixel' => [1, 1, ResizeMode::CONTAIN, [0, 0, 1, 1, 1, 1]];
    }

    /**
     * @param array{int, int, int, int, int, int} $expected
     */
    #[DataProvider('cases')]
    public function testComputesSourceAreaAndOutputSize(int $width, int $height, ResizeMode $mode, array $expected): void
    {
        $g = Geometry::compute($width, $height, new ImageProfile('card', 800, 450, $mode));

        self::assertSame($expected, [$g->sourceX, $g->sourceY, $g->sourceWidth, $g->sourceHeight, $g->width, $g->height]);
    }

    public function testCoverKeepsTheExactFrameRatio(): void
    {
        foreach ([[1000, 1000], [1234, 567], [999, 1001], [4032, 3024]] as [$width, $height]) {
            $g = Geometry::compute($width, $height, new ImageProfile('card', 800, 450));
            self::assertSame([800, 450], [$g->width, $g->height], "$width × $height");
        }
    }

    public function testRejectsEmptyImages(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Geometry::compute(0, 10, new ImageProfile('card', 800, 450));
    }
}
