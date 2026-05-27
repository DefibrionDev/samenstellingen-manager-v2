<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Money;

use InvalidArgumentException;

/**
 * Parse euro-input naar integer cents en omgekeerd.
 *
 * Geld in cents bewaren (int) voorkomt float-rounding-fouten. CLI/UI accepteren
 * gangbare euro-notaties (`79`, `79.50`, `79,50`, met of zonder `€`-prefix),
 * intern werken we met cents.
 */
final class EuroParser
{
    public static function toCents(string $input): int
    {
        $cleaned = preg_replace('/[€\s]/u', '', $input);
        if (!is_string($cleaned) || $cleaned === '') {
            throw new InvalidArgumentException(sprintf("Lege of niet-leesbare euro-waarde: '%s'.", $input));
        }

        // Normaliseer komma naar punt zodat we één decimaal-separator hebben.
        $normalized = str_replace(',', '.', $cleaned);

        // Strikt: één optionele punt, daarvoor 1+ cijfers, daarna max 2 cijfers.
        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalized, $m) !== 1) {
            throw new InvalidArgumentException(sprintf(
                "Ongeldige euro-waarde '%s' — gebruik bv. '79', '79.50' of '79,50'.",
                $input,
            ));
        }

        $euros = (int) $m[1];
        $centsPart = $m[2] ?? '';
        $cents = $centsPart === '' ? 0 : (int) str_pad($centsPart, 2, '0', STR_PAD_RIGHT);

        return $euros * 100 + $cents;
    }

    public static function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $absCents = $negative ? -$cents : $cents;
        $euros = intdiv($absCents, 100);
        $remainder = $absCents % 100;
        $eurosFormatted = number_format($euros, 0, ',', '.');

        return sprintf('%s€ %s,%02d', $negative ? '-' : '', $eurosFormatted, $remainder);
    }
}
