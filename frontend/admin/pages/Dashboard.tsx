import { useAdminStats } from '../api/hooks';

import styles from './Dashboard.module.css';

const STATE_LABEL: Record<string, string> = {
    pendiente: 'Pendientes',
    confirmada: 'Confirmadas',
    cancelada: 'Canceladas',
    finalizada: 'Finalizadas',
};

export function Dashboard(): JSX.Element {
    const { data, isLoading, isError } = useAdminStats();

    if (isLoading) {
        return <p>Cargando estadísticas…</p>;
    }
    if (isError || data === undefined) {
        return <p>No se pudieron cargar las estadísticas.</p>;
    }

    return (
        <div className={styles.wrapper}>
            <section className={styles.kpis}>
                {(Object.keys(STATE_LABEL) as Array<keyof typeof STATE_LABEL>).map((key) => (
                    <article key={key} className={`${styles.kpi} ${styles[`kpi_${key}`] ?? ''}`}>
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

            <section className={styles.section}>
                <h2>Salas más reservadas (últimos 30 días)</h2>
                {data.per_sala.length === 0 && <p>Sin datos aún.</p>}
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
        </div>
    );
}
