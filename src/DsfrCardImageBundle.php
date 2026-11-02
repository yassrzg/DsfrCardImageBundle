<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Extension\AbstractExtension;
use Yassrzg\DsfrCardImageBundle\Command\PurgeCommand;
use Yassrzg\DsfrCardImageBundle\Controller\ImageController;
use Yassrzg\DsfrCardImageBundle\Http\ImageResponseFactory;
use Yassrzg\DsfrCardImageBundle\Processor\GdImageProcessor;
use Yassrzg\DsfrCardImageBundle\Profile\OutputFormat;
use Yassrzg\DsfrCardImageBundle\Profile\ProfileRegistry;
use Yassrzg\DsfrCardImageBundle\Profile\ResizeMode;
use Yassrzg\DsfrCardImageBundle\Storage\VariantStorage;
use Yassrzg\DsfrCardImageBundle\Twig\CardImageExtension;
use Yassrzg\DsfrCardImageBundle\Url\ImageUrlGenerator;
use Yassrzg\DsfrCardImageBundle\Url\ImageUrlResolverInterface;
use Yassrzg\DsfrCardImageBundle\Url\PathUrlResolver;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Images adaptées aux cartes DSFR : profils (cadre, recadrage ou ajustement, format, qualité), images dérivées en
 * cache, réponse HTTP, fonction Twig card_image_url(). Configuration : clé dsfr_card_image (voir README).
 */
final class DsfrCardImageBundle extends AbstractBundle
{
    /**
     * Profils par défaut, remplacés par ceux de l'application dès qu'elle en déclare : image de carte DSFR (16/9,
     * deux fois la largeur affichée d'une carte de grille pour les écrans haute densité), recadrée ou entière.
     */
    public const array DEFAULT_PROFILES = [
        'card' => ['width' => 800, 'height' => 450, 'mode' => 'cover'],
        'card_contain' => ['width' => 800, 'height' => 450, 'mode' => 'contain'],
    ];

    protected string $extensionAlias = 'dsfr_card_image';

