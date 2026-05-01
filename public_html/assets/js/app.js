// ─── State ────────────────────────────────────────────────
let allBooks = [];
let settings = {};
let selectedIds = new Set();
let selectedVariants = new Map(); // productId → Set<variant>
let paymentMethod = 'bkash';
const bkashMode   = window.BKASH_MODE   || 'manual';
const pixelId     = window.PIXEL_ID     || '';
const countryCode = window.COUNTRY_CODE || '+880';

function validatePhone(num) {
  if (countryCode === '+880' || countryCode === '+88') {
    return /^01[3-9]\d{8}$/.test(num);
  }
  return /^\d{6,14}$/.test(num);
}

// ─── Meta Pixel helper ────────────────────────────────────
function fbqTrack(event, data = {}) {
  if (!pixelId || typeof fbq !== 'function') return;
  fbq('track', event, data);
}

// ─── Init ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadData();
  document.getElementById('orderForm').addEventListener('submit', handleSubmit);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closePdf(); });

  const p = new URLSearchParams(location.search);
  const bkashResult = p.get('bkash');
  if (bkashResult === 'success') {
    fbqTrack('Purchase', { value: 0, currency: 'BDT', order_id: p.get('order') || '' });
    document.getElementById('successMsg').textContent =
      `Order #${p.get('order')} payment successful. TXN: ${p.get('trx')}`;
    document.getElementById('successModal').classList.add('show');
    history.replaceState(null, '', location.pathname);
  } else if (bkashResult === 'cancelled') {
    showToast('bKash payment cancelled');
  } else if (bkashResult === 'failed') {
    showToast('bKash payment failed. Please try again');
  }
});

async function loadData() {
  try {
    const [booksRes, settingsRes] = await Promise.all([
      fetch('api/get-books.php'),
      fetch('api/get-settings.php')
    ]);
    const booksData    = await booksRes.json();
    const settingsData = await settingsRes.json();

    if (booksData.success) {
      allBooks = booksData.books.map(b => ({
        ...b,
        variants: Array.isArray(b.variants) ? b.variants
          : (b.variants ? (JSON.parse(b.variants) || []) : [])
      }));
      renderProducts();
    }
    if (settingsData.success) {
      settings = settingsData.settings;

      const numEl = document.getElementById('bkashNumber');
      if (numEl) numEl.textContent = settings.bkash_number || '—';

      renderBkashBox();

      const dChg = parseFloat(settings.dhaka_charge   || 80);
      const oChg = parseFloat(settings.outside_charge || 140);
      const optD = document.getElementById('optDhaka');
      const optO = document.getElementById('optOutside');
      if (optD) optD.textContent = `Dhaka — Delivery ৳${dChg}`;
      if (optO) optO.textContent = `Outside Dhaka — Delivery ৳${oChg}`;

      const hint = document.getElementById('deliveryHint');
      if (hint) hint.textContent = `Dhaka ৳${dChg} · Outside Dhaka ৳${oChg}`;
    }
  } catch (e) {
    console.error('Load error:', e);
  }
}

