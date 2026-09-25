<?php
/**
 * 認証ヘルパー
 * セッション管理・ログイン状態確認・Cookie 発行を担う。
 */

require_once __DIR__ . '/db.php';

/** セッションを安全に開始する（多重呼び出し対応） */
function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 365, // 1年
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * ログイン済みユーザーのIDを返す。未ログインは null。
 */
function get_logged_in_user_id(): ?string
{
    start_session();
    return $_SESSION['user_id'] ?? null;
}

/**
 * 未ログインの場合、現在のURLをリダイレクト先として保存しログイン画面へ遷移する。
 * ログイン済みであればそのまま処理を継続する。
 */
function require_login(): string
{
    $uid = get_logged_in_user_id();
    if ($uid === null) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login');
        exit;
    }
    return $uid;
}

/**
 * ユーザーをログイン状態にする。
 * セッションにユーザーIDをセットし、セッションIDを再生成する。
 */
function login_user(string $user_id): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
}

/**
 * ログアウト：セッション破棄・Cookie削除。
 */
function logout_user(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '',
            time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * CSRFトークンを生成・取得する。
 * セッションに保存しフォームの hidden フィールドに埋め込む。
 */
function get_csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * POSTリクエストのCSRFトークンを検証する。
 * 不正な場合は 403 を返して終了する。
 */
function verify_csrf_token(): void
{
    start_session();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

/**
 * UUID v4 を生成する。
 */
function generate_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * DBからユーザー情報を取得する。存在しない場合は null。
 */
function get_user_by_id(string $user_id): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}
