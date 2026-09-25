-- Web Pushサブスクリプション情報テーブル
-- スマートフォンへのプッシュ通知に使用するサブスクリプション情報を管理する。
-- 通知失敗時は status を 'NG' に更新する。
CREATE TABLE push_subscriptions (
    id         BIGINT       NOT NULL AUTO_INCREMENT COMMENT 'ID',
    user_id    VARCHAR(36)  NOT NULL COMMENT 'ユーザーID',
    endpoint   TEXT         NOT NULL COMMENT 'プッシュサービスのエンドポイントURL',
    p256dh     TEXT         NOT NULL COMMENT '暗号化用公開鍵 (base64url)',
    auth       TEXT         NOT NULL COMMENT '認証シークレット (base64url)',
    status     ENUM('OK','NG') NOT NULL DEFAULT 'OK' COMMENT 'サブスクリプション状態（NG=通知失敗）',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (id),
    KEY idx_push_subscriptions_user_id (user_id),
    CONSTRAINT fk_push_subscriptions_user_id FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Web Pushサブスクリプション情報';
