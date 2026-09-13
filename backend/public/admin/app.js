'use strict';

// UI d'admin servie directement par le backend (backend/public/admin/) — même
// origine que l'API, donc pas de config d'URL ni de souci CORS : tout part
// en relatif vers /api/...
const API = '/api';
const STATUS_LABELS = { pending: 'En attente', approved: 'Approuvé', rejected: 'Rejeté' };

const PER_PAGE = 15;

let token = localStorage.getItem('marketplace_admin_token');
let currentUser = null;
let currentTab = 'pending';
let openEntryId = null;
let catalogPage = 1;
let catalogTotalPages = 1;

const $ = (id) => document.getElementById(id);

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  const hadToken = Boolean(token);
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(`${API}${path}`, { ...options, headers });
  const data = await res.json().catch(() => ({}));

  // Un 401 ne veut dire "session expirée" que si on envoyait déjà un jeton —
  // sinon (ex. mauvais mot de passe sur /auth/login, appel anonyme) c'est une
  // erreur normale de la requête, pas une déconnexion à déclencher.
  if (res.status === 401 && hadToken) {
    logout();
    throw new Error('Session expirée, reconnectez-vous.');
  }

  if (!res.ok) throw new Error(data.message || `Erreur HTTP ${res.status}`);

  return data;
}

function setToken(rawToken) {
  token = rawToken;
  localStorage.setItem('marketplace_admin_token', rawToken);
}

function logout() {
  token = null;
  currentUser = null;
  localStorage.removeItem('marketplace_admin_token');
  render();
}

// --- Auth ---------------------------------------------------------------

$('login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const errorEl = $('login-error');
  errorEl.hidden = true;

  try {
    const email = $('login-email').value.trim();
    const password = $('login-password').value;
    const result = await api('/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) });
    setToken(result.token);
    currentUser = result.user;
    $('login-form').reset();
    await render();
  } catch (err) {
    errorEl.textContent = err.message;
    errorEl.hidden = false;
  }
});

$('logout-btn').addEventListener('click', async () => {
  try {
    await api('/auth/logout', { method: 'POST' });
  } catch {
    // le jeton est peut-être déjà invalide côté serveur — on déconnecte localement quand même
  }
  logout();
});

// --- Tabs -----------------------------------------------------------------

document.querySelectorAll('.tab').forEach((btn) => {
  btn.addEventListener('click', () => {
    currentTab = btn.dataset.tab;
    openEntryId = null;
    catalogPage = 1;
    document.querySelectorAll('.tab').forEach((b) => b.classList.toggle('active', b === btn));
    $('catalog-panel').hidden = currentTab === 'users';
    $('users-panel').hidden = currentTab !== 'users';
    if (currentTab === 'users') loadUsers();
    else loadCatalog();
  });
});

$('prev-catalog-page').addEventListener('click', () => {
  catalogPage -= 1;
  loadCatalog();
});
$('next-catalog-page').addEventListener('click', () => {
  catalogPage += 1;
  loadCatalog();
});

// --- Catalog moderation -----------------------------------------------------

async function loadCatalog() {
  const list = $('catalog-list');
  const empty = $('catalog-empty');
  const pagination = $('catalog-pagination');
  list.innerHTML = '';
  empty.hidden = true;
  pagination.hidden = true;

  let result;
  try {
    result = await api(`/admin/catalog?status=${currentTab}&page=${catalogPage}&perPage=${PER_PAGE}`);
  } catch (err) {
    list.innerHTML = `<p class="error">${escapeHtml(err.message)}</p>`;
    return;
  }

  catalogTotalPages = result.totalPages;

  if (result.items.length === 0) {
    empty.hidden = false;
    return;
  }

  for (const entry of result.items) {
    list.appendChild(renderEntryCard(entry));
  }

  if (result.totalPages > 1) {
    $('catalog-page-info').textContent = `Page ${result.page} sur ${result.totalPages} (${result.total} item(s))`;
    $('prev-catalog-page').disabled = result.page <= 1;
    $('next-catalog-page').disabled = result.page >= result.totalPages;
    pagination.hidden = false;
  }
}

function renderEntryCard(entry) {
  const card = document.createElement('div');
  card.className = 'entry-card';

  const head = document.createElement('div');
  head.className = 'entry-head';
  head.innerHTML = `
    <div>
      <div class="entry-title">${escapeHtml(entry.name)} <span class="badge badge-${entry.status}">${STATUS_LABELS[entry.status]}</span></div>
      <div class="entry-meta">${escapeHtml(entry.code)} · ${escapeHtml(entry.itemType)} · ${escapeHtml(entry.publisherName || 'éditeur inconnu')}${entry.category ? ' · ' + escapeHtml(entry.category) : ''}</div>
    </div>
  `;
  head.addEventListener('click', () => toggleDetail(entry.id, card));
  card.appendChild(head);

  if (openEntryId === entry.id) {
    const detail = document.createElement('div');
    detail.className = 'entry-detail';
    detail.innerHTML = '<p class="hint">Chargement…</p>';
    card.appendChild(detail);
    loadDetail(entry, detail);
  }

  return card;
}

function toggleDetail(id, card) {
  openEntryId = openEntryId === id ? null : id;
  card.querySelector('.entry-detail')?.remove();
  if (openEntryId === id) {
    const detail = document.createElement('div');
    detail.className = 'entry-detail';
    detail.innerHTML = '<p class="hint">Chargement…</p>';
    card.appendChild(detail);
    api(`/admin/catalog/${id}`).then((full) => renderDetail(full, detail)).catch((err) => {
      detail.innerHTML = `<p class="error">${escapeHtml(err.message)}</p>`;
    });
  }
}

