<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Publications;

use Defibrion\Samenstellingen\Application\Publications\AfasFreeFieldStateReader;

final readonly class InMemoryAfasFreeFieldStateReader implements AfasFreeFieldStateReader
{
    /**
     * @param array<int|string, array<string, bool>> $state PHP cast numerieke string-keys naar int — terug naar string bij readAll().
     */
    public function __construct(private array $state)
    {
    }

    public function readAll(): array
    {
        $result = [];
        foreach ($this->state as $itemcode => $flags) {
            $result[(string) $itemcode] = $flags;
        }

        return $result;
    }
}
