<?php define('DEE_LOADED', true); require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>deethrifts</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" href="/assets/icon.ico" type="image/x-icon">
</head>
<body data-page="home">

<div id="siteContainer">
  <div class="spacer-sm"></div>

  <nav id="mainNav" class="glass-header flex-center">
    <div style="display:flex; align-items:center; height:100%;">
      <a href="index.php" class="nav-link" data-page="home"><span>Home</span></a>
      <div class="vsep"></div>
      <a href="shop.php" class="nav-link" data-page="shop"><span>Shop</span></a>
      <div class="vsep"></div>
      <a href="donate.php" class="nav-link" data-page="donate"><span>Donate</span></a>
      <div class="vsep"></div>
      <a href="contact.php" class="nav-link" data-page="contact"><span>Contact</span></a>
      <div class="vsep"></div>
      <a href="cart.php" class="nav-link" data-page="cart" style="gap:6px;">
        <span>Cart</span>
        <span class="cart-badge" style="display:none;">0</span>
      </a>
    </div>
  </nav>

  <div class="spacer-sm"></div>

  <div>
    <div class="glass-body r-top" style="padding:0; flex-direction:column;">
        <pre class="ascii-art" style="text-align:center; padding:16px 16px 0;">    ⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⢀⣳⡀⢸⣀⠀⣀⣀⠀⠀⡀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠀⢀⣀⠀⠀⠀⠀⠸⠀⠸⠙⠀⠛⠈⠀⣴⠀⢠⣴⠓⠀⢀⠀⠀⠀
⠀⠀⡀⠀⢠⣤⡈⠯⠂⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠘⠁⠀⡶⡏⠀⠀⠀
⠀⠀⢘⣦⡈⠛⠁⠀⠀⣾⠛⠦⠤⠶⠶⠶⠶⠦⣤⡴⢾⡇⠀⠀⠈⠁⡀⡴⠄⠀
⠀⠀⠀⠙⢁⡀⠀⠀⢠⠇⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠘⣇⠀⣤⢤⡀⠉⠁⠀⠀
⠀⠀⠀⢰⣿⢿⣧⡀⡾⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⢸⡾⣗⣾⡇⠀⠀⠀⠀
⠀⠀⠀⠘⣟⢶⢾⣻⡇⢠⣤⣄⠀⠀⢀⠀⠀⢀⣀⣀⠀⢸⣗⠛⢹⠇⠀⠀⠀⠀
⠀⠀⠀⠀⠘⣦⠀⠛⢷⡀⠀⠀⠀⠀⠿⠄⠀⠈⠉⠉⣠⠿⠯⠀⡾⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠘⣧⠀⠀⠙⠲⠦⣤⣤⣀⣠⣤⡤⠴⠚⠁⠀⠀⣼⠃⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠀⠈⢧⡀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⢰⠇⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠀⠀⠈⠁⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠈⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀            </pre>
      <div style="padding:10px; text-align:center;">
        <h2>welcome to dee<span style="color:#ffb0cc; font-weight:400;">thrifts</span></h2>
        <p style="text-align:center; margin:8px auto 0; max-width:500px;">
          support a cause
        </p>
      </div>
      <div style="margin-top:20px; text-align:center">
          <a href="donate.php" class="aero-btn donate-index-btn" style="text-decoration:none; gap:8px;">
            <span style="color:#ffb0cc; font-size:1.1em;">♡</span> donate
          </a>
        </div>
      <div class="section-label">featured picks</div>
      <div id="featuredRow" style="display:flex; gap:14px; padding:16px; flex-wrap:wrap; justify-content:center;"></div>
      <div class="section-label">sale items</div>
      <div id="saleRow" style="display:flex; gap:14px; padding:16px; flex-wrap:wrap; justify-content:center;"></div>
    </div>

    <div style="height:2px;"></div>

    <div class="glass-body" style="padding:0; flex-direction:column;">
      <div class="section-label">track your order</div>
      <div style="padding:16px 18px;">
        <p style="margin:0 0 12px; color:rgba(255,255,255,0.62); font-size:0.84em;">
          Enter your order number to check tracking details.
        </p>
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
          <input id="trackOrderId" class="glass-input" type="text" placeholder="Order ID (e.g. ord-123...)" style="max-width:380px;">
          <button id="trackLookupBtn" class="aero-btn pink-btn" type="button">Check Tracking</button>
        </div>
        <div id="trackingLookupResult" style="margin-top:12px; font-size:0.82em; color:rgba(255,255,255,0.72);"></div>
      </div>
    </div>

    <div style="height:2px;"></div>

    <div class="glass-body r-bot flex-center">
      <div style="padding:14px; text-align:center;">
        <p style="font-size:0.95em; color:rgba(255,255,255,0.45); margin:0; max-width:100%;">
          new items added weekly - everything is pre-owned and sold as-is - shipping all over pakistan
        </p>
      </div>
    </div>
    <!-- Product Modal -->
    <div class="modal-overlay" id="productModal">
      <div class="modal-card">
        <div class="modal-images">
          <button class="modal-close" onclick="closeProductModal()"><img src="/assets/close.png" alt="close"></button>
          <button class="modal-nav prev" onclick="prevProductImage()"><img src="/assets/arrow.png" alt="prev"></button>
          <button class="modal-nav next" onclick="nextProductImage()"><img src="/assets/arrow.png" alt="next"></button>
          <div class="modal-main-img-wrap">
            <img class="modal-main-img" src="" alt="">
          </div>
          <div class="modal-thumbs"></div>
        </div>
        <div class="modal-info">
          <span class="modal-condition">10/10</span>
          <div class="modal-name"></div>
          <hr class="modal-divider">
          <div class="modal-meta"></div>
          <div class="modal-description" style="display:none;"></div>
          <div class="modal-defects">No known defects</div>
          <div class="modal-status" style="display:none;"></div>
          <div class="modal-price"></div>
          <button class="aero-btn pink-btn modal-add">Add to Cart</button>
        </div>
      </div>
    </div>

    <div class="spacer-md"></div>

    <div class="glass-header r-all" style="padding:0;">
      <div class="footer-content">
        <span>(c) 2025 <span class="logo-text">dee<span class="pink">thrifts</span></span> - all items are pre-owned and sold as-is</span>
      </div>
    </div>

    <div class="spacer-md"></div>
  </div>
