<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Afas;

use Defibrion\Samenstellingen\Domain\Afas\AfasArticle;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticleRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class AfasArticleRepositoryContractTestCase extends TestCase
{
    abstract protected function makeRepository(): AfasArticleRepository;

    #[Test]
    public function findByItemcodeReturnsNullWhenEmpty(): void
    {
        self::assertNull($this->makeRepository()->findByItemcode('50013'));
    }

    #[Test]
    public function replaceSnapshotStoresAllArticles(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot([
            new AfasArticle('50013', 'AED Nederlands'),
            new AfasArticle('70112', 'Reanimatiekit'),
        ]);

        $found = $repo->findByItemcode('70112');

        self::assertNotNull($found);
        self::assertSame('Reanimatiekit', $found->name);
        self::assertSame(2, $repo->countSnapshot());
    }

    #[Test]
    public function replaceSnapshotIsIdempotentAndReplaces(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot([new AfasArticle('50013', 'Oude naam')]);
        $repo->replaceSnapshot([new AfasArticle('50013', 'Nieuwe naam')]);

        $found = $repo->findByItemcode('50013');
        self::assertNotNull($found);
        self::assertSame('Nieuwe naam', $found->name);
        self::assertSame(1, $repo->countSnapshot());
    }

    #[Test]
    public function replaceSnapshotWithEmptyListClearsTable(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot([new AfasArticle('50013', 'AED Nederlands')]);
        $repo->replaceSnapshot([]);

        self::assertSame(0, $repo->countSnapshot());
        self::assertNull($repo->findByItemcode('50013'));
    }
}
