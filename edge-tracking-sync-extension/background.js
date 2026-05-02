const API_KEY = 'apiUrl';
const TOKEN_KEY = 'syncToken';
const LAST_RESULT_KEY = 'lastSyncResult';
const DEFAULT_API_URL = 'https://deethrifts.store/api.php';
const DEFAULT_TOKEN = '0987654321ghlopin';
const PORTAL_URL = 'https://msroyal.pk/portal/clients/index.php';
const ALARM_NAME = 'dee_hourly_tracking_sync';

function collapseText(v) {
  return String(v || '').replace(/\s+/g, ' ').trim();
}

function extractTracking(raw) {
  const text = collapseText(raw);
  if (!text) return '';
  const patterns = [
    /Shipment\s*out\s*for\s*delivery[^A-Za-z0-9]{0,24}(?:\d{4}[\/-]\d{2}[\/-]\d{2})?[^A-Za-z0-9]{0,24}(ANN-[A-Za-z0-9-]{3,})/i,
    /Shipment\s*out\s*for\s*delivery[^A-Za-z0-9]{0,24}(?:\d{4}[\/-]\d{2}[\/-]\d{2})?[^A-Za-z0-9]{0,24}(K[IL][A-Za-z0-9-]{4,})/i,
    /Shipment\s*out\s*for\s*delivery[^A-Za-z0-9]{0,24}(?:\d{4}[\/-]\d{2}[\/-]\d{2})?[^0-9]{0,24}(\d{10,20})/i,
    /\b(ANN-[A-Za-z0-9-]{3,})\b/i,
    /\b(K[IL][A-Za-z0-9-]{4,})\b/i,
    /\b(\d{10,20})\b/
  ];
  for (const rx of patterns) {
    const m = text.match(rx);
    if (m && m[1]) return String(m[1]).trim().toUpperCase();
  }
  return '';
}

function extractPortalStatus(raw) {
  const text = collapseText(raw).toLowerCase();
  if (!text) return '';
  if (text.includes('delivered')) return 'Delivered';
  if (text.includes('in transit') || text.includes('out for delivery')) return 'Confirmed';
  if (text.includes('parcel booked')) return 'Confirmed';
  return '';
}

async function getSettings() {
  const data = await chrome.storage.local.get([API_KEY, TOKEN_KEY]);
  const apiUrl = collapseText(data[API_KEY] || DEFAULT_API_URL);
  const token = collapseText(data[TOKEN_KEY] || DEFAULT_TOKEN);
  await chrome.storage.local.set({ [API_KEY]: apiUrl, [TOKEN_KEY]: token });
  return { apiUrl, token };
}

function waitForTabComplete(tabId, timeoutMs = 30000) {
  return new Promise((resolve, reject) => {
    let done = false;
    const timer = setTimeout(() => {
      if (done) return;
      done = true;
      chrome.tabs.onUpdated.removeListener(onUpdated);
      reject(new Error('Timed out waiting for MS Royal tab to load.'));
    }, timeoutMs);

    function finish() {
      if (done) return;
      done = true;
      clearTimeout(timer);
      chrome.tabs.onUpdated.removeListener(onUpdated);
      resolve();
    }

    function onUpdated(updatedTabId, info) {
      if (updatedTabId !== tabId) return;
      if (info.status === 'complete') finish();
    }

    chrome.tabs.onUpdated.addListener(onUpdated);
    chrome.tabs.get(tabId, (tab) => {
      if (chrome.runtime.lastError || !tab) return;
      if (tab.status === 'complete') finish();
    });
  });
}

async function findOrCreatePortalTab() {
  const existing = await chrome.tabs.query({ url: [PORTAL_URL, PORTAL_URL + '*'] });
  if (existing && existing.length) {
    return { tab: existing[0], created: false };
  }
  const tab = await chrome.tabs.create({ url: PORTAL_URL, active: false });
  return { tab, created: true };
}

