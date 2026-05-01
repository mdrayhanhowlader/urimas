
<?php
require_once 'config.php';

// Load settings
try {
    $settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
} catch (Exception $e) {
    $settings = [];
}

$shop_name = htmlspecialchars($settings['shop_name'] ?? 'Urimas Books');

// Banner
$banner_enabled  = (int)($settings['banner_enabled'] ?? 0);
$banner_title    = htmlspecialchars($settings['banner_title'] ?? '');
$banner_subtitle = htmlspecialchars($settings['banner_subtitle'] ?? '');
$BANNER_DIR  = __DIR__ . '/assets/images/banner/';
$BANNER_URL  = 'assets/images/banner/';
$banner_image   = $settings['banner_image'] ?? '';
$banner_img_url = ($banner_image && file_exists($BANNER_DIR.$banner_image)) ? $BANNER_URL.$banner_image : '';

// bKash
$bkash_mode = $settings['bkash_mode'] ?? 'manual';

// Theme / Marketing
$theme_accent = $settings['theme_accent'] ?? '#B5183D';
$bg_color     = $settings['bg_color']     ?? '';
$pixel_id     = preg_replace('/[^0-9]/', '', $settings['pixel_id'] ?? '');
$country_code = preg_replace('/[^+0-9]/', '', $settings['country_code'] ?? '+880') ?: '+880';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $shop_name ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg: #FFF5F7;
      --card: #ffffff;
      --accent: #B5183D;
      --accent-light: #FCE8EE;
      --accent-dark: #8B1A2B;
      --sakura: #F8C8D4;
      --text: #2C1810;
      --muted: #9B7B82;
      --border: #F0D0D8;
      --shadow-sm: 0 1px 3px rgba(181,24,61,.07), 0 1px 2px rgba(0,0,0,.04);
      --shadow: 0 4px 16px rgba(181,24,61,.12), 0 1px 4px rgba(0,0,0,.06);
      --shadow-lg: 0 12px 40px rgba(181,24,61,.2), 0 4px 12px rgba(0,0,0,.08);
      --radius: 16px;
      --radius-sm: 10px;
    }

    html { scroll-behavior: smooth; }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      padding-bottom: 120px;
    }

    /* ─── HEADER ─── */
    .header {
      position: sticky; top: 0; z-index: 100;
      background: rgba(255,255,255,0.92);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border);
    }
    .header::after {
      content: ''; display: block; height: 2px;
      background: linear-gradient(90deg, var(--accent-grad), var(--accent), var(--accent-dark));
    }
    .header-inner {
      max-width: 1100px; margin: 0 auto;
      height: 58px; display: flex; align-items: center; justify-content: space-between;
      padding: 0 20px;
    }
    .shop-name {
      font-size: 1.2rem; font-weight: 800; letter-spacing: -.5px;
      background: linear-gradient(135deg, var(--accent-grad) 0%, var(--accent) 100%);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
      text-decoration: none;
    }
    .header-badge {
      background: var(--accent); color: #fff;
      font-size: .75rem; font-weight: 700;
      padding: 5px 14px; border-radius: 20px;
      opacity: 0; transform: scale(.8);
      transition: all .3s cubic-bezier(.34,1.56,.64,1);
    }
    .header-badge.visible { opacity: 1; transform: scale(1); }

    /* ─── PAGE CONTENT ─── */
    .page { max-width: 1100px; margin: 0 auto; padding: 24px 16px 16px; }

    .section-label {
      font-size: .8rem; font-weight: 600; letter-spacing: .08em;
      text-transform: uppercase; color: var(--muted);
      margin-bottom: 16px;
    }

    /* ─── PRODUCT GRID ─── */
    .products-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 32px;
    }
    @media (max-width: 767px) {
      .products-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    }
    @media (min-width: 768px) and (max-width: 1000px) {
      .products-grid { grid-template-columns: repeat(3, 1fr); }
    }

    /* ─── PRODUCT CARD ─── */
    .product-card {
      background: var(--card);
      border-radius: var(--radius);
      border: 2px solid var(--border);
      box-shadow: 0 2px 8px rgba(0,0,0,.05);
      cursor: pointer;
      position: relative;
      overflow: hidden;
      display: flex; flex-direction: column;
      transition: transform .2s cubic-bezier(.34,1.3,.64,1), box-shadow .2s, border-color .15s;
      user-select: none;
      -webkit-tap-highlight-color: transparent;
      touch-action: manipulation;
      animation: fadeUp .45s ease both;
    }
    .product-card:hover { transform: translateY(-4px); box-shadow: 0 8px 28px rgba(181,24,61,.13); border-color: var(--accent); }
    .product-card:active { transform: scale(.97); transition: transform .08s; }
    .product-card.selected {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(181,24,61,.15), 0 4px 18px rgba(181,24,61,.12);
    }
    .product-card.selected:active { transform: scale(.98); }
    .product-card.selected .card-overlay { opacity: 1; }
    .product-card:nth-child(1) { animation-delay: .04s; }
    .product-card:nth-child(2) { animation-delay: .08s; }
    .product-card:nth-child(3) { animation-delay: .12s; }
    .product-card:nth-child(4) { animation-delay: .16s; }
    .product-card:nth-child(5) { animation-delay: .20s; }
    .product-card:nth-child(6) { animation-delay: .24s; }

    .card-overlay {
      position: absolute; inset: 0;
      background: linear-gradient(135deg, rgba(181,24,61,.06), transparent);
      opacity: 0; transition: opacity .2s; pointer-events: none;
    }

    .card-check {
      position: absolute; top: 8px; right: 8px;
      width: 26px; height: 26px;
      background: rgba(255,255,255,.92); border: 2px solid var(--border);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      transition: all .2s cubic-bezier(.34,1.56,.64,1);
      z-index: 2; box-shadow: 0 1px 4px rgba(0,0,0,.1);
    }
    .product-card.selected .card-check {
      background: var(--accent); border-color: var(--accent);
      box-shadow: 0 2px 8px rgba(181,24,61,.25);
      transform: scale(1.12);
    }
    .card-check i {
      font-size: .65rem; color: #fff; opacity: 0; transform: scale(0);
      transition: all .16s cubic-bezier(.34,1.56,.64,1);
    }
    .product-card.selected .card-check i { opacity: 1; transform: scale(1); }

    .card-img-wrap {
      width: 100%; aspect-ratio: 1/1;
      background: var(--accent-light);
      overflow: hidden; display: flex; align-items: center; justify-content: center; position: relative;
      flex-shrink: 0;
    }
    .card-img-wrap img {
      width: 100%; height: 100%; object-fit: cover;
      transition: transform .4s ease; display: block;
    }
    .product-card:hover .card-img-wrap img { transform: scale(1.06); }
    .card-img-placeholder {
      font-size: 2.5rem; opacity: .18;
      display: flex; flex-direction: column; align-items: center; gap: 6px;
    }
    .card-img-placeholder span { font-size: .68rem; font-weight: 700; color: var(--accent); opacity: .8; }

    .card-body { padding: 11px 12px 0; flex: 1; display: flex; flex-direction: column; }
    @media (max-width: 767px) { .card-body { padding: 9px 10px 0; } }

    .card-brand {
      font-size: .63rem; font-weight: 700; color: var(--muted);
      text-transform: uppercase; letter-spacing: .06em; margin-bottom: 3px;
    }

    .card-name {
      font-size: .88rem; font-weight: 700; line-height: 1.32; color: var(--text); margin-bottom: 2px;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    @media (max-width: 767px) { .card-name { font-size: .8rem; } }

    .card-desc {
      font-size: .69rem; color: var(--muted); margin-bottom: 5px;
      display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
    }

    .card-price { font-size: 1rem; font-weight: 800; color: var(--accent); margin-top: 4px; }
    @media (max-width: 767px) { .card-price { font-size: .9rem; } }

    /* Variant / size chips */
    .card-variants {
      display: flex; flex-wrap: wrap; gap: 5px; margin-top: 9px; padding-bottom: 2px;
    }
    .variant-chip {
      padding: 3px 10px; border-radius: 5px;
      border: 1.5px solid var(--border);
      font-size: .68rem; font-weight: 700; line-height: 1.5;
      cursor: pointer; background: transparent; color: var(--muted);
      font-family: inherit; white-space: nowrap;
      transition: all .15s; user-select: none;
      -webkit-tap-highlight-color: transparent;
    }
    .variant-chip.active {
      border-color: var(--accent); background: var(--accent); color: #fff;
    }
    .variant-chip:hover:not(.active) {
      border-color: var(--accent); color: var(--accent); background: var(--accent-light);
    }
    @media (max-width: 767px) { .variant-chip { font-size: .64rem; padding: 2px 8px; } }

    /* Mobile tap bar at bottom of card */
    .card-tap-bar {
      margin-top: 10px;
      padding: 9px 12px;
      background: var(--accent-light);
      border-top: 1px solid var(--border);
      font-size: .7rem; font-weight: 700; color: var(--accent);
      text-align: center; letter-spacing: .02em;
      transition: background .15s, color .15s;
      flex-shrink: 0;
    }
    .product-card.selected .card-tap-bar {
      background: var(--accent); color: #fff;
    }
    .card-select-hint {
      font-size: .68rem; color: var(--muted); margin-top: 2px; font-weight: 500;
    }
    .product-card.selected .card-select-hint { color: var(--accent); font-weight: 600; }

    /* ─── FLOATING ORDER BAR ─── */
    .order-bar {
      position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(100px);
      z-index: 200;
      background: linear-gradient(135deg, var(--accent-grad) 0%, var(--accent) 100%);
      color: #fff;
      border-radius: 50px;
      padding: 14px 24px;
      display: flex; align-items: center; gap: 14px;
      box-shadow: 0 8px 32px rgba(181,24,61,.45), 0 2px 8px rgba(0,0,0,.1);
      min-width: 300px; max-width: 90vw;
      transition: transform .4s cubic-bezier(.34,1.3,.64,1), opacity .3s ease;
      opacity: 0;
      cursor: default;
    }
    .order-bar.visible {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
    }
    .order-bar-info { flex: 1; }
    .order-bar-count { font-size: .75rem; font-weight: 600; opacity: .85; }
    .order-bar-total { font-size: 1.1rem; font-weight: 800; }
    .order-bar-btn {
      background: #fff; color: var(--accent);
      border: none; border-radius: 30px;
      padding: 10px 22px; font-size: .9rem; font-weight: 700;
      cursor: pointer; white-space: nowrap;
      transition: transform .2s, box-shadow .2s;
      font-family: inherit;
    }
    .order-bar-btn:hover { transform: scale(1.04); box-shadow: 0 4px 12px rgba(0,0,0,.15); }
    .order-bar-btn:active { transform: scale(.97); }

    /* ─── ORDER FORM SECTION ─── */
    .order-section {
      display: none;
      animation: fadeUp .4s ease both;
    }
    .order-section.open { display: block; }

    .order-card {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .order-header {
      background: linear-gradient(135deg, var(--accent-grad) 0%, var(--accent) 100%);
      color: #fff; padding: 20px 24px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .order-header h2 { font-size: 1.1rem; font-weight: 700; }
    .order-back-btn {
      background: rgba(255,255,255,.2); border: none; color: #fff;
      border-radius: 8px; padding: 6px 14px;
      font-size: .8rem; font-weight: 600; cursor: pointer;
      font-family: inherit; transition: background .2s;
    }
    .order-back-btn:hover { background: rgba(255,255,255,.3); }

    .order-summary {
      padding: 16px 20px;
      background: var(--accent-light);
      border-bottom: 1px solid rgba(91,79,207,.1);
    }
    .order-summary-title { font-size: .75rem; font-weight: 600; color: var(--accent); margin-bottom: 10px; text-transform: uppercase; letter-spacing: .05em; }
    .order-item {
      display: flex; align-items: center; justify-content: space-between;
      padding: 6px 0; font-size: .88rem;
    }
    .order-item-name { font-weight: 600; color: var(--text); }
    .order-item-price { font-weight: 700; color: var(--accent); }
    .order-item-remove {
      background: none; border: none; color: #ef4444;
      font-size: .8rem; cursor: pointer; padding: 2px 6px;
      margin-left: 8px; border-radius: 4px;
      transition: background .15s;
    }
    .order-item-remove:hover { background: #fee2e2; }
    .order-totals { border-top: 1px solid rgba(91,79,207,.15); margin-top: 8px; padding-top: 10px; }
    .order-total-row {
      display: flex; justify-content: space-between;
      font-size: .84rem; color: var(--muted); margin-bottom: 4px;
    }
    .order-total-row.grand {
      font-size: 1rem; font-weight: 800; color: var(--text);
      margin-bottom: 0; margin-top: 6px;
    }
    .order-total-row.delivery-set { color: var(--text); }
    .order-total-row.delivery-set span:last-child { color: var(--accent); font-weight: 700; }

    .form-body { padding: 20px; }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }

    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full { grid-column: 1 / -1; }

    label {
      font-size: .78rem; font-weight: 600; color: var(--muted);
      text-transform: uppercase; letter-spacing: .04em;
    }

    input[type=text], input[type=tel], input[type=email], textarea, select {
      background: var(--bg); border: 1.5px solid var(--border);
      border-radius: var(--radius-sm); padding: 12px 14px;
      font-size: .92rem; font-family: inherit; color: var(--text);
      outline: none; transition: border-color .2s, box-shadow .2s;
      width: 100%;
    }
    input:focus, textarea:focus, select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(181,24,61,.1);
    }
    textarea { resize: vertical; min-height: 80px; }

    /* Phone with country code prefix */
    .phone-wrap {
      display: flex; border: 1.5px solid var(--border);
      border-radius: var(--radius-sm); overflow: hidden;
      background: var(--bg); transition: border-color .2s, box-shadow .2s;
    }
    .phone-wrap:focus-within {
      border-color: var(--accent); box-shadow: 0 0 0 3px rgba(181,24,61,.1);
    }
    .phone-code {
      padding: 12px 13px; background: var(--accent-light);
      color: var(--accent); font-weight: 700; font-size: .88rem;
      border-right: 1.5px solid var(--border); white-space: nowrap;
      display: flex; align-items: center; flex-shrink: 0;
    }
    .phone-wrap input[type=tel] {
      flex: 1; border: none; border-radius: 0; padding: 12px 14px;
      background: transparent; box-shadow: none !important;
      min-width: 0;
    }
    .phone-wrap input[type=tel]:focus { box-shadow: none !important; }

    /* Payment toggle */
    .payment-toggle {
      display: flex; gap: 10px; margin-top: 2px;
    }
    .pay-btn {
      flex: 1; padding: 12px 8px;
      border: 2px solid var(--border);
      border-radius: var(--radius-sm);
      background: var(--bg); color: var(--muted);
      font-family: inherit; font-size: .88rem; font-weight: 600;
      cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
      transition: all .2s;
    }
    .pay-btn.active {
      border-color: var(--accent); background: var(--accent-light);
      color: var(--accent);
    }
    .pay-btn i { font-size: 1rem; }

    .bkash-info {
      background: #fff3f3; border: 1.5px solid #fca5a5;
      border-radius: var(--radius-sm); padding: 14px 16px;
      display: none; align-items: center; justify-content: space-between;
      margin-top: 12px;
    }
    .bkash-info.show { display: flex; }
    .bkash-label { font-size: .75rem; font-weight: 600; color: #b91c1c; }
    .bkash-number { font-size: 1.1rem; font-weight: 800; color: #dc2626; }
    .copy-btn {
      background: #dc2626; color: #fff;
      border: none; border-radius: 6px;
      padding: 6px 14px; font-size: .78rem; font-weight: 600;
      cursor: pointer; font-family: inherit;
      transition: background .15s;
    }
    .copy-btn:hover { background: #b91c1c; }

    .transaction-field { display: none; margin-top: 12px; }
    .transaction-field.show { display: block; }

    .submit-btn {
      width: 100%; padding: 15px;
      background: linear-gradient(135deg, var(--accent-grad) 0%, var(--accent) 100%);
      color: #fff; border: none; border-radius: var(--radius-sm);
      font-size: 1rem; font-weight: 700; font-family: inherit;
      cursor: pointer; margin-top: 20px;
      transition: transform .2s, box-shadow .2s, opacity .2s;
      box-shadow: 0 4px 16px rgba(181,24,61,.35);
    }
    .submit-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(181,24,61,.45); }
    .submit-btn:active { transform: translateY(0); }
    .submit-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

    /* ─── SUCCESS MODAL ─── */
    .modal-overlay {
      position: fixed; inset: 0; z-index: 300;
      background: rgba(0,0,0,.5);
      display: flex; align-items: center; justify-content: center;
      padding: 20px;
      opacity: 0; pointer-events: none;
      transition: opacity .3s;
    }
    .modal-overlay.show { opacity: 1; pointer-events: all; }
    .modal {
      background: var(--card); border-radius: var(--radius);
      padding: 32px 28px; max-width: 420px; width: 100%;
      text-align: center;
      transform: scale(.9) translateY(20px);
      transition: transform .35s cubic-bezier(.34,1.3,.64,1);
      box-shadow: var(--shadow-lg);
    }
    .modal-overlay.show .modal { transform: scale(1) translateY(0); }
    .modal-icon { font-size: 3rem; margin-bottom: 16px; }
    .modal h3 { font-size: 1.3rem; font-weight: 800; color: var(--text); margin-bottom: 8px; }
    .modal p { color: var(--muted); font-size: .9rem; line-height: 1.6; margin-bottom: 20px; }
    .modal-close {
      background: var(--accent); color: #fff;
      border: none; border-radius: 30px;
      padding: 12px 32px; font-size: .95rem; font-weight: 700;
      cursor: pointer; font-family: inherit;
      transition: transform .2s, box-shadow .2s;
    }
    .modal-close:hover { transform: scale(1.04); box-shadow: 0 4px 16px rgba(181,24,61,.35); }

    /* ─── BANNER ─── */
    .banner-hero {
      position: relative; overflow: hidden;
      background: linear-gradient(140deg, #5C0018 0%, var(--accent) 55%, #C8224A 100%);
      padding: 58px 20px 92px;
      text-align: center;
    }
    .banner-hero.banner-has-img { padding: 0; background: #0a0006; }

    .banner-hero-img {
      width: 100%; height: 280px; object-fit: cover; display: block; opacity: .82;
    }
    .banner-img-overlay {
      position: absolute; inset: 0;
      background: linear-gradient(160deg,
        rgba(6,0,3,.82) 0%, rgba(181,24,61,.46) 45%, rgba(6,0,3,.78) 100%);
    }

    /* Decorative elements (text-only) */
    .banner-blob {
      position: absolute; border-radius: 50%;
      background: rgba(255,255,255,.07); filter: blur(52px); pointer-events: none;
    }
    .banner-blob-1 { width: 380px; height: 380px; top: -140px; right: -90px; }
    .banner-blob-2 { width: 260px; height: 260px; bottom: -100px; left: -55px; }
    .banner-blob-3 { width: 160px; height: 160px; top: 30px; left: 35%;
                     background: rgba(255,255,255,.04); }
    .banner-dots {
      position: absolute; inset: 0;
      background-image: radial-gradient(rgba(255,255,255,.08) 1.5px, transparent 1.5px);
      background-size: 26px 26px; pointer-events: none;
    }

    /* Content */
    .banner-inner {
      position: relative; z-index: 2;
      max-width: 720px; margin: 0 auto;
    }
    .banner-has-img .banner-inner {
      position: absolute; z-index: 4;
      bottom: 54px; left: 0; right: 0; padding: 0 24px;
    }

    .banner-badge-pill {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(255,255,255,.14); color: rgba(255,255,255,.92);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,.22);
      border-radius: 50px; padding: 5px 16px;
      font-size: .72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
      margin-bottom: 16px;
    }
    .banner-hl {
      font-size: 2rem; font-weight: 800; color: #fff;
      line-height: 1.18; letter-spacing: -.02em;
      text-shadow: 0 2px 24px rgba(0,0,0,.28);
      margin-bottom: 12px;
    }
    .banner-sl {
      font-size: .92rem; color: rgba(255,255,255,.82);
      line-height: 1.65; margin-bottom: 26px; font-weight: 400;
    }
    .banner-has-img .banner-sl { margin-bottom: 0; }
    .banner-cta {
      display: inline-flex; align-items: center; gap: 8px;
      background: #fff; color: var(--accent);
      font-weight: 800; font-size: .88rem; font-family: inherit;
      padding: 12px 30px; border-radius: 50px; border: none; cursor: pointer;
      box-shadow: 0 4px 22px rgba(0,0,0,.22);
      transition: transform .2s, box-shadow .2s;
      text-decoration: none; letter-spacing: .01em;
    }
    .banner-cta:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,.26); }
    .banner-cta:active { transform: scale(.97); }

    /* Wave bottom */
    .banner-wave {
      position: absolute; bottom: -1px; left: 0; right: 0; z-index: 5;
      line-height: 0; height: 50px;
    }
    .banner-wave svg { width: 100%; height: 100%; display: block; }

    @media (min-width: 768px) {
      .banner-hero:not(.banner-has-img) { padding: 84px 20px 116px; }
      .banner-hero-img { height: 420px; }
      .banner-has-img .banner-inner { bottom: 76px; }
      .banner-hl { font-size: 3rem; }
      .banner-sl { font-size: 1.02rem; }
      .banner-wave { height: 66px; }
    }
    @media (max-width: 420px) {
      .banner-hl { font-size: 1.6rem; }
    }

    /* ─── bKash QR in order form ─── */
    .bkash-qr-wrap {
      display: flex; align-items: center; gap: 16px;
      background: #fff3f3; border: 1.5px solid #fca5a5;
      border-radius: var(--radius-sm); padding: 14px 16px; margin-top: 12px;
    }
    .bkash-qr-img { width: 90px; height: 90px; object-fit: contain; border-radius: 8px; border: 1px solid #fca5a5; flex-shrink: 0; }
    .bkash-qr-info { flex: 1; }
    .bkash-qr-info .bkash-label { margin-bottom: 4px; }
    @media (max-width: 400px) { .bkash-qr-wrap { flex-direction: column; align-items: flex-start; } }

    /* ─── AUTHOR LINE ─── */
    .card-author {
      font-size: .68rem; color: var(--muted); font-weight: 600;
      margin-bottom: 4px; display: flex; align-items: center; gap: 4px;
    }
    .card-author i { font-size: .58rem; color: var(--accent); opacity: .7; }

    /* ─── SAMPLE BUTTON ON CARD ─── */
    .card-footer {
      display: flex; align-items: center; justify-content: space-between;
      margin-top: 8px;
    }
    .pdf-btns { display: flex; gap: 4px; align-items: center; }
    .sample-btn {
      display: inline-flex; align-items: center; gap: 4px;
      background: #fff5f5; border: 1.5px solid #fecaca;
      color: #dc2626; border-radius: 20px;
      padding: 4px 10px; font-size: .66rem; font-weight: 700;
      cursor: pointer; font-family: inherit;
      transition: all .2s; white-space: nowrap;
      text-decoration: none;
    }
    .sample-btn:hover { background: #fee2e2; border-color: #f87171; transform: scale(1.05); }
    .sample-btn i { font-size: .7rem; }
    .dl-btn { padding: 4px 8px; }
    .dl-btn i { margin: 0; }

    /* ─── PDF VIEWER MODAL ─── */
    .pdf-overlay {
      position: fixed; inset: 0; z-index: 600;
      background: rgba(0,0,0,.7);
      backdrop-filter: blur(6px);
      display: flex; flex-direction: column;
      opacity: 0; pointer-events: none;
      transition: opacity .3s ease;
    }
    .pdf-overlay.show { opacity: 1; pointer-events: all; }

    .pdf-topbar {
      background: #1a1a2e; color: #fff;
      padding: 12px 16px;
      display: flex; align-items: center; gap: 12px;
      flex-shrink: 0;
    }
    .pdf-topbar-title {
      flex: 1; font-size: .9rem; font-weight: 700;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .pdf-topbar-sub { font-size: .72rem; opacity: .6; margin-top: 1px; }
    .pdf-action-btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 14px; border-radius: 8px; border: none;
      font-size: .8rem; font-weight: 700; cursor: pointer;
      font-family: inherit; text-decoration: none;
      transition: transform .15s, opacity .15s; white-space: nowrap;
    }
    .pdf-action-btn:hover { transform: scale(1.04); }
    .pdf-dl-btn { background: var(--accent); color: #fff; }
    .pdf-close-btn { background: rgba(255,255,255,.12); color: #fff; font-size: 1rem; padding: 8px 12px; }
    .pdf-close-btn:hover { background: rgba(255,255,255,.22); }

    /* Desktop: iframe viewer */
    .pdf-iframe-wrap {
      flex: 1; overflow: hidden;
      transform: translateY(20px);
      transition: transform .35s cubic-bezier(.34,1.2,.64,1);
    }
    .pdf-overlay.show .pdf-iframe-wrap { transform: translateY(0); }
    .pdf-iframe-wrap iframe {
      width: 100%; height: 100%; border: none; display: block;
    }

    /* Mobile fallback card */
    .pdf-mobile-card {
      display: none;
      flex: 1; align-items: center; justify-content: center; padding: 32px 20px;
    }
    .pdf-mobile-inner {
      background: #fff; border-radius: 20px; padding: 32px 24px;
      max-width: 380px; width: 100%; text-align: center;
      box-shadow: 0 20px 60px rgba(0,0,0,.3);
      transform: translateY(20px);
      transition: transform .35s cubic-bezier(.34,1.2,.64,1);
    }
    .pdf-overlay.show .pdf-mobile-inner { transform: translateY(0); }
    .pdf-mobile-icon { font-size: 3.5rem; margin-bottom: 16px; }
    .pdf-mobile-title { font-size: 1.1rem; font-weight: 800; color: var(--text); margin-bottom: 6px; }
    .pdf-mobile-sub { font-size: .85rem; color: var(--muted); margin-bottom: 24px; line-height: 1.5; }
    .pdf-mobile-dl {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      background: linear-gradient(135deg, var(--accent-grad) 0%, var(--accent) 100%);
      color: #fff; border-radius: 50px; padding: 14px 28px;
      font-size: 1rem; font-weight: 700; text-decoration: none;
      box-shadow: 0 4px 16px rgba(181,24,61,.35);
      transition: transform .2s, box-shadow .2s;
    }
    .pdf-mobile-dl:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(181,24,61,.45); }

    @media (max-width: 767px) {
      .pdf-iframe-wrap { display: none; }
      .pdf-mobile-card { display: flex; }
    }

    /* ─── TOAST ─── */
    .toast {
      position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%) translateY(20px);
      background: #1e1b4b; color: #fff;
      padding: 10px 20px; border-radius: 30px;
      font-size: .85rem; font-weight: 600;
      z-index: 400; opacity: 0; transition: all .3s ease;
      white-space: nowrap; pointer-events: none;
    }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

    /* ─── LOADING ─── */
    .loading-overlay {
      position: fixed; inset: 0; z-index: 500;
      background: rgba(255,255,255,.8);
      backdrop-filter: blur(4px);
      display: flex; align-items: center; justify-content: center;
      opacity: 0; pointer-events: none; transition: opacity .2s;
    }
    .loading-overlay.show { opacity: 1; pointer-events: all; }
    .spinner {
      width: 44px; height: 44px;
      border: 4px solid var(--accent-light);
      border-top-color: var(--accent);
      border-radius: 50%;
      animation: spin .7s linear infinite;
    }

    /* ─── BODY GRADIENT ─── */
    body {
      background: linear-gradient(160deg, var(--bg) 0%, var(--accent-light) 100%);
    }

    /* ─── SAKURA PETALS ─── */
    .sakura-container {
      position: fixed; inset: 0;
      pointer-events: none; z-index: 0; overflow: hidden;
    }
    .petal {
      position: absolute; top: -24px;
      border-radius: 80% 0 80% 0;
      opacity: 0;
      animation: sakuraFall linear infinite;
      background: linear-gradient(135deg, var(--sakura), var(--border)) !important;
    }
    @keyframes sakuraFall {
      0%   { transform: translateY(0) rotate(0deg) translateX(0); opacity: 0; }
      8%   { opacity: .75; }
      88%  { opacity: .45; }
      100% { transform: translateY(110vh) rotate(600deg) translateX(60px); opacity: 0; }
    }

    /* ─── ANIMATIONS ─── */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
  <?= buildThemeCss($theme_accent, $bg_color) ?>
<?php if ($pixel_id): ?>
  <!-- Meta Pixel -->
  <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
  n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
  document,'script','https://connect.facebook.net/en_US/fbevents.js');
  fbq('init','<?= $pixel_id ?>');fbq('track','PageView');</script>
  <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=<?= $pixel_id ?>&ev=PageView&noscript=1"></noscript>
<?php endif; ?>
</head>
<body>

<!-- SAKURA PETALS -->
<div class="sakura-container" aria-hidden="true">
  <div class="petal" style="left:5%;width:10px;height:8px;background:linear-gradient(135deg,#FFAFC5,#FF8FA8);animation-duration:9s;animation-delay:0s;"></div>
  <div class="petal" style="left:15%;width:8px;height:6px;background:linear-gradient(135deg,#FFC8D8,#FFB0C4);animation-duration:11s;animation-delay:1.5s;"></div>
  <div class="petal" style="left:27%;width:12px;height:9px;background:linear-gradient(135deg,#FF9DB8,#FF7A9E);animation-duration:8s;animation-delay:0.8s;"></div>
  <div class="petal" style="left:38%;width:7px;height:5px;background:linear-gradient(135deg,#FFD0E0,#FFB8CC);animation-duration:13s;animation-delay:2.2s;"></div>
  <div class="petal" style="left:50%;width:11px;height:8px;background:linear-gradient(135deg,#FFAFC5,#FF8FA8);animation-duration:10s;animation-delay:0.4s;"></div>
  <div class="petal" style="left:62%;width:9px;height:7px;background:linear-gradient(135deg,#FFC0D0,#FFA0BA);animation-duration:12s;animation-delay:3s;"></div>
  <div class="petal" style="left:73%;width:8px;height:6px;background:linear-gradient(135deg,#FFD0E0,#FFB8CC);animation-duration:9.5s;animation-delay:1s;"></div>
  <div class="petal" style="left:84%;width:13px;height:10px;background:linear-gradient(135deg,#FF9DB8,#FF7A9E);animation-duration:11.5s;animation-delay:1.8s;"></div>
  <div class="petal" style="left:92%;width:7px;height:5px;background:linear-gradient(135deg,#FFAFC5,#FF8FA8);animation-duration:8.5s;animation-delay:0.3s;"></div>
  <div class="petal" style="left:44%;width:10px;height:7px;background:linear-gradient(135deg,#FFC8D8,#FFB0C4);animation-duration:14s;animation-delay:4s;"></div>
</div>

<!-- HEADER -->
<header class="header">
  <div class="header-inner">
    <a href="index.php" class="shop-name"><?= $shop_name ?></a>
    <span class="header-badge" id="headerBadge">0 টি বাছাই</span>
  </div>
</header>

<!-- BANNER -->
<?php if ($banner_enabled): ?>
<div class="banner-hero<?= $banner_img_url ? ' banner-has-img' : '' ?>">
  <?php if ($banner_img_url): ?>
    <img src="<?= htmlspecialchars($banner_img_url) ?>" alt="banner" class="banner-hero-img">
    <div class="banner-img-overlay"></div>
  <?php else: ?>
    <div class="banner-blob banner-blob-1"></div>
    <div class="banner-blob banner-blob-2"></div>
    <div class="banner-blob banner-blob-3"></div>
    <div class="banner-dots"></div>
  <?php endif; ?>

  <div class="banner-inner">
    <?php if (!$banner_img_url): ?>
      <div class="banner-badge-pill">🛍️ স্বাগতম</div>
    <?php endif; ?>
    <h1 class="banner-hl"><?= $banner_title ?: $shop_name ?></h1>
    <?php if ($banner_subtitle): ?><p class="banner-sl"><?= $banner_subtitle ?></p><?php endif; ?>
    <?php if (!$banner_img_url): ?>
      <button class="banner-cta" onclick="document.getElementById('productsSection').scrollIntoView({behavior:'smooth'})">
        পণ্য দেখুন <i class="fas fa-arrow-down" style="font-size:.75rem"></i>
      </button>
    <?php endif; ?>
  </div>

  <div class="banner-wave">
    <svg viewBox="0 0 1440 50" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
      <path d="M0,25 C240,50 480,0 720,25 C960,50 1200,0 1440,25 L1440,50 L0,50 Z" fill="var(--bg)"/>
    </svg>
  </div>
</div>
<?php endif; ?>

<!-- MAIN PAGE -->
<main class="page">

  <!-- Products -->
  <div id="productsSection">
    <p class="section-label">পণ্য বেছে নিন</p>
    <div class="products-grid" id="productsGrid">
      <!-- cards injected by JS -->
    </div>
  </div>

  <!-- Order Form (hidden until user clicks "Order Now") -->
  <div class="order-section" id="orderSection">
    <div class="order-card">
      <div class="order-header">
        <h2><i class="fas fa-shopping-bag" style="margin-right:8px"></i>অর্ডার দিন</h2>
        <button class="order-back-btn" onclick="closeOrderForm()">← পরিবর্তন করুন</button>
      </div>

      <!-- Selected items summary -->
      <div class="order-summary">
        <div class="order-summary-title">আপনার বাছাই</div>
        <div id="orderItemsList"></div>
        <div class="order-totals">
          <div class="order-total-row">
            <span>পণ্যের মূল্য</span>
            <span id="summaryBookPrice">৳০</span>
          </div>
          <div class="order-total-row">
            <span>ডেলিভারি চার্জ</span>
            <span id="summaryDelivery">৳—</span>
          </div>
          <div class="order-total-row grand">
            <span>মোট</span>
            <span id="summaryTotal">—</span>
          </div>
        </div>
      </div>

      <!-- Form -->
      <div class="form-body">
        <form id="orderForm" novalidate>

          <div class="form-grid">
            <div class="form-group">
              <label for="fname">আপনার নাম *</label>
              <input type="text" id="fname" placeholder="সম্পূর্ণ নাম" required autocomplete="name" inputmode="text">
            </div>
            <div class="form-group">
              <label for="fphone">মোবাইল নম্বর *</label>
              <div class="phone-wrap">
                <span class="phone-code" id="phoneCodeDisplay"><?= htmlspecialchars($country_code) ?></span>
                <input type="tel" id="fphone" placeholder="01XXXXXXXXX" required autocomplete="tel" inputmode="numeric">
              </div>
            </div>
            <div class="form-group full">
              <label for="faddress">পূর্ণ ঠিকানা *</label>
              <textarea id="faddress" placeholder="বাড়ি নং, রাস্তা, এলাকা, জেলা" required autocomplete="street-address"></textarea>
            </div>
            <div class="form-group">
              <label for="farea">এলাকা *</label>
              <select id="farea" required onchange="updateTotals()">
                <option value="">বেছে নিন</option>
                <option value="dhaka" id="optDhaka">ঢাকা</option>
                <option value="outside" id="optOutside">ঢাকার বাইরে</option>
              </select>
              <span class="hint" id="deliveryHint" style="font-size:.73rem;color:var(--muted)"></span>
              <!-- Delivery badge — appears right after selecting area -->
              <div id="deliveryCostBox" style="margin-top:8px;background:#FCE8EE;border:1.5px solid #F0D0D8;background:var(--accent-light);border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between">
                <span style="font-size:.8rem;color:#9B7B82;color:var(--muted);font-weight:600">ডেলিভারি চার্জ</span>
                <span style="font-size:1.05rem;font-weight:800;color:#B5183D;color:var(--accent)" id="deliveryCostDisplay">৳—</span>
              </div>
            </div>
            <div class="form-group">
              <label>পেমেন্ট পদ্ধতি *</label>
              <div class="payment-toggle">
                <button type="button" class="pay-btn" id="btnCod" onclick="setPayment('cod')">
                  <i class="fas fa-money-bill-wave"></i> ক্যাশ অন ডেলিভারি
                </button>
                <button type="button" class="pay-btn active" id="btnBkash" onclick="setPayment('bkash')">
                  <i class="fas fa-mobile-alt"></i> bKash
                </button>
              </div>
            </div>
          </div>

          <!-- bKash manual: QR + number (hidden by JS only if API mode) -->
          <div id="bkashManualBox" style="margin-top:12px">
            <div id="bkashQrBlock"></div><!-- QR block injected by JS -->
            <div class="bkash-info" id="bkashInfoSimple" style="margin-top:8px">
              <div>
                <div class="bkash-label">bKash নম্বরে পাঠান</div>
                <div class="bkash-number" id="bkashNumber">লোড হচ্ছে...</div>
              </div>
              <button type="button" class="copy-btn" onclick="copyBkash()">কপি করুন</button>
            </div>
          </div>

          <!-- bKash API mode -->
          <div id="bkashApiBox" style="display:none;margin-top:12px">
            <button type="button" class="submit-btn" id="bkashPayBtn" onclick="initiateBkashPayment()"
                    style="background:linear-gradient(135deg,#f0166c,#e2136e);margin-top:0;padding:13px">
              <i class="fas fa-mobile-alt" style="margin-right:8px"></i>bKash দিয়ে পেমেন্ট করুন
            </button>
            <p style="font-size:.75rem;color:var(--muted);text-align:center;margin-top:8px">
              বোতামে ক্লিক করলে bKash পেমেন্ট পেজে যাবেন
            </p>
          </div>

          <!-- Transaction ID field (manual bKash only) -->
          <div class="transaction-field show form-group" id="txnField">
            <label for="ftxn">Transaction ID *</label>
            <input type="text" id="ftxn" placeholder="bKash Transaction ID">
          </div>

          <button type="submit" class="submit-btn" id="submitBtn">
            <i class="fas fa-paper-plane" style="margin-right:8px"></i>অর্ডার নিশ্চিত করুন
          </button>

        </form>
      </div>
    </div>
  </div>

</main>

<!-- FLOATING ORDER BAR -->
<div class="order-bar" id="orderBar">
  <div class="order-bar-info">
    <div class="order-bar-count" id="barCount">০ টি পণ্য বাছাই</div>
    <div class="order-bar-total" id="barTotal">৳০</div>
  </div>
  <button class="order-bar-btn" onclick="openOrderForm()">অর্ডার করুন →</button>
</div>

<!-- SUCCESS MODAL -->
<div class="modal-overlay" id="successModal">
  <div class="modal">
    <div class="modal-icon">🎉</div>
    <h3>অর্ডার সফল!</h3>
    <p id="successMsg">আপনার অর্ডার পেয়েছি। শীঘ্রই যোগাযোগ করা হবে।</p>
    <button class="modal-close" onclick="resetAll()">ঠিক আছে</button>
  </div>
</div>

<!-- PDF VIEWER MODAL -->
<div class="pdf-overlay" id="pdfOverlay" onclick="if(event.target===this)closePdf()">

  <!-- Top bar -->
  <div class="pdf-topbar">
    <div>
      <div class="pdf-topbar-title" id="pdfTitle">Sample PDF</div>
      <div class="pdf-topbar-sub">পড়ুন ও বিনামূল্যে ডাউনলোড করুন</div>
    </div>
    <a id="pdfDownloadBtn" href="#" download class="pdf-action-btn pdf-dl-btn">
      <i class="fas fa-download"></i> ডাউনলোড
    </a>
    <button class="pdf-action-btn pdf-close-btn" onclick="closePdf()">✕</button>
  </div>

  <!-- Desktop: iframe -->
  <div class="pdf-iframe-wrap">
    <iframe id="pdfIframe" src="" title="Sample PDF"></iframe>
  </div>

  <!-- Mobile: download card -->
  <div class="pdf-mobile-card">
    <div class="pdf-mobile-inner">
      <div class="pdf-mobile-icon">📄</div>
      <div class="pdf-mobile-title" id="pdfMobileTitle">Sample PDF</div>
      <div class="pdf-mobile-sub">
        এই বইটির sample PDF ডাউনলোড করে পড়ুন এবং সিদ্ধান্ত নিন।
      </div>
      <a id="pdfMobileDl" href="#" download class="pdf-mobile-dl">
        <i class="fas fa-download"></i> ডাউনলোড করে পড়ুন
      </a>
    </div>
  </div>

</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<!-- LOADING -->
<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner"></div>
</div>

<script>
window.BKASH_MODE    = <?= json_encode($bkash_mode) ?>;
window.PIXEL_ID      = <?= json_encode($pixel_id) ?>;
window.SHOP_NAME     = <?= json_encode($shop_name) ?>;
window.COUNTRY_CODE  = <?= json_encode($country_code) ?>;
</script>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__.'/assets/js/app.js') ?>"></script>
</body>
</html>
