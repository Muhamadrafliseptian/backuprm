/**
 * file   : theme-helper.js
 * path   : C:\xampp\htdocs\backuprm\assets\js\theme-helper.js
 * fungsi : Helper modal, toast, confirm (Tailwind-based)
 *          + helper logout via POST (sesuai routing actions/*)
 */
(function () {
    'use strict';

    /* ============================================================
       TOAST NOTIFICATION (Tailwind-based)
       ============================================================ */
    window.showToast = function (message, type = 'success') {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'fixed bottom-5 right-5 z-[100] space-y-2';
            document.body.appendChild(container);
        }

        const icons = {
            success: 'ri-checkbox-circle-fill',
            error:   'ri-close-circle-fill',
            warning: 'ri-alert-fill',
            info:    'ri-information-fill'
        };
        const colors = {
            success: 'bg-mint-600',
            error:   'bg-rose-600',
            warning: 'bg-amber-500',
            info:    'bg-slate-800'
        };

        const toast = document.createElement('div');
        toast.className = `toast-enter flex items-center gap-2.5 px-4 py-3 rounded-xl text-xs font-semibold shadow-lg text-white ${colors[type] || colors.info}`;
        toast.innerHTML = `<i class="${icons[type] || icons.info} text-lg"></i> <span>${message}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.transition = 'opacity .3s, transform .3s';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };

    /* ============================================================
       CONFIRM DIALOG (SweetAlert2 wrapper)
       ============================================================ */
    window.confirmAction = function (opts) {
        if (!window.Swal) return Promise.resolve(false);
        return Swal.fire({
            title: opts.title || 'Yakin?',
            text: opts.text || '',
            icon: opts.icon || 'warning',
            showCancelButton: true,
            confirmButtonText: opts.confirmText || 'Ya, lanjutkan',
            cancelButtonText: opts.cancelText || 'Batal',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#7A8793',
            reverseButtons: true
        }).then(r => r.isConfirmed);
    };

    /* ============================================================
       SIDEBAR TOGGLE
       ============================================================ */
    window.toggleSidebar = function () {
        const sb = document.getElementById('sidebar');
        const bd = document.getElementById('mobileBackdrop');
        if (!sb) return;
        sb.classList.toggle('-translate-x-full');
        if (bd) bd.classList.toggle('hidden');
    };

    /* ============================================================
       USER DROPDOWN
       ============================================================ */
    window.toggleUserMenu = function () {
        const menu = document.getElementById('userMenu');
        if (!menu) return;
        menu.classList.toggle('hidden');
    };

    // Close dropdown when clicking outside
    document.addEventListener('click', function (e) {
        const dd = document.getElementById('userDropdown');
        const menu = document.getElementById('userMenu');
        if (!dd || !menu) return;
        if (!dd.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });

    /* ============================================================
       LOGOUT — POST ke /actions/logout (bukan GET)
       ============================================================ */
    window.doLogout = function (reason) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.BASE_URL + '/actions/logout';
        form.style.display = 'none';

        const token = document.createElement('input');
        token.type  = 'hidden';
        token.name  = '_token';
        token.value = window.CSRF_TOKEN || '';

        const r = document.createElement('input');
        r.type  = 'hidden';
        r.name  = 'reason';
        r.value = reason || 'manual';

        form.appendChild(token);
        form.appendChild(r);
        document.body.appendChild(form);
        form.submit();
    };

    window.confirmLogout = function (e) {
        if (e) e.preventDefault();
        if (!window.Swal) {
            if (confirm('Keluar dari sistem?')) {
                window.doLogout('manual');
            }
            return;
        }
        Swal.fire({
            icon: 'question',
            title: 'Keluar dari sistem?',
            text: 'Anda akan keluar dari sesi saat ini.',
            showCancelButton: true,
            confirmButtonText: 'Ya, Logout',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#E63946',
            cancelButtonColor: '#7A8793',
            reverseButtons: true
        }).then(function (r) {
            if (r.isConfirmed) {
                window.doLogout('manual');
            }
        });
    };

    /* ============================================================
       AUTO-CLOSE SIDEBAR ON MOBILE NAV CLICK
       ============================================================ */
    document.addEventListener('click', function (e) {
        const link = e.target.closest('#sidebar nav a');
        if (!link) return;
        if (window.innerWidth > 768) return;
        const sb = document.getElementById('sidebar');
        const bd = document.getElementById('mobileBackdrop');
        if (sb) sb.classList.add('-translate-x-full');
        if (bd) bd.classList.add('hidden');
    });

    /* ============================================================
       RESIZE HANDLER
       ============================================================ */
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            const sb = document.getElementById('sidebar');
            const bd = document.getElementById('mobileBackdrop');
            if (sb) sb.classList.remove('-translate-x-full');
            if (bd) bd.classList.add('hidden');
        }
    });

})();