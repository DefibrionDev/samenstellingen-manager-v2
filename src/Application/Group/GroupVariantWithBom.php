<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Group\GroupVariant;

final readonly class GroupVariantWithBom
{
    /**
     * @param list<BomItem> $bom
     */
    public function __construct(
        public GroupVariant $variant,
        public array $bom,
    ) {
    }
}
