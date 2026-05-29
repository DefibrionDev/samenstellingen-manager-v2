<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'base:set-variant-label',
    description: 'Zet (of wis met lege string) het variant_label op een base — wordt in canonical-naam tussen <model> en <taal-suffix> geplakt (bv. 4G, WiFi).',
)]
final class SetBaseVariantLabelCommand extends Command
{
    public function __construct(private readonly GroupBaseRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('afas-itemcode', InputArgument::REQUIRED, 'AFAS-itemcode van de base (bv. 21018-DE)')
            ->addArgument('label', InputArgument::REQUIRED, "Label (bv. '4G', 'WiFi'). Lege string ('') = wissen.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $afasItemcode = trim((string) $input->getArgument('afas-itemcode'));
        $label = (string) $input->getArgument('label');

        $normalized = trim($label) === '' ? null : trim($label);
        $count = $this->repository->setVariantLabelByAfasItemcode($afasItemcode, $normalized);

        if ($count === 0) {
            $io->error(sprintf("Geen base gevonden met afas_itemcode '%s'.", $afasItemcode));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            "%d base(s) met afas_itemcode '%s' %s.",
            $count,
            $afasItemcode,
            $normalized === null ? 'gewist (label is nu leeg)' : "gezet op label '$normalized'",
        ));

        return Command::SUCCESS;
    }
}
