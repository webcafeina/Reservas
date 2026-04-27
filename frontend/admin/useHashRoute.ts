import { useEffect, useState } from 'react';

export type AdminView =
    | { name: 'dashboard' }
    | { name: 'calendar' }
    | { name: 'bookings' }
    | { name: 'bookings-new' }
    | { name: 'booking'; id: number }
    | { name: 'settings' };

function parse(hash: string): AdminView {
    const clean = hash.replace(/^#\/?/, '').trim();
    if (clean === '' || clean === 'dashboard') {
        return { name: 'dashboard' };
    }
    if (clean === 'calendar') {
        return { name: 'calendar' };
    }
    if (clean === 'settings') {
        return { name: 'settings' };
    }
    if (clean === 'bookings') {
        return { name: 'bookings' };
    }
    // Must come before the /(\d+)/ match below because `new` isn't a number
    // but we still want a distinct view from the generic list.
    if (clean === 'bookings/new') {
        return { name: 'bookings-new' };
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
