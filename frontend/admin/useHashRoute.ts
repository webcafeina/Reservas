import { useEffect, useState } from 'react';

export type AdminView =
    | { name: 'dashboard' }
    | { name: 'bookings' }
    | { name: 'booking'; id: number }
    | { name: 'settings' };

function parse(hash: string): AdminView {
    const clean = hash.replace(/^#\/?/, '').trim();
    if (clean === '' || clean === 'dashboard') {
        return { name: 'dashboard' };
    }
    if (clean === 'settings') {
        return { name: 'settings' };
    }
    if (clean === 'bookings') {
        return { name: 'bookings' };
    }
    const bookingMatch = /^bookings\/(\d+)$/.exec(clean);
    if (bookingMatch) {
        return { name: 'booking', id: Number(bookingMatch[1]) };
    }
    return { name: 'dashboard' };
}

export function useHashRoute(): AdminView {
    const [view, setView] = useState<AdminView>(() => parse(window.location.hash));

    useEffect(() => {
        const handler = (): void => setView(parse(window.location.hash));
        window.addEventListener('hashchange', handler);
        return () => window.removeEventListener('hashchange', handler);
    }, []);

    return view;
}

export function navigate(path: string): void {
    window.location.hash = '#/' + path.replace(/^#\/?/, '');
}