// ─── Render Product Cards ─────────────────────────────────
function renderProducts() {
  const grid = document.getElementById('productsGrid');
  grid.innerHTML = allBooks.map(book => {
    let imgSrc = '';
    if (book.image) {
      imgSrc = book.image.startsWith('http') ? book.image : 'assets/images/books/' + book.image;
    }

    const imgContent = imgSrc
      ? `<img src="${escHtml(imgSrc)}" alt="${escHtml(book.name)}" loading="lazy"
             onerror="this.style.display='none';this.nextSibling.style.display='flex'">
         <div class="card-img-placeholder" style="display:none"><i class="fas fa-box-open"></i><span>Product</span></div>`
      : `<div class="card-img-placeholder"><i class="fas fa-box-open"></i><span>Product</span></div>`;

    const pdfSrc = book.sample_pdf ? 'assets/pdfs/' + book.sample_pdf : '';
    const pdfBtns = pdfSrc
      ? `<div class="pdf-btns" onclick="event.stopPropagation()">
           <button class="sample-btn" onclick="openPdf(event,${book.id})">
             <i class="fas fa-eye"></i> Sample
           </button>
           <a class="sample-btn dl-btn" href="${escHtml(pdfSrc)}" download="${escHtml(book.name)}_sample.pdf">
             <i class="fas fa-download"></i>
           </a>
         </div>`
      : '';

    const brandLine = book.author
      ? `<div class="card-brand">${escHtml(book.author)}</div>`
      : '';

    const variants = book.variants || [];
    const variantHtml = variants.length > 0
      ? `<div class="card-variants" id="variants-${book.id}" onclick="event.stopPropagation()">
           ${variants.map(v =>
             `<button class="variant-chip" data-v="${escHtml(v)}" onclick="selectVariant(${book.id},this.dataset.v,event)">${escHtml(v)}</button>`
           ).join('')}
         </div>`
      : '';

    const defaultHint = variants.length > 0 ? 'Choose size' : 'Select';

    return `
      <div class="product-card" id="card-${book.id}" onclick="toggleProduct(${book.id})">
        <div class="card-img-wrap">
          ${imgContent}
          <div class="card-check-badge"><i class="fas fa-check"></i></div>
        </div>
        <div class="card-body">
          ${brandLine}
          <div class="card-name">${escHtml(book.name)}</div>
          ${book.description ? `<div class="card-desc">${escHtml(book.description)}</div>` : ''}
          <div class="card-footer">
            <div class="card-price">৳${Number(book.price).toLocaleString('en')}</div>
            ${pdfBtns}
          </div>
          ${variantHtml}
        </div>
        <div class="card-tap-bar" id="hint-${book.id}">${defaultHint}</div>
      </div>`;
  }).join('');
}

// ─── Variant selection ────────────────────────────────────
function selectVariant(id, variant, event) {
  event.stopPropagation();
  const book = allBooks.find(b => b.id === id);
  if (!book) return;

  let varSet = selectedVariants.get(id);
  if (!varSet) { varSet = new Set(); }

  if (varSet.has(variant)) {
    varSet.delete(variant);
    if (varSet.size === 0) {
      selectedVariants.delete(id);
      selectedIds.delete(id);
    } else {
      selectedVariants.set(id, varSet);
    }
  } else {
    const isNew = !selectedIds.has(id);
    varSet.add(variant);
    selectedVariants.set(id, varSet);
    selectedIds.add(id);
    if (isNew) {
      fbqTrack('ViewContent', { content_ids: [String(id)], content_name: book.name, content_type: 'product', value: parseFloat(book.price), currency: 'BDT' });
      fbqTrack('AddToCart',   { content_ids: [String(id)], content_name: book.name, content_type: 'product', value: parseFloat(book.price), currency: 'BDT' });
    }
  }
  updateSelectionUI();
}

