<?php
/**
 * Plugin Name: AFAS Mijn account nette weergave (mu)
 * Description: Presentatielaag bovenop de AFAS B2B-plugin (Mijn account):
 *              nette track & trace-regels met "Volg zending"-knop i.p.v. kale
 *              URL's, logische blokvolgorde, WooCommerce-stijl besteltabel
 *              zonder regelbedragen, badge "Archief" i.p.v. "Extern",
 *              hertaalde AFAS-statussen en een "Recente bestellingen"-widget
 *              op het dashboard. Meertalig: nl-shops tonen Nederlands, alle
 *              andere sitetalen krijgen Engels als basis (incl. gettext-
 *              vertalingen voor de NL-bronstrings van de plugin; bestaande
 *              .mo-vertalingen van de shop blijven leidend) - GTranslate
 *              e.d. vertalen daar client-side op door. Eén versie voor
 *              reseller én arky. Presentatie-only: de plugin wordt niet
 *              aangepast, dus updates van Lefcreative blijven veilig; bij
 *              onbekende markup valt alles terug op de originele weergave.
 * Version: 2.2
 */

defined('ABSPATH') || exit;

/**
 * 'nl' op Nederlandstalige shops, anders 'en'. De frontend rendert in de
 * sitetaal; verdere talen (de/fr/es op arky) doet GTranslate client-side
 * op basis van de Engelse output.
 */
function defib_afas_tt_lang(): string
{
    return str_starts_with(determine_locale(), 'nl') ? 'nl' : 'en';
}

/** Eigen teksten van deze mu-plugin (sleutel = Nederlands). */
function defib_afas_tt_t(string $tekst): string
{
    if (defib_afas_tt_lang() === 'nl') {
        return $tekst;
    }

    static $en = [
        'Volg zending'               => 'Track shipment',
        'Pakbon'                     => 'Packing slip',
        'Ordernummer:'               => 'Order number:',
        'Bekijken'                   => 'View',
        'Archief'                    => 'Archive',
        'Eerdere bestelling uit onze administratie' => 'Earlier order from our records',
        'Webshop bestelnummer'       => 'Webshop order number',
        'Bestelgegevens'             => 'Order details',
        'Product'                    => 'Product',
        'Totaal:'                    => 'Total:',
        'Totaal'                     => 'Total',
        'Recente bestellingen'       => 'Recent orders',
        'Alle bestellingen bekijken' => 'View all orders',
        'Bestelling'                 => 'Order',
        'Datum'                      => 'Date',
        'Status'                     => 'Status',
    ];

    return $en[$tekst] ?? $tekst;
}

/**
 * Wat een NL-bronstring van de AFAS-plugin in de huidige taal rendert
 * (via de .mo van de shop of onze gettext-filter hieronder). Hiermee
 * blijven de zoekpatronen in de buffer-vervangingen taalonafhankelijk.
 */
function defib_afas_tt_plugin_t(string $bron): string
{
    return __($bron, 'lefcreative-afas-b2b'); // phpcs:ignore WordPress.WP.I18n
}

/**
 * Engelse vertalingen voor de Nederlandse bronstrings van de AFAS-plugin.
 * Vult alleen gaten: heeft de shop al een .mo-vertaling voor de string
 * (arky's lefcreative-afas-b2b-en_GB.mo), dan blijft die staan. Op
 * nl-shops doet deze filter niets.
 */
