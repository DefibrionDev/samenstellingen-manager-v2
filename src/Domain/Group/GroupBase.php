<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use InvalidArgumentException;

final readonly class GroupBase
{
    public ?int $id;
    public string $name;
    public string $languageCode;

    public function __construct(?int $id, string $name, string $languageCode)
    {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new InvalidArgumentException('Base naam mag niet leeg zijn.');
        }

        $trimmedLanguage = trim($languageCode);
        if ($trimmedLanguage === '') {
            throw new InvalidArgumentException('Base taal-code mag niet leeg zijn.');
        }

        $this->id = $id;
        $this->name = $trimmedName;
        $this->languageCode = $trimmedLanguage;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->name, $this->languageCode);
    }
}
