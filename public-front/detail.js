'use strict';

const API_URL = window.MARKETPLACE_API_URL;
const $ = (id) => document.getElementById(id);

const entryId = new URLSearchParams(window.location.search).get('id');

let entry = null;
let versions = [];
let currentVersion = null;

// Simulation state (template viewer only) — reset whenever the version changes.
let simCurrentStepId = null;
let simPath = [];

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function formatDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
}

// --- Bootstrap ---------------------------------------------------------

async function init() {
  if (!entryId) {
    showError('Aucun item spécifié.');
    return;
  }

  try {
    const [entryRes, versionsRes] = await Promise.all([
      fetch(`${API_URL}/catalog/${encodeURIComponent(entryId)}`),
      fetch(`${API_URL}/catalog/${encodeURIComponent(entryId)}/versions`),
    ]);
    if (!entryRes.ok) throw new Error(`HTTP ${entryRes.status}`);
    if (!versionsRes.ok) throw new Error(`HTTP ${versionsRes.status}`);

    entry = await entryRes.json();
    versions = await versionsRes.json();

    renderMeta();
    populateVersionSelect();
    selectVersion(versions[0]);

    $('loading').hidden = true;
    $('content').hidden = false;
  } catch (err) {
    showError(`Impossible de charger cet item (${err.message}).`);
  }
}

function showError(message) {
  $('loading').hidden = true;
  $('error').textContent = message;
  $('error').hidden = false;
}

function renderMeta() {
  document.title = `${entry.name} — Marketplace Kibish Approbation`;
  $('page-title').textContent = document.title;
  $('entry-name').textContent = entry.name;
  $('entry-type').textContent = entry.itemType;
  $('entry-meta').textContent = `${entry.code} · ${entry.publisherName || 'éditeur inconnu'}${entry.category ? ' · ' + entry.category : ''} · ${entry.downloadCount} téléchargement(s)`;
  $('entry-description').textContent = entry.description || '';
}

function populateVersionSelect() {
  const select = $('version-select');
  select.innerHTML = '';
  for (const v of versions) {
    const opt = document.createElement('option');
    opt.value = v.id;
    opt.textContent = `v${v.versionNumber} (${v.version}) — ${formatDate(v.createdAt)}`;
    select.appendChild(opt);
  }
  select.addEventListener('change', () => {
    selectVersion(versions.find((v) => v.id === select.value));
  });
}

function selectVersion(version) {
  currentVersion = version;
  $('version-select').value = version.id;
  $('download-status').textContent = '';
  simCurrentStepId = null;
  simPath = [];

  $('raw-json-content').textContent = JSON.stringify(version.content, null, 2);
  renderViewer();
}

// --- Download ------------------------------------------------------------

