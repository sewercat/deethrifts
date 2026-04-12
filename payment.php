<?php define('DEE_LOADED', true); require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment - deethrifts</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" href="/assets/icon.ico" type="image/x-icon">
</head>
<body data-page="payment">
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
        <span>Cart</span><span class="cart-badge" style="display:none;">0</span>
      </a>
    </div>
  </nav>

  <div class="spacer-sm"></div>

  <div id="paymentFormView">
    <div class="glass-body r-top" style="padding:0; flex-direction:column;">
      <div class="section-label">Upload Payment Proof</div>
      <div style="padding:18px 20px 0;">
        <p id="paymentContext" style="margin:0; color:rgba(255,255,255,0.6);">Loading payment details...</p>
        <p id="reservationWarning" style="display:none; margin:8px 0 0; color:rgba(255,120,120,0.95); font-size:0.82em; letter-spacing:0.02em;"></p>
        <div class="payment-amount" id="paymentAmount">Rs --</div>
      </div>
    </div>

    <div style="height:2px;"></div>

    <div class="glass-body" style="padding:0; flex-direction:column;">
      <div class="section-label">Bank Details</div>
      <div style="padding:18px 20px;">
        <div class="bank-detail"><span class="bank-label">Bank</span><span class="bank-value">Habib Bank Limited (HBL)</span></div>
        <div class="bank-detail"><span class="bank-label">Account Title</span><span class="bank-value">LAIBAH IAA</span></div>
        <div class="bank-detail"><span class="bank-label">IBAN</span><span class="bank-value"><span id="ibanText">PK15HABB0058617000055903</span><button class="copy-btn" onclick="copyIBAN()">Copy</button></span></div>
      </div>
    </div>

    <div style="height:2px;"></div>

    <div class="glass-body" style="padding:0; flex-direction:column;">
      <div class="section-label">Proof Screenshot</div>
      <div style="padding:20px;">
        <label class="upload-area">
          <input id="screenshotInput" type="file" accept="image/*">
          <div id="uploadPrompt" style="color:rgba(255,255,255,0.45);">Click to select screenshot (max 50MB). Auto-compressed for upload.</div>
          <img id="uploadPreview" class="upload-preview" alt="Preview">
        </label>
      </div>
    </div>

    <div style="height:2px;"></div>

    <div class="glass-body r-bot" style="padding:16px; justify-content:flex-end;">
      <button class="aero-btn pink-btn" id="submitProof" style="opacity:0.5; pointer-events:none;">Submit Proof</button>
    </div>
  </div>

  <div id="paymentConfirmed" style="display:none;">
    <div class="glass-body r-all" style="padding:0; flex-direction:column;">
      <div style="padding:40px 24px; text-align:center;">
        <div style="font-size:2.2em;">✓</div>
        <h2 style="font-size:1.3em; margin-top:8px; color:#ffb0cc;">Payment proof submitted</h2>
        <p style="margin-top:10px; color:rgba(255,255,255,0.65);">Order ID: <span id="confirmOrderId"></span></p>
        <p style="margin-top:10px; color:rgba(255,255,255,0.72);">Please send the message below to <strong>@deethrifts.pk</strong> on Instagram to complete confirmation.</p>
        <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:10px; margin-top:20px;">
          <a id="igLink" target="_blank" rel="noopener" class="aero-btn pink-btn" style="text-decoration:none;">Open Instagram</a>
          <button class="aero-btn" id="copyMsgBtn" onclick="copyOrderMessage()">Copy message</button>
        </div>
        <textarea id="igMessage" class="glass-input" readonly style="margin:14px auto 0; width:min(560px,100%); min-height:140px; resize:vertical; font-size:0.82em; line-height:1.5;"></textarea>
        <div style="margin-top:20px;">
          <a href="index.php" class="aero-btn" style="text-decoration:none;">Back to home</a>
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
var pgOid = '';
var pgSelectedFile = null;
var pgContext = 'order';
var pgUpfrontAmount = 0;
var pgOrderTotal = 0;
var pgReservationId = '';
var pgReservationToken = '';
var pgReservationEndsAt = 0;
var pgReservationTimer = null;
var IG_PROFILE_URL = 'https://instagram.com/deethrifts.pk';
var COD_PARTIAL_THRESHOLD = 5000;
var COD_PARTIAL_AMOUNT = 1000;
var COD_SHIPPING_FEE = 300;
var pgReservationShipping = COD_SHIPPING_FEE;
var pgReservationSubtotal = 0;

