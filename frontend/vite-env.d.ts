/// <reference types="vite/client" />

interface ReservasBootstrap {
    restBase: string;
    nonce: string;
    turnstileSiteKey: string;
    locale: string;
    isLoggedIn: boolean;
    /** Footer signature; null when hidden in the public form. */
    firma: string | null;
}

declare global {
    interface Window {
        ReservasAldealab?: ReservasBootstrap;
    }
}

export {};
