/* API layer */
window.DEE_API = {
  getJson(params) {
    const url = 'api.php?' + new URLSearchParams(params || {}).toString();
    const headers = {};
    if (typeof window !== 'undefined' && window.DEE_ADMIN_CSRF) {
      headers['X-CSRF-Token'] = String(window.DEE_ADMIN_CSRF);
    }
    return fetch(url, { headers }).then(async r => {
      let payload = {};
      try { payload = await r.json(); } catch { payload = {}; }
      if (!r.ok) {
        const err = new Error(payload.error || ('HTTP ' + r.status));
        err.status = r.status;
        err.payload = payload;
        throw err;
      }
      return payload;
    });
  },
  postJson(body) {
    const payload = (body && typeof body === 'object' && !Array.isArray(body)) ? { ...body } : {};
    if (typeof window !== 'undefined' && window.DEE_ADMIN_CSRF && !payload._csrf) {
      payload._csrf = String(window.DEE_ADMIN_CSRF);
    }
    return fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(async r => {
      let payload = {};
      try { payload = await r.json(); } catch { payload = {}; }
      if (!r.ok) {
        const err = new Error(payload.error || ('HTTP ' + r.status));
        err.status = r.status;
        err.payload = payload;
        throw err;
      }
      return payload;
    });
  }
};

/* Helpers */
function formatPkr(v) {
  const n = Number(v || 0);
  return Number.isFinite(n) ? 'Rs ' + Math.round(n).toLocaleString('en-PK') : 'Rs 0';
}

