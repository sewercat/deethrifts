const API_KEY = 'apiUrl';
const TOKEN_KEY = 'syncToken';
const LAST_RESULT_KEY = 'lastSyncResult';
const DEFAULT_API_URL = 'https://deethrifts.store/api.php';
const DEFAULT_TOKEN = '0987654321ghlopin';

const els = {
  apiUrl: document.getElementById('apiUrl'),
  syncToken: document.getElementById('syncToken'),
  saveBtn: document.getElementById('saveBtn'),
  syncBtn: document.getElementById('syncBtn'),
  status: document.getElementById('status')
};

function collapseText(v) {
  return String(v || '').replace(/\s+/g, ' ').trim();
}

function setStatus(msg, isErr = false) {
  els.status.textContent = String(msg || '');
  els.status.style.color = isErr ? '#ff9cae' : '#d9dfec';
}

function formatLastResult(r) {
  if (!r || typeof r !== 'object') return '';
  if (!r.ok) return `Last sync failed (${r.trigger || 'manual'}): ${r.error || 'Unknown error'}`;
  return `Last sync (${r.trigger || 'manual'}): updated ${r.updated || 0}, matched ${r.matched || 0}, processed ${r.processed || 0}`;
}

async function loadSettings() {
  const data = await chrome.storage.local.get([API_KEY, TOKEN_KEY, LAST_RESULT_KEY]);
  const apiUrl = collapseText(data[API_KEY] || DEFAULT_API_URL);
  const token = collapseText(data[TOKEN_KEY] || DEFAULT_TOKEN);

  els.apiUrl.value = apiUrl;
  els.syncToken.value = token;

  await chrome.storage.local.set({ [API_KEY]: apiUrl, [TOKEN_KEY]: token });

  const lastMsg = formatLastResult(data[LAST_RESULT_KEY]);
  if (lastMsg) setStatus(lastMsg);
  else setStatus('Ready. Hourly background sync is enabled.');
}

async function saveSettings(showMsg = true) {
  const apiUrl = collapseText(els.apiUrl.value || DEFAULT_API_URL);
  const token = collapseText(els.syncToken.value || DEFAULT_TOKEN);
  await chrome.storage.local.set({ [API_KEY]: apiUrl, [TOKEN_KEY]: token });
  if (showMsg) setStatus('Saved.');
}

async function syncNow() {
  els.syncBtn.disabled = true;
  try {
    await saveSettings(false);
    setStatus('Running sync...');

    const response = await chrome.runtime.sendMessage({ type: 'run_sync', trigger: 'manual' });
    if (!response || !response.ok) {
      throw new Error((response && response.error) || 'Sync failed.');
    }

    const result = response.result || {};
    setStatus(`Done. Updated ${result.updated || 0}, matched ${result.matched || 0}, processed ${result.processed || 0}.`);
  } catch (err) {
    setStatus(err && err.message ? err.message : 'Sync failed.', true);
  } finally {
    els.syncBtn.disabled = false;
  }
}

els.saveBtn.addEventListener('click', () => saveSettings(true));
els.syncBtn.addEventListener('click', syncNow);
els.apiUrl.addEventListener('change', () => saveSettings(false));
els.syncToken.addEventListener('change', () => saveSettings(false));

loadSettings();
