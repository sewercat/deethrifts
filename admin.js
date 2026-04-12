var uploadedImageUrls = [];
var currentOrderFilter = '';
var donateCasesCache = [];
var editingCaseId = '';
var editingProductId = '';
var editingDraftIndex = -1;
var dbCurrentTable = '';
var dbCurrentPkCol = '';
var pendingImageUploads = 0;

function switchTab(name, btnEl) {
  document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
  document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });

  var panel = document.getElementById('tab-' + name);
  if (panel) panel.classList.add('active');

  var btn = btnEl || document.querySelector('.tab-btn[data-tab="' + name + '"]');
  if (btn) btn.classList.add('active');

  if (name === 'database' && !dbCurrentTable) loadDbTable();
  if (name === 'storage') renderStorage(true);
}
window.switchTab = switchTab;

document.addEventListener('DOMContentLoaded', function() {
  renderMeasurementInputs();
  renderSelectedImages();
  renderProducts();
  renderOrders('');
  renderCustomers();
  renderDrafts();
  renderDonateCases();
  renderStorage(true);
  bindAdminEvents();
  renderSettings();
  if (typeof window.startDeeAutoRefresh === 'function') {
    startDeeAutoRefresh({
      pollMs: 2500,
      refreshProducts: true,
      syncCart: false,
      onChange: async function() {
        await renderProducts(true);
        await renderOrders(currentOrderFilter);
        await renderCustomers();
        await renderDonateCases();
        await renderStorage(true);
        if (dbCurrentTable) loadDbTable();
      }
    });
  }
});

function bindAdminEvents() {
  document.getElementById('a-garment').addEventListener('change', renderMeasurementInputs);
  document.getElementById('a-img-files').addEventListener('change', handleImageUpload);
  document.getElementById('postNowBtn').addEventListener('click', postProduct);
  document.getElementById('saveDraftBtn').addEventListener('click', saveDraft);
  document.getElementById('cancelEditBtn').addEventListener('click', clearProductForm);
  document.getElementById('postAllDraftsBtn').addEventListener('click', postAllDrafts);
  document.getElementById('saveCaseBtn').addEventListener('click', submitDonateCase);
  document.getElementById('clearCaseBtn').addEventListener('click', clearDonateCaseForm);
  document.getElementById('dbLoadBtn').addEventListener('click', loadDbTable);
  document.getElementById('saveSettingsBtn').addEventListener('click', saveSettings);
  var storageRefreshBtn = document.getElementById('storageRefreshBtn');
  if (storageRefreshBtn) storageRefreshBtn.addEventListener('click', function() { renderStorage(); });
  document.addEventListener('paste', handlePasteImage);
}

function normalizeAdminGarmentType(value) {
  var key = String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
  if (key === 'shirts') return 'tops';
  if (key === 'misc_items') return 'misc';
  if (key === 'tops' || key === 'bottoms' || key === 'bags' || key === 'desi' || key === 'accessories' || key === 'dresses' || key === 'misc') return key;
  return 'accessories';
}

function renderSelectedImages() {
  var list = document.getElementById('selectedImageList');
  if (!list) return;
  var imgs = normalizeImageList(uploadedImageUrls);
  uploadedImageUrls = imgs;
  if (!imgs.length) {
    list.className = 'selected-image-empty';
    list.innerHTML = 'No images selected.';
    return;
  }
  list.className = 'selected-image-grid';
  list.innerHTML = imgs.map(function(url, i) {
    var imgHtml = getImageOrFallbackHtml(url, 'Selected image', '', '', 'no-image-fallback');
    return '<div class="selected-image-item">' + imgHtml + '<button class="selected-image-remove" type="button" onclick="removeSelectedImage(' + i + ')">×</button></div>';
  }).join('');
}

function removeSelectedImage(index) {
  if (index < 0 || index >= uploadedImageUrls.length) return;
  uploadedImageUrls.splice(index, 1);
  renderSelectedImages();
  document.getElementById('imgSelectionInfo').textContent = uploadedImageUrls.length + ' image(s) ready.';
}

function resetProductEditState() {
  editingProductId = '';
  editingDraftIndex = -1;
  var hint = document.getElementById('productEditingHint');
  var cancelBtn = document.getElementById('cancelEditBtn');
  var postBtn = document.getElementById('postNowBtn');
  var draftBtn = document.getElementById('saveDraftBtn');
  if (hint) {
    hint.style.display = 'none';
    hint.textContent = 'Editing posted product';
  }
  if (cancelBtn) cancelBtn.style.display = 'none';
  if (postBtn) postBtn.textContent = 'Post This Product';
  if (draftBtn) {
    draftBtn.style.display = 'inline-flex';
    draftBtn.textContent = 'Save as Draft';
  }
}

function parseMeasurementValue(meta, label) {
  var pattern = new RegExp(label + '\\s*:\\s*([0-9]+(?:\\.[0-9]+)?)', 'i');
  var match = String(meta || '').match(pattern);
  return match ? match[1] : '';
}

function isUpperBodyMeasurementType(type) {
  return type === 'tops' || type === 'dresses';
}

function fillMeasurementInputs(type, meta) {
  var text = String(meta || '');
  if (isUpperBodyMeasurementType(type)) {
    var shoulder = document.getElementById('m-shoulder');
    var chest = document.getElementById('m-chest');
    var waist = document.getElementById('m-waist');
    var length = document.getElementById('m-length');
    if (shoulder) shoulder.value = parseMeasurementValue(text, 'Shoulder');
    if (chest) chest.value = parseMeasurementValue(text, 'Chest');
    if (waist) waist.value = parseMeasurementValue(text, 'Waist');
    if (length) length.value = parseMeasurementValue(text, 'Length');
    return;
  }
  if (type === 'bottoms') {
    var waist2 = document.getElementById('m-waist');
    var length2 = document.getElementById('m-length');
    if (waist2) waist2.value = parseMeasurementValue(text, 'Waist');
    if (length2) length2.value = parseMeasurementValue(text, 'Length');
  }
}

