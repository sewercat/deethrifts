<?php define('DEE_LOADED', true); require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout - deethrifts</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" href="/assets/icon.ico" type="image/x-icon">
</head>
<body data-page="checkout">
<div id="siteContainer">
  <div class="spacer-sm"></div>

  <nav id="mainNav" class="glass-header flex-center">
    <div style="display:flex; align-items:center; height:100%;">
      <a href="index.php"   class="nav-link" data-page="home"><span>Home</span></a>
      <div class="vsep"></div>
      <a href="shop.php"    class="nav-link" data-page="shop"><span>Shop</span></a>
      <div class="vsep"></div>
      <a href="donate.php"  class="nav-link" data-page="donate"><span>Donate</span></a>
      <div class="vsep"></div>
      <a href="contact.php" class="nav-link" data-page="contact"><span>Contact</span></a>
      <div class="vsep"></div>
      <a href="cart.php"    class="nav-link" data-page="cart" style="gap:6px;">
        <span>Cart</span><span class="cart-badge" style="display:none;">0</span>
      </a>
    </div>
  </nav>

  <div class="spacer-sm"></div>

  <!-- Confirmed screen -->
  <div id="orderConfirmed" style="display:none;">
    <div class="glass-body r-all" style="padding:0; flex-direction:column;">
      <div style="padding:48px; text-align:center;">
        <div style="font-size:2.5em; margin-bottom:16px;">✓</div>
        <h2 style="color:#ffb0cc; font-size:1.4em;">Order received!</h2>
        <p style="margin:12px auto 0; max-width:400px; text-align:center;">
          Thanks so much — we'll reach out on WhatsApp within 24 hours to confirm your order.
        </p>
        <div style="margin-top:28px;">
          <a href="index.php" class="aero-btn" style="text-decoration:none;">Back to home</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Checkout form -->
  <div id="checkoutForm">
    <div class="checkout-layout">
      <div>
        <!-- Your Details -->
        <div class="glass-body r-top" style="padding:0; flex-direction:column;">
          <div class="section-label">Your Details</div>
          <div style="padding:20px;">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">First Name</label>
                <input class="glass-input" id="f-first" type="text" placeholder="Jane" required>
              </div>
              <div class="form-group">
                <label class="form-label">Last Name</label>
                <input class="glass-input" id="f-last" type="text" placeholder="Doe" required>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input class="glass-input" id="f-phone" type="tel" placeholder="03001234567" required>
            </div>
            <div class="form-group">
              <label class="form-label">Second Phone (optional)</label>
              <input class="glass-input" id="f-phone2" type="tel" placeholder="03111234567">
            </div>
            <div class="form-group">
              <label class="form-label">Instagram Handle</label>
              <input class="glass-input" id="f-contact" type="text" placeholder="@yourhandle" required>
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input class="glass-input" id="f-email" type="email" placeholder="you@example.com" required>
            </div>
          </div>
        </div>

        <div style="height:2px;"></div>

        <!-- Shipping -->
        <div class="glass-body" style="padding:0; flex-direction:column;">
          <div class="section-label">Shipping Address</div>
          <div style="padding:20px;">
            <div class="form-group">
              <label class="form-label">Street Address</label>
              <input class="glass-input" id="f-addr" type="text" placeholder="123 Main St, Apt 4">
            </div>
            <div class="form-group">
              <label class="form-label">City</label>
              <input class="glass-input" id="f-city" type="text" placeholder="Lahore">
            </div>
            <div class="form-group">
              <label class="form-label">Notes (optional)</label>
              <textarea class="glass-input" id="f-notes" rows="3" placeholder="Any special requests..." style="resize:vertical;"></textarea>
            </div>
          </div>
        </div>

        <div style="height:2px;"></div>

        <!-- Customer status + payment choices (shown after Continue) -->
        <div id="customerStatus" style="display:none;"></div>
        <div id="payChoices"     style="display:none;"></div>

        <!-- Continue button -->
        <div id="formBottom" class="glass-body r-bot" style="padding:16px; justify-content:center; gap:14px;">
          <button class="aero-btn" id="continueBtn">Continue &rarr;</button>
        </div>
      </div>

      <!-- Order summary -->
      <div>
        <div class="glass-body r-all" style="padding:0; flex-direction:column;">
          <div class="section-label">Order Summary</div>
          <div id="summaryItems" style="padding:8px 0;"></div>
          <div style="border-top:1px solid rgba(255,255,255,0.07); padding:14px 16px;">
            <div class="total-row"><span class="total-label">Subtotal</span><span class="total-val" id="sumSubtotal">--</span></div>
            <div class="total-row"><span class="total-label">Shipping</span><span class="total-val" id="sumShipping">Rs 0</span></div>
            <div class="total-row" id="sumCodTaxRow" style="display:none;"><span class="total-label">COD Tax (8%)</span><span class="total-val" id="sumCodTax">Rs 0</span></div>
            <div class="total-row big"><span class="total-label">Total</span><span class="total-val" id="sumTotal">--</span></div>
          </div>
        </div>
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

