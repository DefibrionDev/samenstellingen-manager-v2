#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * One-off: vul ONTBREKENDE base-prijzen in de ARKY-dealerlijsten 026 + 029.
 *
 * Alleen managed base-samenstellingen (group_bases.afas_itemcode) die in 026 en/of
 * 029 GEEN prijslijst-prijs hebben (debiteur leeg). Bestaande prijzen worden nooit
 * aangeraakt — puur INSERT (FbSalesPrice POST).
 *
 * Prijs-bron per gat (zie analyse 2026-06-25):
 *   1) sibling-ARKY  — heeft een broertje in dezelfde groep + dezelfde connectiviteit
 *                      al een prijs in die lijst? Neem die over (houdt de groep
 *                      consistent; ARKY wijkt soms af van 027, bv. Heartsine 868 vs 795).
 *   2) 027           — anders: de eigen 027-prijs (Dealers Benelux) van die base.
 *
 * Staffels: van de gekozen bron worden ALLE staffels gekopieerd (baseline + elke
 * qty-break), niet enkel de baseline. De 027-bron heeft hier enkel baseline; de
 * sibling-ARKY-bron heeft baseline + staffel 10 + 25.
 *
 * Begindatum (DaBg): de echte AFAS-begindatum van de BRON-rij via Get_Prijzen
 * (easylinq levert alleen een dag-view). Fallback = vandaag als de lookup niets
 * vindt of er geen AFAS-creds zijn (alleen in dry-run).
 *
 * Hergebruikt de geteste schrijf-infra (HttpFbSalesPriceWriter / FbSalesPrice).
 *
 * GEBRUIK (vanuit repo-root):
 *   php migration/fill-arky-026-029-prices.php            # dry-run (default)
 *   php migration/fill-arky-026-029-prices.php --apply    # POST naar AFAS
 */

use Defibrion\Samenstellingen\Application\Fix\BeginDateLookup;
use Defibrion\Samenstellingen\Application\Fix\PriceFixFailedException;
use Defibrion\Samenstellingen\Application\Fix\PriceFixPlan;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasClientFactory;
use Defibrion\Samenstellingen\Infrastructure\Fix\HttpFbSalesPriceWriter;
use Defibrion\Samenstellingen\Infrastructure\Fix\HttpGetPrijzenBeginDateLookup;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

const TARGET_LISTS = ['026', '029'];
const FALLBACK_LIST = '027';

$apply = in_array('--apply', $argv, true);
$projectRoot = dirname(__DIR__);
if (file_exists($projectRoot . '/.env')) {
    (new Dotenv())->load($projectRoot . '/.env');
}

$dbPath = $_ENV['SAMENSTELLINGEN_DB_PATH'] ?? $projectRoot . '/tmp/samenstellingen.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$hasCreds = is_string($_ENV['AFAS_BASE_URL'] ?? null) && ($_ENV['AFAS_BASE_URL'] ?? '') !== ''
    && is_string($_ENV['AFAS_TOKEN'] ?? null) && ($_ENV['AFAS_TOKEN'] ?? '') !== '';

if ($apply && !$hasCreds) {
    fwrite(STDERR, "FOUT: --apply vereist AFAS_BASE_URL + AFAS_TOKEN in .env.\n");
    exit(1);
}

$writer = null;
$beginDateLookup = null;
if ($hasCreds) {
    $client = AfasClientFactory::fromEnv();
    $writer = new HttpFbSalesPriceWriter($client);
    $beginDateLookup = new HttpGetPrijzenBeginDateLookup($client);
}

/**
 * Managed bases met itemcode: itemcode => [group_id, connect, groep, naam].
 *
 * @var array<string, array{gid:int, connect:string, groep:string, naam:string}> $bases
 */
$bases = [];
$stmt = $pdo->query(
    "SELECT gb.afas_itemcode AS ic, gb.group_id AS gid,
            COALESCE(NULLIF(gb.variant_label,''),'') AS connect,
            g.name AS groep, gb.name AS naam
     FROM group_bases gb JOIN groups g ON g.id=gb.group_id
     WHERE gb.afas_itemcode IS NOT NULL AND gb.afas_itemcode<>''",
);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $bases[(string) $r['ic']] = [
        'gid' => (int) $r['gid'],
        'connect' => (string) $r['connect'],
        'groep' => (string) $r['groep'],
        'naam' => (string) $r['naam'],
    ];
}

