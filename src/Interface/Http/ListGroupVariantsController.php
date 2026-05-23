<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListGroupVariantsController
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupVariantRepository $variants,
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

        // Cache taal-code per base-id; voorkomt N lookups per variant.
        $languageByBaseId = [];
        foreach ($this->bases->findAllForGroup($group->familyHeadItemcode) as $base) {
            if ($base->id !== null) {
                $languageByBaseId[$base->id] = $base->languageCode;
            }
        }

        $payload = [];
        foreach ($this->variants->findAllForGroup($group->familyHeadItemcode) as $variant) {
            $payload[] = [
                'baseId' => $variant->baseId,
                'baseName' => $variant->baseName,
                'languageCode' => $languageByBaseId[$variant->baseId] ?? null,
                'accessoireItemcode' => $variant->accessoireItemcode,
                'accessoireLabel' => $variant->accessoireLabel,
                'afasSamenstellingItemcode' => $variant->afasSamenstellingItemcode,
                'afasStatus' => $variant->afasStatus,
            ];
        }

        return Json::write($response, $payload);
    }
}
