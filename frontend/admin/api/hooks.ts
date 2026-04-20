import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { adminApi } from './client';
import type { Booking, BookingState } from '../../src/types/booking';

export interface BookingListResponse {
    items: Booking[];
    total: number;
    page: number;
    per_page: number;
}

export interface BookingFilters {
    estado?: BookingState | '';
    sala_id?: number;
    email?: string;
    from?: string;
    to?: string;
    page?: number;
    per_page?: number;
}

export function useAdminBookings(filters: BookingFilters) {
    return useQuery({
        queryKey: ['admin', 'bookings', filters],
        queryFn: () =>
            adminApi.get<BookingListResponse>('/admin/bookings', filters as Record<string, unknown>),
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
        mutationFn: (id: number) => adminApi.delete<{ deleted: boolean; id: number }>(`/admin/bookings/${id}`),
        onSuccess: async () => {
            await qc.invalidateQueries({ queryKey: ['admin', 'bookings'] });
        },
    });
}

export interface AdminStats {
    by_state: Record<BookingState, number>;
    this_week: number;
    upcoming: number;
    per_sala: Array<{ sala_id: number; title: string; total: number }>;
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
        mutationFn: (payload: Partial<Settings>) => adminApi.put<Settings>('/admin/settings', payload),
        onSuccess: async (data) => {
            qc.setQueryData(['admin', 'settings'], data);
        },
    });
}
