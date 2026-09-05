/* =====================================================================
   ISP Management System - shared front-end behaviour
   ===================================================================== */
(function () {
  'use strict';

  // Sidebar toggle (mobile)
  document.addEventListener('DOMContentLoaded', function () {
    var toggleBtn = document.getElementById('sidebarToggle');
    var sidebar = document.querySelector('.ism-sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');

    function closeSidebar() {
      sidebar && sidebar.classList.remove('show');
      backdrop && backdrop.classList.add('d-none');
    }

    if (toggleBtn && sidebar) {
      toggleBtn.addEventListener('click', function () {
        sidebar.classList.toggle('show');
        if (backdrop) backdrop.classList.toggle('d-none');
      });
    }
    if (backdrop) backdrop.addEventListener('click', closeSidebar);

    // Bootstrap tooltips
    if (window.bootstrap) {
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
      });
    }

    // Auto-dismiss toasts
    document.querySelectorAll('.toast').forEach(function (el) {
      if (window.bootstrap) {
        new bootstrap.Toast(el, { delay: 5000 }).show();
      }
    });

    // Generic delete confirmation
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        var msg = form.getAttribute('data-confirm') || 'Are you sure you want to delete this record?';
        if (!window.confirm(msg)) {
          e.preventDefault();
        }
      });
    });

    // DataTables defaults (only if a table opts in via .datatable class)
    if (window.jQuery && jQuery.fn.DataTable) {
      jQuery('.datatable').each(function () {
        jQuery(this).DataTable({
          pageLength: 25,
          order: [],
          language: { search: '', searchPlaceholder: 'Quick filter this page...' },
        });
      });
    }
  });

  // Shared Chart.js color palette
  window.ISM_COLORS = [
    '#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#8b5cf6',
    '#14b8a6', '#ec4899', '#0ea5e9', '#84cc16', '#f97316'
  ];

  window.ismToast = function (type, message) {
    var container = document.getElementById('toastContainer');
    if (!container) return;
    var map = { success: 'text-bg-success', danger: 'text-bg-danger', warning: 'text-bg-warning', info: 'text-bg-primary' };
    var el = document.createElement('div');
    el.className = 'toast align-items-center border-0 ' + (map[type] || map.info);
    el.setAttribute('role', 'alert');
    el.innerHTML = '<div class="d-flex"><div class="toast-body">' + message + '</div>' +
      '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
    container.appendChild(el);
    if (window.bootstrap) new bootstrap.Toast(el, { delay: 5000 }).show();
  };

  // Simple debounce helper used by live-search inputs
  window.ismDebounce = function (fn, delay) {
    var t;
    return function () {
      var args = arguments, ctx = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, delay || 350);
    };
  };
})();