async function loadPaymentSettings() {
  try {
    var s = await window.DEE_API.getJson({ action: 'settings' });
    if (s.codShippingFee      !== undefined) COD_SHIPPING_FEE      = s.codShippingFee;
    if (s.codPartialThreshold !== undefined) COD_PARTIAL_THRESHOLD = s.codPartialThreshold;
    if (s.codPartialAmount    !== undefined) COD_PARTIAL_AMOUNT    = s.codPartialAmount;
  } catch (e) { /* use defaults */ }
}

function toMoneyNumber(value) {
  var n = Number(value || 0);
  return Number.isFinite(n) ? n : 0;
}

function getExpectedUpfront(subtotal, shippingFee) {
  var shipping = Math.max(0, Math.round(toMoneyNumber(shippingFee)));
  var upfront = shipping;
  if (toMoneyNumber(subtotal) > COD_PARTIAL_THRESHOLD) upfront += COD_PARTIAL_AMOUNT;
  return upfront;
}

function getCodAmountAfterShipping(orderTotal, shippingFee) {
  var total = Math.max(0, Math.round(toMoneyNumber(orderTotal)));
  var shipping = Math.max(0, Math.round(toMoneyNumber(shippingFee)));
  return Math.max(total - shipping, 0);
}

function getRemainingCodAfterUpfront(orderTotal, upfrontPaid) {
  var total = Math.max(0, Math.round(toMoneyNumber(orderTotal)));
  var upfront = Math.max(0, Math.round(toMoneyNumber(upfrontPaid)));
  return Math.max(total - upfront, 0);
}

