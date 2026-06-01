<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Website\Website;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryBasePublicationRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWebsiteRepository;
use Defibrion\Samenstellingen\Interface\Cli\PublishBaseCommand;
use Defibrion\Samenstellingen\Interface\Cli\UnpublishBaseCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PublishBaseCommandTest extends TestCase
{
    #[Test]
    public function publishCreatesPublication(): void
    {
        $bag = $this->makeRepos();
        $base = $bag['bases']->saveForGroup('52112', new GroupBase(null, 'NL', 'NL', '11142'));
        $website = $bag['websites']->save(new Website(null, 'Reseller NL', 'U1', 'U2'));
        self::assertNotNull($base->id);
        self::assertNotNull($website->id);

        $tester = new CommandTester(new PublishBaseCommand($bag['bases'], $bag['websites'], $bag['publications']));
        $exit = $tester->execute(['afas-itemcode' => '11142', 'website-naam' => 'Reseller NL']);

        self::assertSame(Command::SUCCESS, $exit);
        $pub = $bag['publications']->find($base->id, $website->id);
        self::assertNotNull($pub);
        self::assertTrue($pub->published);
    }

    #[Test]
    public function unpublishUpdatesExistingPublication(): void
    {
        $bag = $this->makeRepos();
        $base = $bag['bases']->saveForGroup('52112', new GroupBase(null, 'NL', 'NL', '11142'));
        $website = $bag['websites']->save(new Website(null, 'Reseller NL', 'U1', 'U2'));
        self::assertNotNull($base->id);
        self::assertNotNull($website->id);
        $bag['publications']->setPublished($base->id, $website->id, true);

        $tester = new CommandTester(new UnpublishBaseCommand($bag['bases'], $bag['websites'], $bag['publications']));
        $exit = $tester->execute(['afas-itemcode' => '11142', 'website-naam' => 'Reseller NL']);

        self::assertSame(Command::SUCCESS, $exit);
        $pub = $bag['publications']->find($base->id, $website->id);
        self::assertNotNull($pub);
        self::assertFalse($pub->published);
    }

    #[Test]
    public function publishFailsForUnknownWebsite(): void
    {
        $bag = $this->makeRepos();
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'NL', 'NL', '11142'));

        $tester = new CommandTester(new PublishBaseCommand($bag['bases'], $bag['websites'], $bag['publications']));
        $exit = $tester->execute(['afas-itemcode' => '11142', 'website-naam' => 'Onbekend']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('niet gevonden', $tester->getDisplay());
    }

    #[Test]
    public function publishFailsForUnknownBase(): void
    {
        $bag = $this->makeRepos();
        $bag['websites']->save(new Website(null, 'Reseller NL', 'U1', 'U2'));

        $tester = new CommandTester(new PublishBaseCommand($bag['bases'], $bag['websites'], $bag['publications']));
        $exit = $tester->execute(['afas-itemcode' => '99999', 'website-naam' => 'Reseller NL']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Geen base gevonden', $tester->getDisplay());
    }

    /**
     * @return array{bases: InMemoryGroupBaseRepository, websites: InMemoryWebsiteRepository, publications: InMemoryBasePublicationRepository}
     */
    private function makeRepos(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $websites = new InMemoryWebsiteRepository();
        $publications = new InMemoryBasePublicationRepository();
        $groups->save(new Group('Reanibex', '52112'));

        return ['bases' => $bases, 'websites' => $websites, 'publications' => $publications];
    }
}
