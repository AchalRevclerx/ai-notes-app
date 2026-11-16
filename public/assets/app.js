// Margin: AI notes frontend. Vanilla ES module, no build step.
// All user content goes through textContent / text nodes (never innerHTML).

const TOKEN_KEY = 'margin.token';
const PAGE_SIZE = 10;
const INDEX_POLL_MS = 2000;
const INDEX_POLL_TRIES = 8;

const $ = (selector) => document.querySelector(selector);

const state = {
    token: readToken(),
    user: null,
    mode: 'list', // 'list' | 'search'
    page: 1,
    lastPage: 1,
    query: '',
    notes: [],
    note: null, // note open in the editor; { id: null } for a new one
    dirty: false,
    authMode: 'login',
};

// ---------- API ----------

class ApiError extends Error {
    constructor(status, message, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

const FRIENDLY_ERRORS = {
    429: 'Too many requests. Wait a minute and try again.',
    503: 'The AI service is unavailable right now. Try again shortly.',
};

async function api(path, { method = 'GET', body } = {}) {
    const headers = { Accept: 'application/json' };
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    if (state.token) headers.Authorization = `Bearer ${state.token}`;

    let response;
    try {
        response = await fetch(`/api${path}`, { method, headers, body: body === undefined ? undefined : JSON.stringify(body) });
    } catch {
        throw new ApiError(0, "Can't reach the server. Check your connection.");
    }

    if (response.status === 204) return null;
    const data = await response.json().catch(() => null);

    if (response.status === 401 && state.token) {
        signOut();
        throw new ApiError(401, 'Your session expired. Please log in again.');
    }
    if (!response.ok) {
        const message = data?.message && response.status !== 429 ? data.message : FRIENDLY_ERRORS[response.status];
        throw new ApiError(response.status, message || 'Something went wrong. Please try again.', data?.errors ?? {});
    }
    return data;
}

// ---------- Small helpers ----------

function h(tag, props = {}, ...children) {
    const el = document.createElement(tag);
    for (const [key, value] of Object.entries(props)) {
        if (value === false || value == null) continue;
        if (key === 'class') el.className = value;
        else if (key === 'style') el.style.cssText = value;
        else if (key.startsWith('on')) el.addEventListener(key.slice(2), value);
        else el.setAttribute(key, value === true ? '' : value);
    }
    el.append(...children.flat().filter((child) => child != null && child !== false));
    return el;
}

function readToken() {
    try { return localStorage.getItem(TOKEN_KEY); } catch { return null; }
}

function writeToken(token) {
    try { token ? localStorage.setItem(TOKEN_KEY, token) : localStorage.removeItem(TOKEN_KEY); } catch { /* private mode: token lives in memory only */ }
}

const relativeTime = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
function timeAgo(iso) {
    const seconds = (new Date(iso).getTime() - Date.now()) / 1000;
    const steps = [[60, 'second'], [60, 'minute'], [24, 'hour'], [7, 'day'], [4.35, 'week'], [12, 'month'], [Infinity, 'year']];
    let value = seconds;
    for (const [size, unit] of steps) {
        if (Math.abs(value) < size) return relativeTime.format(Math.round(value), unit);
        value /= size;
    }
    return '';
}

let toastTimer;
function toast(message, tone = 'info') {
    const el = $('#toast');
    el.textContent = message;
    el.dataset.tone = tone;
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 2600);
}

function showFieldErrors(scope, errors) {
    scope.querySelectorAll('[data-error]').forEach((el) => {
        const messages = errors[el.dataset.error];
        el.textContent = messages ? messages[0] : '';
        const input = scope.querySelector(`[name="${el.dataset.error}"]`);
        if (!input) return;
        if (messages) input.setAttribute('aria-invalid', 'true');
        else input.removeAttribute('aria-invalid');
    });
}

// Grow textareas with their content. Native `field-sizing: content` where supported.
const NATIVE_AUTOSIZE = CSS.supports('field-sizing', 'content');
function autosize(el) {
    if (NATIVE_AUTOSIZE) return;
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
}

function setBusy(button, busy, label) {
    button.disabled = busy;
    if (label) button.textContent = label;
}

// ---------- Auth ----------

function setAuthMode(mode) {
    state.authMode = mode;
    const isRegister = mode === 'register';
    document.querySelectorAll('.segmented button').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.mode === mode)));
    $('[data-for="name"]').hidden = !isRegister;
    $('[data-help="password"]').hidden = !isRegister;
    $('#auth-password').autocomplete = isRegister ? 'new-password' : 'current-password';
    $('#auth-heading').textContent = isRegister ? 'Create account' : 'Log in';
    $('#auth-submit').textContent = isRegister ? 'Create account' : 'Log in';
    $('#auth-error').textContent = '';
    showFieldErrors($('#auth-form'), {});
}

