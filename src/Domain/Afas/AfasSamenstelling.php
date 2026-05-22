<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

use InvalidArgumentException;

final readonly class AfasSamenstelling
{
    public string $itemcode;
    public string $name;
    public ?string $itemcodeParent;

    /** @var list<string> Gesorteerd, gededupliceerd. */
    public array $bomItemcodes;

    /**
     * @param list<string> $bomItemcodes
     */
    public function __construct(
        string $itemcode,
        string $name,
        ?string $itemcodeParent,
        array $bomItemcodes,
    ) {
        $trimmedItemcode = trim($itemcode);
        if ($trimmedItemcode === '') {
            throw new InvalidArgumentException('AFAS samenstelling-itemcode mag niet leeg zijn.');
        }

        $normalised = [];
        foreach ($bomItemcodes as $code) {
            $trimmed = trim($code);
            if ($trimmed !== '') {
                $normalised[$trimmed] = true;
            }
        }
        $normalisedKeys = array_keys($normalised);
        sort($normalisedKeys);

        $trimmedParent = $itemcodeParent !== null ? trim($itemcodeParent) : null;

        $this->itemcode = $trimmedItemcode;
        $this->name = trim($name);
        $this->itemcodeParent = ($trimmedParent === null || $trimmedParent === '') ? null : $trimmedParent;
        $this->bomItemcodes = $normalisedKeys;
    }

    /**
     * @param list<string> $expectedBomItemcodes
     */
    public function bomMatches(array $expectedBomItemcodes): bool
    {
        $expected = [];
        foreach ($expectedBomItemcodes as $code) {
            $trimmed = trim($code);
            if ($trimmed !== '') {
                $expected[$trimmed] = true;
            }
        }
        $expectedKeys = array_keys($expected);
        sort($expectedKeys);

        return $expectedKeys === $this->bomItemcodes;
    }
}
