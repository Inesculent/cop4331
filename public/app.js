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
const addToggleBtn = document.getElementById('add-toggle');
const addCancelBtn = document.getElementById('add-cancel');

async function getCurrentUid() {
    // Uses UID stashed after signup/login.
    const uid = localStorage.getItem('uid');
    if (!uid) throw new Error('No UID set; store it after signup/login or expose an endpoint to fetch profile.');
    return parseInt(uid, 10);
}

function showAddForm(show) {
    if (!addForm) return;
    addForm.hidden = !show;
    if (addToggleBtn) addToggleBtn.disabled = show;
    if (show) {
        document.getElementById('c-name')?.focus();
    }
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
            const cid =
                c?.cid ??
                c?.id ??
                c?.contact_id ??
                c?.contactId;

            const li = document.createElement('li');

            // left: name + meta
            const left = document.createElement('div');
            left.className = 'row';
            left.innerHTML = `<strong>${escapeHtml(c.name || '')}</strong>
        <span class="meta">${escapeHtml(c.email || '')}${c?.phone ? ' • ' + escapeHtml(c.phone) : ''}</span>`;

            // right: actions (Edit / Delete)
            const actions = document.createElement('div');
            actions.className = 'actions';

            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'btn neutral';
            editBtn.textContent = 'Edit';
            editBtn.disabled = !cid;

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'btn';
            delBtn.textContent = 'Delete';
            delBtn.dataset.cid = cid ? String(cid) : '';
            if (!cid) {
                delBtn.disabled = true;
                delBtn.title = 'Missing contact id from API response';
            }

            // Delete handler
            delBtn.addEventListener('click', async (e) => {
                try {
                    const id = e.currentTarget.dataset.cid;
                    if (!id) throw new Error('Missing contact id');
                    const label = c?.name || c?.email || `contact ${id}`;
                    const ok = window.confirm(`Delete "${label}"? This cannot be undone.`);
                    if (!ok) return;

                    await deleteContact(id);
                    await listContacts();
                } catch (err) {
                    showContactsError(err);
                }
            });

            // Edit handler — inline editor
            editBtn.addEventListener('click', () => {
                if (!cid) return;
                // Build inline editor UI
                const editor = document.createElement('div');
                editor.className = 'edit-inputs';

                const nameI = document.createElement('input');
                nameI.placeholder = 'Name';
                nameI.value = c.name || '';

                const emailI = document.createElement('input');
                emailI.type = 'email';
                emailI.placeholder = 'Email';
                emailI.value = c.email || '';

                const phoneI = document.createElement('input');
                phoneI.placeholder = 'Phone';
                phoneI.value = c.phone || '';

                const editActions = document.createElement('div');
                editActions.className = 'edit-actions';

                const cancelE = document.createElement('button');
                cancelE.type = 'button';
                cancelE.className = 'btn';
                cancelE.textContent = 'Cancel';

                const saveE = document.createElement('button');
                saveE.type = 'button';
                saveE.className = 'btn primary';
                saveE.textContent = 'Save';

                editActions.append(cancelE, saveE);
                editor.append(nameI, emailI, phoneI);
                left.innerHTML = '';      // clear view
                left.append(editor, editActions);

                // Disable action buttons while editing
                editBtn.disabled = true;
                delBtn.disabled = true;

                // Cancel → re-render list
                cancelE.addEventListener('click', listContacts);

                // Save → PATCH /contacts/{cid}
                saveE.addEventListener('click', async () => {
                    try {
                        const payload = {
                            name: nameI.value.trim(),
                            email: emailI.value.trim(),
                            phone: phoneI.value.trim(),
                        };
                        // Remove empty strings so we only send fields the user actually set
                        Object.keys(payload).forEach(k => {
                            if (payload[k] === '') delete payload[k];
                        });

                        await patchContact(cid, payload);
                        await listContacts();
                    } catch (err) {
                        showContactsError(err);
                    }
                });

                // Focus first input
                nameI.focus();
            });

            actions.append(editBtn, delBtn);
            li.append(left, actions);
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
        showAddForm(false);
        listContacts();
    } catch (err) {
        showContactsError(err);
    }
}

async function deleteContact(cid) {
    await api(`/contacts/${encodeURIComponent(String(cid))}`, { method: 'DELETE' });
}

async function patchContact(cid, body) {
    // Uses API PATCH /contacts/{cid}
    await api(`/contacts/${encodeURIComponent(String(cid))}`, {
        method: 'PATCH',
        body
    });
}

if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
        try {
            await api('/auth/logout', { method: 'POST' });
        } finally {
            localStorage.removeItem('uid');
            window.location.href = './index.html';
        }
    });
}

// Add-form wiring
if (addForm) {
    // ensure hidden on load
    addForm.hidden = true;
    addForm.addEventListener('submit', addContact);
}
if (addToggleBtn) {
    addToggleBtn.addEventListener('click', () => showAddForm(true));
}
if (addCancelBtn) {
    addCancelBtn.addEventListener('click', () => showAddForm(false));
}

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
