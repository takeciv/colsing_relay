<?php
/**
 * SC032. イベント詳細プレビュー画面
 * URL: /preview?id=xxxx
 * - プレビューを表示し、「公開」「編集」ボタンを提供する。
 * - イベント起案者のみアクセス可。
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/view.php';
require_once __DIR__ . '/lib/db.php';

start_session();
$uid = require_login();

$event_id = $_GET['id'] ?? '';
if ($event_id === '') {
    header('Location: /index');
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

// 存在確認・起案者確認
if (!$event || $event['user_id'] !== $uid) {
    header('Location: /index');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';

    // 公開処理
    if ($action === 'publish') {
        $upd = $db->prepare("UPDATE events SET status = 'published' WHERE event_id = ? AND user_id = ?");
        $upd->execute([$event_id, $uid]);
        flash('success', 'イベントを公開しました。');
        header('Location: /portal');
        exit;
    }

    // 作成画面に戻る：プレビューイベントをDBから削除し、セッションの復元データを維持してリダイレクト
    if ($action === 'back_to_create') {
        $del = $db->prepare("DELETE FROM events WHERE event_id = ? AND user_id = ? AND status = 'preview'");
        $del->execute([$event_id, $uid]);
        // セッションに保存済みの create_restore はそのまま維持する
        header('Location: /create');
        exit;
    }
}

$runner_stmt = $db->prepare(
    'SELECT id, name, summary, image_path, profile_url, stream_url,
            start_at, end_at, status, runner_bgcolor
       FROM event_runners
      WHERE event_id = ?
      ORDER BY start_at ASC, sort_order ASC'
);
$runner_stmt->execute([$event_id]);
$runners = $runner_stmt->fetchAll();

$status_labels = ['before' => '開始前', 'running' => '実施中', 'finished' => '終了'];
$status_class  = ['before' => 'status-before', 'running' => 'status-running', 'finished' => 'status-finished'];

$theme_css = '';
if ($event['bgcolor'] || $event['fontcolor'] || $event['headercolor'] || $event['bordercolor']) {
    $theme_css = ':root{'
        . ($event['bgcolor']     ? '--theme-bg:'     . h($event['bgcolor'])     . ';' : '')
        . ($event['fontcolor']   ? '--theme-font:'   . h($event['fontcolor'])   . ';' : '')
        . ($event['headercolor'] ? '--theme-header:' . h($event['headercolor']) . ';' : '')
        . ($event['bordercolor'] ? '--theme-border:' . h($event['bordercolor']) . ';' : '')
        . '}';
}

$csrf = get_csrf_token();
html_head('[プレビュー] ' . h($event['name']), true, $theme_css ? "<style>{$theme_css}</style>" : null);
?>
<div class="event-detail-wrap has-fixed-bottom">
  <header class="event-detail-header">
    <span style="font-size:0.9rem;opacity:0.8;">プレビュー中</span>
    <div style="display:flex;gap:0.5rem;">
      <a href="/portal" class="btn btn-ghost btn-sm">マイページ</a>
    </div>
  </header>

  <?php if ($event['banner_image_path']): ?>
    <img class="event-banner" src="<?= h($event['banner_image_path']) ?>" alt="バナー画像">
  <?php endif ?>

  <div class="container event-detail-body">
    <?php render_flash() ?>
    <h1 class="event-title"><?= h($event['name']) ?></h1>
    <dl class="event-info-block">
      <dt>起案者</dt><dd><?= h($event['organizer']) ?></dd>
      <?php if ($event['summary']): ?>
        <dt>概要</dt><dd style="white-space:pre-wrap;"><?= h($event['summary']) ?></dd>
      <?php endif ?>
      <?php if ($event['start_at']): ?>
        <dt>開始</dt><dd><?= h(date('Y/m/d H:i', strtotime($event['start_at']))) ?></dd>
      <?php endif ?>
      <?php if ($event['end_at']): ?>
        <dt>終了</dt><dd><?= h(date('Y/m/d H:i', strtotime($event['end_at']))) ?></dd>
      <?php endif ?>
    </dl>

    <hr class="section-divider">

    <?php foreach ($runners as $r): ?>
      <div class="runner-card"<?= $r['runner_bgcolor'] ? ' style="background-color:' . h($r['runner_bgcolor']) . ';"' : '' ?>>
        <div class="runner-card__top">
          <?php if ($r['image_path']): ?>
            <img class="runner-card__img" src="<?= h($r['image_path']) ?>" alt="">
          <?php else: ?>
            <div class="runner-card__img"></div>
          <?php endif ?>
          <div class="runner-card__info">
            <div class="runner-card__name"><?= h($r['name']) ?></div>
            <span class="status-badge <?= h($status_class[$r['status']] ?? '') ?>">
              <?= h($status_labels[$r['status']] ?? '') ?>
            </span>
            <?php if ($r['start_at']): ?>
              <div style="font-size:0.82rem;color:var(--color-muted);">
                <?= h(date('Y/m/d H:i', strtotime($r['start_at']))) ?>
                <?= $r['end_at'] ? '〜 ' . h(date('Y/m/d H:i', strtotime($r['end_at']))) : '' ?>
              </div>
            <?php endif ?>
            <?php if ($r['profile_url']): ?>
              <div><a href="<?= h($r['profile_url']) ?>" target="_blank" rel="noopener">プロフィール</a></div>
            <?php endif ?>
            <?php if ($r['stream_url']): ?>
              <div><a href="<?= h($r['stream_url']) ?>" target="_blank" rel="noopener">配信リンク</a></div>
            <?php endif ?>
          </div>
        </div>
        <?php if ($r['summary']): ?>
          <div class="runner-card__summary"><?= h($r['summary']) ?></div>
        <?php endif ?>
      </div>
    <?php endforeach ?>

    <!-- PC用ボタン -->
    <div class="pc-only-actions">
      <form method="post" action="/preview?id=<?= h($event_id) ?>" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action"     value="publish">
        <button type="submit" class="btn btn-success">公開</button>
      </form>
      <form method="post" action="/preview?id=<?= h($event_id) ?>" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action"     value="back_to_create">
        <button type="submit" class="btn btn-secondary">作成画面に戻る</button>
      </form>
    </div>
  </div>
</div>

<!-- スマートフォン用固定ボタン -->
<div class="fixed-bottom-bar">
  <form method="post" action="/preview?id=<?= h($event_id) ?>" style="flex:1;">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="action"     value="back_to_create">
    <button type="submit" class="btn btn-secondary btn-block">作成画面に戻る</button>
  </form>
  <form method="post" action="/preview?id=<?= h($event_id) ?>" style="flex:1;">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="action"     value="publish">
    <button type="submit" class="btn btn-success btn-block">公開</button>
  </form>
</div>
<?php html_foot(); ?>
