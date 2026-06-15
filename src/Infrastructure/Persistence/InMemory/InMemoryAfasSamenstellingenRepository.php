<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Afas\DuplicateDetector;

final class InMemoryAfasSamenstellingenRepository implements AfasSamenstellingenRepository
{
    /** @var list<AfasSamenstelling> */
    private array $samenstellingen = [];

    private DuplicateDetector $detector;

    public function __construct()
    {
        $this->detector = new DuplicateDetector();
    }

    public function replaceSnapshot(array $samenstellingen): void
    {
        $this->samenstellingen = $this->detector->annotate($samenstellingen);
    }

    public function updateProductTypes(array $productTypes): void
    {
        $byItemcode = [];
        foreach ($productTypes as $productType) {
            $byItemcode[$productType->itemcode] = $productType;
        }

        $this->samenstellingen = array_map(
            static function (AfasSamenstelling $s) use ($byItemcode): AfasSamenstelling {
                $update = $byItemcode[$s->itemcode] ?? null;
                if ($update === null) {
                    return $s;
                }

                return new AfasSamenstelling(
                    $s->itemcode,
                    $s->name,
                    $s->itemcodeParent,
                    $s->bomItemcodes,
                    $s->duplicateOfItemcode,
                    $s->cbsCode,
                    $update->productType01,
                    $update->productType02,
                );
            },
            $this->samenstellingen,
        );
    }

    public function findAll(): array
    {
        return $this->samenstellingen;
    }

    public function findAllCanonical(): array
    {
        return array_values(array_filter(
            $this->samenstellingen,
            static fn (AfasSamenstelling $s): bool => $s->isCanonical(),
        ));
    }

    public function findAllDuplicates(): array
    {
        return array_values(array_filter(
            $this->samenstellingen,
            static fn (AfasSamenstelling $s): bool => !$s->isCanonical(),
        ));
    }

    public function countSnapshot(): int
    {
        return count($this->samenstellingen);
    }

    public function findByItemcode(string $itemcode): ?AfasSamenstelling
    {
        foreach ($this->samenstellingen as $samenstelling) {
            if ($samenstelling->itemcode === $itemcode) {
                return $samenstelling;
            }
        }

        return null;
    }
}
