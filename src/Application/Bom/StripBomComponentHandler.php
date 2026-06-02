<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Bom;

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
        if ($command->limit !== null) {
            $lines = array_slice($lines, 0, $command->limit);
        }

        if (!$command->apply) {
            return new StripBomComponentResult($lines, 0, 0, []);
        }

        $toolDeleted = $this->baseItems->deleteByItemcode($command->bomItemcode);

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

        return new StripBomComponentResult($lines, $toolDeleted, $applied, $failures);
    }
}
