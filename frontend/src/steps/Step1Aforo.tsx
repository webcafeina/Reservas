import { Button } from '../components/Button';
import { TextField } from '../components/Field';
import { StepFrame } from '../components/StepFrame';
import { useServicios } from '../api/taxonomies';
import { useBookingStore } from '../store/bookingStore';

import styles from './Step1Aforo.module.css';

export function Step1Aforo(): JSX.Element {
    const criteria = useBookingStore((s) => s.criteria);
    const setCriteria = useBookingStore((s) => s.setCriteria);
    const goNext = useBookingStore((s) => s.goNext);

    const { data: servicios, isLoading } = useServicios();

    const toNumberOrNull = (v: string): number | null => {
        if (v.trim() === '') return null;
        const n = Number(v);
        return Number.isFinite(n) && n >= 0 ? Math.floor(n) : null;
    };

    const toggleServicio = (id: number): void => {
        const set = new Set(criteria.servicios);
        if (set.has(id)) {
            set.delete(id);
        } else {
            set.add(id);
        }
        setCriteria({ servicios: Array.from(set) });
    };

    return (
        <StepFrame
            title="¿Qué necesitas?"
            subtitle="Este paso es opcional — sirve para filtrar las salas que verás a continuación. Puedes saltarlo y ver todas las disponibles."
            actions={
                <>
                    <span />
                    <Button onClick={goNext} data-step-advance>
                        Siguiente →
                    </Button>
                </>
            }
        >
            <div className={styles.aforo}>
                <TextField
                    label="Aforo mínimo"
                    type="number"
                    min={0}
                    step={1}
                    value={criteria.aforoMin ?? ''}
                    onChange={(e) => setCriteria({ aforoMin: toNumberOrNull(e.target.value) })}
                    hint="Número mínimo de asistentes esperados."
                />
                <TextField
                    label="Aforo máximo"
                    type="number"
                    min={0}
                    step={1}
                    value={criteria.aforoMax ?? ''}
                    onChange={(e) => setCriteria({ aforoMax: toNumberOrNull(e.target.value) })}
                    hint="Deja en blanco si no te importa el límite superior."
                />
            </div>

            <fieldset className={styles.services}>
                <legend className={styles.servicesLegend}>Servicios necesarios</legend>
                {isLoading && <p className={styles.loading}>Cargando servicios…</p>}
                {servicios !== undefined && servicios.length === 0 && (
                    <p className={styles.loading}>
                        Aún no hay servicios configurados. Continúa sin filtrar.
                    </p>
                )}
                {servicios !== undefined && servicios.length > 0 && (
                    <div className={styles.servicesGrid}>
                        {servicios.map((s) => {
                            const checked = criteria.servicios.includes(s.id);
                            return (
                                <label
                                    key={s.id}
                                    className={`${styles.chip} ${checked ? styles.chipChecked : ''}`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={checked}
                                        onChange={() => toggleServicio(s.id)}
                                    />
                                    <span>{s.name}</span>
                                </label>
                            );
                        })}
                    </div>
                )}
            </fieldset>
        </StepFrame>
    );
}
