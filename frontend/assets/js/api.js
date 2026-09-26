const API_BASE = window.API_BASE || '../../backend/api';

async function apiCall(endpoint, action, payload = null, method = 'POST') {
  const url = `${API_BASE}/${endpoint}.php?action=${encodeURIComponent(action)}`;
  const options = {
    method,
    credentials: 'same-origin',
  };
  if (payload !== null && method !== 'GET') {
    options.headers = { 'Content-Type': 'application/json' };
    options.body = JSON.stringify(payload);
  }

  let res;
  try {
    res = await fetch(url, options);
  } catch (err) {
    return { success: false, message: 'Could not reach the server. Is the backend running?' };
  }

  let json;
  try {
    json = await res.json();
  } catch (err) {
    return { success: false, message: 'Unexpected server response.' };
  }
  return json;
}


async function apiUpload(endpoint, action, formData) {
  const url = `${API_BASE}/${endpoint}.php?action=${encodeURIComponent(action)}`;
  try {
    const res = await fetch(url, { method: 'POST', credentials: 'same-origin', body: formData });
    return await res.json();
  } catch (err) {
    return { success: false, message: 'Upload failed. Could not reach the server.' };
  }
}

function toast(message, type = 'success') {
  let el = document.getElementById('toast');
  if (!el) {
    el = document.createElement('div');
    el.id = 'toast';
    el.className = 'toast';
    document.body.appendChild(el);
  }
  el.textContent = message;
  el.className = `toast show ${type}`;
  clearTimeout(window.__toastTimer);
  window.__toastTimer = setTimeout(() => el.classList.remove('show'), 3200);
}


async function guardRole(expectedRole) {
  const res = await apiCall('auth', 'me', null, 'GET');
  if (!res.success || res.data.role !== expectedRole) {
    window.location.href = '../login.html';
    return null;
  }
  return res.data;
}

async function logout() {
  await apiCall('auth', 'logout', null, 'GET');
  window.location.href = '../login.html';
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function fmtDate(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtTime(timeStr) {
  if (!timeStr) return '—';
  const [h, m] = timeStr.split(':');
  const hour = parseInt(h, 10);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const h12 = hour % 12 === 0 ? 12 : hour % 12;
  return `${h12}:${m} ${ampm}`;
}

function fmtDateTime(ts) {
  if (!ts) return '—';
  const d = new Date(ts.replace(' ', 'T'));
  return d.toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function statusBadge(status) {
  return `<span class="badge ${status}"><span class="dot"></span>${status}</span>`;
}

function openModal(id) {
  document.getElementById(id).classList.add('show');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('show');
}
document.addEventListener('click', (e) => {
  if (e.target.matches('[data-close]')) closeModal(e.target.dataset.close);
  if (e.target.classList.contains('modal-backdrop')) e.target.classList.remove('show');
});
