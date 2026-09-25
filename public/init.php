<?php
/**
 * SC003. 初期情報入力画面
 * URL: /init
 * - 初回ログイン時にニックネームを登録する。
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/view.php';
require_once __DIR__ . '/lib/db.php';

start_session();
$uid = require_login();

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
        $db = get_db();
        $stmt = $db->prepare('UPDATE users SET nickname = ? WHERE user_id = ?');
        $stmt->execute([$nickname, $uid]);

        $redirect = $_SESSION['redirect_after_login'] ?? '/index';
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $redirect);
        exit;
    }
}

$csrf = get_csrf_token();
html_head('ユーザー情報入力', true);
?>
<div class="page-center">
  <div class="page-center__box">
    <div class="page-center__title">ユーザー情報入力</div>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif ?>
    <form method="post" action="/init">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <div class="form-group">
        <label class="form-label" for="nickname">ニックネーム</label>
        <input
          type="text"
          id="nickname"
          name="nickname"
          class="form-control"
          maxlength="128"
          value="<?= h($_POST['nickname'] ?? '') ?>"
          required
        >
      </div>
      <button type="submit" class="btn btn-primary btn-block">登録</button>
    </form>
  </div>
</div>
<?php html_foot(); ?>
