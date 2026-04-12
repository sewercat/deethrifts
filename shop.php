<?php define('DEE_LOADED', true); require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shop - deethrifts</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" href="/assets/icon.ico" type="image/x-icon">
</head>
<body data-page="shop">

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

  <div id="viewCategories">
    <div class="glass-body r-top r-bot" style="padding:0; flex-direction:column;">
      <div class="section-label">Browse Categories</div>
      <div class="cat-grid" id="catGrid"></div>
    </div>
  </div>

  <div id="viewProducts" style="display:none;">
    <div class="glass-body r-top r-bot" style="padding:0; flex-direction:column;">
      <div style="display:flex; align-items:center; border-bottom:1px solid rgba(255,255,255,0.06); padding-right:14px;">
        <button class="back-btn" id="backBtn">Back</button>
        <div class="section-label" id="productSectionLabel" style="border:none; padding-left:0; padding-bottom:10px;"></div>
      </div>
      <div class="shop-grid" id="productGrid"></div>
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

  </div><!-- end siteContainer -->

  <div class="toast-msg" id="toastEl"></div>
<script src="app.js?v=<?php echo filemtime(__DIR__ . '/app.js'); ?>"></script>
<script>
let cachedCategories = [];
let currentCategoryKey = '';
let currentCategoryLabel = '';

document.addEventListener('DOMContentLoaded', async () => {
  await Products.refresh();
  await Cart.syncWithInventory({ refreshProducts: false });
  renderCategories();
  document.getElementById('backBtn').addEventListener('click', showCategoryView);

  var hash = window.location.hash.replace('#', '');
  var match = cachedCategories.find(function(c) { return c.key === hash; });
  if (match) showCategory(match.key, match.label);

  if (typeof window.startDeeAutoRefresh === 'function') {
    startDeeAutoRefresh({
      pollMs: 3000,
      refreshProducts: true,
      syncCart: true,
      onChange: function() {
        renderCategories();
        if (currentCategoryKey) showCategory(currentCategoryKey, currentCategoryLabel, true);
      }
    });
  }
});

function renderCategories() {
  var grid = document.getElementById('catGrid');
  grid.innerHTML = '';
  cachedCategories = getCategories();
  if (cachedCategories.length === 0) {
    grid.innerHTML = '<p style="color:rgba(255,255,255,0.45); font-size:0.9em;">No products available right now.</p>';
    return;
  }
  cachedCategories.forEach(function(cat) {
    var count = getCountByCategory(cat.key);
    var card = document.createElement('div');
    card.className = 'cat-card';
    var previewHtml = count === 0
      ? '<div class="cat-card-empty">no products to show</div>'
      : getImageOrFallbackHtml(cat.img, cat.label, 'cat-card-img', '', 'cat-card-img no-image-fallback');
    card.innerHTML = previewHtml +
      '<div class="cat-card-label">' + cat.label + '<span class="cat-card-count">' + count + ' items</span></div>';
    card.addEventListener('click', function() { showCategory(cat.key, cat.label); });
    grid.appendChild(card);
  });
}

function showCategory(key, label, silent) {
  currentCategoryKey = key || '';
  currentCategoryLabel = label || '';
  document.getElementById('viewCategories').style.display = 'none';
  document.getElementById('viewProducts').style.display = 'block';
  document.getElementById('productSectionLabel').textContent = label;
  var grid = document.getElementById('productGrid');
  grid.innerHTML = '';
  var products = getByCategory(key);
  if (products.length === 0) {
    grid.innerHTML = '<p style="color:rgba(255,255,255,0.45); font-size:0.9em;">No products in this category.</p>';
  } else {
    products.forEach(function(p) { grid.appendChild(buildCard(p)); });
  }
  if (!silent) {
    window.scrollTo({ top: 0, behavior: 'smooth' });
    window.location.hash = key;
  }
}

function showCategoryView() {
  currentCategoryKey = '';
  currentCategoryLabel = '';
  document.getElementById('viewProducts').style.display = 'none';
  document.getElementById('viewCategories').style.display = 'block';
  history.replaceState(null, '', window.location.pathname);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>
