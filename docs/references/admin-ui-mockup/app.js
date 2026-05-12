/* =============================================================
   app.js — 分身AI Admin UI (static prototype)
   API layer: real backend fetch client
   ============================================================= */

// ================================================================
// 1. API LAYER
// ================================================================
const API_CONFIG_STORAGE_KEY = 'bunshin-admin-api-config';
const DEFAULT_API_BASE = '/api/v1';
const LOCAL_DEV_TOKEN = 'local-dev-token';

class ApiClientError extends Error {
  constructor(message, { status = null, errors = null, payload = null } = {}) {
    super(message);
    this.name = 'ApiClientError';
    this.status = status;
    this.errors = errors;
    this.payload = payload;
  }
}

function normalizeApiBase(value) {
  const base = String(value || DEFAULT_API_BASE).trim() || DEFAULT_API_BASE;
  return base.replace(/\/+$/, '');
}

function loadApiConfig() {
  try {
    const saved = JSON.parse(localStorage.getItem(API_CONFIG_STORAGE_KEY) || '{}');
    return {
      baseUrl: normalizeApiBase(saved.baseUrl),
      token: defaultLocalDevToken(typeof saved.token === 'string' ? saved.token.trim() : ''),
    };
  } catch {
    return { baseUrl: DEFAULT_API_BASE, token: defaultLocalDevToken('') };
  }
}

function defaultLocalDevToken(savedToken) {
  if (!isLocalDevHost()) return savedToken;

  if (!savedToken || savedToken.includes('|')) return LOCAL_DEV_TOKEN;

  return savedToken;
}

function isLocalDevHost() {
  return ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
}

const apiState = loadApiConfig();

function saveApiConfig() {
  localStorage.setItem(API_CONFIG_STORAGE_KEY, JSON.stringify(apiState));
}

function setApiConfig({ baseUrl, token }) {
  apiState.baseUrl = normalizeApiBase(baseUrl);
  apiState.token = String(token || '').trim();
  saveApiConfig();
}

function buildQuery(params = {}) {
  const qs = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') qs.set(key, value);
  });
  const query = qs.toString();
  return query ? `?${query}` : '';
}

async function requestApi(path, { method = 'GET', params = {}, body = null, auth = true } = {}) {
  const headers = new Headers({ Accept: 'application/json' });
  if (auth && apiState.token) headers.set('Authorization', `Bearer ${apiState.token}`);
  if (body !== null) headers.set('Content-Type', 'application/json');

  let response;
  try {
    response = await fetch(`${apiState.baseUrl}${path}${buildQuery(params)}`, {
      method,
      headers,
      body: body === null ? null : JSON.stringify(body),
    });
  } catch (error) {
    throw new ApiClientError(error.message || 'API に接続できませんでした。', { payload: error });
  }

  const contentType = response.headers.get('content-type') || '';
  const payload = response.status === 204
    ? null
    : contentType.includes('application/json')
      ? await response.json().catch(() => null)
      : await response.text().catch(() => null);

  if (!response.ok) {
    const message = payload?.message
      || (response.status === 401 ? '認証に失敗しました。Bearer token を確認してください。' : `API request failed (${response.status})`);

    throw new ApiClientError(message, {
      status: response.status,
      errors: payload?.errors || null,
      payload,
    });
  }

  return payload;
}

function responseData(payload) {
  return payload && Object.prototype.hasOwnProperty.call(payload, 'data') ? payload.data : payload;
}

function memoryPayload(payload) {
  return {
    title: payload.title || null,
    body: payload.body,
    period_key: payload.period_key || null,
    occurred_on: payload.occurred_on || null,
    emotion_label: payload.emotion_label || null,
    emotion_intensity: Number.isNaN(Number(payload.emotion_intensity)) ? null : Number(payload.emotion_intensity),
    category_id: payload.category_id || payload.category?.id || null,
    visibility: payload.visibility,
    tags: payload.tags || [],
  };
}

const api = {
  async listMemories(params = {}) {
    return responseData(await requestApi('/memories', { params }));
  },

  async getMemory(id) {
    return responseData(await requestApi(`/memories/${encodeURIComponent(id)}`));
  },

  async createMemory(payload) {
    return responseData(await requestApi('/memories', { method: 'POST', body: memoryPayload(payload) }));
  },

  async updateMemory(id, payload) {
    return responseData(await requestApi(`/memories/${encodeURIComponent(id)}`, {
      method: 'PATCH',
      body: memoryPayload(payload),
    }));
  },

  async deleteMemory(id) {
    await requestApi(`/memories/${encodeURIComponent(id)}`, { method: 'DELETE' });
    return true;
  },

  async listCategories() {
    return responseData(await requestApi('/categories'));
  },

  async getCategory(id) {
    return responseData(await requestApi(`/categories/${encodeURIComponent(id)}`));
  },

  async createCategory(payload) {
    return responseData(await requestApi('/categories', { method: 'POST', body: payload }));
  },

  async updateCategory(id, payload) {
    return responseData(await requestApi(`/categories/${encodeURIComponent(id)}`, { method: 'PATCH', body: payload }));
  },

  async deleteCategory(id) {
    await requestApi(`/categories/${encodeURIComponent(id)}`, { method: 'DELETE' });
    return true;
  },

  async listTags() {
    return responseData(await requestApi('/tags'));
  },

  async getHealth() {
    return requestApi('/health', { auth: false });
  },
};

// ================================================================
// 3. HELPERS
// ================================================================
const periodLabels = {
  childhood: '幼少期', elementary_school: '小学校', junior_high: '中学校',
  high_school: '高校', university: '大学', adult: '社会人',
};

