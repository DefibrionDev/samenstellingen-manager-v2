<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'base:set-language',
    description: 'Wijzig de language_code van een base. Geldige codes: NL, FR, EN, DE, DK, WAL, of compound (NL/FR, NL/EN/FR, …).',
)]
final class SetBaseLanguageCommand extends Command
{
    public function __construct(private readonly GroupBaseRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('afas-itemcode', InputArgument::REQUIRED, 'AFAS-itemcode van de base (bv. 11111)')
            ->addArgument('language', InputArgument::REQUIRED, 'Nieuwe taal-code (bv. NL, FR, NL/FR, NL/EN/FR)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $afasItemcode = trim((string) $input->getArgument('afas-itemcode'));
        $language = (string) $input->getArgument('language');

        try {
            $count = $this->repository->setLanguageCodeByAfasItemcode($afasItemcode, $language);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        if ($count === 0) {
            $io->error(sprintf("Geen base gevonden met afas_itemcode '%s'.", $afasItemcode));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            "%d base(s) met afas_itemcode '%s' gezet op taal '%s'.",
            $count,
            $afasItemcode,
            trim($language),
        ));

        return Command::SUCCESS;
    }
}
