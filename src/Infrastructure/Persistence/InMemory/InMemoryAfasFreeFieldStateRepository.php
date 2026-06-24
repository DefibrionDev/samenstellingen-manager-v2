<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Application\Publications\AfasFreeFieldStateRepository;

final class InMemoryAfasFreeFieldStateRepository implements AfasFreeFieldStateRepository
{
    /** @var array<string, array<string, bool>> */
    private array $state = [];

    /**
     * @param array<int|string, array<string, bool>> $state
     */
    public function __construct(array $state = [])
    {
        $this->replaceSnapshot($state);
    }

    public function replaceSnapshot(array $state): void
    {
        $normalized = [];
        foreach ($state as $itemcode => $flags) {
            $normalized[(string) $itemcode] = $flags;
        }
        $this->state = $normalized;
    }

    public function readAll(): array
    {
        return $this->state;
    }
}
