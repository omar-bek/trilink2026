/**
 * TriLink API client — JWT authenticated.
 * Token stored in localStorage under "trilink_token".
 */
const API_BASE = '/api';

function getToken() {
    return localStorage.getItem('trilink_token');
}

function setToken(token) {
    if (token) localStorage.setItem('trilink_token', token);
    else localStorage.removeItem('trilink_token');
}

async function request(path, options = {}) {
    const token = getToken();
    const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(options.headers || {}),
    };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const res = await fetch(`${API_BASE}${path}`, { ...options, headers });

    if (res.status === 401) {
        setToken(null);
        // Could redirect to login here
    }

    const text = await res.text();
    let data = null;
    try { data = text ? JSON.parse(text) : null; } catch { data = text; }

    if (!res.ok) {
        const err = new Error(data?.message || `HTTP ${res.status}`);
        err.status = res.status;
        err.data = data;
        throw err;
    }
    return data;
}

window.api = {
    get: (path) => request(path),
    post: (path, body) => request(path, { method: 'POST', body: JSON.stringify(body) }),
    put: (path, body) => request(path, { method: 'PUT', body: JSON.stringify(body) }),
    patch: (path, body) => request(path, { method: 'PATCH', body: JSON.stringify(body) }),
    delete: (path) => request(path, { method: 'DELETE' }),

    setToken,
    getToken,

    // Domain helpers
    auth: {
        login: (email, password) => request('/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) }),
        me: () => request('/auth/me'),
        logout: () => request('/auth/logout', { method: 'POST' }),
    },
    purchaseRequests: {
        list: (params = '') => request(`/purchase-requests${params}`),
        get: (id) => request(`/purchase-requests/${id}`),
        create: (data) => request('/purchase-requests', { method: 'POST', body: JSON.stringify(data) }),
    },
    rfqs: {
        list: (params = '') => request(`/rfqs${params}`),
        get: (id) => request(`/rfqs/${id}`),
        compareBids: (id) => request(`/rfqs/${id}/bids/compare`),
    },
    bids: {
        list: (params = '') => request(`/bids${params}`),
        get: (id) => request(`/bids/${id}`),
        evaluate: (id, action) => request(`/bids/${id}/evaluate`, { method: 'POST', body: JSON.stringify({ action }) }),
    },
    contracts: {
        list: (params = '') => request(`/contracts${params}`),
        get: (id) => request(`/contracts/${id}`),
    },
    shipments: {
        list: (params = '') => request(`/shipments${params}`),
        get: (id) => request(`/shipments/${id}`),
        track: (id) => request(`/shipments/${id}/tracking-events`),
    },
    payments: {
        list: (params = '') => request(`/payments${params}`),
        get: (id) => request(`/payments/${id}`),
    },
    disputes: {
        list: (params = '') => request(`/disputes${params}`),
        get: (id) => request(`/disputes/${id}`),
    },
};

console.log('[TriLink API] client ready');
