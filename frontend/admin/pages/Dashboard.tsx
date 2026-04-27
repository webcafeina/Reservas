import { useMemo, useState } from 'react';

import { Button } from '../../src/components/Button';
import { SelectField, TextField } from '../../src/components/Field';
import { useSpaces } from '../../src/api/spaces';
import { buildExportUrl, useAdminStats } from '../api/hooks';
import type { BookingState } from '../../src/types/booking';
import { Calendar } from './Calendar';

import styles from './Dashboard.module.css';

const STATE_LABEL: Record<string, string> = {
    pendiente: 'Pendientes',
    confirmada: 'Confirmadas',
    cancelada: 'Canceladas',
    finalizada: 'Finalizadas',
};

function monthsAgo(n: number): string {
    const d = new Date();
    d.setMonth(d.getMonth() - n);
    return d.toISOString().slice(0, 10);
}

function monthsAhead(n: number): string {
    const d = new Date();
    d.setMonth(d.getMonth() + n);
    return d.toISOString().slice(0, 10);
}

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

export function Dashboard(): JSX.Element {
    const { data, isLoading, isError } = useAdminStats();

    return (
        <div className={styles.wrapper}>
            {isLoading && <p>Cargando estadísticas…</p>}
            {isError && <p>No se pudieron cargar las estadísticas.</p>}
            {data !== undefined && (
                <section className={styles.kpis}>
                    {Object.keys(STATE_LABEL).map((key) => (
                        <article
                            key={key}
                            className={`${styles.kpi} ${styles[`kpi_${key}`] ?? ''}`}
                        >
                            <header>{STATE_LABEL[key]}</header>
                            <strong>{data.by_state[key as 'pendiente'] ?? 0}</strong>
                        </article>
                    ))}
                    <article className={`${styles.kpi} ${styles.kpi_highlight}`}>
                        <header>Esta semana</header>
                        <strong>{data.this_week}</strong>
                    </article>
                    <article className={`${styles.kpi} ${styles.kpi_highlight}`}>
                        <header>Confirmadas próximos 7 días</header>
                        <strong>{data.upcoming}</strong>
                    </article>
                </section>
            )}

            <section className={styles.module}>
                <h2>Calendario</h2>
                <Calendar />
            </section>

            <ExportCsvModule />
        </div>
    );
}

type ExportPreset =
    | 'last-month'
    | 'last-quarter'
    | 'last-year'
    | 'next-month'
    | 'next-quarter'
    | 'next-year'
    | 'all';

