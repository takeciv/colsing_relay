<?php
/**
 * API: イベント情報取得
 * GET /api/event.php?id=xxxx
 * イベント詳細とイベント走者一覧をJSON形式で返す。
 * イベント詳細画面の1秒ポーリングで使用する。
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

header('Content-Type: application/json; charset=UTF-8');
start_session();

$event_id = $_GET['id'] ?? '';
if ($event_id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'id is required']);
    exit;
}

$db = get_db();

$event_stmt = $db->prepare(
    'SELECT e.event_id, e.name, e.summary, e.banner_image_path,
            e.start_at, e.end_at, e.status,
            e.bgcolor, e.fontcolor, e.headercolor, e.bordercolor,
            e.user_id,
            u.nickname AS organizer
       FROM events e
       JOIN users u ON u.user_id = e.user_id
      WHERE e.event_id = ?'
);
$event_stmt->execute([$event_id]);
$event = $event_stmt->fetch();

if (!$event) {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}

$runner_stmt = $db->prepare(
    'SELECT id, name, summary, image_path, profile_url, stream_url,
            start_at, end_at, status
       FROM event_runners
      WHERE event_id = ?
      ORDER BY start_at ASC, sort_order ASC'
);
$runner_stmt->execute([$event_id]);
$runners = $runner_stmt->fetchAll();

$status_labels = ['before' => '開始前', 'running' => '実施中', 'finished' => '終了'];
foreach ($runners as &$r) {
    $r['status_label'] = $status_labels[$r['status']] ?? $r['status'];
    $r['start_at_fmt'] = $r['start_at'] ? date('Y/m/d H:i', strtotime($r['start_at'])) : '';
    $r['end_at_fmt']   = $r['end_at']   ? date('Y/m/d H:i', strtotime($r['end_at']))   : '';
}
unset($r);

$event['start_at_fmt'] = $event['start_at'] ? date('Y/m/d H:i', strtotime($event['start_at'])) : '';
$event['end_at_fmt']   = $event['end_at']   ? date('Y/m/d H:i', strtotime($event['end_at']))   : '';
$event['runners']      = $runners;

echo json_encode($event, JSON_UNESCAPED_UNICODE);