$('download-btn').addEventListener('click', async () => {
  const statusEl = $('download-status');
  statusEl.textContent = 'Téléchargement…';

  try {
    const res = await fetch(`${API_URL}/catalog/${encodeURIComponent(entryId)}/versions/${encodeURIComponent(currentVersion.id)}/download`, { method: 'POST' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();

    const blob = new Blob([JSON.stringify(data.content, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${entry.code}-v${data.version}.json`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);

    statusEl.textContent = 'Téléchargé.';
  } catch (err) {
    statusEl.textContent = `Échec (${err.message}).`;
  }
});

// --- Viewer dispatch -------------------------------------------------------

function renderViewer() {
  const viewer = $('viewer');
  viewer.innerHTML = '';

  const content = currentVersion.content;

  if (entry.itemType === 'template' && content && Array.isArray(content.steps)) {
    renderTemplateViewer(viewer, content);
  } else if (entry.itemType === 'form' && Array.isArray(content)) {
    renderFormViewer(viewer, content);
  } else if (entry.itemType === 'bundle') {
    renderBundleViewer(viewer, content);
  } else {
    // Forme inattendue pour ce itemType (le marketplace ne valide jamais le contenu) — pas de vue dédiée possible.
    const p = document.createElement('p');
    p.className = 'hint';
    p.textContent = 'Aucun aperçu structuré pour ce contenu — voir le JSON brut ci-dessous.';
    viewer.appendChild(p);
  }
}

// --- Template viewer : champs + simulation d'un parcours ---------------------

function renderTemplateViewer(viewer, content) {
  if (Array.isArray(content.form_fields) && content.form_fields.length > 0) {
    const section = document.createElement('div');
    section.className = 'section';
    section.innerHTML = '<h2>Champs du formulaire</h2>';
    const list = document.createElement('div');
    list.className = 'field-list';
    for (const f of content.form_fields) {
      const row = document.createElement('div');
      row.className = 'field-row';
      row.innerHTML = `
        <span>${escapeHtml(f.label || f.key)}${f.required ? '<span class="required-mark">*</span>' : ''}</span>
        <span class="field-type">${escapeHtml(f.type || '')}</span>
      `;
      list.appendChild(row);
    }
    section.appendChild(list);
    viewer.appendChild(section);
  }

  const section = document.createElement('div');
  section.className = 'section';
  section.innerHTML = '<h2>Simulation du parcours</h2><p class="hint">Choisissez une issue à chaque étape pour voir où mène ce template.</p>';
  const simRoot = document.createElement('div');
  section.appendChild(simRoot);
  viewer.appendChild(section);

  const stepsById = {};
  for (const s of content.steps) stepsById[s.id] = s;

  const actorsById = {};
  if (Array.isArray(content.actors)) {
    for (const a of content.actors) actorsById[a.id] = a;
  }

  // Un assignee peut référencer un acteur nommé (actor_ref -> content.actors),
  // porter une valeur directe (role/group), ou n'être qu'un type nu (requester)
  // — le moteur réel accepte les trois formes, donc l'aperçu doit aussi.
  function describeAssignee(a) {
    if (a.type === 'actor_ref' && a.ref) {
      const actor = actorsById[a.ref];
      if (actor && actor.path) return `${a.ref} (${actor.type} : ${actor.path})`;
      if (actor) return `${a.ref} (${actor.type})`;
      return a.ref;
    }
    const detail = a.value ?? a.ref ?? null;
    return detail ? `${a.type} : ${detail}` : a.type;
  }

  if (simCurrentStepId === null) {
    simCurrentStepId = content.start_step;
    simPath = [content.start_step];
  }

  function renderSim() {
    simRoot.innerHTML = '';

    const trail = document.createElement('div');
    trail.className = 'sim-trail';
    trail.innerHTML = simPath.map((id) => `<span class="step-chip">${escapeHtml(id)}</span>`).join('<span>→</span>');
    simRoot.appendChild(trail);

    const step = stepsById[simCurrentStepId];
    const card = document.createElement('div');
    card.className = 'sim-step-card' + (step && step.type === 'end' ? ' is-end' : '');

    if (!step) {
      card.innerHTML = `<p class="hint">Étape "${escapeHtml(simCurrentStepId)}" introuvable dans ce template.</p>`;
      simRoot.appendChild(card);
      return;
    }

    let html = `<span class="sim-step-id">${escapeHtml(step.id)}</span><span class="sim-step-type">${escapeHtml(step.type)}</span>`;

    if (step.type === 'end') {
      html += `<p class="sim-final-status">Résultat : ${escapeHtml(step.final_status || '—')}</p>`;
    } else {
      if (Array.isArray(step.assignees)) {
        html += `<p class="sim-kv">Assigné à : ${step.assignees.map((a) => escapeHtml(describeAssignee(a))).join(', ')}</p>`;
      }
      if (step.mode) {
        html += `<p class="sim-kv">Mode : ${escapeHtml(step.mode)}</p>`;
      }
      // Mode "hierarchical_parallel" : pas d'assignees direct sur l'étape, un
      // par niveau (levels[].assignees) — rendu dédié plutôt que le fallback JSON brut.
      if (Array.isArray(step.levels)) {
        for (const level of step.levels) {
          const who = Array.isArray(level.assignees) ? level.assignees.map(describeAssignee).join(', ') : '—';
          html += `<p class="sim-kv">Niveau ${escapeHtml(level.level ?? '')}${level.name ? ' (' + escapeHtml(level.name) + ')' : ''} : ${escapeHtml(who)}</p>`;
        }
      }
    }

    // Clés non standard (types d'étape futurs/inconnus) — affichées telles quelles, défensif.
    const known = ['id', 'type', 'assignees', 'mode', 'transitions', 'final_status', 'levels'];
    for (const [key, value] of Object.entries(step)) {
      if (known.includes(key)) continue;
      html += `<p class="sim-kv">${escapeHtml(key)} : ${escapeHtml(typeof value === 'object' ? JSON.stringify(value) : String(value))}</p>`;
    }

    card.innerHTML = html;

    if (step.transitions && typeof step.transitions === 'object') {
      const transitionsEl = document.createElement('div');
      transitionsEl.className = 'sim-transitions';
      for (const [label, targetId] of Object.entries(step.transitions)) {
        const btn = document.createElement('button');
        btn.className = 'btn-transition';
        btn.textContent = label;
        btn.addEventListener('click', () => {
          simCurrentStepId = targetId;
          simPath.push(targetId);
          renderSim();
        });
        transitionsEl.appendChild(btn);
      }
      card.appendChild(transitionsEl);
    }

    simRoot.appendChild(card);

    if (simPath.length > 1) {
      const restartBtn = document.createElement('button');
      restartBtn.className = 'btn-restart';
      restartBtn.textContent = 'Recommencer la simulation';
      restartBtn.addEventListener('click', () => {
        simCurrentStepId = content.start_step;
        simPath = [content.start_step];
        renderSim();
      });
      simRoot.appendChild(restartBtn);
    }
  }

  renderSim();
}

// --- Form viewer : aperçu rempli + JSON produit -----------------------------

function renderFormViewer(viewer, fields) {
  const section = document.createElement('div');
  section.className = 'section';
  section.innerHTML = '<h2>Aperçu du formulaire</h2><p class="hint">Remplissez-le pour voir les données qu\'il produirait.</p>';

  const form = document.createElement('form');
  form.className = 'form-preview';

  for (const [i, f] of fields.entries()) {
    const key = f.key || f.name || `field_${i}`;
    const label = document.createElement('label');
    const type = (f.type || 'text').toLowerCase();

    let inputHtml;
    if (type === 'textarea' || type === 'long_text') {
      inputHtml = `<textarea name="${escapeHtml(key)}" rows="3"></textarea>`;
    } else if ((type === 'select' || type === 'choice') && Array.isArray(f.options)) {
      const opts = f.options.map((o) => {
        const val = typeof o === 'object' ? o.value ?? o.label : o;
        const text = typeof o === 'object' ? o.label ?? o.value : o;
        return `<option value="${escapeHtml(val)}">${escapeHtml(text)}</option>`;
      }).join('');
      inputHtml = `<select name="${escapeHtml(key)}"><option value="">—</option>${opts}</select>`;
    } else if (type === 'checkbox' || type === 'boolean') {
      inputHtml = `<input type="checkbox" name="${escapeHtml(key)}">`;
    } else if (['number', 'date', 'email'].includes(type)) {
      inputHtml = `<input type="${type}" name="${escapeHtml(key)}">`;
    } else {
      inputHtml = `<input type="text" name="${escapeHtml(key)}">`;
    }

    label.innerHTML = `${escapeHtml(f.label || key)}${f.required ? '<span class="required-mark">*</span>' : ''} ${inputHtml}`;
    form.appendChild(label);
  }

  const submitBtn = document.createElement('button');
  submitBtn.type = 'button';
  submitBtn.className = 'btn-primary';
  submitBtn.textContent = 'Voir les données produites';
  form.appendChild(submitBtn);

  const output = document.createElement('pre');
  output.className = 'output-json';
  output.hidden = true;

  submitBtn.addEventListener('click', () => {
    const data = {};
    for (const [i, f] of fields.entries()) {
      const key = f.key || f.name || `field_${i}`;
      const el = form.elements.namedItem(key);
      if (!el) continue;
      data[key] = el.type === 'checkbox' ? el.checked : el.value;
    }
    output.textContent = JSON.stringify(data, null, 2);
    output.hidden = false;
  });

  section.appendChild(form);
  section.appendChild(output);
  viewer.appendChild(section);
}

// --- Bundle viewer : liste de fichiers -----------------------------------

function renderBundleViewer(viewer, content) {
  const section = document.createElement('div');
  section.className = 'section';
  section.innerHTML = '<h2>Contenu du bundle</h2>';

  if (content && Array.isArray(content.files) && content.files.length > 0) {
    const list = document.createElement('ul');
    list.className = 'file-list';
    for (const f of content.files) {
      const li = document.createElement('li');
      li.textContent = typeof f === 'string' ? f : (f.path || f.name || JSON.stringify(f));
      list.appendChild(li);
    }
    section.appendChild(list);
  } else {
    section.innerHTML += '<p class="hint">Manifeste sans liste de fichiers reconnaissable — voir le JSON brut ci-dessous.</p>';
  }

  viewer.appendChild(section);
}

init();
