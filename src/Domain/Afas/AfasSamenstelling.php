<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

use InvalidArgumentException;

final readonly class AfasSamenstelling
{
    public string $itemcode;
    public string $name;
    public ?string $itemcodeParent;
    public ?string $duplicateOfItemcode;
    public ?string $cbsCode;

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
        ?string $duplicateOfItemcode = null,
        ?string $cbsCode = null,
    ) {
        $trimmedItemcode = trim($itemcode);
        if ($trimmedItemcode === '') {
            throw new InvalidArgumentException('AFAS samenstelling-itemcode mag niet leeg zijn.');
        }

        $normalised = [];
        $seen = [];
        foreach ($bomItemcodes as $code) {
            $trimmed = trim((string) $code);
            if ($trimmed === '' || isset($seen[$trimmed])) {
                continue;
            }
            $normalised[] = $trimmed;
            $seen[$trimmed] = true;
        }
        sort($normalised);
        $normalisedKeys = $normalised;

        $trimmedParent = $itemcodeParent !== null ? trim($itemcodeParent) : null;
        $trimmedDuplicateOf = $duplicateOfItemcode !== null ? trim($duplicateOfItemcode) : null;

        $trimmedCbs = $cbsCode !== null ? trim($cbsCode) : null;

        $this->itemcode = $trimmedItemcode;
        $this->name = trim($name);
        $this->itemcodeParent = ($trimmedParent === null || $trimmedParent === '') ? null : $trimmedParent;
        $this->bomItemcodes = $normalisedKeys;
        $this->duplicateOfItemcode = ($trimmedDuplicateOf === null || $trimmedDuplicateOf === '') ? null : $trimmedDuplicateOf;
        $this->cbsCode = ($trimmedCbs === null || $trimmedCbs === '') ? null : $trimmedCbs;
    }

    public function isCanonical(): bool
    {
        return $this->duplicateOfItemcode === null;
    }

    /**
     * @param list<string> $expectedBomItemcodes
     */
    public function bomMatches(array $expectedBomItemcodes): bool
    {
        $expected = [];
        $seen = [];
        foreach ($expectedBomItemcodes as $code) {
            $trimmed = trim((string) $code);
            if ($trimmed === '' || isset($seen[$trimmed])) {
                continue;
            }
            $expected[] = $trimmed;
            $seen[$trimmed] = true;
        }
        sort($expected);

        return $expected === $this->bomItemcodes;
    }

    public function bomKey(): string
    {
        return implode(',', $this->bomItemcodes);
    }
}
