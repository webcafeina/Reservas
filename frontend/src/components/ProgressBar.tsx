import styles from './ProgressBar.module.css';

interface ProgressBarProps {
    current: number;
    total: number;
    labels: readonly string[];
}

export function ProgressBar({ current, total, labels }: ProgressBarProps): JSX.Element {
    return (
        <div className={styles.wrapper} aria-label={`Paso ${current} de ${total}`}>
            <ol className={styles.steps}>
                {labels.map((label, idx) => {
                    const index = idx + 1;
                    const state =
                        index < current ? 'done' : index === current ? 'active' : 'pending';
                    return (
                        <li key={label} className={`${styles.step} ${styles[state]}`}>
                            <span className={styles.bullet} aria-hidden="true">
                                {index}
                            </span>
                            <span className={styles.label}>{label}</span>
                        </li>
                    );
                })}
            </ol>
            <div
                className={styles.bar}
                role="progressbar"
                aria-valuemin={1}
                aria-valuemax={total}
                aria-valuenow={current}
            >
                <div
                    className={styles.fill}
                    style={{ width: `${Math.round(((current - 1) / (total - 1)) * 100)}%` }}
                />
            </div>
        </div>
    );
}
