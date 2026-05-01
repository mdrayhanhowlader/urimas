<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php'); exit;
}
require_once '../config.php';

$UPLOAD_DIR  = __DIR__ . '/../assets/images/books/';
$UPLOAD_URL  = '../assets/images/books/';
$PDF_DIR     = __DIR__ . '/../assets/pdfs/';
$PDF_URL     = '../assets/pdfs/';
$QR_DIR      = __DIR__ . '/../assets/images/qr/';
$QR_URL      = '../assets/images/qr/';
$BANNER_DIR  = __DIR__ . '/../assets/images/banner/';
$BANNER_URL  = '../assets/images/banner/';

// Auto-migrations
foreach ([
    "ALTER TABLE books ADD COLUMN author VARCHAR(255) NOT NULL DEFAULT '' AFTER name",
    "ALTER TABLE settings ADD COLUMN bkash_mode VARCHAR(10) NOT NULL DEFAULT 'manual'",
    "ALTER TABLE settings ADD COLUMN bkash_qr_image VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE settings ADD COLUMN bkash_app_key VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE settings ADD COLUMN bkash_app_secret VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE settings ADD COLUMN bkash_username VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE settings ADD COLUMN bkash_password VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE settings ADD COLUMN banner_enabled TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE settings ADD COLUMN banner_title VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE settings ADD COLUMN banner_subtitle VARCHAR(500) NOT NULL DEFAULT ''",
    "ALTER TABLE settings ADD COLUMN banner_image VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE settings ADD COLUMN theme_accent VARCHAR(20) NOT NULL DEFAULT '#B5183D'",
    "ALTER TABLE settings ADD COLUMN bg_color VARCHAR(20) NOT NULL DEFAULT ''",
    "ALTER TABLE settings ADD COLUMN pixel_id VARCHAR(50) NOT NULL DEFAULT ''",
    "ALTER TABLE settings ADD COLUMN country_code VARCHAR(15) NOT NULL DEFAULT '+880'",
    "ALTER TABLE orders ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE books ADD COLUMN variants TEXT NOT NULL DEFAULT ''",
] as $sql) {
    try { $pdo->exec($sql); } catch (Exception $e) {}
}

// Session flash (PRG pattern)
$flash = null;
if (isset($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

/* ─── Image Upload ─────────────────────────────────── */
function uploadBookImage($file, $UPLOAD_DIR) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    if (!in_array($mime, $allowed) || $file['size'] > 5*1024*1024) return null;
    if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0777, true);
    if (!is_writable($UPLOAD_DIR)) return null;
    $ext  = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');
    $dest = $UPLOAD_DIR . uniqid('book_', true) . '.' . $ext;
    $src  = match($mime) {
        'image/png'  => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => @imagecreatefromwebp($file['tmp_name']),
        'image/gif'  => @imagecreatefromgif($file['tmp_name']),
        default      => @imagecreatefromjpeg($file['tmp_name']),
    };
    if (!$src) { return move_uploaded_file($file['tmp_name'], $dest) ? basename($dest) : null; }
    $ow = imagesx($src); $oh = imagesy($src);
    $r  = min(600/$ow, 800/$oh, 1);
    $nw = (int)round($ow*$r); $nh = (int)round($oh*$r);
    $dst = imagecreatetruecolor($nw, $nh);
    if ($ext === 'png') { imagealphablending($dst,false); imagesavealpha($dst,true); }
    imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$ow,$oh);
    $ok = ($ext === 'png') ? imagepng($dst,$dest,8) : imagejpeg($dst,$dest,82);
    unset($src,$dst);
    return $ok ? basename($dest) : null;
}

/* ─── Banner Image Upload (wide landscape) ─────────── */
function uploadBannerImage($file, $UPLOAD_DIR) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    if (!in_array($mime, $allowed) || $file['size'] > 10*1024*1024) return null;
    if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0777, true);
    if (!is_writable($UPLOAD_DIR)) return null;
    $ext  = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');
    $dest = $UPLOAD_DIR . uniqid('banner_', true) . '.' . $ext;
    $src  = match($mime) {
        'image/png'  => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => @imagecreatefromwebp($file['tmp_name']),
        'image/gif'  => @imagecreatefromgif($file['tmp_name']),
        default      => @imagecreatefromjpeg($file['tmp_name']),
    };
    if (!$src) { return move_uploaded_file($file['tmp_name'], $dest) ? basename($dest) : null; }
    $ow = imagesx($src); $oh = imagesy($src);
    $r  = min(1200/$ow, 400/$oh, 1);
    $nw = (int)round($ow*$r); $nh = (int)round($oh*$r);
    $dst = imagecreatetruecolor($nw, $nh);
    if ($ext === 'png') { imagealphablending($dst,false); imagesavealpha($dst,true); }
    imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$ow,$oh);
    $ok = ($ext === 'png') ? imagepng($dst,$dest,8) : imagejpeg($dst,$dest,85);
    unset($src,$dst);
    return $ok ? basename($dest) : null;
}

/* ─── PDF Upload ───────────────────────────────────── */
function uploadPdf($file, $PDF_DIR) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mime !== 'application/pdf' || $file['size'] > 20*1024*1024) return null;
    if (!is_dir($PDF_DIR)) mkdir($PDF_DIR, 0777, true);
    if (!is_writable($PDF_DIR)) return null;
    $dest = $PDF_DIR . uniqid('sample_', true) . '.pdf';
    return move_uploaded_file($file['tmp_name'], $dest) ? basename($dest) : null;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ─── Delete book ──────────────────────────────────── */
if ($action === 'delete_book' && isset($_GET['id'])) {
    $bid = (int)$_GET['id'];
    $row = $pdo->prepare("SELECT image, sample_pdf FROM books WHERE id=?");
    $row->execute([$bid]);
    $old = $row->fetch();
    if ($old) {
        if ($old['image']      && file_exists($UPLOAD_DIR.$old['image']))   @unlink($UPLOAD_DIR.$old['image']);
        if ($old['sample_pdf'] && file_exists($PDF_DIR.$old['sample_pdf'])) @unlink($PDF_DIR.$old['sample_pdf']);
    }
    $pdo->prepare("DELETE FROM books WHERE id=?")->execute([$bid]);
    $_SESSION['flash'] = ['ok','পণ্য মুছে ফেলা হয়েছে'];
    header('Location: settings.php#books'); exit;
}

/* ─── Delete PDF only ──────────────────────────────── */
if ($action === 'delete_pdf' && isset($_GET['id'])) {
    $bid = (int)$_GET['id'];
    $row = $pdo->prepare("SELECT sample_pdf FROM books WHERE id=?"); $row->execute([$bid]);
    $old = $row->fetch();
    if ($old && $old['sample_pdf'] && file_exists($PDF_DIR.$old['sample_pdf'])) @unlink($PDF_DIR.$old['sample_pdf']);
    $pdo->prepare("UPDATE books SET sample_pdf='' WHERE id=?")->execute([$bid]);
    $_SESSION['flash'] = ['ok','Sample PDF মুছে ফেলা হয়েছে'];
    header('Location: settings.php#books'); exit;
}