function periodLabel(k) { return periodLabels[k] || k; }

function isRootCategory(category) {
  return category.parent_id === null || category.parent_id === undefined || category.parent_id === '';
}

function categoryParentMap(categories) {
  return new Map(categories.map(category => [String(category.id), category]));
}

function categoryParentLabel(category, categories) {
  if (isRootCategory(category)) return '—';

  const parent = categoryParentMap(categories).get(String(category.parent_id));
  return parent ? parent.name : `#${category.parent_id}`;
}

function categoryDisplayName(category, categories) {
  const parentLabel = categoryParentLabel(category, categories);
  return parentLabel === '—' ? category.name : `${parentLabel} / ${category.name}`;
}

function relativeTime(iso) {
  const d = new Date(iso), now = new Date();
  const diff = (now - d) / 1000;
  if (diff < 60)    return 'たった今';
  if (diff < 3600)  return `${Math.floor(diff / 60)}分前`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}時間前`;
  return `${Math.floor(diff / 86400)}日前`;
}

function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

let toastTimer;
function showToast(msg, type = '') {
  let area = document.getElementById('toast-area');
  if (!area) { area = Object.assign(document.createElement('div'), { id: 'toast-area' }); document.body.appendChild(area); }
  const t = document.createElement('div');
  t.className = `toast${type ? ' toast--' + type : ''}`;
  t.textContent = msg;
  area.appendChild(t);
  setTimeout(() => t.remove(), 2800);
}

function apiErrorDetails(error) {
  if (!error?.errors) return '';

  return Object.entries(error.errors)
    .flatMap(([field, messages]) => (Array.isArray(messages) ? messages : [messages])
      .map(message => `${field}: ${message}`))
    .join('\n');
}

function showApiError(error) {
  const status = error?.status ? `${error.status} ` : '';
  const detail = apiErrorDetails(error).split('\n').filter(Boolean)[0];
  showToast(`${status}${error?.message || 'API error'}${detail ? `: ${detail}` : ''}`, 'danger');
}

function renderApiError(el, error) {
  const detail = apiErrorDetails(error);
  const status = error?.status ? `HTTP ${error.status}` : 'Network error';

  el.innerHTML = `
    <div class="state-box state-box--error">
      <div class="state-box__title">${esc(status)}: ${esc(error?.message || 'API に接続できませんでした')}</div>
      ${detail ? `<pre class="state-box__pre">${esc(detail)}</pre>` : ''}
      <div class="state-box__sub">Settings で API Base URL と Bearer token を確認してください</div>
      <button class="btn btn--ghost btn--sm" id="open-settings-from-error">Settings を開く</button>
    </div>
  `;

  document.getElementById('open-settings-from-error')?.addEventListener('click', () => navigate('settings'));
}

function updateApiStatusBadge(status, label = null) {
  const badge = document.getElementById('api-status-badge');
  if (!badge) return;

  const statusClass = status === 'ok' ? 'ok' : status === 'warn' ? 'warn' : 'error';
  badge.innerHTML = `<span class="status-dot status-dot--${statusClass}"></span> ${esc(label || `API ${String(status).toUpperCase()}`)}`;
}

// ================================================================
// 4. PAGE ROUTER
// ================================================================
const pageTitles = {
  dashboard: 'Dashboard',
  memories:  'Memories',
  secret:    'Secret Memories',
  categories: 'Categories',
  tags:       'Tags',
  health:     'API Health',
  settings:   'Settings',
};

let currentPage = 'dashboard';

function navigate(page) {
  currentPage = page;
  document.getElementById('page-title').textContent = pageTitles[page] || page;
  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.page === page);
  });
  renderPage(page);
}

async function renderPage(page) {
  const el = document.getElementById('content');
  el.innerHTML = '<div class="state-box"><div class="state-box__title">読み込み中…</div></div>';
  try {
    switch (page) {
      case 'dashboard':   return await renderDashboard(el);
      case 'memories':    return await renderMemories(el);
      case 'secret':      return await renderSecret(el);
      case 'categories':  return await renderCategories(el);
      case 'tags':        return await renderTags(el);
      case 'health':      return await renderHealth(el);
      case 'settings':    return renderSettings(el);
    }
  } catch (error) {
    updateApiStatusBadge('error', error?.status === 401 ? 'API 401' : 'API ERROR');
    renderApiError(el, error);
  }
}

// ================================================================
// 5. DASHBOARD
// ================================================================
async function renderDashboard(el) {
  const [memories, secrets, categories, tags, health] = await Promise.all([
    api.listMemories(),
    api.listMemories({ visibility: 'secret' }),
    api.listCategories(),
    api.listTags(),
    api.getHealth(),
  ]);

  const recent = [...memories].sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at)).slice(0, 5);
  const statusClass = health.status === 'ok' ? 'ok' : health.status === 'warn' ? 'warn' : 'error';

  updateApiStatusBadge(health.status);

  el.innerHTML = `
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card__label">総記憶数</div>
        <div class="stat-card__value">${memories.length + secrets.length}</div>
        <div class="stat-card__sub">通常 + secret 合計</div>
      </div>
      <div class="stat-card">
        <div class="stat-card__label">通常記憶</div>
        <div class="stat-card__value">${memories.length}</div>
        <div class="stat-card__sub">private / shared</div>
      </div>
      <div class="stat-card stat-card--secret">
        <div class="stat-card__label">Secret</div>
        <div class="stat-card__value">${secrets.length}</div>
        <div class="stat-card__sub">秘匿記憶</div>
      </div>
      <div class="stat-card">
        <div class="stat-card__label">カテゴリ</div>
        <div class="stat-card__value">${categories.filter(c => !c.archived).length}</div>
        <div class="stat-card__sub">アーカイブ除く</div>
      </div>
      <div class="stat-card">
        <div class="stat-card__label">タグ</div>
        <div class="stat-card__value">${tags.length}</div>
        <div class="stat-card__sub">表記ゆれ含む</div>
      </div>
      <div class="stat-card">
        <div class="stat-card__label">API Status</div>
        <div class="stat-card__value" style="font-size:16px">${health.status.toUpperCase()}</div>
        <div class="stat-card__sub">v${health.version}</div>
      </div>
    </div>

    <div class="dash-grid">
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">直近の更新</span>
          <button class="btn btn--ghost btn--sm" onclick="navigate('memories')">すべて表示</button>
        </div>
        <div class="panel-body">
          ${recent.map(m => `
            <div class="memory-list-item" onclick="openMemoryDetail('${m.id}')">
              <div class="memory-list-item__body">
                <div class="memory-list-item__title">${esc(m.title)}</div>
                <div class="memory-list-item__meta">
                  <span>${periodLabel(m.period_key)}</span>
                  <span>·</span>
                  <span>${m.emotion_label}</span>
                  <span>·</span>
                  <span>${relativeTime(m.updated_at)}</span>
                </div>
              </div>
              <div class="memory-list-item__badge">
                <span class="badge badge--${m.visibility}">${m.visibility}</span>
              </div>
            </div>
          `).join('')}
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">カテゴリ分布</span>
        </div>
        <div class="panel-body" style="padding:16px">
          ${categories.filter(c => !c.archived).map(c => `
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
              <span style="font-size:12px;color:var(--text-secondary);min-width:80px">${esc(c.name)}</span>
              <div style="flex:1;background:var(--surface-2);border-radius:2px;height:6px;overflow:hidden">
                <div style="height:100%;background:var(--accent);width:${Math.min(100, (c.memory_count / 30) * 100)}%;border-radius:2px"></div>
              </div>
              <span style="font-size:11px;color:var(--text-muted);min-width:24px;text-align:right">${c.memory_count}</span>
            </div>
          `).join('')}
        </div>
      </div>
    </div>
  `;
}

// ================================================================
// 6. MEMORIES
// ================================================================
let memoryFilters = { q: '', period_key: '', category_id: '' };
let memoryCurrent = null;
let deleteTarget = null;

async function renderMemories(el) {
  const [memories, categories] = await Promise.all([api.listMemories(memoryFilters), api.listCategories()]);

  el.innerHTML = `
    <div class="section-header">
      <h2>Memories <span style="font-size:12px;font-weight:400;color:var(--text-muted)">(${memories.length}件)</span></h2>
      <button class="btn btn--primary" id="mem-create-btn">
        <svg viewBox="0 0 16 16" fill="none"><path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        新規作成
      </button>
    </div>

    <div class="toolbar">
      <div class="search-wrap">
        <svg viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="4" stroke="currentColor" stroke-width="1.5"/><path d="M11 11l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        <input type="text" class="search-input" id="mem-search" placeholder="タイトル・本文を検索" value="${esc(memoryFilters.q)}">
      </div>
      <select class="filter-select" id="mem-period">
        <option value="">時期: すべて</option>
        ${Object.entries(periodLabels).map(([v, l]) => `<option value="${v}" ${memoryFilters.period_key === v ? 'selected' : ''}>${l}</option>`).join('')}
      </select>
      <select class="filter-select" id="mem-category">
        <option value="">カテゴリ: すべて</option>
        ${categories.filter(c => !c.archived).map(c => `<option value="${c.id}" ${String(memoryFilters.category_id) === String(c.id) ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}
      </select>
    </div>

    ${memories.length === 0 ? `
      <div class="state-box">
        <svg viewBox="0 0 24 24" fill="none"><path d="M9 12h6M9 16h6M7 4H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2M7 4a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2M7 4h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        <div class="state-box__title">記憶データがありません</div>
        <div class="state-box__sub">フィルターを変更するか、新規作成してください</div>
      </div>
    ` : `
    <div class="table-wrap">
      <table>
        <colgroup>
          <col style="width:28%"><col style="width:12%"><col style="width:10%">
          <col style="width:10%"><col style="width:12%"><col style="width:14%"><col style="width:100px">
        </colgroup>
        <thead>
          <tr>
            <th>タイトル</th>
            <th>時期</th>
            <th>感情</th>
            <th>強度</th>
            <th>カテゴリ</th>
            <th>公開範囲</th>
            <th style="text-align:right">操作</th>
          </tr>
        </thead>
        <tbody>
          ${memories.map(m => `
            <tr data-id="${m.id}">
              <td class="td-title">${esc(m.title)}</td>
              <td><span class="period-label">${periodLabel(m.period_key)}</span></td>
              <td><span class="emotion-badge">${esc(m.emotion_label)}</span></td>
              <td style="color:var(--text-muted)">${m.emotion_intensity}/5</td>
              <td style="color:var(--text-secondary)">${esc(m.category?.name || '—')}</td>
              <td><span class="badge badge--${m.visibility}">${m.visibility}</span></td>
              <td>
                <div class="td-actions">
                  <button class="btn-icon mem-edit-btn" data-id="${m.id}" aria-label="編集" title="編集">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M11 2l3 3-9 9H2v-3L11 2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
                  </button>
                  <button class="btn-icon mem-delete-btn" data-id="${m.id}" aria-label="削除" title="削除" style="color:var(--danger)">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M3 4h10M6 4V2h4v2M5 4v8a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
    `}
  `;

  // Bind events
  document.getElementById('mem-search').addEventListener('input', e => { memoryFilters.q = e.target.value; renderMemories(el); });
  document.getElementById('mem-period').addEventListener('change', e => { memoryFilters.period_key = e.target.value; renderMemories(el); });
  document.getElementById('mem-category').addEventListener('change', e => { memoryFilters.category_id = e.target.value; renderMemories(el); });
  document.getElementById('mem-create-btn').addEventListener('click', () => openMemoryModal(null));

  el.querySelectorAll('tbody tr').forEach(row => {
    row.addEventListener('click', e => {
      if (e.target.closest('.mem-edit-btn') || e.target.closest('.mem-delete-btn')) return;
      openMemoryDetail(row.dataset.id);
    });
  });

  el.querySelectorAll('.mem-edit-btn').forEach(btn => {
    btn.addEventListener('click', e => { e.stopPropagation(); openMemoryModal(btn.dataset.id); });
  });

  el.querySelectorAll('.mem-delete-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const m = memories.find(x => x.id === btn.dataset.id);
      confirmDelete(m);
    });
  });
}

// ================================================================
// 7. SECRET MEMORIES
// ================================================================
let secretUnlocked = false;

async function renderSecret(el) {
  if (!secretUnlocked) {
    el.innerHTML = `
      <div class="secret-gate">
        <svg class="secret-gate__icon" viewBox="0 0 24 24" fill="none">
          <rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <div class="secret-gate__title">Secret Memories</div>
        <div class="secret-gate__desc">
          このエリアには秘匿性の高い記憶が含まれています。<br>
          閲覧するには明示的な確認が必要です。
        </div>
        <button class="btn btn--ghost" id="secret-unlock-btn" style="margin:0 auto">
          <svg viewBox="0 0 16 16" fill="none"><rect x="2" y="7" width="12" height="8" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 7V5a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
          閲覧する
        </button>
      </div>
    `;
    document.getElementById('secret-unlock-btn').addEventListener('click', () => {
      secretUnlocked = true;
      renderSecret(el);
    });
    return;
  }

  const secrets = await api.listMemories({ visibility: 'secret' });

  el.innerHTML = `
    <div class="section-header">
      <h2>Secret Memories <span style="font-size:12px;font-weight:400;color:var(--text-muted)">(${secrets.length}件)</span></h2>
      <button class="btn btn--ghost btn--sm" id="secret-lock-btn">
        <svg viewBox="0 0 16 16" fill="none"><rect x="2" y="7" width="12" height="8" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 7V5a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
        閲覧を終了
      </button>
    </div>

    <div class="secret-context-banner">
      <svg viewBox="0 0 16 16" fill="none"><rect x="2" y="7" width="12" height="8" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 7V5a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
      Secret 記憶を表示中です。取り扱いにご注意ください。
    </div>

    ${secrets.length === 0 ? `
      <div class="state-box">
        <div class="state-box__title">Secret 記憶はありません</div>
      </div>
    ` : `
    <div class="table-wrap" style="border-color:var(--secret-border)">
      <table>
        <colgroup>
          <col style="width:30%"><col style="width:14%"><col style="width:12%"><col style="width:12%"><col style="width:16%"><col style="width:100px">
        </colgroup>
        <thead>
          <tr>
            <th>タイトル</th>
            <th>時期</th>
            <th>感情</th>
            <th>強度</th>
            <th>カテゴリ</th>
            <th style="text-align:right">操作</th>
          </tr>
        </thead>
        <tbody>
          ${secrets.map(m => `
            <tr data-id="${m.id}">
              <td class="td-title">${esc(m.title)}</td>
              <td><span class="period-label">${periodLabel(m.period_key)}</span></td>
              <td><span class="emotion-badge">${esc(m.emotion_label)}</span></td>
              <td style="color:var(--text-muted)">${m.emotion_intensity}/5</td>
              <td style="color:var(--text-secondary)">${esc(m.category?.name || '—')}</td>
              <td>
                <div class="td-actions">
                  <button class="btn-icon sec-delete-btn" data-id="${m.id}" aria-label="削除" title="削除" style="color:var(--danger)">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M3 4h10M6 4V2h4v2M5 4v8a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
    `}
  `;

  document.getElementById('secret-lock-btn').addEventListener('click', () => {
    secretUnlocked = false;
    renderSecret(el);
  });

  el.querySelectorAll('tbody tr').forEach(row => {
    row.addEventListener('click', e => {
      if (e.target.closest('.sec-delete-btn')) return;
      openMemoryDetail(row.dataset.id);
    });
  });

  el.querySelectorAll('.sec-delete-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const m = secrets.find(x => x.id === btn.dataset.id);
      confirmDelete(m);
    });
  });
}

