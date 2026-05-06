// ===== Haley Yachts site analytics =====
// Loads Google Analytics 4 and Microsoft Clarity, then wires conversion events.
// Drop-in: include with <script src="js/analytics.js" defer></script> in <head>.
// Adjust the relative path for nested pages (e.g. ../../js/analytics.js for articles).

(function () {
    var GA_ID = 'G-6CVE0DG8Z3';
    var CLARITY_ID = 'wn3878tuvv';

    // ---------- Google Analytics 4 ----------
    var gaScript = document.createElement('script');
    gaScript.async = true;
    gaScript.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_ID;
    document.head.appendChild(gaScript);

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    gtag('js', new Date());
    gtag('config', GA_ID, { anonymize_ip: true });

    // ---------- Microsoft Clarity ----------
    (function (c, l, a, r, i, t, y) {
        c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments); };
        t = l.createElement(r); t.async = 1; t.src = 'https://www.clarity.ms/tag/' + i;
        y = l.getElementsByTagName(r)[0]; y.parentNode.insertBefore(t, y);
    })(window, document, 'clarity', 'script', CLARITY_ID);

    // ---------- Helpers ----------
    function track(eventName, params) {
        try {
            gtag('event', eventName, params || {});
            if (window.clarity) window.clarity('set', eventName, JSON.stringify(params || {}));
        } catch (e) { /* fail quiet */ }
    }

    // Pull a yacht model name out of a mailto subject like
    // "Riviera 4300 Sports Express Inquiry" -> "Riviera 4300 Sports Express"
    function modelFromMailto(href) {
        try {
            var url = new URL(href);
            var subj = url.searchParams.get('subject') || '';
            return subj.replace(/\s+Inquiry\s*$/i, '').trim() || null;
        } catch (e) { return null; }
    }

    // ---------- Wire events ----------
    document.addEventListener('DOMContentLoaded', function () {

        // Phone + email click delegation (works for any tel:/mailto: anywhere on the page)
        document.body.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('a[href]');
            if (!a) return;
            var href = a.getAttribute('href') || '';
            if (href.indexOf('tel:') === 0) {
                track('phone_click', {
                    page_path: location.pathname,
                    link_text: (a.textContent || '').trim().slice(0, 80)
                });
            } else if (href.indexOf('mailto:') === 0) {
                var model = modelFromMailto('http://x' + href.slice(7).replace('?', '/?'));
                track('email_click', {
                    page_path: location.pathname,
                    link_text: (a.textContent || '').trim().slice(0, 80),
                    model: model || undefined
                });
            }
        }, true);

        // Contact form (AJAX Formsubmit - submit handler fires before XHR)
        var contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', function () {
                track('contact_form_submit', {
                    page_path: location.pathname,
                    form_id: 'contactForm'
                });
            });
        }

        // Valuation form (non-AJAX Formsubmit - page will navigate away).
        // Use transport beacon so the event makes it before navigation.
        var valuationForm = document.getElementById('valuationForm');
        if (valuationForm) {
            valuationForm.addEventListener('submit', function () {
                track('valuation_request_submit', {
                    page_path: location.pathname,
                    form_id: 'valuationForm',
                    transport_type: 'beacon'
                });
            });
        }

        // Engaged-view proxy for the Buy a Yacht page (90s = serious browser).
        // Fires once per page load.
        if (location.pathname.endsWith('/buy.html') || location.pathname.endsWith('/buy')) {
            setTimeout(function () {
                if (!document.hidden) {
                    track('buy_engaged_view', { page_path: location.pathname });
                }
            }, 90000);
        }
    });
})();