async function submitAuth(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = $('#auth-submit');
    const payload = Object.fromEntries(new FormData(form));
    if (state.authMode === 'login') delete payload.name;

    $('#auth-error').textContent = '';
    showFieldErrors(form, {});
    setBusy(submit, true, state.authMode === 'login' ? 'Logging in…' : 'Creating account…');

    try {
        const { data } = await api(`/auth/${state.authMode}`, { method: 'POST', body: payload });
        state.token = data.token;
        writeToken(data.token);
        state.user = data.user;
        form.reset();
        enterWorkspace();
    } catch (error) {
        showFieldErrors(form, error.errors);
        if (!Object.keys(error.errors).length) $('#auth-error').textContent = error.message;
    } finally {
        setBusy(submit, false, state.authMode === 'login' ? 'Log in' : 'Create account');
    }
}

function signOut() {
    state.token = null;
    state.user = null;
    state.note = null;
    state.dirty = false;
    writeToken(null);
    $('#workspace').hidden = true;
    $('#auth').hidden = false;
    setAuthMode('login');
}

async function logout() {
    if (!confirmDiscard()) return;
    try { await api('/auth/logout', { method: 'POST' }); } catch { /* token may already be gone */ }
    signOut();
}

// ---------- List / search ----------

function renderSkeleton() {
    const list = $('#note-list');
    list.setAttribute('aria-busy', 'true');
    list.replaceChildren(...Array.from({ length: 6 }, () => h('li', { class: 'skeleton', 'aria-hidden': 'true' }, h('span'), h('span'), h('span'))));
}

function renderListState({ title, text, action, tone }) {
    $('#note-list').replaceChildren(h('li', { class: `list-state ${tone || ''}` }, h('h3', {}, title), h('p', {}, text), action || null));
}

function renderList() {
    const list = $('#note-list');
    list.setAttribute('aria-busy', 'false');
    const searching = state.mode === 'search';

    $('#list-title').textContent = searching ? `Results for "${state.query}"` : 'All notes';
    $('#pager').hidden = searching || state.lastPage <= 1;
    $('#page-info').textContent = `Page ${state.page} of ${state.lastPage}`;
    $('#prev-page').disabled = state.page <= 1;
    $('#next-page').disabled = state.page >= state.lastPage;

    if (!state.notes.length) {
        return searching
            ? renderListState({ title: 'Nothing close enough.', text: 'Try describing the topic in different words.', action: h('button', { class: 'btn btn-ghost', onclick: clearSearch }, 'Clear search') })
            : renderListState({ title: 'No notes yet.', text: 'Write your first note. It becomes searchable by meaning a few seconds after you save it.', action: h('button', { class: 'btn btn-primary', onclick: newNote }, 'New note') });
    }

    list.replaceChildren(...state.notes.map((note, i) => h('li', { style: `--i:${i}` },
        h('button', {
            class: 'note-item',
            type: 'button',
            'aria-current': state.note?.id === note.id ? 'true' : false,
            onclick: () => openNote(note.id),
        },
        h('span', { class: 'note-title' }, note.title),
        h('span', { class: 'note-excerpt' }, note.content.slice(0, 180)),
        h('span', { class: 'note-meta' },
            searching ? h('span', { class: 'score', title: 'Cosine similarity to your search' }, `${Math.round(note.score * 100)}% match`) : null,
            h('span', {}, timeAgo(note.updated_at)),
            note.is_indexed ? null : h('span', { class: 'pending' }, 'indexing'),
        )),
    )));
}

async function loadList(page = state.page) {
    state.mode = 'list';
    state.query = '';
    $('#list-meta').replaceChildren();
    renderSkeleton();
    try {
        const { data, meta } = await api(`/notes?page=${page}&limit=${PAGE_SIZE}`);
        state.notes = data;
        state.page = meta.current_page;
        state.lastPage = meta.last_page;
        if (!data.length && page > 1) return loadList(page - 1); // deleted the last note on a page
        $('#list-meta').textContent = meta.total ? `${meta.total} note${meta.total === 1 ? '' : 's'}` : '';
        renderList();
    } catch (error) {
        renderListState({ tone: 'error', title: "Couldn't load your notes.", text: error.message, action: h('button', { class: 'btn btn-ghost', onclick: () => loadList(page) }, 'Retry') });
    }
}

