<?php
/**
 * API: Web Pushサブスクリプション登録
 * POST /api/push_subscribe.php
 * JSON Body: { endpoint, p256dh, auth }
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=UTF-8');
start_session();

$uid = get_logged_in_user_id();
if ($uid === null) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$endpoint = trim($body['endpoint'] ?? '');
$p256dh   = trim($body['p256dh']   ?? '');
$auth     = trim($body['auth']     ?? '');

if ($endpoint === '' || $p256dh === '' || $auth === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing fields']);
    exit;
}

$db = get_db();

// 同一ユーザーの同一エンドポイントがあれば更新、なければINSERT
$stmt = $db->prepare(
    "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, status)
     VALUES (?, ?, ?, ?, 'OK')
     ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), status = 'OK'"
);
// endpointにUNIQUE制約がないため、まず既存レコードを確認する
$sel = $db->prepare(
    'SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?'
);
$sel->execute([$uid, $endpoint]);
$existing = $sel->fetch();

if ($existing) {
    $upd = $db->prepare(
        "UPDATE push_subscriptions SET p256dh = ?, auth = ?, status = 'OK' WHERE id = ?"
    );
    $upd->execute([$p256dh, $auth, $existing['id']]);
} else {
    $ins = $db->prepare(
        "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)"
    );
    $ins->execute([$uid, $endpoint, $p256dh, $auth]);
}

echo json_encode(['result' => 'ok']);
