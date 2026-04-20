import type { Sala } from '../types/sala';

import styles from './SalaCard.module.css';

interface SalaCardProps {
    sala: Sala;
    selected: boolean;
    onSelect: (id: number) => void;
}

export function SalaCard({ sala, selected, onSelect }: SalaCardProps): JSX.Element {
    return (
        <button
            type="button"
            className={`${styles.card} ${selected ? styles.selected : ''}`}
            onClick={() => onSelect(sala.id)}
            aria-pressed={selected}
        >
            <div className={styles.image}>
                {sala.featured_image_url !== null ? (
                    <img src={sala.featured_image_url} alt="" loading="lazy" />
                ) : (
                    <div className={styles.placeholder} aria-hidden="true" />
                )}
                {sala.es_cpa && <span className={styles.badge}>CPA</span>}
            </div>
            <div className={styles.content}>
                <h3 className={styles.title}>{sala.title}</h3>
                {sala.excerpt !== '' && <p className={styles.excerpt}>{sala.excerpt}</p>}
                <dl className={styles.meta}>
                    <div>
                        <dt>Aforo</dt>
                        <dd>
                            {sala.aforo_min}–{sala.aforo_max}
                        </dd>
                    </div>
                    {sala.edificios.length > 0 && (
                        <div>
                            <dt>Edificio</dt>
                            <dd>{sala.edificios.map((e) => e.name).join(', ')}</dd>
                        </div>
                    )}
                </dl>
                {sala.servicios.length > 0 && (
                    <ul className={styles.tags}>
                        {sala.servicios.map((s) => (
                            <li key={s.id}>{s.name}</li>
                        ))}
                    </ul>
                )}
            </div>
        </button>
    );
}