async function loadDetail(entry, detail) {
  try {
    const full = await api(`/admin/catalog/${entry.id}`);
    renderDetail(full, detail);
  } catch (err) {
    detail.innerHTML = `<p class="error">${escapeHtml(err.message)}</p>`;
  }
}

function renderDetail(entry, detail) {
  const latest = entry.versions[0];

  detail.innerHTML = '';

  if (entry.description) {
    const p = document.createElement('p');
    p.className = 'hint';
    p.textContent = entry.description;
    detail.appendChild(p);
  }

  if (entry.status === 'rejected' && entry.rejectionReason) {
    const p = document.createElement('p');
    p.className = 'error';
    p.textContent = `Motif du rejet : ${entry.rejectionReason}`;
    detail.appendChild(p);
  }

  if (entry.reviewedByName) {
    const p = document.createElement('p');
    p.className = 'hint';
    p.textContent = `Revu par ${entry.reviewedByName} le ${formatDate(entry.reviewedAt)}`;
    detail.appendChild(p);
  }

  const pre = document.createElement('pre');
  pre.textContent = latest ? JSON.stringify(latest.content, null, 2) : '(aucune version)';
  detail.appendChild(pre);

  if (entry.versions.length > 1) {
    const versionsWrap = document.createElement('div');
    versionsWrap.innerHTML = '<p class="hint">Historique des versions</p>';
    for (const v of entry.versions) {
      const row = document.createElement('div');
      row.className = 'version-item';
      row.textContent = `v${v.versionNumber} (${v.version}) — ${formatDate(v.createdAt)}${v.notes ? ' — ' + v.notes : ''}`;
      versionsWrap.appendChild(row);
    }
    detail.appendChild(versionsWrap);
  }

  const actions = document.createElement('div');
  actions.className = 'entry-actions';

  if (entry.status !== 'approved') {
    const approveBtn = document.createElement('button');
    approveBtn.className = 'btn btn-approve btn-sm';
    approveBtn.textContent = 'Approuver';
    approveBtn.addEventListener('click', async () => {
      approveBtn.disabled = true;
      try {
        await api(`/admin/catalog/${entry.id}/approve`, { method: 'POST' });
        openEntryId = null;
        loadCatalog();
      } catch (err) {
        alert(err.message);
        approveBtn.disabled = false;
      }
    });
    actions.appendChild(approveBtn);
  }

  if (entry.status !== 'rejected') {
    const rejectBtn = document.createElement('button');
    rejectBtn.className = 'btn btn-reject btn-sm';
    rejectBtn.textContent = 'Rejeter';
    rejectBtn.addEventListener('click', () => rejectForm.classList.toggle('open'));
    actions.appendChild(rejectBtn);
  }

  detail.appendChild(actions);

  const rejectForm = document.createElement('div');
  rejectForm.className = 'reject-form';
  rejectForm.innerHTML = `
    <textarea placeholder="Motif du rejet (optionnel)"></textarea>
    <button class="btn btn-reject btn-sm">Confirmer le rejet</button>
  `;
  rejectForm.querySelector('button').addEventListener('click', async () => {
    const reason = rejectForm.querySelector('textarea').value.trim() || null;
    try {
      await api(`/admin/catalog/${entry.id}/reject`, { method: 'POST', body: JSON.stringify({ reason }) });
      openEntryId = null;
      loadCatalog();
    } catch (err) {
      alert(err.message);
    }
  });
  detail.appendChild(rejectForm);
}

// --- User management (admin only) -----------------------------------------

async function loadUsers() {
  const list = $('users-list');
  list.innerHTML = '<p class="hint">Chargement…</p>';
  try {
    const users = await api('/admin/users');
    list.innerHTML = '';
    for (const u of users) {
      const row = document.createElement('div');
      row.className = 'user-row';
      row.innerHTML = `<span>${escapeHtml(u.name)} — ${escapeHtml(u.email)}</span><span class="role-tag">${escapeHtml(u.role)}</span>`;
      list.appendChild(row);
    }
  } catch (err) {
    list.innerHTML = `<p class="error">${escapeHtml(err.message)}</p>`;
  }
}

$('create-user-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const errorEl = $('create-user-error');
  const successEl = $('create-user-success');
  errorEl.hidden = true;
  successEl.hidden = true;

  try {
    await api('/admin/users', {
      method: 'POST',
      body: JSON.stringify({
        name: $('new-user-name').value.trim(),
        email: $('new-user-email').value.trim(),
        password: $('new-user-password').value,
        role: $('new-user-role').value,
      }),
    });
    $('create-user-form').reset();
    successEl.textContent = 'Compte créé.';
    successEl.hidden = false;
    loadUsers();
  } catch (err) {
    errorEl.textContent = err.message;
    errorEl.hidden = false;
  }
});

// --- Bootstrap --------------------------------------------------------------

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function formatDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
}

async function render() {
  if (!token) {
    $('login-view').hidden = false;
    $('app-view').hidden = true;
    $('user-bar').hidden = true;
    return;
  }

  if (!currentUser) {
    try {
      currentUser = await api('/auth/me');
    } catch {
      return; // api() already logged out and re-rendered on 401
    }
  }

  $('login-view').hidden = true;
  $('app-view').hidden = false;
  $('user-bar').hidden = false;
  $('user-info').textContent = `${currentUser.name} (${currentUser.role})`;
  $('users-tab').hidden = currentUser.role !== 'admin';

  if (currentTab === 'users') loadUsers();
  else loadCatalog();
}

render();
