<?php
/**
 * SC033. イベント詳細更新画面
 * URL: /edit?id=xxxx
 * - イベント情報・走者情報の更新・公開・削除を行う。
 * - イベント起案者のみアクセス可。
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/view.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/upload.php';

start_session();
$uid = require_login();

$event_id = $_GET['id'] ?? '';
if ($event_id === '') {
    header('Location: /index');
    exit;
}

$db = get_db();

$event_stmt = $db->prepare(
    'SELECT * FROM events WHERE event_id = ?'
);
$event_stmt->execute([$event_id]);
$event = $event_stmt->fetch();

// 存在確認・起案者確認
if (!$event || $event['user_id'] !== $uid) {
    header('Location: /index');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $action = $_POST['action'] ?? 'update';

    // ── 削除 ──────────────────────────────────────────
    if ($action === 'delete') {
        $del = $db->prepare('DELETE FROM events WHERE event_id = ? AND user_id = ?');
        $del->execute([$event_id, $uid]);
        flash('success', 'イベントを削除しました。');
        header('Location: /portal');
        exit;
    }

    // ── 公開 ──────────────────────────────────────────
    if ($action === 'publish') {
        $upd = $db->prepare("UPDATE events SET status = 'published' WHERE event_id = ? AND user_id = ?");
        $upd->execute([$event_id, $uid]);
        flash('success', 'イベントを公開しました。');
        header('Location: /portal');
        exit;
    }

    // ── 更新 ──────────────────────────────────────────
    $name        = trim($_POST['name']        ?? '');
    $summary     = trim($_POST['summary']     ?? '');
    $start_at    = trim($_POST['start_at']    ?? '');
    $end_at      = trim($_POST['end_at']      ?? '');
    $bgcolor     = trim($_POST['bgcolor']     ?? '');
    $fontcolor   = trim($_POST['fontcolor']   ?? '');
    $headercolor = trim($_POST['headercolor'] ?? '');
    $bordercolor = trim($_POST['bordercolor'] ?? '');

    if ($name === '') $errors[] = 'イベント名は必須です。';

    $runners_post = $_POST['runners'] ?? [];
    foreach ($runners_post as $idx => $r) {
        if (trim($r['name'] ?? '') === '') {
            $errors[] = '走者 #' . ($idx + 1) . ' の走者名は必須です。';
        }
    }

    // バナー画像
    $banner_path = $event['banner_image_path'];
    if (!empty($_FILES['banner_image']['tmp_name'])) {
        $result = save_uploaded_image($_FILES['banner_image'], 'banner');
        if ($result === false) {
            $errors[] = 'バナー画像はPNG/JPGのみアップロードできます。';
        } else {
            $banner_path = $result;
        }
    }

    if (empty($errors)) {
        $upd = $db->prepare(
            'UPDATE events
                SET name = ?, summary = ?, banner_image_path = ?,
                    start_at = ?, end_at = ?,
                    bgcolor = ?, fontcolor = ?, headercolor = ?, bordercolor = ?
              WHERE event_id = ? AND user_id = ?'
        );
        $upd->execute([
            $name, $summary ?: null, $banner_path,
            $start_at ?: null, $end_at ?: null,
            $bgcolor ?: null, $fontcolor ?: null, $headercolor ?: null, $bordercolor ?: null,
            $event_id, $uid,
        ]);

        // 走者削除（削除マーク済み）
        $delete_ids = array_filter(array_map('intval', $_POST['delete_runner_ids'] ?? []));
        if (!empty($delete_ids)) {
            $ph = implode(',', array_fill(0, count($delete_ids), '?'));
            $del_r = $db->prepare("DELETE FROM event_runners WHERE id IN ($ph) AND event_id = ?");
            $del_r->execute(array_merge($delete_ids, [$event_id]));
        }

        // 走者の更新・追加
        foreach ($runners_post as $idx => $r) {
            $runner_name = trim($r['name'] ?? '');
            if ($runner_name === '') continue;
            $runner_id_existing = (int)($r['id'] ?? 0);

            if ($runner_id_existing > 0) {
                // 既存走者の更新
                $r_upd = $db->prepare(
                    'UPDATE event_runners
                        SET name = ?, summary = ?, profile_url = ?, stream_url = ?,
                            start_at = ?, end_at = ?
                      WHERE id = ? AND event_id = ?'
                );
                $r_upd->execute([
                    $runner_name,
                    trim($r['summary']     ?? '') ?: null,
                    trim($r['profile_url'] ?? '') ?: null,
                    trim($r['stream_url']  ?? '') ?: null,
                    trim($r['start_at']    ?? '') ?: null,
                    trim($r['end_at']      ?? '') ?: null,
                    $runner_id_existing, $event_id,
                ]);
            } else {
                // 新規走者の追加
                $r_ins = $db->prepare(
                    'INSERT INTO event_runners
                       (event_id, name, summary, profile_url, stream_url, start_at, end_at, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $r_ins->execute([
                    $event_id,
                    $runner_name,
                    trim($r['summary']     ?? '') ?: null,
                    trim($r['profile_url'] ?? '') ?: null,
                    trim($r['stream_url']  ?? '') ?: null,
                    trim($r['start_at']    ?? '') ?: null,
                    trim($r['end_at']      ?? '') ?: null,
                    (int)($r['sort_order'] ?? 0),
                ]);
            }
        }

        // 走者画像アップロード
        save_runner_images($event_id, $runners_post, $db);

        flash('success', 'イベントを更新しました。');
        header('Location: /edit?id=' . urlencode($event_id));
        exit;
    }

    // バリデーションエラー時は入力値を保持するため $event を上書き
    $event['name']        = $name;
    $event['summary']     = $summary;
    $event['start_at']    = $start_at;
    $event['end_at']      = $end_at;
    $event['bgcolor']     = $bgcolor;
    $event['fontcolor']   = $fontcolor;
    $event['headercolor'] = $headercolor;
    $event['bordercolor'] = $bordercolor;
}

// 走者一覧取得
$runner_stmt = $db->prepare(
    'SELECT id, name, summary, image_path, profile_url, stream_url,
            start_at, end_at, status, sort_order
       FROM event_runners
      WHERE event_id = ?
      ORDER BY start_at ASC, sort_order ASC'
);
$runner_stmt->execute([$event_id]);
$runners = $runner_stmt->fetchAll();

// datetime-local 用フォーマット
foreach ($runners as &$r) {
    $r['start_at_input'] = $r['start_at'] ? date('Y-m-d\TH:i', strtotime($r['start_at'])) : '';
    $r['end_at_input']   = $r['end_at']   ? date('Y-m-d\TH:i', strtotime($r['end_at']))   : '';
}
unset($r);

$event_start_input = $event['start_at'] ? date('Y-m-d\TH:i', strtotime($event['start_at'])) : '';
$event_end_input   = $event['end_at']   ? date('Y-m-d\TH:i', strtotime($event['end_at']))   : '';

$csrf = get_csrf_token();
html_head('イベント詳細更新 - ' . h($event['name']), true);
?>
<header class="site-header">
  <span class="header-title">Colsing Relay</span>
  <div class="header-actions">
    <a href="/portal" class="btn btn-ghost btn-sm">マイページ</a>
  </div>
</header>

<div class="container has-fixed-bottom" id="runner-form">
  <h1 class="page-title">イベント詳細更新画面</h1>

  <?php render_flash() ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e): ?>
        <div><?= h($e) ?></div>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <form method="post" action="/edit?id=<?= h($event_id) ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="action"     value="update">

    <!-- バナー画像 -->
    <div class="form-group">
      <label class="form-label">バナー画像（PNG/JPG）</label>
      <?php if ($event['banner_image_path']): ?>
        <img src="<?= h($event['banner_image_path']) ?>" style="max-height:100px;margin-bottom:0.4rem;">
      <?php endif ?>
      <input type="file" name="banner_image" class="form-control" accept="image/png,image/jpeg">
    </div>

    <!-- イベント名 -->
    <div class="form-group">
      <label class="form-label">イベント名 <span style="color:var(--color-danger)">*</span></label>
      <input type="text" name="name" class="form-control" value="<?= h($event['name']) ?>" required>
    </div>

    <!-- 概要 -->
    <div class="form-group">
      <label class="form-label">概要</label>
      <textarea name="summary" class="form-control"><?= h($event['summary'] ?? '') ?></textarea>
    </div>

    <!-- カラー設定 -->
    <div class="card">
      <div class="card-header">テーマカラー</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">背景色</label>
          <input type="color" name="bgcolor"     class="form-control" value="<?= h($event['bgcolor']     ?? '#f5f5f5') ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">文字色</label>
          <input type="color" name="fontcolor"   class="form-control" value="<?= h($event['fontcolor']   ?? '#212121') ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">ヘッダー色</label>
          <input type="color" name="headercolor" class="form-control" value="<?= h($event['headercolor'] ?? '#4a90e2') ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">ボーダー色</label>
          <input type="color" name="bordercolor" class="form-control" value="<?= h($event['bordercolor'] ?? '#e0e0e0') ?>">
        </div>
      </div>
    </div>

    <!-- 開始・終了日時 -->
    <div style="display:grid;grid-template-columns:1fr auto;gap:0.5rem;align-items:end;" class="form-group">
      <div>
        <label class="form-label" for="event-start-at">イベント開始日時</label>
        <input type="datetime-local" id="event-start-at" name="start_at" class="form-control"
               value="<?= h($event_start_input) ?>">
      </div>
      <button type="button" id="auto-start-btn" class="btn btn-secondary btn-sm" style="margin-bottom:0;">自動入力</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:0.5rem;align-items:end;" class="form-group">
      <div>
        <label class="form-label" for="event-end-at">イベント終了日時</label>
        <input type="datetime-local" id="event-end-at" name="end_at" class="form-control"
               value="<?= h($event_end_input) ?>">
      </div>
      <button type="button" id="auto-end-btn" class="btn btn-secondary btn-sm" style="margin-bottom:0;">自動入力</button>
    </div>

    <!-- 走者一覧（JS でレンダリング） -->
    <div id="runners-container"></div>
    <button type="button" id="add-runner-btn" class="btn btn-secondary" style="margin-bottom:1.5rem;">
      + 走者を追加
    </button>

    <!-- PC用ボタン -->
    <div class="pc-only-actions">
      <button type="submit" name="action" value="update"  class="btn btn-primary">更新</button>
      <button type="submit" name="action" value="publish" class="btn btn-success"
        onclick="return confirm('公開しますか？')">公開</button>
      <button type="submit" name="action" value="delete"  class="btn btn-danger"
        onclick="return confirm('削除すると元に戻せません。削除しますか？')" style="margin-left:auto;">削除</button>
      <a href="/portal" class="btn btn-secondary">キャンセル</a>
    </div>
  </form>
</div>

<!-- スマートフォン用固定ボタン -->
<div class="fixed-bottom-bar">
  <a href="/portal" class="btn btn-secondary">キャンセル</a>
  <form method="post" action="/edit?id=<?= h($event_id) ?>" style="flex:1;display:flex;gap:0.4rem;">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <button type="submit" name="action" value="publish" class="btn btn-success"
      onclick="return confirm('公開しますか？')" style="flex:1;">公開</button>
    <button type="submit" name="action" value="update"
      onclick="document.getElementById('runner-form').querySelector('form').submit();return false;"
      class="btn btn-primary" style="flex:2;">更新</button>
  </form>
</div>

<script>
// 走者の初期データをPHPからJSへ渡す
const INITIAL_RUNNERS = <?= json_encode(array_values($runners), JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('runners-container');
  if (!container) return;
  // 既存走者をフォームに再現
  INITIAL_RUNNERS.forEach((r) => {
    if (typeof addRunnerBlock === 'function') addRunnerBlock(container, r);
  });
});
</script>
<?php html_foot(); ?>