add_filter('gettext_lefcreative-afas-b2b', static function ($translation, $text) {
    if ($translation !== $text || defib_afas_tt_lang() === 'nl') {
        return $translation;
    }

    static $en = [
        'Aantal'                     => 'Quantity',
        'Afgehandeld'                => 'Completed',
        'Bekijk'                     => 'View',
        'Bestelgegevens'             => 'Order details',
        'Bestelling'                 => 'Order',
        'Bestelling %1$s is geplaatst op %2$s en heeft de status "%3$s".' => 'Order %1$s was placed on %2$s and has the status "%3$s".',
        'Bestelling %s'              => 'Order %s',
        'Bestelling niet gevonden.'  => 'Order not found.',
        'Datum'                      => 'Date',
        'Deels geleverd'             => 'Partially delivered',
        'Er zijn nog geen bestellingen.' => 'There are no orders yet.',
        'Extern'                     => 'External',
        'Extern ordernummer:'        => 'External order number:',
        'Geen regels beschikbaar voor deze bestelling.' => 'No order lines available for this order.',
        'Geleverd'                   => 'Delivered',
        'Geplaatst buiten de webshop' => 'Placed outside the webshop',
        'In behandeling'             => 'Processing',
        'In voorbereiding'           => 'In preparation',
        'Referentie:'                => 'Reference:',
        'Terug naar bestellingen'    => 'Back to orders',
        'Totaal'                     => 'Total',
        'Verwerkingsstatus:'         => 'Processing status:',
        'Verzonden'                  => 'Shipped',
        'Volgende'                   => 'Next',
        'Vorige'                     => 'Previous',
        'Webshop bestelnummer'       => 'Webshop order number',
    ];

    return $en[$text] ?? $translation;
}, 10, 2);

add_action('woocommerce_init', function () {
    $overview = 'App\\WooCommerce\\OrderOverview';
    if (!class_exists($overview)) {
        return;
    }

    // remove_action() geeft true terug als de callback hing; zo grijpen we
    // alleen in wanneer de toggle "AFAS-bestellingen in Mijn account" aan
    // staat en de plugin zijn renderers echt geregistreerd heeft.

    // Detailpagina, nieuwe pluginversies: AFAS-orders rijden op WooCommerce's
    // eigen view-order-endpoint met een "ext-"-prefix (view-order/ext-123).
    if (remove_action('woocommerce_account_view-order_endpoint', [$overview, 'maybeRenderAfasDetail'], 5)) {
        add_action('woocommerce_account_view-order_endpoint', static function ($orderId) use ($overview) {
            $orderId = rawurldecode(trim((string) $orderId));
            ob_start();
            $overview::maybeRenderAfasDetail($orderId);
            $html = (string) ob_get_clean();
            // Alleen ext-waarden leveren output; gewone WC-orders rendert
            // WooCommerce zelf op prioriteit 10.
            if (str_starts_with($orderId, 'ext-')) {
                $nummer = substr($orderId, 4);
                // Volgorde: eerst het T&T-blok verplaatsen/herschrijven
                // (restyle gebruikt de order-details-div als ankerpunt), dan
                // die div vervangen door de WC-stijl tabel, dan statussen.
                $html = defib_afas_tt_relabel(defib_afas_tt_wc_table(defib_afas_tt_restyle($html, $nummer), $nummer));
            }
            echo $html;
        }, 5);
    }

    // Detailpagina, oudere pluginversies: eigen "bestelling"-endpoint.
    if (remove_action('woocommerce_account_bestelling_endpoint', [$overview, 'renderDetail'])) {
        add_action('woocommerce_account_bestelling_endpoint', static function ($ordernummer) use ($overview) {
            $ordernummer = rawurldecode(trim((string) $ordernummer));
            ob_start();
            $overview::renderDetail($ordernummer);
            echo defib_afas_tt_relabel(defib_afas_tt_wc_table(defib_afas_tt_restyle((string) ob_get_clean(), $ordernummer), $ordernummer));
        });
    }

    if (remove_action('woocommerce_view_order', [$overview, 'renderAfasBlockForWcOrder'], 5)) {
        add_action('woocommerce_view_order', static function ($orderId) use ($overview) {
            $order      = wc_get_order((int) $orderId);
            $afasNumber = $order ? (string) $order->get_meta('_afas_order_number') : '';
            ob_start();
            $overview::renderAfasBlockForWcOrder((int) $orderId);
            echo defib_afas_tt_relabel(defib_afas_tt_restyle(defib_afas_tt_wc_order_layout((string) ob_get_clean()), $afasNumber));
        }, 5);
    }

    if (remove_action('woocommerce_account_orders_endpoint', [$overview, 'renderOrdersList'])) {
        add_action('woocommerce_account_orders_endpoint', static function ($currentPage = 1) use ($overview) {
            ob_start();
            $overview::renderOrdersList($currentPage);
            echo defib_afas_tt_relabel((string) ob_get_clean());
        });

        // Dashboard-widget hoort bij hetzelfde gecombineerde overzicht, dus
        // alleen registreren als de toggle aan staat (zie guard hierboven).
        add_action('woocommerce_account_dashboard', 'defib_afas_tt_dashboard');
    }
}, 20);

