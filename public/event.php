<?php
/**
 * SC030. イベント詳細画面
 * URL: /event?id=xxxx
 * - テーマカラー適用、1秒ポーリングでリアルタイム更新。
 * - SC034（走者ステータス更新ダイアログ）を含む。
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/view.php';
require_once __DIR__ . '/lib/db.php';

start_session();
$uid       = get_logged_in_user_id();
$is_logged = $uid !== null;

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

if (!$event || $event['status'] !== 'published') {
    header('Location: /index');
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

$is_organizer = $is_logged && ($uid === $event['user_id']);

// フォロー状態
$is_following = false;
if ($is_logged && !$is_organizer) {
    $fstmt = $db->prepare(
        'SELECT id FROM event_follows WHERE user_id = ? AND event_id = ?'
    );
    $fstmt->execute([$uid, $event_id]);
    $is_following = (bool)$fstmt->fetch();
}

$status_labels = ['before' => '開始前', 'running' => '実施中', 'finished' => '終了'];
$status_class  = ['before' => 'status-before', 'running' => 'status-running', 'finished' => 'status-finished'];

// テーマカラー
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
html_head(h($event['name']), $is_logged, $theme_css ? "<style>{$theme_css}</style>" : null);
?>
<div class="event-detail-wrap">
  <header class="event-detail-header">
    <a href="/index" class="btn btn-ghost btn-sm">検索</a>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <?php if ($is_logged && !$is_organizer): ?>
        <?php if ($is_following): ?>
          <button class="btn btn-ghost btn-sm" id="unfollow-btn">フォロー解除</button>
        <?php else: ?>
          <button class="btn btn-ghost btn-sm" id="follow-btn">フォロー</button>
        <?php endif ?>
        <a href="/portal" class="btn btn-ghost btn-sm">マイページ</a>
      <?php endif ?>
      <?php if (!$is_logged): ?>
        <a href="/login" class="btn btn-ghost btn-sm">ログイン</a>
      <?php endif ?>
      <?php if ($is_organizer): ?>
        <a href="/edit?id=<?= h($event_id) ?>" class="btn btn-ghost btn-sm">編集</a>
        <a href="/portal" class="btn btn-ghost btn-sm">マイページ</a>
      <?php endif ?>
    </div>
  </header>

  <?php if ($event['banner_image_path']): ?>
    <img class="event-banner" src="<?= h($event['banner_image_path']) ?>" alt="バナー画像">
  <?php endif ?>

  <div class="container event-detail-body">
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

    <!-- 走者一覧（初期レンダリング） -->
    <div id="runners-list">
      <?php foreach ($runners as $r): ?>
        <div class="runner-card" data-runner-id="<?= h($r['id']) ?>">
          <div class="runner-card__top">
            <?php if ($r['image_path']): ?>
              <img class="runner-card__img" src="<?= h($r['image_path']) ?>" alt="">
            <?php else: ?>
              <div class="runner-card__img"></div>
            <?php endif ?>
            <div class="runner-card__info">
              <div class="runner-card__name"><?= h($r['name']) ?></div>
              <div>
                <span class="status-badge <?= h($status_class[$r['status']] ?? '') ?>" data-status>
                  <?= h($status_labels[$r['status']] ?? '') ?>
                </span>
              </div>
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
          <?php if ($is_organizer): ?>
            <div style="margin-top:0.5rem;">
              <button
                class="btn btn-secondary btn-sm open-status-dialog"
                data-runner-id="<?= h($r['id']) ?>"
                data-runner-name="<?= h($r['name']) ?>"
                data-status="<?= h($r['status']) ?>"
                data-profile-url="<?= h($r['profile_url'] ?? '') ?>"
                data-stream-url="<?= h($r['stream_url'] ?? '') ?>"
              >ステータス更新</button>
            </div>
          <?php endif ?>
        </div>
      <?php endforeach ?>
      <?php if (empty($runners)): ?>
        <p style="color:var(--color-muted);">走者が登録されていません。</p>
      <?php endif ?>
    </div>
  </div>
</div>

<!-- SC034: 走者ステータス更新ダイアログ -->
<?php if ($is_organizer): ?>
<div class="modal-overlay" id="status-modal">
  <div class="modal">
    <div class="modal-title">イベント走者ステータス更新</div>
    <input type="hidden" id="modal-runner-id" value="">
    <div class="form-group">
      <label class="form-label">走者名</label>
      <p id="modal-runner-name" style="font-weight:600;"></p>
    </div>
    <div class="form-group">
      <label class="form-label" for="modal-status">ステータス</label>
      <select id="modal-status" class="form-control">
        <option value="before">開始前</option>
        <option value="running">実施中</option>
        <option value="finished">終了</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label" for="modal-profile-url">プロフィールURL</label>
      <input type="url" id="modal-profile-url" class="form-control">
    </div>
    <div class="form-group">
      <label class="form-label" for="modal-stream-url">配信枠URL</label>
      <input type="url" id="modal-stream-url" class="form-control">
    </div>
    <div class="modal-actions">
      <button class="btn btn-secondary" id="modal-cancel">キャンセル</button>
      <button class="btn btn-primary"   id="modal-submit">更新</button>
    </div>
  </div>
</div>
<?php endif ?>

<script>
const EVENT_ID  = <?= json_encode($event_id) ?>;
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
const IS_ORGANIZER = <?= $is_organizer ? 'true' : 'false' ?>;
const IS_LOGGED = <?= $is_logged ? 'true' : 'false' ?>;

// ── 1秒ポーリング ────────────────────────────────────
const STATUS_LABELS = { before: '開始前', running: '実施中', finished: '終了' };
const STATUS_CLASS  = { before: 'status-before', running: 'status-running', finished: 'status-finished' };

async function pollEvent() {
  try {
    const res = await fetch('/api/event.php?id=' + encodeURIComponent(EVENT_ID));
    if (!res.ok) return;
    const data = await res.json();
    updateRunners(data.runners || []);
  } catch (e) {}
}

function updateRunners(runners) {
  runners.forEach((r) => {
    const card = document.querySelector(`.runner-card[data-runner-id="${r.id}"]`);
    if (!card) return;
    const badge = card.querySelector('[data-status]');
    if (badge) {
      badge.textContent = STATUS_LABELS[r.status] || r.status;
      badge.className = 'status-badge ' + (STATUS_CLASS[r.status] || '');
    }
  });
}

setInterval(pollEvent, 1000);

// ── フォロー / フォロー解除 ──────────────────────────
async function sendFollow(action) {
  const fd = new FormData();
  fd.append('action', action);
  fd.append('event_id', EVENT_ID);
  fd.append('csrf_token', CSRF_TOKEN);
  const res = await fetch('/api/follow.php', { method: 'POST', body: fd });
  return res.ok;
}

document.getElementById('follow-btn')?.addEventListener('click', async () => {
  if (await sendFollow('follow')) location.reload();
});
document.getElementById('unfollow-btn')?.addEventListener('click', async () => {
  if (await sendFollow('unfollow')) location.reload();
});

// ── 走者ステータス更新ダイアログ ─────────────────────
if (IS_ORGANIZER) {
  const modal = document.getElementById('status-modal');

  document.querySelectorAll('.open-status-dialog').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.getElementById('modal-runner-id').value  = btn.dataset.runnerId;
      document.getElementById('modal-runner-name').textContent = btn.dataset.runnerName;
      document.getElementById('modal-status').value     = btn.dataset.status;
      document.getElementById('modal-profile-url').value = btn.dataset.profileUrl;
      document.getElementById('modal-stream-url').value  = btn.dataset.streamUrl;
      modal.classList.add('open');
    });
  });

  document.getElementById('modal-cancel')?.addEventListener('click', () => {
    modal.classList.remove('open');
  });
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) modal.classList.remove('open');
  });

  document.getElementById('modal-submit')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('runner_id',   document.getElementById('modal-runner-id').value);
    fd.append('status',      document.getElementById('modal-status').value);
    fd.append('profile_url', document.getElementById('modal-profile-url').value);
    fd.append('stream_url',  document.getElementById('modal-stream-url').value);
    fd.append('csrf_token',  CSRF_TOKEN);

    const res = await fetch('/api/runner_status.php', { method: 'POST', body: fd });
    if (res.ok) {
      modal.classList.remove('open');
      pollEvent();
    } else {
      alert('更新に失敗しました。');
    }
  });
}
</script>
<?php html_foot(); ?>
