<?php
/**
 * SC010. 検索画面
 * URL: /index
 * - イベントをキーワード検索・ページネーション・ソートで一覧表示する。
 * - ログインなしでも利用可能。
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/view.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/config.php';

start_session();
$uid        = get_logged_in_user_id();
$is_logged  = $uid !== null;

$keyword = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$sort    = in_array($_GET['sort'] ?? '', ['start_at', 'name', 'nickname']) ? $_GET['sort'] : 'start_at';
$offset  = ($page - 1) * SEARCH_PAGE_SIZE;

$db = get_db();

// ── クエリ構築 ────────────────────────────────────────
$where  = ["e.status = 'published'"];
$params = [];

if ($keyword !== '') {
    $like = '%' . $keyword . '%';
    $where[] = '(e.name LIKE ? OR e.summary LIKE ? OR u.nickname LIKE ?)';
    $params = array_merge($params, [$like, $like, $like]);
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$order_map = [
    'start_at' => 'e.start_at ASC',
    'name'     => 'e.name ASC',
    'nickname' => 'u.nickname ASC',
];
$order_sql = 'ORDER BY ' . $order_map[$sort];

// 総件数
$count_stmt = $db->prepare(
    "SELECT COUNT(*) AS cnt
       FROM events e
       JOIN users u ON u.user_id = e.user_id
       {$where_sql}"
);
$count_stmt->execute($params);
$total = (int)$count_stmt->fetch()['cnt'];
$total_pages = max(1, (int)ceil($total / SEARCH_PAGE_SIZE));

// 一覧取得
$list_stmt = $db->prepare(
    "SELECT e.event_id, e.name, e.summary, e.banner_image_path,
            e.start_at, e.end_at,
            u.nickname AS organizer
       FROM events e
       JOIN users u ON u.user_id = e.user_id
       {$where_sql}
       {$order_sql}
      LIMIT ? OFFSET ?"
);
$list_params = array_merge($params, [SEARCH_PAGE_SIZE, $offset]);
$list_stmt->execute($list_params);
$events = $list_stmt->fetchAll();

// ── URL生成ヘルパー ───────────────────────────────────
function index_url(array $overrides = []): string
{
    $base = ['q' => $_GET['q'] ?? '', 'sort' => $_GET['sort'] ?? 'start_at', 'page' => $_GET['page'] ?? 1];
    $params = array_merge($base, $overrides);
    return '/index?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
}

html_head('イベント検索', $is_logged);
?>
<header class="site-header">
  <span class="header-title">Colsing Relay</span>
  <div class="header-actions">
    <?php if ($is_logged): ?>
      <a href="/portal" class="btn btn-ghost btn-sm">マイページ</a>
    <?php else: ?>
      <a href="/login" class="btn btn-ghost btn-sm">ログイン</a>
    <?php endif ?>
  </div>
</header>

<div class="container">
  <h1 class="page-title">イベント検索</h1>
  <?php render_flash() ?>

  <form method="get" action="/index" class="search-form">
    <input
      type="text"
      name="q"
      class="form-control"
      placeholder="イベント名・起案者・概要で検索..."
      value="<?= h($keyword) ?>"
      maxlength="128"
    >
    <input type="hidden" name="sort" value="<?= h($sort) ?>">
    <button type="submit" class="btn btn-primary">検索</button>
  </form>

  <?php if (isset($_GET['q']) || isset($_GET['sort'])): ?>
    <hr class="section-divider">

    <div class="sort-bar">
      <span>並び順：</span>
      <a href="<?= h(index_url(['sort' => 'start_at', 'page' => 1])) ?>" class="<?= $sort === 'start_at' ? 'active' : '' ?>">開始日時</a>
      <a href="<?= h(index_url(['sort' => 'name',     'page' => 1])) ?>" class="<?= $sort === 'name'     ? 'active' : '' ?>">イベント名</a>
      <a href="<?= h(index_url(['sort' => 'nickname', 'page' => 1])) ?>" class="<?= $sort === 'nickname' ? 'active' : '' ?>">起案者</a>
    </div>

    <?php if (empty($events)): ?>
      <p style="color:var(--color-muted);">該当するイベントが見つかりませんでした。</p>
    <?php else: ?>
      <ul class="event-list">
        <?php foreach ($events as $ev): ?>
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
            <a href="<?= h(index_url(['page' => $page - 1])) ?>">前へ</a>
          <?php endif ?>
          <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
            <?php if ($p === $page): ?>
              <span class="current"><?= $p ?></span>
            <?php else: ?>
              <a href="<?= h(index_url(['page' => $p])) ?>"><?= $p ?></a>
            <?php endif ?>
          <?php endfor ?>
          <?php if ($page < $total_pages): ?>
            <a href="<?= h(index_url(['page' => $page + 1])) ?>">次へ</a>
          <?php endif ?>
        </nav>
      <?php endif ?>
    <?php endif ?>
  <?php endif ?>
</div>
<?php html_foot(); ?>
