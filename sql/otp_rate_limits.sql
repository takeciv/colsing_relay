-- OTP送信レート制限ログテーブル
-- IP別および全体のOTP送信回数を制御するためのログ。
-- アプリ側で期間内のCOUNTを取得し、以下のルールを適用する:
--   - 同一IPかつ同一アドレスから1分以内に2回のリクエスト → 2回目を無視
--   - 同一IPから10分以内に規定回数(デフォルト:3)以上 → エラー
--   - システム全体で規定時間(デフォルト:1分)以内に規定回数(デフォルト:4)以上 → エラー
CREATE TABLE otp_rate_limits (
    id           BIGINT      NOT NULL AUTO_INCREMENT COMMENT 'ID',
    ip_address   VARCHAR(45) NOT NULL COMMENT '送信元IPアドレス（IPv6対応）',
    email        VARCHAR(255) NOT NULL DEFAULT '' COMMENT '送信先メールアドレス',
    requested_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'リクエスト日時',
    PRIMARY KEY (id),
    KEY idx_otp_rate_limits_ip_email_requested (ip_address, email, requested_at),
    KEY idx_otp_rate_limits_ip_requested (ip_address, requested_at),
    KEY idx_otp_rate_limits_requested (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OTP送信レート制限ログ';
