<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yassrzg\DsfrCardImageBundle\Exception\UnknownProfileException;
use Yassrzg\DsfrCardImageBundle\Profile\ProfileRegistry;
use Yassrzg\DsfrCardImageBundle\Storage\VariantStorage;

/**
 * Supprime les images générées (toutes ou celles d'un profil) : elles sont recréées à la demande, avec les réglages
 * actuels. Les originaux ne sont jamais touchés.
 */
#[AsCommand(name: 'dsfr-card-image:purge', description: 'Supprime les images générées (recréées à la demande)')]
final class PurgeCommand extends Command
{
    public function __construct(
        private readonly VariantStorage $storage,
        private readonly ProfileRegistry $profiles,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('profile', InputArgument::OPTIONAL, 'Profil à purger (par défaut : tous)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $profile = $input->getArgument('profile');
        if (\is_string($profile)) {
            try {
                $this->profiles->get($profile);
            } catch (UnknownProfileException $e) {
                $io->error($e->getMessage());

                return Command::INVALID;
            }
        }
        $count = $this->storage->purge(\is_string($profile) ? $profile : null);
        $io->success(\sprintf('%d image(s) générée(s) supprimée(s) dans %s%s.', $count, $this->storage->directory(), \is_string($profile) ? '/'.$profile : ''));

        return Command::SUCCESS;
    }
}
