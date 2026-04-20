import { useQuery } from '@tanstack/react-query';

import { api } from './client';
import type { Sala, SpacesQuery } from '../types/sala';

interface SpacesResponse {
    items: Sala[];
    total: number;
}

export function useSpaces(filters: SpacesQuery) {
    return useQuery({
        queryKey: ['spaces', filters],
        queryFn: () => api.get<SpacesResponse>('/spaces', filters as Record<string, unknown>),
    });
}

export function useSpace(id: number | null) {
    return useQuery({
        queryKey: ['spaces', id],
        queryFn: () => api.get<Sala>(`/spaces/${id ?? 0}`),
        enabled: id !== null && id > 0,
    });
}
