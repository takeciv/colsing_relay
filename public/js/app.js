/**
 * app.js — 共通JavaScript
 * - Service Worker 登録
 * - Web Push 通知許可取得 & サブスクリプション送信
 * - イベント作成・更新フォームの走者ブロック動的追加・削除
 * - 開始・終了日時の自動入力ボタン
 * - テーマプリセットセレクター
 */

/* ── Service Worker 登録 ─────────────────────────────── */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/js/sw.js').catch(() => {});
  });
}

/* ── Web Push 通知許可 & サブスクリプション ─────────── */
/**
 * VAPID公開鍵はPHPから <meta name="vapid-public-key" content="..."> で埋め込む。
 */
async function subscribePush() {
  if (!('PushManager' in window)) return;
  const metaKey = document.querySelector('meta[name="vapid-public-key"]');
  if (!metaKey) return;
  const publicKey = metaKey.getAttribute('content');
  if (!publicKey) return;

  try {
    const sw = await navigator.serviceWorker.ready;
    let sub = await sw.pushManager.getSubscription();
    if (!sub) {
      sub = await sw.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey),
      });
    }
    // サーバーへ送信
    await fetch('/api/push_subscribe.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        endpoint: sub.endpoint,
        p256dh:   arrayBufferToBase64url(sub.getKey('p256dh')),
        auth:     arrayBufferToBase64url(sub.getKey('auth')),
      }),
    });
  } catch (e) {
    // 通知許可が拒否された場合などは無視
  }
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