<div class="toast-msg" id="toastEl"></div>
<script src="app.js?v=<?php echo filemtime(__DIR__ . '/app.js'); ?>"></script>
<script>
var lastCustomerProfile = null;

var COD_PARTIAL_THRESHOLD = 5000;
var COD_PARTIAL_AMOUNT    = 1000;
var SHIPPING_FEE          = 300;
var COD_TAX_RATE          = 0.08;

async function loadCheckoutSettings() {
  try {
    var s = await window.DEE_API.getJson({ action: 'settings' });
    if (s.codShippingFee      !== undefined) SHIPPING_FEE          = s.codShippingFee;
    if (s.codPartialThreshold !== undefined) COD_PARTIAL_THRESHOLD = s.codPartialThreshold;
    if (s.codPartialAmount    !== undefined) COD_PARTIAL_AMOUNT    = s.codPartialAmount;
    if (s.codTaxRate          !== undefined) COD_TAX_RATE          = s.codTaxRate;
  } catch (e) { /* use defaults */ }
}
var checkoutCompleted = false;
var checkoutPreviewMode = 'prepaid';
var checkoutPreviewReturning = false;

document.addEventListener('DOMContentLoaded', initCheckout);

function makeOrderId() {
  return 'ord-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
}

function isCodMode(mode) {
  return mode === 'cod' || mode === 'cod_deposit';
}

function getCodDeliveryFee(isReturning) {
  return SHIPPING_FEE;
}

function getCodUpfrontAmount(isReturning, subtotal) {
  var upfront = SHIPPING_FEE;
  if (subtotal > COD_PARTIAL_THRESHOLD) upfront += COD_PARTIAL_AMOUNT;
  return upfront;
}

function calculateCheckoutTotals(subtotal, paymentMode, isReturning) {
  var shipping = getCodDeliveryFee(isReturning);
  var codTax = 0;
  if (isCodMode(paymentMode)) {
    codTax = Math.round((subtotal + shipping) * COD_TAX_RATE);
  }
  return {
    subtotal: subtotal,
    shipping: shipping,
    codDelivery: shipping,
    codTax: codTax,
    total: subtotal + shipping + codTax
  };
}

function renderSummaryFromCart() {
  var cart = Cart.get();
  var si = document.getElementById('summaryItems');
  si.innerHTML = '';
  cart.forEach(function(item) {
    var rowImg = (item.images && item.images[0]) || item.img;
    var rowImgHtml = getImageOrFallbackHtml(
      rowImg,
      item.name || 'Product',
      '',
      'width:40px;height:40px;object-fit:cover;border-radius:5px;display:block;',
      'no-image-fallback',
      'width:40px;height:40px;border-radius:5px;display:flex;font-size:0.58em;padding:4px;'
    );
    var row = document.createElement('div');
    row.style.cssText = 'display:flex; align-items:center; gap:10px; padding:8px 14px; border-bottom:1px solid rgba(255,255,255,0.05);';
    row.innerHTML =
      rowImgHtml +
      '<div style="flex:1;"><div style="font-size:0.82em;color:rgba(255,255,255,0.8);">' + item.name + '</div></div>' +
      '<div style="font-size:0.9em;color:#ffb0cc;font-weight:500;">' + formatPkr(item.price) + '</div>';
    si.appendChild(row);
  });

  var subtotal = Cart.total();
  var totals = calculateCheckoutTotals(subtotal, checkoutPreviewMode, checkoutPreviewReturning);
  var showCodCharges = isCodMode(checkoutPreviewMode);
  document.getElementById('sumSubtotal').textContent = formatPkr(totals.subtotal);
  document.getElementById('sumShipping').textContent = formatPkr(totals.shipping);
  document.getElementById('sumCodTax').textContent = formatPkr(totals.codTax);
  document.getElementById('sumCodTaxRow').style.display = showCodCharges ? 'flex' : 'none';
  document.getElementById('sumTotal').textContent = formatPkr(totals.total);
}

