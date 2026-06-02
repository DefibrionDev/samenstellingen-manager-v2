<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\AuditStickers;
use Defibrion\Samenstellingen\Application\Audit\StickerAuditHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:stickers',
    description: 'Detecteer bases waar de stickerset (81xxx) niet matcht met de taal-code (eerste taal-token telt voor compound).',
)]
final class AuditStickersCommand extends Command
{
    public function __construct(private readonly StickerAuditHandler $handler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = ($this->handler)(new AuditStickers());

        if ($rows === []) {
            $io->success('Geen sticker-drift — alle bases hebben de juiste stickerset voor hun taal.');

            return Command::SUCCESS;
        }

        $table = [];
        $expectedSet = [];
        foreach ($rows as $r) {
            $table[] = [
                $r->baseAfasItemcode,
                $r->groupName,
                $r->languageCode,
                $r->expectedSticker,
                $r->actualStickers === [] ? '(geen)' : implode(', ', $r->actualStickers),
            ];
            $expectedSet[$r->expectedSticker] = true;
        }
        $io->section(sprintf('%d base(s) met sticker-taal-mismatch', count($rows)));
        $io->table(['Itemcode', 'Groep', 'Taal', 'Verwacht', 'Werkelijk'], $table);

        // Als ALLE drift exact één gemeenschappelijke verwachte sticker mist,
        // is de oorzaak vermoedelijk een `bom:strip-component` (bv. tijdelijk
        // uit voorraad). Wijs de gebruiker naar het tegen-commando.
        if (count($expectedSet) === 1) {
            $sticker = array_key_first($expectedSet);
            $io->writeln(sprintf(
                '<comment>Alle %d drift-rijen missen alleen stickerset %s — gebruik `bin/samenstellingen stickers:restore` wanneer de voorraad terug is.</comment>',
                count($rows),
                (string) $sticker,
            ));
        }

        return Command::FAILURE;
    }
}
