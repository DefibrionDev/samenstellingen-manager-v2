<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Publications;

use Defibrion\Samenstellingen\Application\Publications\AfasFreeFieldStateRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class AfasFreeFieldStateRepositoryContractTestCase extends TestCase
{
    abstract protected function makeRepository(): AfasFreeFieldStateRepository;

    #[Test]
    public function roundTripsStatePerItemcodeAndUuid(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot([
            '11111' => ['U_NL_SYNC' => true, 'U_NL_TONEN' => false],
            '11111-60110' => ['U_ARKY_SYNC' => true, 'U_ARKY_TONEN' => true],
        ]);

        self::assertEquals([
            '11111' => ['U_NL_SYNC' => true, 'U_NL_TONEN' => false],
            '11111-60110' => ['U_ARKY_SYNC' => true, 'U_ARKY_TONEN' => true],
        ], $repo->readAll());
    }

    #[Test]
    public function replaceSnapshotClearsPriorState(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot(['11111' => ['U_NL_SYNC' => true]]);
        $repo->replaceSnapshot(['22222' => ['U_NL_SYNC' => false]]);

        self::assertEquals(['22222' => ['U_NL_SYNC' => false]], $repo->readAll());
    }

    #[Test]
    public function emptySnapshotReadsAsEmpty(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot([]);

        self::assertSame([], $repo->readAll());
    }
}