async function handleCheckoutInventoryChange() {
  if (checkoutCompleted) return;
  var cart = Cart.get();
  if (!cart.length) {
    showToast('A product in your cart is no longer available.');
    setTimeout(function() { window.location.href = 'cart.php'; }, 700);
    return;
  }
  renderSummaryFromCart();
}

async function initCheckout() {
  await loadCheckoutSettings();
  await Products.refresh();
  await Cart.syncWithInventory({ refreshProducts: false });
  var cart = Cart.get();
  if (!cart.length) { showToast('Cart is empty.'); window.location.href = 'cart.php'; return; }

  renderSummaryFromCart();

  document.getElementById('continueBtn').addEventListener('click', handleContinue);
  if (typeof window.startDeeAutoRefresh === 'function') {
    startDeeAutoRefresh({
      pollMs: 2500,
      refreshProducts: true,
      syncCart: true,
      onChange: handleCheckoutInventoryChange
    });
  }
}

function getFormData() {
  return {
    first:   document.getElementById('f-first').value.trim(),
    last:    document.getElementById('f-last').value.trim(),
    phone:   document.getElementById('f-phone').value.trim(),
    phone2:  document.getElementById('f-phone2').value.trim(),
    contact: document.getElementById('f-contact').value.trim(),
    email:   document.getElementById('f-email').value.trim(),
    addr:    document.getElementById('f-addr').value.trim(),
    city:    document.getElementById('f-city').value.trim(),
    notes:   document.getElementById('f-notes').value.trim()
  };
}

function isValidEmail(e) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e); }

function buildOrderData(d, cart, paymentMode, isReturning) {
  var subtotal = Cart.total();
  var totals = calculateCheckoutTotals(subtotal, paymentMode, !!isReturning);
  return {
    id:              makeOrderId(),
    name:            (d.first + ' ' + d.last).trim(),
    phone:           d.phone,
    secondPhone:     d.phone2 || '',
    instagramHandle: d.contact,
    email:           d.email,
    address:         [d.addr, d.city].filter(Boolean).join(', '),
    city:            d.city || '',
    notes:           d.notes || '',
    items:           cart,
    subtotal:        totals.subtotal,
    total:           totals.total,
    codDeliveryFee:  totals.codDelivery,
    codTax:          totals.codTax,
    paymentMode:     paymentMode,
    productIds:      cart.map(function(i) { return i.id;   }).filter(Boolean),
    productNames:    cart.map(function(i) { return i.name; }).filter(Boolean),
    firstOrder:      !(lastCustomerProfile && lastCustomerProfile.returning)
  };
}

async function sendOrder(orderData) {
  try {
    return await window.DEE_API.postJson({ type: 'order', order: orderData });
  } catch(e) {
    return {
      ok: false,
      error: e && e.message ? e.message : 'Failed to place order.',
      code: e && e.payload ? e.payload.code : '',
      unavailable: e && e.payload ? (e.payload.unavailable || []) : [],
      missing: e && e.payload ? (e.payload.missing || []) : [],
      status: e && e.status ? e.status : 0
    };
  }
}

async function createPaymentReservation(orderData) {
  try {
    return await window.DEE_API.postJson({ type: 'create_payment_reservation', order: orderData });
  } catch(e) {
    return {
      ok: false,
      error: e && e.message ? e.message : 'Failed to start payment session.',
      code: e && e.payload ? e.payload.code : '',
      unavailable: e && e.payload ? (e.payload.unavailable || []) : [],
      missing: e && e.payload ? (e.payload.missing || []) : [],
      status: e && e.status ? e.status : 0
    };
  }
}

