export interface UserProfile {
    id?: number | null;
    user_id?: number | null;
    nif: string;
    nombre: string;
    primer_apellido: string;
    segundo_apellido: string | null;
    via: string;
    numero: string;
    letra: string | null;
    escalera: string | null;
    piso: string | null;
    puerta: string | null;
    municipio: string;
    provincia: string;
    codigo_postal: string;
    telefono_fijo: string | null;
    movil: string;
    email: string;
}

export const emptyProfile = (): UserProfile => ({
    nif: '',
    nombre: '',
    primer_apellido: '',
    segundo_apellido: null,
    via: '',
    numero: '',
    letra: null,
    escalera: null,
    piso: null,
    puerta: null,
    municipio: '',
    provincia: '',
    codigo_postal: '',
    telefono_fijo: null,
    movil: '',
    email: '',
});
