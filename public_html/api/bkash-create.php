<?php
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(['success'=>false,'message'=>'Invalid request'], 405); }

$settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
if (($settings['bkash_mode'] ?? 'manual') !== 'api') {
    jsonResponse(['success'=>false,'message'=>'API mode না থাকলে এই endpoint ব্যবহার করা যাবে না']);
}

$app_key    = $settings['bkash_app_key']    ?? '';
$app_secret = $settings['bkash_app_secret'] ?? '';
$username   = $settings['bkash_username']   ?? '';
$password   = $settings['bkash_password']   ?? '';

if (!$app_key || !$app_secret || !$username || !$password) {
    jsonResponse(['success'=>false,'message'=>'bKash API credentials সেটিংসে দেওয়া হয়নি']);
}

$amount   = (float)($_POST['amount']   ?? 0);
$order_id = (int)  ($_POST['order_id'] ?? 0);
if ($amount <= 0 || !$order_id) { jsonResponse(['success'=>false,'message'=>'Invalid amount or order_id']); }

$base_url    = 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout';
$callback_url = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on' ? 'https' : 'http')
              . '://' . $_SERVER['HTTP_HOST']
              . dirname($_SERVER['PHP_SELF']) . '/bkash-callback.php';

// ── Step 1: Grant token ─────────────────────────────────────────────────────
$token_res = bkash_post($base_url . '/token/grant', [
    'app_key'    => $app_key,
    'app_secret' => $app_secret,
], ['username: '.$username, 'password: '.$password, 'Content-Type: application/json']);

if (empty($token_res['id_token'])) {
    jsonResponse(['success'=>false,'message'=>'bKash token নিতে ব্যর্থ: '.($token_res['statusMessage']??'unknown')]);
}

$id_token = $token_res['id_token'];

// ── Step 2: Create payment ──────────────────────────────────────────────────
$invoice = 'ORD-'.$order_id.'-'.time();
$pay_res = bkash_post($base_url . '/create', [
    'mode'                  => '0011',
    'payerReference'        => $order_id,
    'callbackURL'           => $callback_url,
    'amount'                => number_format($amount, 2, '.', ''),
    'currency'              => 'BDT',
    'intent'                => 'sale',
    'merchantInvoiceNumber' => $invoice,
], ['Authorization: '.$id_token, 'X-APP-Key: '.$app_key, 'Content-Type: application/json']);

if (empty($pay_res['bkashURL'])) {
    jsonResponse(['success'=>false,'message'=>'bKash payment তৈরি ব্যর্থ: '.($pay_res['statusMessage']??'unknown')]);
}

// Store id_token & paymentID temporarily in session for execute step
session_start();
$_SESSION['bkash_id_token']  = $id_token;
$_SESSION['bkash_app_key']   = $app_key;
$_SESSION['bkash_payment_id']= $pay_res['paymentID'];
$_SESSION['bkash_order_id']  = $order_id;

jsonResponse(['success'=>true, 'bkashURL'=>$pay_res['bkashURL'], 'paymentID'=>$pay_res['paymentID']]);

// ── Helper ──────────────────────────────────────────────────────────────────
function bkash_post(string $url, array $payload, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?: [];
}
