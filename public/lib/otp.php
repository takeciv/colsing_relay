<?php
/**
 * OTP（ワンタイムパスワード）生成・検証・レート制限ロジック
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * 6桁の英数字OTPを生成する。
 */
function generate_otp(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 紛らわしい文字を除外
    $otp = '';
    for ($i = 0; $i < 6; $i++) {
        $otp .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $otp;
}

/**
 * OTPトークンをDBに保存する（以前の未失効トークンは無効化しない）。
 * 有効期限は現在時刻 + OTP_EXPIRE_MINUTES。
 */
function create_otp_token(string $email, string $token): void
{
    $db = get_db();
    $expires_at = date('Y-m-d H:i:s', time() + OTP_EXPIRE_MINUTES * 60);
    $stmt = $db->prepare(
        'INSERT INTO otp_tokens (email, token, attempt_count, expires_at)
         VALUES (LOWER(?), ?, 0, ?)'
    );
    $stmt->execute([$email, $token, $expires_at]);
}

/**
 * OTPを検証する。
 *
 * @return string 'ok' | 'invalid' | 'expired' | 'locked'
 */
function verify_otp(string $email, string $input_token): string
{
    $db = get_db();

    // 最新の有効なトークンを取得
    $stmt = $db->prepare(
        'SELECT * FROM otp_tokens
          WHERE email = LOWER(?)
            AND expires_at > NOW()
          ORDER BY created_at DESC
          LIMIT 1'
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row) {
        return 'expired';
    }

    if ((int)$row['attempt_count'] >= OTP_MAX_ATTEMPTS) {
        return 'locked';
    }

    if (!hash_equals($row['token'], strtoupper($input_token))) {
        // 試行回数を+1
        $upd = $db->prepare(
            'UPDATE otp_tokens SET attempt_count = attempt_count + 1 WHERE id = ?'
        );
        $upd->execute([$row['id']]);
        return 'invalid';
    }

    // 成功：使用済みとして有効期限を過去にする
    $del = $db->prepare('DELETE FROM otp_tokens WHERE id = ?');
    $del->execute([$row['id']]);

    return 'ok';
}

/**
 * IPアドレスとメールアドレスに対してレート制限をチェックする。
 *
 * @return string|null null = 制限なし, 'duplicate' = 短期重複（2回目を無視）, 'rate_limit' = 制限超過
 */
function check_rate_limit(string $ip, string $email = ''): ?string
{
    $db = get_db();

    // 1. 同一IPかつ同一アドレスの短期ウィンドウ内リクエスト数チェック（1分以内に2回 → 2回目を無視）
    if ($email !== '') {
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS cnt FROM otp_rate_limits
              WHERE ip_address = ?
                AND email = LOWER(?)
                AND requested_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$ip, $email, OTP_RATE_LIMIT_IP_SHORT_WINDOW_SEC]);
    } else {
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS cnt FROM otp_rate_limits
              WHERE ip_address = ?
                AND requested_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$ip, OTP_RATE_LIMIT_IP_SHORT_WINDOW_SEC]);
    }
    $row = $stmt->fetch();
    if ((int)$row['cnt'] >= OTP_RATE_LIMIT_IP_SHORT_MAX) {
        return 'duplicate';
    }

    // 2. 同一IPの長期ウィンドウ内リクエスト数チェック（10分以内に3回以上 → エラー）
    $stmt = $db->prepare(
        'SELECT COUNT(*) AS cnt FROM otp_rate_limits
          WHERE ip_address = ?
            AND requested_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
    );
    $stmt->execute([$ip, OTP_RATE_LIMIT_IP_LONG_WINDOW_SEC]);
    $row = $stmt->fetch();
    if ((int)$row['cnt'] >= OTP_RATE_LIMIT_IP_LONG_MAX) {
        return 'rate_limit';
    }

    // 3. システム全体のウィンドウ内リクエスト数チェック
    $stmt = $db->prepare(
        'SELECT COUNT(*) AS cnt FROM otp_rate_limits
          WHERE requested_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
    );
    $stmt->execute([OTP_RATE_LIMIT_GLOBAL_WINDOW_SEC]);
    $row = $stmt->fetch();
    if ((int)$row['cnt'] >= OTP_RATE_LIMIT_GLOBAL_MAX) {
        return 'rate_limit';
    }

    return null;
}

/**
 * レート制限ログにIPアドレスとメールアドレスを記録する。
 */
function record_rate_limit(string $ip, string $email = ''): void
{
    $db = get_db();
    $stmt = $db->prepare(
        'INSERT INTO otp_rate_limits (ip_address, email) VALUES (?, LOWER(?))'
    );
    $stmt->execute([$ip, $email]);
}

/**
 * クライアントのIPアドレスを取得する。
 */
function get_client_ip(): string
{
    // リバースプロキシ環境を考慮
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            return $ip;
        }
    }
    return '0.0.0.0';
}
