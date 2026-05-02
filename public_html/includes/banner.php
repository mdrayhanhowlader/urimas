<?php if ($banner_enabled): ?>
<div class="banner-hero<?= $banner_img_url ? ' banner-has-img' : '' ?>">

  <?php if ($banner_img_url): ?>
    <img src="<?= htmlspecialchars($banner_img_url) ?>" alt="<?= $shop_name ?>" class="banner-hero-img">
  <?php endif; ?>

  <div class="banner-inner">
    <a href="index.php" class="banner-shop-name"><?= $shop_name ?></a>
    <?php if ($banner_title): ?>
      <h1 class="banner-hl"><?= $banner_title ?></h1>
    <?php endif; ?>
    <?php if ($banner_subtitle): ?>
      <p class="banner-sl"><?= $banner_subtitle ?></p>
    <?php endif; ?>
    <?php if (!$banner_img_url): ?>
      <button class="banner-cta" onclick="document.getElementById('productsSection').scrollIntoView({behavior:'smooth'})">
        Browse Products <i class="fas fa-arrow-down" style="font-size:.72rem"></i>
      </button>
    <?php endif; ?>
  </div>

  <div class="banner-wave">
    <svg viewBox="0 0 1440 56" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
      <path d="M0,28 C180,56 360,0 540,28 C720,56 900,0 1080,28 C1260,56 1350,14 1440,28 L1440,56 L0,56 Z" fill="var(--bg)"/>
    </svg>
  </div>
</div>
<?php else: ?>
<div class="banner-minimal">
  <a href="index.php" class="banner-shop-name"><?= $shop_name ?></a>
</div>
<?php endif; ?>