function editProduct(productId) {
  var p = findProduct(productId);
  if (!p) { showToast('Product not found.'); return; }
  editingDraftIndex = -1;
  editingProductId = p.id;
  document.getElementById('a-name').value = p.name || '';
  document.getElementById('a-garment').value = normalizeAdminGarmentType(p.garmentType || p.category);
  document.getElementById('a-cond').value = normalizeCondition(p.cond || '10/10');
  document.getElementById('a-price').value = Number(p.price || 0);
  document.getElementById('a-defects').value = normalizeDefects(p.defects || '');
  document.getElementById('a-description').value = String(p.description || '').trim();
  document.getElementById('a-img-urls').value = '';
  uploadedImageUrls = normalizeImageList((p.images && p.images.length ? p.images : (p.img ? [p.img] : [])));
  renderSelectedImages();
  document.getElementById('imgSelectionInfo').textContent = uploadedImageUrls.length + ' image(s) ready.';
  renderMeasurementInputs();
  fillMeasurementInputs(normalizeAdminGarmentType(p.garmentType || p.category), p.meta || p.measurements || '');

  var hint = document.getElementById('productEditingHint');
  var cancelBtn = document.getElementById('cancelEditBtn');
  var postBtn = document.getElementById('postNowBtn');
  var draftBtn = document.getElementById('saveDraftBtn');
  if (hint) {
    hint.style.display = 'block';
    hint.textContent = 'Editing posted product';
  }
  if (cancelBtn) cancelBtn.style.display = 'inline-flex';
  if (postBtn) postBtn.textContent = 'Save Changes';
  if (draftBtn) draftBtn.style.display = 'none';

  switchTab('products');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function renderMeasurementInputs() {
  var type = normalizeAdminGarmentType(document.getElementById('a-garment').value);
  var box = document.getElementById('measurementBox');
  if (isUpperBodyMeasurementType(type)) {
    box.innerHTML = '<div class="form-row"><div class="form-group"><label class="form-label">Shoulder (in)</label><input class="glass-input" id="m-shoulder" type="number" min="1" step="0.1"></div><div class="form-group"><label class="form-label">Chest (in)</label><input class="glass-input" id="m-chest" type="number" min="1" step="0.1"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Waist (in)</label><input class="glass-input" id="m-waist" type="number" min="1" step="0.1"></div><div class="form-group"><label class="form-label">Length (in)</label><input class="glass-input" id="m-length" type="number" min="1" step="0.1"></div></div>';
  } else if (type === 'bottoms') {
    box.innerHTML = '<div class="form-row"><div class="form-group"><label class="form-label">Waist (in)</label><input class="glass-input" id="m-waist" type="number" min="1" step="0.1"></div><div class="form-group"><label class="form-label">Length (in)</label><input class="glass-input" id="m-length" type="number" min="1" step="0.1"></div></div>';
  } else {
    box.innerHTML = '<div class="form-group"><label class="form-label">Measurements</label><input class="glass-input" type="text" value="Not required for this category" disabled></div>';
  }
}

function collectMeasurements() {
  var type = normalizeAdminGarmentType(document.getElementById('a-garment').value);
  if (isUpperBodyMeasurementType(type)) {
    var s = document.getElementById('m-shoulder').value.trim();
    var c = document.getElementById('m-chest').value.trim();
    var w = document.getElementById('m-waist').value.trim();
    var l = document.getElementById('m-length').value.trim();
    if (!s || !c || !w || !l) return '';
    return 'Shoulder: ' + s + ' in, Chest: ' + c + ' in, Waist: ' + w + ' in, Length: ' + l + ' in';
  }
  if (type === 'bottoms') {
    var w2 = document.getElementById('m-waist').value.trim();
    var l2 = document.getElementById('m-length').value.trim();
    if (!w2 || !l2) return '';
    return 'Waist: ' + w2 + ' in, Length: ' + l2 + ' in';
  }
  return 'None';
}

async function uploadSingleFile(file) {
  var form = new FormData();
  form.append('image', file);
  form.append('type', 'upload_image');
  var res = await fetch('api.php', { method: 'POST', body: form });
  if (!res.ok) throw new Error('Upload failed');
  return res.json();
}

async function compressProductImage(file, maxFileSizeMB) {
  maxFileSizeMB = maxFileSizeMB || 2;
  if (!file || !file.type.startsWith('image/')) return file;
  if (file.size <= maxFileSizeMB * 1024 * 1024 * 0.8) return file;
  return new Promise(function(resolve) {
    var reader = new FileReader();
    reader.onload = function(ev) {
      var img = new Image();
      img.onload = function() {
        var canvas = document.createElement('canvas');
        var width = img.width;
        var height = img.height;
        var maxWidth = 1280;
        var maxHeight = 1280;
        if (width > height) {
          if (width > maxWidth) {
            height = Math.round((height * maxWidth) / width);
            width = maxWidth;
          }
        } else if (height > maxHeight) {
          width = Math.round((width * maxHeight) / height);
          height = maxHeight;
        }
        canvas.width = width;
        canvas.height = height;
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, width, height);
        ctx.drawImage(img, 0, 0, width, height);
        (function tryCompress(q) {
          canvas.toBlob(function(blob) {
            if (!blob) { resolve(file); return; }
            if (blob.size <= maxFileSizeMB * 1024 * 1024 || q <= 0.1) {
              resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
            } else {
              tryCompress(Math.max(0.1, q - 0.1));
            }
          }, 'image/jpeg', q);
        })(0.85);
      };
      img.onerror = function() { resolve(file); };
      img.src = ev.target.result;
    };
    reader.onerror = function() { resolve(file); };
    reader.readAsDataURL(file);
  });
}

async function handleImageUpload(e) {
  var files = Array.from(e.target.files || []);
  if (!files.length) return;
  if (uploadedImageUrls.length + files.length > 5) { showToast('Max 5 images.'); e.target.value = ''; return; }
  if (files.find(function(f) { return f.size > 50 * 1024 * 1024; })) { showToast('Each image max 50MB.'); e.target.value = ''; return; }
  pendingImageUploads++;
  try {
    for (var i = 0; i < files.length; i++) {
      try {
        showToast('Processing image ' + (i + 1) + '/' + files.length + '...');
        var compressedFile = await compressProductImage(files[i], 2);
        var json = await uploadSingleFile(compressedFile);
        if (json.ok && json.url) uploadedImageUrls.push(json.url);
        else showToast('Upload failed.');
      } catch (err) { showToast('Upload error.'); }
    }
  } finally {
    pendingImageUploads = Math.max(0, pendingImageUploads - 1);
  }
  e.target.value = '';
  renderSelectedImages();
  document.getElementById('imgSelectionInfo').textContent = uploadedImageUrls.length + ' image(s) ready.';
}

