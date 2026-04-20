import { useQuery } from '@tanstack/react-query';

/**
 * Fetches WP taxonomy terms. We hit the core REST namespace (wp/v2) rather
 * than our plugin namespace because WP already exposes terms with filters
 * and pagination — no need to re-implement.
 */

export interface Term {
    id: number;
    name: string;
    slug: string;
    count: number;
}

interface RawTerm {
    id: number;
    name: string;
    slug: string;
    count: number;
}

function getBootstrap(): string {
    return window.ReservasAldealab?.restBase ?? '/wp-json/reservas/v1';
}

/**
 * Build the WP core terms URL from the reservas restBase:
 *   /wp-json/reservas/v1 → /wp-json/wp/v2/<rest_base>
 */
function termsUrl(restBase: string): string {
    const marker = '/wp-json/';
    const idx = getBootstrap().indexOf(marker);
    const siteRoot = idx >= 0 ? getBootstrap().slice(0, idx + marker.length) : '/wp-json/';
    return `${siteRoot}wp/v2/${restBase}?per_page=100&_fields=id,name,slug,count`;
}

async function fetchTerms(restBase: string): Promise<Term[]> {
    const res = await fetch(termsUrl(restBase), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) {
        throw new Error(`Failed to load ${restBase}: ${res.status}`);
    }
    const raw = (await res.json()) as RawTerm[];
    return raw.map((t) => ({
        id: Number(t.id),
        name: String(t.name),
        slug: String(t.slug),
        count: Number(t.count),
    }));
}

export function useServicios() {
    return useQuery({
        queryKey: ['taxonomy', 'servicios'],
        queryFn: () => fetchTerms('servicios'),
    });
}

export function useEdificios() {
    return useQuery({
        queryKey: ['taxonomy', 'edificios'],
        queryFn: () => fetchTerms('edificios'),
    });
}
