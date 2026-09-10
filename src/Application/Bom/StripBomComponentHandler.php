<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Bom;

use Defibrion\Samenstellingen\Domain\Bom\BomLine;
use Defibrion\Samenstellingen\Domain\Bom\BomLineReader;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;

/**
 * Strip één BOM-component uit alle samenstellingen waar hij voorkomt.
 *
 * Dry-run levert het plan: welke AFAS-regels geraakt worden + hoeveel
 * `group_base_items`-rijen zouden verdwijnen. Apply doet eerst de tool-side
 * DELETE (één SQL), daarna per AFAS-regel een PUT FbComposition met
 * @Action=delete. Failures per regel verzameld, niet aborten — zo blijft de
 * rest van de batch lopen.
 */
final readonly class StripBomComponentHandler
{
    public function __construct(
        private BomLineReader $reader,
        private BomComponentStripWriter $writer,
        private GroupBaseItemRepository $baseItems,
    ) {
    }

    public function __invoke(StripBomComponent $command): StripBomComponentResult
    {
        $lines = $this->reader->findLinesByBomItemcode($command->bomItemcode);
        $skipped = 0;
        if ($command->onlyWith !== []) {
            $inScope = $this->samenstellingenWithAnyOf($command->onlyWith);
            $before = count($lines);
            $lines = array_values(array_filter(
                $lines,
                static fn (BomLine $l): bool => isset($inScope[$l->samenstellingItemcode]),
            ));
            $skipped = $before - count($lines);
        }
        // Veiligheidsgordel (incident 11167, 10 sep 2026): AFAS matcht de
        // FbCompositionPart-delete op PrSe alléén. Een regel waarvan de PrSe
        // binnen z'n samenstelling niet uniek is, wordt niet gestript.
        [$lines, $unsafe] = $this->splitUnsafe($lines);
        if ($command->limit !== null) {
            $lines = array_slice($lines, 0, $command->limit);
        }

        if (!$command->apply) {
            return new StripBomComponentResult($lines, 0, 0, [], $skipped, $unsafe);
        }

        // Tool-side registraties volgen dezelfde scope als het AFAS-plan: bij
        // --only-with alleen de bases die in het plan zitten, anders alles.
        $toolDeleted = $command->onlyWith === []
            ? $this->baseItems->deleteByItemcode($command->bomItemcode)
            : $this->baseItems->deleteByItemcodeForBases(
                $command->bomItemcode,
                array_values(array_unique(array_map(
                    static fn (BomLine $l): string => $l->samenstellingItemcode,
                    $lines,
                ))),
            );

        $applied = 0;
        $failures = [];
        foreach ($lines as $line) {
            try {
                $this->writer->apply($line);
                ++$applied;
            } catch (BomComponentStripFailedException $e) {
                $failures[] = ['line' => $line, 'error' => $e->getMessage()];
            }
        }

        return new StripBomComponentResult($lines, $toolDeleted, $applied, $failures, $skipped, $unsafe);
    }

    /**
     * @param list<BomLine> $lines
     * @return array{0: list<BomLine>, 1: list<BomLine>} [veilig, onveilig]
     */
    private function splitUnsafe(array $lines): array
    {
        if ($lines === []) {
            return [[], []];
        }
        $perPrSe = [];
        foreach ($this->reader->findAllLines() as $l) {
            $perPrSe[$l->samenstellingItemcode][$l->prSe] = ($perPrSe[$l->samenstellingItemcode][$l->prSe] ?? 0) + 1;
        }
        $safe = [];
        $unsafe = [];
        foreach ($lines as $l) {
            if (($perPrSe[$l->samenstellingItemcode][$l->prSe] ?? 1) > 1) {
                $unsafe[] = $l;
            } else {
                $safe[] = $l;
            }
        }

        return [$safe, $unsafe];
    }

    /**
     * @param list<string> $componentItemcodes
     * @return array<string, true> samenstelling-itemcodes die minstens één van de componenten bevatten
     */
    private function samenstellingenWithAnyOf(array $componentItemcodes): array
    {
        $found = [];
        foreach ($componentItemcodes as $component) {
            foreach ($this->reader->findLinesByBomItemcode($component) as $line) {
                $found[$line->samenstellingItemcode] = true;
            }
        }

        return $found;
    }
}