/**
 * "Track & trace"-linkje onder de status in het dashboard-widget, zoals de
 * plugin dat in de orderlijst doet: één zending linkt direct naar de
 * vervoerder, meerdere naar het track & trace-blok op de detailpagina.
 */
function defib_afas_tt_dash_tt_link(string $ordernummer, string $viewUrl): string
{
    if ($ordernummer === '') {
        return '';
    }

    $overview = 'App\\WooCommerce\\OrderOverview';
    $urls     = [];
    foreach ($overview::getPakbonnen($ordernummer) as $pb) {
        if ((string) ($pb->track_trace_url ?? '') !== '') {
            $urls[] = (string) $pb->track_trace_url;
        }
    }

    if (empty($urls)) {
        return '';
    }

    $href   = count($urls) === 1 ? $urls[0] : $viewUrl . '#afas-track-trace';
    $target = count($urls) === 1 ? ' target="_blank" rel="noopener noreferrer"' : '';

    return '<br><small><a href="' . esc_url($href) . '"' . $target . '>Track &amp; trace</a></small>';
}

/**
 * "Recente bestellingen" op het Mijn account-dashboard: de laatste 5 orders
 * (webshop + AFAS samengevoegd, zelfde dedup en labels als de orderlijst)
 * met een knop naar het volledige overzicht.
 */
