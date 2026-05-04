<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u158394990_prussian_shop');
define('DB_USER', 'u158394990_hasnatarefin');
define('DB_PASS', '01955332867H@snat');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

(function(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS books (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        name           VARCHAR(255)  NOT NULL,
        author         VARCHAR(255)  NOT NULL DEFAULT '',
        description    TEXT,
        price          DECIMAL(10,2) NOT NULL,
        image          VARCHAR(255)           DEFAULT '',
        sample_pdf     VARCHAR(255)  NOT NULL DEFAULT '',
        variants       TEXT          NOT NULL DEFAULT '',
        color_variants TEXT          NOT NULL DEFAULT ''
    ) DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        name           VARCHAR(255)  NOT NULL,
        phone          VARCHAR(20)   NOT NULL,
        address        TEXT          NOT NULL,
        area           VARCHAR(100)  NOT NULL,
        email          VARCHAR(255)  NOT NULL DEFAULT '',
        books_data     TEXT          NOT NULL DEFAULT '',
        transaction_id VARCHAR(50)   NOT NULL DEFAULT '',
        total          DECIMAL(10,2) NOT NULL,
        status         ENUM('pending','confirmed','delivered','cancelled') DEFAULT 'pending',
        created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        shop_name        VARCHAR(255)  NOT NULL DEFAULT 'My Shop',
        bkash_number     VARCHAR(20)   NOT NULL DEFAULT '',
        admin_email      VARCHAR(255)  NOT NULL DEFAULT '',
        whatsapp_number  VARCHAR(20)   NOT NULL DEFAULT '',
        dhaka_charge     DECIMAL(10,2) NOT NULL DEFAULT 80.00,
        outside_charge   DECIMAL(10,2) NOT NULL DEFAULT 140.00,
        bkash_mode       VARCHAR(10)   NOT NULL DEFAULT 'manual',
        bkash_qr_image   VARCHAR(255)  NOT NULL DEFAULT '',
        bkash_app_key    VARCHAR(255)  NOT NULL DEFAULT '',
        bkash_app_secret VARCHAR(255)  NOT NULL DEFAULT '',
        bkash_username   VARCHAR(255)  NOT NULL DEFAULT '',
        bkash_password   VARCHAR(255)  NOT NULL DEFAULT '',
        banner_enabled   TINYINT(1)    NOT NULL DEFAULT 0,
        banner_title     VARCHAR(255)  NOT NULL DEFAULT '',
        banner_subtitle  VARCHAR(500)  NOT NULL DEFAULT '',
        banner_image     VARCHAR(255)  NOT NULL DEFAULT '',
        banner_grad_from VARCHAR(20)   NOT NULL DEFAULT '#0e0306',
        banner_grad_to   VARCHAR(20)   NOT NULL DEFAULT '#d4254e',
        theme_accent     VARCHAR(20)   NOT NULL DEFAULT '#B5183D',
        bg_color         VARCHAR(20)   NOT NULL DEFAULT '',
        pixel_id         VARCHAR(50)   NOT NULL DEFAULT '',
        country_code     VARCHAR(15)   NOT NULL DEFAULT '+880',
        bkash_note       VARCHAR(255)  NOT NULL DEFAULT '',
        logo_image       VARCHAR(255)  NOT NULL DEFAULT ''
    ) DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
        id       INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50)  UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL
    ) DEFAULT CHARSET=utf8mb4");

    $pdo->exec("INSERT IGNORE INTO settings (id, shop_name, dhaka_charge, outside_charge)
                VALUES (1, 'My Shop', 80.00, 140.00)");

    $adminHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    $pdo->prepare("INSERT IGNORE INTO admin (username, password) VALUES (?, ?)")
        ->execute(['admin', $adminHash]);

    foreach ([
        "ALTER TABLE books    ADD COLUMN author         VARCHAR(255)  NOT NULL DEFAULT ''",
        "ALTER TABLE books    ADD COLUMN sample_pdf     VARCHAR(255)  NOT NULL DEFAULT ''",
        "ALTER TABLE books    ADD COLUMN variants       TEXT          NOT NULL DEFAULT ''",
        "ALTER TABLE books    ADD COLUMN color_variants TEXT          NOT NULL DEFAULT ''",
        "ALTER TABLE orders   ADD COLUMN email          VARCHAR(255)  NOT NULL DEFAULT ''",
        "ALTER TABLE orders   ADD COLUMN books_data     TEXT          NOT NULL DEFAULT ''",
        "ALTER TABLE orders   ADD COLUMN transaction_id VARCHAR(50)   NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN bkash_number   VARCHAR(20)   NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN admin_email    VARCHAR(255)  NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN whatsapp_number VARCHAR(20)  NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN bkash_mode     VARCHAR(10)   NOT NULL DEFAULT 'manual'",
        "ALTER TABLE settings ADD COLUMN bkash_qr_image VARCHAR(255)  NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN bkash_app_key  VARCHAR(255)  NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN bkash_app_secret VARCHAR(255) NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN bkash_username VARCHAR(255)  NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN bkash_password VARCHAR(255)  NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN bkash_note     VARCHAR(255)  NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN banner_enabled  TINYINT(1)   NOT NULL DEFAULT 0",
        "ALTER TABLE settings ADD COLUMN banner_title    VARCHAR(255)  NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN banner_subtitle VARCHAR(500)  NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN banner_image    VARCHAR(255)  NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN banner_grad_from VARCHAR(20)  NOT NULL DEFAULT '#0e0306'",
        "ALTER TABLE settings ADD COLUMN banner_grad_to   VARCHAR(20)  NOT NULL DEFAULT '#d4254e'",
        "ALTER TABLE settings ADD COLUMN theme_accent    VARCHAR(20)   NOT NULL DEFAULT '#B5183D'",
        "ALTER TABLE settings ADD COLUMN bg_color        VARCHAR(20)   NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN pixel_id        VARCHAR(50)   NOT NULL DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN country_code    VARCHAR(15)   NOT NULL DEFAULT '+880'",
        "ALTER TABLE settings ADD COLUMN logo_image      VARCHAR(255)  NOT NULL DEFAULT ''",
        "ALTER TABLE orders   MODIFY COLUMN status ENUM('pending','confirmed','delivered','cancelled') NOT NULL DEFAULT 'pending'",
    ] as $sql) {
        try { $pdo->exec($sql); } catch (Exception $e) {}
    }
})($pdo);

