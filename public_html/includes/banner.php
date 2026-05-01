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
    <a href="index.php" class="banner-shop-name"><?= $shop_name ?></a>
    <?php if ($banner_title): ?><h1 class="banner-hl"><?= $banner_title ?></h1><?php endif; ?>
    <?php if ($banner_subtitle): ?><p class="banner-sl"><?= $banner_subtitle ?></p><?php endif; ?>
    <?php if (!$banner_img_url): ?>
      <button class="banner-cta" onclick="document.getElementById('productsSection').scrollIntoView({behavior:'smooth'})">
        Browse Products <i class="fas fa-arrow-down" style="font-size:.75rem"></i>
      </button>
    <?php endif; ?>
  </div>

  <div class="banner-wave">
    <svg viewBox="0 0 1440 50" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
      <path d="M0,25 C240,50 480,0 720,25 C960,50 1200,0 1440,25 L1440,50 L0,50 Z" fill="var(--bg)"/>
    </svg>
  </div>
</div>
<?php else: ?>
<div class="banner-minimal">
  <a href="index.php" class="banner-shop-name"><?= $shop_name ?></a>
</div>
<?php endif; ?>
