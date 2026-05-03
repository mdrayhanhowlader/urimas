<?php
require_once 'config.php';

try {
    $settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
} catch (Exception $e) {
    $settings = [];
}

$shop_name      = htmlspecialchars($settings['shop_name'] ?? 'Urimas Books');
$banner_enabled = (int)($settings['banner_enabled'] ?? 0);
$banner_title   = htmlspecialchars($settings['banner_title'] ?? '');
$banner_subtitle= htmlspecialchars($settings['banner_subtitle'] ?? '');
$BANNER_DIR     = __DIR__ . '/assets/images/banner/';
$BANNER_URL     = 'assets/images/banner/';
$banner_image   = $settings['banner_image'] ?? '';
$banner_img_url = ($banner_image && file_exists($BANNER_DIR.$banner_image)) ? $BANNER_URL.$banner_image : '';
$bkash_mode       = $settings['bkash_mode'] ?? 'manual';
$banner_grad_from = $settings['banner_grad_from'] ?? '#0e0306';
$banner_grad_to   = $settings['banner_grad_to']   ?? '#d4254e';
$theme_accent     = $settings['theme_accent'] ?? '#B5183D';
$bg_color       = $settings['bg_color'] ?? '';
$pixel_id       = preg_replace('/[^0-9]/', '', $settings['pixel_id'] ?? '');
$country_code   = preg_replace('/[^+0-9]/', '', $settings['country_code'] ?? '+880') ?: '+880';
$css_v          = @filemtime(__DIR__.'/assets/css/app.css') ?: time();
$js_v           = @filemtime(__DIR__.'/assets/js/app.js') ?: time();

include 'includes/head.php';
?>
<body>

<div class="sakura-container" aria-hidden="true">
  <div class="petal" style="left:5%;width:10px;height:8px;animation-duration:9s;animation-delay:0s;"></div>
  <div class="petal" style="left:15%;width:8px;height:6px;animation-duration:11s;animation-delay:1.5s;"></div>
  <div class="petal" style="left:27%;width:12px;height:9px;animation-duration:8s;animation-delay:0.8s;"></div>
  <div class="petal" style="left:38%;width:7px;height:5px;animation-duration:13s;animation-delay:2.2s;"></div>
  <div class="petal" style="left:50%;width:11px;height:8px;animation-duration:10s;animation-delay:0.4s;"></div>
  <div class="petal" style="left:62%;width:9px;height:7px;animation-duration:12s;animation-delay:3s;"></div>
  <div class="petal" style="left:73%;width:8px;height:6px;animation-duration:9.5s;animation-delay:1s;"></div>
  <div class="petal" style="left:84%;width:13px;height:10px;animation-duration:11.5s;animation-delay:1.8s;"></div>
  <div class="petal" style="left:92%;width:7px;height:5px;animation-duration:8.5s;animation-delay:0.3s;"></div>
  <div class="petal" style="left:44%;width:10px;height:7px;animation-duration:14s;animation-delay:4s;"></div>
</div>

<?php include 'includes/banner.php'; ?>

<main class="page">
  <?php include 'includes/products-section.php'; ?>
  <?php include 'includes/order-form.php'; ?>
</main>

<?php include 'includes/floating-bar.php'; ?>
<?php include 'includes/modals.php'; ?>
<?php include 'includes/footer-scripts.php'; ?>
