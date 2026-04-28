import { z } from 'zod';

import { isProvincia } from '../data/provincias';
import type { UserProfile } from '../types/profile';

export const profileSchema = z.object({
    nif: z.string().min(1, 'Requerido'),
    nombre: z.string().min(1, 'Requerido'),
    primer_apellido: z.string().min(1, 'Requerido'),
    via: z.string().min(1, 'Requerido'),
    numero: z.string().min(1, 'Requerido'),
    municipio: z.string().min(1, 'Requerido'),
    // `provincia` must match the canonical Spanish list. The dropdown
    // is the only entry path in the UI; the refine guards against
    // legacy values pre-filled from old user profiles or admin edits
    // of bookings created before this validation tightened.
    provincia: z
        .string()
        .min(1, 'Requerido')
        .refine(isProvincia, { message: 'Selecciona una provincia de la lista' }),
    codigo_postal: z
        .string()
        .min(1, 'Requerido')
        .regex(/^\d{5}$/, 'CP inválido (5 dígitos)'),
    movil: z.string().min(9, 'Móvil inválido'),
    email: z.string().email('Email inválido'),
});

export type ProfileErrors = Partial<Record<keyof UserProfile, string>>;

export function validateProfile(profile: UserProfile): ProfileErrors {
    const result = profileSchema.safeParse(profile);
    if (result.success) return {};

    const errors: ProfileErrors = {};
    for (const issue of result.error.issues) {
        const key = issue.path[0] as keyof UserProfile | undefined;
        if (key !== undefined && errors[key] === undefined) {
            errors[key] = issue.message;
        }
    }
    return errors;
}

export function isValidProfile(profile: UserProfile): boolean {
    return profileSchema.safeParse(profile).success;
}
