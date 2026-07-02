<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\NormalizeBaseNames;
use Defibrion\Samenstellingen\Application\Group\NormalizeBaseNamesHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'base:normalize-names',
    description: 'Zet de lokale base-namen van een groep naar de canonieke template-naam. Raakt AFAS niet aan (gebruik names:fix-drift voor AFAS).',
)]
final class NormalizeBaseNamesCommand extends Command
{
    public function __construct(private readonly NormalizeBaseNamesHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'family-head',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'Family-head itemcode(s) van de groep(en) (bv. 52119 52120)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var list<string> $familyHeads */
        $familyHeads = $input->getArgument('family-head');

        $result = ($this->handler)(new NormalizeBaseNames($familyHeads));

        if ($result->renamed !== []) {
            $rows = [];
            foreach ($result->renamed as $r) {
                $rows[] = [$r['afasItemcode'], $this->ellipsize($r['old'], 55), $this->ellipsize($r['new'], 55)];
            }
            $io->section(sprintf('%d base-na(a)m(en) genormaliseerd (alleen lokaal)', count($result->renamed)));
            $io->table(['Itemcode', 'Oud', 'Canonical'], $rows);
        } else {
            $io->success('Alle base-namen zijn al canonical.');
        }

        foreach ($result->skipped as $reason) {
            $io->warning($reason);
        }

        if ($result->renamed !== []) {
            $io->writeln('<comment>Let op: afas:pull spiegelt base-namen terug uit AFAS — de lokale namen blijven pas staan nadat AFAS zelf hernoemd is (names:fix-drift --apply).</comment>');
        }

        return $result->skipped === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function ellipsize(string $s, int $max): string
    {
        return mb_strlen($s) <= $max ? $s : mb_substr($s, 0, $max - 1) . '…';
    }
}