async function search(query) {
    state.mode = 'search';
    state.query = query;
    $('#list-meta').replaceChildren();
    renderSkeleton();
    try {
        const { data, meta } = await api(`/notes/search?q=${encodeURIComponent(query)}&limit=20`);
        state.notes = data;
        const pending = meta.pending_index ? `. ${meta.pending_index} note${meta.pending_index === 1 ? ' is' : 's are'} still being indexed` : '';
        $('#list-meta').replaceChildren(
            `${meta.count} match${meta.count === 1 ? '' : 'es'}${pending}`,
            h('button', { class: 'btn btn-link', type: 'button', onclick: clearSearch }, 'Clear'),
        );
        renderList();
    } catch (error) {
        const message = error.errors?.q?.[0] || error.message;
        renderListState({ tone: 'error', title: 'Search failed.', text: message, action: h('button', { class: 'btn btn-ghost', onclick: clearSearch }, 'Back to all notes') });
    }
}

function clearSearch() {
    $('#search-input').value = '';
    loadList(1);
}

// ---------- Editor ----------

function confirmDiscard() {
    return !state.dirty || window.confirm('You have unsaved changes. Discard them?');
}

function setView(view) {
    $('#workspace').dataset.view = view;
}

function setDirty(dirty) {
    state.dirty = dirty;
    const label = $('#save-state');
    label.dataset.state = dirty ? 'dirty' : 'clean';
    label.textContent = dirty ? 'Unsaved changes' : state.note?.id ? `Saved ${timeAgo(state.note.updated_at)}` : '';
    $('#summarize').disabled = dirty || !state.note?.id;
    $('#summarize').title = dirty ? 'Save the note first' : '';
}

function fillEditor(note) {
    state.note = note;
    $('#editor-empty').hidden = true;
    $('#editor').hidden = false;
    $('#note-title').value = note.title ?? '';
    $('#note-content').value = note.content ?? '';
    document.querySelectorAll('.autosize').forEach(autosize);
    $('#delete').hidden = !note.id;
    $('#summary-error').textContent = '';
    showFieldErrors($('#editor'), {});
    renderSummary();
    renderIndexState();
    updateCharCount();
    setDirty(false);
    document.querySelectorAll('.note-item').forEach((el, i) => el.setAttribute('aria-current', String(state.notes[i]?.id === note.id)));
}

function closeEditor() {
    state.note = null;
    setDirty(false);
    $('#editor').hidden = true;
    $('#editor-empty').hidden = false;
    setView('list');
}

function renderSummary(model) {
    const { summary, summary_generated_at: at } = state.note;
    $('#summary').hidden = !summary;
    $('#summary-text').textContent = summary ?? '';
    $('#summary-meta').textContent = at ? `Generated ${timeAgo(at)}${model ? ` with ${model}` : ''}` : '';
}

function renderIndexState() {
    if (!state.note?.id) return ($('#index-state').textContent = 'Not saved yet');
    $('#index-state').textContent = state.note.is_indexed ? 'Indexed for search' : 'Indexing for search…';
}

function updateCharCount() {
    $('#char-count').textContent = `${$('#note-content').value.length.toLocaleString()} / 20,000`;
}

async function openNote(id) {
    if (state.note?.id === id && !state.dirty) return setView('editor');
    if (!confirmDiscard()) return;
    try {
        const { data } = await api(`/notes/${id}`);
        fillEditor(data);
        setView('editor');
        pollIndex(data.id);
    } catch (error) {
        toast(error.message, 'error');
        if (error.status === 404) loadList();
    }
}

function newNote() {
    if (!confirmDiscard()) return;
    fillEditor({ id: null, title: '', content: '', summary: null });
    setView('editor');
    $('#note-title').focus();
}

async function saveNote(event) {
    event?.preventDefault();
    const editor = $('#editor');
    if (editor.hidden) return;
    const save = $('#save');
    const body = { title: $('#note-title').value.trim(), content: $('#note-content').value };
    const isNew = !state.note.id;

    showFieldErrors(editor, {});
    setBusy(save, true, 'Saving…');
    try {
        const { data } = await api(isNew ? '/notes' : `/notes/${state.note.id}`, { method: isNew ? 'POST' : 'PATCH', body });
        fillEditor(data);
        toast('Saved');
        await (state.mode === 'search' ? search(state.query) : loadList(isNew ? 1 : state.page));
        pollIndex(data.id);
    } catch (error) {
        showFieldErrors(editor, error.errors);
        if (!Object.keys(error.errors).length) toast(error.message, 'error');
    } finally {
        setBusy(save, false, 'Save');
    }
}