// ================================================================
// 8. CATEGORIES
// ================================================================
async function renderCategories(el) {
  const categories = await api.listCategories();

  el.innerHTML = `
    <div class="section-header">
      <h2>Categories</h2>
      <button class="btn btn--primary" id="cat-create-btn">
        <svg viewBox="0 0 16 16" fill="none"><path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        新規作成
      </button>
    </div>

    <div class="table-wrap">
      <table>
        <colgroup>
          <col style="width:24%"><col style="width:17%"><col style="width:18%"><col style="width:8%"><col style="width:10%"><col style="width:12%"><col style="width:100px">
        </colgroup>
        <thead>
          <tr>
            <th>カテゴリ名</th>
            <th>スラッグ</th>
            <th>親カテゴリ</th>
            <th>順序</th>
            <th>記憶数</th>
            <th>状態</th>
            <th style="text-align:right">操作</th>
          </tr>
        </thead>
        <tbody>
          ${categories.map(c => `
            <tr>
              <td class="td-title">
                ${isRootCategory(c) ? '' : '<span style="color:var(--text-muted);font-weight:400">└ </span>'}${esc(c.name)}
              </td>
              <td style="font-family:var(--font-mono);font-size:11.5px;color:var(--text-muted)">${esc(c.slug)}</td>
              <td style="color:var(--text-secondary)">${esc(categoryParentLabel(c, categories))}</td>
              <td style="color:var(--text-muted)">${c.sort_order}</td>
              <td style="color:var(--text-secondary)">${c.memory_count}</td>
              <td>
                <span class="badge ${isRootCategory(c) ? 'badge--neutral' : 'badge--warn'}">${isRootCategory(c) ? 'root' : 'child'}</span>
                <span class="badge ${c.archived ? 'badge--neutral' : 'badge--ok'}">${c.archived ? 'archived' : 'active'}</span>
              </td>
              <td>
                <div class="td-actions">
                  <button class="btn-icon cat-edit-btn" data-id="${c.id}" aria-label="編集" title="編集">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M11 2l3 3-9 9H2v-3L11 2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
                  </button>
                  <button class="btn-icon cat-delete-btn" data-id="${c.id}" aria-label="削除" title="削除" style="color:var(--danger)">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M3 4h10M6 4V2h4v2M5 4v8a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;

  document.getElementById('cat-create-btn').addEventListener('click', () => openCategoryModal(null));
  el.querySelectorAll('.cat-edit-btn').forEach(btn => {
    btn.addEventListener('click', () => openCategoryModal(btn.dataset.id));
  });
  el.querySelectorAll('.cat-delete-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('このカテゴリを削除しますか？紐づく記憶は未分類になります。')) return;
      try {
        await api.deleteCategory(btn.dataset.id);
        showToast('カテゴリを削除しました', 'success');
        renderCategories(el);
      } catch (error) {
        showApiError(error);
      }
    });
  });
}

