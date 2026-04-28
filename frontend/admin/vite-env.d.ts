/// <reference types="vite/client" />

interface ReservasAdminBootstrap {
    restBase: string;
    nonce: string;
    locale: string;
    adminUrl: string;
    /** URL of the customer-supplied logo; null when no file is present. */
    logoUrl: string | null;
}

declare global {
    interface Window {
        ReservasAldealabAdmin?: ReservasAdminBootstrap;
    }
}

export {};
