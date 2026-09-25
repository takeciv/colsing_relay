# PHP実装プラン

## 概要

`specifications/` ディレクトリの仕様書と `sql/` ディレクトリのDDLを元に、`public/` ディレクトリにPHPアプリケーションを実装する。
技術仕様に従い、PHP + MySQL + Web Push + PWA 対応の、POST/GETによる画面遷移型Webアプリを構築する。

### 設計方針

- **言語・DB**: PHP / MySQL (utf8mb4)
- **アーキテクチャ**: POST/GET画面遷移型。ただしイベント詳細画面のみJS APIコール（1秒ポーリング）
- **認証**: OTP（ワンタイムパスワード）メール認証、Cookie セッション管理
- **通知**: Web Push (VAPID)
- **PWA**: manifest.json + Service Worker
- **レスポンシブ**: PC・スマートフォン対応、ライトモード・ダークモード対応
- **テーマ**: イベント詳細画面に6種類のテーマ（デフォルト/シック/キュート/ゴージャス/ダーク/ブライトネス）
- **ファイル構成**: `public/` をDocumentRoot相当とし、共通ロジックは `public/lib/` に配置

### ディレクトリ構成（予定）

```
public/
├── index.php          # 検索画面 (/index)
├── login.php          # ログイン画面 (/login)
├── otp.php            # OTP画面 (/otp)
├── init.php           # 初期情報入力画面 (/init)
├── user.php           # ユーザー情報更新画面 (/user)
├── portal.php         # ポータル画面 (/portal)
├── event.php          # イベント詳細画面 (/event?id=xxxx)
├── create.php         # イベント詳細作成画面 (/create)
├── preview.php        # イベント詳細プレビュー画面 (/preview?id=xxxx)
├── edit.php           # イベント詳細更新画面 (/edit?id=xxxx)
├── api/
│   ├── event.php      # イベント情報取得API (GET, イベント詳細画面用)
│   ├── follow.php     # フォロー/フォロー解除API (POST)
│   ├── runner_status.php  # 走者ステータス更新API (POST)
│   └── push_subscribe.php # Push通知サブスクリプション登録API (POST)
├── lib/
│   ├── config.php     # 設定値（レート制限値等）
│   ├── db.php         # DB接続（PDO）
│   ├── auth.php       # 認証ヘルパー（セッション・ログイン確認）
│   ├── otp.php        # OTP生成・検証ロジック
│   └── push.php       # Web Push送信ロジック
├── css/
│   └── style.css      # 共通スタイルシート（テーマ変数含む）
├── js/
│   ├── app.js         # 共通JS（フォーム動的追加等）
│   └── sw.js          # Service Worker
└── manifest.json      # PWAマニフェスト
```

---

## サブタスク

### ST-01. 共通基盤ファイルの作成

**Intent**
全画面で利用する設定・DB接続・認証ヘルパーを作成する。これ以降のサブタスクの前提となる。

**Expected Outcomes**
- `public/lib/config.php` が作成され、OTPレート制限値・イベント数上限などの設定値が定義されている。
- `public/lib/db.php` が作成され、PDOによるMySQL接続関数が利用できる。
- `public/lib/auth.php` が作成され、ログイン状態確認・ユーザーID取得・ログイン強制リダイレクト関数が利用できる。
- `public/lib/otp.php` が作成され、OTP生成・検証・失効判定ロジックが利用できる。
- `public/lib/push.php` が作成され、Web Push送信ロジックが利用できる。

**Todo List**
1. `public/lib/config.php` を作成する。
   - `OTP_EXPIRE_MINUTES = 10`
   - `OTP_MAX_ATTEMPTS = 3`
   - `OTP_RATE_LIMIT_IP_WINDOW_SEC = 60` (1分)
   - `OTP_RATE_LIMIT_IP_MAX = 2`
   - `OTP_RATE_LIMIT_IP_LONG_WINDOW_SEC = 600` (10分)
   - `OTP_RATE_LIMIT_IP_LONG_MAX = 3`
   - `OTP_RATE_LIMIT_GLOBAL_WINDOW_SEC = 60` (1分)
   - `OTP_RATE_LIMIT_GLOBAL_MAX = 4`
   - `EVENT_MAX_ACTIVE = 3`
   - `SEARCH_PAGE_SIZE = 20`
   - VAPID公開鍵・秘密鍵のプレースホルダー
