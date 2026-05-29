<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Domain\Afas\AfasArticleRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ShowGroupController
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupBaseItemRepository $items,
        private AfasArticleRepository $articles,
    ) {
    }

    /**
     * @param array{familyHead: string} $args
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $familyHead = $args['familyHead'];
        $group = $this->groups->findByFamilyHeadItemcode($familyHead);
        if ($group === null) {
            return Json::write($response, ['error' => "Groep '$familyHead' niet gevonden."], 404);
        }

        $bases = [];
        foreach ($this->bases->findAllForGroup($group->familyHeadItemcode) as $base) {
            $items = [];
            if ($base->id !== null) {
                foreach ($this->items->findAllForBase($base->id) as $item) {
                    $afasArticle = $this->articles->findByItemcode($item->itemcode);
                    $label = $afasArticle !== null && $afasArticle->name !== ''
                        ? $afasArticle->name
                        : $item->name;
                    $items[] = [
                        'itemcode' => $item->itemcode,
                        'label' => $label,
                    ];
                }
            }
            $bases[] = [
                'id' => $base->id,
                'name' => $base->name,
                'languageCode' => $base->languageCode,
                'afasItemcode' => $base->afasItemcode,
                'variantLabel' => $base->variantLabel,
                'items' => $items,
            ];
        }

        return Json::write($response, [
            'familyHead' => $group->familyHeadItemcode,
            'name' => $group->name,
            'modelNameNl' => $group->modelNameNl,
            'modelNameFr' => $group->modelNameFr,
            'modelNameEn' => $group->modelNameEn,
            'bases' => $bases,
        ]);
    }
}