// ================================================================
// 9. TAGS
// ================================================================
async function renderTags(el) {
  const tags = await api.listTags();
  const hasVariants = tags.filter(t => t.name !== t.normalized_name);

  el.innerHTML = `
    <div class="section-header">
      <h2>Tags</h2>
      <button class="btn btn--ghost" id="merge-open-btn">
        <svg viewBox="0 0 16 16" fill="none"><path d="M4 4h3l5 8h-3M4 12h3l5-8h-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        タグをマージ
      </button>
    </div>

    ${hasVariants.length > 0 ? `
    <div style="background:var(--warning-bg);border:1px solid #fcd34d;border-radius:var(--r);padding:10px 14px;font-size:12px;color:var(--warning);margin-bottom:14px;display:flex;align-items:center;gap:8px">
      <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M8 6v4M8 12v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M2 13L8 2l6 11H2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
      ${hasVariants.length}件の表記ゆれが検出されました。マージ機能で統合できます。
    </div>
    ` : ''}

    <div class="table-wrap">
      <table>
        <colgroup>
          <col style="width:26%"><col style="width:30%"><col style="width:16%"><col style="width:100px">
        </colgroup>
        <thead>
          <tr>
            <th>タグ名</th>
            <th>正規化名</th>
            <th>使用数</th>
            <th style="text-align:right">操作</th>
          </tr>
        </thead>
        <tbody>
          ${tags.map(t => `
            <tr>
              <td class="td-title">${esc(t.name)}</td>
              <td>
                ${t.name !== t.normalized_name
                  ? `<span style="color:var(--warning)">${esc(t.normalized_name)}</span> <span style="font-size:10.5px;color:var(--warning);background:var(--warning-bg);border:1px solid #fcd34d;padding:1px 5px;border-radius:3px">ゆれ</span>`
                  : `<span style="color:var(--text-muted)">${esc(t.normalized_name)}</span>`
                }
              </td>
              <td style="color:var(--text-secondary)">${t.usage_count}</td>
              <td>
                <div class="td-actions">
                  <button class="btn-icon" aria-label="削除" title="削除" style="color:var(--danger)">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M3 4h10M6 4V2h4v2M5 4v8a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;

  document.getElementById('merge-open-btn').addEventListener('click', openMergeModal);
}

