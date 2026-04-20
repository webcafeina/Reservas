/// <reference types="vite/client" />

interface ReservasBootstrap {
    restBase: string;
    nonce: string;
    turnstileSiteKey: string;
    locale: string;
    isLoggedIn: boolean;
}

declare global {
    interface Window {
        ReservasAldealab?: ReservasBootstrap;
    }
}

export {};