function ExportCsvModule(): JSX.Element {
    const [from, setFrom] = useState<string>(monthsAgo(1));
    const [to, setTo] = useState<string>(today());
    const [salaId, setSalaId] = useState<number | null>(null);
    const [estado, setEstado] = useState<BookingState | ''>('');
    // Tracks which preset button is currently "selected". Cleared whenever
    // the user manually edits any of the four filter fields, so the
    // highlight only stays on while the values actually match the preset.
    const [activePreset, setActivePreset] = useState<ExportPreset | null>(null);

    const { data: salasData } = useSpaces({ per_page: 100 });
    const salaOptions = useMemo(() => {
        const items = salasData?.items ?? [];
        return [{ value: '', label: 'Todas las salas' }].concat(
            items.map((s) => ({ value: String(s.id), label: s.title })),
        );
    }, [salasData]);

    const applyPreset = (id: ExportPreset, rangeFrom: string, rangeTo: string): void => {
        setFrom(rangeFrom);
        setTo(rangeTo);
        setActivePreset(id);
    };

    const resetAll = (): void => {
        setFrom('');
        setTo('');
        setSalaId(null);
        setEstado('');
        setActivePreset('all');
    };

    const onFromChange = (v: string): void => {
        setFrom(v);
        setActivePreset(null);
    };
    const onToChange = (v: string): void => {
        setTo(v);
        setActivePreset(null);
    };
    const onSalaChange = (v: string): void => {
        setSalaId(v === '' ? null : Number(v));
        setActivePreset(null);
    };
    const onEstadoChange = (v: string): void => {
        setEstado(v as BookingState | '');
        setActivePreset(null);
    };

    const exportUrl = buildExportUrl({
        from: from === '' ? undefined : from,
        to: to === '' ? undefined : to,
        sala_id: salaId ?? undefined,
        estado: estado === '' ? undefined : estado,
    });

    const presetClass = (id: ExportPreset): string =>
        `${styles.presetBtn} ${activePreset === id ? styles.presetBtnActive : ''}`;

    return (
        <section className={styles.module}>
            <h2>Exportar reservas a CSV</h2>
            <p className={styles.moduleSubtitle}>Filtra por fecha, sala o estado.</p>
            <div className={styles.exportFields}>
                <TextField
                    label="Desde"
                    type="date"
                    value={from}
                    onChange={(e) => onFromChange(e.target.value)}
                />
                <TextField
                    label="Hasta"
                    type="date"
                    value={to}
                    onChange={(e) => onToChange(e.target.value)}
                />
                <SelectField
                    label="Sala"
                    value={salaId === null ? '' : String(salaId)}
                    onChange={(e) => onSalaChange(e.target.value)}
                    options={salaOptions}
                />
                <SelectField
                    label="Estado"
                    value={estado}
                    onChange={(e) => onEstadoChange(e.target.value)}
                    options={[
                        { value: '', label: 'Todos los estados' },
                        { value: 'pendiente', label: 'Pendiente' },
                        { value: 'confirmada', label: 'Confirmada' },
                        { value: 'cancelada', label: 'Cancelada' },
                        { value: 'finalizada', label: 'Finalizada' },
                    ]}
                />
            </div>
            <div className={styles.exportActions}>
                <fieldset className={styles.presetGroup}>
                    <legend className={styles.presetGroupLabel}>Pasado</legend>
                    <Button
                        variant="ghost"
                        className={presetClass('last-month')}
                        onClick={() => applyPreset('last-month', monthsAgo(1), today())}
                    >
                        Último mes
                    </Button>
                    <Button
                        variant="ghost"
                        className={presetClass('last-quarter')}
                        onClick={() => applyPreset('last-quarter', monthsAgo(3), today())}
                    >
                        Último trimestre
                    </Button>
                    <Button
                        variant="ghost"
                        className={presetClass('last-year')}
                        onClick={() => applyPreset('last-year', monthsAgo(12), today())}
                    >
                        Último año
                    </Button>
                </fieldset>
                <fieldset className={styles.presetGroup}>
                    <legend className={styles.presetGroupLabel}>Futuro</legend>
                    <Button
                        variant="ghost"
                        className={presetClass('next-month')}
                        onClick={() => applyPreset('next-month', today(), monthsAhead(1))}
                    >
                        Mes siguiente
                    </Button>
                    <Button
                        variant="ghost"
                        className={presetClass('next-quarter')}
                        onClick={() => applyPreset('next-quarter', today(), monthsAhead(3))}
                    >
                        Trimestre siguiente
                    </Button>
                    <Button
                        variant="ghost"
                        className={presetClass('next-year')}
                        onClick={() => applyPreset('next-year', today(), monthsAhead(12))}
                    >
                        Año siguiente
                    </Button>
                </fieldset>
                <div className={styles.exportFinal}>
                    <Button
                        variant="secondary"
                        className={`${styles.allBtn} ${activePreset === 'all' ? styles.allBtnActive : ''}`}
                        onClick={resetAll}
                    >
                        Todas las reservas
                    </Button>
                    <a
                        href={exportUrl}
                        className={styles.exportLink}
                        title="Descargar CSV con las reservas que cumplen los filtros actuales"
                    >
                        Exportar CSV
                    </a>
                </div>
            </div>
        </section>
    );
}
