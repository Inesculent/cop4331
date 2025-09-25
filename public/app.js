// app.js
// Base URL for the API. You set this in each HTML page via:
// <script>window.BASE_API = '';</script>
const BASE =
    (typeof window !== 'undefined' && typeof window.BASE_API === 'string')
        ? window.BASE_API
        : '';

// Helper to join paths safely (avoids double slashes)
function joinApi(path) {
    if (!path) return BASE || '';
    const p = path.startsWith('/') ? path : '/' + path;
    return `${BASE}${p}`;
}

// Helper: JSON fetch with credentials (cookie-based JWT)
async function api(path, { method = 'GET', body, headers = {} } = {}) {
    const res = await fetch(joinApi(path), {
        method,
        headers: { 'Content-Type': 'application/json', ...headers },
        credentials: 'include', // send/receive the auth cookie
        body: body ? JSON.stringify(body) : undefined,
    });
    let data = null;
    try { data = await res.json(); } catch (_) { }
    if (!res.ok || data?.status === 'error') {
        const message = data?.message || `HTTP ${res.status}`;
        const code = data?.code || 'ERROR';
        throw Object.assign(new Error(message), { code, status: res.status, data });
    }
    return data;
}

// --- LOGIN PAGE logic ---
const loginForm = document.getElementById('login-form');
if (loginForm) {
    const emailEl = document.getElementById('email');
    const passwordEl = document.getElementById('password');
    const errEl = document.getElementById('login-error');

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        errEl.textContent = '';
        try {
            const email = emailEl.value.trim();
            const password = passwordEl.value;
            await api('/auth/login', { method: 'POST', body: { email, password } });
            // If login worked, cookie is set by server. Go to dashboard.
            window.location.href = './app.html';
        } catch (err) {
            errEl.textContent = err.message || 'Login failed';
        }
    });
}

// --- DASHBOARD PAGE logic ---
const logoutBtn = document.getElementById('logout');
const contactsList = document.getElementById('contacts');
const contactsEmpty = document.getElementById('contacts-empty');
const contactsError = document.getElementById('contacts-error');
const addForm = document.getElementById('add-form');

// TEMP until you add a /me endpoint:
// You must have uid in localStorage (set it after signup, or set manually for testing)
async function getCurrentUid() {
    const uid = localStorage.getItem('uid');
    if (!uid) throw new Error('No UID set; store it after signup/login or add a /me endpoint.');
    return parseInt(uid, 10);
}

async function listContacts() {
    if (!contactsList) return;
    contactsError.textContent = '';
    contactsList.innerHTML = '';
    try {
        const uid = await getCurrentUid();
        const res = await api(`/users/${uid}/contacts`, { method: 'GET' });

        const items = res?.data || [];
        if (!items.length) {
            contactsEmpty.hidden = false;
            return;
        }
        contactsEmpty.hidden = true;

        for (const c of items) {
            const li = document.createElement('li');
            const left = document.createElement('div');
            left.className = 'row';
            left.innerHTML = `<strong>${escapeHtml(c.name || '')}</strong>
        <span class="meta">${escapeHtml(c.email || '')}${c.phone ? ' • ' + escapeHtml(c.phone) : ''}</span>`;
            const del = document.createElement('button');
            del.textContent = 'Delete';
            del.addEventListener('click', () => deleteContact(c.id).then(listContacts).catch(showContactsError));
            li.append(left, del);
            contactsList.appendChild(li);
        }
    } catch (err) {
        showContactsError(err);
    }
}

function showContactsError(err) {
    if (contactsError) contactsError.textContent = err?.message || 'Error loading contacts';
}

async function addContact(e) {
    e.preventDefault();
    try {
        const uid = await getCurrentUid();
        const name = document.getElementById('c-name').value.trim();
        const email = document.getElementById('c-email').value.trim();
        const phone = document.getElementById('c-phone').value.trim();
        await api(`/users/${uid}/contacts`, { method: 'POST', body: { name, email, phone } });
        e.target.reset();
        listContacts();
    } catch (err) {
        showContactsError(err);
    }
}

async function deleteContact(cid) {
    await api(`/contacts/${cid}`, { method: 'DELETE' });
}

if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
        try {
            await api('/auth/logout', { method: 'POST' });
        } finally {
            localStorage.removeItem('uid');
            window.location.href = './login.html';
        }
    });
}

if (addForm) addForm.addEventListener('submit', addContact);

// Small XSS-safe helper
function escapeHtml(s) {
    return String(s ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

// Kick things off on dashboard
if (contactsList) listContacts();
