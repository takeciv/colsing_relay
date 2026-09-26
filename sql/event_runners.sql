-- イベント走者情報テーブル
-- イベントに紐づく走者（配信者）の情報を管理する。
-- status: before=開始前、running=実施中、finished=終了
-- sort_order: 表示順の手動調整用（画面上はstart_at昇順が基本）
-- runner_bgcolor: 走者カードの背景色（イベントテーマに追加）
-- 走者のステータスが running に変更された際、イベントフォロー者へプッシュ通知が送信される。
CREATE TABLE event_runners (
    id              BIGINT       NOT NULL AUTO_INCREMENT COMMENT 'ID',
    event_id        VARCHAR(36)  NOT NULL COMMENT 'イベントID',
    name            VARCHAR(255) NOT NULL COMMENT '走者名',
    summary         TEXT         COMMENT '走者概要',
    image_path      VARCHAR(512) COMMENT '走者画像パス',
    profile_url     VARCHAR(2048) COMMENT 'プロフィールURL',
    stream_url      VARCHAR(2048) COMMENT '配信枠URL',
    start_at        DATETIME     COMMENT '配信開始日時',
    end_at          DATETIME     COMMENT '配信終了日時',
    status          ENUM('before','running','finished') NOT NULL DEFAULT 'before' COMMENT 'ステータス（before=開始前/running=実施中/finished=終了）',
    runner_bgcolor  VARCHAR(8)   COMMENT '走者カード背景色',
    sort_order      INT          NOT NULL DEFAULT 0 COMMENT '表示順',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (id),
    KEY idx_event_runners_event_id (event_id),
    CONSTRAINT fk_event_runners_event_id FOREIGN KEY (event_id) REFERENCES events (event_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='イベント走者情報';
