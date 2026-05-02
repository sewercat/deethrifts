<?php
define('DEE_LOADED', true);
require_once 'config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$isAdmin = isAdminSessionValid();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    if ($_POST['admin_password'] === ADMIN_PASSWORD) {
        startAdminSession();
        $isAdmin = true;
    } else {
        clearAdminSession();
        session_regenerate_id(true);
        $isAdmin = false;
    }
}
if (isset($_GET['logout'])) {
    clearAdminSession();
    session_regenerate_id(true);
    header('Location: admin.php');
    exit;
}
$isAdmin = isAdminSessionValid();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - deethrifts</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" href="/assets/icon.ico" type="image/x-icon">
  <style>
    .db-table { width:100%; border-collapse:collapse; font-size:0.76em; }
    .db-table th { padding:6px 8px; background:rgba(255,255,255,0.06); color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:0.05em; font-size:0.85em; white-space:nowrap; text-align:left; }
    .db-table td { padding:4px 6px; border-bottom:1px solid rgba(255,255,255,0.04); vertical-align:top; }
    .db-cell { min-width:60px; max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:rgba(255,255,255,0.75); padding:3px 5px; border-radius:3px; cursor:text; border:1px solid transparent; transition:border-color 0.15s; }
    .db-cell:focus { outline:none; border-color:rgba(255,176,204,0.4); white-space:normal; background:rgba(0,0,0,0.3); max-width:none; }
    .db-cell.saving { border-color:rgba(255,220,100,0.4); }
    .db-cell.saved { border-color:rgba(100,220,100,0.4); }
    .tab-bar { display:flex; border-bottom:1px solid rgba(255,255,255,0.08); flex-wrap:wrap; }
    .tab-btn { padding:10px 18px; font-size:0.85em; cursor:pointer; background:none; border:none; color:rgba(255,255,255,0.45); transition:all 0.2s; border-bottom:2px solid transparent; }
    .tab-btn.active { color:#ffb0cc; border-bottom-color:#ffb0cc; }
    .tab-panel { display:none; }
    .tab-panel.active { display:block; }
    .selected-image-grid { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
    .selected-image-item { position:relative; width:64px; height:64px; border-radius:6px; overflow:hidden; border:1px solid rgba(255,255,255,0.12); }
    .selected-image-item img, .selected-image-item .no-image-fallback { width:100%; height:100%; display:block; object-fit:cover; }
    .selected-image-remove { position:absolute; top:2px; right:2px; width:18px; height:18px; border:none; border-radius:50%; cursor:pointer; background:rgba(0,0,0,0.6); color:#fff; font-size:12px; line-height:18px; padding:0; }
    .selected-image-empty { font-size:0.75em; color:rgba(255,255,255,0.4); margin-top:6px; }
    .product-editing-hint { display:none; font-size:0.8em; color:#ffb0cc; margin-top:10px; }
    .storage-bar { height:10px; background:rgba(255,255,255,0.08); border-radius:999px; overflow:hidden; margin-top:10px; }
    .storage-bar-fill { height:100%; width:0%; background:linear-gradient(90deg, rgba(255,176,204,0.85), rgba(255,130,170,0.95)); transition:width 0.25s ease; }
    .storage-list { display:flex; flex-direction:column; gap:10px; margin-top:10px; }
  </style>
</head>
<body>
<div id="siteContainer">
  <div class="spacer-sm"></div>

<?php if (!$isAdmin): ?>
  <div class="glass-body r-all" style="flex-direction:column;">
    <div class="admin-gate">
      <h2 style="font-size:1.2em; margin:0;">Admin Access</h2>
      <p style="text-align:center; max-width:100%; font-size:0.9em;">Enter the admin password.</p>
      <form method="POST" style="margin-top:18px;">
        <input class="glass-input" type="password" name="admin_password" placeholder="Password" style="text-align:center; font-size:1.2em; max-width:240px; margin:0 auto; display:block;" autofocus>
        <button class="aero-btn pink-btn" type="submit" style="margin-top:14px; min-width:220px;">Login</button>
      </form>
      <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isAdmin): ?>
        <p style="color:rgba(255,100,100,0.8); font-size:0.82em; margin-top:12px;">Wrong password.</p>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="glass-header r-top flex-center" style="padding:10px 16px; justify-content:space-between;">
    <h2 style="font-size:1.1em; margin:0;">Admin Panel</h2>
    <div style="display:flex; align-items:center; gap:14px;">
      <a href="index.php" style="font-size:0.82em; color:rgba(255,255,255,0.5);">Storefront</a>
      <a href="admin.php?logout=1" style="font-size:0.82em; color:rgba(255,255,255,0.5);">Logout</a>
    </div>
  </div>

  <div class="glass-body r-bot" style="flex-direction:column; padding:0;">
    <div class="tab-bar">
      <button class="tab-btn active" type="button" data-tab="products" onclick="switchTab('products', this)">Products</button>
      <button class="tab-btn" type="button" data-tab="orders" onclick="switchTab('orders', this)">Orders</button>
      <button class="tab-btn" type="button" data-tab="customers" onclick="switchTab('customers', this)">Customers</button>
      <button class="tab-btn" type="button" data-tab="donate" onclick="switchTab('donate', this)">Donate Cases</button>
      <button class="tab-btn" type="button" data-tab="sales" onclick="switchTab('sales', this)">Sales</button>
      <button class="tab-btn" type="button" data-tab="storage" onclick="switchTab('storage', this)">Storage</button>
      <button class="tab-btn" type="button" data-tab="database" onclick="switchTab('database', this)">Database</button>
      <button class="tab-btn" type="button" data-tab="settings" onclick="switchTab('settings', this)">Settings</button>
    </div>

    <div class="tab-panel active" id="tab-products">
      <div class="admin-section" style="border-bottom:1px solid rgba(255,255,255,0.06);">
        <div class="admin-section-title">Add New Product</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Name</label>
            <input class="glass-input" id="a-name" type="text" placeholder="Vintage Tee">
          </div>
          <div class="form-group">
            <label class="form-label">Garment Type</label>
            <select class="glass-input" id="a-garment">
              <option value="tops">Tops</option>
              <option value="bottoms">Bottoms</option>
              <option value="bags">Bags</option>
              <option value="desi">Desi</option>
              <option value="accessories">Accessories</option>
              <option value="dresses">Dresses</option>
              <option value="tech">Tech</option>
              <option value="misc">Misc</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Condition</label>
            <select class="glass-input" id="a-cond">
              <option value="10/10">10/10</option><option value="9/10">9/10</option><option value="8/10">8/10</option><option value="7/10">7/10</option><option value="6/10">6/10</option><option value="5/10">5/10</option><option value="4/10">4/10</option><option value="3/10">3/10</option><option value="2/10">2/10</option><option value="1/10">1/10</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Price (PKR)</label>
            <input class="glass-input" id="a-price" type="number" placeholder="2500" min="1" step="1">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Defects</label>
          <textarea class="glass-input" id="a-defects" rows="2" style="resize:vertical;">No known defects</textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Description (optional)</label>
          <textarea class="glass-input" id="a-description" rows="3" style="resize:vertical;" placeholder="Add extra details about this product..."></textarea>
        </div>
        <div class="form-group video-only-field">
          <label class="form-label">Working Video URL(s) (optional, comma-separated)</label>
          <input class="glass-input" id="a-video-url" type="text" placeholder="https://...">
        </div>
        <div class="form-group video-only-field">
          <label class="form-label">Video Files (max 5)</label>
          <input class="glass-input" id="a-video-files" type="file" accept="video/mp4,video/webm,video/ogg,video/quicktime,.mp4,.webm,.ogg,.mov" multiple>
          <p id="videoSelectionInfo" style="font-size:0.74em; color:rgba(255,255,255,0.4); margin-top:6px;">Videos are only enabled for tech and misc. Upload up to 5 videos (max 500MB each).</p>
          <div id="selectedVideoList" class="selected-image-empty">No videos selected.</div>
        </div>
        <div id="measurementBox"></div>
        <div class="form-group">
          <label class="form-label">Image URL(s) (optional, comma-separated)</label>
          <input class="glass-input" id="a-img-urls" type="text" placeholder="https://...">
        </div>
        <div class="form-group">
          <label class="form-label">Image Files (max 5)</label>
          <input class="glass-input" id="a-img-files" type="file" accept="image/*" multiple>
          <p id="imgSelectionInfo" style="font-size:0.74em; color:rgba(255,255,255,0.4); margin-top:6px;">Upload up to 5 images (max 50MB each). Images are auto-compressed.</p>
          <div id="selectedImageList" class="selected-image-empty">No images selected.</div>
        </div>
        <div id="productEditingHint" class="product-editing-hint">Editing posted product</div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
          <button class="aero-btn pink-btn" id="postNowBtn" type="button">Post This Product</button>
          <button class="aero-btn" id="saveDraftBtn" type="button">Save as Draft</button>
          <button class="aero-btn" id="cancelEditBtn" type="button" style="display:none;">Cancel Edit</button>
        </div>
      </div>

      <div class="admin-section" style="border-bottom:1px solid rgba(255,255,255,0.06);">
        <div class="admin-section-title">Drafts <span class="draft-count" id="draftCount">0</span></div>
        <div id="draftList"><p style="font-size:0.85em; color:rgba(255,255,255,0.3);">No drafts.</p></div>
        <div id="postDraftsWrap" style="display:none; margin-top:12px;">
          <button class="aero-btn pink-btn" id="postAllDraftsBtn" type="button">Post All Drafts</button>
        </div>
      </div>

      <div class="admin-section">
        <div class="admin-section-title">Live Products <span class="draft-count" id="liveCount">0</span></div>
        <div id="liveList"><p style="font-size:0.85em; color:rgba(255,255,255,0.3);">Loading...</p></div>
      </div>

      <div class="admin-section">
        <div class="admin-section-title">Category Covers</div>
        <p style="font-size:0.8em; color:rgba(255,255,255,0.45); margin-bottom:10px;">Choose which product image appears as the cover on the shop category cards.</p>
        <div id="categoryCoverList"><p style="font-size:0.85em; color:rgba(255,255,255,0.3);">Loading...</p></div>
      </div>
    </div>

    <div class="tab-panel" id="tab-orders">
      <div class="admin-section">
        <div class="admin-section-title">Orders <span class="draft-count" id="orderCount">0</span></div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
          <button class="aero-btn" type="button" onclick="filterOrders('')" id="fAll">All</button>
          <button class="aero-btn" type="button" onclick="filterOrders('pending')" id="fPending">Pending</button>
          <button class="aero-btn" type="button" onclick="filterOrders('awaiting_payment_proof')" id="fAwaitingProof">Awaiting Proof</button>
          <button class="aero-btn" type="button" onclick="filterOrders('pending_payment')" id="fPayment">Awaiting Payment</button>
          <button class="aero-btn" type="button" onclick="filterOrders('Confirmed')" id="fConfirmed">Confirmed</button>
          <button class="aero-btn" type="button" onclick="filterOrders('Delivered')" id="fDelivered">Delivered</button>
          <button class="aero-btn" type="button" onclick="filterOrders('Cancelled')" id="fCancelled">Cancelled</button>
        </div>
        <div id="orderList"><p style="font-size:0.85em; color:rgba(255,255,255,0.3);">Loading...</p></div>
      </div>
    </div>

    <div class="tab-panel" id="tab-customers">
      <div class="admin-section">
        <div class="admin-section-title">Customers <span class="draft-count" id="customerCount">0</span></div>
        <div id="customerList"><p style="font-size:0.85em; color:rgba(255,255,255,0.3);">Loading...</p></div>
      </div>
    </div>

    <div class="tab-panel" id="tab-donate">
      <div class="admin-section" style="border-bottom:1px solid rgba(255,255,255,0.06);">
        <div class="admin-section-title">Add / Edit Donate Case</div>
        <div class="form-row"><div class="form-group"><label class="form-label">Title</label><input class="glass-input" id="dc-title" type="text" placeholder="Ahmed's Medical Fund"></div><div class="form-group"><label class="form-label">Button Label</label><input class="glass-input" id="dc-label" type="text" placeholder="More Info" value="More Info"></div></div>
        <div class="form-group"><label class="form-label">Image URL</label><input class="glass-input" id="dc-img" type="text" placeholder="https://..."></div>
        <div class="form-group"><label class="form-label">Link URL</label><input class="glass-input" id="dc-link" type="text" placeholder="https://..."></div>
        <div class="form-group"><label class="form-label">Description</label><textarea class="glass-input" id="dc-desc" rows="4" placeholder="Tell the story..." style="resize:vertical;"></textarea></div>
        <div id="donateEditingHint" style="display:none; font-size:0.8em; color:#ffb0cc; margin-bottom:10px;">Editing existing case</div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;"><button class="aero-btn pink-btn" id="saveCaseBtn" type="button">Add Case</button><button class="aero-btn" id="clearCaseBtn" type="button">Clear</button></div>
      </div>
      <div class="admin-section"><div class="admin-section-title">Live Donate Cases <span class="draft-count" id="donateCaseCount">0</span></div><div id="donateCaseList"><p style="font-size:0.85em; color:rgba(255,255,255,0.3);">Loading...</p></div></div>
    </div>

    <div class="tab-panel" id="tab-sales">
      <div class="admin-section">
        <div class="admin-section-title">Launch Sale</div>
        <div class="form-row">
          <div class="form-group" style="max-width:260px;">
            <label class="form-label">Discount Percentage</label>
            <input class="glass-input" id="salePercentInput" type="number" min="1" max="90" step="1" placeholder="20">
          </div>
          <div class="form-group" style="display:flex; align-items:flex-end;">
            <button class="aero-btn pink-btn" id="launchSaleBtn" type="button">Launch Sale</button>
          </div>
        </div>
        <p style="font-size:0.8em; color:rgba(255,255,255,0.45); margin:0 0 12px;">Select products from the cards below, then launch the sale.</p>
        <div id="saleProductList"><p style="font-size:0.85em; color:rgba(255,255,255,0.3);">Loading products...</p></div>
      </div>
    </div>

    <div class="tab-panel" id="tab-database">
      <div class="admin-section">
        <div class="admin-section-title">Database Viewer</div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:14px;">
          <select class="glass-input" id="dbTableSelect" style="max-width:200px;"><option value="products">products</option><option value="orders">orders</option><option value="customers">customers</option><option value="donate_cases">donate_cases</option></select>
          <button class="aero-btn" id="dbLoadBtn" type="button">Load Table</button><span id="dbRowCount" style="font-size:0.8em; color:rgba(255,255,255,0.35);"></span>
        </div>
        <div id="dbTableContainer" style="overflow-x:auto;"></div>
      </div>
    </div>

    <div class="tab-panel" id="tab-settings">
    <div class="admin-section">
      <div class="admin-section-title">Shipping &amp; Fee Settings</div>
      <p style="font-size:0.8em; color:rgba(255,255,255,0.4); margin-bottom:16px;">Changes take effect immediately for all new orders.</p>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">COD Shipping Fee (Rs)</label>
          <input class="glass-input" id="set-shipping-fee" type="number" min="0" step="1" placeholder="300">
        </div>
        <div class="form-group">
          <label class="form-label">COD Tax Rate (e.g. 0.08 = 8%)</label>
          <input class="glass-input" id="set-tax-rate" type="number" min="0" max="1" step="0.01" placeholder="0.08">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Partial Payment Threshold (Rs)</label>
          <input class="glass-input" id="set-partial-threshold" type="number" min="0" step="100" placeholder="5000">
        </div>
        <div class="form-group">
          <label class="form-label">Partial Payment Amount (Rs)</label>
          <input class="glass-input" id="set-partial-amount" type="number" min="0" step="100" placeholder="1000">
        </div>
      </div>
      <div style="margin-top:6px; font-size:0.78em; color:rgba(255,255,255,0.35);">
        COD orders above the threshold require the partial amount as additional upfront payment alongside the shipping fee.
      </div>
      <div style="margin-top:14px; display:flex; align-items:center; gap:12px;">
        <button class="aero-btn pink-btn" id="saveSettingsBtn" type="button">Save Settings</button>
        <span id="settingsSavedHint" style="font-size:0.8em; color:rgba(100,220,140,0.8); display:none;">Saved!</span>
      </div>
    </div>
  </div>

    <div class="tab-panel" id="tab-storage">
      <div class="admin-section">
        <div class="admin-section-title">Storage Usage</div>
        <div id="storageUsageText" style="font-size:0.84em; color:rgba(255,255,255,0.72);">Loading storage info...</div>
        <div class="storage-bar"><div id="storageUsageFill" class="storage-bar-fill"></div></div>
        <div style="display:flex; gap:14px; flex-wrap:wrap; margin-top:8px; font-size:0.78em; color:rgba(255,255,255,0.55);">
          <span id="storageUsedText">Used: -</span>
          <span id="storageFreeText">Left: -</span>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px;">
          <button class="aero-btn" id="storageFilterAllBtn" type="button">All Files</button>
          <button class="aero-btn" id="storageFilterOrphansBtn" type="button">Orphans</button>
          <button class="aero-btn" id="storageFilterProofsBtn" type="button">Proofs</button>
        </div>
        <div id="storageFilterSummary" style="margin-top:8px; font-size:0.78em; color:rgba(255,255,255,0.6);">Showing all files.</div>
        <div style="margin-top:12px;">
          <button class="aero-btn" id="storageRefreshBtn" type="button">Refresh Storage</button>
        </div>
        <div id="storageFilteredList" class="storage-list" style="margin-top:12px;"><p style="font-size:0.85em; color:rgba(255,255,255,0.3);">Loading...</p></div>
      </div>

      <div class="admin-section">
        <div class="admin-section-title">Product Images <span class="draft-count" id="storageProductsCount">0</span></div>
        <div id="storageProductsList" class="storage-list"><p style="font-size:0.85em; color:rgba(255,255,255,0.3);">Loading...</p></div>
      </div>

      <div class="admin-section">
        <div class="admin-section-title">Proof Images <span class="draft-count" id="storageProofsCount">0</span></div>
        <div id="storageProofsList" class="storage-list"><p style="font-size:0.85em; color:rgba(255,255,255,0.3);">Loading...</p></div>
      </div>
    </div>

  </div>
<?php endif; ?>

  <div class="spacer-md"></div>
</div>

<div class="toast-msg" id="toastEl"></div>
<script src="app.js?v=<?php echo filemtime(__DIR__ . '/app.js'); ?>"></script>
<?php if ($isAdmin): ?>
<script>
window.DEE_ADMIN_CSRF = '<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>';
if (typeof window.switchTab !== 'function') {
  window.switchTab = function(name, btnEl) {
    document.querySelectorAll('.tab-panel').forEach(function(panel) { panel.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function(btn) { btn.classList.remove('active'); });
    var panel = document.getElementById('tab-' + name);
    if (panel) panel.classList.add('active');
    var activeBtn = btnEl || document.querySelector('.tab-btn[data-tab="' + name + '"]');
    if (activeBtn) activeBtn.classList.add('active');
    if (name === 'database' && typeof window.loadDbTable === 'function') window.loadDbTable();
    if (name === 'storage' && typeof window.renderStorage === 'function') window.renderStorage();
  };
}
</script>
<script src="admin.js?v=<?php echo filemtime(__DIR__ . '/admin.js'); ?>"></script>
<?php endif; ?>
</body>
</html>