async function handlePasteImage(e) {
  var items = (e.clipboardData || window.clipboardData || {}).items;
  if (!items) return;
  pendingImageUploads++;
  try {
    for (var i = 0; i < items.length; i++) {
      var item = items[i];
      if (!item.type || item.type.indexOf('image') === -1) continue;
      var file = item.getAsFile();
      if (!file) continue;
      if (uploadedImageUrls.length >= 5) { showToast('Max 5 images.'); return; }
      if (file.size > 50 * 1024 * 1024) { showToast('Pasted image too large (max 50MB).'); continue; }
      try {
        var compressedFile = await compressProductImage(file, 2);
        var json = await uploadSingleFile(compressedFile);
        if (json.ok && json.url) uploadedImageUrls.push(json.url);
      } catch (err) { showToast('Paste error.'); }
    }
  } finally {
    pendingImageUploads = Math.max(0, pendingImageUploads - 1);
  }
  renderSelectedImages();
  document.getElementById('imgSelectionInfo').textContent = uploadedImageUrls.length + ' image(s) ready.';
}

function clearProductForm() {
  resetProductEditState();
  document.getElementById('a-name').value = '';
  document.getElementById('a-price').value = '';
  document.getElementById('a-img-urls').value = '';
  document.getElementById('a-img-files').value = '';
  document.getElementById('a-defects').value = 'No known defects';
  document.getElementById('a-description').value = '';
  document.getElementById('a-cond').value = '10/10';
  uploadedImageUrls = [];
  renderSelectedImages();
  document.getElementById('imgSelectionInfo').textContent = 'Upload up to 5 images (max 50MB each). Images are auto-compressed.';
  renderMeasurementInputs();
}

function parseManualImageUrls(rawInput) {
  var raw = String(rawInput || '').trim();
  if (!raw) return [];
  return raw.split(/[\r\n,]+/).map(function(v) { return v.trim(); }).filter(Boolean);
}

function normalizeImageList(urls) {
  var src = Array.isArray(urls) ? urls : [];
  var out = [];
  src.forEach(function(v) {
    var s = String(v || '').trim();
    if (!s) return;
    if (out.indexOf(s) === -1) out.push(s);
  });
  return out.slice(0, 5);
}

function buildProductPayload(d) {
  var imgs = normalizeImageList((d && d.imageUrls) || (d && d.images) || []);
  var primary = imgs[0] || '';
  var garmentType = normalizeAdminGarmentType((d && d.garmentType) || '');
  return {
    productId: (d && d.productId) || ('PRD-' + Date.now() + '-' + Math.random().toString(36).slice(2, 6).toUpperCase()),
    name: d.name,
    garmentType: garmentType,
    category: garmentType,
    tags: ['new'],
    cond: d.cond,
    defects: d.defects || 'No known defects',
    description: String((d && d.description) || '').trim(),
    price: d.price,
    measurements: d.measurements,
    imageUrls: imgs,
    images: imgs,
    imageUrl: primary,
    img: primary
  };
}

function collectDraftData() {
  var urlInput = document.getElementById('a-img-urls').value.trim();
  var extraUrls = parseManualImageUrls(urlInput);
  var defects = document.getElementById('a-defects').value.trim();
  return {
    productId: editingProductId || '',
    name: document.getElementById('a-name').value.trim(),
    garmentType: normalizeAdminGarmentType(document.getElementById('a-garment').value),
    cond: document.getElementById('a-cond').value,
    defects: defects || 'No known defects',
    description: document.getElementById('a-description').value.trim(),
    price: Number(document.getElementById('a-price').value || 0),
    measurements: collectMeasurements(),
    imageUrls: normalizeImageList(uploadedImageUrls.concat(extraUrls))
  };
}

async function postProduct() {
  var wasEditingProduct = !!editingProductId;
  var wasEditingDraft = editingDraftIndex >= 0;
  var d = collectDraftData();
  if (pendingImageUploads > 0) { showToast('Please wait for image uploads to finish.'); return; }
  if (!d.name) { showToast('Enter a product name.'); return; }
  if (!d.price) { showToast('Enter a price.'); return; }
  if ((isUpperBodyMeasurementType(d.garmentType) || d.garmentType === 'bottoms') && !d.measurements) { showToast('Fill measurements.'); return; }
  var btn = document.getElementById('postNowBtn');
  btn.textContent = editingProductId ? 'Saving...' : 'Posting...';
  btn.disabled = true;
  try {
    var res = await window.DEE_API.postJson({ type: 'add_product', product: buildProductPayload(d) });
    if (res.ok) {
      if (wasEditingDraft) {
        var existingDrafts = Drafts.get();
        if (editingDraftIndex >= 0 && editingDraftIndex < existingDrafts.length) {
          existingDrafts.splice(editingDraftIndex, 1);
          Drafts.save(existingDrafts);
        }
      }
      showToast(wasEditingProduct ? 'Product updated.' : (wasEditingDraft ? 'Draft posted!' : 'Posted!'));
      clearProductForm();
      await renderProducts();
      renderDrafts();
    }
    else showToast('Failed.');
  } catch (e) { showToast(e.message || 'Connection error.'); }
  btn.textContent = editingProductId ? 'Save Changes' : 'Post This Product';
  btn.disabled = false;
}

function saveDraft() {
  if (editingProductId) { showToast('Finish editing or cancel edit before saving a draft.'); return; }
  var d = collectDraftData();
  if (pendingImageUploads > 0) { showToast('Please wait for image uploads to finish.'); return; }
  if (!d.name) { showToast('Enter a name first.'); return; }
  if (!d.price) { showToast('Enter a price first.'); return; }
  if ((isUpperBodyMeasurementType(d.garmentType) || d.garmentType === 'bottoms') && !d.measurements) { showToast('Fill measurements.'); return; }

  var drafts = Drafts.get();
  if (editingDraftIndex >= 0 && editingDraftIndex < drafts.length) {
    drafts[editingDraftIndex] = d;
    Drafts.save(drafts);
    showToast('Draft updated!');
  } else {
    Drafts.add(d);
    showToast('Draft saved!');
  }
  clearProductForm();
  renderDrafts();
}

