<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Bom;

use InvalidArgumentException;

final readonly class StripBomComponent
{
    public string $bomItemcode;
    public bool $apply;
    public ?int $limit;

    public function __construct(string $bomItemcode, bool $apply = false, ?int $limit = null)
    {
        $code = trim($bomItemcode);
        if ($code === '') {
            throw new InvalidArgumentException('StripBomComponent.bomItemcode mag niet leeg zijn.');
        }
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException('StripBomComponent.limit moet ≥1 zijn of null.');
        }

        $this->bomItemcode = $code;
        $this->apply = $apply;
        $this->limit = $limit;
    }
}
