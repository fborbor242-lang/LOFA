/* ============================================================
   ALEXANDER SHOP MANAGEMENT SYSTEM — CORE APP JS
   ============================================================ */

'use strict';

/* ── Sidebar toggle (mobile) ──────────────────────────────── */
const sidebar         = document.getElementById('sidebar');
const sidebarOverlay  = document.getElementById('sidebar-overlay');
const menuBtn         = document.getElementById('topbar-menu-btn');

function openSidebar() {
  sidebar?.classList.add('open');
  sidebarOverlay?.classList.add('open');
}

function closeSidebar() {
  sidebar?.classList.remove('open');
  sidebarOverlay?.classList.remove('open');
}

menuBtn?.addEventListener('click', openSidebar);
sidebarOverlay?.addEventListener('click', closeSidebar);

/* ── Profile dropdown ─────────────────────────────────────── */
const profileBtn  = document.getElementById('profile-btn');
const profileDrop = document.getElementById('profile-dropdown');

profileBtn?.addEventListener('click', (e) => {
  e.stopPropagation();
  profileDrop?.classList.toggle('open');
});

document.addEventListener('click', () => {
  profileDrop?.classList.remove('open');
});

/* ── Active nav item highlight ────────────────────────────── */
function setActiveNav() {
  const current = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-item').forEach(item => {
    const href = item.getAttribute('data-page') || '';
    if (href && current.includes(href)) {
      item.classList.add('active');
    }
  });
}
setActiveNav();

/* ── Toast notifications ──────────────────────────────────── */
function showToast(message, type = 'default', duration = 3000) {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const icons = {
    success: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`,
    danger:  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    warning: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    default: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`
  };

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `${icons[type] || icons.default}<span>${message}</span>`;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(20px)';
    toast.style.transition = 'opacity .2s, transform .2s';
    setTimeout(() => toast.remove(), 200);
  }, duration);
}

/* ── Modal helpers ────────────────────────────────────────── */
function openModal(id) {
  document.getElementById(id)?.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal(id) {
  document.getElementById(id)?.classList.remove('open');
  document.body.style.overflow = '';
}

/* Close modals on overlay click */
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) {
      overlay.classList.remove('open');
      document.body.style.overflow = '';
    }
  });
});

/* ── Confirm dialog helper ────────────────────────────────── */
function confirmAction(message) {
  return window.confirm(message);
}

/* ── Format helpers ───────────────────────────────────────── */
function formatCurrency(amount) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2
  }).format(amount);
}

function formatDate(date) {
  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric', month: 'short', day: 'numeric'
  }).format(new Date(date));
}

function formatDateLong(date) {
  return new Intl.DateTimeFormat('en-US', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
  }).format(new Date(date));
}

/* ── Simple table search ──────────────────────────────────── */
function setupTableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;

  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(q) ? '' : 'none';
    });
  });
}

/* ── Page-specific init dispatcher ───────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  const page = location.pathname.split('/').pop();
  if (page === 'pos.html' || page === 'sales.html') initPOS?.();
  if (page === 'products.html')  initProducts?.();
  if (page === 'customers.html') initCustomers?.();
  if (page === 'credits.html')   initCredits?.();
  if (page === 'purchases.html') initPurchases?.();
  if (page === 'reports.html')   initReports?.();
});

/* ── Expose globals ───────────────────────────────────────── */
window.showToast      = showToast;
window.openModal      = openModal;
window.closeModal     = closeModal;
window.confirmAction  = confirmAction;
window.formatCurrency = formatCurrency;
window.formatDate     = formatDate;
window.setupTableSearch = setupTableSearch;
