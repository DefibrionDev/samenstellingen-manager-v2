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
        foreach ($rows as $r) {
            $table[] = [
                $r->baseAfasItemcode,
                $r->groupName,
                $r->languageCode,
                $r->expectedSticker,
                $r->actualStickers === [] ? '(geen)' : implode(', ', $r->actualStickers),
            ];
        }
        $io->section(sprintf('%d base(s) met sticker-taal-mismatch', count($rows)));
        $io->table(['Itemcode', 'Groep', 'Taal', 'Verwacht', 'Werkelijk'], $table);

        return Command::FAILURE;
    }
}
