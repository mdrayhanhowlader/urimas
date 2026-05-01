<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php'); exit;
}
require_once '../config.php';

$settings        = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
$whatsapp_number = preg_replace('/\D/', '', $settings['whatsapp_number'] ?? '');
$shop_name       = htmlspecialchars($settings['shop_name'] ?? 'Urimas Books');

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    $order_id = (int)$_POST['order_id'];
    $status   = sanitize($_POST['status']);
    if (in_array($status, ['pending','confirmed','delivered'])) {
        $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status, $order_id]);
        $flash = ['ok','অর্ডার স্ট্যাটাস আপডেট হয়েছে'];
    }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([(int)$_GET['delete']]);
    header('Location: dashboard.php?deleted=1'); exit;
}
if (isset($_GET['deleted'])) $flash = ['ok','অর্ডার মুছে ফেলা হয়েছে'];

$orders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC")->fetchAll();
$stats  = $pdo->query("SELECT COUNT(*) total, SUM(status='pending') pending, SUM(status='confirmed') confirmed, SUM(status='delivered') delivered, SUM(total) revenue FROM orders")->fetch();

function parseBooks($row) {
    if (!empty($row['books_json'])) {
        $d = json_decode($row['books_json'], true);
        if (is_array($d) && count($d)) return $d;
    }
    return [];
}

