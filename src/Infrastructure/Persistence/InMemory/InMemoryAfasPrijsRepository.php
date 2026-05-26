<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijsRepository;

final class InMemoryAfasPrijsRepository implements AfasPrijsRepository
{
    /** @var list<AfasPrijs> */
    private array $prijzen = [];

    public function replaceSnapshot(array $prijzen): void
    {
        $this->prijzen = $prijzen;
    }

    public function findByItemcode(string $itemcode): array
    {
        return array_values(array_filter(
            $this->prijzen,
            static fn (AfasPrijs $p): bool => $p->itemcode === $itemcode,
        ));
    }

    public function countSnapshot(): int
    {
        return count($this->prijzen);
    }
}