</div>

<div class="toast-msg" id="toastEl"></div>
<script src="app.js?v=<?php echo filemtime(__DIR__ . '/app.js'); ?>"></script>
<script>
async function renderFeatured(skipSync) {
  if (!skipSync) {
    await Products.refresh();
    await Cart.syncWithInventory({ refreshProducts: false });
  }

  const row = document.getElementById('featuredRow');
  if (!row) return;
  row.innerHTML = '';
  const allProds = getAllProducts();
  const featured = allProds.filter(p => p.is_featured);
  const picks = (featured.length ? featured : allProds).slice(0, 10);

  if (picks.length === 0) {
    row.innerHTML = '<p style="color:rgba(255,255,255,0.45); font-size:0.9em;">No products available right now. Please check back soon.</p>';
    return;
  }

  picks.forEach(function(p) {
    var card = document.createElement('div');
    card.style.cssText = 'text-align:center; cursor:pointer; transition:all 0.2s; width:160px;';
    var statusText = (p.status && p.status !== 'available') ? productStatusLabel(p.status) : '';
    var statusColor = (p.status === 'sold_out' || p.status === 'confirmation_pending') ? 'rgba(255,120,120,0.95)' : 'rgba(255,210,150,0.85)';
    var featuredImg = getImageOrFallbackHtml(
      (p.images && p.images[0]) || p.img,
      p.name,
      '',
      'width:160px;height:160px;object-fit:cover;border-radius:8px;border:2px solid rgba(0,0,0,0.15);box-shadow:0px 3px 6px rgba(0,0,0,0.2),inset 0px 1px 1px rgba(255,255,255,0.15);margin-bottom:8px;display:block;',
      'no-image-fallback',
      'width:160px;height:160px;border-radius:8px;border:2px solid rgba(0,0,0,0.15);box-shadow:0px 3px 6px rgba(0,0,0,0.2),inset 0px 1px 1px rgba(255,255,255,0.15);margin-bottom:8px;display:flex;'
    );
    card.innerHTML =
      featuredImg +
      '<div style="font-size:0.9em;color:rgba(255,255,255,0.8);text-shadow:0px -1px 1px rgba(0,0,0,0.2);">' + p.name + '</div>' +
      '<div style="font-size:1em;color:#ffb0cc;font-weight:500;text-shadow:0px -1px 1px rgba(0,0,0,0.2);">' + formatPkr(p.price) + '</div>' +
      (statusText ? '<div style="font-size:0.72em;color:' + statusColor + ';margin-top:4px;">' + statusText + '</div>' : '');
    card.onmouseenter = function() { card.style.filter = 'brightness(90%)'; card.style.transform = 'scale(1.02)'; };
    card.onmouseleave = function() { card.style.filter = ''; card.style.transform = ''; };
    card.addEventListener('click', function() { openProductModal(p); });
    row.appendChild(card);
  });
}

function renderSaleItems() {
  const row = document.getElementById('saleRow');
  if (!row) return;
  row.innerHTML = '';

  const saleItems = getByCategory('sale')
    .filter(function(p) { return String(p.status || '').toLowerCase() === 'available'; })
    .slice(0, 12);

  if (!saleItems.length) {
    row.innerHTML = '<p style="color:rgba(255,255,255,0.45); font-size:0.9em;">No sale items live right now.</p>';
    return;
  }

  saleItems.forEach(function(p) {
    row.appendChild(buildCard(p));
  });
}