async function deleteNote() {
    if (!state.note?.id || !window.confirm(`Delete "${state.note.title}"? This can't be undone.`)) return;
    try {
        await api(`/notes/${state.note.id}`, { method: 'DELETE' });
        closeEditor();
        toast('Note deleted');
        await (state.mode === 'search' ? search(state.query) : loadList());
    } catch (error) {
        toast(error.message, 'error');
    }
}

async function summarize(refresh = false) {
    const button = $('#summarize');
    const id = state.note?.id;
    if (!id) return;
    $('#summary-error').textContent = '';
    setBusy(button, true, 'Summarizing…');
    $('#regenerate').disabled = true;
    try {
        const { data } = await api(`/notes/${id}/summary`, { method: 'POST', body: { refresh } });
        if (state.note?.id !== id) return; // user moved on while we waited
        state.note = { ...state.note, summary: data.summary, summary_generated_at: data.generated_at };
        renderSummary(data.cached ? null : data.model);
    } catch (error) {
        $('#summary-error').textContent = error.message;
    } finally {
        setBusy(button, false, 'Summarize');
        $('#regenerate').disabled = false;
        setDirty(state.dirty);
    }
}

// Embeddings are generated by a queue worker; refresh the "indexing" badge until it lands.
async function pollIndex(id, tries = INDEX_POLL_TRIES) {
    if (tries <= 0 || state.note?.id !== id || state.note.is_indexed) return;
    await new Promise((resolve) => setTimeout(resolve, INDEX_POLL_MS));
    if (state.note?.id !== id) return;
    try {
        const { data } = await api(`/notes/${id}`);
        if (state.note?.id !== id) return;
        state.note.is_indexed = data.is_indexed;
        renderIndexState();
        const listed = state.notes.find((n) => n.id === id);
        if (listed && data.is_indexed && !listed.is_indexed) {
            listed.is_indexed = true;
            renderList();
        }
        if (!data.is_indexed) pollIndex(id, tries - 1);
    } catch { /* badge just stays as is */ }
}

// ---------- Boot ----------

function enterWorkspace() {
    $('#auth').hidden = true;
    $('#workspace').hidden = false;
    $('#user-name').textContent = state.user?.name ?? '';
    closeEditor();
    loadList(1);
}

function bindEvents() {
    document.querySelectorAll('.segmented button').forEach((b) => b.addEventListener('click', () => setAuthMode(b.dataset.mode)));
    $('#auth-form').addEventListener('submit', submitAuth);
    $('#logout').addEventListener('click', logout);

    $('#search-form').addEventListener('submit', (event) => {
        event.preventDefault();
        const query = $('#search-input').value.trim();
        if (query.length >= 2) search(query);
        else if (!query) loadList(1);
        else toast('Type at least 2 characters to search.');
    });
    $('#search-input').addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { clearSearch(); event.target.blur(); }
    });

    $('#new-note').addEventListener('click', newNote);
    $('#prev-page').addEventListener('click', () => loadList(state.page - 1));
    $('#next-page').addEventListener('click', () => loadList(state.page + 1));

    $('#editor').addEventListener('submit', saveNote);
    $('#editor').addEventListener('input', (event) => {
        if (event.target.classList.contains('autosize')) autosize(event.target);
        if (event.target.id === 'note-content') updateCharCount();
        if (!state.dirty) setDirty(true);
    });
    $('#note-title').addEventListener('keydown', (event) => {
        if (event.key === 'Enter') { event.preventDefault(); $('#note-content').focus(); } // titles are one line
    });
    $('#delete').addEventListener('click', deleteNote);
    $('#summarize').addEventListener('click', () => summarize(false));
    $('#regenerate').addEventListener('click', () => summarize(true));
    $('#back').addEventListener('click', () => setView('list'));

    document.addEventListener('keydown', (event) => {
        const typing = ['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName);
        if (event.key === '/' && !typing && !$('#workspace').hidden) {
            event.preventDefault();
            $('#search-input').focus();
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's' && !$('#workspace').hidden) {
            event.preventDefault();
            saveNote();
        }
    });
    window.addEventListener('beforeunload', (event) => {
        if (state.dirty) event.preventDefault();
    });
}

async function boot() {
    bindEvents();
    if (state.token) {
        try {
            const { data } = await api('/auth/me');
            state.user = data;
            $('#boot').hidden = true;
            return enterWorkspace();
        } catch { /* invalid/expired token: fall through to login */ }
    }
    $('#boot').hidden = true;
    signOut();
}

boot();
