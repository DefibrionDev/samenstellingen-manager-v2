<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Naming;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Naming\UnknownLanguageException;
use Defibrion\Samenstellingen\Domain\Naming\VariantNamingPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VariantNamingPolicyTest extends TestCase
{
    private VariantNamingPolicy $policy;
    private Group $reanibex;

    protected function setUp(): void
    {
        $this->policy = new VariantNamingPolicy();
        $this->reanibex = new Group('Reanibex 100 Semi-Auto', '52112', 'Reanibex 100 semi-automaat');
    }

    #[Test]
    public function buildsNlBaseName(): void
    {
        $base = new GroupBase(1, 'irrelevant', 'NL');

        $name = $this->policy->expectedName(
            $this->reanibex,
            $base,
            null,
        );

        self::assertSame(
            'AED pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset',
            $name,
        );
    }

    #[Test]
    public function buildsNlVariantNameWithAccessoire(): void
    {
        $base = new GroupBase(1, 'irrelevant', 'NL');
        $accessoire = new Accessoire('60112', 'ARKY metalen binnenkast wit met alarm');

        $name = $this->policy->expectedName($this->reanibex, $base, $accessoire);

        self::assertSame(
            'AED pakket: Reanibex 100 semi-automaat NL incl. ARKY metalen binnenkast wit met alarm',
            $name,
        );
    }

    #[Test]
    public function buildsFrBaseName(): void
    {
        $group = new Group('Reanibex 100 Semi-Auto', '52112', 'Reanibex 100 Semi-automatique');
        $base = new GroupBase(2, 'irrelevant', 'FR');

        $name = $this->policy->expectedName($group, $base, null);

        self::assertSame(
            'Pack DAE: Reanibex 100 Semi-automatique FR avec safeset et signalétique',
            $name,
        );
    }

    #[Test]
    public function buildsFrVariantWithAccessoire(): void
    {
        $group = new Group('Reanibex 100', '52112', 'Reanibex 100 Semi-automatique');
        $base = new GroupBase(2, 'irrelevant', 'FR');
        $accessoire = new Accessoire('60110', 'EHBO-Rugzak YELLOW LARGE RED');

        $name = $this->policy->expectedName($group, $base, $accessoire);

        self::assertSame(
            'Pack DAE: Reanibex 100 Semi-automatique FR avec EHBO-Rugzak YELLOW LARGE RED',
            $name,
        );
    }

    #[Test]
    public function buildsEnBaseName(): void
    {
        $group = new Group('Reanibex 100', '52112', 'Reanibex 100 Semi-Automatic');
        $base = new GroupBase(3, 'irrelevant', 'EN');

        self::assertSame(
            'AED package: Reanibex 100 Semi-Automatic EN incl. safeset and stickerset',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function buildsDeBaseName(): void
    {
        $group = new Group('Reanibex 100', '52112', 'Reanibex 100 Semi-Automatic');
        $base = new GroupBase(4, 'irrelevant', 'DE');

        self::assertSame(
            'AED package: Reanibex 100 Semi-Automatic DE incl. safeset and stickerset',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function buildsDaBaseName(): void
    {
        $group = new Group('Reanibex 100', '52112', 'Reanibex 100 Semi-Automatic');
        $base = new GroupBase(5, 'irrelevant', 'DA');

        self::assertSame(
            'AED package: Reanibex 100 Semi-Automatic DA incl. safeset and stickerset',
            $this->policy->expectedName($group, $base, null),
        );
    }

    #[Test]
    public function buildsEnVariantWithAccessoire(): void
    {
        $group = new Group('Reanibex 100', '52112', 'Reanibex 100 Semi-Automatic');
        $base = new GroupBase(3, 'irrelevant', 'EN');
        $accessoire = new Accessoire('60223', 'ARKY Core Plus buitenkast met alarm, verwarming en pincode');

        self::assertSame(
            'AED package: Reanibex 100 Semi-Automatic EN incl. ARKY Core Plus buitenkast met alarm, verwarming en pincode',
            $this->policy->expectedName($group, $base, $accessoire),
        );
    }

    #[Test]
    public function throwsForUnknownLanguageCode(): void
    {
        $group = new Group('X', '99999', 'X model');
        $base = new GroupBase(99, 'irrelevant', 'IT');

        $this->expectException(UnknownLanguageException::class);
        $this->policy->expectedName($group, $base, null);
    }

    #[Test]
    public function throwsWhenGroupHasNoModelName(): void
    {
        $group = new Group('X', '99999'); // model_name null
        $base = new GroupBase(99, 'irrelevant', 'NL');

        $this->expectException(\RuntimeException::class);
        $this->policy->expectedName($group, $base, null);
    }

    #[Test]
    public function compoundLanguageCodeSplitsOnSlashAndUsesFirstSegment(): void
    {
        // 'NL/FR' bases (uit de portal-CSV) krijgen de NL-template — eerste segment beslist.
        $base = new GroupBase(10, 'irrelevant', 'NL/FR');

        $name = $this->policy->expectedName($this->reanibex, $base, null);

        self::assertSame(
            'AED pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset',
            $name,
        );
    }
}