// ================================================================
// 10. API HEALTH
// ================================================================
async function renderHealth(el) {
  let health, fetchError = null;
  try {
    health = await api.getHealth();
  } catch (e) {
    fetchError = e;
    health = { service: '—', status: 'error', version: '—' };
  }

  const sc = health.status === 'ok' ? 'ok' : health.status === 'warn' ? 'warn' : 'error';
  const statusLabel = { ok: '正常', warn: '警告', error: 'エラー' }[sc] || health.status;
  updateApiStatusBadge(sc);

  el.innerHTML = `
    <div class="section-header">
      <h2>API Health</h2>
      <button class="btn btn--ghost btn--sm" id="health-refresh">再確認</button>
    </div>

    <div class="health-grid">
      <div class="health-card health-card--${sc}">
        <div class="health-card__label">Status</div>
        <div class="health-card__value">${statusLabel}</div>
      </div>
      <div class="health-card">
        <div class="health-card__label">Service</div>
        <div class="health-card__value">${esc(health.service)}</div>
      </div>
      <div class="health-card">
        <div class="health-card__label">Version</div>
        <div class="health-card__value">${esc(health.version)}</div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <span class="panel-title">Raw Response — GET /api/v1/health</span>
      </div>
      <div style="padding:16px">
        <div class="raw-json">${JSON.stringify(fetchError ? { error: fetchError.message } : health, null, 2)}</div>
      </div>
    </div>
  `;

  document.getElementById('health-refresh').addEventListener('click', () => renderHealth(el));
}

