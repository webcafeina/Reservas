import { useState } from 'react';

import { Button } from '../../src/components/Button';
import { SelectField, TextField } from '../../src/components/Field';
import {
    buildExportUrl,
    useAdminBookings,
    type BookingFilters,
    type BookingSort,
} from '../api/hooks';
import { navigate } from '../useHashRoute';
import type { Booking, BookingState } from '../../src/types/booking';
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
    const [expanded, setExpanded] = useState<Set<number>>(new Set());
    const { data, isLoading, isError } = useAdminBookings(filters);

    const updateFilter = <K extends keyof BookingFilters>(
        key: K,
        value: BookingFilters[K],
    ): void => {
        setFilters((prev) => ({ ...prev, [key]: value, page: 1 }));
    };

    const toggleExpanded = (id: number): void => {
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const sortMode: BookingSort = filters.sort ?? 'created_desc';

    const cycleFechasSort = (): void => {
        setFilters((prev) => {
            const current: BookingSort = prev.sort ?? 'created_desc';
            const next: BookingSort =
                current === 'created_desc'
                    ? 'start_desc'
                    : current === 'start_desc'
                      ? 'start_asc'
                      : 'created_desc';
            return { ...prev, sort: next, page: 1 };
        });
    };

    const fechasSortIndicator =
        sortMode === 'start_desc' ? '↓' : sortMode === 'start_asc' ? '↑' : '↕';
    const fechasSortTitle =
        sortMode === 'start_desc'
            ? 'Ordenado por fecha de inicio (más reciente primero). Click para fecha más lejana primero.'
            : sortMode === 'start_asc'
              ? 'Ordenado por fecha de inicio (más lejana primero). Click para volver al orden por defecto.'
              : 'Ordenado por orden de registro. Click para fecha más reciente primero.';

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
                                <th>
                                    <button
                                        type="button"
                                        className={styles.sortHeaderBtn}
                                        onClick={cycleFechasSort}
                                        title={fechasSortTitle}
                                    >
                                        Fechas{' '}
                                        <span className={styles.sortIndicator} aria-hidden="true">
                                            {fechasSortIndicator}
                                        </span>
                                    </button>
                                </th>
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
                                const isRecurrent = b.rrule !== null && b.rrule !== '';
                                return (
                                    <tr key={b.id}>
                                        <td>
                                            <span className={styles.idCell}>
                                                {b.id}
                                                {isRecurrent && <RecurrentIcon />}
                                            </span>
                                        </td>
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
                                                    {b.profile?.empresa !== undefined &&
                                                        b.profile?.empresa !== null &&
                                                        b.profile.empresa !== '' && (
                                                            <>
                                                                <br />
                                                                <small>{b.profile.empresa}</small>
                                                            </>
                                                        )}
                                                </>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td>
                                            <FechasCell
                                                booking={b}
                                                expanded={expanded.has(b.id)}
                                                onToggle={() => toggleExpanded(b.id)}
                                            />
                                        </td>
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

/**
 * Inline "loop" icon (Lucide / Feather "repeat" shape) to flag a
 * recurring reservation next to its #id in the listing. Uses
 * `currentColor` so the surrounding text/badge styling controls hue.
 */
function RecurrentIcon(): JSX.Element {
    return (
        <span
            className={styles.recurrentBadge}
            title="Reserva recurrente"
            aria-label="Reserva recurrente"
        >
            <svg
                viewBox="0 0 24 24"
                width="14"
                height="14"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
            >
                <polyline points="17 1 21 5 17 9" />
                <path d="M3 11V9a4 4 0 0 1 4-4h14" />
                <polyline points="7 23 3 19 7 15" />
                <path d="M21 13v2a4 4 0 0 1-4 4H3" />
            </svg>
        </span>
    );
}

interface FechasCellProps {
    booking: Booking;
    expanded: boolean;
    onToggle: () => void;
}

/**
 * "Fechas" cell renderer. For one-off bookings just shows the start
 * date. For recurring ones it shows a single-line summary
 * (range · count · cadence) with a ▸/▾ toggle that expands an
 * inline `<ul>` of every individual fecha.
 */
function FechasCell({ booking, expanded, onToggle }: FechasCellProps): JSX.Element {
    const isRecurrent = booking.rrule !== null && booking.rrule !== '';

    if (!isRecurrent) {
        return <>{formatDateEs(booking.fecha_inicio)}</>;
    }

    const fechas = booking.fechas;
    const last = fechas.length > 0 ? fechas[fechas.length - 1] : booking.fecha_inicio;
    const summary = `${formatDateEs(booking.fecha_inicio)} → ${formatDateEs(last)} · ${fechas.length} fecha${fechas.length === 1 ? '' : 's'}`;

    return (
        <div>
            <button
                type="button"
                className={styles.recurrentToggleBtn}
                onClick={onToggle}
                aria-expanded={expanded}
            >
                <span className={styles.recurrentChevron} aria-hidden="true">
                    {expanded ? '▾' : '▸'}
                </span>
                <span className={styles.recurrentSummary}>{summary}</span>
            </button>
            {expanded && fechas.length > 0 && (
                <ul className={styles.recurrentDates}>
                    {fechas.map((iso) => (
                        <li key={iso}>{formatDateEs(iso)}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}
