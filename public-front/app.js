'use strict';

const API_URL = window.MARKETPLACE_API_URL;
const PER_PAGE = 12;

const $ = (id) => document.getElementById(id);
let searchDebounce = null;
let state = { page: 1, totalPages: 1 };

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function renderEntry(entry) {
  const card = document.createElement('a');
  card.className = 'entry-card';
  card.href = `detail.html?id=${encodeURIComponent(entry.id)}`;
  card.innerHTML = `
    <div class="entry-card-head">
      <h2>${escapeHtml(entry.name)}</h2>
      <span class="entry-type">${escapeHtml(entry.itemType)}</span>
    </div>
    ${entry.description ? `<p class="entry-description">${escapeHtml(entry.description)}</p>` : ''}
    <div class="entry-card-foot">
      <span>${escapeHtml(entry.publisherName || 'Éditeur inconnu')}${entry.category ? ' · ' + escapeHtml(entry.category) : ''}</span>
      <span>${entry.downloadCount} téléchargement(s)</span>
    </div>
  `;
  return card;
}

async function loadFacets() {
  try {
    const res = await fetch(`${API_URL}/catalog/facets`);
    if (!res.ok) return;
    const { categories, itemTypes } = await res.json();

    const categorySelect = $('category-filter');
    for (const c of categories) {
      const opt = document.createElement('option');
      opt.value = c;
      opt.textContent = c;
      categorySelect.appendChild(opt);
    }

    const typeSelect = $('type-filter');
    for (const t of itemTypes) {
      const opt = document.createElement('option');
      opt.value = t;
      opt.textContent = t;
      typeSelect.appendChild(opt);
    }
  } catch {
    // les filtres restent utilisables sans facettes (juste non pré-remplis) — pas bloquant
  }
}

async function loadCatalog(page = 1) {
  const errorEl = $('error');
  const loadingEl = $('loading');
  const emptyEl = $('empty');
  const entriesEl = $('entries');
  const paginationEl = $('pagination');

  errorEl.hidden = true;
  emptyEl.hidden = true;
  paginationEl.hidden = true;
  loadingEl.hidden = false;
  entriesEl.innerHTML = '';

  const search = $('search-input').value.trim();
  const category = $('category-filter').value;
  const itemType = $('type-filter').value;

  try {
    const url = new URL(`${API_URL}/catalog`);
    if (search) url.searchParams.set('search', search);
    if (category) url.searchParams.set('category', category);
    if (itemType) url.searchParams.set('itemType', itemType);
    url.searchParams.set('page', String(page));
    url.searchParams.set('perPage', String(PER_PAGE));

    const res = await fetch(url);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const result = await res.json();

    loadingEl.hidden = true;
    state.page = result.page;
    state.totalPages = result.totalPages;

    if (result.items.length === 0) {
      emptyEl.hidden = false;
      return;
    }

    for (const entry of result.items) {
      entriesEl.appendChild(renderEntry(entry));
    }

    if (result.totalPages > 1) {
      $('page-info').textContent = `Page ${result.page} sur ${result.totalPages} (${result.total} item(s))`;
      $('prev-page').disabled = result.page <= 1;
      $('next-page').disabled = result.page >= result.totalPages;
      paginationEl.hidden = false;
    }
  } catch (err) {
    loadingEl.hidden = true;
    errorEl.textContent = `Impossible de joindre le marketplace (${err.message}). Le backend tourne-t-il sur ${API_URL} ?`;
    errorEl.hidden = false;
  }
}

$('search-input').addEventListener('input', () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => loadCatalog(1), 300);
});
$('category-filter').addEventListener('change', () => loadCatalog(1));
$('type-filter').addEventListener('change', () => loadCatalog(1));
$('prev-page').addEventListener('click', () => loadCatalog(state.page - 1));
$('next-page').addEventListener('click', () => loadCatalog(state.page + 1));

loadFacets();
loadCatalog();
