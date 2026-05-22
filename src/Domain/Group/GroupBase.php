<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use InvalidArgumentException;

final readonly class GroupBase
{
    public ?int $id;
    public string $name;

    public function __construct(?int $id, string $name)
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Base naam mag niet leeg zijn.');
        }

        $this->id = $id;
        $this->name = $trimmed;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->name);
    }
}