// ================================================================
// 11. SETTINGS
// ================================================================
function renderSettings(el) {
  el.innerHTML = `
    <div class="section-header">
      <h2>Settings</h2>
    </div>

    <div class="settings-section">
      <div class="settings-section-title">API 設定</div>
      <div class="settings-row">
        <div>
          <div class="settings-row__label">API Base URL</div>
          <div class="settings-row__hint">バックエンド API のエンドポイント</div>
        </div>
        <input type="text" id="settings-api-base" class="input settings-input" value="${esc(apiState.baseUrl)}">
      </div>
      <div class="settings-row">
        <div>
          <div class="settings-row__label">Bearer Token</div>
          <div class="settings-row__hint">protected API routes に送る token</div>
        </div>
        <input type="password" id="settings-api-token" class="input settings-input" value="${esc(apiState.token)}" autocomplete="off">
      </div>
      <div class="settings-row">
        <div>
          <div class="settings-row__label">API Client</div>
          <div class="settings-row__hint">mock data は使用せず real API に接続</div>
        </div>
        <span class="badge badge--ok">REAL API</span>
      </div>
      <div class="settings-row">
        <div>
          <div class="settings-row__label">保存</div>
          <div class="settings-row__hint">このブラウザの localStorage に保持</div>
        </div>
        <div class="settings-actions">
          <button class="btn btn--ghost btn--sm" id="settings-token-clear">Token クリア</button>
          <button class="btn btn--primary btn--sm" id="settings-api-save">保存</button>
        </div>
      </div>
    </div>

    <div class="settings-section">
      <div class="settings-section-title">テナント / ユーザー</div>
      <div class="settings-row">
        <div>
          <div class="settings-row__label">Tenant</div>
        </div>
        <span class="settings-row__value">tenant_default</span>
      </div>
      <div class="settings-row">
        <div>
          <div class="settings-row__label">Owner User</div>
        </div>
        <span class="settings-row__value">admin</span>
      </div>
    </div>

    <div class="settings-section">
      <div class="settings-section-title">未実装の機能 (Placeholder)</div>
      <div class="settings-row">
        <div>
          <div class="settings-row__label">APIキー管理</div>
          <div class="settings-row__hint">API 認証キーの発行・失効</div>
        </div>
        <span class="disabled-pill">未実装</span>
      </div>
      <div class="settings-row">
        <div>
          <div class="settings-row__label">Webhook 設定</div>
          <div class="settings-row__hint">外部サービスへの通知</div>
        </div>
        <span class="disabled-pill">未実装</span>
      </div>
      <div class="settings-row">
        <div>
          <div class="settings-row__label">データエクスポート</div>
          <div class="settings-row__hint">記憶データの一括エクスポート</div>
        </div>
        <span class="disabled-pill">未実装</span>
      </div>
      <div class="settings-row">
        <div>
          <div class="settings-row__label">テナント切り替え</div>
          <div class="settings-row__hint">複数テナントの管理</div>
        </div>
        <span class="disabled-pill">未実装</span>
      </div>
    </div>
  `;

  document.getElementById('settings-api-save').addEventListener('click', () => {
    setApiConfig({
      baseUrl: document.getElementById('settings-api-base').value,
      token: document.getElementById('settings-api-token').value,
    });
    showToast('API 設定を保存しました', 'success');
    updateApiStatusBadge('warn', apiState.token ? 'API TOKEN SET' : 'API TOKEN EMPTY');
  });

  document.getElementById('settings-token-clear').addEventListener('click', () => {
    document.getElementById('settings-api-token').value = '';
    setApiConfig({
      baseUrl: document.getElementById('settings-api-base').value,
      token: '',
    });
    showToast('Token をクリアしました');
    updateApiStatusBadge('warn', 'API TOKEN EMPTY');
  });
}

// ================================================================
// 12. MEMORY DETAIL DRAWER
// ================================================================
async function openMemoryDetail(id) {
  let m;
  try {
    m = await api.getMemory(id);
  } catch (error) {
    showApiError(error);
    return;
  }
  if (!m) return;
  memoryCurrent = m;

  document.getElementById('drawer-title').textContent = m.visibility === 'secret' ? '🔒 ' + m.title : m.title;
  document.getElementById('drawer-body').innerHTML = `
    <div class="detail-field">
      <div class="detail-field__label">ID</div>
      <div class="detail-field__value" style="font-family:var(--font-mono);font-size:11.5px;color:var(--text-muted)">${esc(m.id)}</div>
    </div>
    <div class="detail-field">
      <div class="detail-field__label">本文</div>
      <div class="detail-field__value detail-field__value--body">${esc(m.body)}</div>
    </div>
    <div class="detail-divider"></div>
    <div class="detail-field">
      <div class="detail-field__label">時期</div>
      <div class="detail-field__value">${periodLabel(m.period_key)}</div>
    </div>
    <div class="detail-field">
      <div class="detail-field__label">発生日</div>
      <div class="detail-field__value">${m.occurred_on || '—'}</div>
    </div>
    <div class="detail-field">
      <div class="detail-field__label">感情</div>
      <div class="detail-field__value">${esc(m.emotion_label)} <span style="color:var(--text-muted);font-size:12px">強度 ${m.emotion_intensity}/5</span></div>
    </div>
    <div class="detail-field">
      <div class="detail-field__label">カテゴリ</div>
      <div class="detail-field__value">${esc(m.category?.name || '—')}</div>
    </div>
    <div class="detail-field">
      <div class="detail-field__label">タグ</div>
      <div class="detail-field__value">
        <div class="tags-wrap">
          ${(m.tags || []).map(t => `<span class="tag-pill">${esc(t)}</span>`).join('') || '<span style="color:var(--text-muted)">なし</span>'}
        </div>
      </div>
    </div>
    <div class="detail-field">
      <div class="detail-field__label">公開範囲</div>
      <div class="detail-field__value"><span class="badge badge--${m.visibility}">${m.visibility}</span></div>
    </div>
    <div class="detail-divider"></div>
    <div class="detail-field">
      <div class="detail-field__label">作成日時</div>
      <div class="detail-field__value" style="font-size:12px;color:var(--text-muted)">${new Date(m.created_at).toLocaleString('ja-JP')}</div>
    </div>
    <div class="detail-field">
      <div class="detail-field__label">更新日時</div>
      <div class="detail-field__value" style="font-size:12px;color:var(--text-muted)">${new Date(m.updated_at).toLocaleString('ja-JP')}</div>
    </div>
  `;

  document.getElementById('drawer-backdrop').classList.remove('hidden');
}

