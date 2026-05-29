<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;
use Defibrion\Samenstellingen\Domain\Naming\VariantNamingPolicy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class ListGroupVariantsController
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupVariantRepository $variants,
        private AccessoireRepository $accessoires,
        private VariantNamingPolicy $namingPolicy,
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

        /** @var array<int, GroupBase> */
        $baseById = [];
        foreach ($this->bases->findAllForGroup($group->familyHeadItemcode) as $base) {
            if ($base->id !== null) {
                $baseById[$base->id] = $base;
            }
        }

        $payload = [];
        foreach ($this->variants->findAllForGroup($group->familyHeadItemcode) as $variant) {
            $base = $baseById[$variant->baseId] ?? null;
            $accessoire = $variant->accessoireItemcode !== null
                ? $this->accessoires->findByItemcode($variant->accessoireItemcode)
                : null;

            $canonicalName = null;
            if ($base !== null) {
                try {
                    $canonicalName = $this->namingPolicy->expectedName($group, $base, $accessoire);
                } catch (Throwable) {
                    // Group/accessoire mist een canonical-veld — laat null door.
                }
            }

            $payload[] = [
                'baseId' => $variant->baseId,
                'baseName' => $variant->baseName,
                'languageCode' => $base?->languageCode,
                'accessoireItemcode' => $variant->accessoireItemcode,
                'accessoireLabel' => $variant->accessoireLabel,
                'afasSamenstellingItemcode' => $variant->afasSamenstellingItemcode,
                'afasStatus' => $variant->afasStatus,
                'canonicalName' => $canonicalName,
            ];
        }

        return Json::write($response, $payload);
    }
}
