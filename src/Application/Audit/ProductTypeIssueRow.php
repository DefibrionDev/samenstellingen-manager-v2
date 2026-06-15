<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

final readonly class ProductTypeIssueRow
{
    public function __construct(
        public string $afasItemcode,
        public ProductTypeIssueType $issueType,
        public string $baseItemcode,
        public ?string $current01,
        public ?string $current02,
        public ?string $expected01,
        public ?string $expected02,
        public string $groupName,
    ) {
    }
}