/**
 * Prijslijst-prijzen (debiteur leeg), nieuwste per (itemcode, lijst, staffel).
 * Index 1: priceByKey[itemcode|lijst|staffel] = cents.
 * Index 2: hasList[itemcode|lijst] = true (heeft die base/sibling enige prijs in die lijst?).
 *
 * @var array<string, int>  $priceByKey
 * @var array<string, bool> $hasList
 */
$priceByKey = [];
$hasList = [];
$latestVan = [];
$stmt = $pdo->query(
    'SELECT itemcode, prijslijst_id AS lijst, staffel_aantal AS staffel,
            verkoopprijs_cents AS cents, geldig_van AS van
     FROM afas_prijzen WHERE debiteur_id IS NULL',
);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $ic = (string) $r['itemcode'];
    $lijst = (string) $r['lijst'];
    $staffel = $r['staffel'] === null ? '' : (string) (int) $r['staffel'];
    $key = $ic . '|' . $lijst . '|' . $staffel;
    $van = (string) $r['van'];
    if (!isset($latestVan[$key]) || $van > $latestVan[$key]) {
        $latestVan[$key] = $van;
        $priceByKey[$key] = (int) $r['cents'];
    }
    $hasList[$ic . '|' . $lijst] = true;
}

/** Alle staffel-keys (incl. baseline '') die een (itemcode, lijst) heeft. */
$staffelsFor = static function (string $ic, string $lijst) use ($priceByKey): array {
    $out = [];
    $prefix = $ic . '|' . $lijst . '|';
    foreach ($priceByKey as $key => $_cents) {
        if (str_starts_with($key, $prefix)) {
            $out[] = substr($key, strlen($prefix));
        }
    }
    sort($out); // '' (baseline) sorteert eerst

    return $out;
};

/**
 * @var list<array{ic:string, lijst:string, source_ic:string, source_lijst:string,
 *                 staffel:?int, cents:int, bron:string, groep:string, naam:string}> $plans
 */
$plans = [];
$unfillable = [];

foreach ($bases as $ic => $meta) {
    $ic = (string) $ic; // PHP cast numerieke itemcode-keys naar int
    foreach (TARGET_LISTS as $lijst) {
        if (isset($hasList[$ic . '|' . $lijst])) {
            continue; // geen gat
        }

        // 1) sibling-ARKY: broertje in zelfde groep + zelfde connectiviteit met prijs in deze lijst.
        $sourceIc = null;
        $sourceLijst = null;
        $bron = null;
        $siblingCandidates = [];
        foreach ($bases as $sib => $sibMeta) {
            $sib = (string) $sib;
            if ($sib === $ic || $sibMeta['gid'] !== $meta['gid'] || $sibMeta['connect'] !== $meta['connect']) {
                continue;
            }
            if (isset($hasList[$sib . '|' . $lijst])) {
                $siblingCandidates[] = $sib;
            }
        }
        if ($siblingCandidates !== []) {
            sort($siblingCandidates);
            $sourceIc = $siblingCandidates[0];
            $sourceLijst = $lijst;
            $bron = 'sibling-ARKY(' . $sourceIc . ')';
        } elseif (isset($hasList[$ic . '|' . FALLBACK_LIST])) {
            // 2) eigen 027.
            $sourceIc = $ic;
            $sourceLijst = FALLBACK_LIST;
            $bron = '027';
        } else {
            $unfillable[] = ['ic' => $ic, 'lijst' => $lijst, 'groep' => $meta['groep'], 'naam' => $meta['naam']];
            continue;
        }

        foreach ($staffelsFor($sourceIc, $sourceLijst) as $staffelStr) {
            $cents = $priceByKey[$sourceIc . '|' . $sourceLijst . '|' . $staffelStr];
            $plans[] = [
                'ic' => $ic,
                'lijst' => $lijst,
                'source_ic' => $sourceIc,
                'source_lijst' => $sourceLijst,
                'staffel' => $staffelStr === '' ? null : (int) $staffelStr,
                'cents' => $cents,
                'bron' => $bron,
                'groep' => $meta['groep'],
                'naam' => $meta['naam'],
            ];
        }
    }
}

