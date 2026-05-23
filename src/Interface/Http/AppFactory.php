<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

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

        $list = new ListGroupsController(
            $container->groupRepository(),
            $container->baseRepository(),
            $container->baseItemRepository(),
        );
        $show = new ShowGroupController(
            $container->groupRepository(),
            $container->baseRepository(),
            $container->baseItemRepository(),
        );

        $app->get('/api/groups', $list);
        $app->get('/api/groups/{familyHead}', $show);

        return $app;
    }
}