// ─── PDF Modal ────────────────────────────────────────────
function openPdf(event, bookId) {
  event.stopPropagation();
  const book = allBooks.find(b => b.id == bookId);
  if (!book || !book.sample_pdf) return;
  const pdfUrl = 'assets/pdfs/' + book.sample_pdf;
  document.getElementById('pdfTitle').textContent       = book.name;
  document.getElementById('pdfMobileTitle').textContent = book.name;
  document.getElementById('pdfIframe').src              = pdfUrl;
  document.getElementById('pdfDownloadBtn').href        = pdfUrl;
  document.getElementById('pdfDownloadBtn').download    = book.name + '_sample.pdf';
  document.getElementById('pdfMobileDl').href           = pdfUrl;
  document.getElementById('pdfMobileDl').download       = book.name + '_sample.pdf';
  document.getElementById('pdfOverlay').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closePdf() {
  document.getElementById('pdfOverlay').classList.remove('show');
  document.body.style.overflow = '';
  setTimeout(() => { document.getElementById('pdfIframe').src = ''; }, 300);
}

// ─── Product Selection ────────────────────────────────────
function toggleProduct(id) {
  const book = allBooks.find(b => b.id === id);
  if (!book) return;

  if (book.variants && book.variants.length > 0) {
    if (selectedIds.has(id)) {
      selectedIds.delete(id);
      selectedVariants.delete(id);
      updateSelectionUI();
    } else {
      showToast('Please choose a size above');
    }
    return;
  }

  if (selectedIds.has(id)) {
    selectedIds.delete(id);
  } else {
    selectedIds.add(id);
    fbqTrack('ViewContent', { content_ids: [String(id)], content_name: book.name, content_type: 'product', value: parseFloat(book.price), currency: 'BDT' });
    fbqTrack('AddToCart',   { content_ids: [String(id)], content_name: book.name, content_type: 'product', value: parseFloat(book.price), currency: 'BDT' });
  }
  updateSelectionUI();
}

function removeProduct(id, variant) {
  if (variant) {
    const varSet = selectedVariants.get(id);
    if (varSet) {
      varSet.delete(variant);
      if (varSet.size === 0) { selectedVariants.delete(id); selectedIds.delete(id); }
    }
  } else {
    selectedIds.delete(id);
    selectedVariants.delete(id);
  }
  updateSelectionUI();
  if (selectedIds.size === 0) closeOrderForm();
}

function updateSelectionUI() {
  allBooks.forEach(book => {
    const card        = document.getElementById(`card-${book.id}`);
    const hint        = document.getElementById(`hint-${book.id}`);
    const variantsDiv = document.getElementById(`variants-${book.id}`);
    if (!card) return;

    const isSelected  = selectedIds.has(book.id);
    const varSet      = selectedVariants.get(book.id) || new Set();
    const hasVariants = book.variants && book.variants.length > 0;

    card.classList.toggle('selected', isSelected);

    if (variantsDiv && hasVariants) {
      variantsDiv.innerHTML = book.variants.map(v =>
        `<button class="variant-chip${varSet.has(v) ? ' active' : ''}" data-v="${escHtml(v)}" onclick="selectVariant(${book.id},this.dataset.v,event)">${escHtml(v)}</button>`
      ).join('');
    }

    if (hint) {
      if (isSelected) {
        const varList = [...varSet].join(', ');
        hint.textContent = varList ? `✓ ${varList} — Added` : '✓ Added';
      } else {
        hint.textContent = hasVariants ? 'Choose size' : 'Select';
      }
    }
  });

  const total = getSelectedTotal();
  const count = [...selectedIds].reduce((n, id) => {
    const vs = selectedVariants.get(id);
    return n + (vs && vs.size > 0 ? vs.size : 1);
  }, 0);

  const badge = document.getElementById('headerBadge');
  if (badge) {
    badge.textContent = `${count} selected`;
    badge.classList.toggle('visible', count > 0);
  }

  const bar = document.getElementById('orderBar');
  document.getElementById('barCount').textContent = `${count} item(s) selected`;
  document.getElementById('barTotal').textContent = `৳${total.toLocaleString('en')}`;
  bar.classList.toggle('visible', count > 0);

  if (document.getElementById('orderSection').classList.contains('open')) {
    updateOrderSummary();
  }
}

function getSelectedTotal() {
  return allBooks
    .filter(b => selectedIds.has(b.id))
    .reduce((sum, b) => {
      const vs = selectedVariants.get(b.id);
      const qty = (vs && vs.size > 0) ? vs.size : 1;
      return sum + parseFloat(b.price) * qty;
    }, 0);
}

// ─── Order Form ───────────────────────────────────────────
function openOrderForm() {
  if (selectedIds.size === 0) { showToast('Select at least one item'); return; }
  document.getElementById('productsSection').style.display = 'none';
  document.getElementById('orderSection').classList.add('open');
  document.getElementById('orderBar').classList.remove('visible');
  updateOrderSummary();
  window.scrollTo({ top: 0, behavior: 'smooth' });
  fbqTrack('InitiateCheckout', {
    content_ids: [...selectedIds].map(String),
    num_items:   selectedIds.size,
    value:       getSelectedTotal(),
    currency:    'BDT',
  });
}

function closeOrderForm() {
  document.getElementById('productsSection').style.display = 'block';
  document.getElementById('orderSection').classList.remove('open');
  if (selectedIds.size > 0) document.getElementById('orderBar').classList.add('visible');
}

function updateOrderSummary() {
  const selected = allBooks.filter(b => selectedIds.has(b.id));
  let bookTotal = 0;
  const lines = [];
  selected.forEach(b => {
    const vs = selectedVariants.get(b.id);
    if (vs && vs.size > 0) {
      [...vs].forEach(v => { lines.push({ book: b, variant: v }); bookTotal += parseFloat(b.price); });
    } else {
      lines.push({ book: b, variant: '' });
      bookTotal += parseFloat(b.price);
    }
  });

  document.getElementById('orderItemsList').innerHTML = lines.map(({ book: b, variant }) => {
    const imgSrc = b.image
      ? (b.image.startsWith('http') ? b.image : 'assets/images/books/' + b.image)
      : '';
    const thumb = imgSrc
      ? `<img src="${escHtml(imgSrc)}" style="width:44px;height:44px;object-fit:cover;border-radius:8px;flex-shrink:0" onerror="this.style.display='none'">`
      : `<div style="width:44px;height:44px;background:var(--accent-light);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-box-open" style="color:var(--accent);font-size:.85rem"></i></div>`;
    const variantTag = variant
      ? `<span style="font-size:.65rem;font-weight:700;background:var(--accent);color:#fff;padding:1px 7px;border-radius:20px;margin-left:5px;flex-shrink:0">${escHtml(variant)}</span>`
      : '';
    const removeBtn = variant
      ? `<button class="order-item-remove" data-bid="${b.id}" data-v="${escHtml(variant)}" onclick="removeProduct(+this.dataset.bid,this.dataset.v)" title="Remove">✕</button>`
      : `<button class="order-item-remove" onclick="removeProduct(${b.id})" title="Remove">✕</button>`;
    return `<div class="order-item" style="gap:10px">
      ${thumb}
      <span class="order-item-name" style="flex:1;min-width:0;display:flex;align-items:center;flex-wrap:wrap;gap:2px">
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(b.name)}</span>${variantTag}
      </span>
      <span style="display:flex;align-items:center;gap:4px;flex-shrink:0">
        <span class="order-item-price">৳${parseFloat(b.price).toLocaleString('en')}</span>
        ${removeBtn}
      </span>
    </div>`;
  }).join('');

  document.getElementById('summaryBookPrice').textContent = `৳${bookTotal.toLocaleString('en')}`;
  updateTotals();
}

function updateTotals() {
  const area      = document.getElementById('farea').value;
  const bookTotal = allBooks.filter(b => selectedIds.has(b.id)).reduce((s, b) => {
    const vs = selectedVariants.get(b.id);
    const qty = (vs && vs.size > 0) ? vs.size : 1;
    return s + parseFloat(b.price) * qty;
  }, 0);

  let delivery = 0;
  if (area === 'dhaka')   delivery = parseFloat(settings.dhaka_charge   || 80);
  if (area === 'outside') delivery = parseFloat(settings.outside_charge || 140);
  if (isNaN(delivery)) delivery = area === 'dhaka' ? 80 : 140;

  const deliveryRow = document.getElementById('summaryDelivery');
  if (deliveryRow) {
    deliveryRow.textContent = area ? `৳${delivery.toLocaleString('en')}` : '৳—';
    deliveryRow.closest('.order-total-row')?.classList.toggle('delivery-set', !!area);
  }
  const totalEl = document.getElementById('summaryTotal');
  if (totalEl) totalEl.textContent = area ? `৳${(bookTotal + delivery).toLocaleString('en')}` : '—';

  const display = document.getElementById('deliveryCostDisplay');
  if (display) display.textContent = area ? `৳${delivery.toLocaleString('en')}` : '৳—';
}

// ─── Payment Toggle ───────────────────────────────────────
function setPayment(method) {
  paymentMethod = method;
  document.getElementById('btnCod').classList.toggle('active', method === 'cod');
  document.getElementById('btnBkash').classList.toggle('active', method === 'bkash');

  const manualBox = document.getElementById('bkashManualBox');
  const apiBox    = document.getElementById('bkashApiBox');
  const txnField  = document.getElementById('txnField');

  if (method === 'bkash') {
    if (bkashMode === 'api') {
      if (manualBox) manualBox.style.display = 'none';
      if (apiBox)    apiBox.style.display    = 'block';
      if (txnField)  txnField.classList.remove('show');
    } else {
      if (manualBox) manualBox.style.display = 'block';
      if (apiBox)    apiBox.style.display    = 'none';
      if (txnField)  txnField.classList.add('show');
    }
  } else {
    if (manualBox) manualBox.style.display = 'none';
    if (apiBox)    apiBox.style.display    = 'none';
    if (txnField)  txnField.classList.remove('show');
  }
}

function renderBkashBox() {
  const manualBox = document.getElementById('bkashManualBox');
  const apiBox    = document.getElementById('bkashApiBox');
  const txnField  = document.getElementById('txnField');

  if (bkashMode === 'api') {
    if (manualBox) manualBox.style.display = 'none';
    if (apiBox)    apiBox.style.display    = 'block';
    if (txnField)  txnField.classList.remove('show');
  } else {
    const qrBlock = document.getElementById('bkashQrBlock');
    if (qrBlock && settings.bkash_qr_url && !qrBlock.innerHTML.trim()) {
      qrBlock.innerHTML = `
        <div class="bkash-qr-wrap">
          <img src="${escHtml(settings.bkash_qr_url)}" alt="bKash QR" class="bkash-qr-img">
          <div class="bkash-qr-info">
            <div class="bkash-label">Scan bKash QR or send to number</div>
            <div class="bkash-number" style="margin-top:4px">${escHtml(settings.bkash_number||'')}</div>
            <button type="button" class="copy-btn" onclick="copyBkash()"
                    style="margin-top:8px;display:inline-flex;align-items:center;gap:6px">
              <i class="fas fa-copy"></i> Copy
            </button>
          </div>
        </div>`;
    }
    if (manualBox) manualBox.style.display = '';
    if (apiBox)    apiBox.style.display    = 'none';
    if (txnField)  txnField.classList.add('show');
  }
}

function copyBkash() {
  const num = settings.bkash_number || '';
  if (!num) return;
  navigator.clipboard.writeText(num)
    .then(() => showToast('bKash number copied!'))
    .catch(() => showToast('Number: ' + num));
}

// ─── bKash API payment initiation ────────────────────────
async function initiateBkashPayment() {
  if (selectedIds.size === 0) { showToast('Select at least one item'); return; }

  const name    = document.getElementById('fname').value.trim();
  const phone   = document.getElementById('fphone').value.trim();
  const address = document.getElementById('faddress').value.trim();
  const area    = document.getElementById('farea').value;

  if (!name || name.length < 2)        { showToast('Please enter a valid name'); return; }
  if (!validatePhone(phone))            { showToast('Please enter a valid phone number'); return; }
  if (!address || address.length < 10) { showToast('Please enter a full address'); return; }
  if (!area)                           { showToast('Please select a delivery area'); return; }

  showLoading(true);

  const booksArr = [];
  allBooks.filter(b => selectedIds.has(b.id)).forEach(b => {
    const vs = selectedVariants.get(b.id);
    if (vs && vs.size > 0) vs.forEach(v => booksArr.push({ id: b.id, variant: v }));
    else booksArr.push({ id: b.id, variant: '' });
  });

  const orderBody = new FormData();
  orderBody.append('name',           name);
  orderBody.append('phone',          countryCode + ' ' + phone);
  orderBody.append('address',        address);
  orderBody.append('area',           area);
  orderBody.append('payment_method', 'bkash');
  orderBody.append('transaction_id', '');
  orderBody.append('books_json',     JSON.stringify(booksArr));

  try {
    const orderRes  = await fetch('api/create-order.php', { method: 'POST', body: orderBody });
    const orderData = await orderRes.json();

    if (!orderData.success) {
      showLoading(false);
      showToast(orderData.message || 'Failed to create order');
      return;
    }

    const delivery = area === 'dhaka'
      ? parseFloat(settings.dhaka_charge   || 80)
      : parseFloat(settings.outside_charge || 140);
    const total = getSelectedTotal() + delivery;

    const bkashBody = new FormData();
    bkashBody.append('amount',   total.toFixed(2));
    bkashBody.append('order_id', orderData.order_id);

    const bkashRes  = await fetch('api/bkash-create.php', { method: 'POST', body: bkashBody });
    const bkashData = await bkashRes.json();

    showLoading(false);

    if (bkashData.success && bkashData.bkashURL) {
      window.location.href = bkashData.bkashURL;
    } else {
      showToast(bkashData.message || 'Failed to initiate bKash payment');
    }
  } catch (err) {
    showLoading(false);
    showToast('Network error. Please try again');
  }
}

// ─── Submit Order (manual bKash / COD) ───────────────────
async function handleSubmit(e) {
  e.preventDefault();

  if (bkashMode === 'api' && paymentMethod === 'bkash') return;

  const name    = document.getElementById('fname').value.trim();
  const phone   = document.getElementById('fphone').value.trim();
  const address = document.getElementById('faddress').value.trim();
  const area    = document.getElementById('farea').value;
  const txn     = document.getElementById('ftxn').value.trim();

  if (!name || name.length < 2)                     return showToast('Please enter a valid name');
  if (!validatePhone(phone))                         return showToast('Please enter a valid phone number');
  if (!address || address.length < 10)              return showToast('Please enter a full address');
  if (!area)                                        return showToast('Please select a delivery area');
  if (paymentMethod === 'bkash' && txn.length < 5) return showToast('Please enter a valid Transaction ID');
  if (selectedIds.size === 0)                       return showToast('Select at least one item');

  showLoading(true);
  document.getElementById('submitBtn').disabled = true;

  const booksArr = [];
  allBooks.filter(b => selectedIds.has(b.id)).forEach(b => {
    const vs = selectedVariants.get(b.id);
    if (vs && vs.size > 0) vs.forEach(v => booksArr.push({ id: b.id, variant: v }));
    else booksArr.push({ id: b.id, variant: '' });
  });

  const body = new FormData();
  body.append('name',           name);
  body.append('phone',          countryCode + ' ' + phone);
  body.append('address',        address);
  body.append('area',           area);
  body.append('payment_method', paymentMethod);
  body.append('transaction_id', txn);
  body.append('books_json',     JSON.stringify(booksArr));

  try {
    const res  = await fetch('api/create-order.php', { method: 'POST', body });
    const data = await res.json();
    showLoading(false);
    document.getElementById('submitBtn').disabled = false;

    if (data.success) {
      const areaVal  = document.getElementById('farea').value;
      const delivery = areaVal === 'dhaka' ? parseFloat(settings.dhaka_charge || 80) : parseFloat(settings.outside_charge || 140);
      fbqTrack('Purchase', {
        content_ids: [...selectedIds].map(String),
        num_items:   selectedIds.size,
        value:       getSelectedTotal() + delivery,
        currency:    'BDT',
        order_id:    String(data.order_id),
      });
      document.getElementById('successMsg').textContent =
        `Order #${data.order_id} confirmed! We'll contact you shortly.`;
      document.getElementById('successModal').classList.add('show');
    } else {
      showToast(data.message || 'Failed to place order');
    }
  } catch (err) {
    showLoading(false);
    document.getElementById('submitBtn').disabled = false;
    showToast('Network error. Please try again');
  }
}

function resetAll() {
  selectedIds.clear();
  selectedVariants.clear();
  paymentMethod = 'bkash';
  document.getElementById('successModal').classList.remove('show');
  document.getElementById('orderForm').reset();
  document.getElementById('btnBkash').classList.add('active');
  document.getElementById('btnCod').classList.remove('active');
  renderBkashBox();
  closeOrderForm();
  updateSelectionUI();
}

// ─── UI Helpers ───────────────────────────────────────────
let toastTimer;
function showToast(msg) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 2800);
}

function showLoading(on) {
  document.getElementById('loadingOverlay').classList.toggle('show', on);
}

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