usort($plans, static function (array $a, array $b): int {
    return [$a['lijst'], $a['ic'], $a['staffel'] ?? -1] <=> [$b['lijst'], $b['ic'], $b['staffel'] ?? -1];
});

// ── Begindatum resolven (echte DaBg via Get_Prijzen indien creds) ─────────────
$today = date('Y-m-d');
$beginCache = [];
$resolveBegin = static function (string $ic, string $lijst, ?int $staffel) use ($beginDateLookup, &$beginCache, $today): string {
    if ($beginDateLookup === null) {
        return $today; // dry-run zonder creds
    }
    $key = $ic . '|' . $lijst . '|' . ($staffel ?? 0);
    if (!array_key_exists($key, $beginCache)) {
        $beginCache[$key] = $beginDateLookup->find($ic, $lijst, $staffel) ?? $today;
    }

    return $beginCache[$key];
};

// ── Output ────────────────────────────────────────────────────────────────────
$mode = $apply ? 'APPLY' : 'DRY-RUN';
fwrite(STDOUT, sprintf("MODE: %s — snapshot %s\n", $mode, $dbPath));
fwrite(STDOUT, sprintf("Te vullen prijs-rijen: %d (over %d gaten)\n\n", count($plans), countGaps($plans)));

printf("%-16s %-5s %-7s %-11s %-22s %-12s\n", 'itemcode', 'lijst', 'staffel', 'prijs', 'bron', 'begindatum');
printf("%s\n", str_repeat('-', 80));
$failures = [];
$applied = 0;
foreach ($plans as $p) {
    $begin = $resolveBegin($p['source_ic'], $p['source_lijst'], $p['staffel']);
    $staffelLabel = $p['staffel'] === null ? 'basis' : (string) $p['staffel'];
    printf(
        "%-16s %-5s %-7s %11s %-22s %-12s\n",
        $p['ic'],
        $p['lijst'],
        $staffelLabel,
        number_format($p['cents'] / 100, 2, ',', '.'),
        $p['bron'],
        $begin,
    );

    if ($apply) {
        try {
            $plan = new PriceFixPlan($p['ic'], $p['lijst'], $p['staffel'], 0, $p['cents'], $begin);
            $writer->insert($plan);
            $applied++;
        } catch (PriceFixFailedException $e) {
            $failures[] = $p + ['begin' => $begin, 'error' => $e->getMessage()];
        }
    }
}

if ($unfillable !== []) {
    fwrite(STDOUT, sprintf("\nNIET VULBAAR (geen sibling-ARKY én geen 027) — %d:\n", count($unfillable)));
    foreach ($unfillable as $u) {
        fwrite(STDOUT, sprintf("  %s in %s — %s (%s)\n", $u['ic'], $u['lijst'], $u['groep'], $u['naam']));
    }
}

if (!$apply) {
    fwrite(STDOUT, "\nDRY-RUN — geen AFAS-mutaties. Run met --apply om te POST'en.\n");
    if ($beginDateLookup === null) {
        fwrite(STDOUT, "(geen AFAS-creds: begindatums zijn placeholders = vandaag; --apply resolve't de echte DaBg.)\n");
    }
    exit(0);
}

fwrite(STDOUT, sprintf("\n%d ingevoegd, %d gefaald.\n", $applied, count($failures)));
if ($failures !== []) {
    $csv = sprintf('%s/tmp/fill-arky-prices-failures-%s.csv', dirname(__DIR__), date('Y-m-d-His'));
    $fh = fopen($csv, 'w');
    if ($fh !== false) {
        fputcsv($fh, ['itemcode', 'lijst', 'staffel', 'prijs_cents', 'bron', 'begindatum', 'error']);
        foreach ($failures as $f) {
            fputcsv($fh, [
                $f['ic'], $f['lijst'], $f['staffel'] ?? 'basis', $f['cents'], $f['bron'], $f['begin'], $f['error'],
            ]);
        }
        fclose($fh);
        fwrite(STDOUT, sprintf("Failures gelogd naar %s\n", $csv));
    }
    exit(1);
}

exit(0);

/**
 * @param list<array{ic:string, lijst:string}> $plans
 */
function countGaps(array $plans): int
{
    $gaps = [];
    foreach ($plans as $p) {
        $gaps[$p['ic'] . '|' . $p['lijst']] = true;
    }

    return count($gaps);
}
