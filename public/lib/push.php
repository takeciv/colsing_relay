<?php
/**
 * Web Push 送信ロジック（VAPID認証）
 *
 * 外部ライブラリを使わず、VAPIDトークン生成からHTTPリクエストまで
 * OpenSSL + file_get_contents で実装する。
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Web Push通知を送信する。
 *
 * @param string $endpoint    プッシュサービスのエンドポイントURL
 * @param string $p256dh      クライアント公開鍵（base64url）
 * @param string $auth        認証シークレット（base64url）
 * @param array  $payload     通知ペイロード ['title' => ..., 'body' => ..., 'url' => ...]
 * @return bool 送信成功なら true
 */
function send_push(string $endpoint, string $p256dh, string $auth, array $payload): bool
{
    if (VAPID_PUBLIC_KEY === '' || VAPID_PRIVATE_KEY === '') {
        // VAPID未設定の場合はスキップ
        return false;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

    // ── VAPIDトークン生成 ──────────────────────────────────
    $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);

    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims = base64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200, // 12時間
        'sub' => VAPID_SUBJECT,
    ]));

    $unsigned = $header . '.' . $claims;

    // 秘密鍵をPEM形式に変換
    $key = openssl_pkey_get_private(vapid_private_key_to_pem(VAPID_PRIVATE_KEY));
    if ($key === false) {
        return false;
    }

    openssl_sign($unsigned, $sig, $key, OPENSSL_ALGO_SHA256);
    $token = $unsigned . '.' . base64url_encode(der_to_raw_signature($sig));

    $vapid_auth = 'vapid t=' . $token . ', k=' . VAPID_PUBLIC_KEY;

    // ── 暗号化（content-encoding: aes128gcm）は省略し平文で送信 ──
    // 実運用では minishlink/web-push 等のライブラリを推奨。
    // 本実装では VAPID認証付きのシンプル通知（暗号化なし）として送信する。
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", [
                'Content-Type: application/json',
                'Authorization: ' . $vapid_auth,
                'TTL: 86400',
            ]),
            'content' => $json,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    $result = @file_get_contents($endpoint, false, $context);

    // $http_response_header はfile_get_contents後にグローバルにセットされる
    $status = 0;
    if (!empty($http_response_header)) {
        preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
        $status = (int)($m[1] ?? 0);
    }

    return ($status >= 200 && $status < 300);
}

/**
 * ユーザーのサブスクリプション情報を取得し、Push通知を送る。
 * 失敗したサブスクリプションは status='NG' に更新する。
 *
 * @param string[] $user_ids 通知対象ユーザーIDの配列
 * @param array    $payload  通知ペイロード
 */
function notify_users(array $user_ids, array $payload): void
{
    if (empty($user_ids)) {
        return;
    }

    $db = get_db();
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $stmt = $db->prepare(
        "SELECT id, user_id, endpoint, p256dh, auth
           FROM push_subscriptions
          WHERE user_id IN ($placeholders)
            AND status = 'OK'"
    );
    $stmt->execute($user_ids);
    $subscriptions = $stmt->fetchAll();

    foreach ($subscriptions as $sub) {
        $ok = send_push($sub['endpoint'], $sub['p256dh'], $sub['auth'], $payload);
        if (!$ok) {
            $upd = $db->prepare(
                "UPDATE push_subscriptions SET status = 'NG' WHERE id = ?"
            );
            $upd->execute([$sub['id']]);
        }
    }
}

// ── ユーティリティ ────────────────────────────────────────

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

/**
 * VAPID base64url秘密鍵をPEM形式（EC P-256）に変換する。
 */
function vapid_private_key_to_pem(string $b64url_key): string
{
    $raw = base64url_decode($b64url_key);
    // SEC1 ASN.1 DER ヘッダー（P-256 ECPrivateKey）
    $der = hex2bin('3077020101042') . $raw
         . hex2bin('a00a06082a8648ce3d030107a14403420004');
    // 実際はOpenSSL PKCS#8形式での読み込みが安全
    // ここでは簡易的にPEM wrappingを行う
    $pem = "-----BEGIN EC PRIVATE KEY-----\n"
         . chunk_split(base64_encode($raw), 64, "\n")
         . "-----END EC PRIVATE KEY-----\n";
    return $pem;
}

/**
 * OpenSSLが返すDER形式のECDSA署名をraw (r||s) 形式に変換する。
 */
function der_to_raw_signature(string $der): string
{
    // DER: 30 <len> 02 <r_len> <r> 02 <s_len> <s>
    $offset = 2; // skip 30 <len>
    $r_len = ord($der[$offset + 1]);
    $r = substr($der, $offset + 2, $r_len);
    $offset += 2 + $r_len;
    $s_len = ord($der[$offset + 1]);
    $s = substr($der, $offset + 2, $s_len);

    // 32バイトに正規化（先頭の00パディングを除去または補填）
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}