    public function configure(DefinitionConfigurator $definition): void
    {
        $formats = array_map(static fn (OutputFormat $f): string => $f->value, OutputFormat::cases());
        $modes = array_map(static fn (ResizeMode $m): string => $m->value, ResizeMode::cases());

        /** @var NodeBuilder $root */
        $root = $definition->rootNode()->children();
        $root
            ->scalarNode('cache_directory')
                ->info('Dossier des images générées (hors du dossier public : elles sont servies par une réponse HTTP).')
                ->defaultValue('%kernel.project_dir%/var/card_images')
                ->cannotBeEmpty()
            ->end()
            ->scalarNode('source_directory')
                ->info('Dossier des originaux servis par la route du bundle (chemins relatifs donnés à card_image_url()).')
                ->defaultValue('%kernel.project_dir%/public')
                ->cannotBeEmpty()
            ->end()
            ->integerNode('max_pixels')
                ->info('Au-delà (largeur × hauteur), l\'original est servi sans traitement (protection de la mémoire).')
                ->defaultValue(40_000_000)->min(1)
            ->end()
            ->enumNode('format')
                ->info('Format de sortie par défaut des profils (auto : WebP si le serveur le gère, sinon PNG ou JPEG).')
                ->values($formats)->defaultValue(OutputFormat::AUTO->value)
            ->end()
            ->integerNode('quality')
                ->info('Qualité de compression par défaut des profils (1 à 100 ; PNG : niveau de compression sans perte).')
                ->defaultValue(82)->min(1)->max(100)
            ->end()
            ->arrayNode('http_cache')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('max_age')->info('Durée de cache navigateur des images (secondes).')->defaultValue(3600)->min(0)->end()
                    ->booleanNode('public')->info('Cache partagé (proxy, CDN) : seulement si les images sont publiques.')->defaultFalse()->end()
                ->end()
            ->end()
            ->arrayNode('profiles')
                ->info('Profils de traitement, par nom (lettres minuscules, chiffres, « _ », « - »). Remplacent les profils par défaut.')
                ->useAttributeAsKey('name')
                ->defaultValue(self::DEFAULT_PROFILES)
                ->arrayPrototype()
                    ->children()
                        ->integerNode('width')->info('Largeur maximale (pixels).')->isRequired()->min(1)->end()
                        ->integerNode('height')->info('Hauteur maximale (pixels).')->isRequired()->min(1)->end()
                        ->enumNode('mode')->info('cover : recadrage centré aux proportions du cadre ; contain : image entière.')->values($modes)->defaultValue(ResizeMode::COVER->value)->end()
                        ->enumNode('format')->info('Format de sortie (par défaut : option format).')->values($formats)->defaultNull()->end()
                        ->integerNode('quality')->info('Qualité (par défaut : option quality).')->min(1)->max(100)->defaultNull()->end()
                    ->end()
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $profiles): bool => [] !== array_filter(array_keys($profiles), static fn (int|string $name): bool => 1 !== preg_match('/^[a-z0-9][a-z0-9_-]*$/', (string) $name)))
                    ->thenInvalid('Noms de profils invalides dans %s : lettres minuscules, chiffres, « _ » et « - » seulement.')
                ->end()
            ->end();
    }

    /**
     * @param array{cache_directory: string, source_directory: string, max_pixels: int, format: string, quality: int, http_cache: array{max_age: int, public: bool}, profiles: array<string, array{width: int, height: int, mode?: string, format?: string|null, quality?: int|null}>} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $profiles = [];
        foreach ($config['profiles'] as $name => $profile) {
            $profiles[$name] = [
                'width' => $profile['width'],
                'height' => $profile['height'],
                'mode' => $profile['mode'] ?? ResizeMode::COVER->value,
                'format' => $profile['format'] ?? $config['format'],
                'quality' => $profile['quality'] ?? $config['quality'],
            ];
        }
        // Erreur de profil signalée à la compilation du conteneur plutôt qu'au premier affichage.
        ProfileRegistry::fromConfig($profiles);

        $services = $container->services();
        $services->set(ProfileRegistry::class)->factory([ProfileRegistry::class, 'fromConfig'])->args([$profiles]);
        $services->set(GdImageProcessor::class)->args([$config['max_pixels']]);
        $services->set(VariantStorage::class)->args([$config['cache_directory']]);
        $services->set(ImageVariantGenerator::class)->args([service(ProfileRegistry::class), service(GdImageProcessor::class), service(VariantStorage::class)]);
        $services->set(ImageResponseFactory::class)
            ->args([service(ImageVariantGenerator::class), $config['http_cache']['max_age'], $config['http_cache']['public'], service('logger')->nullOnInvalid()])
            ->tag('monolog.logger', ['channel' => 'dsfr_card_image']);
        $services->set(ImageController::class)
            ->args([service(ImageResponseFactory::class), $config['source_directory']])
            ->public()
            ->tag('controller.service_arguments');
        $services->set(PathUrlResolver::class)
            ->args([service('router')])
            ->tag(ImageUrlResolverInterface::TAG, ['priority' => -100]);
        $services->set(ImageUrlGenerator::class)->args([service(ProfileRegistry::class), tagged_iterator(ImageUrlResolverInterface::TAG)]);
        $builder->registerForAutoconfiguration(ImageUrlResolverInterface::class)->addTag(ImageUrlResolverInterface::TAG);

        if (class_exists(AbstractExtension::class)) {
            $services->set(CardImageExtension::class)->args([service(ImageUrlGenerator::class)])->tag('twig.extension');
        }
        if (class_exists(Command::class)) {
            $services->set(PurgeCommand::class)->args([service(VariantStorage::class), service(ProfileRegistry::class)])->tag('console.command');
        }
    }
}