2. `public/lib/db.php` を作成する。
   - PDOで接続するシングルトン関数 `get_db()` を実装する。
   - 接続設定は環境変数または config から読み込む。
3. `public/lib/auth.php` を作成する。
   - `session_start()` ラッパー
   - `get_logged_in_user_id()`: Cookie/セッションからユーザーIDを返す。未ログインはnull。
   - `require_login()`: 未ログイン時にログイン画面へリダイレクト。
   - `login_user($user_id)`: セッションにユーザーIDをセットし、Cookie発行。
   - `logout_user()`: セッション破棄・Cookie削除。
4. `public/lib/otp.php` を作成する。
   - `generate_otp()`: 6桁英数字を生成。
   - `create_otp_token($email, $token)`: otp_tokensへINSERT。
   - `verify_otp($email, $input_token)`: 有効期限・試行回数・トークン一致を検証。成功/失敗/失効のステータスを返す。
   - `check_rate_limit($ip)`: otp_rate_limitsを参照し、制限に引っかかるかチェック。
   - `record_rate_limit($ip)`: otp_rate_limitsへINSERT。
5. `public/lib/push.php` を作成する。
   - VAPID認証を使ったWeb Push送信関数 `send_push($endpoint, $p256dh, $auth, $payload)` を実装する。
   - 送信失敗時はpush_subscriptionsのstatusをNGに更新する。

**Relevant Context**
- `sql/users.sql`, `sql/otp_tokens.sql`, `sql/otp_rate_limits.sql`, `sql/push_subscriptions.sql`
- 機能仕様「ログイン」の詳細条件（レート制限の数値）

**Status:** [ ] pending

---

### ST-02. PWA・静的リソースの作成

**Intent**
PWA対応に必要なマニフェスト・Service Worker・CSS・JSの基盤ファイルを作成する。

**Expected Outcomes**
- `public/manifest.json` が作成され、PWAとしてインストール可能。
- `public/js/sw.js` が作成され、Service Workerが登録できる。
- `public/css/style.css` が作成され、フラットデザイン・レスポンシブ・ライト/ダークモード・テーマCSS変数が定義されている。
- `public/js/app.js` が作成され、共通JS（フォーム動的追加、Push通知許可取得）が実装されている。

**Todo List**
1. `public/manifest.json` を作成する。
   - `name`, `short_name`, `start_url`, `display: standalone`, `icons` (192, 512) を設定する。
   - `icons` は `../images/icon-192.png`, `../images/icon-512.png` を参照する。
2. `public/js/sw.js` を作成する。
   - Push通知受信時に `self.registration.showNotification()` で通知を表示する。
   - 通知クリック時にイベント詳細画面へ遷移する。
3. `public/css/style.css` を作成する。
   - CSS変数でライト/ダークモードのカラースキームを定義する（`prefers-color-scheme` メディアクエリ）。
   - テーマ用CSS変数（`--header-color`, `--font-color`, `--bg-color`, `--card-color`, `--border-color`, `--button-color`）を6テーマ分定義する。
   - レスポンシブ対応（モバイルファーストで`max-width`ブレークポイント設定）。
   - フラットデザインのボタン・カード・フォームのスタイルを定義する。
4. `public/js/app.js` を作成する。
   - Service Workerの登録処理。
   - Push通知許可取得・`api/push_subscribe.php` へのサブスクリプション送信処理。
   - イベント作成・更新画面での走者追加・削除のDOM操作。
   - イベント開始/終了日時の自動入力ボタンの動作。

**Relevant Context**
- `images/icon-192.png`, `images/icon-512.png`, `images/apple-touch-icon.png`
- 技術仕様「スマートフォンのPWAに対応」「スマートフォンの通知に対応」

**Status:** [ ] pending

---

### ST-03. ログイン・OTP・ユーザー登録画面の作成

**Intent**
認証フロー（ログイン画面 → OTP画面 → 初期情報入力画面）を実装する。

