import { beforeEach, describe, expect, it } from 'vitest';

import { useBookingStore } from './bookingStore';

describe('useBookingStore', () => {
    beforeEach(() => {
        useBookingStore.getState().reset();
        sessionStorage.clear();
    });

    it('starts at step 1 with empty state', () => {
        const s = useBookingStore.getState();
        expect(s.currentStep).toBe(1);
        expect(s.selectedSalaId).toBeNull();
        expect(s.criteria.servicios).toEqual([]);
    });

    it('goNext advances and clamps at 8', () => {
        const store = useBookingStore;
        store.getState().setStep(7);
        store.getState().goNext();
        expect(store.getState().currentStep).toBe(8);
        store.getState().goNext();
        expect(store.getState().currentStep).toBe(8);
    });

    it('goBack decrements and clamps at 1', () => {
        const store = useBookingStore;
        store.getState().setStep(2);
        store.getState().goBack();
        expect(store.getState().currentStep).toBe(1);
        store.getState().goBack();
        expect(store.getState().currentStep).toBe(1);
    });

    it('setCriteria merges partial updates', () => {
        const store = useBookingStore;
        store.getState().setCriteria({ aforoMin: 5 });
        store.getState().setCriteria({ servicios: [1, 2] });
        const c = store.getState().criteria;
        expect(c.aforoMin).toBe(5);
        expect(c.servicios).toEqual([1, 2]);
        expect(c.aforoMax).toBeNull();
    });

    it('patchProfile merges fields', () => {
        const store = useBookingStore;
        store.getState().patchProfile({ email: 'u@example.test', nombre: 'Ana' });
        const p = store.getState().profile;
        expect(p.email).toBe('u@example.test');
        expect(p.nombre).toBe('Ana');
        expect(p.primer_apellido).toBe('');
    });

    it('reset restores initial state', () => {
        const store = useBookingStore;
        store.getState().setStep(5);
        store.getState().setSelectedSala(42);
        store.getState().patchProfile({ nombre: 'X' });
        store.getState().reset();

        const s = store.getState();
        expect(s.currentStep).toBe(1);
        expect(s.selectedSalaId).toBeNull();
        expect(s.profile.nombre).toBe('');
    });

    it('persists non-ephemeral fields into sessionStorage', async () => {
        const store = useBookingStore;
        store.getState().setSelectedSala(7);
        store.getState().setTurnstileToken('secret');

        // Zustand persist writes synchronously in modern versions, but allow
        // one microtask for the persistence middleware to flush.
        await Promise.resolve();

        const raw = sessionStorage.getItem('reservas-aldealab-flow');
        expect(raw).not.toBeNull();

        const parsed = JSON.parse(raw as string) as { state: Record<string, unknown> };
        expect(parsed.state.selectedSalaId).toBe(7);
        expect('turnstileToken' in parsed.state).toBe(false);
    });
});
