const config = window.__LOGSCOPE__ ?? {};
const apiBase = config.apiBase ?? '/logscope/api';

function csrf() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? config.csrfToken ?? '';
}

async function request(path, params = {}) {
    const url = new URL(apiBase + path, window.location.origin);
    Object.entries(params).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') {
            url.searchParams.set(k, v);
        }
    });

    const res = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (res.status === 401 || res.status === 419) {
        // Session expired — bounce to the login screen.
        window.location.assign((config.prefix ?? '/logscope') + '/login');
        throw new Error('Unauthorized');
    }

    if (!res.ok) {
        throw new Error(`Request failed (${res.status})`);
    }

    return res.json();
}

export const api = {
    config,

    sources() {
        return request('/sources').then((r) => r.data ?? []);
    },

    files(source) {
        return request('/files', { source }).then((r) => r.data ?? []);
    },

    entries(fileId, { cursor, level, q, perPage = 50 } = {}) {
        return request(`/files/${encodeURIComponent(fileId)}/entries`, {
            cursor,
            level: level && level.length ? level.join(',') : null,
            q,
            per_page: perPage,
        });
    },

    // Cross-file search across every file in a source.
    search(source, { cursor, level, q, perPage = 50 } = {}) {
        return request('/search', {
            source,
            cursor,
            level: level && level.length ? level.join(',') : null,
            q,
            per_page: perPage,
        });
    },

    entry(entryId) {
        return request(`/entries/${encodeURIComponent(entryId)}`).then((r) => r.data);
    },

    // Ends the session login. Submits a real CSRF-protected form so the browser
    // follows the server's redirect to the login screen.
    logout() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = config.logoutUrl ?? (config.prefix ?? '/logscope') + '/logout';

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrf();
        form.appendChild(token);

        document.body.appendChild(form);
        form.submit();
    },
};
