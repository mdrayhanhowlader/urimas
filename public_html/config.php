<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'urimas_books');
define('DB_USER', 'root');
define('DB_PASS', '');

// PDO connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper functions
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
?>