<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Group;

use Defibrion\Samenstellingen\Domain\Group\Group;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GroupTest extends TestCase
{
    #[Test]
    public function exposesNameAndFamilyHeadItemcode(): void
    {
        $group = new Group('Reanibex 100 Semi-Auto', '52112');

        self::assertSame('Reanibex 100 Semi-Auto', $group->name);
        self::assertSame('52112', $group->familyHeadItemcode);
    }

    #[Test]
    public function trimsName(): void
    {
        $group = new Group('  Reanibex 100 Semi-Auto  ', '52112');

        self::assertSame('Reanibex 100 Semi-Auto', $group->name);
    }

    #[Test]
    public function trimsFamilyHeadItemcode(): void
    {
        $group = new Group('Reanibex 100 Semi-Auto', "\t52112\n");

        self::assertSame('52112', $group->familyHeadItemcode);
    }

    #[Test]
    #[DataProvider('blankStrings')]
    public function rejectsBlankName(string $blank): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Groepsnaam mag niet leeg zijn');

        new Group($blank, '52112');
    }

    #[Test]
    #[DataProvider('blankStrings')]
    public function rejectsBlankFamilyHeadItemcode(string $blank): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Family-head itemcode mag niet leeg zijn');

        new Group('Reanibex 100 Semi-Auto', $blank);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankStrings(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'tab' => ["\t"];
        yield 'newline' => ["\n"];
    }
}
