/**
 * file   : main.js
 * path   : C:\xampp\htdocs\backuprm\assets\js\main.js
 * fungsi : NProgress, Select2, sidebar helpers
 */
(function () {
    'use strict';

    /* ============================================================
       NProgress
       ============================================================ */
    if (window.NProgress) {
        NProgress.configure({
            showSpinner: false,
            trickleSpeed: 200,
            minimum: 0.10,
            easing: 'ease',
            speed: 300
        });
    }

    window.addEventListener('load', function () {
        if (window.NProgress) NProgress.done();
    });
    window.addEventListener('pageshow', function () {
        if (window.NProgress) NProgress.done();
    });

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form.dataset.noprogress === '1') return;
        if (window.NProgress) NProgress.start();
    });

    if (window.jQuery) {
        jQuery(document)
            .ajaxStart(function () { if (window.NProgress) NProgress.start(); })
            .ajaxStop(function ()  { if (window.NProgress) NProgress.done(); });
    }

    /* ============================================================
       SELECT2 auto-init
       ============================================================ */
    function initSelect2(root) {
        if (!window.jQuery || !jQuery.fn.select2) return;
        jQuery(root || document).find('select.select2').each(function () {
            const $el = jQuery(this);
            if ($el.data('select2')) return;
            $el.select2({
                width: '100%',
                placeholder: $el.data('placeholder') || '— Pilih —',
                allowClear: $el.data('allow-clear') !== false
            });
        });
    }
    window.initSelect2 = initSelect2;

    document.addEventListener('DOMContentLoaded', function () {
        initSelect2(document);
    });

    /* ============================================================
       TOGGLE PASSWORD (login page)
       ============================================================ */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('#togglePass');
        if (!btn) return;
        const inp = document.getElementById('password');
        if (!inp) return;
        const icon = btn.querySelector('i');
        if (inp.type === 'password') {
            inp.type = 'text';
            if (icon) { icon.classList.remove('ri-eye-line'); icon.classList.add('ri-eye-off-line'); }
        } else {
            inp.type = 'password';
            if (icon) { icon.classList.remove('ri-eye-off-line'); icon.classList.add('ri-eye-line'); }
        }
    });

})();