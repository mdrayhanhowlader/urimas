<?php
// Suppress Intelephense P1008 — variables set by index.php before include
$shop_name        = $shop_name        ?? '';
$banner_enabled   = $banner_enabled   ?? 0;
$banner_title     = $banner_title     ?? '';
$banner_subtitle  = $banner_subtitle  ?? '';
$banner_img_url   = $banner_img_url   ?? '';
$banner_grad_from = $banner_grad_from ?? '#0e0306';
$banner_grad_to   = $banner_grad_to   ?? '#d4254e';
$_show_eyebrow    = $banner_title && $shop_name;
$_grad_style      = 'background:linear-gradient(135deg,'
    . htmlspecialchars($banner_grad_from) . ' 0%,'
    . htmlspecialchars($banner_grad_to)   . ' 100%)';
?>

<header class="site-header">
  <div class="site-nav-inner">
    <a href="index.php" class="site-logo"><?= htmlspecialchars($shop_name) ?></a>
  </div>
</header>

<?php if ($banner_enabled): ?>
<section class="banner-hero" style="<?= $_grad_style ?>">

  <div class="banner-inner">

    <div class="banner-text-col">

      <?php if ($_show_eyebrow): ?>
        <p class="banner-eyebrow"><i class="fas fa-store"></i> <?= htmlspecialchars($shop_name) ?></p>
      <?php endif; ?>

      <h1 class="banner-hl"><?= htmlspecialchars($banner_title ?: $shop_name) ?></h1>

      <?php if ($banner_subtitle): ?>
        <p class="banner-sl"><?= htmlspecialchars($banner_subtitle) ?></p>
      <?php endif; ?>

      <div class="banner-cta-wrap">
        <button class="banner-cta" onclick="document.getElementById('productsSection').scrollIntoView({behavior:'smooth'})">
          Browse Products <i class="fas fa-arrow-right"></i>
        </button>
      </div>

    </div>

    <div class="banner-disc-wrap">
      <div class="banner-disc">
        <?php if ($banner_img_url): ?>
          <img src="<?= htmlspecialchars($banner_img_url) ?>" alt="<?= htmlspecialchars($shop_name) ?>" class="banner-disc-img">
        <?php else: ?>
          <div class="banner-disc-placeholder">
            <i class="fas fa-image"></i>
          </div>
        <?php endif; ?>
      </div>
      <div class="banner-disc-ring banner-disc-ring-1" aria-hidden="true"></div>
      <div class="banner-disc-ring banner-disc-ring-2" aria-hidden="true"></div>
    </div>

  </div>

</section>
<?php endif; ?>