function buildWaText($order, $books_label) {
    $area = $order['area']==='dhaka' ? 'ঢাকা' : 'ঢাকার বাইরে';
    $pay  = $order['payment_method']==='bkash' ? 'bKash' : 'Cash on Delivery';
    $txn  = $order['transaction_id'] ? "\nTXN: {$order['transaction_id']}" : '';
    return rawurlencode("অর্ডার #{$order['id']} নিশ্চিত হয়েছে 🎉\nপণ্য: {$books_label}\nমোট: ৳{$order['total']}\nপেমেন্ট: {$pay}{$txn}\nডেলিভারি: {$area}\nধন্যবাদ!");
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — <?= $shop_name ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --accent: #B5183D; --accent-light: #FCE8EE; --accent-dark: #8B1A2B;
      --bg: #FFF5F7; --card: #fff;
      --text: #2C1810; --muted: #9B7B82; --border: #F0D0D8;
      --yellow: #d97706; --red: #dc2626; --blue: #1e40af;
      --radius: 14px; --radius-sm: 8px;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

    /* ─── NAV ─── */
    .nav { background: #fff; border-bottom: 1px solid var(--border); padding: 0 16px; position: sticky; top: 0; z-index: 100; }
    .nav-inner { max-width: 1200px; margin: 0 auto; height: 56px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .nav-brand { font-size: 1rem; font-weight: 800; color: var(--accent); text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; min-width: 0; }
    .nav-links { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
    .btn { display: inline-flex; align-items: center; gap: 5px; padding: 7px 13px; border-radius: 8px;
           font-size: .8rem; font-weight: 600; border: none; cursor: pointer; font-family: inherit;
           text-decoration: none; transition: all .15s; white-space: nowrap; }
    .btn-ghost  { background: var(--bg); color: var(--text); }
    .btn-ghost:hover  { background: var(--accent-light); color: var(--accent); }
    .btn-danger { background: #fee2e2; color: var(--red); }
    .btn-danger:hover { background: #fecaca; }
    .btn-accent { background: var(--accent); color: #fff; }
    @media (max-width: 520px) {
      .nav-links .btn span { display: none; }
      .nav-links .btn { padding: 8px 10px; }
    }

    /* ─── PAGE ─── */
    .page { max-width: 1200px; margin: 0 auto; padding: 20px 14px 60px; }

    /* ─── FLASH ─── */
    .flash { padding: 11px 16px; border-radius: var(--radius-sm); margin-bottom: 20px;
             font-size: .88rem; font-weight: 500; display: flex; align-items: center; gap: 8px; }
    .flash.ok  { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .flash.err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

    /* ─── STATS ─── */
    .stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 24px; }
    @media (max-width: 640px) { .stats { grid-template-columns: repeat(2,1fr); gap: 10px; } }
    .stat-card { background: var(--card); border-radius: var(--radius); padding: 16px 18px;
                 box-shadow: 0 1px 4px rgba(181,24,61,.07); border: 1px solid var(--border); }
    .stat-icon { font-size: 1.3rem; margin-bottom: 8px; }
    .stat-val  { font-size: 1.5rem; font-weight: 800; line-height: 1; }
    .stat-lbl  { font-size: .74rem; color: var(--muted); font-weight: 500; margin-top: 4px; }

    /* ─── SECTION HEAD ─── */
    .section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .section-title { font-size: 1rem; font-weight: 700; }
    .order-count { font-size: .78rem; color: var(--muted); background: var(--card); padding: 3px 10px; border-radius: 20px; border: 1px solid var(--border); }

    /* ─── DESKTOP TABLE ─── */
    .table-wrap { background: var(--card); border-radius: var(--radius); overflow: hidden;
                  box-shadow: 0 1px 4px rgba(181,24,61,.07); border: 1px solid var(--border); }
    .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; font-size: .84rem; }
    thead { background: var(--accent-light); }
    th { padding: 11px 14px; text-align: left; font-size: .72rem; font-weight: 700;
         color: var(--accent); text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
    td { padding: 11px 14px; border-top: 1px solid var(--border); vertical-align: top; }
    tr:hover td { background: #fffafc; }

    .badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: .7rem; font-weight: 700; white-space: nowrap; }
    .badge-pending   { background: #fef3c7; color: #92400e; }
    .badge-confirmed { background: #dbeafe; color: var(--blue); }
    .badge-delivered { background: #d1fae5; color: #065f46; }

    .pay-badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: .68rem; font-weight: 600; }
    .pay-bkash { background: #fff0f0; color: #dc2626; }
    .pay-cod   { background: #f0fdf4; color: #065f46; }

    select.status-sel { padding: 5px 8px; border-radius: 6px; border: 1.5px solid var(--border);
                        font-size: .78rem; font-family: inherit; background: #fff; color: var(--text);
                        cursor: pointer; outline: none; }
    select.status-sel:focus { border-color: var(--accent); }

    .act-btns { display: flex; gap: 5px; flex-wrap: wrap; }
    .btn-sm { padding: 5px 10px; font-size: .72rem; border-radius: 6px; }
    .btn-wa  { background: #e8f5e9; color: #2e7d32; }
    .btn-wa:hover { background: #c8e6c9; }
    .btn-del { background: #fee2e2; color: var(--red); }
    .btn-del:hover { background: #fecaca; }
    .btn-info { background: var(--accent-light); color: var(--accent); }
    .btn-info:hover { background: #F8C8D4; }

    .books-list { list-style: none; }
    .books-list li { font-size: .78rem; color: var(--muted); }
    .books-list li::before { content: '• '; color: var(--accent); }
    .cust-name  { font-weight: 700; }
    .cust-phone { font-size: .76rem; color: var(--muted); }
    .cust-addr  { font-size: .72rem; color: var(--muted); margin-top: 2px; max-width: 150px; }

    /* desktop only */
    @media (max-width: 767px) { .desktop-only { display: none !important; } }
    @media (min-width: 768px) { .mobile-only  { display: none !important; } }

    /* ─── MOBILE ORDER CARDS ─── */
    .order-cards { display: flex; flex-direction: column; gap: 12px; }

    .order-card {
      background: var(--card); border-radius: var(--radius);
      border: 1.5px solid var(--border);
      box-shadow: 0 1px 4px rgba(181,24,61,.06);
      overflow: hidden;
    }
    .oc-head {
      display: flex; align-items: center; justify-content: space-between;
      padding: 12px 14px; background: var(--accent-light);
      gap: 8px;
    }
    .oc-id   { font-weight: 800; color: var(--accent); font-size: .9rem; }
    .oc-date { font-size: .7rem; color: var(--muted); }
    .oc-body { padding: 12px 14px; display: flex; flex-direction: column; gap: 8px; }
    .oc-row  { display: flex; gap: 8px; align-items: flex-start; font-size: .83rem; }
    .oc-lbl  { font-size: .68rem; font-weight: 700; color: var(--muted); text-transform: uppercase;
               letter-spacing: .04em; min-width: 56px; padding-top: 2px; flex-shrink: 0; }
    .oc-val  { color: var(--text); flex: 1; }
    .oc-books { font-size: .78rem; color: var(--muted); }
    .oc-books span { display: block; }
    .oc-books span::before { content: '• '; color: var(--accent); }
    .oc-footer {
      padding: 10px 14px; border-top: 1px solid var(--border);
      display: flex; gap: 6px; flex-wrap: wrap; align-items: center;
    }
    .oc-status-wrap { flex: 1; min-width: 130px; }
    .oc-total { font-size: 1rem; font-weight: 800; color: var(--accent); margin-left: auto; }

    /* ─── ORDER DETAIL MODAL ─── */
    .modal-overlay { position: fixed; inset: 0; background: rgba(44,24,16,.55);
                     z-index: 200; display: flex; align-items: flex-end; justify-content: center;
                     padding: 0; opacity: 0; pointer-events: none; transition: opacity .25s;
                     backdrop-filter: blur(4px); }
    .modal-overlay.show { opacity: 1; pointer-events: all; }
    .modal-box { background: var(--card); border-radius: 20px 20px 0 0;
                 width: 100%; max-width: 560px; max-height: 92vh; overflow-y: auto;
                 transform: translateY(100%);
                 transition: transform .35s cubic-bezier(.34,1.2,.64,1);
                 box-shadow: 0 -8px 40px rgba(0,0,0,.18); }
    .modal-overlay.show .modal-box { transform: translateY(0); }
    @media (min-width: 600px) {
      .modal-overlay { align-items: center; padding: 20px; }
      .modal-box { border-radius: var(--radius); transform: scale(.95) translateY(16px); max-height: 90vh; }
      .modal-overlay.show .modal-box { transform: scale(1) translateY(0); }
    }
    .modal-head { background: linear-gradient(135deg, var(--accent-grad) 0%, var(--accent) 100%);
                  color: #fff; padding: 18px 20px;
                  display: flex; align-items: center; justify-content: space-between;
                  position: sticky; top: 0; z-index: 2; }
    .modal-head h3 { font-size: .95rem; font-weight: 700; }
    .modal-close-btn { background: rgba(255,255,255,.2); border: none; color: #fff;
                       width: 30px; height: 30px; border-radius: 50%; cursor: pointer;
                       font-size: .95rem; display: flex; align-items: center; justify-content: center; }
    .modal-close-btn:hover { background: rgba(255,255,255,.35); }
    .modal-body { padding: 18px 20px; }
    .detail-row { display: flex; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: .86rem; }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { font-weight: 700; color: var(--muted); min-width: 100px; flex-shrink: 0; font-size: .74rem; text-transform: uppercase; padding-top: 1px; }
    .detail-val { color: var(--text); }
    .modal-books { background: var(--accent-light); border-radius: 8px; padding: 10px 12px; margin: 10px 0; }
    .modal-book-item { display: flex; justify-content: space-between; font-size: .84rem; padding: 3px 0; }
    .modal-totals { background: var(--bg); border-radius: 8px; padding: 10px 12px; }
    .modal-total-row { display: flex; justify-content: space-between; font-size: .82rem; padding: 3px 0; color: var(--muted); }
    .modal-total-row.grand { font-weight: 800; color: var(--text); font-size: .92rem; border-top: 1px solid var(--border); margin-top: 6px; padding-top: 8px; }
    .modal-actions { display: flex; gap: 8px; flex-wrap: wrap; padding: 14px 20px; border-top: 1px solid var(--border); }
    .modal-actions .btn { flex: 1; justify-content: center; min-width: 120px; padding: 11px 16px; font-size: .84rem; }
  </style>
  <?= buildThemeCss(loadThemeAccent(), loadThemeBgColor()) ?>
</head>
<body>

<nav class="nav">
  <div class="nav-inner">
    <a href="../index.php" class="nav-brand"><?= $shop_name ?> Dashboard</a>
    <div class="nav-links">
      <a href="settings.php" class="btn btn-ghost"><i class="fas fa-cog"></i><span> সেটিংস</span></a>
      <a href="../index.php" class="btn btn-ghost" target="_blank"><i class="fas fa-store"></i><span> শপ</span></a>
      <a href="logout.php"   class="btn btn-danger"><i class="fas fa-sign-out-alt"></i><span> লগআউট</span></a>
    </div>
  </div>
</nav>

<div class="page">

  <?php if ($flash): ?>
    <div class="flash <?= $flash[0] ?>"><i class="fas fa-<?= $flash[0]==='ok'?'check-circle':'exclamation-circle' ?>"></i> <?= htmlspecialchars($flash[1]) ?></div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-icon">🛒</div>
      <div class="stat-val"><?= (int)$stats['total'] ?></div>
      <div class="stat-lbl">মোট অর্ডার</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">⏳</div>
      <div class="stat-val" style="color:var(--yellow)"><?= (int)$stats['pending'] ?></div>
      <div class="stat-lbl">পেন্ডিং</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">📦</div>
      <div class="stat-val" style="color:#065f46"><?= (int)$stats['delivered'] ?></div>
      <div class="stat-lbl">ডেলিভারি হয়েছে</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">💰</div>
      <div class="stat-val" style="color:var(--accent)">৳<?= number_format((float)$stats['revenue'],0) ?></div>
      <div class="stat-lbl">মোট রাজস্ব</div>
    </div>
  </div>

  <!-- Section head -->
  <div class="section-head">
    <span class="section-title">সব অর্ডার</span>
    <span class="order-count"><?= count($orders) ?> টি অর্ডার</span>
  </div>

  <!-- ══ DESKTOP TABLE ══ -->
  <div class="table-wrap desktop-only">
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>#</th><th>কাস্টমার</th><th>পণ্য</th><th>পেমেন্ট</th>
            <th>মোট</th><th>স্ট্যাটাস</th><th>তারিখ</th><th>অ্যাকশন</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:40px">কোনো অর্ডার নেই</td></tr>
          <?php endif; ?>
          <?php foreach ($orders as $order):
            $books       = parseBooks($order);
            $books_label = $books ? implode(', ', array_column($books,'name')) : 'N/A';
            $wa_text     = buildWaText($order, $books_label);
            $customer_wa = '880' . ltrim($order['phone'],'0');
          ?>
          <tr>
            <td>
              <span style="font-weight:800;color:var(--accent)">#<?= $order['id'] ?></span><br>
              <button class="btn btn-info btn-sm" style="margin-top:4px" onclick='openDetail(<?= htmlspecialchars(json_encode([
                'id'=>$order['id'],'name'=>$order['name'],'phone'=>$order['phone'],
                'email'=>$order['email']??'',
                'address'=>$order['address'],'area'=>$order['area'],
                'payment_method'=>$order['payment_method']??'bkash',
                'transaction_id'=>$order['transaction_id']??'',
                'total'=>$order['total'],'status'=>$order['status'],
                'created_at'=>$order['created_at'],'books'=>$books,
              ], JSON_UNESCAPED_UNICODE)) ?>)'>বিস্তারিত</button>
            </td>
            <td>
              <div class="cust-name"><?= htmlspecialchars($order['name']) ?></div>
              <div class="cust-phone"><?= htmlspecialchars($order['phone']) ?></div>
              <div class="cust-addr"><?= htmlspecialchars(mb_strimwidth($order['address'],0,55,'…')) ?></div>
              <div style="font-size:.7rem;color:var(--muted);margin-top:2px"><?= $order['area']==='dhaka'?'📍 ঢাকা':'📍 ঢাকার বাইরে' ?></div>
            </td>
            <td>
              <?php if ($books): ?>
                <ul class="books-list" style="list-style:none">
                  <?php foreach ($books as $b):
                    $imgFile = $b['image'] ?? '';
                    $imgUrl  = $imgFile ? '../assets/images/books/'.htmlspecialchars($imgFile) : '';
                  ?>
                    <li style="display:flex;align-items:center;gap:7px;padding:3px 0">
                      <?php if ($imgUrl): ?>
                        <img src="<?= $imgUrl ?>" style="width:28px;height:28px;object-fit:cover;border-radius:4px;flex-shrink:0" onerror="this.style.display='none'">
                      <?php else: ?>
                        <div style="width:28px;height:28px;background:var(--accent-light);border-radius:4px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-box" style="color:var(--accent);font-size:.55rem"></i></div>
                      <?php endif; ?>
                      <span><?= htmlspecialchars($b['name']) ?>
                        <?php if (!empty($b['variant'])): ?>
                          <span style="font-size:.6rem;font-weight:700;background:var(--accent);color:#fff;padding:1px 6px;border-radius:20px;margin-left:3px;vertical-align:middle"><?= htmlspecialchars($b['variant']) ?></span>
                        <?php endif; ?>
                        <strong style="color:var(--accent)"> ৳<?= number_format((float)$b['price'],0) ?></strong>
                      </span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
            </td>
            <td>
              <?php if ($order['payment_method']==='bkash'): ?>
                <span class="pay-badge pay-bkash">bKash</span><br>
                <span style="font-size:.7rem;color:var(--muted)"><?= htmlspecialchars($order['transaction_id']??'') ?></span>
              <?php else: ?>
                <span class="pay-badge pay-cod">COD</span>
              <?php endif; ?>
            </td>
            <td style="font-weight:800;color:var(--accent)">৳<?= number_format((float)$order['total'],0) ?></td>
            <td>
              <form method="POST">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <select name="status" class="status-sel" onchange="this.form.submit()">
                  <option value="pending"   <?= $order['status']==='pending'  ?'selected':'' ?>>⏳ পেন্ডিং</option>
                  <option value="confirmed" <?= $order['status']==='confirmed'?'selected':'' ?>>✅ নিশ্চিত</option>
                  <option value="delivered" <?= $order['status']==='delivered'?'selected':'' ?>>📦 ডেলিভারি</option>
                </select>
              </form>
              <div style="margin-top:4px"><span class="badge badge-<?= $order['status'] ?>"><?= match($order['status']){'pending'=>'পেন্ডিং','confirmed'=>'নিশ্চিত','delivered'=>'ডেলিভারি',default=>$order['status']} ?></span></div>
            </td>
            <td style="white-space:nowrap;font-size:.76rem;color:var(--muted)">
              <?= date('d M Y', strtotime($order['created_at'])) ?><br>
              <?= date('h:i A', strtotime($order['created_at'])) ?>
            </td>
            <td>
              <div class="act-btns">
                <button class="btn btn-sm" style="background:#fff3e0;color:#b45309;border:1px solid #fde68a" onclick='printInvoice(<?= htmlspecialchars(json_encode([
                  'id'=>$order['id'],'name'=>$order['name'],'phone'=>$order['phone'],
                  'email'=>$order['email']??'',
                  'address'=>$order['address'],'area'=>$order['area'],
                  'payment_method'=>$order['payment_method']??'bkash',
                  'transaction_id'=>$order['transaction_id']??'',
                  'total'=>$order['total'],'status'=>$order['status'],
                  'created_at'=>$order['created_at'],'books'=>$books,
                ], JSON_UNESCAPED_UNICODE)) ?>)'>
                  <i class="fas fa-print"></i> ইনভয়েস
                </button>
                <a href="https://wa.me/<?= $customer_wa ?>?text=<?= $wa_text ?>" target="_blank" class="btn btn-sm btn-wa">
                  <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
                <a href="?delete=<?= $order['id'] ?>" onclick="return confirm('অর্ডার #<?= $order['id'] ?> মুছবেন?')" class="btn btn-sm btn-del">
                  <i class="fas fa-trash"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ══ MOBILE CARDS ══ -->
  <div class="order-cards mobile-only">
    <?php if (empty($orders)): ?>
      <div style="text-align:center;padding:40px;color:var(--muted)">কোনো অর্ডার নেই</div>
    <?php endif; ?>
    <?php foreach ($orders as $order):
      $books       = parseBooks($order);
      $books_label = $books ? implode(', ', array_column($books,'name')) : 'N/A';
      $wa_text     = buildWaText($order, $books_label);
      $customer_wa = '880' . ltrim($order['phone'],'0');
    ?>
    <div class="order-card">
      <div class="oc-head">
        <div>
          <span class="oc-id">#<?= $order['id'] ?></span>
          <span class="badge badge-<?= $order['status'] ?>" style="margin-left:8px"><?= match($order['status']){'pending'=>'পেন্ডিং','confirmed'=>'নিশ্চিত','delivered'=>'ডেলিভারি',default=>$order['status']} ?></span>
        </div>
        <span class="oc-date"><?= date('d M, h:i A', strtotime($order['created_at'])) ?></span>
      </div>
      <div class="oc-body">
        <div class="oc-row">
          <span class="oc-lbl">নাম</span>
          <span class="oc-val" style="font-weight:700"><?= htmlspecialchars($order['name']) ?></span>
        </div>
        <div class="oc-row">
          <span class="oc-lbl">মোবাইল</span>
          <span class="oc-val"><?= htmlspecialchars($order['phone']) ?></span>
        </div>
        <div class="oc-row">
          <span class="oc-lbl">ঠিকানা</span>
          <span class="oc-val" style="font-size:.78rem;color:var(--muted)"><?= htmlspecialchars($order['address']) ?></span>
        </div>
        <?php if ($books): ?>
        <div class="oc-row">
          <span class="oc-lbl">পণ্য</span>
          <div class="oc-books">
            <?php foreach ($books as $b):
              $imgFile = $b['image'] ?? '';
              $imgUrl  = $imgFile ? '../assets/images/books/'.htmlspecialchars($imgFile) : '';
            ?>
              <div style="display:flex;align-items:center;gap:8px;padding:2px 0">
                <?php if ($imgUrl): ?>
                  <img src="<?= $imgUrl ?>" style="width:32px;height:42px;object-fit:cover;border-radius:5px;flex-shrink:0" onerror="this.style.display='none'">
                <?php else: ?>
                  <div style="width:32px;height:42px;background:var(--accent-light);border-radius:5px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-box" style="color:var(--accent);font-size:.65rem"></i></div>
                <?php endif; ?>
                <span><?= htmlspecialchars($b['name']) ?>
                  <?php if (!empty($b['variant'])): ?>
                    <span style="font-size:.6rem;font-weight:700;background:var(--accent);color:#fff;padding:1px 6px;border-radius:20px;margin-left:3px;vertical-align:middle"><?= htmlspecialchars($b['variant']) ?></span>
                  <?php endif; ?>
                  <strong style="color:var(--accent)">৳<?= number_format((float)$b['price'],0) ?></strong>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <div class="oc-row">
          <span class="oc-lbl">পেমেন্ট</span>
          <span class="oc-val">
            <?php if ($order['payment_method']==='bkash'): ?>
              <span class="pay-badge pay-bkash">bKash</span>
              <?php if ($order['transaction_id']): ?><br><small style="color:var(--muted)"><?= htmlspecialchars($order['transaction_id']) ?></small><?php endif; ?>
            <?php else: ?>
              <span class="pay-badge pay-cod">Cash on Delivery</span>
            <?php endif; ?>
          </span>
        </div>
      </div>
      <div class="oc-footer">
        <div class="oc-status-wrap">
          <form method="POST">
            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
            <select name="status" class="status-sel" onchange="this.form.submit()" style="width:100%">
              <option value="pending"   <?= $order['status']==='pending'  ?'selected':'' ?>>⏳ পেন্ডিং</option>
              <option value="confirmed" <?= $order['status']==='confirmed'?'selected':'' ?>>✅ নিশ্চিত</option>
              <option value="delivered" <?= $order['status']==='delivered'?'selected':'' ?>>📦 ডেলিভারি</option>
            </select>
          </form>
        </div>
        <span class="oc-total">৳<?= number_format((float)$order['total'],0) ?></span>
      </div>
      <div style="padding:0 14px 12px;display:flex;gap:6px">
        <button class="btn btn-info btn-sm" style="flex:1;justify-content:center" onclick='openDetail(<?= htmlspecialchars(json_encode([
          'id'=>$order['id'],'name'=>$order['name'],'phone'=>$order['phone'],
          'email'=>$order['email']??'',
          'address'=>$order['address'],'area'=>$order['area'],
          'payment_method'=>$order['payment_method']??'bkash',
          'transaction_id'=>$order['transaction_id']??'',
          'total'=>$order['total'],'status'=>$order['status'],
          'created_at'=>$order['created_at'],'books'=>$books,
        ], JSON_UNESCAPED_UNICODE)) ?>)'>
          <i class="fas fa-eye"></i> বিস্তারিত
        </button>
        <a href="https://wa.me/<?= $customer_wa ?>?text=<?= $wa_text ?>" target="_blank" class="btn btn-sm btn-wa" style="flex:1;justify-content:center">
          <i class="fab fa-whatsapp"></i> WhatsApp
        </a>
        <a href="?delete=<?= $order['id'] ?>" onclick="return confirm('অর্ডার #<?= $order['id'] ?> মুছবেন?')" class="btn btn-sm btn-del">
          <i class="fas fa-trash"></i>
        </a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

</div>

<!-- ══ ORDER DETAIL MODAL ══ -->
<div class="modal-overlay" id="orderModal" onclick="if(event.target===this)closeDetail()">
  <div class="modal-box">
    <div class="modal-head">
      <h3 id="modalTitle">অর্ডার বিস্তারিত</h3>
      <button class="modal-close-btn" onclick="closeDetail()">✕</button>
    </div>
    <div class="modal-body" id="modalBody"></div>
    <div class="modal-actions" id="modalActions"></div>
  </div>
</div>

<script>
const SHOP_NAME = <?= json_encode($shop_name) ?>;
let currentOrder = null;

function openDetail(order) {
  currentOrder = order;
  const area  = order.area === 'dhaka' ? 'ঢাকা' : 'ঢাকার বাইরে';
  const pay   = order.payment_method === 'bkash' ? 'bKash' : 'Cash on Delivery';
  const stat  = {'pending':'⏳ পেন্ডিং','confirmed':'✅ নিশ্চিত','delivered':'📦 ডেলিভারি'}[order.status] || order.status;

  let booksHtml = '', bookTotal = 0;
  if (order.books && order.books.length) {
    booksHtml = '<div class="modal-books">';
    order.books.forEach(b => {
      const imgSrc = b.image ? '../assets/images/books/' + b.image : '';
      const imgHtml = imgSrc
        ? `<img src="${imgSrc}" style="width:36px;height:48px;object-fit:cover;border-radius:5px;flex-shrink:0" onerror="this.style.display='none'">`
        : `<div style="width:36px;height:48px;background:var(--accent-light);border-radius:5px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-box" style="color:var(--accent);font-size:.7rem"></i></div>`;
      const variantBadge = b.variant ? `<span style="font-size:.6rem;font-weight:700;background:var(--accent);color:#fff;padding:1px 6px;border-radius:20px;margin-left:4px;vertical-align:middle">${b.variant}</span>` : '';
      booksHtml += `<div class="modal-book-item" style="align-items:center;gap:10px;justify-content:flex-start">
        ${imgHtml}
        <span style="flex:1">${b.name}${variantBadge}</span>
        <span style="font-weight:700;color:var(--accent);flex-shrink:0">৳${Number(b.price).toLocaleString('bn-BD')}</span>
      </div>`;
      bookTotal += parseFloat(b.price);
    });
    booksHtml += '</div>';
  }
  const delivery = parseFloat(order.total) - bookTotal;
  const totalsHtml = `<div class="modal-totals">
    <div class="modal-total-row"><span>পণ্যের মূল্য</span><span>৳${bookTotal.toLocaleString('bn-BD')}</span></div>
    <div class="modal-total-row"><span>ডেলিভারি (${area})</span><span>৳${delivery.toLocaleString('bn-BD')}</span></div>
    <div class="modal-total-row grand"><span>মোট</span><span>৳${parseFloat(order.total).toLocaleString('bn-BD')}</span></div>
  </div>`;

  document.getElementById('modalTitle').textContent = `অর্ডার #${order.id}`;
  document.getElementById('modalBody').innerHTML = `
    <div class="detail-row"><span class="detail-label">নাম</span><span class="detail-val">${order.name}</span></div>
    <div class="detail-row"><span class="detail-label">মোবাইল</span><span class="detail-val">${order.phone}</span></div>
    ${order.email ? `<div class="detail-row"><span class="detail-label">ইমেইল</span><span class="detail-val">${order.email}</span></div>` : ''}
    <div class="detail-row"><span class="detail-label">ঠিকানা</span><span class="detail-val">${order.address}</span></div>
    <div class="detail-row"><span class="detail-label">এলাকা</span><span class="detail-val">${area}</span></div>
    <div class="detail-row"><span class="detail-label">পেমেন্ট</span><span class="detail-val">${pay}${order.transaction_id?'<br><small style="color:var(--muted)">TXN: '+order.transaction_id+'</small>':''}</span></div>
    <div class="detail-row"><span class="detail-label">স্ট্যাটাস</span><span class="detail-val">${stat}</span></div>
    <div style="margin-top:12px;font-size:.74rem;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">পণ্য</div>
    ${booksHtml}${totalsHtml}`;

  const waNum = '880' + order.phone.replace(/^0/,'');
  const waMsg = encodeURIComponent(`অর্ডার #${order.id} নিশ্চিত হয়েছে 🎉\nপণ্য: ${order.books.map(b=>b.name).join(', ')}\nমোট: ৳${order.total}\nপেমেন্ট: ${pay}\nধন্যবাদ!`);
  document.getElementById('modalActions').innerHTML = `
    <button onclick="printInvoice(currentOrder)" class="btn" style="background:#fff3e0;color:#b45309;border:1px solid #fde68a">
      <i class="fas fa-print"></i> ইনভয়েস
    </button>
    <a href="https://wa.me/${waNum}?text=${waMsg}" target="_blank" class="btn btn-wa">
      <i class="fab fa-whatsapp"></i> WhatsApp পাঠান
    </a>
    <a href="?delete=${order.id}" onclick="return confirm('অর্ডার #${order.id} মুছবেন?')" class="btn btn-del">
      <i class="fas fa-trash"></i> মুছুন
    </a>`;

  document.getElementById('orderModal').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeDetail() {
  document.getElementById('orderModal').classList.remove('show');
  document.body.style.overflow = '';
}

function printInvoice(order) {
  const area     = order.area === 'dhaka' ? 'ঢাকা (ভেতরে)' : 'ঢাকার বাইরে';
  const pay      = order.payment_method === 'bkash' ? 'bKash' : 'Cash on Delivery';
  const statMap  = {pending:'পেন্ডিং', confirmed:'নিশ্চিত', delivered:'ডেলিভারি হয়েছে'};
  const stat     = statMap[order.status] || order.status;
  const dateStr  = new Date(order.created_at).toLocaleString('bn-BD', {dateStyle:'long', timeStyle:'short'});

  let bookRows = '', bookTotal = 0;
  if (order.books && order.books.length) {
    order.books.forEach((b, i) => {
      const p = parseFloat(b.price);
      bookTotal += p;
      const imgTag = b.image
        ? `<img src="../assets/images/books/${b.image}" style="width:24px;height:32px;object-fit:cover;border-radius:3px;vertical-align:middle;margin-right:6px" onerror="this.style.display='none'">`
        : '';
      const variantStr = b.variant ? ` <span style="font-size:.65rem;font-weight:700;background:#B5183D;color:#fff;padding:1px 6px;border-radius:20px;margin-left:3px">${b.variant}</span>` : '';
      bookRows += `<tr>
        <td style="padding:8px 10px;border-bottom:1px solid #eee">${imgTag}${i+1}. ${b.name}${variantStr}</td>
        <td style="padding:8px 10px;border-bottom:1px solid #eee;text-align:right;font-weight:700">৳${p.toLocaleString('bn-BD')}</td>
      </tr>`;
    });
  }
  const delivery  = parseFloat(order.total) - bookTotal;
  const emailRow  = order.email ? `<tr><td style="padding:5px 0;color:#666;font-size:13px">ইমেইল</td><td style="padding:5px 0;font-weight:600">${order.email}</td></tr>` : '';

  const html = `<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Invoice #${order.id} — ${SHOP_NAME}</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Inter', sans-serif; background: #fff; color: #1a1a1a; padding: 32px; max-width: 600px; margin: 0 auto; font-size: 14px; }
  .inv-header { border-bottom: 3px solid #B5183D; padding-bottom: 18px; margin-bottom: 22px; display: flex; justify-content: space-between; align-items: flex-start; }
  .shop-name  { font-size: 22px; font-weight: 800; color: #B5183D; }
  .inv-label  { font-size: 11px; font-weight: 700; color: #B5183D; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 4px; }
  .inv-id     { font-size: 20px; font-weight: 800; color: #1a1a1a; }
  .inv-date   { font-size: 12px; color: #666; margin-top: 3px; }
  .section    { margin-bottom: 20px; }
  .section-title { font-size: 11px; font-weight: 700; color: #B5183D; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px; }
  .info-table { width: 100%; border-collapse: collapse; }
  .info-table td { vertical-align: top; }
  .info-table td:first-child { color: #666; font-size: 13px; min-width: 90px; }
  .info-table td:last-child { font-weight: 600; }
  .books-table { width: 100%; border-collapse: collapse; background: #FFF5F7; border-radius: 8px; overflow: hidden; }
  .books-table th { background: #B5183D; color: #fff; padding: 9px 10px; text-align: left; font-size: 12px; font-weight: 700; }
  .books-table th:last-child { text-align: right; }
  .totals-table { width: 100%; border-collapse: collapse; }
  .totals-table td { padding: 5px 0; font-size: 13px; }
  .totals-table td:last-child { text-align: right; font-weight: 600; }
  .grand-total td { font-size: 16px; font-weight: 800; color: #B5183D; border-top: 2px solid #B5183D; padding-top: 10px; }
  .status-badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;
    background: ${ order.status==='delivered'?'#d1fae5':order.status==='confirmed'?'#dbeafe':'#fef3c7' };
    color: ${ order.status==='delivered'?'#065f46':order.status==='confirmed'?'#1e40af':'#92400e' }; }
  .footer { text-align: center; margin-top: 30px; padding-top: 16px; border-top: 1px solid #eee; color: #999; font-size: 12px; }
  .print-btn { display: block; margin: 24px auto 0; padding: 10px 32px; background: #B5183D; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; }
  @media print { .print-btn { display: none !important; } body { padding: 16px; } }
</style>
</head>
<body>
  <div class="inv-header">
    <div>
      <div class="shop-name">${SHOP_NAME}</div>
      <div style="font-size:12px;color:#666;margin-top:4px">অর্ডার ইনভয়েস</div>
    </div>
    <div style="text-align:right">
      <div class="inv-label">Invoice</div>
      <div class="inv-id">#${order.id}</div>
      <div class="inv-date">${dateStr}</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <div class="section">
      <div class="section-title">কাস্টমার তথ্য</div>
      <table class="info-table">
        <tr><td>নাম</td><td>${order.name}</td></tr>
        <tr><td style="padding:5px 0;color:#666;font-size:13px">মোবাইল</td><td style="padding:5px 0;font-weight:600">${order.phone}</td></tr>
        ${emailRow}
        <tr><td style="padding:5px 0;color:#666;font-size:13px">ঠিকানা</td><td style="padding:5px 0;font-weight:600">${order.address}</td></tr>
        <tr><td style="padding:5px 0;color:#666;font-size:13px">এলাকা</td><td style="padding:5px 0;font-weight:600">${area}</td></tr>
      </table>
    </div>
    <div class="section">
      <div class="section-title">পেমেন্ট তথ্য</div>
      <table class="info-table">
        <tr><td>পদ্ধতি</td><td>${pay}</td></tr>
        ${order.transaction_id ? `<tr><td style="padding:5px 0;color:#666;font-size:13px">TXN ID</td><td style="padding:5px 0;font-weight:600">${order.transaction_id}</td></tr>` : ''}
        <tr><td style="padding:5px 0;color:#666;font-size:13px">স্ট্যাটাস</td><td style="padding:5px 0"><span class="status-badge">${stat}</span></td></tr>
      </table>
    </div>
  </div>

  <div class="section">
    <div class="section-title">অর্ডার আইটেম</div>
    <table class="books-table">
      <thead><tr><th>পণ্য</th><th style="text-align:right">মূল্য</th></tr></thead>
      <tbody>${bookRows}</tbody>
    </table>
  </div>

  <div style="display:flex;justify-content:flex-end;margin-top:4px">
    <table class="totals-table" style="width:220px">
      <tr><td style="color:#666">পণ্যের মূল্য</td><td>৳${bookTotal.toLocaleString('bn-BD')}</td></tr>
      <tr><td style="color:#666">ডেলিভারি চার্জ</td><td>৳${delivery.toLocaleString('bn-BD')}</td></tr>
      <tr class="grand-total"><td>মোট</td><td>৳${parseFloat(order.total).toLocaleString('bn-BD')}</td></tr>
    </table>
  </div>

  <div class="footer">
    ধন্যবাদ আপনার অর্ডারের জন্য! — ${SHOP_NAME}
  </div>

  <button class="print-btn" onclick="window.print()">🖨️ প্রিন্ট করুন</button>

<script>
  window.onload = function() { window.print(); };
<\/script>
</body>
</html>`;

  const w = window.open('', '_blank', 'width=700,height=900');
  w.document.write(html);
  w.document.close();
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetail(); });
</script>
</body>
</html>