async function compressPaymentImage(file, maxFileSizeMB) {
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
          if (width > maxWidth) { height = Math.round((height * maxWidth) / width); width = maxWidth; }
        } else {
          if (height > maxHeight) { width = Math.round((width * maxHeight) / height); height = maxHeight; }
        }
        canvas.width = width; canvas.height = height;
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#FFFFFF'; ctx.fillRect(0, 0, width, height); ctx.drawImage(img, 0, 0, width, height);
        (function tryCompress(q) {
          canvas.toBlob(function(blob) {
            if (!blob) { resolve(file); return; }
            if (blob.size <= maxFileSizeMB * 1024 * 1024 || q <= 0.1) {
              resolve(new File([blob], file.name, { type: 'image/jpeg', lastModified: Date.now() }));
            } else tryCompress(Math.max(0.1, q - 0.1));
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

function buildReasonText(context, upfrontAmount) {
  if (context === 'delivery') {
    if (upfrontAmount === 300) return 'Pay the Rs 300 shipping charges to confirm your order.';
    var parts = [];
    if (upfrontAmount >= 300) parts.push('Rs 300 shipping charges');
    if (upfrontAmount > 300) parts.push('Rs ' + (upfrontAmount - 300) + ' partial payment');
    return 'Pay the upfront amount to confirm your order (' + parts.join(' + ') + ').';
  }
  return 'Pay the full order amount to confirm.';
}

function isReservationFlow() {
  return !!(pgReservationId && pgReservationToken);
}

function reservationSecondsLeft() {
  if (!isReservationFlow() || !pgReservationEndsAt) return 0;
  return Math.max(0, Math.ceil((pgReservationEndsAt - Date.now()) / 1000));
}

function formatCountdown(totalSeconds) {
  var s = Math.max(0, parseInt(totalSeconds, 10) || 0);
  var mins = Math.floor(s / 60);
  var secs = s % 60;
  return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
}

function setPaymentFormDisabled(disabled, label) {
  var fileInput = document.getElementById('screenshotInput');
  var submitBtn = document.getElementById('submitProof');
  if (fileInput) fileInput.disabled = !!disabled;
  if (submitBtn) {
    submitBtn.disabled = !!disabled;
    if (disabled) {
      submitBtn.style.opacity = '0.5';
      submitBtn.style.pointerEvents = 'none';
      if (label) submitBtn.textContent = label;
    } else if (pgSelectedFile) {
      submitBtn.textContent = 'Submit Proof';
      submitBtn.style.opacity = '1';
      submitBtn.style.pointerEvents = 'auto';
    }
  }
}

function stopReservationTimer() {
  if (pgReservationTimer) {
    clearInterval(pgReservationTimer);
    pgReservationTimer = null;
  }
}

function renderReservationWarning(forceExpiredText) {
  var warningEl = document.getElementById('reservationWarning');
  if (!warningEl) return;

  if (!isReservationFlow()) {
    warningEl.style.display = 'none';
    warningEl.textContent = '';
    return;
  }

  var left = reservationSecondsLeft();
  if (forceExpiredText || left <= 0) {
    warningEl.style.display = 'block';
    warningEl.style.color = 'rgba(255,120,120,0.95)';
    warningEl.textContent = forceExpiredText || 'This payment session expired after 3 minutes. Please checkout again.';
    setPaymentFormDisabled(true, 'Session Expired');
    return;
  }

  warningEl.style.display = 'block';
  warningEl.style.color = 'rgba(255,120,120,0.95)';
  warningEl.textContent = 'Warning: this payment session will expire in 3 minutes. Time left: ' + formatCountdown(left) + '.';
}

function startReservationCountdown(secondsLeft) {
  if (!isReservationFlow()) return;
  stopReservationTimer();
  var safeSeconds = Math.max(0, parseInt(secondsLeft, 10) || 0);
  pgReservationEndsAt = Date.now() + (safeSeconds * 1000);
  renderReservationWarning();
  if (safeSeconds <= 0) return;
  pgReservationTimer = setInterval(function() {
    if (reservationSecondsLeft() <= 0) {
      stopReservationTimer();
      renderReservationWarning();
      return;
    }
    renderReservationWarning();
  }, 1000);
}

function buildInstagramMessage(orderInfo, orderId) {
  var safeOrderId = String(orderId || '').trim() || '-';
  var messageText = 'Hi! I placed an order on deethrifts.\n\nOrder ID: ' + safeOrderId + '\nPayment proof uploaded.';
  if (!orderInfo) return messageText;

  messageText = 'Hi! I placed an order on deethrifts.\n\nOrder ID: ' + safeOrderId +
    '\nName: ' + (orderInfo.name || '') +
    '\nPhone: ' + (orderInfo.phone || '') +
    '\nCity: ' + (orderInfo.city || '') +
    '\n\nItems:\n';

  (orderInfo.items || []).forEach(function(item, i) {
    messageText += (i + 1) + '. ' + (item.name || 'Item') + ' - ' + formatPkr(item.price) + '\n';
  });

  var orderTotal = Number(orderInfo.total || pgOrderTotal || 0);
  if (pgContext === 'delivery') {
    var shippingFee = Number((orderInfo && (orderInfo.codDeliveryFee || orderInfo.shipping)) || pgReservationShipping || COD_SHIPPING_FEE);
    if (!Number.isFinite(shippingFee) || shippingFee <= 0) shippingFee = COD_SHIPPING_FEE;
    var upfrontPaid = Math.max(Number(pgUpfrontAmount || 0), 0);
    var codAfterShipping = getCodAmountAfterShipping(orderTotal, shippingFee);
    var remainingOnDelivery = getRemainingCodAfterUpfront(orderTotal, upfrontPaid);
    messageText += '\nCOD Amount (shipping excluded): ' + formatPkr(codAfterShipping);
    messageText += '\nUpfront Paid Online: ' + formatPkr(upfrontPaid);
    messageText += '\nRemaining on Delivery: ' + formatPkr(remainingOnDelivery);
  } else {
    messageText += '\nTotal: ' + formatPkr(orderTotal);
  }

  messageText += '\nPayment proof uploaded.';
  return messageText;
}

async function showConfirmedView(orderId) {
  stopReservationTimer();
  pgOid = String(orderId || '').trim();
  var orderInfo = pgOid ? await Orders.getOrder(pgOid) : null;
  var messageText = buildInstagramMessage(orderInfo, pgOid);
  document.getElementById('igLink').href = IG_PROFILE_URL;
  document.getElementById('confirmOrderId').textContent = pgOid || '-';
  document.getElementById('igMessage').value = messageText;
  document.getElementById('paymentFormView').style.display = 'none';
  document.getElementById('paymentConfirmed').style.display = 'block';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', async function() {
  await loadPaymentSettings();
  var params = new URLSearchParams(window.location.search);
  var context = params.get('context');
  pgContext = context === 'delivery' ? 'delivery' : 'order';
  pgReservationId = String(params.get('rid') || '').trim();
  pgReservationToken = String(params.get('rt') || '').trim();
  pgOid = String(params.get('oid') || '').trim();

  if (isReservationFlow()) {
    try {
      var reservationResponse = await window.DEE_API.getJson({
        action: 'payment_reservation',
        rid: pgReservationId,
        token: pgReservationToken
      });
      var reservation = reservationResponse && reservationResponse.reservation ? reservationResponse.reservation : null;
      if (!reservation) throw new Error('Could not load payment session.');

      if (String(reservation.status || '') === 'converted') {
        if (!reservation.orderId) throw new Error('Payment proof was already submitted for this session.');
        await showConfirmedView(reservation.orderId);
        return;
      }

      pgOrderTotal = Number(reservation.total || 0);
      if (!Number.isFinite(pgOrderTotal)) pgOrderTotal = 0;
      pgReservationSubtotal = toMoneyNumber(reservation.subtotal || 0);
      pgReservationShipping = toMoneyNumber(reservation.shipping || COD_SHIPPING_FEE);
      if (pgReservationShipping <= 0) pgReservationShipping = COD_SHIPPING_FEE;

      if (pgContext === 'delivery') {
        var upfrontAmount = parseInt(params.get('amount'), 10);
        var expectedUpfront = getExpectedUpfront(pgReservationSubtotal, pgReservationShipping);
        if (!Number.isFinite(upfrontAmount) || upfrontAmount <= 0) {
          upfrontAmount = expectedUpfront;
        }
        pgUpfrontAmount = upfrontAmount;
        var codAfterShipping = getCodAmountAfterShipping(pgOrderTotal, pgReservationShipping);
        var remainingCod = getRemainingCodAfterUpfront(pgOrderTotal, upfrontAmount);
        var contextText = buildReasonText(pgContext, upfrontAmount);
        if (pgOrderTotal > 0) {
          contextText += ' COD amount after shipping payment: ' + formatPkr(codAfterShipping) + '.';
          contextText += ' Remaining COD after this payment: ' + formatPkr(remainingCod) + '.';
        }
        document.getElementById('paymentContext').textContent = contextText;
        document.getElementById('paymentAmount').textContent = formatPkr(upfrontAmount);
      } else {
        pgUpfrontAmount = 0;
        document.getElementById('paymentContext').textContent = buildReasonText(pgContext, 0);
        document.getElementById('paymentAmount').textContent = formatPkr(pgOrderTotal);
      }

      startReservationCountdown(Number(reservation.secondsLeft || 0));
    } catch (e) {
      renderReservationWarning((e && e.message) ? e.message : 'This payment session is no longer active.');
      showToast((e && e.message) ? e.message : 'This payment session is no longer active.');
      return;
    }
  } else {
    if (!pgOid) {
      setPaymentFormDisabled(true, 'Unavailable');
      showToast('Missing payment info.');
      return;
    }

    var orderData = await Orders.getOrder(pgOid);
    pgOrderTotal = orderData ? Number(orderData.total || 0) : 0;
    pgReservationSubtotal = orderData ? toMoneyNumber(orderData.subtotal || 0) : 0;
    pgReservationShipping = orderData ? toMoneyNumber(orderData.codDeliveryFee || orderData.shipping || COD_SHIPPING_FEE) : COD_SHIPPING_FEE;
    if (pgReservationShipping <= 0) pgReservationShipping = COD_SHIPPING_FEE;

    if (pgContext === 'delivery') {
      var legacyUpfront = parseInt(params.get('amount'), 10);
      if (!Number.isFinite(legacyUpfront) || legacyUpfront <= 0) {
        legacyUpfront = getExpectedUpfront(pgReservationSubtotal, pgReservationShipping);
      }
      pgUpfrontAmount = legacyUpfront;
      var legacyCodAfterShipping = getCodAmountAfterShipping(pgOrderTotal, pgReservationShipping);
      var legacyRemainingCod = getRemainingCodAfterUpfront(pgOrderTotal, legacyUpfront);
      var legacyText = buildReasonText(pgContext, legacyUpfront);
      if (pgOrderTotal > 0) {
        legacyText += ' COD amount after shipping payment: ' + formatPkr(legacyCodAfterShipping) + '.';
        legacyText += ' Remaining COD after this payment: ' + formatPkr(legacyRemainingCod) + '.';
      }
      document.getElementById('paymentContext').textContent = legacyText;
      document.getElementById('paymentAmount').textContent = formatPkr(legacyUpfront);
    } else {
      pgUpfrontAmount = 0;
      document.getElementById('paymentContext').textContent = buildReasonText(pgContext, 0);
      document.getElementById('paymentAmount').textContent = formatPkr(pgOrderTotal);
    }
    renderReservationWarning();
  }

  var fileInput = document.getElementById('screenshotInput');
  var preview = document.getElementById('uploadPreview');
  var prompt = document.getElementById('uploadPrompt');
  var submitBtn = document.getElementById('submitProof');

  fileInput.addEventListener('change', async function(e) {
    var file = e.target.files[0];
    if (!file) return;
    if (isReservationFlow() && reservationSecondsLeft() <= 0) {
      renderReservationWarning();
      showToast('Payment session expired. Please checkout again.');
      fileInput.value = '';
      return;
    }
    if (file.size > 50 * 1024 * 1024) { showToast('Max 50MB for screenshots.'); fileInput.value = ''; return; }
    showToast('Processing screenshot...');
    try { pgSelectedFile = await compressPaymentImage(file, 2); } catch (err) { pgSelectedFile = file; }
    var reader = new FileReader();
    reader.onload = function(ev) {
      preview.src = ev.target.result;
      preview.classList.add('visible');
      prompt.style.display = 'none';
      submitBtn.style.opacity = '1';
      submitBtn.style.pointerEvents = 'auto';
    };
    reader.readAsDataURL(pgSelectedFile);
  });

  submitBtn.addEventListener('click', handleSubmit);
});

async function handleSubmit() {
  if (!pgSelectedFile) { showToast('Select a screenshot first.'); return; }
  if (isReservationFlow() && reservationSecondsLeft() <= 0) {
    renderReservationWarning();
    showToast('Payment session expired. Please checkout again.');
    return;
  }
  if (!isReservationFlow() && !pgOid) { showToast('Missing order info.'); return; }
  var submitBtn = document.getElementById('submitProof');
  submitBtn.textContent = 'Uploading...'; submitBtn.disabled = true;
  try {
    var form = new FormData();
    form.append('image', pgSelectedFile);
    form.append('type', 'upload_image');
    var uploadRes = await fetch('api.php', { method: 'POST', body: form });
    var uploadJson = await uploadRes.json();
    if (!uploadJson.ok || !uploadJson.url) { showToast('Upload failed.'); submitBtn.textContent = 'Submit Proof'; submitBtn.disabled = false; return; }

    var proofPayload = { type: 'payment_proof', screenshot: uploadJson.url };
    if (isReservationFlow()) {
      proofPayload.reservationId = pgReservationId;
      proofPayload.reservationToken = pgReservationToken;
    } else {
      proofPayload.orderId = pgOid;
    }
    var proofResult = await window.DEE_API.postJson(proofPayload);
    if (proofResult && proofResult.orderId) pgOid = String(proofResult.orderId || '').trim();
    if (!pgOid) throw new Error('Order could not be finalized. Please try again.');

    await showConfirmedView(pgOid);
  } catch (e) {
    if (e && (e.status === 409 || (e.payload && (e.payload.code === 'PRODUCT_UNAVAILABLE' || e.payload.code === 'PRODUCT_RACE_LOST')))) {
      showToast('This item is no longer available, or the payment session expired.');
      if (isReservationFlow()) renderReservationWarning(e && e.message ? e.message : '');
    } else {
      showToast(e.message || 'Something went wrong.');
    }
    var sessionStillActive = !isReservationFlow() || reservationSecondsLeft() > 0;
    submitBtn.textContent = sessionStillActive ? 'Submit Proof' : 'Session Expired';
    submitBtn.disabled = !sessionStillActive;
    if (sessionStillActive && pgSelectedFile) {
      submitBtn.style.opacity = '1';
      submitBtn.style.pointerEvents = 'auto';
    } else {
      submitBtn.style.opacity = '0.5';
      submitBtn.style.pointerEvents = 'none';
    }
  }
}

function copyIBAN() { copyText(document.getElementById('ibanText').textContent, 'IBAN copied'); }
function copyOrderMessage() {
  var box = document.getElementById('igMessage');
  var text = box && typeof box.value === 'string' ? box.value : (box ? box.textContent : '');
  copyText(text, 'Message copied');
}
function copyText(text, toast) {
  if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(function() { showToast(toast); });
  else {
    var ta = document.createElement('textarea');
    ta.value = text; ta.style.position = 'fixed'; ta.style.left = '-9999px';
    document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
    showToast(toast);
  }
}
</script>
</body>
</html>
