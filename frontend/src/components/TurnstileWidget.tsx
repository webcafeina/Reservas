import { useEffect, useRef } from 'react';

const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

interface TurnstileWidgetProps {
    siteKey: string;
    onVerify: (token: string) => void;
    onError?: () => void;
    onExpire?: () => void;
    theme?: 'light' | 'dark' | 'auto';
}

interface TurnstileGlobal {
    render(
        container: HTMLElement,
        options: {
            sitekey: string;
            callback: (token: string) => void;
            'error-callback'?: (() => void) | undefined;
            'expired-callback'?: (() => void) | undefined;
            theme?: 'light' | 'dark' | 'auto' | undefined;
        },
    ): string;
    remove(widgetId: string): void;
}

declare global {
    interface Window {
        turnstile?: TurnstileGlobal;
    }
}

function loadScript(): Promise<void> {
    if (document.querySelector(`script[src^="${SCRIPT_SRC}"]`) !== null) {
        return Promise.resolve();
    }
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT_SRC;
        script.async = true;
        script.defer = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Turnstile script failed to load'));
        document.head.appendChild(script);
    });
}

async function waitForTurnstile(timeoutMs = 10000): Promise<TurnstileGlobal> {
    const deadline = Date.now() + timeoutMs;
    // eslint-disable-next-line no-constant-condition
    while (true) {
        if (window.turnstile !== undefined) {
            return window.turnstile;
        }
        if (Date.now() > deadline) {
            throw new Error('Turnstile did not become available in time');
        }
        await new Promise((r) => setTimeout(r, 100));
    }
}

export function TurnstileWidget({
    siteKey,
    onVerify,
    onError,
    onExpire,
    theme = 'auto',
}: TurnstileWidgetProps): JSX.Element {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const widgetIdRef = useRef<string | null>(null);

    // Callers usually pass inline arrows for onError/onExpire, which create
    // a fresh function reference on every render. Including them in the
    // mount effect's deps caused the widget to remount continuously and the
    // Turnstile challenge stayed stuck on "Verificando…". We keep callbacks
    // in refs so the mount effect only restarts when siteKey/theme change.
    const onVerifyRef = useRef(onVerify);
    const onErrorRef = useRef(onError);
    const onExpireRef = useRef(onExpire);
    onVerifyRef.current = onVerify;
    onErrorRef.current = onError;
    onExpireRef.current = onExpire;

    useEffect(() => {
        let cancelled = false;

        const mount = async (): Promise<void> => {
            try {
                await loadScript();
                const ts = await waitForTurnstile();
                if (cancelled || containerRef.current === null) return;
                widgetIdRef.current = ts.render(containerRef.current, {
                    sitekey: siteKey,
                    callback: (token: string) => onVerifyRef.current(token),
                    'error-callback': () => onErrorRef.current?.(),
                    'expired-callback': () => onExpireRef.current?.(),
                    theme,
                });
            } catch {
                if (!cancelled) onErrorRef.current?.();
            }
        };

        void mount();

        return () => {
            cancelled = true;
            if (widgetIdRef.current !== null && window.turnstile !== undefined) {
                window.turnstile.remove(widgetIdRef.current);
                widgetIdRef.current = null;
            }
        };
    }, [siteKey, theme]);

    return <div ref={containerRef} />;
}
