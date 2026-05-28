<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\BeginDateLookup;

/**
 * Test-double: hardcoded mapping van (itemcode, prijslijst, staffel) → begindatum.
 */
final class InMemoryBeginDateLookup implements BeginDateLookup
{
    /** @var array<string, string> */
    private array $entries = [];

    public function set(string $itemcode, string $prijslijstId, ?int $staffelAantal, string $beginDate): void
    {
        $this->entries[$this->key($itemcode, $prijslijstId, $staffelAantal)] = $beginDate;
    }

    public function find(string $itemcode, string $prijslijstId, ?int $staffelAantal): ?string
    {
        return $this->entries[$this->key($itemcode, $prijslijstId, $staffelAantal)] ?? null;
    }

    private function key(string $itemcode, string $prijslijstId, ?int $staffelAantal): string
    {
        return $itemcode . '|' . $prijslijstId . '|' . ($staffelAantal ?? 0);
    }
}
