<?php
/**
 * HTMLテンプレートヘルパー
 * 全ページ共通の <head> / <header> / </body> を出力する。
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

/**
 * HTMLの <head> 〜 <body> 開始タグまでを出力する。
 *
 * @param string      $title      <title> に入るページタイトル
 * @param bool        $logged_in  ログイン済みかどうか（body[data-logged-in] 用）
 * @param string|null $extra_head <head> 内に追加するHTMLの文字列
 */
function html_head(string $title, bool $logged_in = false, ?string $extra_head = null): void
{
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $vapid = htmlspecialchars(VAPID_PUBLIC_KEY, ENT_QUOTES, 'UTF-8');
    $li = $logged_in ? '1' : '0';
    echo <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$t} - Colsing Relay</title>
  <link rel="stylesheet" href="/css/style.css">
  <link rel="manifest" href="/manifest.json">
  <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
  <meta name="vapid-public-key" content="{$vapid}">
  {$extra_head}
</head>
<body data-logged-in="{$li}">
HTML;
}

/**
 * </body></html> を出力する。
 */
function html_foot(): void
{
    echo '<script src="/js/app.js"></script>' . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";
}

/**
 * h() — HTML エスケープの省略形。
 */
function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * フラッシュメッセージをセッションにセット（次のリクエストで1回表示）。
 */
function flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * セッション上のフラッシュメッセージを取り出して出力する。
 */
function render_flash(): void
{
    start_session();
    if (empty($_SESSION['flash'])) return;
    foreach ($_SESSION['flash'] as $f) {
        echo '<div class="alert alert-' . h($f['type']) . '">' . h($f['message']) . '</div>';
    }
    unset($_SESSION['flash']);
}
