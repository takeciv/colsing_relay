<?php
/**
 * ファイルアップロードヘルパー
 */

require_once __DIR__ . '/config.php';

/**
 * アップロードされた画像ファイルを保存する。
 * 許可するMIME: image/png, image/jpeg
 *
 * @param array  $file    $_FILES の1エントリ（['tmp_name', 'type', 'error', ...]）
 * @param string $prefix  保存ファイル名のプレフィックス
 * @return string|false  成功時は公開URLパス、失敗時は false
 */
function save_uploaded_image(array $file, string $prefix = 'img'): string|false
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // MIMEタイプ検証（finfo で実際のファイル内容を確認）
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
        return false;
    }

    $ext  = $mime === 'image/png' ? 'png' : 'jpg';
    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = UPLOAD_DIR . $name;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return false;
    }

    return UPLOAD_URL_PREFIX . $name;
}

/**
 * 走者画像をアップロードし、event_runners テーブルの image_path を更新する。
 *
 * $_FILES['runners'] は以下の構造を想定:
 *   $_FILES['runners']['tmp_name'][$idx]
 *   $_FILES['runners']['error'][$idx]
 *
 * @param string $event_id
 * @param array  $runners_post $_POST['runners'] の配列
 * @param \PDO   $db
 */
function save_runner_images(string $event_id, array $runners_post, \PDO $db): void
{
    if (empty($_FILES['runners'])) {
        return;
    }

    // event_runners を取得（INSERT順と対応）
    $stmt = $db->prepare(
        'SELECT id FROM event_runners WHERE event_id = ? ORDER BY id ASC'
    );
    $stmt->execute([$event_id]);
    $runner_rows = $stmt->fetchAll();

    foreach ($runners_post as $idx => $r) {
        $tmp   = $_FILES['runners']['tmp_name'][$idx]['image'] ?? null;
        $error = $_FILES['runners']['error'][$idx]['image']    ?? UPLOAD_ERR_NO_FILE;

        if (!$tmp || $error !== UPLOAD_ERR_OK) {
            continue;
        }

        $fake_file = [
            'tmp_name' => $tmp,
            'error'    => $error,
            'type'     => $_FILES['runners']['type'][$idx]['image'] ?? '',
        ];

        $path = save_uploaded_image($fake_file, 'runner');
        if ($path === false) {
            continue;
        }

        $runner_id = $runner_rows[$idx]['id'] ?? null;
        if ($runner_id === null) continue;

        $upd = $db->prepare('UPDATE event_runners SET image_path = ? WHERE id = ?');
        $upd->execute([$path, $runner_id]);
    }
}
