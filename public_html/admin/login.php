<?php
session_start();
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php'); exit;
}

require_once '../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'ইউজারনেম ও পাসওয়ার্ড দিন';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin['username'];
                header('Location: dashboard.php'); exit;
            } else {
                $error = 'ইউজারনেম বা পাসওয়ার্ড সঠিক নয়';
            }
        } catch (Exception $e) {
            $error = 'লগইন করতে সমস্যা হয়েছে';
        }
    }
}

// Load shop name
try {
    $s = $pdo->query("SELECT shop_name FROM settings LIMIT 1")->fetch();
    $shop_name = htmlspecialchars($s['shop_name'] ?? 'Urimas Books');
} catch (Exception $e) {
    $shop_name = 'Urimas Books';
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — <?= $shop_name ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --accent: #B5183D; --accent-dark: #8B1A2B; --accent-light: #FCE8EE;
      --bg: #FFF5F7; --card: #fff; --text: #2C1810; --muted: #9B7B82; --border: #F0D0D8;
      --red: #dc2626;
    }
    body {
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      background: linear-gradient(135deg, #FFF5F7 0%, #FCE8EE 40%, #F8C8D4 100%);
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      padding: 20px;
    }
    .brand {
      text-align: center; margin-bottom: 28px;
    }
    .brand-icon {
      width: 56px; height: 56px; background: var(--accent);
      border-radius: 16px; display: flex; align-items: center; justify-content: center;
      margin: 0 auto 12px;
      box-shadow: 0 8px 24px rgba(181,24,61,.3);
    }
    .brand-icon i { font-size: 1.4rem; color: #fff; }
    .brand-name {
      font-size: 1.3rem; font-weight: 800; color: var(--text);
      text-decoration: none;
    }
    .brand-name:hover { color: var(--accent); }
    .brand-sub { font-size: .8rem; color: var(--muted); margin-top: 3px; }

    .card {
      background: var(--card); border-radius: 20px;
      padding: 32px 28px; width: 100%; max-width: 380px;
      box-shadow: 0 12px 40px rgba(181,24,61,.12), 0 4px 12px rgba(0,0,0,.06);
    }
    .card h2 { font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 24px; text-align: center; }

    .error {
      background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b;
      border-radius: 10px; padding: 10px 14px; font-size: .85rem;
      margin-bottom: 18px; display: flex; align-items: center; gap: 8px;
    }

    .form-group { margin-bottom: 16px; }
    label { display: block; font-size: .74rem; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
    .input-wrap { position: relative; }
    .input-wrap i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: .9rem; }
    input[type=text], input[type=password] {
      width: 100%; padding: 11px 14px 11px 38px;
      background: #f8fffe; border: 1.5px solid var(--border);
      border-radius: 10px; font-size: .9rem; font-family: inherit; color: var(--text);
      outline: none; transition: border-color .2s, box-shadow .2s;
    }
    input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(181,24,61,.12); background: #fff; }

    .submit-btn {
      width: 100%; padding: 13px;
      background: linear-gradient(135deg, var(--accent-grad) 0%, var(--accent) 100%);
      color: #fff; border: none; border-radius: 10px;
      font-size: .95rem; font-weight: 700; font-family: inherit; cursor: pointer;
      margin-top: 8px; box-shadow: 0 4px 16px rgba(181,24,61,.3);
      transition: transform .15s, box-shadow .15s;
    }
    .submit-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(181,24,61,.4); }
    .submit-btn:active { transform: translateY(0); }

    .back-link { display: block; text-align: center; margin-top: 18px; font-size: .82rem; color: var(--muted); text-decoration: none; }
    .back-link:hover { color: var(--accent); }
  </style>
  <?= buildThemeCss(loadThemeAccent(), loadThemeBgColor()) ?>
</head>
<body>

<div class="brand">
  <div class="brand-icon"><i class="fas fa-leaf"></i></div>
  <a href="../index.php" class="brand-name"><?= $shop_name ?></a>
  <div class="brand-sub">Admin Panel</div>
</div>

<div class="card">
  <h2><i class="fas fa-lock" style="color:var(--accent);margin-right:8px"></i>লগইন করুন</h2>

  <?php if ($error): ?>
    <div class="error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>ইউজারনেম</label>
      <div class="input-wrap">
        <i class="fas fa-user"></i>
        <input type="text" name="username" placeholder="username" required autocomplete="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
    </div>
    <div class="form-group">
      <label>পাসওয়ার্ড</label>
      <div class="input-wrap">
        <i class="fas fa-lock"></i>
        <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
      </div>
    </div>
    <button type="submit" class="submit-btn">
      <i class="fas fa-sign-in-alt" style="margin-right:8px"></i>লগইন
    </button>
  </form>
</div>

<a href="../index.php" class="back-link"><i class="fas fa-arrow-left" style="margin-right:4px"></i>শপে ফিরে যান</a>

</body>
</html>
