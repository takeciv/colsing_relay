<?php
/**
 * SC031. イベント詳細作成画面
 * URL: /create
 * - イベント情報と走者情報を登録し、プレビュー画面へ遷移する。
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/view.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/upload.php';

start_session();
$uid = require_login();

$db     = get_db();
$errors = [];

// ── アクティブイベント数チェック ──────────────────────
$active_stmt = $db->prepare(
    "SELECT COUNT(*) AS cnt FROM events
      WHERE user_id = ?
        AND (status = 'preview' OR end_at IS NULL OR end_at > NOW())"
);
$active_stmt->execute([$uid]);
$active_count = (int)$active_stmt->fetch()['cnt'];

if ($active_count >= EVENT_MAX_ACTIVE) {
    flash('error', '作成できるイベント数の上限（' . EVENT_MAX_ACTIVE . '件）に達しています。');
    header('Location: /index');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $name      = trim($_POST['name']      ?? '');
    $summary   = trim($_POST['summary']   ?? '');
    $start_at  = trim($_POST['start_at']  ?? '');
    $end_at    = trim($_POST['end_at']    ?? '');
    $bgcolor     = trim($_POST['bgcolor']     ?? '');
    $fontcolor   = trim($_POST['fontcolor']   ?? '');
    $headercolor = trim($_POST['headercolor'] ?? '');
    $bordercolor = trim($_POST['bordercolor'] ?? '');

    if ($name === '') {
        $errors[] = 'イベント名は必須です。';
    }

    // 走者バリデーション
    $runners_post = $_POST['runners'] ?? [];
    foreach ($runners_post as $idx => $r) {
        if (trim($r['name'] ?? '') === '') {
            $errors[] = '走者 #' . ($idx + 1) . ' の走者名は必須です。';
        }
    }

    // 画像バリデーション
    $banner_path = null;
    if (!empty($_FILES['banner_image']['tmp_name'])) {
        $result = save_uploaded_image($_FILES['banner_image'], 'banner');
        if ($result === false) {
            $errors[] = 'バナー画像はPNG/JPGのみアップロードできます。';
        } else {
            $banner_path = $result;
        }
    }

    if (empty($errors)) {
        $event_id = generate_uuid();
        $ins = $db->prepare(
            "INSERT INTO events
               (event_id, user_id, name, summary, banner_image_path,
                start_at, end_at, status, bgcolor, fontcolor, headercolor, bordercolor)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'preview', ?, ?, ?, ?)"
        );
        $ins->execute([
            $event_id, $uid, $name, $summary ?: null, $banner_path,
            $start_at ?: null, $end_at ?: null,
            $bgcolor ?: null, $fontcolor ?: null, $headercolor ?: null, $bordercolor ?: null,
        ]);

        // 走者INSERT
        foreach ($runners_post as $r) {
            $runner_name = trim($r['name'] ?? '');
            if ($runner_name === '') continue;

            $run_ins = $db->prepare(
                'INSERT INTO event_runners
                   (event_id, name, summary, image_path, profile_url, stream_url, start_at, end_at, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $run_ins->execute([
                $event_id,
                $runner_name,
                trim($r['summary']     ?? '') ?: null,
                null, // 走者画像は後述の処理で対応
                trim($r['profile_url'] ?? '') ?: null,
                trim($r['stream_url']  ?? '') ?: null,
                trim($r['start_at']    ?? '') ?: null,
                trim($r['end_at']      ?? '') ?: null,
                (int)($r['sort_order'] ?? 0),
            ]);
        }

        // 走者画像アップロード処理
        save_runner_images($event_id, $runners_post, $db);

        header('Location: /preview?id=' . urlencode($event_id));
        exit;
    }
}

$csrf = get_csrf_token();
html_head('イベント詳細作成', true);
?>
<header class="site-header">
  <span class="header-title">Colsing Relay</span>
  <div class="header-actions">
    <a href="/portal" class="btn btn-ghost btn-sm">マイページ</a>
  </div>
</header>

<div class="container has-fixed-bottom" id="runner-form">
  <h1 class="page-title">イベント詳細作成画面</h1>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e): ?>
        <div><?= h($e) ?></div>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <form method="post" action="/create" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <!-- テーマ選択 -->
    <div class="card">
      <div class="card-header">テーマ</div>
      <div id="theme-presets"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-top:0.5rem;">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">背景色</label>
          <input type="color" name="bgcolor"     class="form-control" value="<?= h($_POST['bgcolor']     ?? '#f5f5f5') ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">文字色</label>
          <input type="color" name="fontcolor"   class="form-control" value="<?= h($_POST['fontcolor']   ?? '#212121') ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">ヘッダー色</label>
          <input type="color" name="headercolor" class="form-control" value="<?= h($_POST['headercolor'] ?? '#4a90e2') ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">ボーダー色</label>
          <input type="color" name="bordercolor" class="form-control" value="<?= h($_POST['bordercolor'] ?? '#e0e0e0') ?>">
        </div>
      </div>
    </div>

    <!-- バナー画像 -->
    <div class="form-group">
      <label class="form-label">バナー画像（PNG/JPG）</label>
      <input type="file" name="banner_image" class="form-control" accept="image/png,image/jpeg">
    </div>

    <!-- イベント名 -->
    <div class="form-group">
      <label class="form-label">イベント名 <span style="color:var(--color-danger)">*</span></label>
      <input type="text" name="name" class="form-control" value="<?= h($_POST['name'] ?? '') ?>" required>
    </div>

    <!-- 概要 -->
    <div class="form-group">
      <label class="form-label">概要</label>
      <textarea name="summary" class="form-control"><?= h($_POST['summary'] ?? '') ?></textarea>
    </div>

    <!-- 開始・終了日時 -->
    <div style="display:grid;grid-template-columns:1fr auto;gap:0.5rem;align-items:end;" class="form-group">
      <div>
        <label class="form-label" for="event-start-at">イベント開始日時</label>
        <input type="datetime-local" id="event-start-at" name="start_at" class="form-control"
               value="<?= h($_POST['start_at'] ?? '') ?>">
      </div>
      <button type="button" id="auto-start-btn" class="btn btn-secondary btn-sm" style="margin-bottom:0;">自動入力</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:0.5rem;align-items:end;" class="form-group">
      <div>
        <label class="form-label" for="event-end-at">イベント終了日時</label>
        <input type="datetime-local" id="event-end-at" name="end_at" class="form-control"
               value="<?= h($_POST['end_at'] ?? '') ?>">
      </div>
      <button type="button" id="auto-end-btn" class="btn btn-secondary btn-sm" style="margin-bottom:0;">自動入力</button>
    </div>

    <!-- 走者一覧 -->
    <div id="runners-container"></div>
    <button type="button" id="add-runner-btn" class="btn btn-secondary" style="margin-bottom:1.5rem;">
      + 走者を追加
    </button>

    <!-- PC用ボタン -->
    <div class="pc-only-actions">
      <button type="submit" class="btn btn-primary">プレビューへ</button>
      <a href="/portal" class="btn btn-secondary">キャンセル</a>
    </div>
  </form>
</div>

<!-- スマートフォン用固定ボタン -->
<div class="fixed-bottom-bar">
  <a href="/portal" class="btn btn-secondary">キャンセル</a>
  <button form="" type="submit" class="btn btn-primary"
    onclick="document.querySelector('form').submit()">プレビューへ</button>
</div>
<?php html_foot(); ?>
