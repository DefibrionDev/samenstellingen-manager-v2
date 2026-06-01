<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'website:list',
    description: 'Toon alle geregistreerde websites met hun vrije-veld-UUIDs. Optioneel exporteer naar CSV.',
)]
final class ListWebsitesCommand extends Command
{
    public function __construct(private readonly WebsiteRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('csv', null, InputOption::VALUE_REQUIRED, 'Schrijf de tabel als CSV naar dit pad i.p.v. naar stdout.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $websites = $this->repository->findAll();

        if ($websites === []) {
            $io->writeln('<comment>Geen websites geregistreerd.</comment>');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($websites as $website) {
            $rows[] = [$website->name, $website->ffSyncUuid, $website->ffTonenUuid];
        }

        $csvOption = $input->getOption('csv');
        if (is_string($csvOption) && $csvOption !== '') {
            $this->writeCsv($csvOption, $rows);
            $io->success(sprintf('%d website(s) geëxporteerd naar %s.', count($websites), $csvOption));

            return Command::SUCCESS;
        }

        $io->table(['Naam', 'FF Sync UUID', 'FF Tonen UUID'], $rows);
        $io->writeln(sprintf('<info>%d website(s).</info>', count($websites)));

        return Command::SUCCESS;
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $fh = fopen($path, 'w');
        if ($fh === false) {
            throw new RuntimeException(sprintf('Kan CSV-bestand niet openen voor schrijven: %s', $path));
        }
        fputcsv($fh, ['name', 'ff_sync_uuid', 'ff_tonen_uuid'], ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($fh, $row, ',', '"', '\\');
        }
        fclose($fh);
    }
}
