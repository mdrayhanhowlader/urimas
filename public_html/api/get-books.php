<?php
require_once '../config.php';

try {
    $stmt = $pdo->query("SELECT * FROM books ORDER BY id");
    $books = $stmt->fetchAll();

    foreach ($books as &$b) {
        $b['variants'] = !empty($b['variants'])
            ? (json_decode($b['variants'], true) ?: [])
            : [];
        $b['color_variants'] = !empty($b['color_variants'])
            ? (json_decode($b['color_variants'], true) ?: [])
            : [];
    }
    unset($b);

    jsonResponse(['success' => true, 'books' => $books]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Failed to load books'], 500);
}
?>