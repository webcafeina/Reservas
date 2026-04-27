import { useState } from 'react';

import { Button } from '../../src/components/Button';
import { SelectField, TextField } from '../../src/components/Field';
import { buildExportUrl, useAdminBookings, type BookingFilters } from '../api/hooks';
import { navigate } from '../useHashRoute';
import type { BookingState } from '../../src/types/booking';
import { formatDateEs } from '../../src/utils/dateFormat';

import styles from './BookingsList.module.css';

const STATE_BADGE: Record<BookingState, string> = {
    pendiente: styles.statePendiente ?? '',
    confirmada: styles.stateConfirmada ?? '',
    cancelada: styles.stateCancelada ?? '',
    finalizada: styles.stateFinalizada ?? '',
};

const STATE_LABEL: Record<BookingState | '', string> = {
    '': 'Todos los estados',
    pendiente: 'Pendiente',
    confirmada: 'Confirmada',
    cancelada: 'Cancelada',
    finalizada: 'Finalizada',
};

export function BookingsList(): JSX.Element {
    const [filters, setFilters] = useState<BookingFilters>({ page: 1, per_page: 20 });
    const { data, isLoading, isError } = useAdminBookings(filters);

    const updateFilter = <K extends keyof BookingFilters>(
        key: K,
        value: BookingFilters[K],
    ): void => {
        setFilters((prev) => ({ ...prev, [key]: value, page: 1 }));
    };

    const totalPages = data !== undefined ? Math.max(1, Math.ceil(data.total / data.per_page)) : 1;

    return (
        <div className={styles.wrapper}>
            <div className={styles.toolbar}>
                <Button onClick={() => navigate('bookings/new')}>+ Crear reserva</Button>
                <a
                    href={buildExportUrl({ ...filters, page: undefined, per_page: undefined })}
                    className={styles.exportLink}
                    title="Exportar CSV con los filtros actuales"
                >
                    Exportar CSV con filtros actuales
                </a>
            </div>
            <header className={styles.filters}>
                <SelectField
                    label="Estado"
                    value={filters.estado ?? ''}
                    onChange={(e) => updateFilter('estado', e.target.value as BookingState | '')}
                    options={(Object.keys(STATE_LABEL) as Array<BookingState | ''>).map((v) => ({
                        value: v,
                        label: STATE_LABEL[v],
                    }))}
                />
                <TextField
                    label="Email del solicitante"
                    type="email"
                    value={filters.email ?? ''}
                    onChange={(e) => updateFilter('email', e.target.value)}
                />
                <TextField
                    label="Desde"
                    type="date"
                    value={filters.from ?? ''}
                    onChange={(e) => updateFilter('from', e.target.value)}
                />
                <TextField
                    label="Hasta"
                    type="date"
                    value={filters.to ?? ''}
                    onChange={(e) => updateFilter('to', e.target.value)}
                />
            </header>

            {isLoading && <p>Cargando reservas…</p>}
            {isError && <p>Error al cargar reservas.</p>}
            {data !== undefined && (
                <>
                    <table className={styles.table}>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Estado</th>
                                <th>Sala</th>
                                <th>Solicitante</th>
                                <th>Fecha inicio</th>
                                <th>Horario</th>
                                <th>Objeto</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.items.length === 0 && (
                                <tr>
                                    <td colSpan={8} className={styles.empty}>
                                        No hay reservas con estos filtros.
                                    </td>
                                </tr>
                            )}
                            {data.items.map((b) => {
                                const solicitante =
                                    b.profile !== null && b.profile !== undefined
                                        ? [
                                              b.profile.nombre,
                                              b.profile.primer_apellido,
                                              b.profile.segundo_apellido,
                                          ]
                                              .filter((s) => s !== null && s !== '')
                                              .join(' ')
                                        : '';
                                return (
                                    <tr key={b.id}>
                                        <td>{b.id}</td>
                                        <td>
                                            <span
                                                className={`${styles.badge} ${STATE_BADGE[b.estado] ?? ''}`}
                                            >
                                                {STATE_LABEL[b.estado]}
                                            </span>
                                        </td>
                                        <td>{b.sala_title ?? `#${b.sala_id}`}</td>
                                        <td>
                                            {solicitante !== '' ? (
                                                <>
                                                    <strong>{solicitante}</strong>
                                                    {b.profile?.email !== undefined &&
                                                        b.profile.email !== '' && (
                                                            <>
                                                                <br />
                                                                <small>{b.profile.email}</small>
                                                            </>
                                                        )}
                                                </>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td>{formatDateEs(b.fecha_inicio)}</td>
                                        <td>
                                            {b.hora_inicio.slice(0, 5)} – {b.hora_fin.slice(0, 5)}
                                        </td>
                                        <td className={styles.objeto}>{b.objeto_reserva}</td>
                                        <td>
                                            <Button
                                                variant="secondary"
                                                onClick={() => navigate(`bookings/${b.id}`)}
                                            >
                                                Ver
                                            </Button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>

                    <footer className={styles.pager}>
                        <span>
                            {data.total} resultados · página {data.page} de {totalPages}
                        </span>
                        <div className={styles.pagerBtns}>
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    setFilters((prev) => ({
                                        ...prev,
                                        page: Math.max(1, (prev.page ?? 1) - 1),
                                    }))
                                }
                                disabled={data.page <= 1}
                            >
                                Anterior
                            </Button>
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    setFilters((prev) => ({
                                        ...prev,
                                        page: Math.min(totalPages, (prev.page ?? 1) + 1),
                                    }))
                                }
                                disabled={data.page >= totalPages}
                            >
                                Siguiente
                            </Button>
                        </div>
                    </footer>
                </>
            )}
        </div>
    );
}