function arrayBufferToBase64url(buf) {
  if (!buf) return '';
  return btoa(String.fromCharCode(...new Uint8Array(buf)))
    .replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

/* ── 走者入力ブロックの動的追加・削除 ──────────────── */
let runnerIndex = 0;

function initRunnerForm() {
  const container = document.getElementById('runners-container');
  if (!container) return;

  // 既存ブロック数をカウント
  runnerIndex = container.querySelectorAll('.runner-input-block').length;

  document.getElementById('add-runner-btn')?.addEventListener('click', () => {
    addRunnerBlock(container);
  });
}

function addRunnerBlock(container, data = {}) {
  const idx = runnerIndex++;
  const block = document.createElement('div');
  block.className = 'runner-input-block';
  block.dataset.idx = idx;
  block.innerHTML = runnerBlockHTML(idx, data);
  container.appendChild(block);
  bindRunnerBlockEvents(block);
}

function runnerBlockHTML(idx, data = {}) {
  const v = (key) => escapeHtml(data[key] || '');
  return `
    <div class="runner-input-block__header">
      <span>走者 #${idx + 1}</span>
      <button type="button" class="btn btn-danger btn-sm remove-runner-btn">削除</button>
    </div>
    ${data.id ? `<input type="hidden" name="runners[${idx}][id]" value="${v('id')}">` : ''}
    <div class="form-group">
      <label class="form-label">走者名 <span style="color:var(--color-danger)">*</span></label>
      <input type="text" name="runners[${idx}][name]" class="form-control" value="${v('name')}" required>
    </div>
    <div class="form-group">
      <label class="form-label">走者画像（PNG/JPG）</label>
      <input type="file" name="runners[${idx}][image]" class="form-control" accept="image/png,image/jpeg">
      ${data.image_path ? `<img src="${v('image_path')}" style="width:64px;height:64px;margin-top:0.4rem;border-radius:50%;">` : ''}
    </div>
    <div class="form-group">
      <label class="form-label">走者概要</label>
      <textarea name="runners[${idx}][summary]" class="form-control">${v('summary')}</textarea>
    </div>
    <div class="form-group">
      <label class="form-label">プロフィールURL</label>
      <input type="url" name="runners[${idx}][profile_url]" class="form-control" value="${v('profile_url')}">
    </div>
    <div class="form-group">
      <label class="form-label">配信枠URL</label>
      <input type="url" name="runners[${idx}][stream_url]" class="form-control" value="${v('stream_url')}">
    </div>
    <div class="form-group">
      <label class="form-label">開始日時</label>
      <input type="datetime-local" name="runners[${idx}][start_at]" class="form-control runner-start" value="${v('start_at_input')}">
    </div>
    <div class="form-group">
      <label class="form-label">終了日時</label>
      <input type="datetime-local" name="runners[${idx}][end_at]" class="form-control runner-end" value="${v('end_at_input')}">
    </div>
  `;
}

function bindRunnerBlockEvents(block) {
  block.querySelector('.remove-runner-btn')?.addEventListener('click', () => {
    const idInput = block.querySelector('input[name$="[id]"]');
    if (idInput) {
      // 既存走者の削除マーク
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'delete_runner_ids[]';
      hidden.value = idInput.value;
      document.getElementById('runner-form')?.appendChild(hidden);
    }
    block.remove();
    updateRunnerLabels();
  });
}

function updateRunnerLabels() {
  document.querySelectorAll('.runner-input-block').forEach((block, i) => {
    const header = block.querySelector('.runner-input-block__header span');
    if (header) header.textContent = `走者 #${i + 1}`;
  });
}

/* ── 開始・終了日時の自動入力（トグル式） ──────────── */
let autoStartEnabled = false;
let autoEndEnabled   = false;

function setAutoStartState(enabled) {
  autoStartEnabled = enabled;
  const btn   = document.getElementById('auto-start-btn');
  const input = document.getElementById('event-start-at');
  if (!btn || !input) return;
  if (enabled) {
    btn.classList.add('btn-primary');
    btn.classList.remove('btn-secondary');
    btn.textContent = '自動入力 ON';
    input.disabled = true;
    // 即時反映
    const values = [...document.querySelectorAll('.runner-start')]
      .map((el) => el.value).filter(Boolean).sort();
    if (values.length) input.value = values[0];
  } else {
    btn.classList.add('btn-secondary');
    btn.classList.remove('btn-primary');
    btn.textContent = '自動入力 OFF';
    input.disabled = false;
  }
}

function setAutoEndState(enabled) {
  autoEndEnabled = enabled;
  const btn   = document.getElementById('auto-end-btn');
  const input = document.getElementById('event-end-at');
  if (!btn || !input) return;
  if (enabled) {
    btn.classList.add('btn-primary');
    btn.classList.remove('btn-secondary');
    btn.textContent = '自動入力 ON';
    input.disabled = true;
    // 即時反映
    const values = [...document.querySelectorAll('.runner-end')]
      .map((el) => el.value).filter(Boolean).sort();
    if (values.length) input.value = values[values.length - 1];
  } else {
    btn.classList.add('btn-secondary');
    btn.classList.remove('btn-primary');
    btn.textContent = '自動入力 OFF';
    input.disabled = false;
  }
}

function triggerAutoDate() {
  if (autoStartEnabled) {
    const values = [...document.querySelectorAll('.runner-start')]
      .map((el) => el.value).filter(Boolean).sort();
    const input = document.getElementById('event-start-at');
    if (values.length && input) input.value = values[0];
  }
  if (autoEndEnabled) {
    const values = [...document.querySelectorAll('.runner-end')]
      .map((el) => el.value).filter(Boolean).sort();
    const input = document.getElementById('event-end-at');
    if (values.length && input) input.value = values[values.length - 1];
  }
}

function initAutoDateButtons() {
  document.getElementById('auto-start-btn')?.addEventListener('click', () => {
    setAutoStartState(!autoStartEnabled);
  });

  document.getElementById('auto-end-btn')?.addEventListener('click', () => {
    setAutoEndState(!autoEndEnabled);
  });

  // 走者の日時変更を監視して自動反映
  document.getElementById('runners-container')?.addEventListener('change', (e) => {
    if (e.target.classList.contains('runner-start') || e.target.classList.contains('runner-end')) {
      triggerAutoDate();
    }
  });

  // 初期状態はOFF
  setAutoStartState(false);
  setAutoEndState(false);
}

/* ── テーマプリセットセレクター ─────────────────────── */
const THEME_PRESETS = {
  default:    { label: 'デフォルト', bgcolor: '#f5f5f5', fontcolor: '#212121', headercolor: '#4a90e2', bordercolor: '#e0e0e0', cardcolor: '#ffffff', buttoncolor: '#4a90e2', runbgcolor: '#ffffff' },
  chic:       { label: 'シック',     bgcolor: '#1a1a2e', fontcolor: '#e0e0e0', headercolor: '#16213e', bordercolor: '#0f3460', cardcolor: '#16213e', buttoncolor: '#e94560', runbgcolor: '#16213e' },
  cute:       { label: 'キュート',   bgcolor: '#fff0f6', fontcolor: '#5c0035', headercolor: '#ff6fa8', bordercolor: '#ffb3d1', cardcolor: '#ffffff', buttoncolor: '#ff6fa8', runbgcolor: '#ffe0ef' },
  gorgeous:   { label: 'ゴージャス', bgcolor: '#1a0a00', fontcolor: '#f5e6c8', headercolor: '#8b6914', bordercolor: '#c8a84b', cardcolor: '#2a1a00', buttoncolor: '#c8a84b', runbgcolor: '#2a1a00' },
  dark:       { label: 'ダーク',     bgcolor: '#121212', fontcolor: '#e0e0e0', headercolor: '#1e1e1e', bordercolor: '#333333', cardcolor: '#1e1e1e', buttoncolor: '#5ba4f5', runbgcolor: '#1e1e1e' },
  brightness: { label: '明るい',     bgcolor: '#fffde7', fontcolor: '#212121', headercolor: '#fdd835', bordercolor: '#f9a825', cardcolor: '#ffffff', buttoncolor: '#f9a825', runbgcolor: '#ffffff' },
};

const COLOR_FIELDS = ['bgcolor', 'fontcolor', 'headercolor', 'bordercolor', 'runbgcolor'];

function updateColorInputLabels() {
  COLOR_FIELDS.forEach((key) => {
    const input = document.querySelector(`input[name="${key}"]`);
    if (!input) return;
    let label = input.parentElement.querySelector('.color-code-label');
    if (!label) {
      label = document.createElement('span');
      label.className = 'color-code-label';
      label.style.cssText = 'display:block;font-size:0.78rem;color:var(--color-muted);margin-top:0.2rem;';
      input.parentElement.appendChild(label);
    }
    label.textContent = input.value.toUpperCase();
    input.style.outline = `3px solid ${input.value}`;
  });
}

function initThemeSelector() {
  const container = document.getElementById('theme-presets');
  if (!container) return;

  // 色コードラベルを初期化
  updateColorInputLabels();
  COLOR_FIELDS.forEach((key) => {
    document.querySelector(`input[name="${key}"]`)?.addEventListener('input', updateColorInputLabels);
  });

  Object.entries(THEME_PRESETS).forEach(([key, colors]) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'theme-preset-btn';
    btn.title = colors.label;
    btn.style.background = colors.headercolor;
    btn.dataset.theme = key;

    const labelEl = document.createElement('span');
    labelEl.className = 'theme-preset-btn__label';
    labelEl.textContent = colors.label;
    btn.appendChild(labelEl);

    btn.addEventListener('click', () => {
      applyThemePreset(colors);
      container.querySelectorAll('.theme-preset-btn').forEach((b) => b.classList.remove('selected'));
      btn.classList.add('selected');
    });
    container.appendChild(btn);
  });
}

function applyThemePreset(colors) {
  COLOR_FIELDS.forEach((key) => {
    const el = document.querySelector(`input[name="${key}"]`);
    if (el) el.value = colors[key];
  });
  updateColorInputLabels();
}

/* ── XSS用エスケープ ─────────────────────────────────── */
function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/* ── DOMContentLoaded ────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initRunnerForm();
  initAutoDateButtons();
  initThemeSelector();

  // ログイン済みページでは通知購読を試みる
  if (document.body.dataset.loggedIn === '1') {
    Notification.requestPermission().then((perm) => {
      if (perm === 'granted') subscribePush();
    });
  }
});
