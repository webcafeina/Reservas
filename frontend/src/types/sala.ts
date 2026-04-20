export interface Taxon {
    id: number;
    name: string;
    slug: string;
}

export interface Sala {
    id: number;
    slug: string;
    title: string;
    description: string;
    excerpt: string;
    featured_image_url: string | null;
    aforo_min: number;
    aforo_max: number;
    disponible: boolean;
    es_cpa: boolean;
    edificios: Taxon[];
    servicios: Taxon[];
}

export interface SpacesQuery {
    aforo_min?: number | undefined;
    aforo_max?: number | undefined;
    edificio?: number | undefined;
    servicios?: number[] | undefined;
    disponible?: boolean | undefined;
    per_page?: number | undefined;
    page?: number | undefined;
}
