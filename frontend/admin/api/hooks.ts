import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { adminApi } from './client';
import type { Booking, BookingState } from '../../src/types/booking';
import type { UserProfile } from '../../src/types/profile';

export interface BookingListResponse {
    items: Booking[];
    total: number;
    page: number;
    per_page: number;
}

export type BookingSort = 'created_desc' | 'start_desc' | 'start_asc';

export interface BookingFilters {
    estado?: BookingState | '' | undefined;
    sala_id?: number | undefined;
    email?: string | undefined;
    from?: string | undefined;
    to?: string | undefined;
    sort?: BookingSort | undefined;
    page?: number | undefined;
    per_page?: number | undefined;
}

export function useAdminBookings(filters: BookingFilters) {
    return useQuery({
        queryKey: ['admin', 'bookings', filters],
        queryFn: () =>
            adminApi.get<BookingListResponse>(
                '/admin/bookings',
                filters as Record<string, unknown>,
            ),
        staleTime: 15_000,
    });
}

export function useAdminBooking(id: number | null) {
    return useQuery({
        queryKey: ['admin', 'bookings', id],
        queryFn: () => adminApi.get<Booking>(`/admin/bookings/${id ?? 0}`),
        enabled: id !== null && id > 0,
    });
}

export function useUpdateBooking() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: ({ id, payload }: { id: number; payload: Partial<Booking> }) =>
            adminApi.patch<Booking>(`/admin/bookings/${id}`, payload),
        onSuccess: async () => {
            await qc.invalidateQueries({ queryKey: ['admin', 'bookings'] });
        },
    });
}

export function useDeleteBooking() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (id: number) =>
            adminApi.delete<{ deleted: boolean; id: number }>(`/admin/bookings/${id}`),
        onSuccess: async () => {
            await qc.invalidateQueries({ queryKey: ['admin', 'bookings'] });
        },
    });
}

/**
 * Admin "manual" booking payload — same core shape as the public
 * BookingPayload (sans turnstile_token) plus admin-only extras.
 */
export interface AdminBookingPayload {
    sala_id: number;
    hora_inicio: string;
    hora_fin: string;
    fecha_inicio: string;
    fecha_fin_serie: string | null;
    rrule: string | null;
    fechas_excluidas: string[];
    objeto_reserva: string;
    profile: UserProfile;
    estado?: BookingState;
    force_override?: boolean;
    suppress_notifications?: boolean;
    nota_admin?: string;
}

export function useCreateAdminBooking() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (payload: AdminBookingPayload) =>
            adminApi.post<{ success: true; booking: Booking }>('/admin/bookings', payload),
        onSuccess: async () => {
            await qc.invalidateQueries({ queryKey: ['admin', 'bookings'] });
            await qc.invalidateQueries({ queryKey: ['admin', 'stats'] });
        },
    });
}

/**
 * Full edit of an existing booking: PUT /admin/bookings/{id}. Same payload
 * shape as create + an explicit `notify_user` flag (defaults to true on
 * the backend) that controls whether the modified-booking email goes out.
 */
export interface AdminBookingUpdatePayload extends AdminBookingPayload {
    notify_user?: boolean;
}

export function useUpdateAdminBooking() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: ({ id, payload }: { id: number; payload: AdminBookingUpdatePayload }) =>
            adminApi.put<{ success: true; booking: Booking }>(`/admin/bookings/${id}`, payload),
        onSuccess: async (_data, vars) => {
            await qc.invalidateQueries({ queryKey: ['admin', 'bookings'] });
            await qc.invalidateQueries({ queryKey: ['admin', 'bookings', vars.id] });
            await qc.invalidateQueries({ queryKey: ['admin', 'stats'] });
            await qc.invalidateQueries({ queryKey: ['admin', 'calendar'] });
        },
    });
}

export interface AdminStats {
    /** Lifetime count per estado, past + future. Not date-filtered. */
    by_state: Record<BookingState, number>;
    /** Bookings whose fecha_inicio falls in the current ISO week. */
    this_week: number;
    /** Confirmed bookings in the next 7 days from today. */
    upcoming: number;
}

export function useAdminStats() {
    return useQuery({
        queryKey: ['admin', 'stats'],
        queryFn: () => adminApi.get<AdminStats>('/admin/stats'),
        staleTime: 60_000,
    });
}

