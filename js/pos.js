'use strict';

/* ── Config ──────────────────────────────────────────────── */
const API_BASE = '../api';

/* ── State ───────────────────────────────────────────────── */
let cart         = [];
let allProducts  = [];   // cached from DB
let currentCat   = '';
let debounceTimer = null;
let lastReceiptData = null;

/* ── DOM refs ────────────────────────────────────────────── */
const productGrid      = document.getElementById('product-grid');
const cartItemsEl      = document.getElementById('cart-items');
const cartEmptyEl      = document.getElementById('cart-empty');
const cartSubtotalEl   = document.getElementById('cart-subtotal');
const cartTotalEl      = document.getElementById('cart-total');
const cashTenderedEl   = document.getElementById('cash-tendered');
const changeAmountEl   = document.getElementById('change-amount');
const cashTenderRow    = document.getElementById('cash-tender-row');
const creditPartialRow = document.getElementById('credit-partial-row');
const creditDownEl     = document.getElementById('credit-downpayment');
const creditBalanceEl  = document.getElementById('credit-balance');
const productSearch    = document.getElementById('product-search');
const categoryTabs     = document.getElementById('category-tabs');
const searchSpinner    = document.getElementById('search-spinner');
const customerSelect   = document.getElementById('pos-customer');
const invoiceLabel     = document.getElementById('invoice-label');

/* ── Init ────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('pos-date').textContent =
    new Intl.DateTimeFormat('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' }).format(new Date());

  loadCustomers();
  loadProducts('', '');
  generateInvoicePreview();
});

/* ── Generate invoice preview ────────────────────────────── */
function generateInvoicePreview() {
  const n = Math.floor(Math.random() * 9000) + 1000;
  invoiceLabel.textContent = 'INV-' + String(n).padStart(6, '0');
}

/* ── Load customers ──────────────────────────────────────── */
async function loadCustomers() {
  try {
    const res  = await fetch(`${API_BASE}/customers/list.php`);
    const data = await res.json();
    if (!Array.isArray(data)) return;
    data.forEach(c => {
      const opt = document.createElement('option');
      opt.value       = c.id;
      opt.textContent = c.name + (c.phone ? ' — ' + c.phone : '');
      customerSelect.appendChild(opt);
    });
  } catch (e) {
    console.warn('Could not load customers:', e);
  }
}

/* ── Load / search products from DB ─────────────────────── */
async function loadProducts(query, category) {
  searchSpinner.style.display = 'block';
  productGrid.style.opacity   = '0.5';

  try {
    const params = new URLSearchParams();
    if (query)    params.set('q', query);
    if (category) params.set('cat', category);

    const res  = await fetch(`${API_BASE}/products/search.php?${params}`);
    const data = await res.json();

    if (!Array.isArray(data)) {
      renderProductError('Could not load products. Is the database set up?');
      return;
    }

    allProducts = data;

    // Filter by category client-side if needed
    let filtered = data;
    if (category) {
      filtered = data.filter(p => p.category && p.category.toLowerCase() === category.toLowerCase());
    }

    renderProducts(filtered);
  } catch (e) {
    renderProductError('Cannot connect to server. Make sure XAMPP is running and the database is set up.');
  } finally {
    searchSpinner.style.display = 'none';
    productGrid.style.opacity   = '1';
  }
}

