<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

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
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach ($this->groups->findAll() as $group) {
            $bases = $this->bases->findAllForGroup($group->familyHeadItemcode);
            $itemCount = 0;
            $familyHeadIsBase = false;
            foreach ($bases as $base) {
                if ($base->afasItemcode === $group->familyHeadItemcode) {
                    $familyHeadIsBase = true;
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
                'modelNameNl' => $group->modelNameNl,
                'modelNameFr' => $group->modelNameFr,
                'modelNameEn' => $group->modelNameEn,
            ];
        }

        return Json::write($response, $payload);
    }
}
