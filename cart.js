const GOOGLE_SCRIPT_URL = "https://script.google.com/macros/s/AKfycby4GFix50mKnAHSpDGi-WQU6emttDYLDu3usIyqK6ncTtFJKbqfKND_wJLHeI_UF3J8/exec";

const PRODUCT_STATUS = {
  AVAILABLE: 'available',
  PENDING: 'confirmation_pending',
  SOLD_OUT: 'sold_out'
};

const CATEGORY_FALLBACK_ORDER = ['new', 'dresses', 'tops', 'bottoms'];
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

  const category = normalizeCategoryKey(raw.category || raw.Category || 'misc');
  const statusRaw = String(raw.status || raw.Status || PRODUCT_STATUS.AVAILABLE).toLowerCase().trim();
  const status = [PRODUCT_STATUS.AVAILABLE, PRODUCT_STATUS.PENDING, PRODUCT_STATUS.SOLD_OUT].includes(statusRaw)
    ? statusRaw
    : PRODUCT_STATUS.AVAILABLE;

  const priceNum = Number(raw.price ?? raw.Price ?? 0);

  return {
    id,
    name: String(raw.name || raw.Name || '').trim() || 'Unnamed product',
    meta: String(raw.meta || raw.Meta || '').trim(),
    price: Number.isFinite(priceNum) ? priceNum : 0,
    cond: String(raw.cond || raw.condition || raw.Condition || 'good').toLowerCase().trim() || 'good',
    img: String(raw.imageUrl || raw.image || raw.img || raw['Image URL'] || '').trim(),
    category,
    status,
    orderId: String(raw.orderId || raw['Reserved Order ID'] || '').trim()
  };
}

const Products = {
  _url: GOOGLE_SCRIPT_URL,
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
      const res = await fetch(this._url + '?redirect=false&action=products');
      const data = await res.json();
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
  return getAllProducts().filter(p => p.category === key);
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
    if (!byKey.has(p.category)) {
      byKey.set(p.category, {
        key: p.category,
        label: titleCaseCategory(p.category),
        img: p.img,
        count: 0
      });
    }
    byKey.get(p.category).count += 1;
    if (!byKey.get(p.category).img && p.img) byKey.get(p.category).img = p.img;
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
  _url: GOOGLE_SCRIPT_URL,

  setUrl(url) {
    this._url = url;
  },

  async getProfile(phone) {
    if (!this._url) return { returning: false, codBlocked: false, latestOrderStatus: '' };
    try {
      const clean = String(phone || '').replace(/\D/g, '');
      const res = await fetch(this._url + '?redirect=false&phone=' + encodeURIComponent(clean));
      const data = await res.json();
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
      const res = await fetch(this._url + '?redirect=false&action=customers');
      const data = await res.json();
      return Array.isArray(data.customers) ? data.customers : [];
    } catch (e) {
      console.warn('Customer list fetch failed:', e);
      return [];
    }
  }
};

const Orders = {
  _key: 'dee_pending_orders',
  _url: GOOGLE_SCRIPT_URL,

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
      let url = this._url + '?redirect=false&action=orders';
      if (status) url += '&status=' + encodeURIComponent(status);
      const res = await fetch(url);
      const data = await res.json();
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
      const res = await fetch(this._url + '?redirect=false', {
        method: 'POST',
        headers: { 'Content-Type': 'text/plain;charset=utf-8' },
        body: JSON.stringify({
          type: 'set_confirmation',
          orderId,
          confirmed: true,
          notifyWhatsApp: notifyWhatsApp === true
        })
      });
      return await res.json();
    } catch (e) {
      console.warn('Order confirmation update failed:', e);
      return { ok: false, reason: 'connection_error' };
    }
  },

  async setStatusRemote(orderId, status, notifyWhatsApp) {
    if (!this._url) return { ok: false, reason: 'no_url' };
    try {
      const res = await fetch(this._url + '?redirect=false', {
        method: 'POST',
        headers: { 'Content-Type': 'text/plain;charset=utf-8' },
        body: JSON.stringify({
          type: 'set_order_status',
          orderId,
          status,
          notifyWhatsApp: notifyWhatsApp === true
        })
      });
      return await res.json();
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
  card.className = 'shop-card hover-tint';
  card.innerHTML = `
    <div style="position:relative;">
      <span class="shop-card-condition ${p.cond}">${p.cond}</span>
      ${statusBadge}
      <img class="shop-card-img" src="${getProductImageSrc(p.img, 360, 360)}" alt="${p.name}">
    </div>
    <div class="shop-card-body">
      <div class="shop-card-name">${p.name}</div>
      <div class="shop-card-meta">${p.meta || ''}</div>
      <div class="shop-card-bottom">
        <div class="shop-card-price">$${Number(p.price || 0).toFixed(2)}</div>
        <button class="${addBtnClass}" ${addAction}>${addBtnLabel}</button>
      </div>
    </div>
  `;

  if (!canBuy) card.classList.add('shop-card-disabled');
  return card;
}

document.addEventListener('DOMContentLoaded', () => {
  Products.setUrl(GOOGLE_SCRIPT_URL);
  Customers.setUrl(GOOGLE_SCRIPT_URL);
  Orders.setUrl(GOOGLE_SCRIPT_URL);
  Cart.updateBadge();

  const page = document.body.dataset.page;
  document.querySelectorAll('.nav-link[data-page]').forEach(a => {
    if (a.dataset.page === page) a.classList.add('active');
    else a.classList.remove('active');
  });
});