/* ── Render product cards ────────────────────────────────── */
function renderProducts(products) {
  productGrid.innerHTML = '';

  if (!products || products.length === 0) {
    productGrid.innerHTML = `
      <div class="empty-state" style="grid-column:1/-1">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" width="48" height="48"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <h4>No products found</h4>
        <p>Try a different search term or category</p>
      </div>`;
    return;
  }

  products.forEach(p => {
    const outOfStock = p.stock_quantity <= 0;
    const lowStock   = p.stock_quantity > 0 && p.stock_quantity <= 5;

    let stockLabel, stockClass;
    if (outOfStock)    { stockLabel = 'Out of Stock'; stockClass = 'stock-out'; }
    else if (lowStock) { stockLabel = `${p.stock_quantity} left`;  stockClass = 'stock-low'; }
    else               { stockLabel = `${p.stock_quantity} in stock`; stockClass = 'stock-ok'; }

    const card = document.createElement('div');
    card.className = `product-card${outOfStock ? ' out-of-stock' : ''}`;
    card.dataset.id = p.id;
    card.innerHTML = `
      <div class="product-card-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
      </div>
      <div class="product-card-name">${escHtml(p.name)}</div>
      <div class="product-card-sku">${escHtml(p.sku || p.barcode || '')}</div>
      <div class="product-card-price">$${parseFloat(p.sell_price).toFixed(2)}</div>
      <div class="product-card-stock ${stockClass}">${stockLabel}</div>`;

    if (!outOfStock) {
      card.addEventListener('click', () => addToCart(p));
    }
    productGrid.appendChild(card);
  });
}

function renderProductError(msg) {
  productGrid.innerHTML = `
    <div class="empty-state" style="grid-column:1/-1">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="var(--danger)" stroke-width="1.5" width="48" height="48"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <h4 style="color:var(--danger)">Connection Error</h4>
      <p style="max-width:280px;text-align:center;">${msg}</p>
      <a href="/ALEXANDER/db/setup.php" target="_blank" class="btn btn-primary btn-sm" style="margin-top:12px;">Run Database Setup</a>
    </div>`;
}

/* ── Cart: Add ───────────────────────────────────────────── */
function addToCart(product) {
  const existing = cart.find(i => i.id === product.id);

  if (existing) {
    if (existing.qty >= product.stock_quantity) {
      showToast(`Only ${product.stock_quantity} units of "${product.name}" available.`, 'warning');
      return;
    }
    existing.qty++;
  } else {
    cart.push({
      id:    product.id,
      name:  product.name,
      price: parseFloat(product.sell_price),
      stock: product.stock_quantity,
      qty:   1,
    });
  }

  renderCart();
  showToast(`${product.name} added`, 'success', 1400);
}

/* ── Cart: Remove ────────────────────────────────────────── */
function removeFromCart(id) {
  cart = cart.filter(i => i.id !== id);
  renderCart();
}

/* ── Cart: Update qty ────────────────────────────────────── */
function updateQty(id, delta) {
  const item = cart.find(i => i.id === id);
  if (!item) return;

  const newQty = item.qty + delta;
  if (newQty <= 0) return; // use Remove button to delete

  if (newQty > item.stock) {
    showToast(`Only ${item.stock} units of "${item.name}" are available.`, 'warning');
    return;
  }

  item.qty = newQty;
  renderCart();
}

/* ── Cart: Render ────────────────────────────────────────── */
function renderCart() {
  // Remove existing item rows
  cartItemsEl.querySelectorAll('.cart-item').forEach(r => r.remove());

  if (cart.length === 0) {
    cartEmptyEl.style.display = 'flex';
    updateTotals();
    return;
  }

  cartEmptyEl.style.display = 'none';

  cart.forEach(item => {
    const sub = item.price * item.qty;
    const row = document.createElement('div');
    row.className = 'cart-item';
    row.innerHTML = `
      <div>
        <div class="cart-item-top">
          <div class="cart-item-name">${escHtml(item.name)}</div>
          <button class="cart-item-remove" data-id="${item.id}" aria-label="Remove">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="cart-item-controls">
          <div class="qty-control">
            <button class="qty-btn" data-id="${item.id}" data-delta="-1">−</button>
            <span class="qty-val">${item.qty}</span>
            <button class="qty-btn" data-id="${item.id}" data-delta="1">+</button>
          </div>
          <span class="cart-item-price">$${item.price.toFixed(2)} × ${item.qty}</span>
          <span class="cart-item-subtotal">$${sub.toFixed(2)}</span>
        </div>
      </div>`;
    cartItemsEl.appendChild(row);
  });

  cartItemsEl.querySelectorAll('.cart-item-remove').forEach(btn => {
    btn.addEventListener('click', () => removeFromCart(+btn.dataset.id));
  });
  cartItemsEl.querySelectorAll('.qty-btn').forEach(btn => {
    btn.addEventListener('click', () => updateQty(+btn.dataset.id, +btn.dataset.delta));
  });

  updateTotals();
}

