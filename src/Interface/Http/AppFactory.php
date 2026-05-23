<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Audit\ListMissingVariantsHandler;
use Defibrion\Samenstellingen\Bootstrap\Container;
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

        $app->get('/api/groups', $listGroups);
        $app->get('/api/groups/{familyHead}', $showGroup);
        $app->get('/api/groups/{familyHead}/accessoires', $listGroupAccessoires);
        $app->get('/api/groups/{familyHead}/variants', $listGroupVariants);
        $app->get('/api/accessoires', $listAccessoires);
        $app->get('/api/bom-blacklist', $listBlacklist);
        $app->get('/api/missing-variants', $listMissing);

        return $app;
    }
}