/* ─── Add book ─────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_book') {
    $name   = sanitize($_POST['new_name']     ?? '');
    $author = sanitize($_POST['new_author']   ?? '');
    $desc   = sanitize($_POST['new_desc']     ?? '');
    $price  = (float)($_POST['new_price']     ?? 0);
    $vrraw  = trim($_POST['new_variants']     ?? '');
    $vrarr  = array_values(array_filter(array_map('trim', explode(',', $vrraw))));
    $vrjson = !empty($vrarr) ? json_encode($vrarr, JSON_UNESCAPED_UNICODE) : '';
    $img = $pdf = '';
    if (!empty($_FILES['new_image']['name'])) { $fn = uploadBookImage($_FILES['new_image'], $UPLOAD_DIR); if ($fn) $img = $fn; }
    if (!empty($_FILES['new_pdf']['name']))   { $fn = uploadPdf($_FILES['new_pdf'], $PDF_DIR);            if ($fn) $pdf = $fn; }
    if ($name && $price > 0) {
        $pdo->prepare("INSERT INTO books (name, author, description, price, image, sample_pdf, variants) VALUES (?,?,?,?,?,?,?)")
            ->execute([$name, $author, $desc, $price, $img, $pdf, $vrjson]);
        $_SESSION['flash'] = ['ok', '"'.$name.'" পণ্য যোগ করা হয়েছে'];
    } else {
        $_SESSION['flash'] = ['err', 'নাম ও মূল্য দেওয়া বাধ্যতামূলক'];
    }
    header('Location: settings.php#books'); exit;
}

/* ─── Update single book ───────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_book') {
    $bid    = (int)($_POST['book_id']       ?? 0);
    $bname  = sanitize($_POST['name']       ?? '');
    $bauth  = sanitize($_POST['author']     ?? '');
    $bdesc  = sanitize($_POST['description']?? '');
    $bprice = (float)($_POST['price']       ?? 0);
    $img    = sanitize($_POST['image_current'] ?? '');
    $pdf    = sanitize($_POST['pdf_current']   ?? '');
    $bvrraw = trim($_POST['variants']       ?? '');
    $bvrarr = array_values(array_filter(array_map('trim', explode(',', $bvrraw))));
    $bvrjson = !empty($bvrarr) ? json_encode($bvrarr, JSON_UNESCAPED_UNICODE) : '';

    if (!empty($_FILES['image']['name'])) {
        $fn = uploadBookImage($_FILES['image'], $UPLOAD_DIR);
        if ($fn) { if ($img && file_exists($UPLOAD_DIR.$img)) @unlink($UPLOAD_DIR.$img); $img = $fn; }
    }
    if (!empty($_FILES['pdf']['name'])) {
        $fn = uploadPdf($_FILES['pdf'], $PDF_DIR);
        if ($fn) { if ($pdf && file_exists($PDF_DIR.$pdf)) @unlink($PDF_DIR.$pdf); $pdf = $fn; }
    }

    if ($bname && $bprice > 0 && $bid) {
        $pdo->prepare("UPDATE books SET name=?, author=?, description=?, price=?, image=?, sample_pdf=?, variants=? WHERE id=?")
            ->execute([$bname, $bauth, $bdesc, $bprice, $img, $pdf, $bvrjson, $bid]);
        $_SESSION['flash'] = ['ok', '"'.$bname.'" আপডেট হয়েছে ✓'];
    } else {
        $_SESSION['flash'] = ['err', 'নাম ও মূল্য দেওয়া বাধ্যতামূলক'];
    }
    header('Location: settings.php#books'); exit;
}

/* ─── Update settings ──────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_settings') {
    // Reload current settings for fallback file values
    $cur = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch() ?: [];

    // QR code upload
    $qr_image = $cur['bkash_qr_image'] ?? '';
    if (!empty($_FILES['bkash_qr_image']['name'])) {
        if (!is_dir($QR_DIR)) mkdir($QR_DIR, 0777, true);
        $fn = uploadBookImage($_FILES['bkash_qr_image'], $QR_DIR);
        if ($fn) { if ($qr_image && file_exists($QR_DIR.$qr_image)) @unlink($QR_DIR.$qr_image); $qr_image = $fn; }
    }

    // Banner image upload
    $banner_image = $cur['banner_image'] ?? '';
    if (!empty($_FILES['banner_image']['name'])) {
        if (!is_dir($BANNER_DIR)) mkdir($BANNER_DIR, 0777, true);
        $fn = uploadBannerImage($_FILES['banner_image'], $BANNER_DIR);
        if ($fn) { if ($banner_image && file_exists($BANNER_DIR.$banner_image)) @unlink($BANNER_DIR.$banner_image); $banner_image = $fn; }
    }

    $raw_accent = trim($_POST['theme_accent'] ?? '#B5183D');
    $accent_val = preg_match('/^#[0-9A-Fa-f]{6}$/', $raw_accent) ? $raw_accent : '#B5183D';
    $raw_bg     = trim($_POST['bg_color'] ?? '');
    $bg_val     = preg_match('/^#[0-9A-Fa-f]{6}$/', $raw_bg) ? $raw_bg : '';
    $pixel_val  = preg_replace('/[^0-9]/', '', trim($_POST['pixel_id'] ?? ''));

    $pdo->prepare("UPDATE settings SET
        shop_name=?, bkash_number=?, admin_email=?, whatsapp_number=?,
        dhaka_charge=?, outside_charge=?,
        bkash_mode=?, bkash_qr_image=?,
        bkash_app_key=?, bkash_app_secret=?, bkash_username=?, bkash_password=?,
        banner_enabled=?, banner_title=?, banner_subtitle=?, banner_image=?,
        theme_accent=?, bg_color=?, pixel_id=?, country_code=?
        WHERE id=1")
        ->execute([
            sanitize($_POST['shop_name']        ?? ''),
            sanitize($_POST['bkash_number']     ?? ''),
            sanitize($_POST['admin_email']      ?? ''),
            sanitize($_POST['whatsapp_number']  ?? ''),
            (float)($_POST['dhaka_charge']      ?? 80),
            (float)($_POST['outside_charge']    ?? 140),
            in_array($_POST['bkash_mode'] ?? '', ['manual','api']) ? $_POST['bkash_mode'] : 'manual',
            $qr_image,
            trim($_POST['bkash_app_key']    ?? ''),
            trim($_POST['bkash_app_secret'] ?? ''),
            trim($_POST['bkash_username']   ?? ''),
            trim($_POST['bkash_password']   ?? ''),
            isset($_POST['banner_enabled']) ? 1 : 0,
            sanitize($_POST['banner_title']    ?? ''),
            sanitize($_POST['banner_subtitle'] ?? ''),
            $banner_image,
            $accent_val,
            $bg_val,
            $pixel_val,
            preg_replace('/[^+0-9]/', '', trim($_POST['country_code'] ?? '+880')),
        ]);
    $_SESSION['flash'] = ['ok', 'সেটিংস সেভ হয়েছে ✓'];
    header('Location: settings.php#shop'); exit;
}

/* ─── Change password ──────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'change_password') {
    $current  = $_POST['current_password']  ?? '';
    $new_pass = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';
    $stmt = $pdo->prepare("SELECT password FROM admin WHERE username=?");
    $stmt->execute([$_SESSION['admin_username']]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current, $row['password'])) {
        $_SESSION['flash'] = ['err', 'বর্তমান পাসওয়ার্ড সঠিক নয়'];
    } elseif (strlen($new_pass) < 6) {
        $_SESSION['flash'] = ['err', 'নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে'];
    } elseif ($new_pass !== $confirm) {
        $_SESSION['flash'] = ['err', 'পাসওয়ার্ড দুটো মিলছে না'];
    } else {
        $pdo->prepare("UPDATE admin SET password=? WHERE username=?")->execute([password_hash($new_pass, PASSWORD_BCRYPT), $_SESSION['admin_username']]);
        $_SESSION['flash'] = ['ok', 'পাসওয়ার্ড পরিবর্তন হয়েছে ✓'];
    }
    header('Location: settings.php#password'); exit;
}

// Load data
$settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
$books    = $pdo->query("SELECT * FROM books ORDER BY id")->fetchAll();
$shop_name_display = htmlspecialchars($settings['shop_name'] ?? 'Urimas Books');

// Derived display values
$theme_accent   = $settings['theme_accent']  ?? '#B5183D';
$bg_color       = $settings['bg_color']      ?? '';
$pixel_id       = $settings['pixel_id']      ?? '';
$country_code   = $settings['country_code']  ?? '+880';
$bkash_mode     = $settings['bkash_mode']    ?? 'manual';
$bkash_qr_image = $settings['bkash_qr_image'] ?? '';
$bkash_qr_url   = ($bkash_qr_image && file_exists($QR_DIR.$bkash_qr_image)) ? $QR_URL.$bkash_qr_image : '';
$banner_enabled = (int)($settings['banner_enabled'] ?? 0);
$banner_image   = $settings['banner_image'] ?? '';
$banner_img_url = ($banner_image && file_exists($BANNER_DIR.$banner_image)) ? $BANNER_URL.$banner_image : '';

// Build book data for JS
$booksJs = array_map(fn($b) => [
    'id'          => (int)$b['id'],
    'name'        => $b['name'],
    'author'      => $b['author'] ?? '',
    'description' => $b['description'] ?? '',
    'price'       => (float)$b['price'],
    'image'       => $b['image'] ?? '',
    'sample_pdf'  => $b['sample_pdf'] ?? '',
    'variants'    => !empty($b['variants']) ? (json_decode($b['variants'], true) ?: []) : [],
    'image_url'   => (!empty($b['image']) && file_exists($UPLOAD_DIR.$b['image'])) ? $UPLOAD_URL.$b['image'] : '',
    'has_pdf'     => !empty($b['sample_pdf']) && file_exists($PDF_DIR.$b['sample_pdf']),
], $books);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>সেটিংস — <?= $shop_name_display ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --accent: #B5183D; --accent-light: #FCE8EE; --accent-dark: #8B1A2B;
      --bg: #FFF5F7; --card: #fff;
      --text: #2C1810; --muted: #9B7B82; --border: #F0D0D8;
      --red: #dc2626; --radius: 14px; --radius-sm: 8px;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); padding-bottom: 80px; min-height: 100vh; }

    /* NAV */
    .nav { background: #fff; border-bottom: 1px solid var(--border); padding: 0 24px; position: sticky; top: 0; z-index: 50; }
    .nav-inner { max-width: 1100px; margin: 0 auto; height: 58px; display: flex; align-items: center; justify-content: space-between; }
    .nav-brand { font-size: 1.05rem; font-weight: 800; color: var(--accent); text-decoration: none; display: flex; align-items: center; gap: 8px; }
    .nav-links { display: flex; gap: 8px; }
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px;
           font-size: .82rem; font-weight: 600; border: none; cursor: pointer; font-family: inherit;
           text-decoration: none; transition: all .15s; white-space: nowrap; }
    .btn-ghost { background: var(--bg); color: var(--text); }
    .btn-ghost:hover { background: var(--accent-light); color: var(--accent); }
    .btn-danger { background: #fee2e2; color: var(--red); }
    .btn-danger:hover { background: #fecaca; }
    .btn-accent { background: var(--accent); color: #fff; }
    .btn-accent:hover { background: var(--accent-dark); }
    .btn-sm { padding: 5px 12px; font-size: .78rem; }
    .btn-xs { padding: 3px 8px; font-size: .72rem; }

    /* TABS */
    .tabs { display: flex; gap: 0; background: var(--card); border-bottom: 2px solid var(--border); padding: 0 24px; overflow-x: auto; }
    .tab { padding: 14px 20px; font-size: .85rem; font-weight: 600; color: var(--muted);
           border-bottom: 3px solid transparent; cursor: pointer; white-space: nowrap;
           transition: color .15s, border-color .15s; background: none; border-top: none; border-left: none; border-right: none;
           font-family: inherit; margin-bottom: -2px; }
    .tab.active { color: var(--accent); border-bottom-color: var(--accent); }
    .tab:hover:not(.active) { color: var(--text); }

    /* PAGE */
    .page { max-width: 1100px; margin: 0 auto; padding: 24px 16px; }
    .tab-panel { display: none; animation: fadeIn .2s ease; }
    .tab-panel.active { display: block; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }

    /* FLASH */
    .flash { padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: 20px;
             font-size: .88rem; font-weight: 500; display: flex; align-items: center; gap: 10px; }
    .flash.ok  { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .flash.err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

    /* FORM CARDS */
    .card { background: var(--card); border-radius: var(--radius); padding: 24px; margin-bottom: 20px;
            box-shadow: 0 1px 4px rgba(181,24,61,.07); border: 1px solid var(--border); }
    .card-title { font-size: .95rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center;
                  gap: 8px; color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 14px; }
    .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .grid3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; }
    @media (max-width: 640px) { .grid2, .grid3 { grid-template-columns: 1fr; } }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full { grid-column: 1 / -1; }
    label { font-size: .74rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
    input[type=text], input[type=number], input[type=email], input[type=password], textarea {
      background: #fff; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
      padding: 10px 13px; font-size: .9rem; font-family: inherit; color: var(--text);
      outline: none; transition: border-color .2s, box-shadow .2s; width: 100%;
    }
    input:focus, textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(181,24,61,.1); }
    .hint { font-size: .72rem; color: var(--muted); margin-top: 3px; }

    /* ADD BOOK CARD */
    .add-book-card {
      border: 2px dashed var(--accent); border-radius: var(--radius);
      padding: 24px; background: var(--accent-light); margin-bottom: 24px;
    }
    .add-book-title { font-size: .95rem; font-weight: 700; color: var(--accent);
                      margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }

    /* Upload zone */
    .img-drop {
      position: relative;
      border: 2px dashed var(--border); border-radius: 8px;
      min-height: 80px; transition: border-color .2s, background .2s;
      display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;
      padding: 14px;
    }
    .img-drop:focus-within { border-color: var(--accent); background: #fff; }
    .img-drop:hover { border-color: var(--accent); }
    /* Transparent overlay — user directly clicks the native file input */
    .file-overlay {
      position: absolute; inset: 0;
      width: 100%; height: 100%;
      opacity: 0; cursor: pointer; z-index: 10;
    }
    /* Visual content sits below the overlay */
    .drop-content {
      pointer-events: none; position: relative; z-index: 1;
      display: flex; flex-direction: column; align-items: center; gap: 2px;
      font-size: .78rem; color: var(--muted); line-height: 1.6; text-align: center;
    }
    .drop-content i { font-size: 1.3rem; color: var(--accent); }
    .drop-content small { font-size: .7rem; opacity: .75; }
    .upload-preview { max-width: 100%; max-height: 90px; border-radius: 6px; object-fit: cover; margin-top: 4px; position: relative; z-index: 1; }

    /* ─── ADMIN BOOK GRID ─── */
    .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
    .section-title { font-size: .95rem; font-weight: 700; color: var(--text); }
    .section-count { font-size: .78rem; color: var(--muted); background: var(--bg); padding: 3px 10px; border-radius: 20px; border: 1px solid var(--border); }

    .admin-books-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
    }
    @media (max-width: 900px) { .admin-books-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 600px) { .admin-books-grid { grid-template-columns: repeat(2, 1fr); } }

    .admin-book-card {
      background: var(--card); border-radius: 12px; overflow: hidden;
      border: 1.5px solid var(--border);
      transition: border-color .2s, box-shadow .2s, transform .2s;
      display: flex; flex-direction: column;
    }
    .admin-book-card:hover { border-color: var(--accent); box-shadow: 0 4px 20px rgba(181,24,61,.12); transform: translateY(-2px); }

    .admin-book-img {
      aspect-ratio: 3/4; background: var(--accent-light);
      position: relative; overflow: hidden;
      display: flex; align-items: center; justify-content: center;
    }
    .admin-book-img img { width: 100%; height: 100%; object-fit: cover; }
    .admin-book-placeholder { font-size: 2.5rem; color: var(--accent); opacity: .25; }

    .pdf-badge-corner {
      position: absolute; top: 8px; left: 8px;
      background: rgba(220,38,38,.85); color: #fff;
      font-size: .6rem; font-weight: 700; padding: 3px 8px; border-radius: 20px;
      letter-spacing: .03em;
    }
    .no-img-badge {
      position: absolute; bottom: 6px; right: 6px;
      background: rgba(0,0,0,.4); color: #fff;
      font-size: .58rem; padding: 2px 7px; border-radius: 20px;
    }

    .admin-book-body { padding: 12px 14px; flex: 1; }
    .admin-book-name { font-size: .88rem; font-weight: 700; color: var(--text); margin-bottom: 2px; line-height: 1.3;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .admin-book-author { font-size: .72rem; color: var(--muted); margin-bottom: 6px;
      display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
    .admin-book-price { font-size: .95rem; font-weight: 800; color: var(--accent); }

    .admin-book-footer {
      padding: 10px 12px; border-top: 1px solid var(--border);
      display: flex; gap: 6px;
    }
    .admin-book-footer .btn { flex: 1; justify-content: center; }

    .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
    .empty-state i { font-size: 2.5rem; margin-bottom: 12px; opacity: .3; display: block; }

    /* ─── EDIT MODAL ─── */
    .modal-overlay {
      position: fixed; inset: 0; z-index: 500;
      background: rgba(44,24,16,.55);
      backdrop-filter: blur(4px);
      display: flex; align-items: center; justify-content: center;
      padding: 16px;
      opacity: 0; pointer-events: none;
      transition: opacity .25s;
    }
    .modal-overlay.show { opacity: 1; pointer-events: all; }
    .modal-box {
      background: var(--card); border-radius: var(--radius);
      width: 100%; max-width: 640px; max-height: 92vh; overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0,0,0,.25);
      transform: scale(.95) translateY(16px);
      transition: transform .3s cubic-bezier(.34,1.3,.64,1);
    }
    .modal-overlay.show .modal-box { transform: scale(1) translateY(0); }
    .modal-header {
      background: linear-gradient(135deg, var(--accent-grad) 0%, var(--accent) 100%);
      color: #fff; padding: 18px 22px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 2;
    }
    .modal-header h3 { font-size: 1rem; font-weight: 700; }
    .modal-close-btn { background: rgba(255,255,255,.2); border: none; color: #fff; width: 32px; height: 32px;
                       border-radius: 50%; cursor: pointer; font-size: .95rem; font-family: inherit;
                       display: flex; align-items: center; justify-content: center; transition: background .15s; }
    .modal-close-btn:hover { background: rgba(255,255,255,.35); }
    .modal-body { padding: 22px; }
    .modal-footer { padding: 16px 22px; border-top: 1px solid var(--border); display: flex; gap: 10px; justify-content: flex-end; }

    .current-image-wrap {
      display: flex; align-items: center; gap: 14px; background: var(--bg);
      border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; border: 1px solid var(--border);
    }
    .current-img-thumb {
      width: 60px; height: 80px; border-radius: 6px; object-fit: cover;
      background: var(--accent-light); flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem; color: var(--accent); opacity: .4;
    }
    .current-img-thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; }
    .current-img-info { flex: 1; font-size: .8rem; color: var(--muted); }
    .current-img-info strong { display: block; font-size: .85rem; color: var(--text); margin-bottom: 3px; }

    .pdf-current-row {
      display: flex; align-items: center; gap: 8px; background: #fff5f5;
      border: 1px solid #fecaca; border-radius: 8px; padding: 10px 14px;
      font-size: .8rem; flex-wrap: wrap; margin-bottom: 8px;
    }
    .pdf-filename { flex: 1; color: #991b1b; font-weight: 600; font-size: .75rem; word-break: break-all; }

    /* bKash mode toggle */
    .mode-toggle { display: flex; gap: 8px; margin-top: 4px; }
    .mode-btn {
      flex: 1; padding: 10px 8px; border: 2px solid var(--border); border-radius: 8px;
      background: var(--bg); color: var(--muted); font-family: inherit;
      font-size: .82rem; font-weight: 600; cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 6px;
      transition: all .15s;
    }
    .mode-btn.active { border-color: var(--accent); background: var(--accent-light); color: var(--accent); }
    .mode-section { display: none; margin-top: 14px; }
    .mode-section.show { display: block; }
    .api-credentials { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 16px; }
    .api-credentials-title { font-size: .78rem; font-weight: 700; color: #92400e; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
    .qr-preview-wrap { display: flex; align-items: center; gap: 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; margin-bottom: 10px; }
    .qr-preview-img { width: 70px; height: 70px; object-fit: contain; border-radius: 6px; border: 1px solid var(--border); }

    /* Banner section */
    .toggle-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .toggle-switch { position: relative; width: 44px; height: 24px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider {
      position: absolute; inset: 0; background: #ddd; border-radius: 24px; cursor: pointer;
      transition: background .2s;
    }
    .toggle-slider:before {
      position: absolute; content: ''; height: 18px; width: 18px; left: 3px; bottom: 3px;
      background: #fff; border-radius: 50%; transition: transform .2s;
      box-shadow: 0 1px 3px rgba(0,0,0,.2);
    }
    .toggle-switch input:checked + .toggle-slider { background: var(--accent); }
    .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }
    .banner-preview-wrap { position: relative; width: 100%; aspect-ratio: 16/5; background: var(--accent-light); border-radius: 8px; overflow: hidden; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; }
    .banner-preview-wrap img { width: 100%; height: 100%; object-fit: cover; }
  </style>
  <?= buildThemeCss($theme_accent, $bg_color) ?>
</head>
<body>

<nav class="nav">
  <div class="nav-inner">
    <a href="../index.php" class="nav-brand"><i class="fas fa-cog"></i> সেটিংস</a>
    <div class="nav-links">
      <a href="dashboard.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> ড্যাশবোর্ড</a>
      <a href="logout.php"    class="btn btn-danger"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </div>
</nav>

<div class="tabs">
  <button class="tab active" onclick="switchTab('books')">📦 পণ্য ম্যানেজ</button>
  <button class="tab"        onclick="switchTab('shop')">🏪 শপ সেটিংস</button>
  <button class="tab"        onclick="switchTab('password')">🔒 পাসওয়ার্ড</button>
</div>

<div class="page">

  <?php if ($flash): ?>
    <div class="flash <?= $flash[0] ?>" id="flashMsg">
      <i class="fas fa-<?= $flash[0]==='ok'?'check-circle':'exclamation-circle' ?>"></i>
      <?= htmlspecialchars($flash[1]) ?>
    </div>
  <?php endif; ?>

  <!-- ══ TAB: BOOKS ══ -->
  <div class="tab-panel active" id="tab-books">

    <!-- ADD NEW BOOK -->
    <div class="add-book-card">
      <div class="add-book-title"><i class="fas fa-plus-circle"></i> নতুন পণ্য যোগ করুন</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_book">
        <div class="grid3">
          <div class="form-group">
            <label>পণ্যের নাম *</label>
            <input type="text" name="new_name" placeholder="পণ্যের নাম" required>
          </div>
          <div class="form-group">
            <label>ব্র্যান্ড / লেখক</label>
            <input type="text" name="new_author" placeholder="ব্র্যান্ড বা লেখকের নাম">
          </div>
          <div class="form-group">
            <label>মূল্য (৳) *</label>
            <input type="number" name="new_price" placeholder="৳" step="1" min="1" required>
          </div>
          <div class="form-group full">
            <label>বিবরণ (ঐচ্ছিক)</label>
            <input type="text" name="new_desc" placeholder="সংক্ষিপ্ত বিবরণ">
          </div>
          <div class="form-group full">
            <label>সাইজ / ভেরিয়েন্ট <span style="font-weight:400;text-transform:none;letter-spacing:0">(ঐচ্ছিক — কমা দিয়ে আলাদা করুন)</span></label>
            <input type="text" name="new_variants" placeholder="যেমন: S, M, L, XL  অথবা  100ml, 200ml, 500ml">
            <span class="hint">খালি রাখলে কোনো সাইজ অপশন থাকবে না</span>
          </div>
          <div class="form-group">
            <label>পণ্যের ছবি</label>
            <div class="img-drop">
              <input type="file" class="file-overlay" name="new_image" accept="image/*"
                     onchange="uploadZonePreview(this,'prev_new_img')">
              <div class="drop-content">
                <i class="fas fa-cloud-upload-alt"></i>
                ছবি বেছে নিন
                <small>JPG/PNG/WEBP · max 5MB</small>
              </div>
              <img id="prev_new_img" class="upload-preview" style="display:none">
            </div>
          </div>
          <div class="form-group">
            <label>Sample PDF</label>
            <div class="img-drop">
              <input type="file" class="file-overlay" name="new_pdf" accept="application/pdf,.pdf"
                     onchange="uploadZonePdf(this,'lbl_new_pdf')">
              <div class="drop-content">
                <i class="fas fa-file-pdf" style="color:#dc2626"></i>
                <span id="lbl_new_pdf">PDF বেছে নিন</span>
                <small>PDF · max 20MB</small>
              </div>
            </div>
          </div>
        </div>
        <div style="margin-top:18px;text-align:right">
          <button type="submit" class="btn btn-accent"><i class="fas fa-plus"></i> পণ্য যোগ করুন</button>
        </div>
      </form>
    </div>

    <!-- EXISTING BOOKS -->
    <div class="section-header">
      <span class="section-title">বিদ্যমান পণ্য</span>
      <span class="section-count"><?= count($books) ?> টি পণ্য</span>
    </div>

    <?php if (empty($books)): ?>
      <div class="empty-state"><i class="fas fa-box-open"></i>কোনো পণ্য নেই। উপরে যোগ করুন।</div>
    <?php else: ?>
      <div class="admin-books-grid">
        <?php foreach ($books as $book):
          $hasImg = !empty($book['image'])      && file_exists($UPLOAD_DIR.$book['image']);
          $hasPdf = !empty($book['sample_pdf']) && file_exists($PDF_DIR.$book['sample_pdf']);
        ?>
        <div class="admin-book-card">
          <div class="admin-book-img">
            <?php if ($hasImg): ?>
              <img src="<?= $UPLOAD_URL . htmlspecialchars($book['image']) ?>" alt="">
            <?php else: ?>
              <div class="admin-book-placeholder"><i class="fas fa-box-open"></i></div>
              <span class="no-img-badge">ছবি নেই</span>
            <?php endif; ?>
            <?php if ($hasPdf): ?><span class="pdf-badge-corner"><i class="fas fa-file-pdf"></i> PDF</span><?php endif; ?>
          </div>
          <div class="admin-book-body">
            <div class="admin-book-name"><?= htmlspecialchars($book['name']) ?></div>
            <?php if (!empty($book['author'])): ?>
              <div class="admin-book-author"><i class="fas fa-pen-nib" style="font-size:.6rem;margin-right:3px"></i><?= htmlspecialchars($book['author']) ?></div>
            <?php endif; ?>
            <div class="admin-book-price">৳<?= number_format((float)$book['price']) ?></div>
          </div>
          <div class="admin-book-footer">
            <button class="btn btn-ghost btn-sm" onclick="openEdit(<?= $book['id'] ?>)">
              <i class="fas fa-edit"></i> সম্পাদনা
            </button>
            <a href="?action=delete_book&id=<?= $book['id'] ?>"
               onclick="return confirm('\'<?= htmlspecialchars(addslashes($book['name'])) ?>\' মুছবেন?')"
               class="btn btn-danger btn-sm">
              <i class="fas fa-trash"></i>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div><!-- /tab-books -->

  <!-- ══ TAB: SHOP ══ -->
  <div class="tab-panel" id="tab-shop">
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_settings">

      <!-- Shop info -->
      <div class="card">
        <div class="card-title"><i class="fas fa-store"></i> শপের তথ্য</div>
        <div class="grid2">
          <div class="form-group">
            <label>শপের নাম</label>
            <input type="text" name="shop_name" value="<?= htmlspecialchars($settings['shop_name'] ?? '') ?>" placeholder="Urimas Books">
          </div>
          <div class="form-group">
            <label>bKash নম্বর (ম্যানুয়াল)</label>
            <input type="text" name="bkash_number" value="<?= htmlspecialchars($settings['bkash_number'] ?? '') ?>" placeholder="01XXXXXXXXX">
          </div>
          <div class="form-group">
            <label>ফোন কান্ট্রি কোড</label>
            <input type="text" name="country_code" value="<?= htmlspecialchars($country_code) ?>" placeholder="+880" maxlength="8" style="font-family:monospace;letter-spacing:.05em;max-width:120px">
            <span class="hint">বাংলাদেশ: +880 | ভারত: +91 | USA: +1</span>
          </div>
        </div>
      </div>

      <!-- Delivery charges -->
      <div class="card">
        <div class="card-title"><i class="fas fa-truck"></i> ডেলিভারি চার্জ</div>
        <div class="grid2">
          <div class="form-group">
            <label>ঢাকার মধ্যে (৳)</label>
            <input type="number" name="dhaka_charge" value="<?= (float)($settings['dhaka_charge'] ?? 80) ?>" step="1" min="0">
          </div>
          <div class="form-group">
            <label>ঢাকার বাইরে (৳)</label>
            <input type="number" name="outside_charge" value="<?= (float)($settings['outside_charge'] ?? 140) ?>" step="1" min="0">
          </div>
        </div>
      </div>

      <!-- bKash payment system -->
      <div class="card">
        <div class="card-title"><i class="fas fa-mobile-alt" style="color:#e2136e"></i> bKash পেমেন্ট সিস্টেম</div>

        <div class="form-group" style="margin-bottom:16px">
          <label>পেমেন্ট মোড</label>
          <div class="mode-toggle">
            <button type="button" class="mode-btn <?= $bkash_mode==='manual'?'active':'' ?>" onclick="setBkashMode('manual')" id="modeBtnManual">
              <i class="fas fa-qrcode"></i> ম্যানুয়াল (QR)
            </button>
            <button type="button" class="mode-btn <?= $bkash_mode==='api'?'active':'' ?>" onclick="setBkashMode('api')" id="modeBtnApi">
              <i class="fas fa-plug"></i> API (স্বয়ংক্রিয়)
            </button>
          </div>
          <input type="hidden" name="bkash_mode" id="bkashModeInput" value="<?= htmlspecialchars($bkash_mode) ?>">
          <span class="hint" id="bkashModeHint">
            <?= $bkash_mode==='manual' ? 'গ্রাহক QR স্ক্যান করে পে করবে — আপনি ম্যানুয়ালি যাচাই করবেন' : 'bKash API দিয়ে পেমেন্ট স্বয়ংক্রিয়ভাবে যাচাই হবে' ?>
          </span>
        </div>

        <!-- Manual mode: QR upload -->
        <div class="mode-section <?= $bkash_mode==='manual'?'show':'' ?>" id="sectionManual">
          <?php if ($bkash_qr_url): ?>
            <div class="qr-preview-wrap">
              <img src="<?= htmlspecialchars($bkash_qr_url) ?>" alt="QR Code" class="qr-preview-img">
              <div style="font-size:.8rem;color:var(--muted)">
                <strong style="display:block;color:var(--text);margin-bottom:2px">বর্তমান QR কোড</strong>
                নতুন আপলোড করলে পুরোনোটি সরে যাবে
              </div>
            </div>
          <?php else: ?>
            <p style="font-size:.8rem;color:var(--muted);margin-bottom:10px">কোনো QR কোড আপলোড হয়নি</p>
          <?php endif; ?>
          <div class="form-group">
            <label>bKash QR কোড ছবি</label>
            <div class="img-drop">
              <input type="file" class="file-overlay" name="bkash_qr_image" accept="image/*"
                     onchange="previewQr(this)">
              <div class="drop-content">
                <i class="fas fa-qrcode" style="color:#e2136e"></i>
                QR ছবি বেছে নিন
                <small>JPG/PNG · max 5MB</small>
              </div>
            </div>
            <img id="qrPreview" class="upload-preview" style="display:none;max-height:120px;margin-top:8px">
          </div>
        </div>

        <!-- API mode: credentials -->
        <div class="mode-section <?= $bkash_mode==='api'?'show':'' ?>" id="sectionApi">
          <div class="api-credentials">
            <div class="api-credentials-title"><i class="fas fa-key"></i> bKash API Credentials (Tokenized Checkout)</div>
            <div class="grid2" style="gap:12px">
              <div class="form-group">
                <label>App Key</label>
                <input type="text" name="bkash_app_key" value="<?= htmlspecialchars($settings['bkash_app_key'] ?? '') ?>" placeholder="bKash App Key">
              </div>
              <div class="form-group">
                <label>App Secret</label>
                <input type="password" name="bkash_app_secret" value="<?= htmlspecialchars($settings['bkash_app_secret'] ?? '') ?>" placeholder="bKash App Secret">
              </div>
              <div class="form-group">
                <label>Username</label>
                <input type="text" name="bkash_username" value="<?= htmlspecialchars($settings['bkash_username'] ?? '') ?>" placeholder="bKash Username">
              </div>
              <div class="form-group">
                <label>Password</label>
                <input type="password" name="bkash_password" value="<?= htmlspecialchars($settings['bkash_password'] ?? '') ?>" placeholder="bKash Password">
              </div>
            </div>
            <span class="hint" style="margin-top:8px;display:block">
              <i class="fas fa-info-circle"></i>
              bKash Merchant পোর্টাল থেকে Tokenized Checkout credentials নিন।
              Callback URL: <code style="background:#fff3cd;padding:2px 5px;border-radius:4px"><?= htmlspecialchars((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'],2).'/api/bkash-callback.php') ?></code>
            </span>
          </div>
        </div>
      </div>

      <!-- Banner section -->
      <div class="card">
        <div class="card-title"><i class="fas fa-image"></i> প্রোডাক্ট পেজ ব্যানার</div>

        <div class="toggle-row">
          <div>
            <div style="font-size:.88rem;font-weight:600;color:var(--text)">ব্যানার চালু করুন</div>
            <div style="font-size:.75rem;color:var(--muted)">শপের হোমপেজে প্রোডাক্ট সম্পর্কিত ব্যানার দেখাবে</div>
          </div>
          <label class="toggle-switch">
            <input type="checkbox" name="banner_enabled" <?= $banner_enabled?'checked':'' ?>>
            <span class="toggle-slider"></span>
          </label>
        </div>

        <div class="grid2" style="margin-bottom:16px">
          <div class="form-group">
            <label>ব্যানার শিরোনাম</label>
            <input type="text" name="banner_title" value="<?= htmlspecialchars($settings['banner_title'] ?? '') ?>" placeholder="যেমন: আমাদের বইয়ের সংগ্রহ">
          </div>
          <div class="form-group">
            <label>সাবটাইটেল / বিবরণ</label>
            <input type="text" name="banner_subtitle" value="<?= htmlspecialchars($settings['banner_subtitle'] ?? '') ?>" placeholder="যেমন: ইসলামি, শিক্ষামূলক ও শিশুতোষ বই">
          </div>
        </div>

        <div class="form-group">
          <label>ব্যানার ছবি (প্রশস্ত, ১৬:৫ অনুপাত ভালো)</label>
          <?php if ($banner_img_url): ?>
            <div class="banner-preview-wrap" style="margin-bottom:10px">
              <img src="<?= htmlspecialchars($banner_img_url) ?>" alt="Banner">
            </div>
          <?php endif; ?>
          <div class="img-drop">
            <input type="file" class="file-overlay" name="banner_image" accept="image/*"
                   onchange="previewBanner(this)">
            <div class="drop-content">
              <i class="fas fa-panorama"></i>
              ব্যানার ছবি বেছে নিন
              <small>JPG/PNG · max 5MB · প্রশস্ত ছবি ভালো</small>
            </div>
          </div>
          <div id="bannerPreviewWrap" style="margin-top:8px;display:none">
            <div class="banner-preview-wrap"><img id="bannerPreview" src="" alt="Preview"></div>
          </div>
        </div>
      </div>

      <!-- Theme color -->
      <div class="card">
        <div class="card-title"><i class="fas fa-palette"></i> থিম কালার</div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div class="form-group" style="flex:0 0 auto">
            <label>প্রাইমারি কালার</label>
            <div style="display:flex;align-items:center;gap:10px;margin-top:4px">
              <input type="color" name="theme_accent" id="themeAccentPicker"
                     value="<?= htmlspecialchars($theme_accent) ?>"
                     style="width:52px;height:44px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;padding:2px;background:var(--card)"
                     oninput="liveTheme(this.value)">
              <span id="themeAccentHex"
                    style="font-size:.85rem;font-weight:700;font-family:monospace;color:var(--text)"><?= htmlspecialchars($theme_accent) ?></span>
            </div>
          </div>
          <div class="form-group" style="flex:0 0 auto">
            <label>ব্যাকগ্রাউন্ড কালার</label>
            <div style="display:flex;align-items:center;gap:10px;margin-top:4px">
              <input type="color" id="bgColorPicker"
                     value="<?= htmlspecialchars($bg_color ?: '#fff5f7') ?>"
                     style="width:52px;height:44px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;padding:2px;background:var(--card)"
                     oninput="liveBg(this.value)">
              <span id="bgColorHex" style="font-size:.85rem;font-weight:700;font-family:monospace;color:var(--text)"><?= htmlspecialchars($bg_color ?: 'auto') ?></span>
              <button type="button" id="bgAutoBtn" onclick="resetBg()"
                      style="font-size:.72rem;padding:4px 10px;background:var(--accent-light);color:var(--accent);border:1px solid var(--border);border-radius:6px;cursor:pointer;font-weight:600;<?= $bg_color ? '' : 'opacity:.4' ?>">Auto</button>
            </div>
            <input type="hidden" name="bg_color" id="bgColorInput" value="<?= htmlspecialchars($bg_color) ?>">
            <span class="hint">কালার পিকার দিয়ে কাস্টম করুন অথবা Auto চাপলে Primary Color থেকে স্বয়ংক্রিয় ঠিক হবে</span>
          </div>
          <div class="form-group" style="flex:1;min-width:200px">
            <label>প্রিসেট কালার</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px" id="colorPresets">
              <?php foreach ([
                '#B5183D'=>'সাকুরা','#DC2626'=>'রেড','#EA580C'=>'অরেঞ্জ',
                '#CA8A04'=>'গোল্ড','#16A34A'=>'গ্রিন','#0284C7'=>'ব্লু',
                '#7C3AED'=>'পার্পল','#DB2777'=>'পিংক','#0F766E'=>'টিল',
                '#57534E'=>'ব্রাউন','#374151'=>'গ্রে','#111827'=>'ব্ল্যাক',
              ] as $hex => $label): ?>
                <button type="button" title="<?= $label ?>"
                        onclick="document.getElementById('themeAccentPicker').value='<?= $hex ?>';liveTheme('<?= $hex ?>')"
                        style="width:28px;height:28px;border-radius:6px;background:<?= $hex ?>;border:2px solid <?= $hex===$theme_accent?'#fff':'transparent' ?>;
                               outline:2px solid <?= $hex===$theme_accent?$hex:'transparent' ?>;cursor:pointer;transition:transform .15s"
                        onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform=''"></button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div style="margin-top:14px;padding:14px;background:var(--bg);border-radius:8px;display:flex;align-items:center;gap:12px;flex-wrap:wrap" id="themePreview">
          <span style="background:var(--accent);color:#fff;padding:8px 18px;border-radius:8px;font-weight:700;font-size:.85rem">Button</span>
          <span style="background:var(--accent-light);color:var(--accent);padding:8px 18px;border-radius:8px;font-weight:700;font-size:.85rem;border:1px solid var(--border)">Secondary</span>
          <span style="color:var(--accent);font-weight:800;font-size:1.05rem">৳১,২০০</span>
          <span style="color:var(--muted);font-size:.85rem">মিউটেড টেক্সট</span>
          <span style="color:var(--text);font-size:.85rem">সাধারণ টেক্সট</span>
        </div>
      </div>

      <!-- Facebook Pixel / Marketing -->
      <div class="card">
        <div class="card-title"><i class="fab fa-facebook" style="color:#1877f2"></i> Facebook Pixel (মার্কেটিং)</div>
        <div class="form-group">
          <label>Pixel ID</label>
          <input type="text" name="pixel_id" value="<?= htmlspecialchars($pixel_id) ?>"
                 placeholder="যেমন: 1234567890123456" maxlength="20"
                 style="font-family:monospace;letter-spacing:.05em">
          <span class="hint">Facebook Ads Manager → Events Manager থেকে Pixel ID কপি করুন। খালি রাখলে Pixel কাজ করবে না।</span>
        </div>
        <?php if ($pixel_id): ?>
          <div style="display:flex;align-items:center;gap:8px;margin-top:8px;padding:10px 14px;background:#e7f3ff;border:1.5px solid #b3d4fb;border-radius:8px">
            <i class="fas fa-check-circle" style="color:#1877f2"></i>
            <span style="font-size:.82rem;font-weight:600;color:#1877f2">Pixel সক্রিয়: <?= htmlspecialchars($pixel_id) ?></span>
          </div>
        <?php endif; ?>
        <div style="margin-top:14px;padding:12px 14px;background:var(--bg);border-radius:8px;font-size:.8rem;color:var(--muted);line-height:1.6">
          <strong style="color:var(--text)">যে Events ট্র্যাক হবে:</strong><br>
          <span style="color:#16a34a">●</span> <b>PageView</b> — পেজ লোড হলে<br>
          <span style="color:#16a34a">●</span> <b>ViewContent</b> — পণ্য দেখলে<br>
          <span style="color:#16a34a">●</span> <b>AddToCart</b> — পণ্য বাছলে<br>
          <span style="color:#16a34a">●</span> <b>InitiateCheckout</b> — অর্ডার ফর্ম খুললে<br>
          <span style="color:#16a34a">●</span> <b>Purchase</b> — অর্ডার সম্পন্ন হলে
        </div>
      </div>

      <div class="card">
        <div class="card-title"><i class="fas fa-bell"></i> নোটিফিকেশন</div>
        <div class="grid2">
          <div class="form-group">
            <label>অ্যাডমিন ইমেইল</label>
            <input type="email" name="admin_email" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>" placeholder="you@example.com">
            <span class="hint">নতুন অর্ডারে ইমেইল পাঠাবে</span>
          </div>
          <div class="form-group">
            <label>WhatsApp নম্বর</label>
            <input type="text" name="whatsapp_number" value="<?= htmlspecialchars($settings['whatsapp_number'] ?? '') ?>" placeholder="01XXXXXXXXX">
            <span class="hint">ড্যাশবোর্ড থেকে কাস্টমারকে WhatsApp পাঠাতে</span>
          </div>
        </div>
      </div>

      <div style="text-align:right">
        <button type="submit" class="btn btn-accent" style="padding:12px 32px;font-size:.95rem">
          <i class="fas fa-save"></i> সেভ করুন
        </button>
      </div>
    </form>
  </div><!-- /tab-shop -->

  <!-- ══ TAB: PASSWORD ══ -->
  <div class="tab-panel" id="tab-password">
    <div class="card" style="max-width:480px">
      <div class="card-title"><i class="fas fa-lock"></i> পাসওয়ার্ড পরিবর্তন</div>
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div style="display:flex;flex-direction:column;gap:14px">
          <div class="form-group">
            <label>বর্তমান পাসওয়ার্ড</label>
            <input type="password" name="current_password" required placeholder="••••••••">
          </div>
          <div class="form-group">
            <label>নতুন পাসওয়ার্ড</label>
            <input type="password" name="new_password" required placeholder="কমপক্ষে ৬ অক্ষর">
          </div>
          <div class="form-group">
            <label>নতুন পাসওয়ার্ড নিশ্চিত করুন</label>
            <input type="password" name="confirm_password" required placeholder="••••••••">
          </div>
          <button type="submit" class="btn btn-accent" style="margin-top:6px;padding:12px">
            <i class="fas fa-key"></i> পাসওয়ার্ড পরিবর্তন করুন
          </button>
        </div>
      </form>
    </div>
  </div><!-- /tab-password -->

</div><!-- /page -->

<!-- ══ EDIT MODAL ══ -->
<div class="modal-overlay" id="editModal" onclick="if(event.target===this)closeEdit()">
  <div class="modal-box">
    <div class="modal-header">
      <h3><i class="fas fa-edit" style="margin-right:8px"></i><span id="editModalTitle">পণ্য সম্পাদনা</span></h3>
      <button class="modal-close-btn" onclick="closeEdit()">✕</button>
    </div>
    <div class="modal-body">
      <form id="editForm" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action"        value="update_book">
        <input type="hidden" name="book_id"       id="editId">
        <input type="hidden" name="image_current" id="editImgCurrent">
        <input type="hidden" name="pdf_current"   id="editPdfCurrent">

        <div class="grid2" style="gap:14px;margin-bottom:14px">
          <div class="form-group">
            <label>পণ্যের নাম *</label>
            <input type="text" name="name" id="editName" required placeholder="পণ্যের নাম">
          </div>
          <div class="form-group">
            <label>ব্র্যান্ড / লেখক</label>
            <input type="text" name="author" id="editAuthor" placeholder="ব্র্যান্ড বা লেখকের নাম">
          </div>
          <div class="form-group">
            <label>মূল্য (৳) *</label>
            <input type="number" name="price" id="editPrice" step="1" min="0" required>
          </div>
          <div class="form-group">
            <label>বিবরণ</label>
            <input type="text" name="description" id="editDesc" placeholder="সংক্ষিপ্ত বিবরণ">
          </div>
          <div class="form-group full">
            <label>সাইজ / ভেরিয়েন্ট</label>
            <input type="text" name="variants" id="editVariants" placeholder="S, M, L, XL  অথবা  100ml, 200ml">
            <span class="hint">কমা দিয়ে আলাদা করুন। খালি রাখলে কোনো সাইজ অপশন থাকবে না।</span>
          </div>
        </div>

        <!-- Image section -->
        <div class="form-group" style="margin-bottom:14px">
          <label>পণ্যের ছবি</label>
          <div class="current-image-wrap" id="editImgWrap">
            <div class="current-img-thumb" id="editImgThumb"><i class="fas fa-box-open"></i></div>
            <div class="current-img-info">
              <strong id="editImgLabel">কোনো ছবি নেই</strong>
              নতুন ছবি দিয়ে পরিবর্তন করতে নিচে আপলোড করুন
            </div>
          </div>
          <div class="img-drop">
            <input type="file" id="inp_edit_img" class="file-overlay" name="image" accept="image/*"
                   onchange="uploadZonePreview(this,'prev_edit_img'); previewEditImg(this)">
            <div class="drop-content">
              <i class="fas fa-cloud-upload-alt"></i>
              <span id="editImgDropLabel">নতুন ছবি আপলোড (ঐচ্ছিক)</span>
              <small>JPG/PNG · max 5MB</small>
            </div>
            <img id="prev_edit_img" class="upload-preview" style="display:none">
          </div>
        </div>

        <!-- PDF section -->
        <div class="form-group">
          <label>Sample PDF</label>
          <div id="editPdfStatus" style="margin-bottom:8px"></div>
          <div class="img-drop">
            <input type="file" id="inp_edit_pdf" class="file-overlay" name="pdf" accept="application/pdf,.pdf"
                   onchange="uploadZonePdf(this,'lbl_edit_pdf')">
            <div class="drop-content">
              <i class="fas fa-file-pdf" style="color:#dc2626"></i>
              <span id="lbl_edit_pdf">নতুন PDF আপলোড (ঐচ্ছিক)</span>
              <small>PDF · max 20MB</small>
            </div>
          </div>
        </div>

      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeEdit()">বাতিল</button>
      <button type="submit" form="editForm" class="btn btn-accent">
        <i class="fas fa-save"></i> সেভ করুন
      </button>
    </div>
  </div>
</div>

<script>
// JSON_HEX_TAG + fallback '[]' for safe JS embedding
const allBooks = <?= json_encode($booksJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?: '[]' ?>;
const PDF_URL  = '<?= addslashes($PDF_URL) ?>';

// ─── Tab switching ─────────────────────────────────────
function switchTab(name) {
  document.querySelectorAll('.tab').forEach((t, i) => {
    t.classList.toggle('active', ['books','shop','password'][i] === name);
  });
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  history.replaceState(null, '', '#' + name);
}
const hash = location.hash.replace('#','');
if (['books','shop','password'].includes(hash)) switchTab(hash);

// ─── Edit Modal ────────────────────────────────────────
function openEdit(id) {
  const book = allBooks.find(b => b.id === id);
  if (!book) return;

  document.getElementById('editModalTitle').textContent = book.name;
  document.getElementById('editId').value       = id;
  document.getElementById('editName').value     = book.name;
  document.getElementById('editAuthor').value   = book.author;
  document.getElementById('editDesc').value     = book.description;
  document.getElementById('editPrice').value    = book.price;
  document.getElementById('editVariants').value = (book.variants || []).join(', ');
  document.getElementById('editImgCurrent').value = book.image;
  document.getElementById('editPdfCurrent').value = book.sample_pdf;

  // Current image preview
  const thumb = document.getElementById('editImgThumb');
  const label = document.getElementById('editImgLabel');
  if (book.image_url) {
    thumb.innerHTML = `<img src="${book.image_url}" style="width:60px;height:80px;object-fit:cover;border-radius:6px">`;
    label.textContent = 'ছবি আছে';
  } else {
    thumb.innerHTML = '<i class="fas fa-box-open" style="font-size:1.6rem;color:var(--accent);opacity:.4"></i>';
    label.textContent = 'কোনো ছবি নেই';
  }

  // PDF status
  const pdfStatus = document.getElementById('editPdfStatus');
  if (book.has_pdf) {
    pdfStatus.innerHTML = `<div class="pdf-current-row">
      <i class="fas fa-file-pdf" style="color:#dc2626"></i>
      <span class="pdf-filename">${book.sample_pdf}</span>
      <a href="?action=delete_pdf&id=${id}" onclick="return confirm('Sample PDF মুছবেন?')"
         class="btn btn-danger btn-xs">মুছুন</a>
    </div>`;
  } else {
    pdfStatus.innerHTML = '<p style="font-size:.78rem;color:var(--muted);margin-bottom:6px">কোনো Sample PDF নেই</p>';
  }

  // Reset upload zone labels and previews
  const lbl1 = document.getElementById('editImgDropLabel');
  if (lbl1) { lbl1.textContent = 'নতুন ছবি আপলোড (ঐচ্ছিক)'; lbl1.style.color = ''; }
  const lbl2 = document.getElementById('lbl_edit_pdf');
  if (lbl2) { lbl2.textContent = 'নতুন PDF আপলোড (ঐচ্ছিক)'; lbl2.style.color = ''; }
  const pv = document.getElementById('prev_edit_img');
  if (pv) { pv.src = ''; pv.style.display = 'none'; }
  // Clear file inputs
  const fi = document.getElementById('inp_edit_img');
  if (fi) fi.value = '';
  const fp = document.getElementById('inp_edit_pdf');
  if (fp) fp.value = '';

  document.getElementById('editModal').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeEdit() {
  document.getElementById('editModal').classList.remove('show');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeEdit(); });

// ─── Upload zone helpers ────────────────────────────────
// Show image preview inside the zone
function uploadZonePreview(input, imgId) {
  if (!input.files[0]) return;
  const img = document.getElementById(imgId);
  if (!img) return;
  img.src = URL.createObjectURL(input.files[0]);
  img.style.display = 'block';
  // Green border on zone
  const zone = img.closest('.img-drop');
  if (zone) zone.style.borderColor = 'var(--accent)';
}

// Show PDF filename inside the zone
function uploadZonePdf(input, spanId) {
  if (!input.files[0]) return;
  const el = document.getElementById(spanId);
  if (el) {
    const sz = (input.files[0].size / 1024 / 1024).toFixed(1);
    el.textContent = input.files[0].name + ' (' + sz + ' MB) ✓';
    el.style.color = 'var(--accent)';
  }
  const zone = input.closest('.img-drop');
  if (zone) zone.style.borderColor = '#dc2626';
}

// Edit modal — also update the thumb preview
function previewEditImg(input) {
  if (!input.files[0]) return;
  const url = URL.createObjectURL(input.files[0]);
  const lbl = document.getElementById('editImgDropLabel');
  if (lbl) lbl.textContent = input.files[0].name + ' ✓';
  const thumb = document.getElementById('editImgThumb');
  if (thumb) thumb.innerHTML = `<img src="${url}" style="width:60px;height:80px;object-fit:cover;border-radius:6px">`;
  const label = document.getElementById('editImgLabel');
  if (label) label.textContent = input.files[0].name;
}

// Flash auto-dismiss
const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => { flash.style.transition='opacity .5s'; flash.style.opacity='0'; }, 4000);

// ─── Live theme preview ─────────────────────────────────
const _mix = (r1,g1,b1,r2,g2,b2,w) => [Math.round(r1*w+r2*(1-w)),Math.round(g1*w+g2*(1-w)),Math.round(b1*w+b2*(1-w))];
const _hex = (r,g,b) => '#'+[r,g,b].map(v=>Math.max(0,Math.min(255,v)).toString(16).padStart(2,'0')).join('');

function liveTheme(hex) {
  if (!/^#[0-9A-Fa-f]{6}$/.test(hex)) return;
  document.getElementById('themeAccentHex').textContent = hex;
  document.querySelectorAll('#colorPresets button').forEach(btn => {
    const c = btn.getAttribute('onclick').match(/'(#[0-9A-Fa-f]{6})'/)?.[1];
    btn.style.outline = c === hex ? `2px solid ${c}` : 'none';
    btn.style.border  = c === hex ? '2px solid #fff' : '2px solid transparent';
  });
  const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
  const root = document.documentElement.style;
  root.setProperty('--accent',       hex);
  root.setProperty('--accent-dark',  _hex(..._mix(r,g,b,0,0,0,.75)));
  root.setProperty('--accent-grad',  _hex(Math.min(255,r+50),Math.min(255,g+30),Math.min(255,b+30)));
  root.setProperty('--accent-light', _hex(..._mix(r,g,b,255,255,255,.12)));
  root.setProperty('--border',       _hex(..._mix(r,g,b,255,255,255,.20)));
  root.setProperty('--sakura',       _hex(..._mix(r,g,b,255,255,255,.30)));
  root.setProperty('--text',         _hex(..._mix(r,g,b,17,17,17,.20)));
  root.setProperty('--muted',        _hex(..._mix(r,g,b,136,136,136,.25)));
  // Only update --bg from accent if no custom bg set
  if (!document.getElementById('bgColorInput').value) {
    root.setProperty('--bg', _hex(..._mix(r,g,b,255,255,255,.04)));
  }
}

function liveBg(hex) {
  if (!/^#[0-9A-Fa-f]{6}$/.test(hex)) return;
  document.getElementById('bgColorInput').value = hex;
  document.getElementById('bgColorHex').textContent = hex;
  document.getElementById('bgAutoBtn').style.opacity = '1';
  document.documentElement.style.setProperty('--bg', hex);
}

function resetBg() {
  document.getElementById('bgColorInput').value = '';
  document.getElementById('bgColorHex').textContent = 'auto';
  document.getElementById('bgAutoBtn').style.opacity = '.4';
  // Recompute from accent
  const hex = document.getElementById('themeAccentPicker').value;
  const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
  document.documentElement.style.setProperty('--bg', _hex(..._mix(r,g,b,255,255,255,.04)));
}

// ─── bKash mode toggle ──────────────────────────────────
function setBkashMode(mode) {
  document.getElementById('bkashModeInput').value = mode;
  document.getElementById('modeBtnManual').classList.toggle('active', mode === 'manual');
  document.getElementById('modeBtnApi').classList.toggle('active', mode === 'api');
  document.getElementById('sectionManual').classList.toggle('show', mode === 'manual');
  document.getElementById('sectionApi').classList.toggle('show', mode === 'api');
  document.getElementById('bkashModeHint').textContent = mode === 'manual'
    ? 'গ্রাহক QR স্ক্যান করে পে করবে — আপনি ম্যানুয়ালি যাচাই করবেন'
    : 'bKash API দিয়ে পেমেন্ট স্বয়ংক্রিয়ভাবে যাচাই হবে';
}

// QR code preview
function previewQr(input) {
  if (!input.files[0]) return;
  const img = document.getElementById('qrPreview');
  img.src = URL.createObjectURL(input.files[0]);
  img.style.display = 'block';
  input.closest('.img-drop').style.borderColor = '#e2136e';
}

// Banner preview
function previewBanner(input) {
  if (!input.files[0]) return;
  const wrap = document.getElementById('bannerPreviewWrap');
  const img  = document.getElementById('bannerPreview');
  img.src = URL.createObjectURL(input.files[0]);
  wrap.style.display = 'block';
  input.closest('.img-drop').style.borderColor = 'var(--accent)';
}
</script>
</body>
</html>
