window.DEE_GOOGLE_SCRIPT_URL = window.DEE_GOOGLE_SCRIPT_URL || "https://script.google.com/macros/s/AKfycby4GFix50mKnAHSpDGi-WQU6emttDYLDu3usIyqK6ncTtFJKbqfKND_wJLHeI_UF3J8/exec";
window.DEE_GOOGLE_SCRIPT_URL = String(window.DEE_GOOGLE_SCRIPT_URL || '').replace(/\/+$/, '');

function buildApiUrl(params) {
  const qp = new URLSearchParams(params || {});
  if (!qp.has('redirect')) qp.set('redirect', 'false');
  return window.DEE_GOOGLE_SCRIPT_URL + '?' + qp.toString();
}

function jsonpGet(url) {
  return new Promise((resolve, reject) => {
    const cbName = 'deeJsonp_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
    const script = document.createElement('script');
    const timeout = setTimeout(() => {
      cleanup();
      reject(new Error('JSONP timeout'));
    }, 15000);

    function cleanup() {
      clearTimeout(timeout);
      try {
        delete window[cbName];
      } catch (_) {
        window[cbName] = undefined;
      }
      if (script.parentNode) script.parentNode.removeChild(script);
    }

    window[cbName] = (data) => {
      cleanup();
      resolve(data);
    };

    script.onerror = () => {
      cleanup();
      reject(new Error('JSONP failed'));
    };

    const sep = url.includes('?') ? '&' : '?';
    script.src = url + sep + 'callback=' + encodeURIComponent(cbName);
    document.body.appendChild(script);
  });
}

async function apiGetJson(params) {
  const url = buildApiUrl(params);
  try {
    return await jsonpGet(url);
  } catch (_) {
    const res = await fetch(url);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return await res.json();
  }
}

async function apiPostJson(body) {
  const url = buildApiUrl({});
  const req = {
    method: 'POST',
    headers: { 'Content-Type': 'text/plain;charset=utf-8' },
    body: JSON.stringify(body || {})
  };

  try {
    // Prefer no-cors for GitHub Pages + Apps Script to avoid CORS blocks on response reading.
    await fetch(url, { ...req, mode: 'no-cors' });
    return { ok: true, queued: true, corsBypass: true };
  } catch (_) {
    // Fallback: if no-cors is blocked by policy, try standard CORS fetch.
    const res = await fetch(url, req);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return await res.json();
  }
}

window.DEE_API = {
  getJson: apiGetJson,
  postJson: apiPostJson
};

function formatPkr(value) {
  const n = Number(value || 0);
  if (!Number.isFinite(n)) return 'Rs 0';
  return 'Rs ' + Math.round(n).toLocaleString('en-PK');
}

const PRODUCT_STATUS = {
  AVAILABLE: 'available',
  PENDING: 'confirmation_pending',
  SOLD_OUT: 'sold_out'
};

const CATEGORY_FALLBACK_ORDER = ['new', 'shirts', 'bottoms', 'accessories', 'dresses', 'tops', 'misc'];
const CATEGORIES = [];

function normalizeCategoryKey(value) {
  return String(value || 'misc')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '') || 'misc';
}

