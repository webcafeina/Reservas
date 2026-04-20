import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';

import type { Booking } from '../types/booking';
import { emptyProfile, type UserProfile } from '../types/profile';
import { defaultRruleInput, type RruleInput } from './buildRrule';

export type StepId = 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8;

export interface Criteria {
    aforoMin: number | null;
    aforoMax: number | null;
    servicios: number[];
}

export type DateMode = 'single' | 'recurring';

export interface BookingState {
    currentStep: StepId;

    criteria: Criteria;
    selectedSalaId: number | null;

    dateMode: DateMode;
    fechaInicio: string; // YYYY-MM-DD
    fechaFinSerie: string | null;
    rruleInput: RruleInput;
    fechasExcluidas: string[];

    horaInicio: string; // HH:MM
    horaFin: string; // HH:MM

    objetoReserva: string;
    profile: UserProfile;

    turnstileToken: string | null;

    confirmedBooking: Booking | null;

    // Actions
    setStep: (s: StepId) => void;
    goNext: () => void;
    goBack: () => void;
    setCriteria: (c: Partial<Criteria>) => void;
    setSelectedSala: (id: number | null) => void;
    setDateMode: (m: DateMode) => void;
    setFechaInicio: (d: string) => void;
    setFechaFinSerie: (d: string | null) => void;
    setRruleInput: (r: Partial<RruleInput>) => void;
    setRruleInputFull: (r: RruleInput) => void;
    setFechasExcluidas: (d: string[]) => void;
    setHora: (inicio: string, fin: string) => void;
    setObjeto: (o: string) => void;
    setProfile: (p: UserProfile) => void;
    patchProfile: (p: Partial<UserProfile>) => void;
    setTurnstileToken: (t: string | null) => void;
    setConfirmedBooking: (b: Booking | null) => void;
    reset: () => void;
}

const initialCriteria = (): Criteria => ({
    aforoMin: null,
    aforoMax: null,
    servicios: [],
});

const initialState = (): Omit<
    BookingState,
    | 'setStep' | 'goNext' | 'goBack' | 'setCriteria' | 'setSelectedSala'
    | 'setDateMode' | 'setFechaInicio' | 'setFechaFinSerie' | 'setRruleInput'
    | 'setRruleInputFull' | 'setFechasExcluidas' | 'setHora' | 'setObjeto'
    | 'setProfile' | 'patchProfile' | 'setTurnstileToken' | 'setConfirmedBooking'
    | 'reset'
> => ({
    currentStep: 1,
    criteria: initialCriteria(),
    selectedSalaId: null,
    dateMode: 'single',
    fechaInicio: '',
    fechaFinSerie: null,
    rruleInput: defaultRruleInput(),
    fechasExcluidas: [],
    horaInicio: '09:00',
    horaFin: '10:00',
    objetoReserva: '',
    profile: emptyProfile(),
    turnstileToken: null,
    confirmedBooking: null,
});

const clampStep = (s: number): StepId => {
    if (s < 1) return 1;
    if (s > 8) return 8;
    return s as StepId;
};

export const useBookingStore = create<BookingState>()(
    persist(
        (set) => ({
            ...initialState(),

            setStep: (s) => set({ currentStep: clampStep(s) }),
            goNext: () => set((state) => ({ currentStep: clampStep(state.currentStep + 1) })),
            goBack: () => set((state) => ({ currentStep: clampStep(state.currentStep - 1) })),

            setCriteria: (c) =>
                set((state) => ({ criteria: { ...state.criteria, ...c } })),

            setSelectedSala: (id) => set({ selectedSalaId: id }),

            setDateMode: (m) => set({ dateMode: m }),
            setFechaInicio: (d) => set({ fechaInicio: d }),
            setFechaFinSerie: (d) => set({ fechaFinSerie: d }),

            setRruleInput: (r) =>
                set((state) => ({ rruleInput: { ...state.rruleInput, ...r } })),
            setRruleInputFull: (r) => set({ rruleInput: r }),

            setFechasExcluidas: (d) => set({ fechasExcluidas: d }),

            setHora: (inicio, fin) => set({ horaInicio: inicio, horaFin: fin }),
            setObjeto: (o) => set({ objetoReserva: o }),

            setProfile: (p) => set({ profile: p }),
            patchProfile: (p) =>
                set((state) => ({ profile: { ...state.profile, ...p } })),

            setTurnstileToken: (t) => set({ turnstileToken: t }),
            setConfirmedBooking: (b) => set({ confirmedBooking: b }),

            reset: () => set(initialState()),
        }),
        {
            name: 'reservas-aldealab-flow',
            version: 1,
            storage: createJSONStorage(() => sessionStorage),
            /**
             * Don't persist ephemeral things:
             *  - turnstileToken: one-shot, must be regenerated each submit.
             *  - confirmedBooking: success state, should only live in memory.
             */
            partialize: (state) => {
                const { turnstileToken: _a, confirmedBooking: _b, ...rest } = state;
                return rest;
            },
        },
    ),
);