**Expected Outcomes**
- `public/login.php` が作成され、メールアドレス入力・OTP送信・レート制限が動作する。
- `public/otp.php` が作成され、OTP入力・検証・ログイン状態設定が動作する。
- `public/init.php` が作成され、ニックネーム入力・ユーザー登録が動作する。
- `public/user.php` が作成され、ニックネーム更新が動作する。

**Todo List**
1. `public/login.php` を作成する。
   - GET: メールアドレス入力フォームを表示する。
   - POST: メールアドレスをバリデーション（空白チェック、`@`が1つ、使用不可文字チェック）。
   - POST: `check_rate_limit($ip)` を呼び、制限に引っかかる場合はエラー表示。
   - POST: `generate_otp()` でOTPを生成し、`create_otp_token($email, $token)` でDBへ保存。
   - POST: `record_rate_limit($ip)` でレート制限ログを記録。
   - POST: 外部インターフェース仕様のシェルスクリプト (`send_onetimepassword_for_relay.sh`) を `exec()` で呼び出しメール送信。
   - POST: OTP画面 (`/otp`) にリダイレクト。セッションにメールアドレスを保存。
2. `public/otp.php` を作成する。
   - GET: OTP入力フォームを表示する。セッションにメールアドレスがない場合はログイン画面へリダイレクト。
   - POST: 入力チェック（空白チェック、英数字のみ）。
   - POST: `verify_otp($email, $input_token)` を呼び、成功/失敗/失効を判定してエラー表示。
   - POST 成功時: `users` テーブルでメールアドレスを検索。
     - 未登録の場合: UUID生成し、users テーブルへ INSERT（nickname は仮値 ''、またはNULLは許容しないため最初は空でINSERT）。初期情報入力画面へリダイレクト。
     - 登録済で nickname が未設定の場合: 初期情報入力画面へリダイレクト。
     - 登録済で nickname 設定済の場合: セッション前の遷移元URLへリダイレクト（なければ検索画面）。
   - `login_user($user_id)` でセッション・Cookie設定。
3. `public/init.php` を作成する。
   - GET: ニックネーム入力フォームを表示する。未ログインの場合はログイン画面へリダイレクト。
   - POST: バリデーション（空白チェック、128文字以内）。
   - POST: `users` テーブルの nickname を UPDATE する。
   - POST: 遷移元URLへリダイレクト（なければ検索画面）。
4. `public/user.php` を作成する。
   - GET: ニックネーム入力フォームを現在値で表示する。未ログインの場合はログイン画面へリダイレクト。
   - POST: バリデーション（空白チェック、128文字以内）。
   - POST: `users` テーブルの nickname を UPDATE する。
   - POST: ポータル画面へリダイレクト。

**Relevant Context**
- `sql/users.sql`, `sql/otp_tokens.sql`, `sql/otp_rate_limits.sql`
- 機能仕様「ログイン」「ユーザー登録」
- 画面仕様 SC001, SC002, SC003, SC004
- 外部インターフェース仕様「メール送信シェル」

**Status:** [ ] pending

---

### ST-04. 検索画面・ポータル画面の作成

**Intent**
イベント一覧を表示する検索画面とポータル画面を実装する。ページネーション・ソート機能を含む。

**Expected Outcomes**
- `public/index.php` が作成され、イベント検索・ページネーション・ソートが動作する。
- `public/portal.php` が作成され、自分のイベント・フォローイベントの一覧表示・ページネーション・ソートが動作する。

**Todo List**
1. `public/index.php` を作成する。
   - GET: 検索ワードが空なら全件、あれば `events.name`, `events.summary`, `users.nickname` で検索（FULLTEXT + LIKE JOINで対応）。
   - ステータスが `published` のイベントのみ表示する。
   - ページネーション: 1ページ20件、`?page=n` で切り替え。
   - ソート: `?sort=start_at|name|nickname`、デフォルトは `start_at`。
   - ログイン状態に応じてヘッダーの「ログインボタン」と「ポータルボタン」を切り替える。
   - SQLインジェクション対策: プリペアドステートメントを使用する。
