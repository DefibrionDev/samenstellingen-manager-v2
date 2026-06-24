/**
 * AFAS B2B, frontend pricing controller (customer staffel + admin preview).
 *
 * Reads window.afasPriceContext (emitted server-side by PriceHooks), tracks
 * the currently-active artikelnummer (which changes when the user selects a
 * variation), and re-renders the staffel table + admin preview accordingly.
 *
 * Product-type strategy: the active artikelnummer is sourced from whichever
 * UI signal is present. Today: WC variations_form events. Other product
 * types (bundles, composites) can plug in by dispatching
 *   document.dispatchEvent(new CustomEvent('afas:price-context:set', { detail: { artikelnummer, tiers, priceHtml } }))
 * which this script honours as a generic external override.
 *
 * Context shape (from PHP):
 *   afasPriceContext = {
 *     productId, productType, isAdmin,
 *     ajaxUrl, previewNonce,
 *     default:    { artikelnummer, tiers:[{vanaf,prijs,prijsHtml}], priceHtml },
 *     variations: { <variationId>: {artikelnummer, tiers, priceHtml}, ... },
 *     prijslijsten: [...],
 *     myPrijslijstLabel: '...'
 *   }
 */
(function () {
    'use strict';

    var ctx = window.afasPriceContext;
    if (!ctx) {
        return;
    }

    // -----------------------------------------------
    // State
    // -----------------------------------------------

    var activeArtikelnummer = '';
    var activeTiers         = [];
    var activePriceHtml     = '';
    // Auto-preselect the admin's own prijslijst when the server tells us to.
    // Server only ships this for admins (manage_options), customers receive
    // an empty string so their default view stays customer-discounted.
    var activePrijslijstId  = (ctx.myPrijslijstId || '');

    var qtyBoundEl = null;

    // -----------------------------------------------
    // Bootstrap
    // -----------------------------------------------

    function start() {
        bindVariationForm();
        bindExternalContextOverride();
        bindAdminPreview();

        var form = document.querySelector('form.variations_form');
        if (form) {
            // Variable product, wait for WC to fire show_variation. Until then
            // there's nothing meaningful to display (no variation = no price).
            // If WC has a preselected variation it will fire immediately.
            renderStaffel();
            return;
        }

        // Non-variation product (simple, external, subscription, ...), use default.
        setActiveContext(ctx.default || null, 'default');
    }

    // -----------------------------------------------
    // Context source: WC variations_form
    // -----------------------------------------------

    function bindVariationForm() {
        var form = document.querySelector('form.variations_form');
        if (!form || !window.jQuery) {
            return;
        }
        var $f = window.jQuery(form);

        $f.on('show_variation found_variation', function (e, variation) {
            if (!variation || typeof variation.variation_id === 'undefined') {
                return;
            }
            var v = ctx.variations && ctx.variations[variation.variation_id];
            if (v) {
                setActiveContext(v, 'variation');
            } else {
                // Variation has no AFAS artikelnummer / no pricing.
                clearActiveContext('variation');
            }
        });

        $f.on('hide_variation reset_data', function () {
            clearActiveContext('variation');
        });

        // Catch the race where WC has already selected a variation (default
        // attributes, browser back, ?attribute_pa_X=... in the URL) before
        // our listeners bound. WC won't re-fire show_variation, so without
        // this nudge the staffel stays empty until the user re-picks.
        var preselected = currentVariationId();
        if (preselected > 0 && ctx.variations && ctx.variations[preselected]) {
            setActiveContext(ctx.variations[preselected], 'variation-initial');
        }
    }

    // External context source, bundles / composites / 3rd-party can dispatch this.
    function bindExternalContextOverride() {
        document.addEventListener('afas:price-context:set', function (e) {
            if (!e.detail) return;
            setActiveContext(e.detail, e.detail.source || 'external');
        });
    }

    function setActiveContext(c, source) {
        if (!c) {
            clearActiveContext(source);
            return;
        }
        activeArtikelnummer = c.artikelnummer || '';

        // If a prijslijst preview is active, re-fetch for the new artikelnummer
        // instead of using the embedded "own prices" tiers.
        if (activePrijslijstId !== '' && activeArtikelnummer !== '') {
            fetchPrijslijstPreview(activePrijslijstId);
            dispatchChange(source);
            return;
        }

        activeTiers     = c.tiers     || [];
        activePriceHtml = c.priceHtml || '';
        renderStaffel();
        dispatchChange(source);
    }

    function clearActiveContext(source) {
        activeArtikelnummer = '';
        activeTiers         = [];
        activePriceHtml     = '';
        renderStaffel();
        dispatchChange(source);
    }

    function dispatchChange(source) {
        document.dispatchEvent(new CustomEvent('afas:price-context:change', {
            detail: {
                artikelnummer:  activeArtikelnummer,
                tiers:          activeTiers,
                priceHtml:      activePriceHtml,
                prijslijstId:   activePrijslijstId,
                source:         source || ''
            }
        }));
    }

    // -----------------------------------------------
    // Staffel table render + qty-aware price update
    // -----------------------------------------------

    var STAFFEL_HOST_ID  = 'afas-staffel-host';
    var STAFFEL_TABLE_ID = 'afas-staffel-table';

    function ensureHost() {
        var host = document.getElementById(STAFFEL_HOST_ID);
        if (host) return host;

        // Server-rendered fallback host (non-variable) is already in the DOM.
        // For variable products we create it on demand right after the
        // add-to-cart button, same position the PHP hook used to.
        var cartBtn = document.querySelector('.single_add_to_cart_button');
        if (!cartBtn) return null;

        host    = document.createElement('div');
        host.id = STAFFEL_HOST_ID;
        cartBtn.insertAdjacentElement('afterend', host);
        return host;
    }

    function renderStaffel() {
        var host = ensureHost();
        if (host) {
            if (!activeTiers || activeTiers.length < 2) {
                host.innerHTML = '';
            } else {
                host.innerHTML = buildStaffelTableHtml(activeTiers);
                bindQtyListener();
            }
        }
        // Always refresh the displayed price, even when the active context
        // has no staffel (single tier or just a base price). The early-return
        // below this line was the bug: switching to a non-staffel prijslijst
        // ran the AJAX but never repainted the price.
        updatePriceDisplay();
    }

    function buildStaffelTableHtml(tiers) {
        var rows = '';
        for (var i = 0; i < tiers.length; i++) {
            var t     = tiers[i];
            var label = t.vanaf === 1 ? '1 unit' : t.vanaf + '+ units';
            rows += '<tr>'
                +    '<td style="padding-right:2em;">' + escapeHtml(label) + '</td>'
                +    '<td>' + t.prijsHtml + '</td>'
                +  '</tr>';
        }
        var json = JSON.stringify(tiers).replace(/"/g, '&quot;');
        return '<table id="' + STAFFEL_TABLE_ID + '" class="afas-staffel-table shop_attributes"'
            +  ' data-tiers="' + json + '" style="width:auto;margin:.75em 0 1em;">'
            +  '<thead><tr>'
            +    '<th style="text-align:left;padding-right:2em;">Quantity</th>'
            +    '<th style="text-align:left;">Price per unit</th>'
            +  '</tr></thead>'
            +  '<tbody>' + rows + '</tbody>'
            +  '</table>';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
        });
    }

    function bindQtyListener() {
        var qtyInput = document.querySelector('form.cart .qty');
        if (!qtyInput || qtyBoundEl === qtyInput) return;

        qtyInput.addEventListener('change', updatePriceDisplay);
        qtyInput.addEventListener('input',  updatePriceDisplay);
        qtyBoundEl = qtyInput;
    }

    function currentQty() {
        var qtyInput = document.querySelector('form.cart .qty');
        var qty      = qtyInput ? parseInt(qtyInput.value, 10) : NaN;
        return qty > 0 ? qty : 1;
    }

    function resolveTierIndex(qty) {
        var idx = 0;
        for (var i = 0; i < activeTiers.length; i++) {
            if (activeTiers[i].vanaf <= qty) idx = i;
        }
        return idx;
    }

    /**
     * Update the on-page price element from active context. Works for any
     * active state:
     *   - 2+ tiers: pick the tier matching the current qty
     *   - 0/1 tier: use the base priceHtml (qty=1 single price)
     * Also highlights the active staffel row when a table is rendered.
     */
    function updatePriceDisplay() {
        if (!activePriceHtml && activeTiers.length === 0) {
            return;
        }

        var html;
        var activeIdx = -1;
        if (activeTiers.length >= 2) {
            activeIdx = resolveTierIndex(currentQty());
            html      = activeTiers[activeIdx].prijsHtml;
        } else {
            html = activePriceHtml;
        }

        if (html) {
            // Variation price element (variable products) takes precedence
            // over the summary price element (simple products). WC renders
            // the variation price into .woocommerce-variation-price on
            // show_variation.
            var priceEl = document.querySelector('.woocommerce-variation-price .price')
                || document.querySelector('.summary .price');
            if (priceEl) {
                var amountEl = priceEl.querySelector('.woocommerce-Price-amount');
                if (amountEl) {
                    var tmp = document.createElement('span');
                    tmp.innerHTML = html;
                    var newAmount = tmp.querySelector('.woocommerce-Price-amount');
                    if (newAmount) {
                        amountEl.outerHTML = newAmount.outerHTML;
                    } else {
                        // priceHtml is a raw amount string, drop it in.
                        priceEl.innerHTML = html;
                    }
                } else {
                    // Theme stripped .woocommerce-Price-amount, overwrite the
                    // whole price element rather than silently no-op.
                    priceEl.innerHTML = html;
                }
            }
        }

        if (activeIdx >= 0) {
            var table = document.getElementById(STAFFEL_TABLE_ID);
            if (table) {
                var rows = table.querySelectorAll('tbody tr');
                rows.forEach(function (r, i) {
                    r.style.fontWeight = i === activeIdx ? 'bold' : '';
                });
            }
        }
    }

    // -----------------------------------------------
    // Admin preview bar
    // -----------------------------------------------

    /**
     * Refresh the dropdown's per-option `(N)` count suffix from the
     * currently-active context. For variable products this means the
     * counts update on every variation switch, same artikelnummer that
     * drives the price + staffel.
     */
    function refreshDropdownCounts() {
        var select = document.getElementById('afas-preview-select');
        if (!select) return;

        var counts = activePrijslijstCounts();

        Array.prototype.forEach.call(select.options, function (opt) {
            if (!opt.value) return; // skip "- Eigen prijzen -"
            var base = opt.getAttribute('data-base-label');
            if (base === null) return; // option lacks data → leave alone
            var n = counts[opt.value] || 0;
            opt.textContent = n > 0 ? (base + ' (' + n + ')') : base;
        });
    }

    function activePrijslijstCounts() {
        var vid = currentVariationId();
        if (vid > 0 && ctx.variations && ctx.variations[vid] && ctx.variations[vid].prijslijstCounts) {
            return ctx.variations[vid].prijslijstCounts;
        }
        if (ctx.default && ctx.default.prijslijstCounts) {
            return ctx.default.prijslijstCounts;
        }
        return {};
    }

    function bindAdminPreview() {
        // Refresh dropdown counts on every context change (variation switch,
        // prijslijst preview, init). Cheap, only walks ~50 options.
        document.addEventListener('afas:price-context:change', refreshDropdownCounts);
        // Initial paint for the case where the dropdown was rendered before
        // the first variation event (or non-variable products).
        refreshDropdownCounts();

        // Use event delegation so the bindings survive any code path that
        // injects the bar after DOMContentLoaded (admin themes, dynamic
        // page-builder rebinds, etc.).
        document.addEventListener('change', function (e) {
            if (!e.target || e.target.id !== 'afas-preview-select') return;

            activePrijslijstId = e.target.value;

            if (activePrijslijstId === '') {
                // Reset to embedded "own prices" for the currently-active context.
                var fresh = currentEmbeddedContext();
                if (fresh) {
                    activeArtikelnummer = fresh.artikelnummer || activeArtikelnummer;
                    activeTiers         = fresh.tiers         || [];
                    activePriceHtml     = fresh.priceHtml     || '';
                    renderStaffel();
                    dispatchChange('preview-reset');
                }
                setStatus('');
                return;
            }
            fetchPrijslijstPreview(activePrijslijstId);
        });

        document.addEventListener('click', function (e) {
            if (!e.target || e.target.id !== 'afas-preview-close') return;
            var bar = document.getElementById('afas-admin-preview');
            if (bar) bar.style.display = 'none';
        });
    }

    function currentEmbeddedContext() {
        var vid = currentVariationId();
        if (vid > 0 && ctx.variations && ctx.variations[vid]) {
            return ctx.variations[vid];
        }
        return ctx.default || null;
    }

    function currentVariationId() {
        var input = document.querySelector('form.variations_form input.variation_id');
        return input ? (parseInt(input.value, 10) || 0) : 0;
    }

    function setStatus(text) {
        var el = document.getElementById('afas-preview-status');
        if (el) el.textContent = text;
    }

    function fetchPrijslijstPreview(prijslijstId) {
        if (!activeArtikelnummer) {
            setStatus('geen artikelnummer');
            return;
        }
        setStatus('laden…');

        var body = new URLSearchParams();
        body.append('action',         'afas_admin_price_preview');
        body.append('nonce',          ctx.previewNonce);
        body.append('artikelnummer',  activeArtikelnummer);
        body.append('prijslijst_id',  prijslijstId);
        body.append('product_id',     String(ctx.productId));
        body.append('variation_id',   String(currentVariationId()));

        fetch(ctx.ajaxUrl, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            if (!resp || !resp.success || !resp.data) {
                setStatus('fout');
                return;
            }
            activeTiers     = resp.data.tiers     || [];
            activePriceHtml = resp.data.priceHtml || '';
            renderStaffel();
            dispatchChange('preview');

            if (!activePriceHtml && activeTiers.length === 0) {
                setStatus('geen prijs in deze lijst');
            } else {
                var select = document.getElementById('afas-preview-select');
                setStatus(select ? select.options[select.selectedIndex].text : '');
            }
        })
        .catch(function () {
            setStatus('fout');
        });
    }

    // -----------------------------------------------
    // Go
    // -----------------------------------------------

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