function escapeHtml(v) {
  return String(v || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

const PRODUCT_STATUS = { AVAILABLE: 'available', PENDING: 'confirmation_pending', SOLD_OUT: 'sold_out' };
const CATEGORY_FALLBACK_ORDER = ['new', 'bottoms', 'accessories', 'dresses', 'tops', 'bags', 'desi', 'misc'];

function normalizeCategoryKey(v) {
  const key = String(v || 'accessories')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '') || 'accessories';
  if (key === 'shirts') return 'tops';
  if (key === 'misc_items') return 'misc';
  return key;
}

function titleCaseCategory(v) {
  const key = normalizeCategoryKey(v);
  if (key === 'new') return 'New Arrivals';
  return String(key || '').replace(/_/g, ' ').replace(/\b\w/g, m => m.toUpperCase());
}

function normalizeCondition(value) {
  const raw = String(value || '').trim();
  const legacy = raw.toLowerCase();
  if (legacy === 'great') return '9/10';
  if (legacy === 'good') return '8/10';
  const m = raw.match(/^(10|[1-9])\s*\/\s*10$/);
  return m ? (parseInt(m[1], 10) + '/10') : '10/10';
}

function normalizeDefects(value) {
  const text = String(value || '').trim();
  return text || 'No known defects';
}

function normalizeProduct(raw) {
  const id = String(raw.productId || raw.id || '').trim();
  const garmentType = normalizeCategoryKey(raw.garmentType || raw.category || 'accessories');
  const tags = [].concat(raw.tags || ['new']).map(normalizeCategoryKey).filter(Boolean);
  if (!tags.includes('new')) tags.push('new');
  const categories = [garmentType].concat(tags).filter((v, i, a) => v && a.indexOf(v) === i);

  const statusRaw = String(raw.status || PRODUCT_STATUS.AVAILABLE).toLowerCase().trim();
  const status = [PRODUCT_STATUS.AVAILABLE, PRODUCT_STATUS.PENDING, PRODUCT_STATUS.SOLD_OUT].includes(statusRaw)
    ? statusRaw : PRODUCT_STATUS.AVAILABLE;

  const imageUrls = [].concat(raw.imageUrls || raw.images || [])
    .map(v => String(v || '').trim()).filter(Boolean);
  const primaryImg = String(raw.imageUrl || raw.img || imageUrls[0] || '').trim();
  if (primaryImg && !imageUrls.includes(primaryImg)) imageUrls.unshift(primaryImg);

  return {
    id,
    name: String(raw.name || '').trim() || 'Unnamed',
    meta: String(raw.measurements || raw.meta || '').trim(),
    description: String(raw.description || raw.desc || '').trim(),
    price: Number(raw.price) || 0,
    cond: normalizeCondition(raw.cond || ''),
    defects: normalizeDefects(raw.defects || ''),
    img: primaryImg,
    images: imageUrls.slice(0, 5),
    category: garmentType,
    garmentType,
    tags,
    categories,
    status,
    qty: 1,
    is_featured: !!(raw.is_featured)
  };
}

function getProductImageSrc(img, w, h) {
  const val = String(img || '').trim();
  if (!val) return '';
  if (val.startsWith('http://') || val.startsWith('https://') || val.startsWith('data:image/') || val.startsWith('/')) return val;
  return val;
}

function getImageOrFallbackHtml(img, alt, imgClass, imgStyle, fallbackClass, fallbackStyle, fallbackText) {
  const src = getProductImageSrc(img);
  if (src) {
    return '<img' +
      (imgClass ? ' class="' + imgClass + '"' : '') +
      ' src="' + src + '"' +
      ' alt="' + escapeHtml(alt || '') + '"' +
      (imgStyle ? ' style="' + imgStyle + '"' : '') +
      '>';
  }
  return '<div' +
    ' class="' + (fallbackClass || 'no-image-fallback') + '"' +
    (fallbackStyle ? ' style="' + fallbackStyle + '"' : '') +
    '>' + escapeHtml(fallbackText || 'No image available') + '</div>';
}

function setModalMainImage(mainWrap, mainImg, src, altText) {
  if (!mainWrap || !mainImg) return false;
  let fallbackEl = mainWrap.querySelector('.modal-no-image');
  if (src) {
    if (fallbackEl) fallbackEl.style.display = 'none';
    mainImg.style.display = 'block';
    mainImg.src = src;
    mainImg.alt = altText || 'Product image';
    mainImg.style.cursor = 'zoom-in';
    return true;
  }
  mainImg.removeAttribute('src');
  mainImg.style.display = 'none';
  if (!fallbackEl) {
    fallbackEl = document.createElement('div');
    fallbackEl.className = 'modal-no-image no-image-fallback';
    fallbackEl.textContent = 'No image available';
    mainWrap.appendChild(fallbackEl);
  }
  fallbackEl.style.display = 'flex';
  return false;
}

function productStatusLabel(s) {
  s = String(s || '').toLowerCase();
  if (s === PRODUCT_STATUS.PENDING) return '';
  if (s === PRODUCT_STATUS.SOLD_OUT) return 'sold out';
  return '';
}

function isProductBuyable(s) {
  return String(s || '').toLowerCase() !== PRODUCT_STATUS.SOLD_OUT;
}

/* Products */
const CategoryCovers = {
  _cacheKey: 'dee_category_covers_cache',
  _normalizeMap(map) {
    const src = map && typeof map === 'object' ? map : {};
    const out = {};
    Object.keys(src).forEach(k => {
      const key = normalizeCategoryKey(k);
      const pid = String(src[k] || '').trim();
      if (key && pid) out[key] = pid;
    });
    return out;
  },
  getMap() {
    try {
      return this._normalizeMap(JSON.parse(localStorage.getItem(this._cacheKey) || '{}'));
    } catch {
      return {};
    }
  },
  saveMap(map) {
    const normalized = this._normalizeMap(map);
    localStorage.setItem(this._cacheKey, JSON.stringify(normalized));
    return normalized;
  },
  setFromApi(map) {
    return this.saveMap(map || {});
  },
  setOne(category, productId) {
    const key = normalizeCategoryKey(category);
    const pid = String(productId || '').trim();
    const map = this.getMap();
    if (!key) return;
    if (pid) map[key] = pid;
    else delete map[key];
    this.saveMap(map);
  },
  getProductId(category) {
    return this.getMap()[normalizeCategoryKey(category)] || '';
  }
};

const Products = {
  _cacheKey: 'dee_products_cache',
  getCached() {
    try {
      return JSON.parse(localStorage.getItem(this._cacheKey) || '[]').map(normalizeProduct).filter(p => p.id);
    } catch {
      return [];
    }
  },
  _save(list) {
    localStorage.setItem(this._cacheKey, JSON.stringify(list));
  },
  async refresh() {
    localStorage.removeItem(this._cacheKey);
    try {
      const data = await window.DEE_API.getJson({ action: 'products' });
      const products = (data.products || []).map(normalizeProduct).filter(p => p.id);
      if (data.categoryCovers && typeof data.categoryCovers === 'object') CategoryCovers.setFromApi(data.categoryCovers);
      this._save(products);
      return products;
    } catch {
      return this.getCached();
    }
  },
  getAll() {
    return this.getCached();
  }
};

function getAllProducts() { return Products.getAll(); }
function getByCategory(cat) {
  const key = normalizeCategoryKey(cat);
  return getAllProducts().filter(p => (p.categories || [p.category]).includes(key));
}
function getCountByCategory(cat) { return getByCategory(cat).length; }
function findProduct(id) { return getAllProducts().find(p => p.id === id); }

function getCategories() {
  const byKey = new Map();
  const allProducts = getAllProducts();

  CATEGORY_FALLBACK_ORDER
    .map(normalizeCategoryKey)
    .forEach(k => {
      if (!byKey.has(k)) byKey.set(k, { key: k, label: titleCaseCategory(k), img: '', count: 0 });
    });

  allProducts.forEach(p => {
    (p.categories || [p.category]).forEach(k => {
      const key = normalizeCategoryKey(k);
      if (!byKey.has(key)) byKey.set(key, { key, label: titleCaseCategory(key), img: p.img, count: 0 });
      byKey.get(key).count++;
      if (!byKey.get(key).img && p.img) byKey.get(key).img = p.img;
    });
  });

  const productsById = new Map(allProducts.map(p => [p.id, p]));
  const coverMap = CategoryCovers.getMap();
  byKey.forEach(function(cat) {
    const coverPid = coverMap[cat.key] || '';
    if (!coverPid) return;
    const coverProduct = productsById.get(coverPid);
    if (!coverProduct) return;
    const coverImg = (coverProduct.images && coverProduct.images[0]) || coverProduct.img || '';
    if (coverImg) cat.img = coverImg;
  });

  return Array.from(byKey.values()).sort((a, b) => {
    const ia = CATEGORY_FALLBACK_ORDER.indexOf(a.key);
    const ib = CATEGORY_FALLBACK_ORDER.indexOf(b.key);
    if (ia === -1 && ib === -1) return a.label.localeCompare(b.label);
    if (ia === -1) return 1;
    if (ib === -1) return -1;
    return ia - ib;
  });
}

/* Customers */
const Customers = {
  async getProfile(phone) {
    try {
      const d = await window.DEE_API.getJson({ action: 'customer', phone: String(phone || '').replace(/\D/g, '') });
      return {
        returning: d.returning === true,
        codBlocked: d.codBlocked === true,
        latestOrderStatus: d.latestOrderStatus || ''
      };
    } catch {
      return { returning: false, codBlocked: false, latestOrderStatus: '' };
    }
  }
};

/* Orders */
const Orders = {
  async getOrdersRemote(status) {
    try {
      return (await window.DEE_API.getJson({ action: 'orders', status: status || '' })).orders || [];
    } catch {
      return [];
    }
  },
  async getOrder(id) {
    try {
      return (await window.DEE_API.getJson({ action: 'order', id })).order || null;
    } catch {
      return null;
    }
  },
  async setStatusRemote(orderId, status) {
    try {
      return await window.DEE_API.postJson({ type: 'set_order_status', orderId, status });
    } catch {
      return { ok: false };
    }
  }
};

/* Drafts */
const Drafts = {
  _key: 'dee_drafts',
  get() { try { return JSON.parse(localStorage.getItem(this._key) || '[]'); } catch { return []; } },
  save(list) { localStorage.setItem(this._key, JSON.stringify(list)); },
  add(draft) { const a = this.get(); a.push(draft); this.save(a); },
  remove(idx) { const a = this.get(); a.splice(idx, 1); this.save(a); },
  clear() { localStorage.removeItem(this._key); },
  count() { return this.get().length; }
};

/* Cart */
const Cart = {
  get() {
    try { return JSON.parse(localStorage.getItem('dee_cart') || '[]'); } catch { return []; }
  },
  save(c) {
    localStorage.setItem('dee_cart', JSON.stringify(c));
    this.updateBadge();
  },
  async syncWithInventory(options) {
    const opts = options || {};
    if (opts.refreshProducts !== false) await Products.refresh();
    const all = this.get();
    const filtered = all.filter(item => {
      const current = findProduct(item.id);
      return current && isProductBuyable(current.status);
    });
    if (filtered.length !== all.length) this.save(filtered);
  },
  add(pid) {
    const p = findProduct(pid);
    if (!p) { showToast('Product not found.'); return; }
    if (!isProductBuyable(p.status)) { showToast('Not available.'); return; }
    if (this.get().find(i => i.id === pid)) { showToast('Already in cart.'); return; }
    this.save([].concat(this.get(), [{ ...p, qty: 1 }]));
    showToast(p.name + ' added to cart.');
  },
  remove(pid) {
    this.save(this.get().filter(i => i.id !== pid));
  },
  total() {
    return this.get().reduce((s, i) => s + (i.price || 0) * (i.qty || 1), 0);
  },
  count() {
    return this.get().length;
  },
  clear() {
    localStorage.removeItem('dee_cart');
    this.updateBadge();
  },
  updateBadge() {
    document.querySelectorAll('.cart-badge').forEach(b => {
      const n = this.count();
      b.textContent = n;
      b.style.display = n > 0 ? 'flex' : 'none';
    });
  }
};

/* Auto refresh helper */
window.startDeeAutoRefresh = function(options) {
  const opts = Object.assign({
    pollMs: 4000,
    refreshProducts: true,
    syncCart: true,
    onChange: null,
    initialSync: false
  }, options || {});

  const tokenKey = 'dee_change_token';
  let stopped = false;
  let busy = false;
  let lastToken = localStorage.getItem(tokenKey) || '';
  let timer = null;

  async function runRefresh(reason) {
    if (opts.refreshProducts) await Products.refresh();
    if (opts.syncCart) await Cart.syncWithInventory({ refreshProducts: false });
    if (typeof opts.onChange === 'function') await opts.onChange(reason);
  }

  async function check(reason) {
    if (stopped || busy) return;
    busy = true;
    try {
      const data = await window.DEE_API.getJson({ action: 'change_token' });
      const token = String(data.token || '');
      if (!token) return;

      if (!lastToken) {
        lastToken = token;
        localStorage.setItem(tokenKey, token);
        if (opts.initialSync) await runRefresh(reason || 'initial');
        return;
      }

      if (token !== lastToken) {
        lastToken = token;
        localStorage.setItem(tokenKey, token);
        await runRefresh(reason || 'change');
      }
    } catch {
      // Silent fallback for offline/transient errors.
    } finally {
      busy = false;
    }
  }

  async function onStorage(e) {
    if (stopped || e.key !== tokenKey || !e.newValue || e.newValue === lastToken) return;
    lastToken = e.newValue;
    await runRefresh('storage');
  }

  function onVisibility() {
    if (document.hidden || stopped) return;
    check('visibility');
  }

  function onFocus() {
    if (stopped) return;
    check('focus');
  }

  window.addEventListener('storage', onStorage);
  document.addEventListener('visibilitychange', onVisibility);
  window.addEventListener('focus', onFocus);
  timer = setInterval(() => check('interval'), Math.max(2000, Number(opts.pollMs) || 4000));
  setTimeout(() => check('start'), 900);

  return function stop() {
    stopped = true;
    if (timer) clearInterval(timer);
    window.removeEventListener('storage', onStorage);
    document.removeEventListener('visibilitychange', onVisibility);
    window.removeEventListener('focus', onFocus);
  };
};

/* Toast */
let _toastTimer;
function showToast(msg) {
  const el = document.getElementById('toastEl');
  if (!el) return;
  el.textContent = String(msg || '');
  el.classList.add('show');
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => el.classList.remove('show'), 1800);
}