function defib_afas_tt_dashboard(): void
{
    if (!is_user_logged_in()) {
        return;
    }

    $overview = 'App\\WooCommerce\\OrderOverview';
    $rows     = [];

    foreach (wc_get_orders([
        'customer_id' => get_current_user_id(),
        'limit'       => 5,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ]) as $order) {
        $rows[] = [
            'sort' => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
            'wc'   => $order,
        ];
    }

    $relatieId = (string) get_user_meta(get_current_user_id(), 'afas_relatie_id', true);
    if ($relatieId !== '') {
        global $wpdb;
        $t = $wpdb->prefix . 'lef_afas_verkooporders';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $afasOrders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$t}` WHERE debiteur_id = %s ORDER BY orderdatum DESC, ordernummer DESC LIMIT 10",
            $relatieId
        )) ?: [];

        if (!empty($afasOrders)) {
            // Ordernummers die als webshoporder bestaan niet dubbel tonen
            // (hun WC-orderdatum bepaalt al of ze de top 5 halen).
            $nummers   = array_map(static fn(object $a): string => (string) $a->ordernummer, $afasOrders);
            $metaTable = (class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')
                && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled())
                ? $wpdb->prefix . 'wc_orders_meta'
                : $wpdb->postmeta;
            $ph = implode(',', array_fill(0, count($nummers), '%s'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $gepusht = array_flip((array) $wpdb->get_col($wpdb->prepare(
                "SELECT meta_value FROM `{$metaTable}` WHERE meta_key = '_afas_order_number' AND meta_value IN ($ph)",
                $nummers
            )));

            foreach ($afasOrders as $ao) {
                if (isset($gepusht[(string) $ao->ordernummer])) {
                    continue;
                }
                $rows[] = [
                    'sort' => $ao->orderdatum ? (int) strtotime((string) $ao->orderdatum) : 0,
                    'afas' => $ao,
                ];
            }
        }
    }

    if (empty($rows)) {
        return;
    }

    usort($rows, static fn(array $a, array $b): int => $b['sort'] <=> $a['sort']);
    $rows = array_slice($rows, 0, 5);

    $cell = static fn(string $titel, string $inhoud): string =>
        '<td class="woocommerce-orders-table__cell" data-title="' . esc_attr($titel) . '">' . $inhoud . '</td>';

    $body = '';
    foreach ($rows as $row) {
        if (isset($row['wc'])) {
            $order      = $row['wc'];
            $afasNummer = (string) $order->get_meta('_afas_order_number');
            $afasOrder  = $afasNummer !== '' ? $overview::getVerkooporder($afasNummer) : null;

            $nummer = '<a href="' . esc_url($order->get_view_order_url()) . '">'
                . esc_html($afasNummer !== '' ? $afasNummer : '#' . $order->get_order_number()) . '</a>'
                . ($afasNummer !== '' ? ' <span class="afas-extern-badge afas-extern-badge--wc" title="' . esc_attr(defib_afas_tt_t('Webshop bestelnummer')) . '">#' . esc_html($order->get_order_number()) . '</span>' : '');
            $datum  = $order->get_date_created() ? esc_html(wc_format_datetime($order->get_date_created())) : '';
            $status = ($afasOrder !== null && (string) $afasOrder->status !== '')
                ? esc_html(defib_afas_tt_status_label($overview::orderStatusLabel((string) $afasOrder->status)))
                : esc_html(wc_get_order_status_name($order->get_status()));
            $status .= defib_afas_tt_dash_tt_link($afasNummer, $order->get_view_order_url());
            $totaal = wp_kses_post($order->get_formatted_order_total());
        } else {
            $ao = $row['afas'];
            // Nieuwe pluginversies serveren AFAS-orders op view-order/ext-<nummer>;
            // oudere op het eigen "bestelling"-endpoint.
            $viewUrl = method_exists($overview, 'maybeRenderAfasDetail')
                ? wc_get_endpoint_url('view-order', 'ext-' . rawurlencode((string) $ao->ordernummer), wc_get_page_permalink('myaccount'))
                : wc_get_endpoint_url('bestelling', rawurlencode((string) $ao->ordernummer), wc_get_page_permalink('myaccount'));

            $nummer = '<a href="' . esc_url($viewUrl) . '">' . esc_html((string) $ao->ordernummer) . '</a>'
                . ' <span class="afas-extern-badge" title="' . esc_attr(defib_afas_tt_t('Eerdere bestelling uit onze administratie')) . '">' . esc_html(defib_afas_tt_t('Archief')) . '</span>';
            $datum  = $ao->orderdatum ? esc_html(date_i18n(get_option('date_format'), (int) strtotime((string) $ao->orderdatum))) : '';
            $status = esc_html(defib_afas_tt_status_label($overview::orderStatusLabel((string) $ao->status)))
                . defib_afas_tt_dash_tt_link((string) $ao->ordernummer, $viewUrl);
            $totaal = $ao->totaal !== null ? wp_kses_post(wc_price((float) $ao->totaal)) : '-';
        }

        $body .= '<tr class="woocommerce-orders-table__row order">'
            . $cell(defib_afas_tt_t('Bestelling'), $nummer)
            . $cell(defib_afas_tt_t('Datum'), $datum)
            . $cell(defib_afas_tt_t('Status'), $status)
            . $cell(defib_afas_tt_t('Totaal'), $totaal)
            . '</tr>';
    }
    ?>
    <style>
        .afas-dash-orders h2 { margin: 1.5em 0 .75em; }
        .afas-dash-orders .afas-extern-badge {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: .72em;
            line-height: 1.7;
            background: #e4e4e7;
            color: #3f3f46;
            vertical-align: middle;
            white-space: nowrap;
        }
        .afas-dash-orders .afas-extern-badge--wc { background: #fff; }
    </style>
    <div class="afas-dash-orders">
        <h2><?php echo esc_html(defib_afas_tt_t('Recente bestellingen')); ?></h2>
        <table class="woocommerce-orders-table shop_table shop_table_responsive my_account_orders account-orders-table">
            <thead>
                <tr>
                    <th class="woocommerce-orders-table__header"><span class="nobr"><?php echo esc_html(defib_afas_tt_t('Bestelling')); ?></span></th>
                    <th class="woocommerce-orders-table__header"><span class="nobr"><?php echo esc_html(defib_afas_tt_t('Datum')); ?></span></th>
                    <th class="woocommerce-orders-table__header"><span class="nobr"><?php echo esc_html(defib_afas_tt_t('Status')); ?></span></th>
                    <th class="woocommerce-orders-table__header"><span class="nobr"><?php echo esc_html(defib_afas_tt_t('Totaal')); ?></span></th>
                </tr>
            </thead>
            <tbody><?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- cellen zijn hierboven ge-escaped ?></tbody>
        </table>
        <p><a class="woocommerce-button button" href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>"><?php echo esc_html(defib_afas_tt_t('Alle bestellingen bekijken')); ?></a></p>
    </div>
    <?php
}

/**
 * Klantvriendelijk label voor de rauwe AFAS-orderstatus, per taal. De data
 * uit de connector is altijd Nederlands (ook op Engelstalige shops) en valt
 * buiten gettext; daarom hier expliciet gemapt. Onbekende waarden blijven
 * bewust ongemoeid (rauw zichtbaar is beter dan stilletjes fout gemapt);
 * WooCommerce-statussen komen hier niet in voor en blijven sowieso staan.
 */
function defib_afas_tt_status_label(string $raw): string
{
    static $maps = [
        'nl' => [
            'Verwerkt'              => 'Verzonden',
            'Gedeeltelijk verwerkt' => 'Deellevering',
            ''                      => 'Onbekend',
            '-'                     => 'Onbekend',
        ],
        'en' => [
            'Verwerkt'              => 'Shipped',
            'Gedeeltelijk verwerkt' => 'Partial delivery',
            'In behandeling'        => 'Processing',
            ''                      => 'Unknown',
            '-'                     => 'Unknown',
        ],
    ];

    $raw = trim($raw);

    return $maps[defib_afas_tt_lang()][$raw] ?? $raw;
}

/**
 * Hertaal AFAS-orderstatussen in de gebufferde output, op precies de drie
 * plekken waar de plugin ze print (statuskolom orderlijst, statuszin
 * AFAS-detailpagina, "Verwerkingsstatus"-regel WC-orderpagina), plus wat
 * kleine cosmetiek. De zoekpatronen worden opgebouwd uit wat de plugin-
 * strings in de huidige taal renderen, zodat dit ook op niet-NL shops matcht.
 */
function defib_afas_tt_relabel(string $html): string
{
    $map = static function (array $m): string {
        $label = defib_afas_tt_status_label($m[2]);
        // Ongewijzigde (of onbekende) waarden byte-voor-byte laten staan;
        // de plugin heeft ze al ge-escaped.
        return $label === trim($m[2]) ? $m[0] : $m[1] . esc_html($label) . $m[3];
    };

    $statusKop  = preg_quote(esc_attr(defib_afas_tt_plugin_t('Status')), '#');
    $verwStatus = preg_quote(esc_html(defib_afas_tt_plugin_t('Verwerkingsstatus:')), '#');

    $html = (string) preg_replace_callback('#(data-title="' . $statusKop . '">\s*)([^<]*?)(\s*<)#', $map, $html);
    $html = (string) preg_replace_callback('#(<mark class="order-status">)([^<]*)(</mark>)#', $map, $html);
    $html = (string) preg_replace_callback('#(' . $verwStatus . ' <strong>)([^<]*)(</strong>)#', $map, $html);

    // AFAS-only orders zijn grotendeels bestellingen uit de oude webshop;
    // "Extern" dekt de lading niet. (De badge--wc-variant met het
    // webshopnummer heeft een andere titel en blijft dus ongemoeid.)
    $html = str_replace(
        'class="afas-extern-badge" title="' . esc_attr(defib_afas_tt_plugin_t('Geplaatst buiten de webshop')) . '">' . esc_html(defib_afas_tt_plugin_t('Extern')) . '</span>',
        'class="afas-extern-badge" title="' . esc_attr(defib_afas_tt_t('Eerdere bestelling uit onze administratie')) . '">' . esc_html(defib_afas_tt_t('Archief')) . '</span>',
        $html
    );

    // Het AFAS-nummer op de WooCommerce-orderpagina is voor de klant gewoon
    // hét ordernummer.
    $html = str_replace(
        esc_html(defib_afas_tt_plugin_t('Extern ordernummer:')),
        esc_html(defib_afas_tt_t('Ordernummer:')),
        $html
    );

    // De plugin-template zet het ordernummer op een eigen regel binnen de
    // <a>, waardoor de onderstreping een spatie te ver doorloopt. Witruimte
    // aan de randen van tekst-links (geen geneste tags) opruimen.
    $html = (string) preg_replace('#(<a\b[^>]*>)\s*([^<>]*?)\s*(</a>)#', '$1$2$3', $html);

    // Zelfde knoptekst als de WooCommerce-rijen; de match op de class
    // voorkomt dat andere teksten geraakt worden. (In het Engels zijn
    // beide al "View" en is dit een no-op.)
    $html = str_replace(
        'class="woocommerce-button button view">' . esc_html(defib_afas_tt_plugin_t('Bekijk')) . '</a>',
        'class="woocommerce-button button view">' . esc_html(defib_afas_tt_t('Bekijken')) . '</a>',
        $html
    );

    return $html;
}

/**
 * Herschik het AFAS-blok op de WooCommerce-orderpagina. De plugin print:
 * [track & trace][<p>ordernummer][<p>verwerkingsstatus] direct onder de
 * intro-zin van WooCommerce, wat rommelig leest. Dit voegt de twee losse
 * regels samen tot één en zet ze VOOR het track & trace-blok, zodat de
 * volgorde wordt: zin, ordernummer + status, track & trace, besteltabel.
 * Moet op de ORIGINELE pluginmarkup draaien (vóór restyle: geen geneste
 * divs in het blok, en relabel verwacht de originele teksten nog).
 */
function defib_afas_tt_wc_order_layout(string $html): string
{
    $nr = preg_quote(esc_html(defib_afas_tt_plugin_t('Extern ordernummer:')), '#');
    $st = preg_quote(esc_html(defib_afas_tt_plugin_t('Verwerkingsstatus:')), '#');

    $html = (string) preg_replace(
        '#<p>(' . $nr . ' <strong>[^<]*</strong>)</p>\s*<p>(' . $st . ' <strong>[^<]*</strong>)</p>#',
        '<p>$1 &nbsp;&middot;&nbsp; $2</p>',
        $html,
        1
    );

    if (preg_match('#<div class="afas-pakbonnen" id="afas-track-trace">.*?</div>#s', $html, $blok)) {
        $rest = str_replace($blok[0], '', $html);
        if (trim($rest) !== '') {
            $html = $rest . $blok[0];
        }
    }

    return $html;
}

/**
 * Vervang de eigen besteltabel van de plugin (.afas-order-details) door markup
 * met de standaard WooCommerce order-details classes, zodat het thema de
 * AFAS-detailpagina identiek styleert aan een gewone view-order-pagina.
 * Bewust zonder bedrag per regel: de AFAS-regelbedragen sluiten niet aan op
 * het ordertotaal (excl./incl. en ontbrekende kostenregels); voor
 * archieforders volstaan de producten plus het ordertotaal.
 */
function defib_afas_tt_wc_table(string $html, string $ordernummer): string
{
    $overview = 'App\\WooCommerce\\OrderOverview';

    if ($ordernummer === '' || !preg_match('#<div class="afas-order-details">.*?</div>#s', $html, $oud)) {
        return $html;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'lef_afas_verkooporderregels';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $regels = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE ordernummer = %s ORDER BY regelnummer ASC",
        $ordernummer
    )) ?: [];

    if (empty($regels)) {
        return $html;
    }

    $rows = '';
    foreach ($regels as $regel) {
        $sku  = (string) ($regel->artikelnummer ?? '');
        $name = (string) ($regel->omschrijving ?: $sku);
        if ($name === '') {
            continue;
        }

        $nameHtml  = esc_html($name);
        $productId = $sku !== '' ? wc_get_product_id_by_sku($sku) : 0;
        if ($productId) {
            $nameHtml = '<a href="' . esc_url((string) get_permalink($productId)) . '">' . $nameHtml . '</a>';
        }

        $qtyHtml = '';
        if ($regel->aantal !== null && $regel->aantal !== '') {
            $aantal = (float) $regel->aantal;
            $qty    = $aantal == (int) $aantal ? (string) (int) $aantal : wc_format_localized_decimal((string) $aantal);
            $eenheid = (string) ($regel->eenheid ?? '');
            // STK is de default en ruis ('*****' e.d.) verbergen we; een
            // afwijkende eenheid (DOZ, SET, KG, ...) is juist relevant.
            $toonEenheid = $eenheid !== '' && strtoupper($eenheid) !== 'STK' && preg_match('/[a-z0-9]/i', $eenheid);
            $qtyHtml = ' <strong class="product-quantity">&times;&nbsp;' . esc_html($qty)
                . ($toonEenheid ? '&nbsp;' . esc_html($eenheid) : '')
                . '</strong>';
        }

        $skuHtml = ($sku !== '' && $sku !== $name)
            ? '<br><small class="afas-line-sku">' . esc_html($sku) . '</small>'
            : '';

        $rows .= '<tr class="woocommerce-table__line-item order_item">'
            . '<td class="woocommerce-table__product-name product-name" colspan="2">' . $nameHtml . $qtyHtml . $skuHtml . '</td>'
            . '</tr>';
    }

    if ($rows === '') {
        return $html;
    }

    $order = $overview::getVerkooporder($ordernummer);
    $foot  = ($order !== null && $order->totaal !== null)
        ? '<tfoot><tr><th scope="row">' . esc_html(defib_afas_tt_t('Totaal:')) . '</th><td>' . wp_kses_post(wc_price((float) $order->totaal)) . '</td></tr></tfoot>'
        : '';

    $section = '<section class="woocommerce-order-details">'
        . '<style>.woocommerce-order-details .afas-line-sku{opacity:.65}</style>'
        . '<h2 class="woocommerce-order-details__title">' . esc_html(defib_afas_tt_t('Bestelgegevens')) . '</h2>'
        . '<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">'
        . '<thead><tr>'
        . '<th class="woocommerce-table__product-name product-name" colspan="2">' . esc_html(defib_afas_tt_t('Product')) . '</th>'
        . '</tr></thead>'
        . '<tbody>' . $rows . '</tbody>'
        . $foot
        . '</table></section>';

    return str_replace($oud[0], $section, $html);
}

/**
 * Vervang de inhoud van het afas-pakbonnen-blok in de gebufferde plugin-output
 * door de nette weergave. Laat de HTML ongemoeid bij twijfel.
 */
function defib_afas_tt_restyle(string $html, string $ordernummer): string
{
    if ($ordernummer === '' || strpos($html, 'id="afas-track-trace"') === false) {
        return $html;
    }

    $overview = 'App\\WooCommerce\\OrderOverview';
    $block    = defib_afas_tt_block($overview::getPakbonnen($ordernummer));
    if ($block === '') {
        return $html;
    }

    // NB: beide regexes hieronder matchen lazy tot de eerste </div> en werken
    // dus alleen op de ORIGINELE pluginmarkup, die binnen het blok geen
    // geneste divs heeft. Daarom eerst verplaatsen, dan pas de inhoud
    // vervangen (het eigen blok bevat wél geneste divs).

    // Op de AFAS-detailpagina print de plugin het pakbonnenblok vóór de
    // statuszin; verplaats het naar net boven de Bestelgegevens-tabel zodat
    // de zin "Bestelling ... heeft de status ..." weer bovenaan staat.
    $target = '<div class="afas-order-details">';
    if (strpos($html, $target) !== false
        && preg_match('#<div class="afas-pakbonnen" id="afas-track-trace">.*?</div>#s', $html, $blok)) {
        $html = str_replace($blok[0], '', $html);
        $html = str_replace($target, $blok[0] . $target, $html);
    }

    $out = preg_replace_callback(
        '#(<div class="afas-pakbonnen" id="afas-track-trace">)(.*?)(</div>)#s',
        static function (array $m) use ($block): string {
            // Het <style>-blok dat de plugin hier print (printTableCss) stylet
            // ook de orderregels-tabel op de pagina; behouden dus.
            preg_match('#<style>.*?</style>#s', $m[2], $style);
            return $m[1] . ($style[0] ?? '') . $block . $m[3];
        },
        $html,
        1
    );

    return $out ?? $html;
}

/**
 * Bouw de nette track & trace-lijst uit pakbon-rijen (lef_afas_pakbonnen).
 *
 * @param object[] $pakbonnen
 */
function defib_afas_tt_block(array $pakbonnen): string
{
    $items = '';

    foreach ($pakbonnen as $pb) {
        $url = (string) ($pb->track_trace_url ?? '');
        if ($url === '') {
            continue;
        }

        $carrier = defib_afas_tt_carrier((string) parse_url($url, PHP_URL_HOST));
        $code    = defib_afas_tt_code($url);

        $meta = [];
        if ((string) ($pb->pakbonnummer ?? '') !== '') {
            $meta[] = defib_afas_tt_t('Pakbon') . ' ' . $pb->pakbonnummer;
        }
        if (!empty($pb->pakbondatum)) {
            $meta[] = date_i18n(get_option('date_format'), (int) strtotime((string) $pb->pakbondatum));
        }
        // Bewust geen pakbonstatus: die is intern; de klant heeft genoeg aan
        // vervoerder, code en datum (de actuele status staat bij de vervoerder).

        $items .= '<li class="afas-tt-row"><div class="afas-tt-info">'
            . '<span class="afas-tt-carrier">' . esc_html($carrier) . '</span>'
            . ($code !== '' ? '<span class="afas-tt-code">' . esc_html($code) . '</span>' : '')
            . (!empty($meta) ? '<span class="afas-tt-meta">' . esc_html(implode(' · ', $meta)) . '</span>' : '')
            . '</div>'
            . '<a class="woocommerce-button button afas-tt-button" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html(defib_afas_tt_t('Volg zending')) . '</a>'
            . '</li>';
    }

    if ($items === '') {
        return '';
    }

    return '<style>'
        . '.afas-pakbonnen ul.afas-tt-list{list-style:none;margin:0 0 1.5em;padding:0}'
        . '.afas-tt-row{display:flex;align-items:center;justify-content:space-between;gap:1em;flex-wrap:wrap;padding:.85em 1em;border:1px solid rgba(0,0,0,.15)}'
        . '.afas-tt-row+.afas-tt-row{border-top:0}'
        . '.afas-tt-info{display:flex;flex-direction:column;gap:.2em}'
        . '.afas-tt-carrier{font-weight:600}'
        . '.afas-tt-code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.9em}'
        . '.afas-tt-meta{font-size:.85em;opacity:.7}'
        . '.afas-pakbonnen a.afas-tt-button{word-break:normal;white-space:nowrap}'
        . '</style>'
        . '<h3>Track &amp; trace</h3>'
        . '<ul class="afas-tt-list">' . $items . '</ul>';
}

/** Vervoerdersnaam op basis van de hostname van de track & trace-URL. */
function defib_afas_tt_carrier(string $host): string
{
    $host = strtolower($host);

    foreach ([
        'ups.'   => 'UPS',
        'fedex'  => 'FedEx',
        'postnl' => 'PostNL',
        'dhl'    => 'DHL',
        'dpd'    => 'DPD',
        'gls'    => 'GLS',
    ] as $needle => $name) {
        if (strpos($host, $needle) !== false) {
            return $name;
        }
    }

    return (string) preg_replace('/^www\./', '', $host);
}

/** Trackingcode uit de bekende querystring-parameters van vervoerders. */
function defib_afas_tt_code(string $url): string
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
    $params = array_change_key_case($params, CASE_LOWER);

    foreach (['tracknum', 'tracknumbers', 'trknbr', 'trackingnumber', 'tracknr', 'barcode', 'parcelnr'] as $key) {
        if (!empty($params[$key]) && is_string($params[$key])) {
            // Meerdere codes (kommagescheiden) toont FedEx soms in één param.
            return trim(str_replace(',', ', ', $params[$key]));
        }
    }

    return '';
}
