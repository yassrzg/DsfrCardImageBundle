<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Yassrzg\DsfrCardImageBundle\DsfrCardImageBundle;
use Yassrzg\DsfrCardImageBundle\Exception\UnknownProfileException;
use Yassrzg\DsfrCardImageBundle\Exception\UnresolvableImageException;
use Yassrzg\DsfrCardImageBundle\Http\ImageResponseFactory;
use Yassrzg\DsfrCardImageBundle\ImageVariantGenerator;
use Yassrzg\DsfrCardImageBundle\Profile\OutputFormat;
use Yassrzg\DsfrCardImageBundle\Profile\ProfileRegistry;
use Yassrzg\DsfrCardImageBundle\Profile\ResizeMode;
use Yassrzg\DsfrCardImageBundle\Storage\VariantStorage;
use Yassrzg\DsfrCardImageBundle\Tests\App\Src\Document;
use Yassrzg\DsfrCardImageBundle\Tests\ImageFactory;
use Yassrzg\DsfrCardImageBundle\Url\ImageUrlGenerator;

/**
 * Le bundle dans une application Symfony : services, configuration, Twig, route et commande.
 */
#[RequiresPhpExtension('gd')]
final class BundleTest extends WebTestCase
{
    private const string IMAGES = __DIR__.'/../App/public/images';

    protected function setUp(): void
    {
        (new Filesystem())->remove(\dirname(__DIR__, 2).'/var/card_images');
        ImageFactory::create(self::IMAGES.'/photo.jpeg', 1600, 1200, 'jpeg');
        ImageFactory::create(self::IMAGES.'/logo.png', 1000, 1000, 'png', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove([self::IMAGES.'/photo.jpeg', self::IMAGES.'/logo.png']);
        parent::tearDown();
    }

    public function testServicesAreRegisteredWithTheConfiguredProfiles(): void
    {
        $container = static::getContainer();
        foreach ([ProfileRegistry::class, ImageVariantGenerator::class, ImageResponseFactory::class, ImageUrlGenerator::class, VariantStorage::class] as $id) {
            self::assertTrue($container->has($id), $id);
        }
        $profiles = $container->get(ProfileRegistry::class);
        self::assertInstanceOf(ProfileRegistry::class, $profiles);
        self::assertSame(['card', 'card_contain', 'square'], array_keys($profiles->all()));
        self::assertSame(ResizeMode::CONTAIN, $profiles->get('card_contain')->mode);
        self::assertSame(OutputFormat::AUTO, $profiles->get('card')->format, 'Format par défaut.');
        self::assertSame(82, $profiles->get('card')->quality, 'Qualité par défaut.');
        self::assertSame([OutputFormat::PNG, 90], [$profiles->get('square')->format, $profiles->get('square')->quality]);
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->load([]);

        $profiles = $config->getDefinition(ProfileRegistry::class)->getArgument(0);
        self::assertIsArray($profiles);
        self::assertSame(['card', 'card_contain'], array_keys($profiles));
        self::assertSame('%kernel.project_dir%/var/card_images', $config->getDefinition(VariantStorage::class)->getArgument(0));
    }

    public function testInvalidConfigurationIsRejected(): void
    {
        foreach ([
            ['profiles' => ['Carte' => ['width' => 800, 'height' => 450]]],
            ['profiles' => ['card' => ['width' => 0, 'height' => 450]]],
            ['profiles' => ['card' => ['width' => 800]]],
            ['profiles' => ['card' => ['width' => 800, 'height' => 450, 'mode' => 'stretch']]],
            ['profiles' => ['card' => ['width' => 800, 'height' => 450, 'format' => 'bmp']]],
            ['quality' => 120],
            ['max_pixels' => 0],
        ] as $config) {
            try {
                $this->load([$config]);
                self::fail('Configuration acceptée : '.json_encode($config));
            } catch (InvalidConfigurationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTwigFunctionUsesTheResolverOfEachKindOfImage(): void
    {
        $twig = static::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertSame(
            '<div class="fr-card__img"><img class="fr-responsive-img fr-ratio-16x9" src="/card-image/card/images/photo.jpeg" alt=""></div>',
            trim($twig->render('card.html.twig', ['image' => 'images/photo.jpeg', 'profile' => 'card'])),
        );
        self::assertStringContainsString('src="/document/42/card_contain"', $twig->render('card.html.twig', ['image' => new Document(42), 'profile' => 'card_contain']));

        foreach ([['images/photo.jpeg', 'hero', UnknownProfileException::class], [new \stdClass(), 'card', UnresolvableImageException::class]] as [$image, $profile, $exception]) {
            try {
                $twig->render('card.html.twig', ['image' => $image, 'profile' => $profile]);
                self::fail($exception.' attendue.');
            } catch (RuntimeError $e) {
                self::assertInstanceOf($exception, $e->getPrevious());
            }
        }
    }

    public function testRouteServesAdaptedImages(): void
    {
        $client = static::createClient();

        $client->request('GET', '/card-image/card/images/photo.jpeg');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertSame([800, 450], self::sizeOf($client->getInternalResponse()->getContent()));
        $lastModified = (string) $client->getResponse()->headers->get('Last-Modified');

        $client->request('GET', '/card-image/card_contain/images/logo.png');
        self::assertSame([450, 450], self::sizeOf($client->getInternalResponse()->getContent()), 'Logo entier.');

        $client->request('GET', '/card-image/square/images/photo.jpeg');
        self::assertResponseHeaderSame('Content-Type', 'image/png');

        $client->request('GET', '/card-image/card/images/photo.jpeg', server: ['HTTP_IF_MODIFIED_SINCE' => $lastModified]);
        self::assertResponseStatusCodeSame(304);
    }

    public function testRouteNeverServesOtherFiles(): void
    {
        $client = static::createClient();

        $client->request('GET', '/card-image/card/images/absent.png');
        self::assertResponseHeaderSame('Content-Type', 'image/svg+xml', 'Absente : image de remplacement.');

        $client->request('GET', '/card-image/card/images/notes.txt');
        self::assertResponseHeaderSame('Content-Type', 'image/svg+xml');
        self::assertStringNotContainsString('Fichier texte', (string) $client->getInternalResponse()->getContent());

        $client->request('GET', '/card-image/card/../Kernel.php');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/card-image/card/images/..%2F..%2FKernel.php');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/card-image/hero/images/photo.jpeg');
        self::assertResponseStatusCodeSame(404, 'Profil inconnu.');
    }

    public function testPurgeCommand(): void
    {
        $kernel = static::bootKernel();
        $generator = static::getContainer()->get(ImageVariantGenerator::class);
        self::assertInstanceOf(ImageVariantGenerator::class, $generator);
        $generator->generate(self::IMAGES.'/photo.jpeg', 'card');
        $generator->generate(self::IMAGES.'/photo.jpeg', 'square');

        $tester = new CommandTester((new Application($kernel))->find('dsfr-card-image:purge'));
        self::assertSame(2, $tester->execute(['profile' => 'hero']), 'Profil inconnu : Command::INVALID.');
        self::assertSame(0, $tester->execute(['profile' => 'square']));
        self::assertStringContainsString('1 image(s)', $tester->getDisplay());
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('1 image(s)', $tester->getDisplay());
        self::assertFileExists(self::IMAGES.'/photo.jpeg');
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $extension = (new DsfrCardImageBundle())->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load($configs, $container);

        return $container;
    }

    /** @return array{0: int, 1: int} */
    private static function sizeOf(string|false $content): array
    {
        $info = getimagesizefromstring((string) $content);
        self::assertNotFalse($info);

        return [$info[0], $info[1]];
    }
}