async function handleOrderFailure(result) {
  if (!result) {
    showToast('Failed to place order.');
    return;
  }

  var unavailable = Array.isArray(result.unavailable) ? result.unavailable : [];
  var hasCheckoutLock = unavailable.some(function(item) {
    return String((item && item.status) || '').toLowerCase() === 'confirmation_pending';
  });

  if (hasCheckoutLock) {
    showToast('One or more items are currently in another customer checkout. Please try again in a few minutes.');
    await Products.refresh();
    renderSummaryFromCart();
    return;
  }

  if (result.code === 'PRODUCT_UNAVAILABLE' || result.code === 'PRODUCT_RACE_LOST' || result.status === 409) {
    showToast('One or more items were just taken. Cart has been refreshed.');
    await Products.refresh();
    await Cart.syncWithInventory({ refreshProducts: false });
    setTimeout(function() { window.location.href = 'cart.php'; }, 700);
    return;
  }

  showToast(result.error || 'Failed to place order.');
}

function buildCodLabel(isReturning, subtotal) {
  var exceeds = subtotal > COD_PARTIAL_THRESHOLD;
  if (!exceeds) return 'Cash on Delivery (+ Rs ' + SHIPPING_FEE + ' shipping + ' + Math.round(COD_TAX_RATE * 100) + '% tax)';
  return 'Cash on Delivery (+ Rs ' + SHIPPING_FEE + ' shipping + partial + ' + Math.round(COD_TAX_RATE * 100) + '% tax)';
}

function buildCodNote(isReturning, subtotal) {
  var exceeds = subtotal > COD_PARTIAL_THRESHOLD;
  var deliveryFee = getCodDeliveryFee(isReturning);
  var upfront = getCodUpfrontAmount(isReturning, subtotal);
  var codTax = Math.round((subtotal + deliveryFee) * COD_TAX_RATE);
  var codTotal = subtotal + deliveryFee + codTax;
  var codAfterShipping = Math.max(codTotal - deliveryFee, 0);
  var remainingCod = Math.max(codTotal - upfront, 0);
  var taxLine = 'COD total includes 8% tax on subtotal + shipping (' + formatPkr(codTax) + '). COD amount after shipping payment: ' + formatPkr(codAfterShipping) + '. ';
  var remainingLine = upfront > 0 ? ' Remaining to pay on delivery after upfront: ' + formatPkr(remainingCod) + '. ' : '';
  if (!exceeds) {
    return taxLine + 'COD: pay Rs 300 shipping now, rest on delivery.' + remainingLine + 'Online: pay full amount now.';
  }
  return taxLine + 'COD: pay Rs ' + (SHIPPING_FEE + COD_PARTIAL_AMOUNT) + ' upfront (shipping + partial), rest on delivery.' + remainingLine + 'Online: pay full amount now.';
}

async function handleContinue() {
  await Products.refresh();
  await Cart.syncWithInventory({ refreshProducts: false });
  renderSummaryFromCart();
  var d    = getFormData();
  var cart = Cart.get();
  var subtotal = Cart.total();

  if (!cart.length)                          { showToast('Cart has no available items.'); window.location.href = 'cart.php'; return; }
  if (!d.first || !d.last || !d.phone || !d.contact || !d.email) { showToast('Fill name, phone, Instagram and email.'); return; }
  if (d.phone.replace(/\D/g,'').length < 7) { showToast('Enter a valid phone number.'); return; }
  if (!isValidEmail(d.email))               { showToast('Enter a valid email.'); return; }

  var btn = document.getElementById('continueBtn');
  btn.textContent = 'Checking...'; btn.disabled = true;

  var profile = await Customers.getProfile(d.phone);
  lastCustomerProfile = profile;

  btn.textContent = 'Continue →'; btn.disabled = false;

  var isReturning = profile.returning  === true;
  var codBlocked  = profile.codBlocked === true;
  var exceeds     = subtotal > COD_PARTIAL_THRESHOLD;
  checkoutPreviewReturning = isReturning;
  checkoutPreviewMode = codBlocked ? 'prepaid' : 'cod';
  renderSummaryFromCart();

  var statusEl  = document.getElementById('customerStatus');
  var choicesEl = document.getElementById('payChoices');
  statusEl.style.display  = 'block';
  choicesEl.style.display = 'block';
  btn.style.display       = 'none';

  // Status banner
  if (isReturning && !codBlocked) {
    var extra = exceeds ? ' Your order exceeds Rs 5,000 — an additional <span class="status-highlight">partial online payment</span> is required.' : '';
    statusEl.innerHTML = '<div class="customer-status returning"><div class="status-icon">✓</div>' +
      '<div class="status-text">Welcome back, <strong>' + d.first + '</strong>! <span class="status-highlight">Rs 300 shipping</span> applies to every order.' + extra + '</div></div>';
  } else if (isReturning && codBlocked) {
    statusEl.innerHTML = '<div class="customer-status first-time"><div class="status-icon">!</div>' +
      '<div class="status-text">Your latest order was <span class="status-highlight">' + (profile.latestOrderStatus || 'returned') + '</span> — COD is disabled for your account.</div></div>';
  } else {
    var extraFirst = exceeds ? ' Your order exceeds Rs 5,000 — an additional <span class="status-highlight">partial payment</span> is required.' : '';
    statusEl.innerHTML = '<div class="customer-status first-time"><div class="status-icon">!</div>' +
      '<div class="status-text"><span class="status-highlight">Rs 300 shipping</span> applies to every order.' + extraFirst + '</div></div>';
  }

  // Payment options
  var codBtn = '';
  if (!codBlocked) {
    codBtn = '<button class="aero-btn pink-btn" onclick="handleCOD()" style="min-width:200px;">' + buildCodLabel(isReturning, subtotal) + '</button>';
  }
  var prepaidBtn = '<button class="aero-btn" onclick="handlePrepaid()" style="min-width:200px;">Pay Full Amount Online</button>';
  var note = codBlocked ? 'Online payment only due to your previous order status.' : buildCodNote(isReturning, subtotal);

  choicesEl.innerHTML =
    '<div class="glass-body r-bot" style="flex-direction:column; padding:0;">' +
      '<div style="padding:16px 20px;">' +
        '<div class="pay-choices">' + codBtn + prepaidBtn + '</div>' +
        '<p style="font-size:0.78em; color:rgba(255,255,255,0.3); text-align:center; margin-top:8px;">' + note + '</p>' +
      '</div>' +
    '</div>';
}

