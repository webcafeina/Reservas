import { Button } from '../components/Button';
import { TextField } from '../components/Field';
import { StepFrame } from '../components/StepFrame';
import { useBookingStore } from '../store/bookingStore';

export function Step5Horario(): JSX.Element {
    const horaInicio = useBookingStore((s) => s.horaInicio);
    const horaFin = useBookingStore((s) => s.horaFin);
    const setHora = useBookingStore((s) => s.setHora);
    const setStep = useBookingStore((s) => s.setStep);
    const dateMode = useBookingStore((s) => s.dateMode);

    const invalid = horaInicio >= horaFin;

    return (
        <StepFrame
            title="Horario"
            subtitle="Indica la franja horaria dentro de cada día reservado."
            actions={
                <>
                    <Button
                        variant="ghost"
                        onClick={() => setStep(dateMode === 'recurring' ? 4 : 3)}
                    >
                        ← Atrás
                    </Button>
                    <Button onClick={() => setStep(6)} disabled={invalid} data-step-advance>
                        Siguiente →
                    </Button>
                </>
            }
        >
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 'var(--ra-space-4)',
                }}
            >
                <TextField
                    label="Hora de inicio"
                    type="time"
                    value={horaInicio}
                    onChange={(e) => setHora(e.target.value, horaFin)}
                    required
                />
                <TextField
                    label="Hora de fin"
                    type="time"
                    value={horaFin}
                    onChange={(e) => setHora(horaInicio, e.target.value)}
                    required
                    error={invalid ? 'La hora de fin debe ser posterior a la de inicio.' : null}
                />
            </div>
        </StepFrame>
    );
}
