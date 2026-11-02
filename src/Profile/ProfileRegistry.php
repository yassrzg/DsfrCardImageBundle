<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Profile;

use Yassrzg\DsfrCardImageBundle\Exception\UnknownProfileException;

/**
 * Profils déclarés dans la configuration (clé dsfr_card_image.profiles).
 */
final readonly class ProfileRegistry
{
    /** @var array<string, ImageProfile> */
    private array $profiles;

    /**
     * @param iterable<ImageProfile> $profiles
     */
    public function __construct(iterable $profiles)
    {
        $byName = [];
        foreach ($profiles as $profile) {
            $byName[$profile->name] = $profile;
        }
        $this->profiles = $byName;
    }

    /**
     * @param array<string, array{width: int, height: int, mode: string, format: string, quality: int}> $config
     */
    public static function fromConfig(array $config): self
    {
        $profiles = [];
        foreach ($config as $name => $options) {
            $profiles[] = new ImageProfile((string) $name, $options['width'], $options['height'], ResizeMode::from($options['mode']), OutputFormat::from($options['format']), $options['quality']);
        }

        return new self($profiles);
    }

    public function has(string $name): bool
    {
        return isset($this->profiles[$name]);
    }

    /**
     * @throws UnknownProfileException
     */
    public function get(string $name): ImageProfile
    {
        return $this->profiles[$name] ?? throw new UnknownProfileException(\sprintf('Profil d\'image « %s » inconnu (profils déclarés : %s).', $name, implode(', ', array_keys($this->profiles)) ?: 'aucun'));
    }

    /** @return array<string, ImageProfile> */
    public function all(): array
    {
        return $this->profiles;
    }
}