async function scrapePortalRows(tabId) {
  const [{ result }] = await chrome.scripting.executeScript({
    target: { tabId },
    func: () => {
      function collapse(v) { return String(v || '').replace(/\s+/g, ' ').trim(); }
      const rows = Array.from(document.querySelectorAll('#myTable tbody tr'));
      return rows.map((tr) => collapse(tr.innerText || tr.textContent || '')).filter(Boolean);
    }
  });
  return Array.isArray(result) ? result : [];
}

function buildRecords(rawRows) {
  const records = [];
  for (const row of (rawRows || [])) {
    const orderMatch = String(row || '').match(/Order\s+(ord-[A-Za-z0-9_-]+)/i);
    if (!orderMatch || !orderMatch[1]) continue;
    const orderId = String(orderMatch[1]).trim();
    const trackingNumber = extractTracking(row);
    const portalStatus = extractPortalStatus(row);
    if (!trackingNumber && !portalStatus) continue;
    records.push({
      orderId,
      trackingNumber,
      statusText: row,
      portalStatus
    });
  }
  return records;
}

async function postSync(apiUrl, token, records) {
  const res = await fetch(apiUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      type: 'sync_tracking_batch',
      token,
      source: 'edge_extension',
      records
    })
  });

  let payload = {};
  try { payload = await res.json(); } catch (_) { payload = {}; }
  if (!res.ok || !payload.ok) {
    throw new Error(payload.error || `Sync failed (${res.status})`);
  }
  return payload;
}

async function runSync(trigger = 'manual') {
  const startedAt = new Date().toISOString();
  const { apiUrl, token } = await getSettings();
  const open = await findOrCreatePortalTab();
  const tab = open.tab;

  try {
    if (!tab || !tab.id) throw new Error('Could not open MS Royal tab.');
    await waitForTabComplete(tab.id);
    const rawRows = await scrapePortalRows(tab.id);
    if (!rawRows.length) throw new Error('No booking rows found on MS Royal page.');

    const records = buildRecords(rawRows);
    if (!records.length) throw new Error('No trackable or deliverable rows found yet.');

    const payload = await postSync(apiUrl, token, records);
    const result = {
      ok: true,
      trigger,
      startedAt,
      finishedAt: new Date().toISOString(),
      processed: payload.processed || 0,
      matched: payload.matched || 0,
      updated: payload.updated || 0,
      skipped: payload.skipped || 0
    };
    await chrome.storage.local.set({ [LAST_RESULT_KEY]: result });
    return result;
  } catch (err) {
    const result = {
      ok: false,
      trigger,
      startedAt,
      finishedAt: new Date().toISOString(),
      error: err && err.message ? err.message : 'Sync failed.'
    };
    await chrome.storage.local.set({ [LAST_RESULT_KEY]: result });
    throw err;
  } finally {
    if (open.created && tab && tab.id) {
      try { await chrome.tabs.remove(tab.id); } catch (_) {}
    }
  }
}

async function ensureHourlyAlarm() {
  const alarm = await chrome.alarms.get(ALARM_NAME);
  if (!alarm) {
    chrome.alarms.create(ALARM_NAME, { periodInMinutes: 60 });
  }
}

chrome.runtime.onInstalled.addListener(() => {
  ensureHourlyAlarm();
  getSettings();
});

chrome.runtime.onStartup.addListener(() => {
  ensureHourlyAlarm();
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (!alarm || alarm.name !== ALARM_NAME) return;
  runSync('hourly').catch(() => {});
});

chrome.runtime.onMessage.addListener((msg, _sender, sendResponse) => {
  if (!msg || msg.type !== 'run_sync') return;
  runSync(msg.trigger || 'manual')
    .then((result) => sendResponse({ ok: true, result }))
    .catch((err) => sendResponse({ ok: false, error: err && err.message ? err.message : 'Sync failed.' }));
  return true;
});
