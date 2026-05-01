<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

$name           = sanitize($_POST['name'] ?? '');
$phone          = sanitize($_POST['phone'] ?? '');
$email          = sanitize($_POST['email'] ?? '');
$address        = sanitize($_POST['address'] ?? '');
$area           = sanitize($_POST['area'] ?? '');
$payment_method = sanitize($_POST['payment_method'] ?? 'bkash');
$transaction_id = sanitize($_POST['transaction_id'] ?? '');
$books_json_raw = $_POST['books_json'] ?? '[]';

// Validate
$errors = [];
if (empty($name) || strlen($name) < 2)                      $errors[] = 'Please enter a valid name';
$phone_digits = preg_replace('/[^0-9]/', '', $phone);
if (strlen($phone_digits) < 6 || strlen($phone_digits) > 16) $errors[] = 'Please enter a valid phone number';
if (empty($address) || strlen($address) < 10)               $errors[] = 'Please enter a full address';
if (!in_array($area, ['dhaka', 'outside']))                 $errors[] = 'Please select a valid delivery area';
if (!in_array($payment_method, ['cod', 'bkash']))           $errors[] = 'Please select a valid payment method';
if ($payment_method === 'bkash' && strlen($transaction_id) < 5) $errors[] = 'Please enter a valid Transaction ID';

$books_raw_data = json_decode($books_json_raw, true);
if (!is_array($books_raw_data) || count($books_raw_data) === 0) $errors[] = 'Please select at least one item';

if (!empty($errors)) {
    jsonResponse(['success' => false, 'message' => implode(' | ', $errors)], 400);
}

// Support both old [1,2] and new [{id:1,variant:"M"},...] formats
// Same product can appear multiple times with different variants
$item_pairs = [];
foreach ($books_raw_data as $item) {
    if (is_numeric($item)) {
        $bid = (int)$item;
        if ($bid > 0) $item_pairs[] = ['id' => $bid, 'variant' => ''];
    } elseif (is_array($item) && isset($item['id'])) {
        $bid = (int)$item['id'];
        if ($bid > 0) $item_pairs[] = ['id' => $bid, 'variant' => sanitize($item['variant'] ?? '')];
    }
}

try {
    if (empty($item_pairs)) {
        jsonResponse(['success' => false, 'message' => 'অন্তত একটি পণ্য বাছুন'], 400);
    }

    // Load unique books from DB
    $unique_ids   = array_values(array_unique(array_column($item_pairs, 'id')));
    $placeholders = implode(',', array_fill(0, count($unique_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, image FROM books WHERE id IN ($placeholders)");
    $stmt->execute($unique_ids);
    $books_lookup = [];
    foreach ($stmt->fetchAll() as $b) { $books_lookup[$b['id']] = $b; }

    if (empty($books_lookup)) {
        jsonResponse(['success' => false, 'message' => 'নির্বাচিত পণ্য পাওয়া যায়নি'], 400);
    }

    // Build books_data — one entry per (product, variant) pair
    $books_data = [];
    foreach ($item_pairs as $pair) {
        if (!isset($books_lookup[$pair['id']])) continue;
        $b = $books_lookup[$pair['id']];
        $books_data[] = [
            'id'      => $b['id'],
            'name'    => $b['name'],
            'price'   => $b['price'],
            'image'   => $b['image'] ?? '',
            'variant' => $pair['variant'],
        ];
    }

    if (empty($books_data)) {
        jsonResponse(['success' => false, 'message' => 'নির্বাচিত পণ্য পাওয়া যায়নি'], 400);
    }

    // Load settings
    $settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
    $delivery_charge = ($area === 'dhaka') ? floatval($settings['dhaka_charge']) : floatval($settings['outside_charge']);
    $book_total = array_sum(array_column($books_data, 'price'));
    $total = $book_total + $delivery_charge;

    $books_json = json_encode($books_data, JSON_UNESCAPED_UNICODE);

    // Insert order (use book_id = NULL for multi-book orders)
    $stmt = $pdo->prepare("
        INSERT INTO orders (name, phone, email, address, area, book_id, books_json, transaction_id, payment_method, total, status)
        VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$name, $phone, $email, $address, $area, $books_json, $transaction_id ?: null, $payment_method, $total]);
    $order_id = $pdo->lastInsertId();

    // Send email notification
    sendEmailNotification($settings, $order_id, $name, $phone, $address, $area, $books_data, $payment_method, $transaction_id, $delivery_charge, $total);

    jsonResponse(['success' => true, 'order_id' => $order_id, 'message' => 'অর্ডার সফল হয়েছে']);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'অর্ডার সংরক্ষণে সমস্যা হয়েছে: ' . $e->getMessage()], 500);
}

// ─── Email Notification ────────────────────────────────────────────────────
function sendEmailNotification($settings, $order_id, $name, $phone, $address, $area, $books, $payment_method, $txn, $delivery, $total) {
    $admin_email = $settings['admin_email'] ?? '';
    if (empty($admin_email)) return;

    $shop_name   = $settings['shop_name'] ?? 'Urimas Books';
    $area_label  = ($area === 'dhaka') ? 'ঢাকা' : 'ঢাকার বাইরে';
    $pay_label   = ($payment_method === 'bkash') ? 'bKash' : 'Cash on Delivery';

    $book_lines = '';
    foreach ($books as $b) {
        $book_lines .= "  • {$b['name']} — ৳{$b['price']}\n";
    }

    $body = <<<MAIL
নতুন অর্ডার পেয়েছেন!

অর্ডার আইডি : #{$order_id}
নাম          : {$name}
মোবাইল       : {$phone}
ঠিকানা       : {$address}
এলাকা        : {$area_label}

বই:
{$book_lines}
ডেলিভারি     : ৳{$delivery}
মোট          : ৳{$total}

পেমেন্ট      : {$pay_label}
Transaction  : {$txn}

Dashboard: http://{$_SERVER['HTTP_HOST']}/urimas/public_html/admin/dashboard.php
MAIL;

    $subject = "[$shop_name] নতুন অর্ডার #{$order_id} - {$name}";
    $headers = "From: noreply@{$_SERVER['HTTP_HOST']}\r\nContent-Type: text/plain; charset=UTF-8\r\n";

    @mail($admin_email, $subject, $body, $headers);
}
