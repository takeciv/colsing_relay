-- イベント情報テーブル
-- イベントの基本情報、ステータス、テーマ（詳細画面スタイル）を管理する。
-- status: preview=プレビュー中（未公開）、published=公開済
-- theme: イベント詳細画面の表示テーマ（6種類）
-- name と summary に FULLTEXT INDEX を付与し、キーワード検索に対応する。
-- イベント起案者（ニックネーム）はアプリ側で users テーブルと JOIN して取得する。
CREATE TABLE events (
    event_id          VARCHAR(36)  NOT NULL COMMENT 'イベントID (UUID)',
    user_id           VARCHAR(36)  NOT NULL COMMENT '起案者ユーザーID',
    name              VARCHAR(255) NOT NULL COMMENT 'イベント名',
    summary           TEXT         COMMENT 'イベント概要',
    banner_image_path VARCHAR(512) COMMENT 'バナー画像パス',
    start_at          DATETIME     COMMENT 'イベント開始日時',
    end_at            DATETIME     COMMENT 'イベント終了日時',
    status            ENUM('preview','published') NOT NULL DEFAULT 'preview' COMMENT 'ステータス（preview=プレビュー/published=公開済）',
    bgcolor           VARCHAR(8) COMMENT '背景色',
    fontcolor         VARCHAR(8) COMMENT '文字色',
    headercolor       VARCHAR(8) COMMENT 'ヘッダー色',
    bordercolor       VARCHAR(8) COMMENT 'ボーダー色',
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (event_id),
    KEY idx_events_user_id (user_id),
    FULLTEXT KEY ft_events_name_summary (name, summary),
    CONSTRAINT fk_events_user_id FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='イベント情報';