/* ── Totals ──────────────────────────────────────────────── */
function updateTotals() {
  const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
  const total    = subtotal;

  cartSubtotalEl.textContent = '$' + subtotal.toFixed(2);
  cartTotalEl.textContent    = '$' + total.toFixed(2);

  updatePaymentCalc();
}

function updatePaymentCalc() {
  const total    = parseFloat(cartTotalEl.textContent.replace('$', '')) || 0;
  const method   = document.querySelector('input[name="payment"]:checked')?.value || 'cash';

  if (method === 'cash') {
    const tendered = parseFloat(cashTenderedEl.value) || 0;
    const change   = tendered - total;
    changeAmountEl.textContent = change >= 0 ? '$' + change.toFixed(2) : '-';
    changeAmountEl.style.color = change >= 0 ? 'var(--success)' : 'var(--danger)';
  }

  if (method === 'credit') {
    const down    = parseFloat(creditDownEl.value) || 0;
    const balance = Math.max(0, total - down);
    creditBalanceEl.textContent = '$' + balance.toFixed(2);
  }
}

/* ── Payment method toggle ───────────────────────────────── */
document.querySelectorAll('input[name="payment"]').forEach(radio => {
  radio.addEventListener('change', () => {
    const v = radio.value;
    cashTenderRow.style.display    = v === 'cash'   ? 'flex' : 'none';
    creditPartialRow.style.display = v === 'credit' ? 'flex' : 'none';

    // Credit requires a customer
    if (v === 'credit') {
      customerSelect.style.borderColor = '';
    }
    updatePaymentCalc();
  });
});

cashTenderedEl.addEventListener('input', updatePaymentCalc);
creditDownEl.addEventListener('input', updatePaymentCalc);

/* ── Complete Sale ───────────────────────────────────────── */
document.getElementById('complete-sale-btn').addEventListener('click', async () => {
  if (cart.length === 0) {
    showToast('Add at least one product to the cart', 'warning');
    return;
  }

  const method      = document.querySelector('input[name="payment"]:checked')?.value || 'cash';
  const customer_id = customerSelect.value ? parseInt(customerSelect.value) : null;
  const total       = parseFloat(cartTotalEl.textContent.replace('$', '')) || 0;

  let amount_paid = 0;

  if (method === 'cash') {
    amount_paid = parseFloat(cashTenderedEl.value) || 0;
    if (amount_paid < total) {
      showToast('Amount paid is less than the total', 'warning');
      cashTenderedEl.focus();
      return;
    }
  } else if (method === 'credit') {
    if (!customer_id) {
      showToast('Please select a registered customer for credit sales', 'warning');
      customerSelect.style.borderColor = 'var(--danger)';
      customerSelect.focus();
      return;
    }
    amount_paid = parseFloat(creditDownEl.value) || 0;
  } else {
    amount_paid = total;
  }

  const btn = document.getElementById('complete-sale-btn');
  btn.disabled = true;
  btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="spin"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Processing…`;

  const payload = {
    items:          cart.map(i => ({ id: i.id, qty: i.qty })),
    customer_id:    customer_id,
    payment_method: method,
    amount_paid:    amount_paid,
    notes:          '',
  };

  try {
    const res  = await fetch(`${API_BASE}/sales/complete.php`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
    });
    const data = await res.json();

    if (data.success) {
      lastReceiptData = data;
      showReceipt(data);
    } else {
      showToast(data.error || 'Sale failed. Please try again.', 'danger');
    }
  } catch (e) {
    showToast('Network error. Check that XAMPP is running.', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Complete Sale`;
  }
});

