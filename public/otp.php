<?php
/**
 * SC002. OTP画面
 * URL: /otp
 * - ワンタイムパスワードを入力してログインする。
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/otp.php';
require_once __DIR__ . '/lib/view.php';
require_once __DIR__ . '/lib/db.php';

start_session();

// セッションにメールアドレスがなければログイン画面へ
if (empty($_SESSION['otp_email'])) {
    header('Location: /login');
    exit;
}

// ログイン済みなら検索画面へ
if (get_logged_in_user_id() !== null) {
    header('Location: /index');
    exit;
}

$email = $_SESSION['otp_email'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $input = strtoupper(trim($_POST['otp'] ?? ''));

    // ── 入力チェック ──────────────────────────────────
    if ($input === '') {
        $error = 'ワンタイムパスワードを入力してください。';
    } elseif (!preg_match('/^[A-Z0-9]{6}$/', $input)) {
        $error = 'ワンタイムパスワードは英数字6桁で入力してください。';
    }

    if ($error === '') {
        $result = verify_otp($email, $input);

        switch ($result) {
            case 'ok':
                // ユーザー存在確認・登録
                $db = get_db();
                $stmt = $db->prepare('SELECT user_id, nickname FROM users WHERE email = LOWER(?)');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user) {
                    // 新規ユーザー登録（nickname は後で init.php で設定）
                    $user_id = generate_uuid();
                    $ins = $db->prepare(
                        "INSERT INTO users (user_id, email, nickname) VALUES (?, LOWER(?), '')"
                    );
                    $ins->execute([$user_id, $email]);
                    $is_new = true;
                } else {
                    $user_id  = $user['user_id'];
                    $is_new   = ($user['nickname'] === '');
                }

                login_user($user_id);
                unset($_SESSION['otp_email']);

                if ($is_new) {
                    header('Location: /init');
                } else {
                    $redirect = $_SESSION['redirect_after_login'] ?? '/index';
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirect);
                }
                exit;

            case 'invalid':
                $error = 'ワンタイムパスワードが正しくありません。';
                break;

            case 'locked':
                $error = 'ワンタイムパスワードの試行回数が上限に達しました。再度メールアドレスを送信してください。';
                break;

            case 'expired':
            default:
                $error = 'ワンタイムパスワードの有効期限が切れました。再度メールアドレスを送信してください。';
                break;
        }
    }
}

$csrf = get_csrf_token();
html_head('ワンタイムパスワード入力');
?>
<div class="page-center">
  <div class="page-center__box">
    <div class="page-center__title">ワンタイムパスワード入力</div>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif ?>
    <p style="font-size:0.9rem;color:var(--color-muted);margin-bottom:1.2rem;">
      <?= h($email) ?> に送信されたワンタイムパスワードを入力してください。
    </p>
    <form method="post" action="/otp">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <div class="form-group">
        <label class="form-label" for="otp">ワンタイムパスワード</label>
        <input
          type="text"
          id="otp"
          name="otp"
          class="form-control"
          maxlength="6"
          autocomplete="one-time-code"
          inputmode="text"
          style="text-transform:uppercase;letter-spacing:0.2em;font-size:1.4rem;text-align:center;"
          required
        >
      </div>
      <button type="submit" class="btn btn-primary btn-block">ログイン</button>
    </form>
    <div style="margin-top:1rem;text-align:center;">
      <a href="/login" style="font-size:0.85rem;">メールアドレスを再入力する</a>
    </div>
  </div>
</div>
<?php html_foot(); ?>