// ─── Helper functions ─────────────────────────────────────────────────────────
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ─── Theme color helpers ──────────────────────────────────────────────────────
function _themeRgb(string $hex): array {
    $hex = ltrim($hex, '#');
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) $hex = 'B5183D';
    return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
}
function _toHex(int $r, int $g, int $b): string {
    return sprintf('#%02x%02x%02x', max(0,min(255,$r)), max(0,min(255,$g)), max(0,min(255,$b)));
}
function _themeMix(string $hexA, string $hexB, float $wA): string {
    [$ar,$ag,$ab] = _themeRgb($hexA); [$br,$bg,$bb] = _themeRgb($hexB); $wB = 1-$wA;
    return _toHex((int)round($ar*$wA+$br*$wB), (int)round($ag*$wA+$bg*$wB), (int)round($ab*$wA+$bb*$wB));
}
function buildThemeCss(string $accent, string $bg_color = ''): string {
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) $accent = '#B5183D';
    [$r,$g,$b] = _themeRgb($accent);
    $dark      = _themeMix($accent, '#000000', 0.75);
    $gradStart = _toHex(min(255,$r+50), min(255,$g+30), min(255,$b+30));
    $light     = _themeMix($accent, '#ffffff', 0.12);
    $bg        = preg_match('/^#[0-9A-Fa-f]{6}$/', $bg_color) ? $bg_color : _themeMix($accent, '#ffffff', 0.04);
    $border    = _themeMix($accent, '#ffffff', 0.20);
    $sakura    = _themeMix($accent, '#ffffff', 0.30);
    $text      = _themeMix($accent, '#111111', 0.20);
    $muted     = _themeMix($accent, '#888888', 0.25);
    return "<style>:root{" .
        "--accent:$accent;--accent-dark:$dark;--accent-grad:$gradStart;" .
        "--accent-light:$light;--bg:$bg;--border:$border;--sakura:$sakura;" .
        "--text:$text;--muted:$muted;" .
        "}</style>\n";
}
function loadThemeAccent(): string {
    global $pdo;
    try {
        $row = $pdo->query("SELECT theme_accent FROM settings LIMIT 1")->fetch();
        $c = $row['theme_accent'] ?? '#B5183D';
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $c) ? $c : '#B5183D';
    } catch (Exception $e) { return '#B5183D'; }
}
function loadThemeBgColor(): string {
    global $pdo;
    try {
        $row = $pdo->query("SELECT bg_color FROM settings LIMIT 1")->fetch();
        $c = $row['bg_color'] ?? '';
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $c) ? $c : '';
    } catch (Exception $e) { return ''; }
}
