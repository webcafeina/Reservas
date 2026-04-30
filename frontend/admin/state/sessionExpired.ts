/**
 * Global flag for "the WP REST nonce / auth cookie has expired". The admin
 * client (`api/client.ts`) calls `markSessionExpired()` when it receives a
 * 401/403 with a nonce/cookie-related error code. The flag is one-way: once
 * set, the only way out is `window.location.reload()`, which destroys the
 * SPA and pulls a fresh nonce from PHP.
 *
 * On expiry we also cancel and clear all React Query state so any in-flight
 * polling stops immediately and we don't keep hammering REST endpoints with
 * a dead nonce in a loop.
 */

import { create } from 'zustand';
import type { QueryClient } from '@tanstack/react-query';

interface SessionExpiredState {
    isExpired: boolean;
    markExpired: () => void;
}

let registeredQueryClient: QueryClient | null = null;

export function registerQueryClient(qc: QueryClient): void {
    registeredQueryClient = qc;
}

export const useSessionExpired = create<SessionExpiredState>((set, get) => ({
    isExpired: false,
    markExpired: () => {
        if (get().isExpired) return;
        set({ isExpired: true });
        if (registeredQueryClient !== null) {
            void registeredQueryClient.cancelQueries();
            registeredQueryClient.getQueryCache().clear();
        }
    },
}));

export function markSessionExpired(): void {
    useSessionExpired.getState().markExpired();
}
