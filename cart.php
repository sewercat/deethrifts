<?php define('DEE_LOADED', true); require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cart - deethrifts</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" href="/assets/icon.ico" type="image/x-icon">
</head>
<body data-page="cart">

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

  <div class="glass-body r-top" style="padding:0; flex-direction:column;">
    <div class="section-label">Your Cart</div>
    <div id="cartContent"></div>
  </div>

  <div id="cartFooter" style="display:none; height:2px;"></div>
  <div id="cartTotals" style="display:none;">
    <div class="glass-body" style="flex-direction:column; padding:0;">
      <div class="cart-totals" id="totalsArea"></div>
    </div>
  </div>

  <div style="height:2px;"></div>
  <div id="checkoutBtn" style="display:none;">
    <div class="glass-body r-bot" style="padding:16px; justify-content:flex-end; align-items:center; gap:14px;">
      <a href="shop.php" style="font-size:0.85em; color:rgba(255,255,255,0.4);">Keep shopping</a>
      <a href="checkout.php" class="aero-btn pink-btn" style="text-decoration:none;">Checkout</a>
    </div>
  </div>

  <div id="emptyBottom" class="glass-body r-bot" style="display:none; padding:0; justify-content:center;">
    <div class="empty-state">
      <p>Your cart is empty.</p>
      <div style="margin-top:16px;">
        <a href="shop.php" class="aero-btn" style="text-decoration:none;">Browse the shop</a>
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
async function renderCart(skipSync) {
  if (!skipSync) {
    await Products.refresh();
    await Cart.syncWithInventory({ refreshProducts: false });
  }
  var cart = Cart.get();
  var content = document.getElementById('cartContent');

  if (cart.length === 0) {
    content.innerHTML = '';
    document.getElementById('cartFooter').style.display = 'none';
    document.getElementById('cartTotals').style.display = 'none';
    document.getElementById('checkoutBtn').style.display = 'none';
    document.getElementById('emptyBottom').style.display = 'flex';
    return;
  }

  document.getElementById('emptyBottom').style.display = 'none';
  document.getElementById('cartFooter').style.display = 'block';
  document.getElementById('cartTotals').style.display = 'block';
  document.getElementById('checkoutBtn').style.display = 'block';

  content.innerHTML = '<table class="cart-table"><thead><tr><th style="padding-left:16px;">Item</th><th>Details</th><th>Qty</th><th>Price</th></tr></thead><tbody id="cartBody"></tbody></table>';

  var tbody = document.getElementById('cartBody');
  cart.forEach(function(item) {
    var rowImg = (item.images && item.images[0]) || item.img;
    var rowImgHtml = getImageOrFallbackHtml(
      rowImg,
      item.name || 'Product',
      'cart-item-img',
      '',
      'cart-item-img no-image-fallback'
    );
    var tr = document.createElement('tr');
    tr.innerHTML = '<td style="padding-left:16px; width:60px;">' + rowImgHtml + '</td>' +
      '<td><div class="cart-item-name">' + item.name + '</div><div class="cart-item-meta">' + (item.meta || '') + '</div><button class="remove-btn cart-remove-inline" onclick="removeItem(\'' + item.id + '\')">Remove</button></td>' +
      '<td><div class="qty-ctrl"><button class="qty-btn" disabled></button><span class="qty-num">1</span><button class="qty-btn" disabled>+</button></div></td>' +
      '<td class="cart-item-price">' + formatPkr(item.price) + '</td>';
    tbody.appendChild(tr);
  });

  var subtotal = Cart.total();
  document.getElementById('totalsArea').innerHTML =
    '<div class="total-row"><span class="total-label">Subtotal</span><span class="total-val">' + formatPkr(subtotal) + '</span></div>' +
    '<div class="total-row"><span class="total-label">Shipping</span><span class="total-val">' + formatPkr(400) + '</span></div>' +
    '<div class="total-row big"><span class="total-label">Total</span><span class="total-val">' + formatPkr(subtotal + 400) + '</span></div>';
  Cart.updateBadge();
}

function removeItem(id) { Cart.remove(id); renderCart(); }
document.addEventListener('DOMContentLoaded', async function() {
  await renderCart();
  if (typeof window.startDeeAutoRefresh === 'function') {
    startDeeAutoRefresh({
      pollMs: 3000,
      refreshProducts: true,
      syncCart: true,
      onChange: function() { return renderCart(true); }
    });
  }
});
</script>
</body>
</html>
