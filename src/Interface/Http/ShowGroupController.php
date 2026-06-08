<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Domain\Afas\AfasArticleRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Website\BasePublicationRepository;
use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ShowGroupController
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupBaseItemRepository $items,
        private AfasArticleRepository $articles,
        private WebsiteRepository $websites,
        private BasePublicationRepository $publications,
        private AfasSamenstellingenRepository $samenstellingen,
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

        $websitesById = [];
        foreach ($this->websites->findAll() as $website) {
            if ($website->id !== null) {
                $websitesById[$website->id] = $website->name;
            }
        }

        $bases = [];
        foreach ($this->bases->findAllForGroup($group->familyHeadItemcode) as $base) {
            $items = [];
            $publishedOn = [];
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
                foreach ($this->publications->findAllForBase($base->id) as $pub) {
                    if ($pub->published && isset($websitesById[$pub->websiteId])) {
                        $publishedOn[] = $websitesById[$pub->websiteId];
                    }
                }
            }
            $afasParent = null;
            if ($base->afasItemcode !== null) {
                $samenstelling = $this->samenstellingen->findByItemcode($base->afasItemcode);
                $afasParent = $samenstelling?->itemcodeParent;
            }

            $bases[] = [
                'id' => $base->id,
                'name' => $base->name,
                'languageCode' => $base->languageCode,
                'afasItemcode' => $base->afasItemcode,
                'afasItemcodeParent' => $afasParent,
                'variantLabel' => $base->variantLabel,
                'publishedOn' => $publishedOn,
                'items' => $items,
            ];
        }

        $familyHeadSamenstelling = $this->samenstellingen->findByItemcode($group->familyHeadItemcode);

        return Json::write($response, [
            'familyHead' => $group->familyHeadItemcode,
            'name' => $group->name,
            'modelNameNl' => $group->modelNameNl,
            'modelNameFr' => $group->modelNameFr,
            'modelNameEn' => $group->modelNameEn,
            'familyHeadParentInAfas' => $familyHeadSamenstelling?->itemcodeParent,
            'bases' => $bases,
        ]);
    }
}
