<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'group:list',
    description: 'Toon alle geregistreerde groepen met family-head, naam en aantal bases. Optioneel exporteer naar CSV.',
)]
final class ListGroupsCommand extends Command
{
    public function __construct(
        private readonly GroupRepository $groups,
        private readonly GroupBaseRepository $bases,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('csv', null, InputOption::VALUE_REQUIRED, 'Schrijf de tabel als CSV naar dit pad i.p.v. naar stdout.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $groups = $this->groups->findAll();

        if ($groups === []) {
            $io->writeln('<comment>Geen groepen geregistreerd.</comment>');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($groups as $group) {
            $rows[] = [
                $group->familyHeadItemcode,
                $group->name,
                $group->modelNameNl ?? '',
                (string) count($this->bases->findAllForGroup($group->familyHeadItemcode)),
            ];
        }

        $csvOption = $input->getOption('csv');
        if (is_string($csvOption) && $csvOption !== '') {
            $this->writeCsv($csvOption, $rows);
            $io->success(sprintf('%d groep(en) geëxporteerd naar %s.', count($groups), $csvOption));

            return Command::SUCCESS;
        }

        // Voor tabel-weergave alleen — vervang lege model-naam door en-dash.
        $displayRows = array_map(static fn (array $r): array => [$r[0], $r[1], $r[2] === '' ? '—' : $r[2], $r[3]], $rows);
        $io->table(['Family-head', 'Naam', 'Model (NL)', 'Bases'], $displayRows);
        $io->writeln(sprintf('<info>%d groep(en).</info>', count($groups)));

        return Command::SUCCESS;
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $fh = fopen($path, 'w');
        if ($fh === false) {
            throw new RuntimeException(sprintf('Kan CSV-bestand niet openen voor schrijven: %s', $path));
        }
        fputcsv($fh, ['family_head', 'name', 'model_name_nl', 'bases_count'], ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($fh, $row, ',', '"', '\\');
        }
        fclose($fh);
    }
}
