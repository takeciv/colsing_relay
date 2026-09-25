-- OTPトークン管理テーブル
-- ワンタイムパスワードの発行・検証に使用する。
-- 有効期限は発行から10分、失敗3回で失効する。
CREATE TABLE otp_tokens (
    id            BIGINT      NOT NULL AUTO_INCREMENT COMMENT 'ID',
    email         VARCHAR(255) NOT NULL COMMENT '送信先メールアドレス（小文字保存）',
    token         VARCHAR(6)  NOT NULL COMMENT 'ワンタイムパスワード（6桁英数字）',
    attempt_count INT         NOT NULL DEFAULT 0 COMMENT '失敗試行回数',
    expires_at    DATETIME    NOT NULL COMMENT '有効期限（発行から10分）',
    created_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    PRIMARY KEY (id),
    KEY idx_otp_tokens_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OTPトークン管理';