export interface Settings {
    admin_emails: string[];
    turnstile_site_key: string;
    turnstile_secret: string;
    sede_url: string;
    sede_tramite_url: string;
    pdftk_path: string;
    vite_dev_url: string;
    email_intro_user: string;
    email_intro_admin: string;
    delete_on_uninstall: boolean;
    sms_provider: 'none' | 'twilio';
    twilio_account_sid: string;
    twilio_auth_token: string;
    twilio_from_number: string;
}

export function useSettings() {
    return useQuery({
        queryKey: ['admin', 'settings'],
        queryFn: () => adminApi.get<Settings>('/admin/settings'),
        staleTime: 60_000,
    });
}

export function useUpdateSettings() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (payload: Partial<Settings>) =>
            adminApi.put<Settings>('/admin/settings', payload),
        onSuccess: (data) => {
            qc.setQueryData(['admin', 'settings'], data);
        },
    });
}

export interface PdfTemplate {
    key: string;
    filename: string;
    source: 'custom' | 'packaged';
    uploaded_at: string | null;
    fields_ok: boolean;
}

export function usePdfTemplates() {
    return useQuery({
        queryKey: ['admin', 'pdf-templates'],
        queryFn: () => adminApi.get<{ items: PdfTemplate[] }>('/admin/pdf-templates'),
        staleTime: 30_000,
    });
}

export interface AdminLogoStatus {
    url: string | null;
    source: 'uploads' | 'packaged' | null;
    uploaded_at: string | null;
}

export function useAdminLogo() {
    return useQuery({
        queryKey: ['admin', 'logo'],
        queryFn: () => adminApi.get<AdminLogoStatus>('/admin/logo'),
        staleTime: 30_000,
    });
}

export interface CalendarEvent {
    id: string;
    booking_id: number;
    title: string;
    start: string;
    end: string;
    estado: BookingState;
    sala_title: string;
    solicitante: string;
    objeto: string;
    backgroundColor: string;
    borderColor: string;
}

export interface CalendarFilters {
    salaId?: number | null;
    estado?: BookingState | null;
}

/**
 * Calendar events for the admin Calendario tab. The range is set by the
 * FullCalendar `datesSet` callback; we keep the query keyed on the range
 * AND the filters so React Query caches per-view + per-filter combo.
 */
export function useCalendarEvents(
    from: string | null,
    to: string | null,
    filters: CalendarFilters = {},
) {
    const params: Record<string, unknown> = {
        from: from ?? '',
        to: to ?? '',
    };
    if (filters.salaId !== null && filters.salaId !== undefined && filters.salaId > 0) {
        params['sala_id'] = filters.salaId;
    }
    if (filters.estado !== null && filters.estado !== undefined) {
        params['estado'] = filters.estado;
    }

    return useQuery({
        queryKey: ['admin', 'calendar', from, to, filters.salaId ?? null, filters.estado ?? null],
        queryFn: () => adminApi.get<{ events: CalendarEvent[] }>('/admin/calendar', params),
        enabled: from !== null && to !== null,
        staleTime: 30_000,
    });
}

export type HealthSeverity = 'ok' | 'warn' | 'error' | 'info';

export interface HealthCheck {
    id: string;
    category: string;
    label: string;
    severity: HealthSeverity;
    message: string;
    fix_url: string | null;
}

export interface HealthResponse {
    summary: { ok: number; warn: number; error: number; info: number };
    checks: HealthCheck[];
}

/**
 * Health check status of every plugin dependency. Live HTTP probes happen
 * server-side, so this query is potentially slow (1-3s); we keep it
 * un-cached so each visit reflects the current state.
 */
export function useHealthChecks() {
    return useQuery({
        queryKey: ['admin', 'health'],
        queryFn: () => adminApi.get<HealthResponse>('/admin/health'),
        staleTime: 0,
        refetchOnMount: true,
    });
}

/**
 * Admin-specific URL builder for file downloads (CSV / iCal). We need
 * the absolute REST URL (including nonce in query string) because the
 * browser navigates to it directly for downloads.
 */
export function buildExportUrl(filters: BookingFilters): string {
    const base = window.ReservasAldealabAdmin?.restBase ?? '/wp-json/reservas/v1';
    const nonce = window.ReservasAldealabAdmin?.nonce ?? '';
    const params = new URLSearchParams();
    for (const [k, v] of Object.entries(filters)) {
        if (v === undefined || v === null || v === '') continue;
        params.append(k, String(v));
    }
    if (nonce !== '') params.append('_wpnonce', nonce);
    return `${base}/admin/bookings/export?${params.toString()}`;
}
