<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;

final readonly class GroupOverview
{
    /**
     * @param list<GroupBase>            $bases
     * @param list<Accessoire>           $accessoires
     * @param list<GroupVariantWithBom>  $variants
     */
    public function __construct(
        public Group $group,
        public array $bases,
        public array $accessoires,
        public array $variants,
    ) {
    }
}
