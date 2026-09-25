<?php
/**
 * アプリケーション設定
 */

// ── OTPレート制限 ──────────────────────────────────────────
/** OTP有効期限（分） */
define('OTP_EXPIRE_MINUTES', 10);

/** OTP最大試行回数 */
define('OTP_MAX_ATTEMPTS', 3);

/** 同一IPからの短期ウィンドウ（秒） */
define('OTP_RATE_LIMIT_IP_SHORT_WINDOW_SEC', 60);

/** 同一IPからの短期ウィンドウ内最大送信回数（これ以上は無視） */
define('OTP_RATE_LIMIT_IP_SHORT_MAX', 2);

/** 同一IPからの長期ウィンドウ（秒） */
define('OTP_RATE_LIMIT_IP_LONG_WINDOW_SEC', 600);

/** 同一IPからの長期ウィンドウ内最大送信回数 */
define('OTP_RATE_LIMIT_IP_LONG_MAX', 3);

/** システム全体ウィンドウ（秒） */
define('OTP_RATE_LIMIT_GLOBAL_WINDOW_SEC', 60);

/** システム全体ウィンドウ内最大送信回数 */
define('OTP_RATE_LIMIT_GLOBAL_MAX', 4);

// ── イベント ──────────────────────────────────────────────
/** 作成可能なアクティブイベント数の上限 */
define('EVENT_MAX_ACTIVE', 3);

/** 検索・ポータルの1ページあたり表示件数 */
define('SEARCH_PAGE_SIZE', 20);

// ── DB接続 ────────────────────────────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'colsing_relay');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ── Web Push (VAPID) ──────────────────────────────────────
/** VAPID公開鍵（base64url） */
define('VAPID_PUBLIC_KEY',  getenv('VAPID_PUBLIC_KEY')  ?: '');

/** VAPID秘密鍵（base64url） */
define('VAPID_PRIVATE_KEY', getenv('VAPID_PRIVATE_KEY') ?: '');

/** VAPID subject（mailto: または https://） */
define('VAPID_SUBJECT', getenv('VAPID_SUBJECT') ?: 'mailto:admin@example.com');

// ── アップロード ──────────────────────────────────────────
/** アップロードファイルの保存先（DocumentRoot 相対） */
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

/** アップロードファイルのURL prefix */
define('UPLOAD_URL_PREFIX', '/uploads/');

// ── メール送信シェル ───────────────────────────────────────
define('MAIL_SHELL_PATH', '/home/ccs-cardcaptor/tool/send_onetimepassword_for_relay.sh');
