import { useMutation } from '@tanstack/react-query';

import { api } from './client';
import type { Booking, BookingPayload } from '../types/booking';

interface BookingResponse {
    success: true;
    booking: Booking;
}

export function useCreateBooking() {
    return useMutation({
        mutationFn: (payload: BookingPayload) => api.post<BookingResponse>('/bookings', payload),
    });
}
