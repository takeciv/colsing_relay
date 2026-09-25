<?php
/**
 * API: 走者ステータス更新
 * POST /api/runner_status.php
 * Body: runner_id, status (before|running|finished), profile_url, stream_url
 * - イベント起案者のみ実行可能。
 * - status が running に変わった場合、フォロー者へプッシュ通知を送る。
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/push.php';

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

$runner_id   = (int)($_POST['runner_id']   ?? 0);
$new_status  = $_POST['status']            ?? '';
$profile_url = trim($_POST['profile_url']  ?? '');
$stream_url  = trim($_POST['stream_url']   ?? '');

if ($runner_id <= 0 || !in_array($new_status, ['before', 'running', 'finished'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid parameters']);
    exit;
}

$db = get_db();

// 走者とイベント起案者を確認
$stmt = $db->prepare(
    'SELECT er.id, er.event_id, er.name AS runner_name, er.status AS old_status,
            e.user_id AS organizer_id, e.name AS event_name
       FROM event_runners er
       JOIN events e ON e.event_id = er.event_id
      WHERE er.id = ?'
);
$stmt->execute([$runner_id]);
$runner = $stmt->fetch();

if (!$runner) {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}

if ($runner['organizer_id'] !== $uid) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

// ステータス更新
$upd = $db->prepare(
    'UPDATE event_runners
        SET status = ?, profile_url = ?, stream_url = ?
      WHERE id = ?'
);
$upd->execute([$new_status, $profile_url ?: null, $stream_url ?: null, $runner_id]);

// running に変わった場合はプッシュ通知を送信
if ($new_status === 'running' && $runner['old_status'] !== 'running') {
    // フォロー者かつ users テーブルに登録済みのユーザーIDを取得
    $follow_stmt = $db->prepare(
        'SELECT ef.user_id
           FROM event_follows ef
           JOIN users u ON u.user_id = ef.user_id
          WHERE ef.event_id = ?
            AND u.nickname != \'\''
    );
    $follow_stmt->execute([$runner['event_id']]);
    $follower_ids = array_column($follow_stmt->fetchAll(), 'user_id');

    if (!empty($follower_ids)) {
        notify_users($follower_ids, [
            'title' => $runner['event_name'],
            'body'  => $runner['runner_name'] . ' が配信を開始しました！',
            'url'   => '/event?id=' . $runner['event_id'],
        ]);
    }
}

echo json_encode(['result' => 'ok']);
