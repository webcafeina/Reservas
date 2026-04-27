import { useMemo, useState } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import multiMonthPlugin from '@fullcalendar/multimonth';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import esLocale from '@fullcalendar/core/locales/es';
import type { DatesSetArg, EventClickArg } from '@fullcalendar/core';

import { SelectField } from '../../src/components/Field';
import { useSpaces } from '../../src/api/spaces';
import { useCalendarEvents } from '../api/hooks';
import { navigate } from '../useHashRoute';
import type { BookingState } from '../../src/types/booking';

import styles from './Calendar.module.css';

const STATE_LABELS: Record<string, string> = {
    pendiente: 'Pendiente',
    confirmada: 'Confirmada',
    cancelada: 'Cancelada',
    finalizada: 'Finalizada',
};

const STATE_COLORS: Record<string, string> = {
    pendiente: '#f59e0b',
    confirmada: '#10b981',
    cancelada: '#9ca3af',
    finalizada: '#3b82f6',
};

function toIsoDate(d: Date): string {
    // Use UTC components to avoid TZ shifts when FullCalendar gives us a
    // local-midnight Date that crosses the date line.
    const yyyy = d.getUTCFullYear();
    const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
    const dd = String(d.getUTCDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

export function Calendar(): JSX.Element {
    const [range, setRange] = useState<{ from: string; to: string } | null>(null);
    const [salaFilter, setSalaFilter] = useState<number | null>(null);
    const [estadoFilter, setEstadoFilter] = useState<BookingState | ''>('');

    const { data: salasData } = useSpaces({ per_page: 100 });
    const salaOptions = useMemo(() => {
        const items = salasData?.items ?? [];
        return [{ value: '', label: 'Todas las salas' }].concat(
            items.map((s) => ({ value: String(s.id), label: s.title })),
        );
    }, [salasData]);

    const { data, isLoading, isError } = useCalendarEvents(range?.from ?? null, range?.to ?? null, {
        salaId: salaFilter,
        estado: estadoFilter === '' ? null : estadoFilter,
    });

    const events = useMemo(() => data?.events ?? [], [data]);

    const handleDatesSet = (arg: DatesSetArg): void => {
        const from = toIsoDate(arg.start);
        // FullCalendar's `end` is exclusive — back off one day for the
        // inclusive range our API expects.
        const endExclusive = new Date(arg.end);
        endExclusive.setUTCDate(endExclusive.getUTCDate() - 1);
        const to = toIsoDate(endExclusive);
        // Avoid loops by only updating when the range actually changes.
        if (range === null || range.from !== from || range.to !== to) {
            setRange({ from, to });
        }
    };

    const handleEventClick = (arg: EventClickArg): void => {
        const bookingId = arg.event.extendedProps['booking_id'] as number | undefined;
        if (bookingId !== undefined && bookingId > 0) {
            navigate(`bookings/${bookingId}`);
        }
    };

    return (
        <div className={styles.wrapper}>
            <div className={styles.toolbar}>
                <SelectField
                    label="Sala"
                    value={salaFilter === null ? '' : String(salaFilter)}
                    onChange={(e) => {
                        const v = e.target.value;
                        setSalaFilter(v === '' ? null : Number(v));
                    }}
                    options={salaOptions}
                />
                <SelectField
                    label="Estado"
                    value={estadoFilter}
                    onChange={(e) => setEstadoFilter(e.target.value as BookingState | '')}
                    options={[
                        { value: '', label: 'Todos los estados' },
                        { value: 'pendiente', label: 'Pendiente' },
                        { value: 'confirmada', label: 'Confirmada' },
                        { value: 'cancelada', label: 'Cancelada' },
                        { value: 'finalizada', label: 'Finalizada' },
                    ]}
                />
            </div>

            <div className={styles.legend}>
                <span>Estados:</span>
                {(['pendiente', 'confirmada', 'cancelada', 'finalizada'] as const).map((s) => (
                    <span key={s} className={styles.legendItem}>
                        <span
                            className={styles.legendDot}
                            style={{ background: STATE_COLORS[s] }}
                        />
                        {STATE_LABELS[s]}
                    </span>
                ))}
            </div>

            <div className={styles.calendarHost}>
                <FullCalendar
                    plugins={[
                        dayGridPlugin,
                        timeGridPlugin,
                        multiMonthPlugin,
                        listPlugin,
                        interactionPlugin,
                    ]}
                    locale={esLocale}
                    initialView="dayGridMonth"
                    headerToolbar={{
                        left: 'prev,next today',
                        center: 'title',
                        right: 'multiMonthYear,dayGridMonth,timeGridWeek,timeGridDay,listMonth',
                    }}
                    buttonText={{
                        today: 'Hoy',
                        year: 'Año',
                        month: 'Mes',
                        week: 'Semana',
                        day: 'Día',
                        list: 'Lista',
                    }}
                    views={{
                        multiMonthYear: { buttonText: 'Año' },
                        dayGridMonth: { buttonText: 'Mes' },
                        timeGridWeek: { buttonText: 'Semana' },
                        timeGridDay: { buttonText: 'Día' },
                        listMonth: { buttonText: 'Lista' },
                    }}
                    height="auto"
                    contentHeight="auto"
                    expandRows
                    nowIndicator
                    weekNumbers={false}
                    firstDay={1}
                    slotMinTime="07:00:00"
                    slotMaxTime="23:00:00"
                    eventTimeFormat={{
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false,
                    }}
                    events={events}
                    eventClassNames={(arg) => {
                        const estado = arg.event.extendedProps['estado'] as string | undefined;
                        return estado !== undefined ? [`fc-event-${estado}`] : [];
                    }}
                    datesSet={handleDatesSet}
                    eventClick={handleEventClick}
                />

                {isLoading && range !== null && <p className={styles.empty}>Cargando reservas…</p>}
                {isError && <p className={styles.empty}>No se pudieron cargar las reservas.</p>}
            </div>
        </div>
    );
}
