// app.js
const BASE = window.BASE_API || '/index.php';

// Helper: JSON fetch with credentials (cookie-based JWT)
async function api(path, { method = 'GET', body, headers = {} } = {}) {
    const res = await fetch(`${BASE}${path}`, {
        method,
        headers: {
            'Content-Type': 'application/json',
            ...headers,
        },
        credentials: 'include', // send/receive the auth cookie
        body: body ? JSON.stringify(body) : undefined,
    });
    let data = null;
    try { data = await res.json(); } catch (_) { }
    if (!res.ok) {
        const message = data?.message || `HTTP ${res.status}`;
        const code = data?.code || 'ERROR';
        throw Object.assign(new Error(message), { code, status: res.status, data });
    }
    return data;
}

// --- LOGIN PAGE logic ---
const loginForm = document.getElementById('login-form');  // <form id="login-form" ...>
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

            const res = await api('/auth/login', {
                method: 'POST',
                body: { email, password }
            });

            const uid =
                res?.data?.user?.id ??
                res?.data?.id ??
                res?.id ??
                res?.uid;

            if (!uid) throw new Error('Login ok but no UID returned');

            localStorage.setItem('uid', String(uid));
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

async function getCurrentUid() {
    // Uses UID stashed after signup/login.
    const uid = localStorage.getItem('uid');
    if (!uid) throw new Error('No UID set; store it after signup/login or expose an endpoint to fetch profile.');
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
            // Be defensive about the contact id field name.
            const cid =
                c?.cid ??
                c?.id ??
                c?.contact_id ??
                c?.contactId;

            const li = document.createElement('li');

            const left = document.createElement('div');
            left.className = 'row';
            left.innerHTML = `<strong>${escapeHtml(c.name || '')}</strong>
        <span class="meta">${escapeHtml(c.email || '')}${c?.phone ? ' • ' + escapeHtml(c.phone) : ''}</span>`;

            const del = document.createElement('button');
            del.type = 'button';
            del.textContent = 'Delete';
            del.dataset.cid = cid ? String(cid) : '';

            if (!cid) {
                // If the API didn’t send an id, disable to avoid /undefined
                del.disabled = true;
                del.title = 'Missing contact id from API response';
            }

            del.addEventListener('click', async (e) => {
                try {
                    const id = e.currentTarget.dataset.cid;
                    if (!id) throw new Error('Missing contact id');

                    // Confirmation before deleting
                    const label = c?.name || c?.email || `contact ${id}`;
                    const ok = window.confirm(`Delete "${label}"? This cannot be undone.`);
                    if (!ok) return;

                    await deleteContact(id);
                    await listContacts();
                } catch (err) {
                    showContactsError(err);
                }
            });

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
    await api(`/contacts/${encodeURIComponent(String(cid))}`, { method: 'DELETE' });
}

if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
        try {
            await api('/auth/logout', { method: 'POST' });
        } finally {
            // Clear any cached UID and go back to login
            localStorage.removeItem('uid');
            window.location.href = './index.html';
        }
    });
}

if (addForm) addForm.addEventListener('submit', addContact);

// Simple HTML escaping
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
