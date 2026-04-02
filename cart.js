// ══════════════════════════════════════════
//  DEFAULT PRODUCTS (qty is always 1 per item)
// ══════════════════════════════════════════
const DEFAULT_PRODUCTS = [
  { id:"jacket1",  name:"Vintage Levi's Denim Jacket", meta:"Size M · 1980s",       price:34, cond:"great", img:"dee-jacket1",    category:"new" },
  { id:"watch3",   name:"Seiko 5 Automatic Watch",     meta:"1978 · Gold Tone",      price:55, cond:"good",  img:"dee-watch3",     category:"new" },
  { id:"bag5",     name:"Vintage Leather Satchel",     meta:"Brown · 12\"",          price:40, cond:"good",  img:"dee-bag5",       category:"new" },
  { id:"shoes6",   name:"Dr. Martens 1460 Boots",      meta:"UK 7 · Cherry Red",     price:48, cond:"good",  img:"dee-shoes6",     category:"new" },
  { id:"lamp7",    name:"Brass Table Lamp",            meta:"1965 · Restored",       price:38, cond:"great", img:"dee-lamp7",      category:"new" },
  { id:"dress2",   name:"Silk Floral Midi Dress",      meta:"Size S · 1970s",       price:28, cond:"great", img:"dee-dress2",     category:"dresses" },
  { id:"velvet1",  name:"Velvet Slip Dress",           meta:"Size M · 1990s",       price:24, cond:"great", img:"dee-velvet-d1",  category:"dresses" },
  { id:"smock2",   name:"Cotton Smock Dress",          meta:"Size L · 1980s",       price:20, cond:"good",  img:"dee-smock-d2",   category:"dresses" },
  { id:"wrap3",    name:"Linen Wrap Dress",            meta:"Size S · 1970s",       price:30, cond:"great", img:"dee-wrap-d3",    category:"dresses" },
  { id:"party4",   name:"Nylon Party Dress",           meta:"Size M · 1960s",       price:26, cond:"good",  img:"dee-party-d4",   category:"dresses" },
  { id:"knit4",    name:"Hand-Knit Wool Cardigan",     meta:"Size L · 1960s",       price:22, cond:"great", img:"dee-knit4",      category:"tops" },
  { id:"band1",    name:"Vintage Band Tee",            meta:"Size M · 1995",        price:18, cond:"good",  img:"dee-band-t1",    category:"tops" },
  { id:"cham2",    name:"Chambray Button-Down",        meta:"Size M · 1980s",       price:16, cond:"great", img:"dee-cham-t2",    category:"tops" },
  { id:"cash3",    name:"Cashmere V-Neck Sweater",     meta:"Size S · Cream",       price:32, cond:"great", img:"dee-cash-t3",    category:"tops" },
  { id:"crop4",    name:"Cropped Denim Jacket",        meta:"Size S · 1990s",       price:28, cond:"good",  img:"dee-crop-t4",    category:"tops" },
  { id:"polo5",    name:"Polo Ralph Lauren Oxford",    meta:"Size M · Y2K",         price:15, cond:"great", img:"dee-polo-t5",    category:"tops" },
  { id:"levi1",    name:"High-Waist Levi's 501",       meta:"Size 28 · 1985",       price:30, cond:"great", img:"dee-levi-b1",    category:"bottoms" },
  { id:"pleat2",   name:"Pleated Midi Skirt",          meta:"Size M · 1970s",       price:20, cond:"great", img:"dee-pleat-b2",   category:"bottoms" },
  { id:"cord3",    name:"Corduroy Flares",             meta:"Size 29 · 1970s",      price:26, cond:"good",  img:"dee-cord-b3",    category:"bottoms" },
  { id:"cargo4",   name:"Vintage Cargo Pants",         meta:"Size M · 1990s",       price:22, cond:"good",  img:"dee-cargo-b4",   category:"bottoms" },
  { id:"tweed5",   name:"Wool Tweed Trousers",         meta:"Size L · 1960s",       price:28, cond:"great", img:"dee-tweed-b5",   category:"bottoms" },
];

const CATEGORIES = [
  { key:"new",      label:"✦ New Arrivals", img:"dee-jacket1" },
  { key:"dresses",  label:"✦ Dresses",      img:"dee-dress2"  },
  { key:"tops",     label:"✦ Tops",         img:"dee-knit4"   },
  { key:"bottoms",  label:"✦ Bottoms",      img:"dee-levi-b1" },
];

// ══════════════════════════════════════════
//  PRODUCT GETTERS (merges default + live)
// ══════════════════════════════════════════
function getAllProducts() {
  const live = Items.getLive();
  return [...DEFAULT_PRODUCTS, ...live];
}
function getByCategory(cat) {
  return getAllProducts().filter(p => p.category === cat);
}
function getCountByCategory(cat) {
  return getByCategory(cat).length;
}
function findProduct(id) {
  return getAllProducts().find(p => p.id === id);
}

