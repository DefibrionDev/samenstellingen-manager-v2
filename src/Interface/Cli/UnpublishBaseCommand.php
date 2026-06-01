<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Website\BasePublicationRepository;
use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'base:unpublish',
    description: 'Zet publicatie van een base op een website uit. Accessoire-varianten volgen.',
)]
final class UnpublishBaseCommand extends Command
{
    public function __construct(
        private readonly GroupBaseRepository $bases,
        private readonly WebsiteRepository $websites,
        private readonly BasePublicationRepository $publications,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('afas-itemcode', InputArgument::REQUIRED, 'AFAS-itemcode van de base')
            ->addArgument('website-naam', InputArgument::REQUIRED, 'Naam van een geregistreerde website');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return PublishBaseSupport::run($input, $output, $this->bases, $this->websites, $this->publications, false);
    }
}
