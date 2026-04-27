import { useState } from 'react';

import { Button } from '../../src/components/Button';
import { TextField } from '../../src/components/Field';
import { buildExportUrl, useAdminStats } from '../api/hooks';
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

function ExportCsvModule(): JSX.Element {
    const [from, setFrom] = useState<string>(monthsAgo(1));
    const [to, setTo] = useState<string>(today());

    const apply = (rangeFrom: string, rangeTo: string): void => {
        setFrom(rangeFrom);
        setTo(rangeTo);
    };

    return (
        <section className={styles.module}>
            <h2>
                Exportar reservas a CSV
                <small>filtra por rango de fecha de inicio</small>
            </h2>
            <div className={styles.exportRow}>
                <div className={styles.exportFields}>
                    <TextField
                        label="Desde"
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                    />
                    <TextField
                        label="Hasta"
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                    />
                </div>
                <div className={styles.exportPresets}>
                    <Button variant="ghost" onClick={() => apply(monthsAgo(1), today())}>
                        Último mes
                    </Button>
                    <Button variant="ghost" onClick={() => apply(monthsAgo(3), today())}>
                        Último trimestre
                    </Button>
                    <Button variant="ghost" onClick={() => apply(monthsAgo(12), today())}>
                        Último año
                    </Button>
                    <a
                        href={buildExportUrl({ from, to })}
                        className={styles.exportLink}
                        title="Descargar CSV con las reservas del rango seleccionado"
                    >
                        Exportar CSV
                    </a>
                </div>
            </div>
        </section>
    );
}
