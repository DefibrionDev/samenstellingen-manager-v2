<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use InvalidArgumentException;

final readonly class GroupBase
{
    public ?int $id;
    public string $name;
    public ?string $languageCode;

    public function __construct(?int $id, string $name, ?string $languageCode = null)
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Base naam mag niet leeg zijn.');
        }

        $trimmedLanguage = $languageCode !== null ? trim($languageCode) : null;
        $this->id = $id;
        $this->name = $trimmed;
        $this->languageCode = ($trimmedLanguage === null || $trimmedLanguage === '') ? null : $trimmedLanguage;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->name, $this->languageCode);
    }
}
