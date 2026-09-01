<?php

declare(strict_types=1);

/**
 * Plugin Name: DefibSolutions login-fix
 * Description: Repareert twee euvels van admin-custom-login op wp-login.php
 * (bestonden ook al op de oude live-site, besluit Cas 31 aug 2026 om te fixen):
 *
 * 1. "Caps lock staat aan." altijd zichtbaar — WP-core verbergt #caps-warning
 *    via de selector `.wp-pwd .caps-warning`, maar admin-custom-login herbouwt
 *    de wachtwoord-markup (.input-container i.p.v. .wp-pwd) waardoor die regel
 *    nooit matcht. Fix: zelf verbergen, bewust ZONDER !important zodat
 *    user-profile.js de melding bij échte caps lock gewoon kan tonen
 *    (jQuery .show() zet inline display en wint dan van deze regel).
 *
 * 2. "Onthoud mij" niet aanklikbaar — de submit-paragraaf (#submit_input,
 *    position:relative) overlapt de .forgetmenot-regel, dus kliks landen op
 *    de submit-p in plaats van op de checkbox. Fix: forgetmenot een eigen
 *    stacking-laag geven; de Inloggen-knop staat rechts ernaast en heeft
 *    nergens last van.
 */

add_action('login_enqueue_scripts', static function (): void {
    ?>
    <style id="defibs-login-fix">
        .login #caps-warning { display: none; }
        .login form .forgetmenot { position: relative; z-index: 2; }
    </style>
    <?php
});