/* ── Show Receipt ────────────────────────────────────────── */
function showReceipt(data) {
  const methodLabels = { cash:'Cash', card:'Card', mobile_money:'Mobile Money', credit:'Credit' };
  const mLabel = methodLabels[data.payment_method] || data.payment_method;

  let itemsHtml = data.items.map(i => `
    <tr>
      <td style="padding:6px 0;font-size:.82rem;">${escHtml(i.name)}</td>
      <td style="padding:6px 0;font-size:.82rem;text-align:center;">${i.qty}</td>
      <td style="padding:6px 0;font-size:.82rem;text-align:right;">$${i.price.toFixed(2)}</td>
      <td style="padding:6px 0;font-size:.82rem;text-align:right;font-weight:700;">$${i.subtotal.toFixed(2)}</td>
    </tr>`).join('');

  let paymentRows = `
    <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:.82rem;">
      <span style="color:var(--text-muted);">Amount Paid</span>
      <span style="font-weight:600;">$${parseFloat(data.amount_paid).toFixed(2)}</span>
    </div>`;

  if (data.payment_method === 'cash' && data.change_amount > 0) {
    paymentRows += `
      <div style="display:flex;justify-content:space-between;font-size:.82rem;">
        <span style="color:var(--text-muted);">Change</span>
        <span style="font-weight:700;color:var(--success);">$${parseFloat(data.change_amount).toFixed(2)}</span>
      </div>`;
  }
  if (data.payment_method === 'credit' && data.credit_amount > 0) {
    paymentRows += `
      <div style="display:flex;justify-content:space-between;font-size:.82rem;">
        <span style="color:var(--text-muted);">Credit Balance</span>
        <span style="font-weight:700;color:var(--warning);">$${parseFloat(data.credit_amount).toFixed(2)}</span>
      </div>`;
  }

  document.getElementById('receipt-body').innerHTML = `
    <div style="text-align:center;margin-bottom:20px;">
      <div style="width:60px;height:60px;background:var(--success-light);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="var(--success)" stroke-width="2.5" width="28" height="28"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h4 style="font-size:1.05rem;color:var(--success);margin-bottom:4px;">Sale Completed Successfully!</h4>
      <p style="font-size:.8rem;color:var(--text-muted);">${data.date}</p>
    </div>

    <div style="background:var(--bg);border-radius:8px;padding:14px;margin-bottom:14px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
        <span style="font-size:.78rem;color:var(--text-muted);">Invoice</span>
        <span style="font-size:.82rem;font-weight:800;color:var(--primary);">${escHtml(data.invoice_number)}</span>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
        <span style="font-size:.78rem;color:var(--text-muted);">Customer</span>
        <span style="font-size:.82rem;font-weight:600;">${escHtml(data.customer)}</span>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
        <span style="font-size:.78rem;color:var(--text-muted);">Payment</span>
        <span style="font-size:.82rem;font-weight:600;">${mLabel}</span>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span style="font-size:.78rem;color:var(--text-muted);">Cashier</span>
        <span style="font-size:.82rem;font-weight:600;">${escHtml(data.cashier)}</span>
      </div>
    </div>

    <table style="width:100%;border-collapse:collapse;margin-bottom:14px;">
      <thead>
        <tr style="border-bottom:2px solid var(--border);">
          <th style="padding:6px 0;font-size:.72rem;color:var(--text-muted);text-align:left;font-weight:600;text-transform:uppercase;">Item</th>
          <th style="padding:6px 0;font-size:.72rem;color:var(--text-muted);text-align:center;font-weight:600;text-transform:uppercase;">Qty</th>
          <th style="padding:6px 0;font-size:.72rem;color:var(--text-muted);text-align:right;font-weight:600;text-transform:uppercase;">Price</th>
          <th style="padding:6px 0;font-size:.72rem;color:var(--text-muted);text-align:right;font-weight:600;text-transform:uppercase;">Total</th>
        </tr>
      </thead>
      <tbody>${itemsHtml}</tbody>
    </table>

    <div style="border-top:2px solid var(--border);padding-top:12px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
        <span style="font-size:.9rem;font-weight:800;">TOTAL</span>
        <span style="font-size:1.1rem;font-weight:800;color:var(--primary);">$${parseFloat(data.total).toFixed(2)}</span>
      </div>
      ${paymentRows}
    </div>`;

  openModal('receipt-modal');
}