/* Card builder */
function buildCard(p) {
  const canBuy = isProductBuyable(p.status);
  const st = productStatusLabel(p.status);
  const statusBadge = st ? '<span class="shop-card-status ' + p.status + '">' + escapeHtml(st) + '</span>' : '';
  const card = document.createElement('div');
  const displayImg = (p.images && p.images[0]) || p.img;

  card.className = 'shop-card hover-tint' + (canBuy ? '' : ' shop-card-disabled');
  card.innerHTML =
    '<div class="shop-card-media">' +
      '<span class="shop-card-condition">' + escapeHtml(p.cond) + '</span>' +
      statusBadge +
      getImageOrFallbackHtml(displayImg, p.name, 'shop-card-img', '', 'shop-card-img no-image-fallback') +
    '</div>' +
    '<div class="shop-card-body">' +
      '<div class="shop-card-name">' + escapeHtml(p.name) + '</div>' +
      '<div class="shop-card-meta">' + escapeHtml(p.meta || '') + '</div>' +
      '<div class="shop-card-bottom">' +
        '<div class="shop-card-price">' + formatPkr(p.price) + '</div>' +
        '<button class="shop-card-add' + (canBuy ? '' : ' disabled') + '" data-pid="' + escapeHtml(p.id) + '">' +
          (canBuy ? '+' : '-') +
        '</button>' +
      '</div>' +
    '</div>';

  const addBtn = card.querySelector('.shop-card-add');
  if (addBtn && canBuy) {
    addBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      Cart.add(p.id);
    });
  }

  card.addEventListener('click', function() { openProductModal(p); });
  return card;
}