function renderDrafts() {
  var drafts = Drafts.get();
  document.getElementById('draftCount').textContent = drafts.length;
  document.getElementById('postDraftsWrap').style.display = drafts.length ? 'block' : 'none';
  var el = document.getElementById('draftList');
  if (!drafts.length) { el.innerHTML = '<p style="font-size:0.85em; color:rgba(255,255,255,0.3);">No drafts.</p>'; return; }
  el.innerHTML = drafts.map(function(d, i) {
    var draftType = normalizeAdminGarmentType(d.garmentType || d.category);
    var measureText = String(d.measurements || d.meta || '').trim();
    return '<div class="admin-item-row"><div class="admin-item-info"><div class="admin-item-name">' + escHtml(d.name) + '</div><div class="admin-item-meta">' + escHtml(draftType) + ' | ' + escHtml(d.cond) + ' | ' + formatPkr(d.price) + '</div><div class="admin-item-meta">Defects: ' + escHtml(d.defects || 'No known defects') + '</div>' + (measureText ? '<div class="admin-item-meta">Measurements: ' + escHtml(measureText) + '</div>' : '') + '</div><button class="admin-wa-btn" onclick="editDraft(' + i + ')">edit</button><button class="admin-del-btn" onclick="deleteDraft(' + i + ')">remove</button></div>';
  }).join('');
}

