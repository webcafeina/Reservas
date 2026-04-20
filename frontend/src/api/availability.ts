import { useMutation } from '@tanstack/react-query';

import { api } from './client';
import type { AvailabilityResponse } from '../types/booking';

export interface AvailabilityRequest {
    sala_id: number;
    fecha_inicio: string;
    fecha_fin_serie?: string | null;
    hora_inicio: string;
    hora_fin: string;
    rrule?: string | null;
    fechas_excluidas?: string[];
}

export function useAvailability() {
    return useMutation({
        mutationFn: (payload: AvailabilityRequest) =>
            api.post<AvailabilityResponse>('/availability', payload),
    });
}