// ================================================================
// 13. MEMORY CREATE / EDIT MODAL
// ================================================================
async function openMemoryModal(id) {
  const title = document.getElementById('memory-modal-title');
  let m = null;

  try {
    const [memory, categories] = await Promise.all([
      id ? api.getMemory(id) : Promise.resolve(null),
      api.listCategories(),
    ]);
    m = memory;
    populateMemoryCategoryOptions(categories, m?.category?.id || '');
  } catch (error) {
    showApiError(error);
    return;
  }

  if (id) {
    title.textContent = '記憶を編集';
    if (m) {
      document.getElementById('f-title').value = m.title || '';
      document.getElementById('f-body').value = m.body || '';
      document.getElementById('f-period').value = m.period_key || '';
      document.getElementById('f-occurred').value = m.occurred_on || '';
      document.getElementById('f-emotion').value = m.emotion_label || '';
      document.getElementById('f-intensity').value = m.emotion_intensity || 3;
      document.getElementById('f-category').value = m.category?.id ? String(m.category.id) : '';
      document.getElementById('f-visibility').value = m.visibility || 'private';
      document.getElementById('f-tags').value = (m.tags || []).join(', ');
    }
  } else {
    title.textContent = '記憶を作成';
    document.getElementById('f-title').value = '';
    document.getElementById('f-body').value = '';
    document.getElementById('f-period').value = '';
    document.getElementById('f-occurred').value = '';
    document.getElementById('f-emotion').value = '';
    document.getElementById('f-intensity').value = '3';
    document.getElementById('f-category').value = '';
    document.getElementById('f-visibility').value = 'private';
    document.getElementById('f-tags').value = '';
  }

  document.getElementById('memory-modal-backdrop').classList.remove('hidden');

  const saveBtn = document.getElementById('memory-modal-save');
  const newSave = saveBtn.cloneNode(true);
  saveBtn.parentNode.replaceChild(newSave, saveBtn);

  newSave.addEventListener('click', async () => {
    const payload = {
      title: document.getElementById('f-title').value.trim(),
      body: document.getElementById('f-body').value.trim(),
      period_key: document.getElementById('f-period').value,
      occurred_on: document.getElementById('f-occurred').value,
      emotion_label: document.getElementById('f-emotion').value,
      emotion_intensity: parseInt(document.getElementById('f-intensity').value),
      category_id: document.getElementById('f-category').value || null,
      visibility: document.getElementById('f-visibility').value,
      tags: document.getElementById('f-tags').value.split(',').map(t => t.trim()).filter(Boolean),
    };
    if (!payload.title) { showToast('タイトルを入力してください', 'danger'); return; }

    try {
      if (id) {
        await api.updateMemory(id, payload);
        showToast('記憶を更新しました', 'success');
      } else {
        await api.createMemory(payload);
        showToast('記憶を作成しました', 'success');
      }
      closeModal('memory-modal-backdrop');
      if (currentPage === 'memories') renderMemories(document.getElementById('content'));
      if (currentPage === 'secret') renderSecret(document.getElementById('content'));
    } catch (error) {
      showApiError(error);
    }
  });
}

function populateMemoryCategoryOptions(categories, selectedId = '') {
  const select = document.getElementById('f-category');
  select.innerHTML = `
    <option value="">未分類</option>
    ${categories.filter(c => !c.archived).map(c => `<option value="${c.id}">${esc(categoryDisplayName(c, categories))}</option>`).join('')}
  `;
  select.value = selectedId ? String(selectedId) : '';
}

function populateCategoryParentOptions(categories, selectedId = '', excludeId = null) {
  const select = document.getElementById('cat-parent');
  const exclude = excludeId === null ? null : String(excludeId);
  const roots = categories.filter(c => !c.archived && isRootCategory(c) && String(c.id) !== exclude);

  select.innerHTML = `
    <option value="">なし（大カテゴリ）</option>
    ${roots.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('')}
  `;
  select.value = selectedId ? String(selectedId) : '';
}

// ================================================================
// 14. DELETE CONFIRM
// ================================================================
function confirmDelete(m) {
  if (!m) return;
  deleteTarget = m;
  document.getElementById('delete-confirm-target').textContent = m.title;
  document.getElementById('delete-modal-backdrop').classList.remove('hidden');
}

async function executeDelete() {
  if (!deleteTarget) return;
  try {
    await api.deleteMemory(deleteTarget.id);
    showToast('削除しました');
    closeModal('delete-modal-backdrop');
    closeDrawer();
    deleteTarget = null;
    if (currentPage === 'memories') renderMemories(document.getElementById('content'));
    if (currentPage === 'secret') renderSecret(document.getElementById('content'));
  } catch (error) {
    showApiError(error);
  }
}