// ── COD handler ───────────────────────────────────
async function handleCOD() {
  var d    = getFormData();
  var cart = Cart.get();
  if (!cart.length) { showToast('Cart empty.'); window.location.href = 'cart.php'; return; }

  var isReturning = !!(lastCustomerProfile && lastCustomerProfile.returning);
  checkoutPreviewReturning = isReturning;
  checkoutPreviewMode = 'cod';
  renderSummaryFromCart();
  var subtotal    = Cart.total();
  var exceeds     = subtotal > COD_PARTIAL_THRESHOLD;

  // Calculate upfront amount
  var upfrontFee     = SHIPPING_FEE;                   // Rs 300 shipping on every order
  if (exceeds)      upfrontFee += COD_PARTIAL_AMOUNT; // Rs 1,000 partial for >5k

  // COD always requires upfront online payment proof (shipping, plus partial when applicable).
  var orderData2 = buildOrderData(d, cart, 'cod_deposit', isReturning);
  orderData2.firstOrder = !isReturning;
  var result2 = await createPaymentReservation(orderData2);
  if (result2.ok && result2.reservationId && result2.reservationToken) {
    checkoutCompleted = true;
    Cart.clear();
    window.location.href =
      'payment.php?context=delivery' +
      '&rid=' + encodeURIComponent(result2.reservationId) +
      '&rt=' + encodeURIComponent(result2.reservationToken) +
      '&amount=' + encodeURIComponent(upfrontFee);
  } else {
    await handleOrderFailure(result2);
  }
}

// ── Prepaid handler ───────────────────────────────
async function handlePrepaid() {
  var d    = getFormData();
  var cart = Cart.get();
  if (!cart.length) { showToast('Cart empty.'); window.location.href = 'cart.php'; return; }

  checkoutPreviewReturning = !!(lastCustomerProfile && lastCustomerProfile.returning);
  checkoutPreviewMode = 'prepaid';
  renderSummaryFromCart();

  var orderData = buildOrderData(d, cart, 'prepaid', checkoutPreviewReturning);
  var result    = await createPaymentReservation(orderData);
  if (result.ok && result.reservationId && result.reservationToken) {
    checkoutCompleted = true;
    Cart.clear();
    window.location.href =
      'payment.php?context=order' +
      '&rid=' + encodeURIComponent(result.reservationId) +
      '&rt=' + encodeURIComponent(result.reservationToken);
  } else {
    await handleOrderFailure(result);
  }
}
</script>
</body>
</html>