2. `public/portal.php` を作成する。
   - `require_login()` でログイン必須とする。
   - 自分が起案者であるイベント一覧（ステータス問わず）を上部に表示する。
   - フォローしているイベント一覧（statusがpublishedのもの）を下部に表示する。ページネーション・ソート付き。
   - ヘッダーに「検索ボタン」「イベント作成ボタン」を表示する。

**Relevant Context**
- `sql/events.sql`, `sql/users.sql`, `sql/event_follows.sql`
- 機能仕様「イベント検索」
- 画面仕様 SC010, SC020

**Status:** [ ] pending

---

### ST-05. イベント詳細画面・APIの作成

**Intent**
イベント詳細画面（リアルタイムポーリング）と、それを支えるAPI群を実装する。フォロー/フォロー解除・走者ステータス更新・プッシュ通知送信を含む。

**Expected Outcomes**
- `public/event.php` が作成され、イベント詳細・走者一覧がテーマ適用で表示される。1秒ポーリングで走者ステータスがリアルタイム更新される。
- `public/api/event.php` が作成され、JSON形式でイベント詳細データを返す。
- `public/api/follow.php` が作成され、フォロー/フォロー解除が動作する。
- `public/api/runner_status.php` が作成され、走者ステータス更新・プッシュ通知送信が動作する。
- `public/api/push_subscribe.php` が作成され、Web Pushサブスクリプション情報を登録できる。

**Todo List**
1. `public/api/event.php` を作成する。
   - GET `?id=xxxx`: `events` テーブルと `event_runners` テーブルをJOINし、JSON形式でレスポンスを返す。
   - `event_runners` は `start_at` 昇順で取得する。
2. `public/event.php` を作成する。
   - `?id=xxxx` パラメータでイベントIDを受け取る。
   - PHPでイベント初期データをDBから取得し、HTML描画する（初回表示）。
   - イベントのテーマカラー（`bgcolor`, `fontcolor`, `headercolor`, `bordercolor`）をCSS変数としてインラインで出力する。
   - JSで1秒ごとに `api/event.php?id=xxxx` をfetchし、走者ステータスを更新する。
   - ヘッダーにフォローボタン・フォロー解除ボタン・ログインボタンを条件に応じて表示する。
   - イベント起案者の場合のみ走者ステータス更新ボタン（SC034ダイアログトリガー）を表示する。
   - SC034（走者ステータス更新ダイアログ）を同一ファイル内にHTML/JSで実装する。
3. `public/api/follow.php` を作成する。
   - POST: `action=follow|unfollow`, `event_id=xxxx`。
   - `require_login()` で認証必須。
   - フォロー: `event_follows` テーブルへINSERT（重複はUNIQUE制約で弾く）。
   - フォロー解除: `event_follows` テーブルからDELETE。
   - JSONで成功/失敗を返す。
4. `public/api/runner_status.php` を作成する。
   - POST: `runner_id`, `status` (before/running/finished), `profile_url`, `stream_url`。
   - `require_login()` で認証必須。イベント起案者のみ実行可能とする。
   - `event_runners` テーブルを UPDATE する。
   - status が `running` に変更された場合: フォロー者のサブスクリプション情報を取得し、`send_push()` で通知送信。通知失敗時はサブスクリプションのstatusをNGに更新。
   - JSONで成功/失敗を返す。
5. `public/api/push_subscribe.php` を作成する。
   - POST: JSON body (`endpoint`, `p256dh`, `auth`)。
   - `require_login()` で認証必須。
   - `push_subscriptions` テーブルへINSERT or UPDATE（同一ユーザーのendpointは上書き）する。
   - JSONで成功/失敗を返す。

**Relevant Context**
- `sql/events.sql`, `sql/event_runners.sql`, `sql/event_follows.sql`, `sql/push_subscriptions.sql`
- 機能仕様「イベント走者開始通知送信」「イベント情報フォロー」「イベント情報フォロー解除」
- 画面仕様 SC030, SC034
- 技術仕様「イベント詳細画面のみAPIコールを継続して、リアルタイム情報を取得する」

**Status:** [ ] pending

---