function titleCaseCategory(value) {
  return String(value || '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, m => m.toUpperCase());
}

function normalizeProduct(raw) {
  const id = String(
    raw.productId || raw.product_id || raw.id || raw['Product ID'] || ''
  ).trim();

  const garmentType = normalizeCategoryKey(raw.garmentType || raw.garment_type || raw.category || raw.Category || 'accessories');
  const tags = []
    .concat(Array.isArray(raw.tags) ? raw.tags : String(raw.tags || raw.Tags || '').split(','))
    .map(v => normalizeCategoryKey(v))
    .filter(Boolean);
  if (tags.indexOf('new') === -1) tags.push('new');

  const categories = [garmentType]
    .concat(tags)
    .map(v => normalizeCategoryKey(v))
    .filter((v, i, arr) => v && arr.indexOf(v) === i);

  const statusRaw = String(raw.status || raw.Status || PRODUCT_STATUS.AVAILABLE).toLowerCase().trim();
  const status = [PRODUCT_STATUS.AVAILABLE, PRODUCT_STATUS.PENDING, PRODUCT_STATUS.SOLD_OUT].includes(statusRaw)
    ? statusRaw
    : PRODUCT_STATUS.AVAILABLE;

  const imageUrls = []
    .concat(Array.isArray(raw.imageUrls) ? raw.imageUrls : String(raw.imageUrls || raw.images || raw['Image URLs'] || '').split(','))
    .map(v => String(v || '').trim())
    .filter(Boolean);
  const primaryImg = String(raw.imageUrl || raw.image || raw.img || raw['Image URL'] || imageUrls[0] || '').trim();
  if (primaryImg && imageUrls.indexOf(primaryImg) === -1) imageUrls.unshift(primaryImg);

  const measurements = String(raw.measurements || raw.Measurements || raw.meta || raw.Meta || '').trim();
  const priceNum = Number(raw.price ?? raw.Price ?? 0);

  return {
    id,
    name: String(raw.name || raw.Name || '').trim() || 'Unnamed product',
    meta: measurements,
    price: Number.isFinite(priceNum) ? priceNum : 0,
    cond: String(raw.cond || raw.condition || raw.Condition || 'good').toLowerCase().trim() || 'good',
    img: primaryImg,
    images: imageUrls.slice(0, 5),
    category: garmentType,
    garmentType,
    tags,
    categories,
    status,
    orderId: String(raw.orderId || raw['Reserved Order ID'] || '').trim(),
    qty: 1
  };
}

const Products = {
  _url: window.DEE_GOOGLE_SCRIPT_URL,
  _cacheKey: 'dee_products_cache',

  setUrl(url) {
    this._url = url;
  },

  getCached() {
    try {
      const list = JSON.parse(localStorage.getItem(this._cacheKey) || '[]');
      return Array.isArray(list) ? list.map(normalizeProduct).filter(p => p.id) : [];
    } catch {
      return [];
    }
  },

  _saveCache(list) {
    localStorage.setItem(this._cacheKey, JSON.stringify(list));
  },

  async refresh() {
    if (!this._url) return this.getCached();
    try {
      const data = await window.DEE_API.getJson({ action: 'products' });
      const products = Array.isArray(data.products) ? data.products.map(normalizeProduct).filter(p => p.id) : [];
      this._saveCache(products);
      return products;
    } catch (e) {
      console.warn('Product refresh failed:', e);
      return this.getCached();
    }
  },

  getAll() {
    return this.getCached();
  }
};

function isDirectImageSource(img) {
  const s = String(img || '');
  return s.startsWith('http://') || s.startsWith('https://') || s.startsWith('data:image/');
}

function driveViewUrl(fileId) {
  return 'https://drive.google.com/uc?export=view&id=' + encodeURIComponent(fileId);
}

function getProductImageSrc(img, w, h) {
  const width = w || 360;
  const height = h || 360;
  const val = String(img || '').trim();

  if (!val) return `https://picsum.photos/seed/dee-fallback/${width}/${height}.jpg`;
  if (isDirectImageSource(val)) return val;

  if (/^[a-zA-Z0-9_-]{20,}$/.test(val)) {
    return driveViewUrl(val);
  }

  return `https://picsum.photos/seed/${encodeURIComponent(val)}/${width}/${height}.jpg`;
}

function getAllProducts() {
  return Products.getAll();
}

function getByCategory(cat) {
  const key = normalizeCategoryKey(cat);
  return getAllProducts().filter(p => {
    const list = Array.isArray(p.categories) ? p.categories : [p.category];
    return list.includes(key);
  });
}

function getCountByCategory(cat) {
  return getByCategory(cat).length;
}

function findProduct(id) {
  return getAllProducts().find(p => p.id === id);
}

function getCategories() {
  const byKey = new Map();
  getAllProducts().forEach(p => {
    const list = Array.isArray(p.categories) && p.categories.length ? p.categories : [p.category];
    list.forEach(catKey => {
      if (!byKey.has(catKey)) {
        byKey.set(catKey, {
          key: catKey,
          label: titleCaseCategory(catKey),
          img: p.img,
          count: 0
        });
      }
      byKey.get(catKey).count += 1;
      if (!byKey.get(catKey).img && p.img) byKey.get(catKey).img = p.img;
    });
  });

  const categories = Array.from(byKey.values());
  categories.sort((a, b) => {
    const ia = CATEGORY_FALLBACK_ORDER.indexOf(a.key);
    const ib = CATEGORY_FALLBACK_ORDER.indexOf(b.key);
    if (ia === -1 && ib === -1) return a.label.localeCompare(b.label);
    if (ia === -1) return 1;
    if (ib === -1) return -1;
    return ia - ib;
  });

  return categories;
}

const Customers = {
  _url: window.DEE_GOOGLE_SCRIPT_URL,

  setUrl(url) {
    this._url = url;
  },

  async getProfile(phone) {
    if (!this._url) return { returning: false, codBlocked: false, latestOrderStatus: '' };
    try {
      const clean = String(phone || '').replace(/\D/g, '');
      const data = await window.DEE_API.getJson({ phone: clean });
      return {
        returning: data.returning === true,
        codBlocked: data.codBlocked === true || data.codAllowed === false,
        latestOrderStatus: String(data.latestOrderStatus || '')
      };
    } catch (e) {
      console.warn('Customer profile check failed:', e);
      return { returning: false, codBlocked: false, latestOrderStatus: '' };
    }
  },

  async getAll() {
    if (!this._url) return [];
    try {
      const data = await window.DEE_API.getJson({ action: 'customers' });
      return Array.isArray(data.customers) ? data.customers : [];
    } catch (e) {
      console.warn('Customer list fetch failed:', e);
      return [];
    }
  }
};

const Orders = {
  _key: 'dee_pending_orders',
  _url: window.DEE_GOOGLE_SCRIPT_URL,

  setUrl(url) {
    this._url = url;
  },

  getAll() {
    try {
      const list = JSON.parse(localStorage.getItem(this._key) || '[]');
      return Array.isArray(list) ? list : [];
    } catch {
      return [];
    }
  },

  _save(o) {
    localStorage.setItem(this._key, JSON.stringify(o));
  },

  create(data) {
    const list = this.getAll();
    const order = {
      id: 'ord-' + Date.now(),
      createdAt: new Date().toISOString(),
      status: 'pending',
      ...data
    };
    list.push(order);
    this._save(list);
    return order;
  },

  markPaid(id) {
    const list = this.getAll();
    const o = list.find(x => x.id === id);
    if (o) o.status = 'paid';
    this._save(list);
  },

  async getOrdersRemote(status) {
    if (!this._url) return [];
    try {
      const data = await window.DEE_API.getJson({ action: 'orders', status: status || '' });
      return Array.isArray(data.orders) ? data.orders : [];
    } catch (e) {
      console.warn('Order list fetch failed:', e);
      return [];
    }
  },

  async getPendingRemote() {
    return this.getOrdersRemote('pending');
  },

  async setConfirmedRemote(orderId, notifyWhatsApp) {
    if (!this._url) return { ok: false, reason: 'no_url' };
    try {
      return await window.DEE_API.postJson({
        type: 'set_confirmation',
        orderId,
        confirmed: true,
        notifyWhatsApp: notifyWhatsApp === true
      });
    } catch (e) {
      console.warn('Order confirmation update failed:', e);
      return { ok: false, reason: 'connection_error' };
    }
  },

  async setStatusRemote(orderId, status, notifyWhatsApp) {
    if (!this._url) return { ok: false, reason: 'no_url' };
    try {
      return await window.DEE_API.postJson({
        type: 'set_order_status',
        orderId,
        status,
        notifyWhatsApp: notifyWhatsApp === true
      });
    } catch (e) {
      console.warn('Order status update failed:', e);
      return { ok: false, reason: 'connection_error' };
    }
  },

  remove(id) {
    this._save(this.getAll().filter(x => x.id !== id));
  }
};

function productStatusLabel(status) {
  const s = String(status || '').toLowerCase();
  if (s === PRODUCT_STATUS.PENDING) return 'confirmation pending';
  if (s === PRODUCT_STATUS.SOLD_OUT) return 'sold out';
  return '';
}

function isProductBuyable(status) {
  return String(status || '').toLowerCase() === PRODUCT_STATUS.AVAILABLE;
}

const Cart = {
  get() {
    try {
      const list = JSON.parse(localStorage.getItem('dee_cart') || '[]');
      return Array.isArray(list) ? list : [];
    } catch {
      return [];
    }
  },

  save(c) {
    localStorage.setItem('dee_cart', JSON.stringify(c));
    this.updateBadge();
  },

  async syncWithInventory() {
    await Products.refresh();
    const all = this.get();
    const filtered = all.filter(item => {
      const current = findProduct(item.id);
      return current && isProductBuyable(current.status);
    });
    if (filtered.length !== all.length) {
      this.save(filtered);
    }
  },

  add(productId) {
    const product = findProduct(productId);
    if (!product) {
      showToast('Product not found.');
      return;
    }

    if (!isProductBuyable(product.status)) {
      showToast('This product is currently not available.');
      return;
    }

    const cart = this.get();
    if (cart.find(i => i.id === productId)) {
      showToast('Already in cart.');
      return;
    }

    cart.push({ ...product, qty: 1 });
    this.save(cart);
    showToast(product.name + ' added to cart.');
  },

  remove(productId) {
    this.save(this.get().filter(i => i.id !== productId));
  },

  setQty(productId) {
    const cart = this.get();
    const item = cart.find(i => i.id === productId);
    if (item) item.qty = 1;
    this.save(cart);
  },

  total() {
    return this.get().reduce((s, i) => s + Number(i.price || 0) * Number(i.qty || 1), 0);
  },

  count() {
    return this.get().length;
  },

  clear() {
    localStorage.removeItem('dee_cart');
    this.updateBadge();
  },

  updateBadge() {
    document.querySelectorAll('.cart-badge').forEach(badge => {
      const n = this.count();
      badge.textContent = n;
      badge.style.display = n > 0 ? 'flex' : 'none';
    });
  }
};

let _toastTimer;
function showToast(msg) {
  const el = document.getElementById('toastEl');
  if (!el) return;
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => el.classList.remove('show'), 1800);
}

