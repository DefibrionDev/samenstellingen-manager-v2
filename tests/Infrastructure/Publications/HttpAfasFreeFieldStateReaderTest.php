<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Publications;

use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;
use Defibrion\Samenstellingen\Infrastructure\Publications\HttpAfasFreeFieldStateReader;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HttpAfasFreeFieldStateReaderTest extends TestCase
{
    // UUID's spiegelen de websites-tabel (Reseller NL = website 1, ARKY = website 2).
    private const RESELLER_SYNC = 'U4E3E32DEFB374A1BA9F8680B8C405907';
    private const RESELLER_TONEN = 'UD77EC755E2F1404EB184A956685A7C0C';
    private const ARKY_SYNC = 'U50A21258B95F4493986990B0141049C8';
    private const ARKY_TONEN = 'U620F63CE511E4308923C155399EE8EAE';

    #[Test]
    public function mapsResellerAndArkyFreeFieldColumnsToUuids(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode(['rows' => [
                ['Itemcode' => '52120', 'Sync_Reseller_NL' => '1', 'Tonen_Reseller_NL' => '1', 'Sync_ARKY' => '1', 'Tonen_ARKY' => '0'],
                ['Itemcode' => '52192', 'Sync_Reseller_NL' => '', 'Tonen_Reseller_NL' => '', 'Sync_ARKY' => '', 'Tonen_ARKY' => ''],
            ]])),
        ]);
        $reader = new HttpAfasFreeFieldStateReader(
            new AfasHttpClient(new Client(['handler' => HandlerStack::create($mock)]), 'https://example.test', 'token'),
        );

        $state = $reader->readAll();

        // Hele structuur ineens — reseller-kolommen (bestaande mapping) + ARKY-kolommen
        // (nieuwe mapping): '1' → true, '0'/'' → false.
        self::assertEquals([
            '52120' => [
                self::RESELLER_SYNC => true,
                self::RESELLER_TONEN => true,
                self::ARKY_SYNC => true,
                self::ARKY_TONEN => false,
            ],
            '52192' => [
                self::RESELLER_SYNC => false,
                self::RESELLER_TONEN => false,
                self::ARKY_SYNC => false,
                self::ARKY_TONEN => false,
            ],
        ], $state);
    }
}
