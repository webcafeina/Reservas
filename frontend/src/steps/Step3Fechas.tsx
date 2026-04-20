import { Button } from '../components/Button';
import { TextField } from '../components/Field';
import { StepFrame } from '../components/StepFrame';
import { useBookingStore, type DateMode } from '../store/bookingStore';

import styles from './Step3Fechas.module.css';

export function Step3Fechas(): JSX.Element {
    const dateMode = useBookingStore((s) => s.dateMode);
    const setDateMode = useBookingStore((s) => s.setDateMode);
    const fechaInicio = useBookingStore((s) => s.fechaInicio);
    const setFechaInicio = useBookingStore((s) => s.setFechaInicio);
    const goBack = useBookingStore((s) => s.goBack);
    const setStep = useBookingStore((s) => s.setStep);

    const handleNext = (): void => {
        if (dateMode === 'recurring') {
            setStep(4);
        } else {
            setStep(5);
        }
    };

    const modeOption = (id: DateMode, title: string, description: string): JSX.Element => (
        <label key={id} className={`${styles.option} ${dateMode === id ? styles.selected : ''}`}>
            <input
                type="radio"
                name="date-mode"
                value={id}
                checked={dateMode === id}
                onChange={() => setDateMode(id)}
            />
            <span>
                <strong>{title}</strong>
                <small>{description}</small>
            </span>
        </label>
    );

    return (
        <StepFrame
            title="¿Cuándo?"
            subtitle="Elige una fecha única o configura una reserva recurrente."
            actions={
                <>
                    <Button variant="ghost" onClick={goBack}>
                        Atrás
                    </Button>
                    <Button onClick={handleNext} disabled={fechaInicio === ''}>
                        Siguiente
                    </Button>
                </>
            }
        >
            <div className={styles.options} role="radiogroup" aria-label="Tipo de fecha">
                {modeOption('single', 'Fecha única', 'Una sola reserva en un día concreto.')}
                {modeOption(
                    'recurring',
                    'Recurrente',
                    'Varias fechas siguiendo un patrón (diario, semanal, mensual…).',
                )}
            </div>
            <TextField
                label={dateMode === 'recurring' ? 'Fecha de inicio de la serie' : 'Fecha'}
                type="date"
                value={fechaInicio}
                onChange={(e) => setFechaInicio(e.target.value)}
                required
            />
        </StepFrame>
    );
}