function trackingEsc(v) {
  if (typeof escapeHtml === 'function') return escapeHtml(v);
  return String(v || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function renderTrackingResult(payload, requestedOrderId) {
  var box = document.getElementById('trackingLookupResult');
  if (!box) return;

  var safeRequested = trackingEsc(requestedOrderId || '');
  if (!payload || payload.ok === false) {
    box.innerHTML = '<div style="color:rgba(255,140,140,0.95);">Could not check tracking right now. Please try again.</div>';
    return;
  }

  if (!payload.foundOrder) {
    box.innerHTML =
      '<div style="color:rgba(255,180,180,0.95);">Order <strong>' + safeRequested + '</strong> not found.</div>' +
      '<div style="margin-top:4px; color:rgba(255,255,255,0.58);">Please check the order number and try again.</div>';
    return;
  }

  var trackingNumber = String(payload.trackingNumber || '').trim();
  var carrierType = String(payload.carrierType || '').trim().toLowerCase();
  var carrierLabel = trackingEsc(payload.carrierLabel || '');
  var message = trackingEsc(payload.message || 'No tracking info yet. Please check back in a day.');
  var rawOrderStatus = String(payload.orderStatus || '').trim().toLowerCase();
  var orderStatusLabel = (rawOrderStatus === 'confirmed' || rawOrderStatus === 'delivered')
    ? 'Confirmed'
    : 'Pending';
  var carrierUrl = String(payload.carrierUrl || '').trim();
  var carrierActionLabel = trackingEsc(payload.carrierActionLabel || 'Open Courier');

  if (!trackingNumber) {
    var portalHint = String(payload.portalMessage || '').trim();
    if (portalHint.length > 160) portalHint = portalHint.slice(0, 160) + '...';
    box.innerHTML =
      '<div style="color:rgba(255,255,255,0.86);">' + message + '</div>' +
      '<div style="margin-top:4px; color:rgba(255,255,255,0.56);">Order: <strong>' + trackingEsc(payload.orderId || requestedOrderId || '-') + '</strong></div>' +
      '<div style="margin-top:4px; color:rgba(255,255,255,0.62);">Order Status: <strong>' + trackingEsc(orderStatusLabel) + '</strong></div>' +
      (portalHint ? '<div style="margin-top:4px; color:rgba(255,255,255,0.46);">Portal: ' + trackingEsc(portalHint) + '</div>' : '');
    return;
  }

  var actionBtn = '';
  if (carrierUrl) {
    actionBtn =
      '<a class="aero-btn pink-btn" href="' + trackingEsc(carrierUrl) + '" target="_blank" rel="noopener" style="text-decoration:none;">' +
      carrierActionLabel +
      '</a>';
  }

  var carrierText = carrierLabel ? ('<div style="margin-top:4px; color:rgba(255,255,255,0.62);">Courier: <strong>' + carrierLabel + '</strong></div>') : '';
  var localHint = (carrierType === 'local')
    ? '<div style="margin-top:4px; color:rgba(255,255,255,0.72);">This parcel is being delivered locally. Please contact @deethrifts.pk on Instagram.</div>'
    : '';

  box.innerHTML =
    '<div style="color:rgba(255,255,255,0.88);">Tracking Number: <strong style="color:#ffb0cc;">' + trackingEsc(trackingNumber) + '</strong></div>' +
    '<div style="margin-top:4px; color:rgba(255,255,255,0.62);">Order Status: <strong>' + trackingEsc(orderStatusLabel) + '</strong></div>' +
    carrierText +
    '<div style="margin-top:4px; color:rgba(255,255,255,0.72);">' + message + '</div>' +
    localHint +
    (actionBtn ? ('<div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">' + actionBtn + '</div>') : '');
}

async function lookupTrackingFromHome() {
  var input = document.getElementById('trackOrderId');
  var button = document.getElementById('trackLookupBtn');
  var box = document.getElementById('trackingLookupResult');
  if (!input || !button || !box) return;

  var orderId = String(input.value || '').trim();
  if (!orderId) {
    box.innerHTML = '<div style="color:rgba(255,180,180,0.95);">Please enter your order number first.</div>';
    return;
  }

  button.disabled = true;
  button.style.opacity = '0.7';
  box.innerHTML = '<div style="color:rgba(255,255,255,0.62);">Checking tracking details...</div>';

  try {
    var payload = await window.DEE_API.getJson({ action: 'tracking_lookup', orderId: orderId });
    renderTrackingResult(payload, orderId);
  } catch (e) {
    box.innerHTML = '<div style="color:rgba(255,140,140,0.95);">' + trackingEsc((e && e.message) ? e.message : 'Could not check tracking right now.') + '</div>';
  } finally {
    button.disabled = false;
    button.style.opacity = '1';
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  await renderFeatured();
  renderSaleItems();

  var trackBtn = document.getElementById('trackLookupBtn');
  var trackInput = document.getElementById('trackOrderId');
  if (trackBtn) trackBtn.addEventListener('click', lookupTrackingFromHome);
  if (trackInput) {
    trackInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        lookupTrackingFromHome();
      }
    });
  }

  if (typeof window.startDeeAutoRefresh === 'function') {
    startDeeAutoRefresh({
      pollMs: 3000,
      refreshProducts: true,
      syncCart: true,
      onChange: function() {
        renderSaleItems();
        return renderFeatured(true);
      }
    });
  }
});
</script>
</body>
</html>
