<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

enum WcHealthStatus: string
{
    case Ok = 'ok';
    case WrongType = 'wrong-type';
    case NotPublish = 'not-publish';
    case Missing = 'missing';
}
