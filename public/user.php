<?php
/**
 * SC004. ユーザー情報更新画面
 * URL: /user
 * - ニックネームを更新する。
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/view.php';
require_once __DIR__ . '/lib/db.php';

start_session();
$uid = require_login();

$db   = get_db();
$user = get_user_by_id($uid);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $nickname = trim($_POST['nickname'] ?? '');

    if ($nickname === '') {
        $error = 'ニックネームを入力してください。';
    } elseif (mb_strlen($nickname) > 128) {
        $error = 'ニックネームは128文字以内で入力してください。';
    }

    if ($error === '') {
        $stmt = $db->prepare('UPDATE users SET nickname = ? WHERE user_id = ?');
        $stmt->execute([$nickname, $uid]);
        flash('success', 'ニックネームを更新しました。');
        header('Location: /portal');
        exit;
    }
}

$csrf     = get_csrf_token();
$nickname = $_POST['nickname'] ?? ($user['nickname'] ?? '');

html_head('ユーザー情報更新', true);
?>
<div class="container" style="padding-top:1.5rem;">
  <h1 class="page-title">ユーザー情報入力</h1>
  <?php render_flash() ?>
  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif ?>
  <div class="card" style="max-width:480px;">
    <form method="post" action="/user">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <div class="form-group">
        <label class="form-label" for="nickname">ニックネーム</label>
        <input
          type="text"
          id="nickname"
          name="nickname"
          class="form-control"
          maxlength="128"
          value="<?= h($nickname) ?>"
          required
        >
      </div>
      <button type="submit" class="btn btn-primary">更新</button>
      <a href="/portal" class="btn btn-secondary" style="margin-left:0.5rem;">キャンセル</a>
    </form>
  </div>
</div>
<?php html_foot(); ?>
