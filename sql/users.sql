-- ユーザー情報テーブル
-- メールアドレスによるOTP認証でログイン・登録を行う。
-- メールアドレスの大文字小文字は区別しない（utf8mb4_unicode_ci で対応）。
CREATE TABLE users (
    user_id    VARCHAR(36)  NOT NULL COMMENT 'ユーザーID (UUID)',
    email      VARCHAR(255) NOT NULL COMMENT 'メールアドレス（小文字保存）',
    nickname   VARCHAR(128) NOT NULL COMMENT 'ニックネーム',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ユーザー情報';
