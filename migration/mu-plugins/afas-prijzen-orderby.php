<?php

declare(strict_types=1);

/**
 * Plugin Name: AFAS prijzen-sync sorteerfix
 * Description: Dwingt een oplopende Begindatum-sortering af op de Get_Prijzen-
 * REST-calls van lefcreative-afas-b2b.
 *
 * Waarom (bug gevonden 1 sept 2026, HS1/11145 toonde verlopen prijs 825 i.p.v.
 * 775): AFAS bewaart prijshistorie en de connector levert per prijssleutel de
 * actuele regel EERST, daarna de afgesloten generaties. SyncPrijzenJob upsert
 * elke rij over dezelfde unieke sleutel (prijslijst, relatie, artikel,
 * staffelgrens) zonder geldigheids-check, dus de laatst verwerkte — een
 * verlopen — regel wint. Elke ooit gewijzigde prijs staat daardoor verkeerd in
 * wp_lef_afas_prijzen (geldt ook voor reseller-productie).
 *
 * Fix: de plugin sorteert zelf niet (volgt connector-default) maar praat via
 * wp_remote_get; hier onderscheppen we de call en plakken we
 * `orderbyfieldids` met Begindatum voorop aan de URL. Oplopend gesorteerd komt
 * de actuele generatie per sleutel als laatste binnen en wint de upsert. De
 * extra velden maken de totale orde nagenoeg uniek zodat de skip/take-
 * paginering stabiel blijft (AFAS-advies bij skip/take).
 *
 * Structurele fix hoort in de plugin (verlopen regels overslaan bij import) —
 * gemeld bij lefcreative; deze mu-plugin kan weg zodra dat geleverd is.
 */

// Na elke prijzen-sync de WooCommerce variatieprijs-cache legen: de plugin
// doet dat zelf niet, waardoor prijs-ranges op productpagina's ("€ x - € y")
// oude prijzen bleven tonen nadat de tabel al ververst was.
add_action('afas_sync_prijzen', static function (): void {
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
          WHERE option_name LIKE '\_transient\_wc\_var\_prices\_%'
             OR option_name LIKE '\_transient\_timeout\_wc\_var\_prices\_%'"
    );
    wp_cache_flush();
}, 999);

add_filter('pre_http_request', static function ($preempt, array $args, string $url) {
    if ($preempt !== false) {
        return $preempt;
    }
    // Al gesorteerd (o.a. onze eigen herhaal-call hieronder): doorlaten.
    if (str_contains($url, 'orderbyfieldids=')) {
        return false;
    }
    $connector = (string) get_option('afas_connector_prijzen', 'Get_Prijzen');
    if ($connector === '' || !str_contains($url, '/connectors/' . $connector . '?')) {
        return false;
    }
    $url .= '&orderbyfieldids=' . rawurlencode('Begindatum,Itemcode,Prijslijst,Debiteur,Grondslag_berekening');
    return wp_remote_get($url, $args);
}, 10, 3);
