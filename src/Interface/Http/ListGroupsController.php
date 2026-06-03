<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Audit\ListMissingVariants;
use Defibrion\Samenstellingen\Application\Audit\ListMissingVariantsHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListGroupsController
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupBaseItemRepository $items,
        private ListMissingVariantsHandler $missingVariants,
        private AfasSamenstellingenRepository $afasSamenstellingen,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $missingByFamilyHead = [];
        foreach (($this->missingVariants)(new ListMissingVariants()) as $row) {
            $missingByFamilyHead[$row->familyHead] = ($missingByFamilyHead[$row->familyHead] ?? 0) + 1;
        }

        $payload = [];
        foreach ($this->groups->findAll() as $group) {
            $bases = $this->bases->findAllForGroup($group->familyHeadItemcode);
            $itemCount = 0;
            $familyHeadIsBase = false;
            $parentMismatchCount = 0;
            foreach ($bases as $base) {
                if ($base->afasItemcode === $group->familyHeadItemcode) {
                    $familyHeadIsBase = true;
                }
                if ($base->afasItemcode !== null) {
                    $samenstelling = $this->afasSamenstellingen->findByItemcode($base->afasItemcode);
                    if (
                        $samenstelling !== null
                        && $samenstelling->itemcodeParent !== null
                        && $samenstelling->itemcodeParent !== $group->familyHeadItemcode
                    ) {
                        ++$parentMismatchCount;
                    }
                }
                if ($base->id === null) {
                    continue;
                }
                $itemCount += count($this->items->findAllForBase($base->id));
            }
            $payload[] = [
                'name' => $group->name,
                'familyHead' => $group->familyHeadItemcode,
                'baseCount' => count($bases),
                'baseItemCount' => $itemCount,
                'familyHeadIsBase' => $familyHeadIsBase,
                'missingVariantCount' => $missingByFamilyHead[$group->familyHeadItemcode] ?? 0,
                'parentMismatchCount' => $parentMismatchCount,
                'modelNameNl' => $group->modelNameNl,
                'modelNameFr' => $group->modelNameFr,
                'modelNameEn' => $group->modelNameEn,
            ];
        }

        return Json::write($response, $payload);
    }
}