function editDraft(idx) {
  var drafts = Drafts.get();
  if (idx < 0 || idx >= drafts.length) { showToast('Draft not found.'); return; }
  var d = drafts[idx] || {};

  editingProductId = '';
  editingDraftIndex = idx;
  document.getElementById('a-name').value = String(d.name || '').trim();
  document.getElementById('a-garment').value = normalizeAdminGarmentType(d.garmentType || d.category || 'accessories');
  document.getElementById('a-cond').value = normalizeCondition(d.cond || '10/10');
  document.getElementById('a-price').value = Number(d.price || 0);
  document.getElementById('a-defects').value = normalizeDefects(d.defects || '');
  document.getElementById('a-description').value = String(d.description || '').trim();
  document.getElementById('a-img-urls').value = '';
  uploadedImageUrls = normalizeImageList((d.imageUrls && d.imageUrls.length ? d.imageUrls : (d.images && d.images.length ? d.images : (d.imageUrl ? [d.imageUrl] : []))));
  renderSelectedImages();
  document.getElementById('imgSelectionInfo').textContent = uploadedImageUrls.length + ' image(s) ready.';
  renderMeasurementInputs();
  fillMeasurementInputs(normalizeAdminGarmentType(d.garmentType || d.category), d.measurements || d.meta || '');

  var hint = document.getElementById('productEditingHint');
  var cancelBtn = document.getElementById('cancelEditBtn');
  var postBtn = document.getElementById('postNowBtn');
  var draftBtn = document.getElementById('saveDraftBtn');
  if (hint) {
    hint.style.display = 'block';
    hint.textContent = 'Editing draft';
  }
  if (cancelBtn) cancelBtn.style.display = 'inline-flex';
  if (postBtn) postBtn.textContent = 'Post This Product';
  if (draftBtn) {
    draftBtn.style.display = 'inline-flex';
    draftBtn.textContent = 'Update Draft';
  }

  switchTab('products');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function deleteDraft(idx) {
  var wasEditingThis = editingDraftIndex === idx;
  Drafts.remove(idx);
  if (editingDraftIndex > idx) editingDraftIndex -= 1;
  if (wasEditingThis) clearProductForm();
  renderDrafts();
  showToast('Draft removed.');
}

async function postAllDrafts() {
  if (pendingImageUploads > 0) { showToast('Please wait for image uploads to finish.'); return; }
  var drafts = Drafts.get(); if (!drafts.length) return;
  var btn = document.getElementById('postAllDraftsBtn');
  btn.textContent = 'Posting...'; btn.disabled = true;
  var failed = 0;
  for (var i = 0; i < drafts.length; i++) {
    var d = drafts[i];
    try {
      var r = await window.DEE_API.postJson({ type: 'add_product', product: buildProductPayload(d) });
      if (!r.ok) failed++;
    } catch (e) { failed++; }
  }
  if (!failed) { Drafts.clear(); showToast('All drafts posted!'); } else showToast(failed + ' draft(s) failed.');
  btn.textContent = 'Post All Drafts'; btn.disabled = false; renderDrafts(); await renderProducts();
}

async function renderProducts(skipRefresh) {
  if (!skipRefresh) await Products.refresh();
  var products = getAllProducts();
  document.getElementById('liveCount').textContent = products.length;
  var el = document.getElementById('liveList');
  if (!products.length) {
    el.innerHTML = '<p style="font-size:0.85em; color:rgba(255,255,255,0.3);">No products yet.</p>';
    renderCategoryCovers();
    return;
  }
  el.innerHTML = products.map(function(p) {
    var img = (p.images && p.images[0]) || p.img;
    var imgHtml = getImageOrFallbackHtml(img, p.name || 'Product', '', 'width:48px;height:48px;object-fit:cover;border-radius:6px;display:block;', 'no-image-fallback', 'width:48px;height:48px;border-radius:6px;display:flex;font-size:0.58em;padding:4px;');
    var rawStatus = String(p.status || 'available').toLowerCase();
    var status = rawStatus === 'confirmation_pending'
      ? 'checkout locked'
      : (productStatusLabel(rawStatus) || 'available');
    var cats = (p.categories || [p.category]).join(', ');
    var desc = String(p.description || '').trim();
    var descHtml = desc ? '<div class="admin-item-meta">Description: ' + escHtml(desc) + '</div>' : '';
    return '<div class="admin-item-row">' + imgHtml + '<div class="admin-item-info"><div class="admin-item-name">' + escHtml(p.name) + '</div><div class="admin-item-meta">' + escHtml(p.meta || '-') + ' | ' + escHtml(cats) + ' | ' + escHtml(status) + '</div><div class="admin-item-meta">Condition: ' + escHtml(p.cond) + ' | Defects: ' + escHtml(p.defects || 'No known defects') + '</div>' + descHtml + '</div><div class="admin-item-price">' + formatPkr(p.price) + '</div><button class="admin-wa-btn" onclick="editProduct(\'' + escAttr(p.id) + '\')">edit</button><button class="admin-feat-btn' + (p.is_featured ? ' featured' : '') + '" onclick="toggleFeatured(\'' + escAttr(p.id) + '\')">' + (p.is_featured ? 'on' : 'off') + '</button><button class="admin-del-btn" onclick="deleteProduct(\'' + escAttr(p.id) + '\')">remove</button></div>';
  }).join('');
  renderCategoryCovers();
}

function renderCategoryCovers() {
  var container = document.getElementById('categoryCoverList');
  if (!container) return;
  var categories = getCategories();
  var products = getAllProducts();
  var coverMap = (typeof CategoryCovers !== 'undefined' && CategoryCovers.getMap) ? CategoryCovers.getMap() : {};

  if (!categories.length) {
    container.innerHTML = '<p style="font-size:0.85em; color:rgba(255,255,255,0.3);">No categories yet.</p>';
    return;
  }

  container.innerHTML = categories.map(function(cat) {
    var categoryProducts = products.filter(function(p) {
      return (p.categories || [p.category]).indexOf(cat.key) !== -1;
    });
    var selectedPid = coverMap[cat.key] || '';
    var options = '<option value="">Auto pick</option>' + categoryProducts.map(function(p) {
      var sel = p.id === selectedPid ? ' selected' : '';
      return '<option value="' + escAttr(p.id) + '"' + sel + '>' + escHtml(p.name) + '</option>';
    }).join('');
    return '<div class="admin-item-row" style="align-items:center; gap:10px;"><div class="admin-item-info"><div class="admin-item-name">' + escHtml(cat.label) + '</div><div class="admin-item-meta">' + categoryProducts.length + ' product(s)</div></div><select class="glass-input" id="coverSel-' + cat.key + '" style="max-width:240px;">' + options + '</select><button class="admin-wa-btn" type="button" onclick="saveCategoryCover(\'' + escAttr(cat.key) + '\')">Save</button></div>';
  }).join('');
}

async function saveCategoryCover(categoryKey) {
  var select = document.getElementById('coverSel-' + categoryKey);
  if (!select) return;
  var productId = select.value || '';
  try {
    var res = await window.DEE_API.postJson({ type: 'set_category_cover', category: categoryKey, productId: productId });
    if (res.ok) {
      if (typeof CategoryCovers !== 'undefined' && CategoryCovers.setOne) CategoryCovers.setOne(categoryKey, productId);
      showToast('Category cover saved.');
      renderCategoryCovers();
    } else {
      showToast('Failed to save cover.');
    }
  } catch (e) {
    showToast(e.message || 'Failed to save cover.');
  }
}

async function deleteProduct(pid) {
  if (!pid || !confirm('Delete this product and its images?')) return;
  try {
    var r = await window.DEE_API.postJson({ type: 'delete_product', productId: pid });
    if (r.ok) { showToast('Deleted.'); await renderProducts(); } else showToast('Failed.');
  } catch (e) { showToast(e.message || 'Connection error.'); }
}

async function toggleFeatured(pid) {
  try {
    var r = await window.DEE_API.postJson({ type: 'toggle_featured', productId: pid });
    if (r.ok) await renderProducts();
  } catch (e) { showToast('Error.'); }
}

function formatStorageBytes(bytes) {
  var b = Number(bytes || 0);
  if (!Number.isFinite(b) || b < 0) b = 0;
  var units = ['B', 'KB', 'MB', 'GB', 'TB'];
  var i = 0;
  while (b >= 1024 && i < units.length - 1) {
    b = b / 1024;
    i++;
  }
  var decimals = i === 0 ? 0 : (b >= 100 ? 0 : (b >= 10 ? 1 : 2));
  return b.toFixed(decimals) + ' ' + units[i];
}

function formatStorageTime(ts) {
  var n = Number(ts || 0);
  if (!Number.isFinite(n) || n <= 0) return '-';
  try {
    return new Date(n * 1000).toLocaleString();
  } catch (_) {
    return '-';
  }
}

function renderStorageFileGroup(containerId, files, emptyText) {
  var el = document.getElementById(containerId);
  if (!el) return;
  var list = Array.isArray(files) ? files : [];
  if (!list.length) {
    el.innerHTML = '<p style="font-size:0.82em; color:rgba(255,255,255,0.35);">' + escHtml(emptyText || 'No files.') + '</p>';
    return;
  }

  el.innerHTML = list.map(function(f) {
    var url = String(f.url || f.path || '').trim();
    var name = String(f.name || '').trim() || 'file';
    var path = String(f.path || url || '').trim();
    var size = formatStorageBytes(f.size || 0);
    var modified = formatStorageTime(f.modifiedAt || 0);
    var refTag = (f.referenced === false)
      ? '<span style="color:rgba(255,180,130,0.85);">orphan</span>'
      : '<span style="color:rgba(100,220,140,0.8);">linked</span>';
    var preview = getImageOrFallbackHtml(
      url,
      name,
      '',
      'width:52px;height:52px;object-fit:cover;border-radius:6px;display:block;',
      'no-image-fallback',
      'width:52px;height:52px;border-radius:6px;display:flex;font-size:0.58em;padding:4px;'
    );
    var openBtn = url ? '<a class="admin-wa-btn" href="' + escHtml(url) + '" target="_blank" rel="noopener" style="text-decoration:none;">view</a>' : '';
    return '<div class="admin-item-row" style="align-items:center; gap:10px;">' +
      preview +
      '<div class="admin-item-info" style="min-width:220px;">' +
        '<div class="admin-item-name">' + escHtml(name) + '</div>' +
        '<div class="admin-item-meta">' + escHtml(path) + '</div>' +
        '<div class="admin-item-meta">' + escHtml(size) + ' | ' + escHtml(modified) + ' | ' + refTag + '</div>' +
      '</div>' +
      '<div style="display:flex; flex-direction:column; gap:6px;">' +
        openBtn +
        '<button class="admin-del-btn" type="button" onclick="deleteStorageFile(\'' + escAttr(path) + '\')">delete</button>' +
      '</div>' +
    '</div>';
  }).join('');
}

async function renderStorage(silent) {
  var usageTextEl = document.getElementById('storageUsageText');
  var fillEl = document.getElementById('storageUsageFill');
  var usedEl = document.getElementById('storageUsedText');
  var freeEl = document.getElementById('storageFreeText');
  var pCountEl = document.getElementById('storageProductsCount');
  var prCountEl = document.getElementById('storageProofsCount');
  if (!usageTextEl || !fillEl || !usedEl || !freeEl) return;

  if (!silent) {
    usageTextEl.textContent = 'Loading storage info...';
  }

  try {
    var res = await window.DEE_API.getJson({ action: 'storage_files' });
    var storage = res && res.storage ? res.storage : {};
    var capacity = Number(storage.capacityBytes || 0);
    var used = Number(storage.usedBytes || 0);
    var free = Number(storage.freeBytes || Math.max(capacity - used, 0));
    var percent = Number(storage.usagePercent || 0);
    if (!Number.isFinite(percent) || percent < 0) percent = 0;
    if (percent > 100) percent = 100;

    usageTextEl.textContent = 'Using ' + formatStorageBytes(used) + ' of ' + formatStorageBytes(capacity) + ' (' + percent.toFixed(2) + '%).';
    fillEl.style.width = percent + '%';
    usedEl.textContent = 'Used: ' + formatStorageBytes(used);
    freeEl.textContent = 'Left: ' + formatStorageBytes(free);

    var files = Array.isArray(storage.files) ? storage.files : [];
    var productFiles = files.filter(function(f) { return String((f && f.category) || 'products') !== 'proofs'; });
    var proofFiles = files.filter(function(f) { return String((f && f.category) || '') === 'proofs'; });

    if (pCountEl) pCountEl.textContent = productFiles.length;
    if (prCountEl) prCountEl.textContent = proofFiles.length;

    renderStorageFileGroup('storageProductsList', productFiles, 'No product images found.');
    renderStorageFileGroup('storageProofsList', proofFiles, 'No proof images found.');
  } catch (e) {
    usageTextEl.textContent = 'Failed to load storage info.';
    fillEl.style.width = '0%';
    usedEl.textContent = 'Used: -';
    freeEl.textContent = 'Left: -';
    if (pCountEl) pCountEl.textContent = '0';
    if (prCountEl) prCountEl.textContent = '0';
    var msg = escHtml((e && e.message) ? e.message : 'Could not load storage data.');
    var fallback = '<p style="font-size:0.82em; color:rgba(255,120,120,0.75);">' + msg + '</p>';
    var pList = document.getElementById('storageProductsList');
    var prList = document.getElementById('storageProofsList');
    if (pList) pList.innerHTML = fallback;
    if (prList) prList.innerHTML = fallback;
  }
}
window.renderStorage = renderStorage;

async function deleteStorageFile(filePath) {
  var p = String(filePath || '').trim();
  if (!p) return;
  if (!confirm('Delete this file from storage?')) return;
  try {
    var res = await window.DEE_API.postJson({ type: 'delete_storage_file', file: p });
    if (res && res.ok) {
      showToast('File deleted.');
      await renderStorage(true);
      await renderProducts(true);
      await renderOrders(currentOrderFilter);
    } else {
      showToast((res && res.error) ? String(res.error) : 'Delete failed.');
    }
  } catch (e) {
    showToast((e && e.message) ? e.message : 'Delete failed.');
  }
}
window.deleteStorageFile = deleteStorageFile;

async function renderOrders(status) {
  currentOrderFilter = status;
  var orders = await Orders.getOrdersRemote(status);
  document.getElementById('orderCount').textContent = orders.length;
  var el = document.getElementById('orderList');
  if (!orders.length) { el.innerHTML = '<p style="font-size:0.85em; color:rgba(255,255,255,0.3);">No orders.</p>'; return; }
  el.innerHTML = orders.map(function(o) {
    var oid = o.id || '';
    var s = String(o.status || 'pending');
    var sl = s.toLowerCase();
    var pnames = (o.productNames || []).join(', ') || 'No products';
    var isAwaitingProof = sl === 'awaiting_payment_proof';
    var isPending = sl === 'pending';
    var isPendingPayment = sl === 'pending_payment';
    var isConfirmed = sl === 'confirmed';
    var color = isAwaitingProof ? 'rgba(255,176,204,0.9)' : isPending ? 'rgba(255,200,100,0.8)' : isPendingPayment ? 'rgba(150,150,255,0.8)' : isConfirmed ? 'rgba(100,220,150,0.8)' : 'rgba(255,255,255,0.4)';
    var awaitingNote = isAwaitingProof ? '<div class="admin-item-meta" style="color:rgba(255,176,204,0.85);">Waiting for customer payment proof.</div>' : '';
    var proofUrl = String(o.screenshot || '').trim();
    var proofBtn = proofUrl ? '<button class="admin-wa-btn" onclick="viewPaymentProof(\'' + escAttr(proofUrl) + '\')">Proof</button>' : '';
    var rebookBtn = isConfirmed ? '<button class="admin-wa-btn" onclick="rebookDelivery(\'' + escAttr(oid) + '\')">Rebook</button>' : '';
    return '<div class="admin-item-row" style="flex-wrap:wrap; gap:8px; padding:12px 0;"><div class="admin-item-info" style="min-width:200px;"><div class="admin-item-name" style="font-size:0.82em;">' + escHtml(oid) + '</div><div class="admin-item-name">' + escHtml(o.name || 'Unknown') + '</div><div class="admin-item-meta">' + escHtml(o.phone || '-') + (o.phone2 ? ' / ' + escHtml(o.phone2) : '') + '</div><div class="admin-item-meta">' + escHtml(o.instagram || '-') + ' | ' + escHtml(o.city || '-') + '</div><div class="admin-item-meta">' + escHtml(pnames) + '</div><div class="admin-item-meta">' + escHtml(o.payment_mode || '-') + ' | <span style="color:' + color + '">' + escHtml(s) + '</span></div>' + awaitingNote + (o.notes ? '<div class="admin-item-meta" style="color:rgba(255,200,100,0.6);">Notes: ' + escHtml(o.notes) + '</div>' : '') + '</div><div class="admin-item-price">' + formatPkr(o.total) + '</div><div style="display:flex; flex-direction:column; gap:6px;">' + proofBtn + (isPending ? '<button class="admin-wa-btn" onclick="updateOrder(\'' + escAttr(oid) + '\',\'Confirmed\')">Confirm</button>' : '') + (isPendingPayment ? '<button class="admin-wa-btn" onclick="updateOrder(\'' + escAttr(oid) + '\',\'Confirmed\')">Confirm Payment</button>' : '') + rebookBtn + (isConfirmed ? '<button class="admin-wa-btn" onclick="updateOrder(\'' + escAttr(oid) + '\',\'Delivered\')">Delivered</button>' : '') + ((isAwaitingProof || isPending || isPendingPayment) ? '<button class="admin-del-btn" onclick="updateOrder(\'' + escAttr(oid) + '\',\'Cancelled\')">Cancel</button>' : '') + (isConfirmed ? '<button class="admin-del-btn" onclick="updateOrder(\'' + escAttr(oid) + '\',\'Returned\')">Returned</button>' : '') + '</div></div>';
  }).join('');
}

function filterOrders(status) { renderOrders(status); }

async function updateOrder(orderId, status) {
  if (!orderId) return;
  if (status === 'Delivered' && !confirm('Mark delivered? Products and images will be deleted permanently.')) return;
  try {
    var r = await Orders.setStatusRemote(orderId, status);
    if (r.ok) {
      showToast('Order: ' + status);
      if (status === 'Confirmed' && r.delivery) {
        if (r.delivery.ok) {
          var did = String(r.delivery.parcelId || '').trim();
          showToast(did ? ('Delivery booking created (' + did + ').') : 'Delivery booking created.');
        } else {
          showToast('Order confirmed, but delivery booking failed. ' + String(r.delivery.message || ''));
        }
      }
      await renderProducts();
      await renderOrders(currentOrderFilter);
      await renderCustomers();
    }
    else showToast('Failed.');
  } catch (e) { showToast('Connection error.'); }
}

function viewPaymentProof(url) {
  var u = String(url || '').trim();
  if (!u) { showToast('No proof image available.'); return; }
  window.open(u, '_blank', 'noopener');
}

async function rebookDelivery(orderId) {
  if (!orderId) return;
  if (!confirm('Send this confirmed order to the delivery portal again?')) return;
  try {
    var r = await window.DEE_API.postJson({ type: 'rebook_delivery', orderId: orderId });
    if (r && r.ok) {
      var did = r.delivery ? String(r.delivery.parcelId || '').trim() : '';
      showToast(did ? ('Delivery rebooked (' + did + ').') : 'Delivery rebooked.');
    } else {
      showToast((r && r.error) ? String(r.error) : 'Rebook failed.');
    }
    await renderOrders(currentOrderFilter);
  } catch (e) {
    showToast(e.message || 'Rebook failed.');
  }
}

async function renderCustomers() {
  try {
    var d = await window.DEE_API.getJson({ action: 'customers' });
    var customers = d.customers || [];
    document.getElementById('customerCount').textContent = customers.length;
    var el = document.getElementById('customerList');
    if (!customers.length) { el.innerHTML = '<p style="font-size:0.85em; color:rgba(255,255,255,0.3);">No customers yet.</p>'; return; }
    el.innerHTML = '<div class="customers-grid"><div class="cust-header"><span>Phone</span><span>Phone 2</span><span>Name</span><span>City</span><span>Orders</span><span>Status</span><span>COD</span></div>' + customers.map(function(c) {
      var sc = c.latest_order_status === 'returned' ? 'status-returned' : c.latest_order_status === 'delivered' ? 'status-delivered' : '';
      var cod = c.cod_blocked == 1 ? '<span style="color:rgba(255,100,100,0.8);">blocked</span>' : '<span style="color:rgba(100,220,100,0.7);">ok</span>';
      return '<div class="cust-row"><span>' + escHtml(c.phone || '-') + '</span><span>' + escHtml(c.phone2 || '-') + '</span><span>' + escHtml(c.name || '-') + '</span><span>' + escHtml(c.city || '-') + '</span><span>' + escHtml(c.order_count || 0) + '</span><span class="' + sc + '">' + escHtml(c.latest_order_status || 'none') + '</span><span>' + cod + '</span></div>';
    }).join('') + '</div>';
  } catch (e) { document.getElementById('customerList').innerHTML = '<p style="font-size:0.85em; color:rgba(255,100,100,0.5);">Failed to load.</p>'; }
}

async function renderDonateCases() {
  try {
    var data = await window.DEE_API.getJson({ action: 'donate_cases' });
    donateCasesCache = data.cases || [];
    document.getElementById('donateCaseCount').textContent = donateCasesCache.length;
    var el = document.getElementById('donateCaseList');
    if (!donateCasesCache.length) { el.innerHTML = '<p style="font-size:0.85em; color:rgba(255,255,255,0.3);">No donate cases yet.</p>'; return; }
    el.innerHTML = donateCasesCache.map(function(c) {
      return '<div class="admin-item-row" style="align-items:flex-start;">' + (c.image_url ? '<img src="' + escHtml(c.image_url) + '" alt="" style="width:64px;height:64px;">' : '<div style="width:64px;"></div>') + '<div class="admin-item-info"><div class="admin-item-name">' + escHtml(c.title || '') + '</div><div class="admin-item-meta">' + escHtml(c.link_label || 'More Info') + (c.link_url ? ' | ' + escHtml(c.link_url) : '') + '</div><div class="admin-item-meta">' + escHtml((c.description || '').slice(0, 180)) + ((c.description || '').length > 180 ? '...' : '') + '</div></div><button class="admin-wa-btn" onclick="editDonateCase(\'' + escAttr(c.id) + '\')">edit</button><button class="admin-del-btn" onclick="deleteDonateCase(\'' + escAttr(c.id) + '\')">remove</button></div>';
    }).join('');
  } catch (e) { document.getElementById('donateCaseList').innerHTML = '<p style="font-size:0.85em; color:rgba(255,100,100,0.5);">Failed to load cases.</p>'; }
}

function clearDonateCaseForm() {
  editingCaseId = '';
  document.getElementById('dc-title').value = '';
  document.getElementById('dc-label').value = 'More Info';
  document.getElementById('dc-img').value = '';
  document.getElementById('dc-link').value = '';
  document.getElementById('dc-desc').value = '';
  document.getElementById('saveCaseBtn').textContent = 'Add Case';
  document.getElementById('donateEditingHint').style.display = 'none';
}

function editDonateCase(id) {
  var c = donateCasesCache.find(function(item) { return item.id === id; });
  if (!c) { showToast('Case not found.'); return; }
  editingCaseId = c.id;
  document.getElementById('dc-title').value = c.title || '';
  document.getElementById('dc-label').value = c.link_label || 'More Info';
  document.getElementById('dc-img').value = c.image_url || '';
  document.getElementById('dc-link').value = c.link_url || '';
  document.getElementById('dc-desc').value = c.description || '';
  document.getElementById('saveCaseBtn').textContent = 'Save Changes';
  document.getElementById('donateEditingHint').style.display = 'block';
}

async function submitDonateCase() {
  var title = document.getElementById('dc-title').value.trim();
  var desc = document.getElementById('dc-desc').value.trim();
  var img = document.getElementById('dc-img').value.trim();
  var link = document.getElementById('dc-link').value.trim();
  var label = document.getElementById('dc-label').value.trim() || 'More Info';
  if (!title) { showToast('Enter a title.'); return; }
  var btn = document.getElementById('saveCaseBtn');
  var t = btn.textContent; btn.textContent = 'Saving...'; btn.disabled = true;
  try {
    var payload = { case: { title: title, description: desc, image_url: img, link_url: link, link_label: label } };
    var res;
    if (editingCaseId) { payload.type = 'edit_case'; payload.caseId = editingCaseId; res = await window.DEE_API.postJson(payload); }
    else { payload.type = 'add_case'; res = await window.DEE_API.postJson(payload); }
    if (res.ok) { showToast(editingCaseId ? 'Case updated.' : 'Case added.'); clearDonateCaseForm(); await renderDonateCases(); }
    else showToast('Failed.');
  } catch (e) { showToast(e.message || 'Error.'); }
  btn.textContent = t; btn.disabled = false;
}

async function deleteDonateCase(id) {
  if (!confirm('Remove this case?')) return;
  try {
    var res = await window.DEE_API.postJson({ type: 'delete_case', caseId: id });
    if (res.ok) { showToast('Removed.'); if (editingCaseId === id) clearDonateCaseForm(); await renderDonateCases(); }
    else showToast('Failed.');
  } catch (e) { showToast(e.message || 'Error.'); }
}

async function loadDbTable() {
  var table = document.getElementById('dbTableSelect').value;
  dbCurrentTable = table;
  var btn = document.getElementById('dbLoadBtn');
  btn.textContent = 'Loading...'; btn.disabled = true;
  try { var data = await window.DEE_API.getJson({ action: 'db_table', table: table }); renderDbTable(data.rows || [], table); }
  catch (e) { showToast('Failed to load table.'); }
  btn.textContent = 'Load Table'; btn.disabled = false;
}

function renderDbTable(rows, table) {
  var container = document.getElementById('dbTableContainer');
  if (!rows.length) { container.innerHTML = '<p style="font-size:0.85em; color:rgba(255,255,255,0.3); margin-top:10px;">No rows.</p>'; document.getElementById('dbRowCount').textContent = ''; return; }
  var cols = Object.keys(rows[0]); dbCurrentPkCol = cols[0]; document.getElementById('dbRowCount').textContent = rows.length + ' rows';
  var html = '<table class="db-table"><thead><tr>'; cols.forEach(function(c) { html += '<th>' + c + '</th>'; }); html += '<th></th></tr></thead><tbody>';
  rows.forEach(function(row) {
    var pkVal = String(row[cols[0]] || '').replace(/'/g, '');
    html += '<tr>';
    cols.forEach(function(col) {
      var val = row[col] === null ? '' : String(row[col]);
      var display = val.length > 140 ? val.substring(0, 140) + '...' : val;
      html += '<td><div class="db-cell" contenteditable="true" data-table="' + table + '" data-col="' + col + '" data-id="' + pkVal + '" data-pk="' + cols[0] + '">' + escHtml(display) + '</div></td>';
    });
    html += '<td><button class="admin-del-btn" onclick="dbDeleteRow(\'' + table + '\',\'' + cols[0] + '\',\'' + escAttr(pkVal) + '\')">del</button></td></tr>';
  });
  html += '</tbody></table>';
  container.innerHTML = html;
  container.querySelectorAll('.db-cell').forEach(function(cell) {
    cell.addEventListener('blur', function() { dbSaveCell(cell); });
    cell.addEventListener('keydown', function(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); cell.blur(); } if (e.key === 'Escape') cell.blur(); });
  });
}