function buildCard(p) {
  const canBuy = isProductBuyable(p.status);
  const statusText = productStatusLabel(p.status);
  const statusBadge = statusText
    ? `<span class="shop-card-status ${p.status}">${statusText}</span>`
    : '';

  const addBtnLabel = canBuy ? '+' : '-';
  const addBtnClass = canBuy ? 'shop-card-add' : 'shop-card-add disabled';
  const addAction = canBuy ? `onclick="event.stopPropagation(); Cart.add('${p.id}')"` : '';

  const card = document.createElement('div');
  const displayImg = Array.isArray(p.images) && p.images.length ? p.images[0] : p.img;
  card.className = 'shop-card hover-tint';
  card.innerHTML = `
    <div style="position:relative;">
      <span class="shop-card-condition ${p.cond}">${p.cond}</span>
      ${statusBadge}
      <img class="shop-card-img" src="${getProductImageSrc(displayImg, 360, 360)}" alt="${p.name}">
    </div>
    <div class="shop-card-body">
      <div class="shop-card-name">${p.name}</div>
      <div class="shop-card-meta">${p.meta || ''}</div>
      <div class="shop-card-bottom">
        <div class="shop-card-price">${formatPkr(p.price)}</div>
        <button class="${addBtnClass}" ${addAction}>${addBtnLabel}</button>
      </div>
    </div>
  `;

  if (!canBuy) card.classList.add('shop-card-disabled');
  return card;
}

document.addEventListener('DOMContentLoaded', () => {
  Products.setUrl(window.DEE_GOOGLE_SCRIPT_URL);
  Customers.setUrl(window.DEE_GOOGLE_SCRIPT_URL);
  Orders.setUrl(window.DEE_GOOGLE_SCRIPT_URL);
  Cart.updateBadge();

  const page = document.body.dataset.page;
  document.querySelectorAll('.nav-link[data-page]').forEach(a => {
    if (a.dataset.page === page) a.classList.add('active');
    else a.classList.remove('active');
  });
});
