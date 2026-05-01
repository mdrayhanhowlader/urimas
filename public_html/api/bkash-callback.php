<?php
session_start();
require_once '../config.php';

$payment_id = $_GET['paymentID'] ?? '';
$status     = $_GET['status']    ?? '';

// User cancelled
if ($status === 'cancel' || $status === 'failure') {
    header('Location: ../index.php?bkash=cancelled'); exit;
}

if ($status !== 'success' || !$payment_id) {
    header('Location: ../index.php?bkash=failed'); exit;
}

$id_token = $_SESSION['bkash_id_token'] ?? '';
$app_key  = $_SESSION['bkash_app_key']  ?? '';
$order_id = (int)($_SESSION['bkash_order_id'] ?? 0);

if (!$id_token || !$app_key || !$order_id) {
    header('Location: ../index.php?bkash=session_expired'); exit;
}

$base_url = 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout';

// Execute payment
$ch = curl_init($base_url . '/execute');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['paymentID' => $payment_id]),
    CURLOPT_HTTPHEADER     => ['Authorization: '.$id_token, 'X-APP-Key: '.$app_key, 'Content-Type: application/json'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 30,
]);
$res  = curl_exec($ch);
curl_close($ch);
$data = json_decode($res, true) ?: [];

if (($data['transactionStatus'] ?? '') === 'Completed') {
    $trx_id = $data['trxID'] ?? $payment_id;
    // Mark order as paid
    $pdo->prepare("UPDATE orders SET payment_status='paid', transaction_id=? WHERE id=?")
        ->execute([$trx_id, $order_id]);
    // Clear session tokens
    unset($_SESSION['bkash_id_token'], $_SESSION['bkash_app_key'], $_SESSION['bkash_payment_id'], $_SESSION['bkash_order_id']);
    header('Location: ../index.php?bkash=success&order='.$order_id.'&trx='.urlencode($trx_id)); exit;
}

header('Location: ../index.php?bkash=failed&msg='.urlencode($data['statusMessage']??'Payment failed'));
