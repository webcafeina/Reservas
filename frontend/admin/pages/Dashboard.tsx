import { useState } from 'react';

import { Button } from '../../src/components/Button';
import { TextField } from '../../src/components/Field';
import { buildExportUrl, useAdminStats } from '../api/hooks';

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
    const [from, setFrom] = useState<string>(monthsAgo(1));
    const [to, setTo] = useState<string>(today());

    const { data, isLoading, isError } = useAdminStats({ from, to });

    const applyPreset = (rangeFrom: string, rangeTo: string): void => {
        setFrom(rangeFrom);
        setTo(rangeTo);
    };

    return (
        <div className={styles.wrapper}>
            <section className={styles.rangeBar}>
                <div className={styles.rangeFields}>
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
                <div className={styles.rangePresets}>
                    <Button variant="ghost" onClick={() => applyPreset(monthsAgo(1), today())}>
                        Último mes
                    </Button>
                    <Button variant="ghost" onClick={() => applyPreset(monthsAgo(3), today())}>
                        Último trimestre
                    </Button>
                    <Button variant="ghost" onClick={() => applyPreset(monthsAgo(12), today())}>
                        Último año
                    </Button>
                    <a
                        href={buildExportUrl({ from, to })}
                        className={styles.exportLink}
                        title="Exportar las reservas del rango como CSV"
                    >
                        Exportar CSV
                    </a>
                </div>
            </section>

            {isLoading && <p>Cargando estadísticas…</p>}
            {isError && <p>No se pudieron cargar las estadísticas.</p>}
            {data !== undefined && (
                <>
                    <section className={styles.kpis}>
                        {(Object.keys(STATE_LABEL) as Array<keyof typeof STATE_LABEL>).map(
                            (key) => (
                                <article
                                    key={key}
                                    className={`${styles.kpi} ${styles[`kpi_${key}`] ?? ''}`}
                                >
                                    <header>{STATE_LABEL[key]}</header>
                                    <strong>{data.by_state[key as 'pendiente'] ?? 0}</strong>
                                </article>
                            ),
                        )}
                        <article className={`${styles.kpi} ${styles.kpi_highlight}`}>
                            <header>Esta semana</header>
                            <strong>{data.this_week}</strong>
                        </article>
                        <article className={`${styles.kpi} ${styles.kpi_highlight}`}>
                            <header>Confirmadas próximos 7 días</header>
                            <strong>{data.upcoming}</strong>
                        </article>
                    </section>

                    <section className={styles.section}>
                        <h2>
                            Salas más reservadas{' '}
                            <small>
                                ({data.range.from} → {data.range.to})
                            </small>
                        </h2>
                        {data.per_sala.length === 0 && <p>Sin datos en el rango seleccionado.</p>}
                        {data.per_sala.length > 0 && (
                            <ol className={styles.ranking}>
                                {data.per_sala.map((s) => (
                                    <li key={s.sala_id}>
                                        <span>{s.title || `#${s.sala_id}`}</span>
                                        <strong>{s.total}</strong>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                </>
            )}
        </div>
    );
}
