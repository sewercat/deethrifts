<?php define('DEE_LOADED', true); require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Donate - deethrifts</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" href="/assets/icon.ico" type="image/x-icon">
</head>
<body data-page="donate">
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

  <div class="glass-body r-all" style="flex-direction:column; padding:0; overflow:hidden;">
    <div class="donate-hero">
      <div class="donate-hero-inner">
        <div class="donate-hero-icon">♡</div>
        <h2 style="font-size:1.5em; margin:0 0 10px;">give a little.</h2>
        <p style="margin:0; max-width:460px; text-align:center; color:rgba(255,255,255,0.55); font-size:0.95em; line-height:1.7;">
          a portion of every sale goes toward causes i care about. here are people and organisations doing real work; if you can help, even a small amount matters.
        </p>
      </div>
    </div>
  </div>

  <div class="spacer-sm"></div>

  <div id="casesList">
    <div class="glass-body r-all" style="justify-content:center; padding:48px;">
      <p style="color:rgba(255,255,255,0.3); font-size:0.9em; margin:0;">Loading...</p>
    </div>
  </div>

  <div class="spacer-sm"></div>

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
function renderCases(cases) {
  var el = document.getElementById('casesList');
  if (!cases.length) {
    el.innerHTML = '<div class="glass-body r-all" style="justify-content:center; padding:48px;">' +
      '<p style="color:rgba(255,255,255,0.3); font-size:0.9em; margin:0;">No cases posted yet.</p></div>';
    return;
  }

  el.innerHTML = cases.map(function(c, i) {
    var isFirst = i === 0;
    var isLast = i === cases.length - 1;
    var rClass = cases.length === 1 ? 'r-all' : isFirst ? 'r-top' : isLast ? 'r-bot' : '';
    var sep = !isLast ? '<div style="height:2px;"></div>' : '';
    var imgHtml = c.image_url
      ? '<div class="donate-card-img-wrap"><img class="donate-card-img" src="' + c.image_url + '" alt="' + c.title + '" onerror="this.parentElement.style.display=\'none\'"></div>'
      : '';
    var linkHtml = c.link_url
      ? '<a href="' + c.link_url + '" target="_blank" rel="noopener" class="aero-btn pink-btn donate-more-btn">' + (c.link_label || 'More Info') + ' &rarr;</a>'
      : '';

    return '<div class="glass-body ' + rClass + ' donate-card">' +
      imgHtml +
      '<div class="donate-card-body">' +
        '<h3 class="donate-card-title">' + c.title + '</h3>' +
        '<p class="donate-card-desc">' + (c.description || '').replace(/\n/g, '<br>') + '</p>' +
        '<div style="display:flex; align-items:center; gap:10px; margin-top:auto; padding-top:18px;">' + linkHtml + '</div>' +
      '</div>' +
      '</div>' + sep;
  }).join('');
}

async function loadCases() {
  try {
    var data = await window.DEE_API.getJson({ action: 'donate_cases' });
    renderCases(data.cases || []);
  } catch (e) {
    document.getElementById('casesList').innerHTML =
      '<div class="glass-body r-all" style="justify-content:center; padding:48px;">' +
      '<p style="color:rgba(255,100,100,0.5); font-size:0.9em; margin:0;">Could not load cases.</p></div>';
  }
}

document.addEventListener('DOMContentLoaded', async function() {
  await loadCases();
  if (typeof window.startDeeAutoRefresh === 'function') {
    startDeeAutoRefresh({
      pollMs: 3000,
      refreshProducts: false,
      syncCart: false,
      onChange: loadCases
    });
  }
});
</script>
</body>
</html>
