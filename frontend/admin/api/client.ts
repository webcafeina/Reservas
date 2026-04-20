/**
 * Admin-specific fetch wrapper. Separate from the public `api/client.ts`
 * because it reads from `window.ReservasAldealabAdmin` (different nonce
 * and base URL bootstrap).
 */

import { ApiError, type ApiErrorPayload } from '../../src/api/client';

function getBootstrap(): { restBase: string; nonce: string } {
    const w = typeof window !== 'undefined' ? window.ReservasAldealabAdmin : undefined;
    return {
        restBase: w?.restBase ?? '/wp-json/reservas/v1',
        nonce: w?.nonce ?? '',
    };
}

function joinUrl(base: string, path: string): string {
    return `${base.replace(/\/$/, '')}/${path.replace(/^\//, '')}`;
}

async function request<T>(
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
    path: string,
    options: { query?: Record<string, unknown>; body?: unknown } = {},
): Promise<T> {
    const { restBase, nonce } = getBootstrap();
    let url = joinUrl(restBase, path);

    if (options.query !== undefined) {
        const params = new URLSearchParams();
        for (const [k, v] of Object.entries(options.query)) {
            if (v === undefined || v === null || v === '') continue;
            if (Array.isArray(v)) {
                for (const item of v)
                    params.append(`${k}[]`, String(item as string | number | boolean));
            } else {
                params.append(k, String(v as string | number | boolean));
            }
        }
        const qs = params.toString();
        if (qs.length > 0) url = `${url}?${qs}`;
    }

    const headers: Record<string, string> = { Accept: 'application/json' };
    if (nonce !== '') headers['X-WP-Nonce'] = nonce;
    if (options.body !== undefined) headers['Content-Type'] = 'application/json';

    const res = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        ...(options.body !== undefined ? { body: JSON.stringify(options.body) } : {}),
    });

    const text = await res.text();
    const json = text.length > 0 ? (JSON.parse(text) as unknown) : null;

    if (!res.ok) {
        const payload =
            json !== null && typeof json === 'object'
                ? (json as ApiErrorPayload)
                : { code: 'rest_unknown', message: `HTTP ${res.status}` };
        throw new ApiError(res.status, payload);
    }

    return json as T;
}

export const adminApi = {
    get: <T>(path: string, query?: Record<string, unknown>) =>
        request<T>('GET', path, query !== undefined ? { query } : {}),
    put: <T>(path: string, body?: unknown) =>
        request<T>('PUT', path, body !== undefined ? { body } : {}),
    patch: <T>(path: string, body?: unknown) =>
        request<T>('PATCH', path, body !== undefined ? { body } : {}),
    delete: <T>(path: string) => request<T>('DELETE', path),
};