async function dbSaveCell(cell) {
  var table = cell.dataset.table, col = cell.dataset.col, id = cell.dataset.id, pk = cell.dataset.pk, value = cell.textContent.trim();
  cell.classList.add('saving');
  try {
    var res = await window.DEE_API.postJson({ type: 'db_update', table: table, col: col, id: id, pkCol: pk, value: value });
    cell.classList.remove('saving');
    if (res.ok) { cell.classList.add('saved'); setTimeout(function() { cell.classList.remove('saved'); }, 1000); } else showToast('Save failed.');
  } catch (e) { cell.classList.remove('saving'); showToast('Save failed.'); }
}

async function dbDeleteRow(table, pkCol, id) {
  if (!confirm('Delete this row from ' + table + '? This cannot be undone.')) return;
  try {
    var res = await window.DEE_API.postJson({ type: 'db_delete_row', table: table, pkCol: pkCol, id: id });
    if (res.ok) { showToast('Row deleted.'); loadDbTable(); } else showToast('Failed.');
  } catch (e) { showToast('Error.'); }
}

async function renderSettings() {
  try {
    var data = await window.DEE_API.getJson({ action: 'settings' });
    var fee = document.getElementById('set-shipping-fee');
    var tax = document.getElementById('set-tax-rate');
    var thresh = document.getElementById('set-partial-threshold');
    var partial = document.getElementById('set-partial-amount');
    if (fee) fee.value = data.codShippingFee ?? 300;
    if (tax) tax.value = data.codTaxRate ?? 0.08;
    if (thresh) thresh.value = data.codPartialThreshold ?? 5000;
    if (partial) partial.value = data.codPartialAmount ?? 1000;
  } catch (e) { showToast('Could not load settings.'); }
}

