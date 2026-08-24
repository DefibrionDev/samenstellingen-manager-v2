<?php
/**
 * Plugin Name: Checkout AJAX fallback (Defibrion)
 * Description: Vangt falende ?wc-ajax=update_order_review responses af (WAF-block, verlopen sessie, HTML i.p.v. JSON). WooCommerce core heeft daar geen error-afhandeling, waardoor het order-review blok anders permanent blijft hangen in een spinner. Eén automatische reload; blijft het misgaan, dan verdwijnt de spinner en verschijnt een duidelijke melding.
 * Author: Defibrion / Cas van Noort
 *
 * Deploy: kopieer dit bestand naar wp-content/mu-plugins/ (map aanmaken als
 * die nog niet bestaat). Geen activatie nodig; mu-plugins laden altijd.
 */

defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    if (!function_exists('is_checkout') || !is_checkout() || is_wc_endpoint_url()) {
        return;
    }

    $js = <<<'JS'
(function ($) {
    var handled = false;

    function isOrderReview(settings) {
        return settings && settings.url
            && settings.url.indexOf('wc-ajax=update_order_review') !== -1;
    }

    // WooCommerce breekt het lopende update_order_review-request zelf af
    // wanneer er snel een nieuwe update start (xhr.abort() in wc-checkout.js).
    // Dat is normaal gedrag, geen fout.
    function isAbort(xhr) {
        return xhr && xhr.status === 0 && (xhr.statusText === 'abort' || xhr.statusText === 'canceled');
    }

    function showError() {
        if ($.fn.unblock) {
            $('#order_review').unblock();
            $('.woocommerce-checkout-review-order-table').unblock();
        }
        var wrapper = $('.woocommerce-notices-wrapper').first();
        if (!wrapper.length) {
            wrapper = $('<div class="woocommerce-notices-wrapper"></div>')
                .prependTo($('form.checkout').parent());
        }
        wrapper.prepend(
            '<div class="woocommerce-error" role="alert">' +
            'Something went wrong while updating your order. ' +
            'Please reload the page and try again, or contact us if the problem persists.' +
            '</div>'
        );
        $('html, body').animate({ scrollTop: 0 }, 300);
    }

    function bail() {
        if (handled) return;
        handled = true;

        // Max één automatische reload per 30s (sessionStorage overleeft de
        // reload), anders zou een structureel falend endpoint een
        // reload-loop veroorzaken.
        var last = 0;
        try { last = parseInt(sessionStorage.getItem('wcOrrReloadTs') || '0', 10); } catch (e) {}
        var now = Date.now();

        if (now - last > 30000) {
            try { sessionStorage.setItem('wcOrrReloadTs', String(now)); } catch (e) {}
            window.location.reload();
            return;
        }
        showError();
    }

    $(document).ajaxError(function (e, xhr, settings, thrownError) {
        if (!isOrderReview(settings)) return;
        if (isAbort(xhr) || thrownError === 'abort') return;
        bail();
    });

    $(document).ajaxComplete(function (e, xhr, settings) {
        if (!isOrderReview(settings) || isAbort(xhr)) return;
        var body = (xhr.responseText || '').trim();
        // WC hoort JSON te leveren. "-1" = nonce/sessie verlopen (403),
        // "<" = HTML-pagina (loginpagina, WAF-blockpagina, cachepagina).
        if (xhr.status !== 200 || body === '-1' || body.charAt(0) === '<') bail();
    });
})(jQuery);
JS;

    // Aan wc-checkout hangen: print alleen waar WooCommerce zijn checkout-JS
    // ook echt laadt, en gegarandeerd ná die scripts.
    wp_add_inline_script('wc-checkout', $js, 'after');
}, 20);