/* Product modal + zoom */
window._productModalImages = [];
window._productModalIndex = 0;
window._productModalZoomed = false;

function setModalZoom(zoomed) {
  const overlay = document.getElementById('productModal');
  if (!overlay) return;
  const mainImg = overlay.querySelector('.modal-main-img');
  if (!mainImg || mainImg.style.display === 'none') {
    window._productModalZoomed = false;
    return;
  }

  window._productModalZoomed = !!zoomed;
  if (window._productModalZoomed) {
    mainImg.style.transform = 'scale(2)';
    mainImg.style.cursor = 'zoom-out';
  } else {
    mainImg.style.transform = 'scale(1)';
    mainImg.style.transformOrigin = '50% 50%';
    mainImg.style.cursor = 'zoom-in';
  }
}

window.toggleModalZoom = function(forceState) {
  const next = typeof forceState === 'boolean' ? forceState : !window._productModalZoomed;
  setModalZoom(next);
};

window.openProductModal = function(p) {
  const overlay = document.getElementById('productModal');
  if (!overlay) return;

  let imgs = [];
  if (Array.isArray(p.images) && p.images.length) imgs = p.images.map(function(v) { return String(v || '').trim(); }).filter(Boolean);
  else if (p.img) imgs = [String(p.img).trim()].filter(Boolean);
  if (!imgs.length) imgs = [''];

  const canBuy = isProductBuyable(p.status);
  const st = productStatusLabel(p.status);

  window._productModalImages = imgs;
  window._productModalIndex = 0;
  setModalZoom(false);

  const mainImg = overlay.querySelector('.modal-main-img');
  const mainWrap = overlay.querySelector('.modal-main-img-wrap');
  const firstUrl = getProductImageSrc(imgs[0] || '');
  const hasMainImage = setModalMainImage(mainWrap, mainImg, firstUrl, p.name || 'Product image');

  if (hasMainImage) {
    mainImg.onclick = function(e) {
      e.stopPropagation();
      window.toggleModalZoom();
    };
    mainImg.onmousemove = function(e) {
      if (!window._productModalZoomed) return;
      const rect = mainImg.getBoundingClientRect();
      const x = ((e.clientX - rect.left) / rect.width) * 100;
      const y = ((e.clientY - rect.top) / rect.height) * 100;
      mainImg.style.transformOrigin = x + '% ' + y + '%';
    };
    if (mainWrap) {
      mainWrap.onmouseleave = function() {
        if (window._productModalZoomed) mainImg.style.transformOrigin = '50% 50%';
      };
    }
  } else {
    mainImg.onclick = null;
    mainImg.onmousemove = null;
    if (mainWrap) mainWrap.onmouseleave = null;
  }

  const thumbsContainer = overlay.querySelector('.modal-thumbs');
  thumbsContainer.innerHTML = '';
  const validThumbs = imgs.filter(function(url) { return !!getProductImageSrc(url); });
  imgs.forEach(function(url, i) {
    const thumbSrc = getProductImageSrc(url);
    if (!thumbSrc) return;
    const thumb = document.createElement('img');
    thumb.className = 'modal-thumb' + (i === 0 ? ' active' : '');
    thumb.src = thumbSrc;
    thumb.alt = 'Image ' + (i + 1);
    thumb.addEventListener('click', function() {
      window._productModalIndex = i;
      updateModalImage();
    });
    thumbsContainer.appendChild(thumb);
  });
  thumbsContainer.style.display = validThumbs.length > 1 ? 'flex' : 'none';

  const condEl = overlay.querySelector('.modal-condition');
  condEl.textContent = normalizeCondition(p.cond);
  condEl.className = 'modal-condition';

  overlay.querySelector('.modal-name').textContent = p.name || 'Unnamed';
  overlay.querySelector('.modal-meta').textContent = p.meta || 'No measurements listed.';
  var descEl = overlay.querySelector('.modal-description');
  var descText = String(p.description || '').trim();
  if (descEl) {
    if (descText) {
      descEl.textContent = descText;
      descEl.style.display = 'block';
    } else {
      descEl.textContent = '';
      descEl.style.display = 'none';
    }
  }
  overlay.querySelector('.modal-defects').textContent = normalizeDefects(p.defects);

  const statusEl = overlay.querySelector('.modal-status');
  if (st) {
    statusEl.textContent = st;
    statusEl.className = 'modal-status ' + p.status;
    statusEl.style.display = 'block';
  } else {
    statusEl.style.display = 'none';
  }

  overlay.querySelector('.modal-price').textContent = formatPkr(p.price);

  const addBtn = overlay.querySelector('.modal-add');
  if (canBuy) {
    addBtn.textContent = 'Add to Cart';
    addBtn.className = 'aero-btn pink-btn modal-add';
    addBtn.style.opacity = '1';
    addBtn.style.pointerEvents = 'auto';
    addBtn.onclick = function(e) {
      e.stopPropagation();
      Cart.add(p.id);
      addBtn.textContent = 'Added!';
      addBtn.style.opacity = '0.6';
      addBtn.style.pointerEvents = 'none';
      setTimeout(function() {
        addBtn.textContent = 'Add to Cart';
        addBtn.style.opacity = '1';
        addBtn.style.pointerEvents = 'auto';
      }, 1200);
    };
  } else {
    addBtn.textContent = st || 'Unavailable';
    addBtn.className = 'aero-btn modal-add';
    addBtn.style.opacity = '0.4';
    addBtn.style.pointerEvents = 'none';
    addBtn.onclick = null;
  }

  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';
};

