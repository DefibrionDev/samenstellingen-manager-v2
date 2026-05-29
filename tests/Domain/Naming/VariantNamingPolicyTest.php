<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Naming;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Naming\VariantNamingPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class VariantNamingPolicyTest extends TestCase
{
    private VariantNamingPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new VariantNamingPolicy();
    }

    #[Test]
    public function buildsNlBaseName(): void
    {
        $group = $this->group(modelNl: 'Reanibex 100 semi-automaat');
        $base = new GroupBase(1, 'irrelevant', 'NL');

        self::assertSame(
            'AED Pakket: Reanibex 100 semi-automaat NL',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function buildsNlVariantWithAccessoire(): void
    {
        $group = $this->group(modelNl: 'Heartsine Samaritan PAD 350P');
        $base = new GroupBase(1, 'irrelevant', 'NL');
        $accessoire = new Accessoire('60110', 'EHBO-Rugzak', naamKortNl: 'Rugtas');

        self::assertSame(
            'AED Pakket: Heartsine Samaritan PAD 350P NL met Rugtas',
            $this->policy->expectedName($group, $base, $accessoire),
        );
    }

    #[Test]
    public function buildsFrBaseWithFrTemplate(): void
    {
        $group = $this->group(modelFr: 'Reanibex 100 Entièrement automatique');
        $base = new GroupBase(2, 'irrelevant', 'FR');

        self::assertSame(
            'Pack DAE: Reanibex 100 Entièrement automatique FR',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function buildsFrVariantWithFrTemplateAndFrLabel(): void
    {
        $group = $this->group(modelFr: 'Reanibex 100 Entièrement automatique');
        $base = new GroupBase(2, 'irrelevant', 'FR');
        $accessoire = new Accessoire('60110', 'EHBO-Rugzak', naamKortFr: 'Sac à dos');

        self::assertSame(
            'Pack DAE: Reanibex 100 Entièrement automatique FR avec Sac à dos',
            $this->policy->expectedName($group, $base, $accessoire),
        );
    }

    #[Test]
    public function englishBaseGetsUkSuffixWithNlTemplate(): void
    {
        $group = $this->group(modelNl: 'Heartsine Samaritan PAD 350P');
        $base = new GroupBase(3, 'irrelevant', 'EN');

        self::assertSame(
            'AED Pakket: Heartsine Samaritan PAD 350P UK',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function deBaseGetsDeSuffixWithNlTemplate(): void
    {
        $group = $this->group(modelNl: 'Heartsine Samaritan PAD 350P');
        $base = new GroupBase(4, 'irrelevant', 'DE');

        self::assertSame(
            'AED Pakket: Heartsine Samaritan PAD 350P DE',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function dkBaseGetsDkSuffix(): void
    {
        $group = $this->group(modelNl: 'Heartsine Samaritan PAD 350P');
        $base = new GroupBase(5, 'irrelevant', 'DK');

        self::assertSame(
            'AED Pakket: Heartsine Samaritan PAD 350P DK',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function compoundNlFrUsesNlTemplateWithDashSuffix(): void
    {
        // 'NL/FR' → eerste taal-token is NL → NL-template. Suffix wordt 'NL-FR'.
        $group = $this->group(modelNl: 'LIFEPAK CR2 AED semi-automaat 3G');
        $base = new GroupBase(6, 'irrelevant', 'NL/FR');

        self::assertSame(
            'AED Pakket: LIFEPAK CR2 AED semi-automaat 3G NL-FR',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function compoundNlEnFrUsesNlTemplateAndMapsEnToUk(): void
    {
        $group = $this->group(modelNl: 'Mindray Beneheart C1');
        $base = new GroupBase(7, 'irrelevant', 'NL/EN/FR');

        self::assertSame(
            'AED Pakket: Mindray Beneheart C1 NL-UK-FR',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function nlBaseWithVariantLabelInsertsLabelBeforeSuffix(): void
    {
        $group = $this->group(modelNl: 'Mindray Beneheart C1 semi-automaat');
        $base = new GroupBase(8, 'irrelevant', 'DE', '21018-DE', '4G');

        self::assertSame(
            'AED Pakket: Mindray Beneheart C1 semi-automaat 4G DE',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function frBaseWithVariantLabelInsertsLabelBeforeSuffix(): void
    {
        $group = $this->group(modelFr: 'Mindray Beneheart C1 semi-automatique');
        $base = new GroupBase(9, 'irrelevant', 'FR', '21018-FR', '4G');

        self::assertSame(
            'Pack DAE: Mindray Beneheart C1 semi-automatique 4G FR',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function variantLabelWithAccessoireKeepsLabelAndAddsAccessoireSuffix(): void
    {
        $group = $this->group(modelNl: 'Mindray Beneheart C1 semi-automaat');
        $base = new GroupBase(10, 'irrelevant', 'DE', '21018-DE', '4G');
        $accessoire = new Accessoire('60110', 'EHBO-Rugzak', naamKortNl: 'Rugtas');

        self::assertSame(
            'AED Pakket: Mindray Beneheart C1 semi-automaat 4G DE met Rugtas',
            $this->policy->expectedName($group, $base, $accessoire),
        );
    }

    #[Test]
    public function variantLabelWithCompoundLanguageStaysBeforeCompoundSuffix(): void
    {
        $group = $this->group(modelNl: 'LIFEPAK CR2 AED volautomaat');
        $base = new GroupBase(11, 'irrelevant', 'NL/EN', '11144', 'WiFi');

        self::assertSame(
            'AED Pakket: LIFEPAK CR2 AED volautomaat WiFi NL-UK',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function emptyVariantLabelBehavesLikeNullLabel(): void
    {
        $group = $this->group(modelNl: 'Heartsine Samaritan PAD 350P');
        $base = new GroupBase(12, 'irrelevant', 'NL', '11111', '');

        self::assertSame(
            'AED Pakket: Heartsine Samaritan PAD 350P NL',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function throwsWhenModelNameForBucketIsMissing(): void
    {
        // Group heeft alleen NL-modelnaam; base is puur FR → FR-bucket → faalt.
        $group = $this->group(modelNl: 'Heartsine Samaritan PAD 350P');
        $base = new GroupBase(2, 'irrelevant', 'FR');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/model_name_fr/');
        $this->policy->expectedName($group, $base, null);
    }

    #[Test]
    public function throwsWhenAccessoireLabelForBucketIsMissing(): void
    {
        $group = $this->group(modelNl: 'Heartsine Samaritan PAD 350P');
        $base = new GroupBase(1, 'irrelevant', 'NL');
        $accessoire = new Accessoire('60110', 'EHBO-Rugzak'); // geen naam_kort_nl gezet

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/naam_kort_nl/');
        $this->policy->expectedName($group, $base, $accessoire);
    }

    private function group(
        ?string $modelNl = null,
        ?string $modelFr = null,
        ?string $modelEn = null,
    ): Group {
        return new Group('Test groep', '12345', $modelNl, $modelFr, $modelEn);
    }
}
