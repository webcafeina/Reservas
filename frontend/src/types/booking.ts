import type { UserProfile } from './profile';

export type BookingState = 'pendiente' | 'confirmada' | 'cancelada' | 'finalizada';

export interface BookingConflict {
    fecha: string;
    booking_id: number;
    hora_inicio: string;
    hora_fin: string;
}

export interface AvailabilityResponse {
    disponible: boolean;
    conflictos: BookingConflict[];
    fechas_evaluadas: string[];
}

export interface BookingPayload {
    sala_id: number;
    hora_inicio: string;
    hora_fin: string;
    fecha_inicio: string;
    fecha_fin_serie: string | null;
    rrule: string | null;
    fechas_excluidas: string[];
    objeto_reserva: string;
    profile: UserProfile;
    turnstile_token: string | null;
    cpa_items?: Array<{ item_type: string; item_label: string }>;
}

export interface Booking {
    id: number;
    uuid: string;
    user_id: number | null;
    profile_id: number | null;
    sala_id: number;
    /** Set by admin endpoints that JOIN posts; null on public payloads. */
    sala_title?: string | null;
    estado: BookingState;
    hora_inicio: string;
    hora_fin: string;
    rrule: string | null;
    fecha_inicio: string;
    fecha_fin_serie: string | null;
    objeto_reserva: string;
    nota_admin: string | null;
    created_at: string | null;
    updated_at: string | null;
    fechas: string[];
    /** Set by admin endpoints that JOIN user_profiles; null otherwise. */
    profile?: UserProfile | null;
}
