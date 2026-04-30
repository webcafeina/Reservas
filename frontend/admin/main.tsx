import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import { AdminApp } from './AdminApp';
import { registerQueryClient } from './state/sessionExpired';
import '../src/styles/tokens.css';
import './styles/admin.css';

const MOUNT_ID = 'reservas-admin-app';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            retry: 1,
            staleTime: 30_000,
            refetchOnWindowFocus: true,
            refetchOnReconnect: true,
        },
    },
});

// Lets the session-expired store cancel and clear all queries when the
// nonce dies, so we stop polling REST in a loop with a dead credential.
registerQueryClient(queryClient);

function mount(): void {
    const container = document.getElementById(MOUNT_ID);
    if (!container) return;

    createRoot(container).render(
        <StrictMode>
            <QueryClientProvider client={queryClient}>
                <AdminApp />
            </QueryClientProvider>
        </StrictMode>,
    );
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
} else {
    mount();
}
