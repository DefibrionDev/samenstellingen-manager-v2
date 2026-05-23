<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Audit\ListMissingVariantsHandler;
use Defibrion\Samenstellingen\Application\Audit\NameAuditHandler;
use Defibrion\Samenstellingen\Bootstrap\Container;
use Defibrion\Samenstellingen\Domain\Naming\VariantNamingPolicy;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppFactory
{
    /**
     * @return App<ContainerInterface|null>
     */
    public static function create(Container $container): App
    {
        $app = SlimAppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(false, true, true);

        $listGroups = new ListGroupsController(
            $container->groupRepository(),
            $container->baseRepository(),
            $container->baseItemRepository(),
        );
        $showGroup = new ShowGroupController(
            $container->groupRepository(),
            $container->baseRepository(),
            $container->baseItemRepository(),
            $container->afasArticleRepository(),
        );
        $listAccessoires = new ListAccessoiresController(
            $container->accessoireRepository(),
        );
        $listBlacklist = new ListBomBlacklistController(
            $container->bomBlacklistRepository(),
        );
        $listGroupAccessoires = new ListGroupAccessoiresController(
            $container->groupRepository(),
            $container->linkRepository(),
            $container->accessoireRepository(),
        );
        $listGroupVariants = new ListGroupVariantsController(
            $container->groupRepository(),
            $container->baseRepository(),
            $container->variantRepository(),
        );
        $listMissing = new ListMissingVariantsController(
            new ListMissingVariantsHandler(
                $container->groupRepository(),
                $container->variantRepository(),
                $container->baseItemRepository(),
            ),
        );
        $listNameDrift = new ListNameDriftController(
            new NameAuditHandler(
                $container->groupRepository(),
                $container->baseRepository(),
                $container->variantRepository(),
                $container->accessoireRepository(),
                $container->afasSamenstellingenRepository(),
                new VariantNamingPolicy(),
            ),
        );
        $app->get('/api/groups', $listGroups);
        $app->get('/api/groups/{familyHead}', $showGroup);
        $app->get('/api/groups/{familyHead}/accessoires', $listGroupAccessoires);
        $app->get('/api/groups/{familyHead}/variants', $listGroupVariants);
        $app->get('/api/accessoires', $listAccessoires);
        $app->get('/api/bom-blacklist', $listBlacklist);
        $app->get('/api/missing-variants', $listMissing);
        $app->get('/api/name-drift', $listNameDrift);

        return $app;
    }
}