### ST-06. イベント作成・更新・削除画面の作成

**Intent**
イベント情報の登録（作成→プレビュー→公開）・更新・削除フローを実装する。

**Expected Outcomes**
- `public/create.php` が作成され、イベント作成・プレビュー遷移が動作する。
- `public/preview.php` が作成され、プレビュー表示・公開・編集戻りが動作する。
- `public/edit.php` が作成され、イベント情報更新・削除が動作する。

**Todo List**
1. `public/create.php` を作成する。
   - `require_login()` で認証必須。
   - 機能仕様「イベント情報登録」の詳細条件：自分が起案者でステータスが作成中または終了日時が未来のイベントが規定数(3)以上ある場合、エラーを出して検索画面へリダイレクト。
   - GET: イベント作成フォームを表示する（テーマ選択・バナー画像・走者情報入力エリア）。
   - POST: バリデーション（イベント名必須、走者名必須、画像はPNG/JPGのみ）。
   - POST: UUID生成し `events` テーブルへ INSERT (status='preview')。走者情報を `event_runners` テーブルへ INSERT。
   - バナー画像・走者画像のアップロード処理（`public/uploads/` 等に保存）。
   - POST 成功後: プレビュー画面 (`/preview?id=xxxx`) へリダイレクト。
2. `public/preview.php` を作成する。
   - `require_login()` で認証必須。イベント起案者のみアクセス可（それ以外は検索画面へリダイレクト）。
   - GET: `event.php` と同様のレイアウトでイベント詳細を表示する。ただしリアルタイムポーリングなし。
   - 「編集」ボタン: 作成画面 (`/create`) ではなく更新画面 (`/edit?id=xxxx`) へ遷移。
   - 「公開」ボタン: POST で `events.status` を `published` に UPDATE し、ポータル画面へリダイレクト。
3. `public/edit.php` を作成する。
   - `require_login()` で認証必須。イベント起案者のみアクセス可（それ以外は検索画面へリダイレクト）。
   - GET: 既存データを読み込み、フォームに設定済みの値を表示する。テーマ選択UIなし（仕様SC033）。
   - POST (更新): バリデーション後、`events` テーブルと `event_runners` テーブルを UPDATE する。
   - POST (公開): `events.status` を `published` に UPDATE する。
   - POST (削除): `events` テーブルから DELETE（CASCADE で event_runners, event_follows も削除）し、ポータル画面へリダイレクト。
   - 「キャンセル」ボタン: ポータル画面へ遷移。
   - 走者の追加・削除はJSで動的にフォームを操作する。削除済みの走者はhidden fieldで管理し、POST時にDELETEする。

**Relevant Context**
- `sql/events.sql`, `sql/event_runners.sql`
- 機能仕様「イベント情報登録」「イベント情報更新」「イベント情報削除」「イベント詳細テーマ変更」
- 画面仕様 SC031, SC032, SC033
- URL仕様「/create」「/preview?id=xxxx」「/edit?id=xxxx」

**Status:** [ ] pending

---

## 実装時の横断的な注意事項

- **SQLインジェクション対策**: すべてのDB操作はPDOプリペアドステートメントで行う。
- **XSS対策**: HTMLへの出力は `htmlspecialchars()` を使用する。
- **CSRF対策**: POST処理には同一セッション由来のトークンチェックを加える（セッション内にCSRFトークンを保持）。
- **ファイルアップロード**: `MIME_TYPE` チェック・ファイル名サニタイズを行う。PNG/JPGのみ許容。
- **セッション**: `session_start()` は全ページで統一して `auth.php` 経由で呼び出す。
- **画像パス**: アップロード画像は `public/uploads/` に保存し、DBにはパスを格納する。
- **テーマ**: `events.bgcolor`, `fontcolor`, `headercolor`, `bordercolor` をCSS変数として `<style>` タグでインライン出力。テーマ名（enum値）はDBに保存しない。作成画面ではプリセットテーマ（6種類）の色コードをJS側で選択肢として提示し、選択するとカラーコードが各フォームに設定される形とする。
- **ポーリング**: `event.php` のJSポーリングは `fetch` APIで実装し、エラー時も中断しない。
