<?php
/**
 * SC001. ログイン画面
 * URL: /login
 * - メールアドレスを入力してOTPを送信する。
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/otp.php';
require_once __DIR__ . '/lib/view.php';
require_once __DIR__ . '/lib/config.php';

start_session();

// ログイン済みなら検索画面へ
if (get_logged_in_user_id() !== null) {
    header('Location: /index');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $email = trim($_POST['email'] ?? '');

    // ── 入力チェック ──────────────────────────────────
    if ($email === '') {
        $error = 'メールアドレスを入力してください。';
    } elseif (substr_count($email, '@') !== 1) {
        $error = 'メールアドレスの形式が正しくありません（「@」は1つのみ）。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'メールアドレスの形式が正しくありません。';
    }

    if ($error === '') {
        $ip = get_client_ip();
        $rate = check_rate_limit($ip);

        if ($rate === 'rate_limit') {
            $error = 'しばらく待ってから再実施してください。';
        } elseif ($rate === 'duplicate') {
            // 短期重複：2回目は無視してOTP画面へ遷移（メールは送らない）
            $_SESSION['otp_email'] = strtolower($email);
            header('Location: /otp');
            exit;
        } else {
            // OTP生成・送信
            $token = generate_otp();
            create_otp_token($email, $token);
            record_rate_limit($ip);

            // メール送信シェル呼び出し
            $shell = escapeshellarg(MAIL_SHELL_PATH);
            $addr  = escapeshellarg(strtolower($email));
            $otp   = escapeshellarg($token);
            @exec("{$shell} {$addr} {$otp} > /dev/null 2>&1 &");

            $_SESSION['otp_email'] = strtolower($email);
            header('Location: /otp');
            exit;
        }
    }
}

$csrf = get_csrf_token();
html_head('ログイン');
?>
<div class="page-center">
  <div class="page-center__box">
    <div class="page-center__title">ログイン</div>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif ?>
    <form method="post" action="/login">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <div class="form-group">
        <label class="form-label" for="email">メールアドレス</label>
        <input
          type="email"
          id="email"
          name="email"
          class="form-control"
          value="<?= h($_POST['email'] ?? '') ?>"
          autocomplete="email"
          required
        >
      </div>
      <button type="submit" class="btn btn-primary btn-block">ワンタイムパスワードを送信</button>
    </form>
  </div>
</div>
<?php html_foot(); ?>