async function saveSettings() {
  var btn = document.getElementById('saveSettingsBtn');
  var hint = document.getElementById('settingsSavedHint');
  var fee = parseFloat(document.getElementById('set-shipping-fee').value);
  var tax = parseFloat(document.getElementById('set-tax-rate').value);
  var thresh = parseFloat(document.getElementById('set-partial-threshold').value);
  var partial = parseFloat(document.getElementById('set-partial-amount').value);
  if (isNaN(fee) || isNaN(tax) || isNaN(thresh) || isNaN(partial)) { showToast('Fill all fields.'); return; }
  btn.textContent = 'Saving...'; btn.disabled = true;
  try {
    var res = await window.DEE_API.postJson({
      type: 'save_settings',
      cod_shipping_fee: fee,
      cod_tax_rate: tax,
      cod_partial_threshold: thresh,
      cod_partial_amount: partial
    });
    if (res.ok) {
      hint.style.display = 'inline';
      setTimeout(function() { hint.style.display = 'none'; }, 2500);
    } else showToast('Failed to save.');
  } catch (e) { showToast(e.message || 'Error saving settings.'); }
  btn.textContent = 'Save Settings'; btn.disabled = false;
}

function escHtml(str) { return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
function escAttr(str) { return String(str).replace(/'/g, '&#39;').replace(/"/g, '&quot;'); }

