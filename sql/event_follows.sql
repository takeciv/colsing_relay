-- イベントフォロー関係テーブル
-- ユーザーとイベントのフォロー関係を管理する。
-- ポータル画面のフォロー一覧表示と、走者ステータス変更時のプッシュ通知対象絞り込みに使用する。
-- (user_id, event_id) にUNIQUE制約を設け、同一ユーザーによる重複フォローを防ぐ。
CREATE TABLE event_follows (
    id         BIGINT      NOT NULL AUTO_INCREMENT COMMENT 'ID',
    user_id    VARCHAR(36) NOT NULL COMMENT 'ユーザーID',
    event_id   VARCHAR(36) NOT NULL COMMENT 'イベントID',
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'フォロー日時',
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_follows_user_event (user_id, event_id),
    KEY idx_event_follows_event_id (event_id),
    CONSTRAINT fk_event_follows_user_id  FOREIGN KEY (user_id)  REFERENCES users  (user_id)  ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_event_follows_event_id FOREIGN KEY (event_id) REFERENCES events (event_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='イベントフォロー関係';
