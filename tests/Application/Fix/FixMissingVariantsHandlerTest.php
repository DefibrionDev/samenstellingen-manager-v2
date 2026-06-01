<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\ListMissingVariantsHandler;
use Defibrion\Samenstellingen\Application\Fix\FixMissingVariants;
use Defibrion\Samenstellingen\Application\Fix\FixMissingVariantsHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Domain\Naming\VariantNamingPolicy;
use Defibrion\Samenstellingen\Infrastructure\Fix\InMemoryVariantFixMissingWriter;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FixMissingVariantsHandlerTest extends TestCase
{
    #[Test]
    public function dryRunCollectsPlansButDoesNotWrite(): void
    {
        $bag = $this->wiringWithOneMissingVariant();

        $result = ($bag['handler'])(new FixMissingVariants(apply: false));

        self::assertCount(1, $result->plans);
        self::assertSame(0, $result->appliedCount);
        self::assertSame([], $bag['writer']->applied);
        self::assertSame('11111-60110', $result->plans[0]->afasItemcode);
        self::assertStringContainsString('AED Pakket', $result->plans[0]->canonicalName);
    }

    #[Test]
    public function applyWritesAllPlans(): void
    {
        $bag = $this->wiringWithOneMissingVariant();

        $result = ($bag['handler'])(new FixMissingVariants(apply: true));

        self::assertSame(1, $result->appliedCount);
        self::assertCount(1, $bag['writer']->applied);
        self::assertSame('11111-60110', $bag['writer']->applied[0]->afasItemcode);
    }

    #[Test]
    public function skipsRowsThatAlreadyExistInAfas(): void
    {
        // verwachteSku 11111-60110 zit al in afas_samenstellingen → skip
        $bag = $this->wiringWithOneMissingVariant(addAfasVariantForSku: '11111-60110');

        $result = ($bag['handler'])(new FixMissingVariants(apply: true));

        self::assertCount(0, $result->plans);
        self::assertSame(0, $result->appliedCount);
    }

    #[Test]
    public function groupFilterSkipsOtherGroups(): void
    {
        $bag = $this->wiringWithOneMissingVariant();

        $result = ($bag['handler'])(new FixMissingVariants(apply: false, familyHeadItemcode: '99999'));

        self::assertSame([], $result->plans);
    }

    #[Test]
    public function limitTruncatesPlans(): void
    {
        $bag = $this->wiringWithTwoMissingVariants();

        $result = ($bag['handler'])(new FixMissingVariants(apply: false, limit: 1));

        self::assertCount(1, $result->plans);
    }

    #[Test]
    public function failureDoesNotBlockOtherPlans(): void
    {
        $bag = $this->wiringWithTwoMissingVariants(failOn: '11111-60110');

        $result = ($bag['handler'])(new FixMissingVariants(apply: true));

        self::assertSame(1, $result->appliedCount);
        self::assertCount(1, $result->failures);
        self::assertSame('11111-60110', $result->failures[0]['plan']->afasItemcode);
    }

    /**
     * @return array{handler: FixMissingVariantsHandler, writer: InMemoryVariantFixMissingWriter}
     */
    private function wiringWithOneMissingVariant(?string $addAfasVariantForSku = null): array
    {
        return $this->makeWiring(['60110'], addAfasVariantForSku: $addAfasVariantForSku);
    }

    /**
     * @return array{handler: FixMissingVariantsHandler, writer: InMemoryVariantFixMissingWriter}
     */
    private function wiringWithTwoMissingVariants(?string $failOn = null): array
    {
        return $this->makeWiring(['60110', '60112'], failOn: $failOn);
    }

    /**
     * @param list<string> $missingAccessoireItemcodes
     * @return array{handler: FixMissingVariantsHandler, writer: InMemoryVariantFixMissingWriter}
     */
    private function makeWiring(
        array $missingAccessoireItemcodes,
        ?string $failOn = null,
        ?string $addAfasVariantForSku = null,
    ): array {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $baseItems = new InMemoryGroupBaseItemRepository($bases);
        $afas = new InMemoryAfasSamenstellingenRepository();

        $groups->save(new Group('AED Samaritan PAD 350P', '10013', 'Heartsine Samaritan PAD 350P'));

        $base = $bases->saveForGroup('10013', new GroupBase(null, 'AED Pakket: 350P NL', 'NL', '11111'));
        if ($base->id !== null) {
            $baseItems->saveForBase($base->id, new GroupBaseItem('10111', 'Heartsine 350P NL'));
            $baseItems->saveForBase($base->id, new GroupBaseItem('70112', 'Reanimatiekit'));
        }

        foreach ($missingAccessoireItemcodes as $accItemcode) {
            $accessoires->save(new Accessoire($accItemcode, 'Accessoire ' . $accItemcode, naamKortNl: 'Rugtas ' . $accItemcode));
            $links->link('10013', $accItemcode);
        }

        $variants->regenerateForGroup('10013');

        // Base wordt matched (= referentie), accessoire-varianten no_match (= te creëren).
        $afasRows = [new AfasSamenstelling('11111', 'AED Pakket: 350P NL', '10013', ['10111', '70112'])];
        if ($addAfasVariantForSku !== null) {
            $afasRows[] = new AfasSamenstelling($addAfasVariantForSku, 'al bestaand', '10013', ['10111', '70112', '60110']);
        }
        $afas->replaceSnapshot($afasRows);

        foreach ($variants->findAllForGroup('10013') as $v) {
            if ($v->id === null) {
                continue;
            }
            if ($v->accessoireItemcode === null) {
                $variants->markMatched($v->id, '11111');
            } else {
                $variants->markNoMatch($v->id);
            }
        }

        $audit = new ListMissingVariantsHandler($groups, $variants, $baseItems);
        $writer = new InMemoryVariantFixMissingWriter($failOn);
        $websites = new \Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWebsiteRepository();
        $publications = new \Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryBasePublicationRepository();

        $handler = new FixMissingVariantsHandler(
            $audit,
            $groups,
            $bases,
            $variants,
            $accessoires,
            $afas,
            new VariantNamingPolicy(),
            $writer,
            $websites,
            $publications,
        );

        return ['handler' => $handler, 'writer' => $writer];
    }
}
