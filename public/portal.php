<?php
/**
 * SC020. ポータル画面
 * URL: /portal
 * - 自分が起案したイベント一覧とフォロー中のイベント一覧を表示する。
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/view.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/config.php';

start_session();
$uid = require_login();

$db   = get_db();
$page = max(1, (int)($_GET['page'] ?? 1));
$sort = in_array($_GET['sort'] ?? '', ['start_at', 'name', 'nickname']) ? $_GET['sort'] : 'start_at';
$offset = ($page - 1) * SEARCH_PAGE_SIZE;

$order_map = [
    'start_at' => 'e.start_at ASC',
    'name'     => 'e.name ASC',
    'nickname' => 'u.nickname ASC',
];
$order_sql = 'ORDER BY ' . $order_map[$sort];

// ── 自分が起案したイベント一覧（ステータス問わず） ──
$own_stmt = $db->prepare(
    "SELECT e.event_id, e.name, e.summary, e.banner_image_path,
            e.start_at, e.end_at, e.status,
            u.nickname AS organizer
       FROM events e
       JOIN users u ON u.user_id = e.user_id
      WHERE e.user_id = ?
      ORDER BY e.created_at DESC"
);
$own_stmt->execute([$uid]);
$own_events = $own_stmt->fetchAll();

// ── フォロー中のイベント（published のみ、ページネーション付き） ──
$count_stmt = $db->prepare(
    "SELECT COUNT(*) AS cnt
       FROM event_follows ef
       JOIN events e ON e.event_id = ef.event_id
       JOIN users  u ON u.user_id  = e.user_id
      WHERE ef.user_id = ? AND e.status = 'published'"
);
$count_stmt->execute([$uid]);
$total       = (int)$count_stmt->fetch()['cnt'];
$total_pages = max(1, (int)ceil($total / SEARCH_PAGE_SIZE));

$follow_stmt = $db->prepare(
    "SELECT e.event_id, e.name, e.summary, e.banner_image_path,
            e.start_at, e.end_at,
            u.nickname AS organizer
       FROM event_follows ef
       JOIN events e ON e.event_id = ef.event_id
       JOIN users  u ON u.user_id  = e.user_id
      WHERE ef.user_id = ? AND e.status = 'published'
      {$order_sql}
      LIMIT ? OFFSET ?"
);
$follow_stmt->execute([$uid, SEARCH_PAGE_SIZE, $offset]);
$follow_events = $follow_stmt->fetchAll();

function portal_url(array $overrides = []): string
{
    $base = ['sort' => $_GET['sort'] ?? 'start_at', 'page' => $_GET['page'] ?? 1];
    return '/portal?' . http_build_query(array_merge($base, $overrides));
}

$status_labels = ['preview' => 'プレビュー', 'published' => '公開済'];

html_head('マイページ', true);
?>
<header class="site-header">
  <span class="header-title">Colsing Relay</span>
  <div class="header-actions">
    <a href="/index"  class="btn btn-ghost btn-sm">検索</a>
    <a href="/create" class="btn btn-ghost btn-sm">イベント作成</a>
    <a href="/user"   class="btn btn-ghost btn-sm">設定</a>
  </div>
</header>

<div class="container">
  <h1 class="page-title">マイページ</h1>
  <?php render_flash() ?>

  <!-- 自分のイベント -->
  <?php if (!empty($own_events)): ?>
  <h2 style="font-size:1rem;font-weight:600;margin-bottom:0.75rem;">自分のイベント</h2>
    <ul class="event-list">
      <?php foreach ($own_events as $ev): ?>
        <li class="event-item">
          <?php if ($ev['banner_image_path']): ?>
            <img class="event-item__img" src="<?= h($ev['banner_image_path']) ?>" alt="">
          <?php else: ?>
            <div class="event-item__img"></div>
          <?php endif ?>
          <div class="event-item__body">
            <div class="event-item__name"><?= h($ev['name']) ?></div>
            <div class="event-item__meta">
              状態：<?= h($status_labels[$ev['status']] ?? $ev['status']) ?>
            </div>
            <div class="event-item__meta">
              <?= $ev['start_at'] ? h(date('Y/m/d H:i', strtotime($ev['start_at']))) : '' ?>
              <?= $ev['end_at']   ? '〜 ' . h(date('Y/m/d H:i', strtotime($ev['end_at']))) : '' ?>
            </div>
            <div class="event-item__footer" style="display:flex;gap:0.4rem;flex-wrap:wrap;">
              <a href="/event?id=<?= h($ev['event_id']) ?>"   class="btn btn-primary btn-sm">詳細</a>
              <a href="/edit?id=<?= h($ev['event_id']) ?>"    class="btn btn-secondary btn-sm">編集</a>
              <?php if ($ev['status'] === 'preview'): ?>
                <a href="/preview?id=<?= h($ev['event_id']) ?>" class="btn btn-secondary btn-sm">プレビュー</a>
              <?php endif ?>
            </div>
          </div>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>

  <hr class="section-divider">

  <!-- フォロー中のイベント -->
  <h2 style="font-size:1rem;font-weight:600;margin-bottom:0.75rem;">フォロー中のイベント</h2>

  <div class="sort-bar">
    <span>並び順：</span>
    <a href="<?= h(portal_url(['sort' => 'start_at', 'page' => 1])) ?>" class="<?= $sort === 'start_at' ? 'active' : '' ?>">開始日時</a>
    <a href="<?= h(portal_url(['sort' => 'name',     'page' => 1])) ?>" class="<?= $sort === 'name'     ? 'active' : '' ?>">イベント名</a>
    <a href="<?= h(portal_url(['sort' => 'nickname', 'page' => 1])) ?>" class="<?= $sort === 'nickname' ? 'active' : '' ?>">起案者</a>
  </div>

  <?php if (empty($follow_events)): ?>
    <p style="color:var(--color-muted);font-size:0.9rem;">フォローしているイベントはありません。</p>
  <?php else: ?>
    <ul class="event-list">
      <?php foreach ($follow_events as $ev): ?>
        <li class="event-item">
          <?php if ($ev['banner_image_path']): ?>
            <img class="event-item__img" src="<?= h($ev['banner_image_path']) ?>" alt="">
          <?php else: ?>
            <div class="event-item__img"></div>
          <?php endif ?>
          <div class="event-item__body">
            <div class="event-item__name"><?= h($ev['name']) ?></div>
            <div class="event-item__meta">起案者：<?= h($ev['organizer']) ?></div>
            <div class="event-item__summary"><?= h($ev['summary'] ?? '') ?></div>
            <div class="event-item__meta">
              <?= $ev['start_at'] ? h(date('Y/m/d H:i', strtotime($ev['start_at']))) : '' ?>
              <?= $ev['end_at']   ? '〜 ' . h(date('Y/m/d H:i', strtotime($ev['end_at']))) : '' ?>
            </div>
            <div class="event-item__footer">
              <a href="/event?id=<?= h($ev['event_id']) ?>" class="btn btn-primary btn-sm">詳細</a>
            </div>
          </div>
        </li>
      <?php endforeach ?>
    </ul>

    <?php if ($total_pages > 1): ?>
      <nav class="pager">
        <?php if ($page > 1): ?>
          <a href="<?= h(portal_url(['page' => $page - 1])) ?>">前へ</a>
        <?php endif ?>
        <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
          <?php if ($p === $page): ?>
            <span class="current"><?= $p ?></span>
          <?php else: ?>
            <a href="<?= h(portal_url(['page' => $p])) ?>"><?= $p ?></a>
          <?php endif ?>
        <?php endfor ?>
        <?php if ($page < $total_pages): ?>
          <a href="<?= h(portal_url(['page' => $page + 1])) ?>">次へ</a>
        <?php endif ?>
      </nav>
    <?php endif ?>
  <?php endif ?>
</div>
<?php html_foot(); ?>
