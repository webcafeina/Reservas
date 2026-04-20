import { Button } from '../components/Button';
import { TextField } from '../components/Field';
import { StepFrame } from '../components/StepFrame';
import { useBookingStore } from '../store/bookingStore';

export function Step1Aforo(): JSX.Element {
    const criteria = useBookingStore((s) => s.criteria);
    const setCriteria = useBookingStore((s) => s.setCriteria);
    const goNext = useBookingStore((s) => s.goNext);

    const toNumberOrNull = (v: string): number | null => {
        if (v.trim() === '') return null;
        const n = Number(v);
        return Number.isFinite(n) && n >= 0 ? Math.floor(n) : null;
    };

    return (
        <StepFrame
            title="¿Cuántas personas?"
            subtitle="Indícanos el aforo aproximado para filtrar las salas adecuadas. Este paso es opcional."
            actions={
                <>
                    <span />
                    <Button onClick={goNext}>Siguiente</Button>
                </>
            }
        >
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--ra-space-4)' }}>
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
        </StepFrame>
    );
}
