<?php

declare(strict_types=1);

use Defibrion\Samenstellingen\Bootstrap\Container;
use Defibrion\Samenstellingen\Interface\Http\AppFactory;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);

if (file_exists($projectRoot . '/.env')) {
    (new Dotenv())->load($projectRoot . '/.env');
}

$dbPath = $_ENV['SAMENSTELLINGEN_DB_PATH'] ?? $projectRoot . '/tmp/samenstellingen.sqlite';
// Relatieve paden uit `.env` ankeren we aan de project-root zodat ze niet afhangen van cwd.
if ($dbPath !== '' && $dbPath[0] !== '/') {
    $dbPath = $projectRoot . '/' . ltrim($dbPath, './');
}

$container = new Container($dbPath, $projectRoot . '/migrations');
$app = AppFactory::create($container);

$app->run();
