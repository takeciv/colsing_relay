<?php
/**
 * API: フォロー / フォロー解除
 * POST /api/follow.php
 * Body: action=follow|unfollow, event_id=xxxx
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

verify_csrf_token();

$action   = $_POST['action']   ?? '';
$event_id = $_POST['event_id'] ?? '';

if (!in_array($action, ['follow', 'unfollow'], true) || $event_id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'invalid parameters']);
    exit;
}

$db = get_db();

if ($action === 'follow') {
    try {
        $stmt = $db->prepare(
            'INSERT IGNORE INTO event_follows (user_id, event_id) VALUES (?, ?)'
        );
        $stmt->execute([$uid, $event_id]);
        echo json_encode(['result' => 'followed']);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'db error']);
    }
} else {
    $stmt = $db->prepare(
        'DELETE FROM event_follows WHERE user_id = ? AND event_id = ?'
    );
    $stmt->execute([$uid, $event_id]);
    echo json_encode(['result' => 'unfollowed']);
}