window.closeProductModal = function() {
  const overlay = document.getElementById('productModal');
  if (!overlay) return;
  overlay.classList.remove('open');
  document.body.style.overflow = '';
  setModalZoom(false);
};

window.nextProductImage = function() {
  let imgs = window._productModalImages;
  if (!imgs || !imgs.length) imgs = window._productModalImages = [''];
  if (imgs.length <= 1) return;
  window._productModalIndex = (window._productModalIndex + 1) % imgs.length;
  updateModalImage();
};

window.prevProductImage = function() {
  let imgs = window._productModalImages;
  if (!imgs || !imgs.length) imgs = window._productModalImages = [''];
  if (imgs.length <= 1) return;
  window._productModalIndex = (window._productModalIndex - 1 + imgs.length) % imgs.length;
  updateModalImage();
};

function updateModalImage() {
  const overlay = document.getElementById('productModal');
  if (!overlay) return;
  const mainImg = overlay.querySelector('.modal-main-img');
  const mainWrap = overlay.querySelector('.modal-main-img-wrap');
  const thumbsContainer = overlay.querySelector('.modal-thumbs');
  const imgs = window._productModalImages || [''];
  const idx = window._productModalIndex || 0;
  const src = getProductImageSrc(imgs[idx] || '');
  setModalMainImage(mainWrap, mainImg, src, 'Product image');
  thumbsContainer.querySelectorAll('.modal-thumb').forEach(function(t, i) {
    t.classList.toggle('active', i === idx);
  });
  setModalZoom(false);
}

document.addEventListener('click', function(e) {
  if (e.target.id === 'productModal') closeProductModal();
});

document.addEventListener('keydown', function(e) {
  const overlay = document.getElementById('productModal');
  const open = overlay && overlay.classList.contains('open');
  if (!open) return;

  if (e.key === 'ArrowLeft') { prevProductImage(); e.preventDefault(); }
  if (e.key === 'ArrowRight') { nextProductImage(); e.preventDefault(); }
  if (e.key.toLowerCase() === 'z') { window.toggleModalZoom(); e.preventDefault(); }
  if (e.key === 'Escape') closeProductModal();
});

document.addEventListener('keydown', function(e) {
  if (e.altKey && !e.shiftKey && !e.ctrlKey && !e.metaKey && (String(e.key || '').toLowerCase() === 'a' || String(e.code || '') === 'KeyA')) {
    e.preventDefault();
    window.location.href = 'admin.php';
  }
});

/* Boot */
document.addEventListener('DOMContentLoaded', function() {
  Products.refresh();
  Cart.updateBadge();
  const page = document.body.dataset.page;
  document.querySelectorAll('.nav-link[data-page]').forEach(a => {
    a.classList.toggle('active', a.dataset.page === page);
  });
});
