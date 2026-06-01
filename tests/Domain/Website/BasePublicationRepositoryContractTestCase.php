<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Website;

use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Website\BasePublicationRepository;
use Defibrion\Samenstellingen\Domain\Website\Website;
use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class BasePublicationRepositoryContractTestCase extends TestCase
{
    /**
     * @return array{groups: GroupRepository, bases: GroupBaseRepository, websites: WebsiteRepository, publications: BasePublicationRepository}
     */
    abstract protected function makeRepositories(): array;

    private GroupRepository $groups;
    private GroupBaseRepository $bases;
    private WebsiteRepository $websites;
    private BasePublicationRepository $publications;

    protected function setUp(): void
    {
        $repos = $this->makeRepositories();
        $this->groups = $repos['groups'];
        $this->bases = $repos['bases'];
        $this->websites = $repos['websites'];
        $this->publications = $repos['publications'];

        $this->groups->save(new Group('Reanibex 100', '52112'));
    }

    #[Test]
    public function setPublishedCreatesNewRow(): void
    {
        $base = $this->bases->saveForGroup('52112', new GroupBase(null, 'NL', 'NL', '11142'));
        $website = $this->websites->save(new Website(null, 'Reseller NL', 'U1', 'U2'));
        self::assertNotNull($base->id);
        self::assertNotNull($website->id);

        $pub = $this->publications->setPublished($base->id, $website->id, true);

        self::assertNotNull($pub->id);
        self::assertTrue($pub->published);
    }

    #[Test]
    public function setPublishedIsUpsert(): void
    {
        $base = $this->bases->saveForGroup('52112', new GroupBase(null, 'NL', 'NL', '11142'));
        $website = $this->websites->save(new Website(null, 'Reseller NL', 'U1', 'U2'));
        self::assertNotNull($base->id);
        self::assertNotNull($website->id);

        $first = $this->publications->setPublished($base->id, $website->id, true);
        $second = $this->publications->setPublished($base->id, $website->id, false);

        self::assertSame($first->id, $second->id);
        self::assertFalse($second->published);
        self::assertCount(1, $this->publications->findAllForBase($base->id));
    }

    #[Test]
    public function findReturnsNullWhenNoRow(): void
    {
        self::assertNull($this->publications->find(9999, 8888));
    }

    #[Test]
    public function findAllForBaseAndForWebsite(): void
    {
        $b1 = $this->bases->saveForGroup('52112', new GroupBase(null, 'NL', 'NL', '11142'));
        $b2 = $this->bases->saveForGroup('52112', new GroupBase(null, 'FR', 'FR', '11142-FR'));
        $w1 = $this->websites->save(new Website(null, 'Reseller NL', 'U1', 'U2'));
        $w2 = $this->websites->save(new Website(null, 'Reseller FR', 'U3', 'U4'));
        self::assertNotNull($b1->id);
        self::assertNotNull($b2->id);
        self::assertNotNull($w1->id);
        self::assertNotNull($w2->id);

        $this->publications->setPublished($b1->id, $w1->id, true);
        $this->publications->setPublished($b1->id, $w2->id, false);
        $this->publications->setPublished($b2->id, $w1->id, true);

        self::assertCount(2, $this->publications->findAllForBase($b1->id));
        self::assertCount(1, $this->publications->findAllForBase($b2->id));
        self::assertCount(2, $this->publications->findAllForWebsite($w1->id));
    }
}