// ══════════════════════════════════════════
//  ITEM MANAGER (drafts + live releases)
// ══════════════════════════════════════════
const Items = {
  _draftKey:  'dee_drafts',
  _liveKey:   'dee_live_items',

  getDrafts()  { try { return JSON.parse(localStorage.getItem(this._draftKey)  || '[]'); } catch { return []; } },
  getLive()    { try { return JSON.parse(localStorage.getItem(this._liveKey)   || '[]'); } catch { return []; } },
  _saveDrafts(d)  { localStorage.setItem(this._draftKey,  JSON.stringify(d)); },
  _saveLive(l)    { localStorage.setItem(this._liveKey,   JSON.stringify(l)); },

  addDraft(item) {
    const drafts = this.getDrafts();
    item.id = 'custom-' + Date.now() + '-' + Math.random().toString(36).slice(2,6);
    drafts.push(item);
    this._saveDrafts(drafts);
    return item;
  },
  removeDraft(id) {
    this._saveDrafts(this.getDrafts().filter(i => i.id !== id));
  },
  removeLive(id) {
    this._saveLive(this.getLive().filter(i => i.id !== id));
  },
  releaseAll() {
    const drafts = this.getDrafts();
    if (drafts.length === 0) return 0;
    const live = this.getLive();
    live.push(...drafts);
    this._saveLive(live);
    this._saveDrafts([]);
    return drafts.length;
  },
  exportAsJS() {
    const live = this.getLive();
    if (live.length === 0) return '';
    const lines = live.map(p =>
      `  { id:"${p.id}", name:"${p.name.replace(/"/g,'\\"')}", meta:"${p.meta.replace(/"/g,'\\"')}", price:${p.price}, cond:"${p.cond}", img:"${p.img}", category:"${p.category}" }`
    );
    return `// Paste these into DEFAULT_PRODUCTS in cart.js:\n${lines.join(',\n')},`;
  }
};

// ══════════════════════════════════════════
//  CUSTOMER DB (localStorage per-browser)
// ══════════════════════════════════════════
const Customers = {
  _url: null,

  setUrl(url) {
    this._url = url;
  },

  async isReturning(phone) {
    if (!this._url) return false;
    try {
      const clean = phone.replace(/\D/g, "");
      const res = await fetch(this._url + '?redirect=false&phone=' + encodeURIComponent(clean));
      const data = await res.json();
      return data.returning === true;
    } catch(e) {
      console.warn('Customer check failed, assuming first-time:', e);
      return false;
    }
  },

  add() { return Promise.resolve(); },
  getAll() { return []; }
};

// ══════════════════════════════════════════
//  PENDING ORDERS
// ══════════════════════════════════════════
const Orders = {
  _key: 'dee_pending_orders',

  getAll() { try { return JSON.parse(localStorage.getItem(this._key) || '[]'); } catch { return []; } },
  _save(o) { localStorage.setItem(this._key, JSON.stringify(o)); },

  create(data) {
    const list = this.getAll();
    const order = { id: 'ord-' + Date.now(), createdAt: new Date().toISOString(), status: 'pending', ...data };
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
  remove(id) {
    this._save(this.getAll().filter(x => x.id !== id));
  }
};

// ══════════════════════════════════════════
//  CART (qty capped at 1 — thrift items)
// ══════════════════════════════════════════
const Cart = {
  get()    { try { return JSON.parse(localStorage.getItem('dee_cart') || '[]'); } catch { return []; } },
  save(c)  { localStorage.setItem('dee_cart', JSON.stringify(c)); this.updateBadge(); },

  add(productId) {
    const product = findProduct(productId);
    if (!product) return;
    const cart = this.get();
    if (cart.find(i => i.id === productId)) {
      showToast('Already in cart — each item is unique ✦');
      return;
    }
    cart.push({ ...product, qty: 1 });
    this.save(cart);
    showToast(product.name + ' added to cart ✦');
  },
  remove(productId) {
    this.save(this.get().filter(i => i.id !== productId));
  },
  setQty(productId, qty) {
    // Cap at 1
    const cart = this.get();
    const item = cart.find(i => i.id === productId);
    if (item) item.qty = 1;
    this.save(cart);
  },
  total()  { return this.get().reduce((s, i) => s + i.price * i.qty, 0); },
  count()  { return this.get().length; },
  clear()  { localStorage.removeItem('dee_cart'); this.updateBadge(); },

  updateBadge() {
    document.querySelectorAll('.cart-badge').forEach(badge => {
      const n = this.count();
      badge.textContent = n;
      badge.style.display = n > 0 ? 'flex' : 'none';
    });
  }
};

// ══════════════════════════════════════════
//  TOAST
// ══════════════════════════════════════════
let _toastTimer;
function showToast(msg) {
  const el = document.getElementById('toastEl');
  if (!el) return;
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => el.classList.remove('show'), 1800);
}

// ══════════════════════════════════════════
//  CARD BUILDER
// ══════════════════════════════════════════
function buildCard(p) {
  const card = document.createElement('div');
  card.className = 'shop-card hover-tint';
  card.innerHTML = `
    <div style="position:relative;">
      <span class="shop-card-condition ${p.cond}">${p.cond}</span>
      <img class="shop-card-img" src="https://picsum.photos/seed/${p.img}/360/360.jpg" alt="${p.name}">
    </div>
    <div class="shop-card-body">
      <div class="shop-card-name">${p.name}</div>
      <div class="shop-card-meta">${p.meta}</div>
      <div class="shop-card-bottom">
        <div class="shop-card-price">$${p.price}</div>
        <button class="shop-card-add" onclick="event.stopPropagation(); Cart.add('${p.id}')">+</button>
      </div>
    </div>
  `;
  return card;
}

// ══════════════════════════════════════════
//  NAV ACTIVE STATE
// ══════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  Cart.updateBadge();
  const page = document.body.dataset.page;
  document.querySelectorAll('.nav-link[data-page]').forEach(a => {
    if (a.dataset.page === page) a.classList.add('active');
    else a.classList.remove('active');
  });
});