// ================================================================
// 15. CATEGORY MODAL
// ================================================================
async function openCategoryModal(id) {
  document.getElementById('category-modal-title').textContent = id ? 'カテゴリを編集' : 'カテゴリを作成';
  let category = null;
  let categories = [];

  try {
    [category, categories] = await Promise.all([
      id ? api.getCategory(id) : Promise.resolve(null),
      api.listCategories(),
    ]);
  } catch (error) {
    showApiError(error);
    return;
  }

  populateCategoryParentOptions(categories, category?.parent_id || '', id);

  if (id) {
    document.getElementById('cat-name').value = category.name;
    document.getElementById('cat-slug').value = category.slug;
    document.getElementById('cat-sort').value = category.sort_order;
  } else {
    document.getElementById('cat-name').value = '';
    document.getElementById('cat-slug').value = '';
    document.getElementById('cat-parent').value = '';
    document.getElementById('cat-sort').value = '0';
  }
  document.getElementById('category-modal-backdrop').classList.remove('hidden');

  const saveBtn = document.getElementById('category-modal-save');
  const newSave = saveBtn.cloneNode(true);
  saveBtn.parentNode.replaceChild(newSave, saveBtn);

  newSave.addEventListener('click', async () => {
    const payload = {
      name: document.getElementById('cat-name').value.trim(),
      slug: document.getElementById('cat-slug').value.trim(),
      parent_id: document.getElementById('cat-parent').value || null,
      sort_order: parseInt(document.getElementById('cat-sort').value || '0'),
    };

    try {
      if (id) {
        await api.updateCategory(id, payload);
        showToast('カテゴリを更新しました', 'success');
      } else {
        await api.createCategory(payload);
        showToast('カテゴリを作成しました', 'success');
      }
      closeModal('category-modal-backdrop');
      if (currentPage === 'categories') renderCategories(document.getElementById('content'));
    } catch (error) {
      showApiError(error);
    }
  });
}

// ================================================================
// 16. MERGE MODAL
// ================================================================
async function openMergeModal() {
  let tags = [];
  try {
    tags = await api.listTags();
  } catch (error) {
    showApiError(error);
    return;
  }
  const fromSel = document.getElementById('merge-from');
  const toSel   = document.getElementById('merge-to');
  fromSel.innerHTML = tags.map(t => `<option value="${t.id}">${esc(t.name)} (${t.usage_count})</option>`).join('');
  toSel.innerHTML   = tags.map(t => `<option value="${t.id}">${esc(t.name)} (${t.usage_count})</option>`).join('');
  document.getElementById('merge-modal-backdrop').classList.remove('hidden');
}

// ================================================================
// 17. MODAL / DRAWER HELPERS
// ================================================================
function closeModal(backdropId) {
  document.getElementById(backdropId).classList.add('hidden');
}

function closeDrawer() {
  document.getElementById('drawer-backdrop').classList.add('hidden');
  memoryCurrent = null;
}

// ================================================================
// 18. GLOBAL EVENT BINDINGS
// ================================================================
function bindGlobalEvents() {
  // Nav
  document.querySelectorAll('.nav-item').forEach(el => {
    el.addEventListener('click', e => { e.preventDefault(); navigate(el.dataset.page); });
  });

  // Drawer
  document.getElementById('drawer-close').addEventListener('click', closeDrawer);
  document.getElementById('drawer-backdrop').addEventListener('click', e => {
    if (e.target === document.getElementById('drawer-backdrop')) closeDrawer();
  });
  document.getElementById('drawer-edit').addEventListener('click', () => {
    if (memoryCurrent) { closeDrawer(); openMemoryModal(memoryCurrent.id); }
  });
  document.getElementById('drawer-delete').addEventListener('click', () => {
    if (memoryCurrent) confirmDelete(memoryCurrent);
  });

  // Memory modal
  document.getElementById('memory-modal-close').addEventListener('click', () => closeModal('memory-modal-backdrop'));
  document.getElementById('memory-modal-cancel').addEventListener('click', () => closeModal('memory-modal-backdrop'));
  document.getElementById('memory-modal-backdrop').addEventListener('click', e => {
    if (e.target === document.getElementById('memory-modal-backdrop')) closeModal('memory-modal-backdrop');
  });

  // Delete modal
  document.getElementById('delete-cancel').addEventListener('click', () => closeModal('delete-modal-backdrop'));
  document.getElementById('delete-confirm').addEventListener('click', executeDelete);

  // Category modal
  document.getElementById('category-modal-close').addEventListener('click', () => closeModal('category-modal-backdrop'));
  document.getElementById('category-modal-cancel').addEventListener('click', () => closeModal('category-modal-backdrop'));

  // Merge modal
  document.getElementById('merge-modal-close').addEventListener('click', () => closeModal('merge-modal-backdrop'));
  document.getElementById('merge-modal-cancel').addEventListener('click', () => closeModal('merge-modal-backdrop'));
  document.getElementById('merge-modal-confirm').addEventListener('click', () => {
    showToast('タグマージ API は未実装です', 'danger');
    closeModal('merge-modal-backdrop');
  });

  // ESC key
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeDrawer();
      closeModal('memory-modal-backdrop');
      closeModal('delete-modal-backdrop');
      closeModal('category-modal-backdrop');
      closeModal('merge-modal-backdrop');
    }
  });
}

// ================================================================
// 19. INIT
// ================================================================
document.addEventListener('DOMContentLoaded', () => {
  bindGlobalEvents();
  navigate('dashboard');
});
