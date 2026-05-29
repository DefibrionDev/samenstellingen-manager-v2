<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use InvalidArgumentException;

final readonly class GroupBase
{
    public ?int $id;
    public string $name;
    public string $languageCode;
    public ?string $afasItemcode;
    public ?string $variantLabel;

    public function __construct(
        ?int $id,
        string $name,
        string $languageCode,
        ?string $afasItemcode = null,
        ?string $variantLabel = null,
    ) {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new InvalidArgumentException('Base naam mag niet leeg zijn.');
        }

        $trimmedLanguage = trim($languageCode);
        if ($trimmedLanguage === '') {
            throw new InvalidArgumentException('Base taal-code mag niet leeg zijn.');
        }

        $trimmedAfas = $afasItemcode !== null ? trim($afasItemcode) : null;
        $trimmedLabel = $variantLabel !== null ? trim($variantLabel) : null;

        $this->id = $id;
        $this->name = $trimmedName;
        $this->languageCode = $trimmedLanguage;
        $this->afasItemcode = ($trimmedAfas === null || $trimmedAfas === '') ? null : $trimmedAfas;
        $this->variantLabel = ($trimmedLabel === null || $trimmedLabel === '') ? null : $trimmedLabel;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->name, $this->languageCode, $this->afasItemcode, $this->variantLabel);
    }
}
