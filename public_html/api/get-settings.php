<?php
require_once '../config.php';

$QR_DIR     = __DIR__ . '/../assets/images/qr/';
$QR_URL     = 'assets/images/qr/';
$BANNER_DIR = __DIR__ . '/../assets/images/banner/';
$BANNER_URL = 'assets/images/banner/';

try {
    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt->fetch();

    if (!$settings) {
        $pdo->exec("INSERT INTO settings (shop_name, bkash_number, admin_email, whatsapp_number, dhaka_charge, outside_charge)
                    VALUES ('Urimas Books', '01712345678', '', '', 80.00, 140.00)");
        $settings = [
            'shop_name'      => 'Urimas Books',
            'bkash_number'   => '01712345678',
            'dhaka_charge'   => 80.00,
            'outside_charge' => 140.00,
            'bkash_mode'     => 'manual',
            'bkash_qr_image' => '',
            'banner_enabled' => 0,
            'banner_title'   => '',
            'banner_subtitle'=> '',
            'banner_image'   => '',
        ];
    }

    $bkash_qr_image = $settings['bkash_qr_image'] ?? '';
    $bkash_qr_url   = ($bkash_qr_image && file_exists($QR_DIR.$bkash_qr_image))
                      ? $QR_URL.$bkash_qr_image : '';

    $banner_image   = $settings['banner_image'] ?? '';
    $banner_img_url = ($banner_image && file_exists($BANNER_DIR.$banner_image))
                      ? $BANNER_URL.$banner_image : '';

    $public = [
        'bkash_number'    => $settings['bkash_number']   ?? '',
        'dhaka_charge'    => $settings['dhaka_charge']   ?? 80,
        'outside_charge'  => $settings['outside_charge'] ?? 140,
        'shop_name'       => $settings['shop_name']      ?? 'Urimas Books',
        'bkash_mode'      => $settings['bkash_mode']     ?? 'manual',
        'bkash_qr_url'    => $bkash_qr_url,
        'banner_enabled'  => (int)($settings['banner_enabled'] ?? 0),
        'banner_title'    => $settings['banner_title']    ?? '',
        'banner_subtitle' => $settings['banner_subtitle'] ?? '',
        'banner_img_url'  => $banner_img_url,
    ];

    jsonResponse(['success' => true, 'settings' => $public]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Failed to load settings'], 500);
}
