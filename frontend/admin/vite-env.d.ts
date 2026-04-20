/// <reference types="vite/client" />

interface ReservasAdminBootstrap {
    restBase: string;
    nonce: string;
    locale: string;
    adminUrl: string;
}

declare global {
    interface Window {
        ReservasAldealabAdmin?: ReservasAdminBootstrap;
    }
}

export {};
