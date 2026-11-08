/* ============================================================
   ALEXANDER SHOP — API HELPER
   ============================================================ */
'use strict';

const BASE = (location.pathname.includes('/pages/') ? '../' : '') + 'api';

const API = {
  get:    (url)       => fetch(BASE + url).then(r => r.json()),
  post:   (url, data) => fetch(BASE + url, { method:'POST',   headers:{'Content-Type':'application/json'}, body: JSON.stringify(data) }).then(r => r.json()),
  put:    (url, data) => fetch(BASE + url, { method:'PUT',    headers:{'Content-Type':'application/json'}, body: JSON.stringify(data) }).then(r => r.json()),
  delete: (url)       => fetch(BASE + url, { method:'DELETE' }).then(r => r.json()),
};

/* ── Currency formatter ───────────────────────────────────── */
function fmt(n) {
  return '$' + parseFloat(n || 0).toFixed(2);
}

/* ── Date formatter ───────────────────────────────────────── */
function fmtDate(str) {
  if (!str) return '—';
  return new Date(str).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
}

/* ── Badge HTML ───────────────────────────────────────────── */
function badge(text, type) {
  const map = { active:'success', inactive:'secondary', paid:'success', unpaid:'danger',
                partial:'warning', received:'success', pending:'warning', cancelled:'secondary',
                completed:'success', voided:'danger', low:'warning', out:'danger' };
  const cls = map[type] || map[text?.toLowerCase()] || 'secondary';
  return `<span class="badge badge-${cls}"><span class="badge-dot"></span>${text}</span>`;
}

/* ── Stock badge ──────────────────────────────────────────── */
function stockBadge(qty, alert) {
  if (qty === 0)        return badge('Out of Stock', 'out');
  if (qty <= alert)     return badge('Low Stock', 'low');
  return badge('Active', 'active');
}

window.API     = API;
window.fmt     = fmt;
window.fmtDate = fmtDate;
window.badge   = badge;
window.stockBadge = stockBadge;
