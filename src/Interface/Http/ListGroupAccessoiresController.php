<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListGroupAccessoiresController
{
    public function __construct(
        private GroupRepository $groups,
        private GroupAccessoireRepository $links,
        private AccessoireRepository $accessoires,
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

        $payload = [];
        foreach ($this->links->findAllForGroup($group->familyHeadItemcode) as $linked) {
            // findAllForGroup levert ook al een Accessoire — geen extra lookup nodig,
            // maar voor veiligheid valideer dat de accessoire nog in de catalogus staat.
            $accessoire = $this->accessoires->findByItemcode($linked->itemcode) ?? $linked;
            $payload[] = [
                'itemcode' => $accessoire->itemcode,
                'label' => $accessoire->label,
            ];
        }
        usort($payload, static fn ($a, $b) => strcmp($a['itemcode'], $b['itemcode']));

        return Json::write($response, $payload);
    }
}