/* ── Print Receipt ───────────────────────────────────────── */
function printReceipt() {
  const body = document.getElementById('receipt-body').innerHTML;
  const win  = window.open('', '_blank', 'width=400,height=600');
  win.document.write(`<!DOCTYPE html><html><head><title>Receipt</title>
    <style>body{font-family:sans-serif;padding:20px;max-width:380px;margin:0 auto;}
    table{width:100%;border-collapse:collapse;}
    @media print{button{display:none;}}</style></head>
    <body>${body}<br><button onclick="window.print()">Print</button></body></html>`);
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 400);
}

/* ── Reset POS after sale ────────────────────────────────── */
function resetPOS() {
  cart = [];
  renderCart();
  cashTenderedEl.value  = '';
  creditDownEl.value    = '';
  customerSelect.value  = '';
  customerSelect.style.borderColor = '';
  document.querySelector('input[name="payment"][value="cash"]').checked = true;
  cashTenderRow.style.display    = 'flex';
  creditPartialRow.style.display = 'none';
  generateInvoicePreview();
  loadProducts('', currentCat);
}

/* ── Clear Cart ──────────────────────────────────────────── */
document.getElementById('clear-cart-btn').addEventListener('click', () => {
  if (cart.length === 0) return;
  openModal('clear-modal');
});

document.getElementById('confirm-clear-btn').addEventListener('click', () => {
  cart = [];
  renderCart();
  closeModal('clear-modal');
  showToast('Order cleared', 'default');
});

/* ── Hold Order ──────────────────────────────────────────── */
document.getElementById('hold-btn').addEventListener('click', () => {
  if (cart.length === 0) { showToast('No items to hold', 'warning'); return; }
  showToast('Order held. You can resume later.', 'default');
  cart = [];
  renderCart();
});

/* ── Live Search with debounce ───────────────────────────── */
productSearch.addEventListener('input', () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    loadProducts(productSearch.value.trim(), currentCat);
  }, 280);
});

/* ── Barcode scanner support ─────────────────────────────── */
// Barcode scanners type fast and end with Enter
let barcodeBuffer = '';
let barcodeTimer  = null;

productSearch.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    const q = productSearch.value.trim();
    if (!q) return;

    // Try exact barcode/SKU match first
    const exact = allProducts.find(p =>
      (p.barcode && p.barcode.toLowerCase() === q.toLowerCase()) ||
      (p.sku     && p.sku.toLowerCase()     === q.toLowerCase())
    );

    if (exact) {
      if (exact.stock_quantity > 0) {
        addToCart(exact);
        productSearch.value = '';
        loadProducts('', currentCat);
      } else {
        showToast(`"${exact.name}" is out of stock`, 'warning');
      }
    } else {
      // Just search
      loadProducts(q, currentCat);
    }
  }
});

/* ── Category tabs ───────────────────────────────────────── */
categoryTabs.addEventListener('click', (e) => {
  const tab = e.target.closest('.cat-tab');
  if (!tab) return;
  categoryTabs.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
  tab.classList.add('active');
  currentCat = tab.dataset.cat;
  loadProducts(productSearch.value.trim(), currentCat);
});

/* ── Utility ─────────────────────────────────────────────── */
function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function initPOS() {}
