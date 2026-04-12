<?php define('DEE_LOADED', true); require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact - deethrifts</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" href="/assets/icon.ico" type="image/x-icon">
  <style>
    /* ── Grid Overlap Wrapper ─────────────────────────────── */
    .about-overlap-wrapper {
      display: grid;
      grid-template-columns: 1fr;
      align-items: center;
      justify-items: center;
      padding: 40px 18px;
      min-height: 600px;
      position: relative;
      overflow: hidden;
    }

    /* ── ASCII Background ─────────────────────── */
    .ascii-art {
      grid-area: 1 / 1;
      font-size: 0.85em; 
      margin: 0;
      line-height: 1.15;
      opacity: 1; 
      transform: translateX(-15%); 
      user-select: none;
      pointer-events: none;
    }

    /* ── Text Overlay ───────────────────────── */
    .info-side {
      grid-area: 1 / 1;
      transform: translate(25%, 10%); 
      width: 100%;
      max-width: 420px; 
      z-index: 2;
      
      background: transparent; 
      backdrop-filter: none;
      -webkit-backdrop-filter: none;
      border: none;
      box-shadow: none;
    }

    .info-side .about-text {
      font-family: "Times New Roman", Times, serif;
      font-size: 0.9em;
      line-height: 1.7;
      margin: 0 0 24px 0;
      color: rgba(255,255,255,0.8);
    }

    /* ── Contact Buttons ─────────────────────────────── */
    .contact-section {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-bottom: 28px;
    }

    .contact-label {
      font-family: "Times New Roman", Times, serif;
      font-size: 0.82em;
      color: rgba(255,255,255,0.45);
      text-transform: lowercase;
      letter-spacing: 0.5px;
      margin-bottom: 2px;
    }

    .contact-btn {
      display: inline-flex;
      align-items: center;
      text-decoration: none;
      gap: 10px;
      padding: 10px 20px;
      width: fit-content;
    }

    .contact-btn svg { flex-shrink: 0; }
    .contact-wa-btn { color: #25d366; }
    .contact-ig-btn { color: #e1306c; }

    /* ── Policies ─────────────────────────────── */
    .policies-label {
      font-size: 0.82em;
      color: rgba(255,255,255,0.45);
      text-transform: lowercase;
      letter-spacing: 0.5px;
      margin-bottom: 12px;
    }

    .policies-list {
      display: flex;
      flex-direction: column;
      gap: 0;
    }

    .pol {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      font-size: 0.85em;
      line-height: 1.65;
      color: rgba(255,255,255,0.85); 
    }

    .pol:last-child { border-bottom: none; }

    .pol-dot {
      flex-shrink: 0;
      width: 6px;
      height: 6px;
      border-radius: 50%;
      margin-top: 6px;
      background: rgba(255,176,204,0.5);
    }

    .pol-dot.warn { background: rgba(255,200,100,0.6); }
    .pol-dot.red  { background: rgba(255,100,100,0.55); }

    .pol strong {
      color: #fff;
      font-weight: 500;
    }

    .pol .hl { color: #ffb0cc; font-weight: 500; }
    .pol .hl-w { color: #ffd78a; font-weight: 500; }
    .pol .hl-r { color: #ff9090; font-weight: 500; }

    /* ── Mobile Adjustments ─────────────────────────────── */
    @media (max-width: 800px) {
      .about-overlap-wrapper {
        min-height: 520px;
      }
      .ascii-art { 
        transform: translateX(0); /* Centers on mobile */
        font-size: 0.6em; 
        opacity: 0.2; /* Faded more so text remains readable */
      } 
      .info-side {
        transform: translateX(0); /* Centers on mobile */
        width: 90%;
      }
      .pol { font-size: 0.8em; padding: 8px 0; }
    }
  </style>
</head>
<body data-page="contact">

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

  <div class="glass-header r-top flex-center" style="padding:10px 16px;">
    <h2 style="font-size:1.2em; margin:0;">About &amp; Contact</h2>
  </div>

  <div class="glass-body r-bot" style="display: flex; flex-direction: column; padding: 0;">

    <div class="about-overlap-wrapper">

      <pre class="ascii-art">
⠀⠀⠀⠀⠀⣠⣤⡦⣤⣬⣍⡉⠒⢤⡀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⣠⠒⠉⠁⠀⠀⠀⠀⠈⠙⠻⣦⣜⠳⣄⢀⣀⣀⣠⣄⣀⣀⣀⡀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⡜⠁⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⢈⢿⡖⠛⣯⠀⠀⣀⣀⣀⣀⡀⠈⠉⠓⠒⠠⠤⣀⣀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⣼⣯⠙⣦⠀
⣼⠁⠀⠀⠀⠀⠀⠀⠀⠀⠀⣠⠞⠃⣤⣽⣶⠾⣿⠉⠉⠉⠉⠉⠉⠙⠓⢤⡀⠀⠀⠀⠉⠙⠒⠶⢤⣤⣀⡀⠀⠀⠀⠀⠨⠍⢀⡾⠃
⢿⠀⠀⠀⠀⠀⠀⠀⠀⡠⠊⣠⣶⣿⠋⠉⣏⠀⢹⡀⠀⠀⠀⠀⠀⠀⠀⠀⠈⠣⡀⠀⠀⠀⠀⠀⠀⠀⠈⠉⠉⠛⠓⠲⠖⠚⠉⠀⠀
⠘⣆⢀⣤⡀⠀⠀⠀⡼⢥⣶⠿⠙⠀⠀⠀⡇⠀⢸⡇⠀⠀⠀⠀⢠⣦⣤⠀⠀⠀⢹⡀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠈⠻⣿⡟⠀⠀⣾⣽⡿⠏⠁⠀⠀⠀⡸⠁⠀⣾⠀⠀⠀⠀⠀⢯⠙⠃⠀⠀⠀⣸⡇⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠀⣑⣿⡟⠃⠀⠀⠀⠀⡴⠁⠀⣰⠏⠃⠀⠀⠀⠀⠘⢦⣄⣀⣠⡴⠃⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⢀⠀⢀⣾⠟⠐⢲⡷⠶⢶⡞⠁⠀⣴⠟⠀⠀⠀⠀⠀⠀⠀⠀⠈⠉⠉⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⢰⢿⡿⣾⡏⠀⠀⠀⠁⢰⠏⠀⢀⡾⡉⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠸⣌⢠⡿⠄⠀⢀⣠⡶⠁⠀⡰⠏⠁⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠈⣿⡟⠛⠋⢡⠞⠀⣠⠞⠉⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⢀⢠⡟⠀⠀⢠⠃⠀⠀⠁⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⡎⢸⠇⠀⠀⡏⠀⠀⠀⠀⣴⣗⠲⢤⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⢀⡇⣼⠀⠀⢸⡇⠀⠀⠀⠈⠛⠿⠃⠘⣧⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠘⡇⣧⠀⠀⠀⢷⡀⠀⠀⠀⠀⠀⢀⡼⠁⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⡇⢸⠀⠀⠀⠈⠻⢶⣤⣤⣤⠶⠋⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⢃⠸⡄⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⢸⠀⢧⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠸⡄⠘⣆⠀⠀⣰⣦⡄⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⢧⠀⠉⠳⢤⣽⡿⠙⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⢀⠸⡄⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠸⡆⢳⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⢸⡘⡇⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⢧⢸⡀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠸⡄⢿⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⢇⠘⣆⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⢸⠀⢹⡆⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⠸⡄⠀⢷⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⣠⡀⠈⡇⠀⢸⡇⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⢸⢿⠇⠀⡇⠀⣸⡇⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠸⣄⣀⢰⣧⣴⡿⠁⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠈⠙⢻⣿⠟⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⠀⣼⠁⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠀⢀⡿⠤⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⢀⣠⣿⡇⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⢸⠿⠁⣿⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⢰⡇⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⡿⠐⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⢸⠇⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⡞⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠰⣿⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⢫⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠘⡄⠀⠀⢀⣤⣀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀
⠀⠀⠀⠘⠢⣀⣘⣿⠋⠀⠀⠀⠀⠀
      </pre>

      <div class="info-side">
        <div class="contact-section">
          <a href="https://wa.me/923307969536" target="_blank" class="aero-btn contact-btn contact-wa-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            <span>WhatsApp</span>
          </a>
          <a href="https://instagram.com/deethrifts.pk" target="_blank" class="aero-btn contact-btn contact-ig-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            <span>Instagram</span>
          </a>
        </div>

        <div class="policies-label">policies</div>
        <div class="policies-list">
          <div class="pol"><span class="pol-dot"></span><span><strong>First come, first serve</strong></span></div>
          <div class="pol"><span class="pol-dot"></span><span><strong>No returns or exchanges.</strong> Confirm measurements and condition before ordering.</span></div>
          <div class="pol"><span class="pol-dot"></span><span><strong>Max 3 delivery attempts</strong> until the parcel is cancelled</span></div>
          <div class="pol"><span class="pol-dot"></span><span><strong>Respect is mutual.</strong> Unreasonable behaviour will not be tolerated.</span></div>
          <div class="pol"><span class="pol-dot red"></span><span><strong>Account flagging</strong> disrespect, cancelling, or rejecting parcels without reason will get your details <span class="hl-r">blocked</span>.</span></div>
          <div class="pol"><span class="pol-dot warn"></span><span><strong>COD orders above <span class="hl-w">Rs 5,000</span></strong> require a partial amount to be paid online upfront.</span></div>
          <div class="pol"><span class="pol-dot"></span><span><strong>No bookings are available.</strong></span></div>
        </div>
      </div>

    </div>
  </div>

  <div class="spacer-md"></div>

  <div class="glass-header r-all" style="padding:0;">
    <div class="footer-content">
      <span>(c) 2025 <span class="logo-text">dee<span class="pink">thrifts</span></span> — all items are pre-owned and sold as-is</span>
    </div>
  </div>
  <div class="spacer-md"></div>
</div>

<script src="app.js?v=<?php echo filemtime(__DIR__ . '/app.js'); ?>"></script>
</body>
</html>
